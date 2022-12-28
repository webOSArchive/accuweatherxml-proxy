<?php
include("../../accuweather-proxy.php"); //this page is invoked from a client-specific sub-folder
include("../../config.php");

header('Content-Type: text/xml');

$theQuery = $_SERVER['QUERY_STRING'];
$theUrl = "http://" . $realServiceDomain . "/widget/accuwxiphonev4/city-find.asp?" . $theQuery;

echo get_relay_data($theUrl);
?>