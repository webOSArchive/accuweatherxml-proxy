<?php
$forecastDays = 8;  //We can have 8 days with OpenWeather, not sure this is even needed now
$cacheRoot = "../../cache/";
$accuweatherRoot = "http://dataservice.accuweather.com";
$openweatherRoot = "https://api.openweathermap.org/data/3.0/";

// IP Whitelist for authentication
// Add IP addresses of devices/clients that are allowed to access the proxy
// Leave empty array to allow all IPs (not recommended for production)
$allowedIPs = array(
    // Examples:
    // "192.168.1.100",     // Your webOS device
    // "10.0.0.50",         // Another trusted client
    // "2001:db8::1",       // IPv6 address example
);

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

// IP Whitelist Authentication
// Call this function at the top of protected pages
function check_ip_whitelist() {
    global $allowedIPs;

    // If whitelist is empty, allow all (for backwards compatibility)
    if (empty($allowedIPs)) {
        return true;
    }

    // Get client IP address
    $clientIP = $_SERVER['REMOTE_ADDR'];

    // Check if client IP is in whitelist
    if (!in_array($clientIP, $allowedIPs)) {
        error_log("Weather Proxy - Access denied for IP: " . $clientIP);
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/xml');
        die('<?xml version="1.0" encoding="utf-8"?><adc_database><error>Access Forbidden: IP address not authorized</error></adc_database>');
    }

    return true;
}
?>