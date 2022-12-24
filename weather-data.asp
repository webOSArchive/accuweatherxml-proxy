<?php
include("../../accuweather-proxy.php"); //this page is invoked from a client-specific sub-folder
include("../../config.php");
$apiKey = get_apiKey();

header('Content-Type: text/xml');
echo ('<?xml version="1.0"  encoding="utf-8"?>');

$locationId = "";
if (isset($_GET['location'])) {
    $locationId = $_GET['location'];
    if (strpos($locationId, "cityId") !== false)
        $locationId = str_replace("cityId:", "", $locationId);
    else        //If location is defined using postal code, we need to look it up
        $locationId = get_postalcode_locationId($locationId, $apiKey);
} else {
    die ("<adc_database><error>No location specified</error></adc_database>");
}

$useMetric = false;
if (isset($_GET['metric']) && $_GET['metric'] != 0) {
    $useMetric = true;
}
?>

<adc_database xmlns="http://www.accuweather.com">
<?php
echo get_units_asXml($useMetric);
$tzOffset = 0;
$localData = get_locale_data($locationId, $apiKey);
if (isset($localData)) {
    $tzOffset = $localData->TimeZone->GmtOffset;
    echo convert_local_data_toXml($localData);
    echo "<watchwarnareas isactive=\"0\">";
    echo "  <url>https://www.accuweather.com/en/" . strtolower($localData->Country->ID) . "/" . str_replace(" ", "-", strtolower($localData->EnglishName)) . "/" . $localData->PrimaryPostalCode . "/weather-warnings/" . $localData->Key . "</url>";
    echo "</watchwarnareas>";
}

echo get_current_conditions_asXml($locationId, $useMetric, $apiKey);
echo "<forecast>";
    echo get_daily_forecast_asXml($locationId, $forecastDays, $useMetric, $apiKey);
    echo get_hourly_forecast_asXml($locationId, $tzOffset, $useMetric, $apiKey);
echo "</forecast>";
?>
    <hurricane>
        <wxh url="http://hurricane.accuweather.com/hurricane/index.asp?partner=blstreamhptablet" sat="http://sirocco.accuweather.com/adc_sat_108x81_public/ir/iscar.gif" cnt="0" />
    </hurricane>
<?php
echo get_indices_asXml($locationId, $apiKey);
?>
    <video>
        <!-- TODO: This has been dead so long, I don't know what to do about it... -->
        <clipCode>LGA</clipCode>
    </video>

    <copyright>Copyright <?php echo date("Y"); ?> AccuWeather.com</copyright>
    <use>This document is intended only for use by authorized licensees of AccuWeather.com. Unauthorized use is prohibited. All Rights Reserved.</use>
    <product>blstream</product>
    <redistribution>Redistribution Prohibited.</redistribution>
</adc_database>