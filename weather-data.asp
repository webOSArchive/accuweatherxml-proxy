<?php
include("accuweather-proxy.php"); //this page is invoked from a client-specific sub-folder
include("config.php");
$accuweatherKey = get_accuweatherApiKey();
$openweatherKey = get_openweatherApiKey();
$theQuery = $_SERVER['QUERY_STRING'];
//$theUrl = "http://" . $realServiceDomain . "/widget/accuwxiphonev4/weather-data.asp?" . $theQuery;
$theUrl = $realServiceDomain;

header('Content-Type: text/xml');
//header('Content-Type: application/json');

//TODO: accept location in query, obviously!
//$locationId = "lat=33.44&lon=-94.04";

if (isset($_GET['location'])) {
    $locationId = $_GET['location'];
    if (strpos($locationId, "cityId") !== false)
        $locationId = str_replace("cityId:", "", $locationId);
    else        //If location is defined using postal code, we need to look it up
        $locationId = get_postalcode_locationId($locationId, $accuweatherKey);
} else {
    die ("<adc_database><error>No location specified</error></adc_database>");
}

//echo "locationId = " . $locationId . "<br>";
$localeData = get_locale_data($locationId, $accuweatherKey);
$realLocation = "&lat=" . $localeData->GeoPosition->Latitude . "&lon=" . $localeData->GeoPosition->Longitude;
//echo $realLocation . "<br>";
//print_r($localeData);
//die();

$useMetric = false;
if (isset($_GET['metric']) && $_GET['metric'] != 0) {
    $useMetric = true;
}

$openWeatherData = openWeatherOneCall($theUrl, $realLocation, $useMetric, $openweatherKey);
//echo json_encode($openWeatherData);
?>
<?xml version="1.0" encoding="utf-8"?>
<adc_database xmlns="http://www.accuweather.com">
<?php
echo get_units_asXml($useMetric);
$tzOffset = 0;
if (isset($openWeatherData)) {
    $tzOffset = $openWeatherData->timezone_offset;
    echo get_header_asXml($openWeatherData, $localeData);
    echo "<watchwarnareas isactive=\"0\">\r\n";
    echo "  <url>https://www.accuweather.com/en/" . strtolower($localeData->Country->ID) . "/" . str_replace(" ", "-", strtolower($localeData->EnglishName)) . "/" . $localeData->PrimaryPostalCode . "/weather-warnings/" . $localeData->Key . "</url>\r\n";
    echo "</watchwarnareas>\r\n";
}
echo get_current_conditions_asXml($openWeatherData, $useMetric);

echo "<forecast>\r\n";
    echo get_daily_forecast_asXml($openWeatherData, $useMetric);
    echo get_hourly_forecast_asXml($openWeatherData, $useMetric);
echo "</forecast>\r\n";
?>
    <hurricane>
        <wxh url="http://hurricane.accuweather.com/hurricane/index.asp?partner=blstreamhptablet" sat="http://sirocco.accuweather.com/adc_sat_108x81_public/ir/iscar.gif" cnt="0" />
    </hurricane>
<?php
echo get_indices_asXml($locationId, $accuweatherKey);
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
