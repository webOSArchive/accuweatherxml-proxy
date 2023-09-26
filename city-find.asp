<?xml version="1.0"  encoding="utf-8"?>
<?php
include("accuweather-proxy.php"); //this page is invoked from a client-specific sub-folder
include("config.php");
$apiKey = get_accuweatherApiKey();
if (isset($_GET['location'])) {
    $serviceData = get_city_search($_GET['location'], $apiKey);
}
header('Content-Type: text/xml');

//In order to match original XML, we need some counts
$usCount = 0;
$intlCount = 0;
foreach($serviceData as $location){ 
    if ($location->Country->ID == "US"){
        $usCount++;
    }
    else {
        $intlCount++;
    }
}

?>
<adc_database xmlns="http://www.accuweather.com">	
	<citylist us="<?php echo $usCount;?>" intl="<?php echo $intlCount;?>" extra_cities="0">
    <?php
        $count = 1;
        foreach($serviceData as $location) {
            echo "\r\n";
            $stateStr = $location->AdministrativeArea->LocalizedName;
            if ($location->Country->ID != "US") {
                $stateStr = $stateStr . " (" . $location->Country->LocalizedName . ")";
            }
            echo "            <location cnt=\"" . $count . "\" city=\"" . $location->LocalizedName . "\" state=\"" . $stateStr . "\" location=\"cityId:" . $location->Key . "\"/>\r\n";
            $count++;
        }
    ?>

	</citylist>
	<copyright>Copyright <?php echo date("Y"); ?> AccuWeather.com</copyright>
	<use>This document is intended only for use by authorized licensees of AccuWeather.com. Unauthorized use is prohibited. All Rights Reserved.</use>
	<product>blstream</product>
	<redistribution>Redistribution Prohibited.</redistribution>
</adc_database>
