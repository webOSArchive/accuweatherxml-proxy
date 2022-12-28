<?php
include("../../accuweather-proxy.php"); //this page is invoked from a client-specific sub-folder
include("../../config.php");

header('Content-Type: text/xml');

//As of this writing, the city-find XML API is still working, so rather than proxy it to the new API
//we'll just pass the call along directly.
$theQuery = $_SERVER['QUERY_STRING'];
$theUrl = "http://" . $realServiceDomain . "/widget/accuwxiphonev4/city-find.asp?" . $theQuery;

echo get_relay_data($theUrl);
?>