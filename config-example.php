<?php
$forecastDays = 8;  //We can have 8 days with OpenWeather, not sure this is even needed now
$cacheRoot = "../../cache/";
$realServiceDomain = "https://api.openweathermap.org/data/3.0/onecall";  //A hostname (from your hosts file) or IP address for the actual Accuweather service
function get_accuweatherApiKey() {
    $apiKeys = array(
        "GET YOUR API KEY FROM https://home.openweathermap.org/api_keys"
    );
    
    //Choose a random API key to use from the array above
    return($apiKeys[array_rand($apiKeys)]);
}
function get_openweatherApiKey() {
    $apiKeys = array(
        "GET YOUR API KEY FROM https://openweathermap.org/api/one-call-3"
    );
    //Choose a random API key to use from the array above
    return($apiKeys[array_rand($apiKeys)]);
}
?>