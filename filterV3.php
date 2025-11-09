<?
include_once ($_SERVER['DOCUMENT_ROOT'].'/config.php');
include_once ($_SERVER['DOCUMENT_ROOT'].'/content_v2/function.php');

function translateSizeLabel($size) {
    
    $translated = $size;

   
    $lower = mb_strtolower($size, 'UTF-8');

  
    if (preg_match('/(\d+)\s*years?/i', $lower, $m)) {
        $num = (int)$m[1];
        if ($num % 10 == 1 && $num % 100 != 11) {
            $translated = str_replace($m[0], "$num год", $size);
        } elseif (in_array($num % 10, [2,3,4]) && !in_array($num % 100, [12,13,14])) {
            $translated = str_replace($m[0], "$num года", $size);
        } else {
            $translated = str_replace($m[0], "$num лет", $size);
        }
    }

   
    if (preg_match('/(\d+)\s*[-–]?\s*(\d+)?\s*months?/i', $lower, $m)) {
        if (isset($m[2])) {
            $translated = str_replace($m[0], "{$m[1]}–{$m[2]} мес.", $size);
        } else {
            $translated = str_replace($m[0], "{$m[1]} мес.", $size);
        }
    }

    
    if (preg_match('/(\d+)\s*[-–]\s*(\d+)\s*months?/i', $lower, $m)) {
        $translated = str_replace($m[0], "{$m[1]}–{$m[2]} мес.", $size);
    }

   
    $translated = preg_replace('/\s*\([^)]*\)/', '', $translated);

    return trim($translated);
}


$saleSort= isset($_GET['sale']) ? (int)$_GET['sale'] : 0;
$brendParam= isset($_GET['brend']) ? $_GET['brend'] : '';
$sizeParam= isset($_GET['size']) ? $_GET['size'] : '';
$SetCatIDs= isset($_GET['cat']) ? $_GET['cat'] : 0;
// Гарантированно определяем переменную
$SetCatIDsNOW = isset($SetCatIDsNOW) ? (int)$SetCatIDsNOW : 0;
$SetCatIDss = $SetCatIDsNOW ?: $SetCatIDs;
if (empty($SetCatIDss)) $SetCatIDss = 0;

$sectionName1 = isset($sectionName1) ? $sectionName1 : '';
$selectedSizes = isset($_GET['size']) ? explode(',', $_GET['size']) : [];
$selectedCount = count($selectedSizes);

$sizeSort='';
$brendSort='';
$catSort='';
$types[1]='women';
$sectionName1='WOMAN';
if ($SetCatIDss > 0) {
    $cats_0 = mysqli_query($dbcnx, 
        "SELECT menuLevel, parent, id_category, sectionName,idSale,categoryUrl
         FROM category 
         WHERE active=1 AND id_category=".$SetCatIDss
    );
    $catRow = mysqli_fetch_assoc($cats_0);
    $sectionName1 = $catRow['sectionName'] ?? $sectionName1;
} else {
    $catRow = null;
}


$Level=0;$saleIX='';
if ($catRow>0){
  $Level=$catRow['menuLevel'];$sectionName1=$catRow['sectionName'];$idSale=$catRow['idSale']; $saleIX=$catRow['categoryUrl']; if ($idSale==1){$saleSort=1;}
}
$catSort='';
//echo '<br>'.$saleIX;
if (($Level>1)and($saleIX!='woman-sale')and($saleIX!='man-sale')and($saleIX!='kids-sale')and($saleIX!='home-sale')){
//echo '<br>'.$SetCatIDss;
$cats_2 = mysqli_query($dbcnx, "
    SELECT c.id_category, c.parent
    FROM category c
    WHERE (c.parent = $SetCatIDss OR c.id_category = $SetCatIDss) AND c.active = 1
    UNION
    SELECT c2.id_category, c2.parent
    FROM category c1
    JOIN category c2 ON c2.parent = c1.id_category
    WHERE (c1.parent = $SetCatIDss OR c1.id_category = $SetCatIDss) AND c2.active = 1
");

if (mysqli_num_rows($cats_2)>0){
     $iy=0;
     while($cats = mysqli_fetch_array($cats_2))
	   {
	     if ($iy==0){ $catsCP= "cp.id_category=".$cats['id_category'];}
	     else {     $catsCP= $catsCP." OR cp.id_category=".$cats['id_category'];}
		   $iy=$iy+1;
        }
	   $catSort = " AND EXISTS (SELECT 1 FROM `categoryProduct` cp WHERE pr.id=cp.idProduct and (".$catsCP.") )";
	}
   
 }  
//echo  $catSort;
if (!empty($sizeParam)) {
    // Разбиваем строку параметров
    $sizeIds = explode(',', $sizeParam);
    $sizeIds = array_filter(array_map('trim', $sizeIds));

    if (!empty($sizeIds)) {
        $likeConditions = [];

        foreach ($sizeIds as $size) {
            // Преобразуем URL-формат вроде 5_years → 5 years
            $normalized = str_replace('_', ' ', $size);

            // Экранируем и формируем LIKE-условие
            $escaped = mysqli_real_escape_string($dbcnx, $normalized);

            // Добавляем шаблон поиска (ищет вхождение)
            $likeConditions[] = "sc.EU LIKE '%$escaped%'";
        }

        // Объединяем условия через OR
        $likeClause = implode(' OR ', $likeConditions);

        // Гибкий фильтр по размерам
        $sizeSort = "
            AND EXISTS (
                SELECT 1
                FROM SizeContent sc
                WHERE sc.productID = pr.id
                  AND sc.availability = 'in_stock'
                  AND ($likeClause)
            )";
    }
}


if ($brendParam !== '') {
    // Разбираем по тире
    $brandIds = explode('-', $brendParam);
    
    // Фильтруем: только положительные числа (для безопасности)
    $brandIds = array_filter($brandIds);

if (!empty($brandIds)) { $ix=0;
        foreach ($brandIds as $brandId) {
            $brandId = intval($brandId);
			if ($ix==0){$brendSort=" pr.brand=$brandId";}
            else {$brendSort .= " OR pr.brand=$brandId";}
			$ix=$ix+1;
        }
    }
	$brendSort=' AND ('.$brendSort.') ';
}

if (($types[1]!= 'women7')) {
if (($sectionName1=='WOMEN')||($sectionName1=='WOMAN')){$sectionName="(pr.sectionName = 'WOMAN' OR pr.sectionName = 'WOMEN')";}
if (($sectionName1=='MEN')||($sectionName1=='MAN')){$sectionName="(pr.sectionName = 'MAN' OR pr.sectionName = 'MEN')";}
if (($sectionName1=='KID')){$sectionName="pr.sectionName = 'KID'";}
if ($sectionName1=='HOME'){$sectionName=" pr.sectionName = 'HOME' ";}
$ses = 2;
    $stmt2 = mysqli_prepare($dbcnx,
    "SELECT 
        COUNT(DISTINCT pr.id) AS total_count,
        GROUP_CONCAT(DISTINCT sc.EU ORDER BY sc.EU ASC SEPARATOR '_') AS sizes,
        GROUP_CONCAT(DISTINCT pr.macroColor ORDER BY pr.macroColor DESC SEPARATOR ',') AS colors,  
        GROUP_CONCAT(DISTINCT pr.brand ORDER BY pr.brand ASC SEPARATOR ',') AS brands,
        GROUP_CONCAT(DISTINCT pr.sectionName ORDER BY pr.sectionName ASC SEPARATOR ',') AS sectionName,
        COUNT(DISTINCT CASE WHEN pr.price_old > 0 THEN pr.id END) AS discounted_count  
    FROM 
        boxed_prod_content pr
    LEFT JOIN 
        SizeContent sc ON sc.productID = pr.id AND sc.availability = 'in_stock' AND sc.`sku` IS NOT NULL AND sc.`EU` IS NOT NULL AND sc.`EU`!='' AND pr.`familyName` != 'BISUTERIA'
    WHERE 
        $sectionName
        $catSort 
        AND pr.active = 1 
        AND pr.activeNew = 1 
        AND pr.availability = 'in_stock' 
        AND pr.img_slaider != '' 
        AND pr.price > 0 
        $brendSort 
        $sizeSort
    ");
    mysqli_stmt_execute($stmt2);
	$result2 = mysqli_stmt_get_result($stmt2);
    
	
    if ($row = mysqli_fetch_assoc($result2)) {
        $CountProduct = $row['total_count'];
		$CountProductSale = $row['discounted_count'];
		
		if ($saleSort>0){$CountProduct =$CountProductSale;}
	?>



<div class="panelFilter">	
<? if ($CountProductSale>0){ ?>
<div onclick="toggleSale('1', this);" class="filter-switch btn-switch btn-switch--bg btn-switch--action j-action-filter" style="padding-top:4px"><span class="btn-switch__text">Скидки</span><button class="btn-switch__btn j-list-item <? if ($saleSort>0){?>active<? }?>"  type="button" data-sale="1" aria-label="Кнопка"></button></div>
<? } ?>	


<?	if (!empty($row['brands'])) { ?>
		
            <? 
			$brandsArray = explode(',', $row['brands']); //echo $row['brands']; 
			   $selectedBrend = isset($_GET['brend']) ? explode('-', $_GET['brend']) : array();
			   $brArray=array_map('trim', $brandsArray); 
               $selectedBrend = array_map('trim', $selectedBrend); 
		       $selectedCountB = count($selectedBrend);
			   
			    // Добавляем класс, если совпадает
			if (count($brandsArray) > 0) {  ?>
			  <div class="PanelFilterBrand off"> 
                <div class="containerP">
                  <div class="close-buttonZ nameI" style="display: flex!important"> 
                    <div style="max-width:98%;width:98%; display:block;margin-left:0;text-align:left"><span class="brand">Бренд</span></div> 
					<div class="popup-filters__title-reset" onclick="resetBrands()">Сбросить</div>
                 <div class="close-button" onclick="closeBrandPanel();" style="text-align: right;"><span class="cross"><span style="color:#000000"><svg xmlns="http://www.w3.org/2000/svg" width="15.707" height="15.707" viewBox="0 0 10.707 10.707"> <g data-name="Icon/Close" transform="translate(0.354 0.354)"> <line data-name="Line 118 close" x2="10" y2="10" fill="none" stroke="currentColor" stroke-width="1"></line> <line data-name="Line 119 close" x1="10" y2="10" fill="none" stroke="currentColor" stroke-width="1"></line> </g> </svg></span></span></div>
                </div> 
                <ul class="popup-sorting__list" style="margin-top:26px">
                  <ul class="filter__list">
				  <? if (in_array('1', $brArray)){?><? } ?>
	                <li onclick="toggleBrand(1)" class="filter__item brand-btn <? if (in_array('1', $selectedBrend)){?> activeZ<? } ?>" data-brand="1">
					<div class="checkbox-with-text j-list-item brand-filter-logo">
					<span class="checkbox-with-text__decor"><span class="decor"></span></span><span class="checkbox-with-text__text">Zara</span></div></li>
		            
					<? if (in_array('2', $brArray)){?><? } ?>
					<li  onclick="toggleBrand(2)" class="filter__item brand-btn <? if (in_array('2', $selectedBrend)){?> activeZ<? } ?>" data-brand="2"><div class="checkbox-with-text j-list-item brand-filter-logo"><span class="checkbox-with-text__decor"><span class="decor"></span></span><span class="checkbox-with-text__text">Massimo Dutti</span></div></li>
					
					<? if (in_array('3', $brArray)){?><? } ?>
		            <li onclick="toggleBrand(3)" class="filter__item brand-btn <? if (in_array('3', $selectedBrend)){?> activeZ<? } ?>" data-brand="3"><div class="checkbox-with-text j-list-item brand-filter-logo"><span class="checkbox-with-text__decor"><span class="decor"></span></span><span class="checkbox-with-text__text">Bershka</span></div></li>
					<? if (in_array('5', $brArray)){?><? } ?>
		            <li onclick="toggleBrand(5)" class="filter__item brand-btn <? if (in_array('5', $selectedBrend)){?> activeZ<? } ?>" data-brand="5"><div class="checkbox-with-text j-list-item brand-filter-logo"><span class="checkbox-with-text__decor"><span class="decor"></span></span><span class="checkbox-with-text__text">Stradivarius</span></div></li>
					<? if (in_array('6', $brArray)){?><? } ?>
		            <li onclick="toggleBrand(6)" class="filter__item brand-btn <? if (in_array('6', $selectedBrend)){?> activeZ<? } ?>" data-brand="6"><div class="checkbox-with-text j-list-item brand-filter-logo"><span class="checkbox-with-text__decor"><span class="decor"></span></span><span class="checkbox-with-text__text">Oysho</span></div></li>
		           </ul>
		 
   </ul> <button class="brand-button" onclick="applyBrand()">Применить</button>
   
			</div> 
</div>
		<? } ?> 
		<div><div class="filters-block__dropdown <? if (count($brandsArray) <1) { ?>off<? } ?>" data-testid="catalog-filter"><div class="dropdown-filter dropdown-filter--mobile"><button type="button" class="dropdown-filter__btn dropdown-filter__btn--fbrandB dropdown-brand_btn" onclick="brandON();"><div class="dropdown-filter__btn-name"><div class="dropdown-filter__btn-icon"></div>Бренд</div><?  if ($selectedCountB>0){?><span class="sizeIco"><? echo $selectedCountB;?></span><? } ?></button></div></div></div>
		
		<? if ($selectedCountB>0){?><style>.dropdown-brand_btn:not(.switcher):after {content: none!important;}</style><? } ?>
		<? } //конец брендов
		
$unwantedSizes = ['ONE SIZE ONLY', 'Iphone 13/14/15', 'IPhone 12 / PRO', 'Iphone 16', 'IPhone 11 / XR', 'IPhone 14 PRO MAX','01','2','3'];  
$selectedCount = 0; 

if (!empty($row['sizes'])) { 
    $sizesArray = explode('_', $row['sizes']);
    if (count($sizesArray) > 1) { ?>
        <div class="PanelFilterRazmer off"> 
            <div class="containerP">
                <div class="close-buttonZ nameI" style="display: flex!important"> 
                    <div style="max-width:98%;width:98%; display:block;margin-left:0;text-align:left">
                        <span class="brand">Размеры</span>
                    </div> 
                    <div class="popup-filters__size-reset" onclick="resetSizes()">Сбросить</div>
                    <div class="close-button" onclick="closeSizePanel();" style="text-align: right;">
                        <span class="cross"><span style="color:#000000">X</span></span>
                    </div>
                </div>     
                <div class="razmerBl">
                    <?php
                    $selectedSizes = isset($_GET['size']) ? explode(',', $_GET['size']) : array();
                    $selectedSizes = array_map('trim', $selectedSizes); 

                    $cleanedSizes = [];

                    foreach ($sizesArray as $size) {
                        $size = trim($size);

                        if ($sectionName1 == 'KID') {
                            // Для KID извлекаем только цифры с years/months
                            if (preg_match('/(\d+(-\d+)?\s*(years|months))/i', $size, $matches)) {
                                $cleanSize = $matches[1];
                            } elseif (preg_match('/\((\d+(-\d+)?\s*(years|months))\)/i', $size, $matches)) {
                                $cleanSize = $matches[1];
                            } else {
                                continue;
                            }

                            if (!in_array($cleanSize, $unwantedSizes)) {
                                $cleanedSizes[] = $cleanSize;
                            }
                        } else {
                            // Для остальных категорий старая логика
                            if (!in_array($size, $unwantedSizes) && strpos($size, '-') === false && strpos($size, '/') === false) {
                                $cleanedSizes[] = $size;
                            }
                        }
                    }

                    // Убираем дубликаты
                    $cleanedSizes = array_unique($cleanedSizes);

                    // Сортировка для KID — сначала months, потом years
                    if ($sectionName1 == 'KID') {
                        usort($cleanedSizes, function($a, $b) {
                            // months должны идти первыми
                            $isMonthsA = stripos($a, 'months') !== false;
                            $isMonthsB = stripos($b, 'months') !== false;

                            if ($isMonthsA && !$isMonthsB) return -1;
                            if (!$isMonthsA && $isMonthsB) return 1;

                            // Оба либо months, либо years — сортируем по числу
                            preg_match('/\d+/', $a, $matchA);
                            preg_match('/\d+/', $b, $matchB);
                            $numA = isset($matchA[0]) ? intval($matchA[0]) : 0;
                            $numB = isset($matchB[0]) ? intval($matchB[0]) : 0;
                            return $numA <=> $numB;
                        });
                    }

                    // Вывод размеров
                    foreach ($cleanedSizes as $size) {
                        $isSelected = in_array(str_replace(' ','_',$size), $selectedSizes);  
                        $activeClass = $isSelected ? 'activeZ' : '';
						$translatedLabel = translateSizeLabel($size); ?>
                        <button onclick="toggleSize('<?php echo htmlspecialchars(str_replace(' ','_',$size)); ?>')" data-size="<?php echo htmlspecialchars(str_replace(' ','_',$size)); ?>" class="size-btn <?php echo $activeClass; ?>"><?php echo $translatedLabel; ?></button>
                    <?php } ?>
                </div>
                <button class="size-button" onclick="applySizes()">Применить</button>
            </div>
        </div>
<?php
    }


?><div><div class="filters-block__dropdown " data-testid="catalog-filter"><div class="dropdown-filter dropdown-filter--mobile"><button type="button" class="dropdown-filter__btn filter_size_btn dropdown-filter__btn--fsize2" onclick="sizeON();"><div class="dropdown-filter__btn-name"><div class="dropdown-filter__btn-icon"></div>Размеры</div><?  if ($selectedCount>0){?><span class="sizeIco"><? echo $selectedCount;?></span><? } ?></button></div></div></div>
	<? if ($selectedCount>0){?><style>.filter_size_btn:not(.switcher):after {content: none!important;}</style><? } ?>			
       <? }//конец размеров
$colorMap=['red'=>['hex'=>'#FF0000','ru'=>'Красный','tcex'=>'#fff','en'=>'red'],'blue'=>['hex'=>'#0000FF','ru'=>'Синий','tcex'=>'#fff','en'=>'blue'],'yellow'=>['hex'=>'#FFFF00','ru'=>'Желтый','tcex'=>'#333','en'=>'yellow'],'green'=>['hex'=>'#008000','ru'=>'Зеленый','tcex'=>'#fff','en'=>'green'],'black'=>['hex'=>'#000','ru'=>'Черный','tcex'=>'#fff','en'=>'black'],'white'=>['hex'=>'#FFF','ru'=>'Белый','tcex'=>'#333','en'=>'white'],'pink'=>['hex'=>'#FFC0CB','ru'=>'Розовый','tcex'=>'#333','en'=>'pink'],'purple'=>['hex'=>'#800080','ru' =>'Фиолетовый','tcex'=>'#fff','en'=>'purple'],'orange' =>['hex'=>'#FFA500','ru'=>'Оранжевый','tcex'=>'#fff','en'=>'orange'],'brown'=>['hex'=>'#A52A2A','ru'=>'Коричневый','tcex'=>'#fff','en'=>'brown'],'grey'=>['hex'=>'#808080','ru'=>'Серый','tcex'=>'#fff','en'=>'grey'],'beige'=>['hex'=>'#F5F5DC','ru'=>'Бежевый','tcex'=>'#333','en'=>'beige'],
'gold'=>['hex'=>'#FFD700','ru'=>'Золотой','tcex'=>'#333','en'=>'gold'],'silver'=>['hex'=>'#C0C0C0','ru'=>'Серебряный','tcex'=>'#fff','en'=>'silver'],
'navy'=>['hex'=>'#000080','ru'=>'Темно-синий','tcex'=>'#fff','en'=>'navy'],'maroon'=>['hex'=>'#800000','ru'=>'Бордовый','tcex'=>'#fff','en'=>'maroon'],
'turquoise'=>['hex'=>'#40E0D0','ru'=>'Бирюзовый','tcex'=>'#333','en'=>'turquoise'],'coral'=>['hex'=>'#FF7F50','ru'=>'Коралловый','tcex'=>'#333','en'=>'coral'],
'lime'=>['hex'=>'#00FF00','ru'=>'Лаймовый','tcex'=>'#333','en'=>'lime'],'mint'=>['hex'=>'#98FB98','ru'=>'Мятный','tcex'=>'#333','en'=>'mint'],'lavender'=>['hex'=>'#E6E6FA','ru'=>'Лавандовый','tcex'=>'#333','en'=>'lavender'],'khaki'=>['hex'=>'#F0E68C','ru'=>'Хаки','tcex'=>'#fff','en'=>'khaki'],'olive'=>['hex'=>'#808000','ru'=>'Оливковый','tcex'=>'#fff','en'=>'olive'],'tan'=>['hex'=>'#D2B48C','ru'=>'Загар','tcex'=>'#fff','en'=>'tan'],'cream'=>['hex'=>'#FFFDD0','ru'=>'Кремовый','tcex'=>'#333','en'=>'cream'],'ivory'=>['hex'=>'#FFFFF0','ru'=>'Слоновая кость','tcex'=>'#333','en'=>'ivory'],'charcoal'=>['hex'=>'#36454F','ru'=>'Угольный','tcex'=>'#fff','en'=>'charcoal']];
function filterMainColors($colorsArray) {
$mainColors = ['red','красный','blue','синий','yellow','желтый','green','зеленый','black','черный','white','белый','pink','розовый','purple','фиолетовый','orange','оранжевый','brown','коричневый','grey','серый','beige','бежевый','gold','золотой','silver','серебряный','navy','темно-синий','maroon','бордовый','turquoise','бирюзовый','coral','коралловый','lime','лаймовый','mint','мятный','lavender','лавандовый','khaki','хаки','olive','оливковый','tan','загар','cream','кремовый','ivory','слоновая кость','charcoal','угольный'];
$filtered = [];
foreach ($colorsArray as $color) {
$cleaned = preg_replace('/\b(light|dark|pale|washed|mid|deep|bright|neon|faded|soft|dusty|muted|pastel|lemon|stone|steel|petrol|vermelho|verde|velvet|vanilla|tobacco|toasted|tinted|terra|taupe|tangerine|tan|strawberry|straw|stone|spicy|snakeskin|snake|sky|silver|shiny|sand|shimmery|shimmer|sea|salmon|rust|russet|royal|rose|rosa|rinse|regatta|reddish|raspberry|quartz|purplish|prussian|printed|preto|prata|plum|platinum|plaster|pitaya|pistachio|pinks|topaz|mermaid|madeleine|guava|glow|pine|petroleum|petrol|pedra|pearl|peachy|copper|peach|oyster|overdyed|orion|ochre|oatmeal|nude|neutral|mustard|multicoloured|multicolored|mulberry|mrs|mottled|moss|mole|mocha|mousse|mint|mink|military|midnight|marron|glacee|maple|syrup|mahogany|lychee|limoncello|lilac|leopard|lead|khaki|ivory|iris|iridescent|ink|indigo|ice|hunter|gunpowder|greyish|graphite|grapefruit|golden|fuchsia|fluorescent|emerald|electric|ecru|dusty|duck|denim|deep|charcoal|champagne|chalk|cement|cava|castanho|toureira|carey|caramelo|caramel|capuccino|camel|butter|burnt|burgundy|bubblegum|bronze|brick|brandy|branco|bottle|bordeaux|bone|blush|bluish|blueberry|bleach|berry|beige|azul|marinho|aubergine|asphalt|aquamarine|aqua|fever|apricot|apple|anthracite|animal|aged|amaranth|amethyst|amber|alabaster|adamant|abyss|abalone|absolute)/i', '', $color);
        $cleaned = trim($cleaned);
        $cleaned = trim(strtolower($cleaned));
        $mainColors = array_map('strtolower', $mainColors);
        if (in_array($cleaned, $mainColors) && !in_array($cleaned, $filtered)) {
            $filtered[] = $cleaned;
        }
    }
    
    return $filtered;
}		
		
       if (!empty($row['colors'])) {
            $colorsArray = explode(',', $row['colors']);  
			$filteredColors = filterMainColors($colorsArray);
			if (!empty($filteredColors)) {
			  if (count($filteredColors) > 1) { ?>
			  
			  <div class="PanelFilterColor off"> 
                <div class="containerP">
                  <div class="close-buttonZ nameI" style=" display: flex!important"> 
                   <div style="max-width:98%;width:98%; display:block;margin-left:0;text-align:left"><span class="brand">Цвет</span> </div> 
			         <div class="popup-filters__size-reset" onclick="resetColor()">Сбросить</div>
                     <div class="close-button" onclick="closeColorPanel();" style="text-align:right;"><span class="cross"><span style="color:#000"><svg xmlns="http://www.w3.org/2000/svg" width="15.707" height="15.707" viewBox="0 0 10.707 10.707"> <g data-name="Icon/Close" transform="translate(0.354 0.354)"> <line data-name="Line 118 close" x2="10" y2="10" fill="none" stroke="currentColor" stroke-width="1"></line> <line data-name="Line 119 close" x1="10" y2="10" fill="none" stroke="currentColor" stroke-width="1"></line> </g> </svg></span></span></div>
                   </div> 	
			       <div class="colorsBl">
             <? $ic=0; foreach ($filteredColors as $color) {
                $color = trim($color);
                // Фильтр, если нужно (добавь нежелательные цвета, если есть)
                if (!empty($color) && isset($colorMap[$color])) {
                   $map = $colorMap[$color];$hex = $map['hex'];$ruText = $map['ru'];$colorText= $map['tcex']; ?>
               <button class="color-btn cdx_<? echo $ic;?>" style="background:<? echo $hex; ?>;color:<? echo $colorText; ?>" data-color="<? echo $map['en']; ?>" onclick="toggleColor('<? echo $map['en']; ?>');"></button>
			   <style>.cdx_<? echo $ic;?>.activeZ:after{
    border-bottom: 2px solid <? echo $colorText; ?>;
    border-right: 2px solid <? echo $colorText; ?>;}
</style>
                 <?   $ic=$ic+1;  }} ?> 
				</div></div>  <button class="size-button" onclick="applyColor()">Применить</button></div>
				<?	  } ?>
		<div><div class="filters-block__dropdown" data-testid="catalog-filter"><div class="dropdown-filter dropdown-filter--mobile"><button type="button" class="dropdown-filter__btn dropdown-filter__btn--fcolor" onclick="colorON();"><div class="dropdown-filter__btn-name"><div class="dropdown-filter__btn-icon"></div>Цвет</div></button></div></div></div>
				<?	  }}
		} ?>
		
		
   </div>
  

   <? }?>


 


 <div class="shell shell--fluid" style="margin-top:8px">
 <div class="section-collection__nav" style="display:flex; flex-wrap:nowrap;justify-content: center;align-items: center;flex-direction: row;width: max-content;margin: auto; padding-bottom:8px ">
	<span class="js-products-count" id="js-products-count">Товаров: <? echo $CountProduct; ?></span>
	</div>
</div>
		