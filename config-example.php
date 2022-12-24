<?php
$forecastDays = 5;  //1, 5, 10 or 15: Don't go above 5 unless you've paid Accuweather for more
$cacheRoot = "../../cache/";
function get_apiKey() {
    $apiKeys = array(
        "GET YOUR API KEYS FROM developer.accuweather.com"
    );
    
    //Choose a random API key to use from the array above
    return($apiKeys[array_rand($apiKeys)]);
}
?>