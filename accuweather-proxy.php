<?php
function get_postalcode_locationId($locationId, $apiKey) {
    global $accuweatherRoot;
    
    $locParts = explode("|", $locationId);
    $pcode = str_replace("postalCode:", "", $locParts[0]);
    $ccode = "US";
    if (count($locParts) > 1) {
        $ccode = $locParts[1];
    }
    $serviceUrl = $accuweatherRoot . "/locations/v1/postalcodes/" . $ccode . "/search?q=" . $pcode;
    $serviceRaw = get_remote_data($serviceUrl, $apiKey, $cacheHours=8760);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (is_array($serviceData) && isset($serviceData[0]) && isset($serviceData[0]->Key)) {
            return $serviceData[0]->Key;
        } else {
            return 0;
        }
    }
}

function get_city_search($searchString, $apiKey) {
    global $accuweatherRoot;
    $serviceUrl = $accuweatherRoot . "/locations/v1/cities/search?q=" . urlencode($searchString);
    $serviceRaw = get_remote_data($serviceUrl, $apiKey, $cacheHours=8760);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        return $serviceData;
    }
}

function get_units_asXml($useMetric) {
    if (!$useMetric) {
        return "<units>\r\n<temp>F</temp>\r\n<dist>MI</dist>\r\n<speed>MPH</speed>\r\n<pres>IN</pres>\r\n<prec>IN</prec>\r\n</units>" . PHP_EOL;
    } else {
        return "<units>\r\n<temp>C</temp>\r\n<dist>KM</dist>\r\n<speed>KPH</speed>\r\n<pres>CM</pres>\r\n<prec>CM</prec>\r\n</units>" . PHP_EOL;
    }
}

function openWeatherOneCall($serviceUrl, $location, $useMetric, $apiKey) {
    $units = "imperial";
    if ($useMetric > 0)
        $units = "metric";
    $serviceUrl = $serviceUrl . $location . "&units=" . $units . "&appid=" . $apiKey;
    $serviceRaw = get_remote_data($serviceUrl, $apiKey, $cacheHours=1);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData)) {
            return $serviceData;
        }
    }
    error_log("An error occurred getting data from OpenWeather. " . $serviceRaw);
    return null;
}

function get_locale_data($locationId, $apiKey) {
    global $accuweatherRoot;
    $serviceUrl = $accuweatherRoot . "/locations/v1/" . $locationId . "?details=true";
    $serviceRaw = get_remote_data($serviceUrl, $apiKey, $cacheHours=8760);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData)) {
            return $serviceData;
        }
    }
    return null;
}

function get_header_asXml($openweatherData, $accuweatherData) {
    $returnData = "";
    if (isset($openweatherData) && isset($accuweatherData)) {
        try {
            $returnData .= "<local>" . PHP_EOL;
            $returnData .= "  <city>" . $accuweatherData->LocalizedName  . "</city>" . PHP_EOL;
            $returnData .= "  <adminArea code=\"" . $accuweatherData->AdministrativeArea->ID . "\">" .  $accuweatherData->AdministrativeArea->LocalizedName  . "</adminArea>" . PHP_EOL;
            $returnData .= "  <country code=\"" . $accuweatherData->Country->ID . "\">" .  $accuweatherData->Country->LocalizedName . "</country>" . PHP_EOL;
            $returnData .= "  <cityId>" . $accuweatherData->Key . "</cityId>" . PHP_EOL;
            $returnData .= "  <primaryPostalCode>" . $accuweatherData->PrimaryPostalCode . "</primaryPostalCode>" . PHP_EOL;
            $returnData .= "  <locationKey>" . $accuweatherData->Key . "</locationKey>" . PHP_EOL;
            $returnData .= "  <lat>" . $openweatherData->lat . "</lat>" . PHP_EOL;
            $returnData .= "  <lon>" . $openweatherData->lon . "</lon>" . PHP_EOL;
            $timestamp = $openweatherData->current->dt + $openweatherData->timezone_offset;
            $useTime = gmdate("H:i", $timestamp);
            $returnData .= "  <time>" . $useTime . "</time>" . PHP_EOL;
            $returnData .= "  <timeZone>" . $openweatherData->timezone . "</timeZone>" . PHP_EOL;
            $returnData .= "  <obsDaylight>" . $accuweatherData->TimeZone->IsDaylightSaving . "</obsDaylight>" . PHP_EOL;
            $returnData .= "  <timeZoneAbbreviation>" . $accuweatherData->TimeZone->Code . "</timeZoneAbbreviation>" . PHP_EOL;
            $returnData .= "</local>" . PHP_EOL;
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: location</error>";
        }
    } else {
        $errormessage = "<error>location data from remote service could not be parsed</error>";
        return $errormessage;
    }
    return $returnData;
}

function get_current_conditions_asXml($serviceData, $useMetric, $locationId) {
    $units = "Metric";
    $returnData = "";
    if (!$useMetric)
        $units = "Imperial";
    try {
        $isDaylight = false;
        if ($serviceData->current->dt > $serviceData->current->sunrise && $serviceData->current->dt < $serviceData->current->sunset)
            $isDaylight = true;
        $returnData .= "<currentconditions daylight=\"" . $isDaylight . "\">" . PHP_EOL;
        
        //$returnData .= "<url>" . str_replace("&", "&amp;", $serviceData[0]->MobileLink) . "</url>" . PHP_EOL;
        $returnData .= "    <url>http://www.accuweather.com/index-forecast.asp?partner=blstreamhptablet&amp;" . $locationId . "</url>" . PHP_EOL;
        //Note: original dataset used AM/PM or h:i A
        $timestamp = $serviceData->current->dt + $serviceData->timezone_offset;
        $returnData .= "    <observationtime>" . gmdate("H:i", $timestamp) . "</observationtime>" . PHP_EOL;
        //TODO: this pressure conversion should be double-checked!
        $returnData .= "    <pressure state=\"Unknown\">" .  ($serviceData->current->pressure * 0.0294) . "</pressure>" . PHP_EOL;
        $returnData .= "    <temperature>" . round($serviceData->current->temp) . "</temperature>" . PHP_EOL;
        $returnData .= "    <realfeel>" . round($serviceData->current->feels_like) . "</realfeel>" . PHP_EOL;
        $returnData .= "    <humidity>" . $serviceData->current->humidity . "</humidity>" . PHP_EOL;
        $returnData .= "    <weathertext>" . $serviceData->current->weather[0]->description . "</weathertext>" . PHP_EOL;
        $returnData .= "    <weathericon>" . map_weather_icon($serviceData->current->weather[0]->icon) . "</weathericon>" . PHP_EOL;
        $returnData .= "    <windgusts>" . $serviceData->hourly[0]->wind_gust . "</windgusts>" . PHP_EOL;
        $returnData .= "    <windspeed>" . $serviceData->current->wind_speed . "</windspeed>" . PHP_EOL;
        $returnData .= "    <winddirection>" . $serviceData->current->wind_deg . "°</winddirection>" . PHP_EOL;
        //TODO: openweather returns in meters, is this a good conversion?
        $returnData .= "    <visibility>" . ($serviceData->current->visibility / 1000). "</visibility>" . PHP_EOL;
        $returnData .= make_precip_amounts($serviceData->current, $useMetric, false);
        $returnData .= "    <uvindex index=\"" . $serviceData->current->uvi . "\">" .  map_uvi_text($serviceData->current->uvi) . "</uvindex>" . PHP_EOL;
        $returnData .= "    <dewpoint>" . $serviceData->current->dew_point . "</dewpoint>" . PHP_EOL;
        $returnData .= "    <cloudcover>" . $serviceData->current->clouds . "%</cloudcover>" . PHP_EOL;
        $returnData .= "    <apparenttemp>" . $serviceData->current->feels_like . "</apparenttemp>" . PHP_EOL;
        //TODO: missing: $returnData .= "<windchill>" . $serviceData[0]->WindChillTemperature->$units->Value . "</windchill>" . PHP_EOL;
        $returnData .= "</currentconditions>" . PHP_EOL;
    } catch (Exception $e) {
        return "<error>an error occurred while parsing remote service data. last attempted node was: currentconditions</error>";
    }
    return $returnData;
}

function make_precip_amounts($data, $useMetric, $longLabel) {
    $precipAmount = 0;
    $label = "";
    $returnData = "";
    if ($longLabel)
        $label = "amount";
    if (isset($data->rain)) {
        if (isset($data->rain->{'1h'}))
            $rain = $data->rain->{'1h'};
        else
            $rain = $data->rain;
        if (!$useMetric)
            $rain = round(($rain * 0.0393701), 2);
        $returnData .= "    <rain" . $label . ">" . $rain . "</rain" . $label .">" . PHP_EOL;
        $precipAmount = $precipAmount + $rain;
    } else {
        $returnData .= "    <rain" . $label . ">0</rain" . $label . ">" . PHP_EOL;
    }
    if (isset($data->snow)) {
        if (isset($data->snow->{'1h'}))
            $snow = $data->snow->{'1h'};
        else
            $snow = $data->snow;
        if (!$useMetric)
            $snow = round(($snow * 0.0393701), 2);
        $returnData .= "    <snow" . $label .">" . $snow . "</snow" . $label .">" . PHP_EOL;
        $precipAmount = $precipAmount + $snow;
    } else {
        $returnData .= "    <snow" . $label .">0</snow" . $label . ">" . PHP_EOL;
    }
    $returnData .= "    <ice" . $label . ">0</ice" . $label . ">" . PHP_EOL;
    $returnData .= "    <precip" . $label . ">" . $precipAmount . "</precip" . $label . ">" . PHP_EOL;
    return $returnData;
}

function map_uvi_text($uvi) {
    if ($uvi <= 3)
        return "Low";
    if ($uvi > 3 && $uvi <= 5)
        return "Moderate";
    if ($uvi > 5 && $uvi <= 7)
        return "High";
    if ($uvi > 7 && $uvi < 8)
        return "Very High";
    if ($uvi > 8)
        return "Extreme";
}

function get_daily_forecast_asXml($serviceData, $useMetric, $locationId) {
    $returnData = "";
    $dayCount = 0;
    foreach($serviceData->daily as $day){
        if ($dayCount == 0 && $day->summary)
            $returnData .= "<url>http://www.accuweather.com/forecast.asp?partner=blstreamhptablet&amp;" . $locationId . "</url>" . PHP_EOL;
        $dayCount++;
        try {
            $returnData .= "<day number=\"" . $dayCount . "\">" . PHP_EOL;
            $returnData .= "  <url>http://www.accuweather.com/forecast-details.asp?partner=blstreamhptablet&amp;" . $locationId . "&amp;fday=" . $dayCount . "</url>" . PHP_EOL;
            //Note: original dataset used AM/PM or h:i A
            $timestamp = $day->dt + $serviceData->timezone_offset;
            $returnData .= "  <obsdate>" . gmdate("m/d/Y", $timestamp) . "</obsdate>" . PHP_EOL;
            $returnData .= "  <daycode>" . gmdate('l', $timestamp) . "</daycode>" . PHP_EOL;
            $timestamp = $day->sunrise + $serviceData->timezone_offset;
            $returnData .= "  <sunrise>" . gmdate("H:i", $timestamp) . "</sunrise>" . PHP_EOL;
            $timestamp = $day->sunset + $serviceData->timezone_offset;
            $returnData .= "  <sunset>" . gmdate("H:i", $timestamp) . "</sunset>" . PHP_EOL;
            $returnData .= "  <daytime>" . PHP_EOL;
            $returnData .= "    <txtshort>" . $day->weather[0]->description . "</txtshort>" . PHP_EOL;
            $returnData .= "    <txtlong>" . $day->summary . "</txtlong>" . PHP_EOL;
            $returnData .= "    <weathericon>" . map_weather_icon($day->weather[0]->icon) . "</weathericon>" . PHP_EOL;
            $returnData .= "    <hightemperature>" . round($day->temp->max) . "</hightemperature>" . PHP_EOL;
            $returnData .= "    <lowtemperature>" . round($day->temp->min) . "</lowtemperature>" . PHP_EOL;
            $returnData .= "    <realfeelhigh>" . round($day->feels_like->day) . "</realfeelhigh>" . PHP_EOL;
            $returnData .= "    <realfeellow>" . round($day->feels_like->night) . "</realfeellow>" . PHP_EOL;
            $returnData .= "    <windspeed>" . $day->wind_speed . "</windspeed>" . PHP_EOL;
            $returnData .= "    <winddirection>" . $day->wind_deg . "</winddirection>" . PHP_EOL;
            $returnData .= "    <windgust>" . $day->wind_gust . "</windgust>" . PHP_EOL;
            $returnData .= "    <maxuv>" . $day->uvi . "</maxuv>" . PHP_EOL;
            $returnData .= make_precip_amounts($day, $useMetric, true);
            //TODO: this is actually precipitation probability, not storm probability
            $returnData .= "    <tstormprob>" . $day->pop . "</tstormprob>" . PHP_EOL;
            $returnData .= "  </daytime>" . PHP_EOL;
            $returnData .= "  <nighttime>" . PHP_EOL;
            $returnData .= "    <txtshort>" . $day->weather[0]->description . "</txtshort>" . PHP_EOL;
            $returnData .= "    <txtlong>" . $day->summary . "</txtlong>" . PHP_EOL;
            $returnData .= "    <weathericon>" . map_weather_icon($day->weather[0]->icon) . "</weathericon>" . PHP_EOL;
            $returnData .= "    <hightemperature>" . round($day->temp->night) . "</hightemperature>" . PHP_EOL;
            $returnData .= "    <lowtemperature>" . round($day->temp->min) . "</lowtemperature>" . PHP_EOL;
            $returnData .= "    <realfeelhigh>" . round($day->feels_like->eve) . "</realfeelhigh>" . PHP_EOL;
            $returnData .= "    <realfeellow>" . round($day->feels_like->night) . "</realfeellow>" . PHP_EOL;
            $returnData .= "    <windspeed>" . $day->wind_speed . "</windspeed>" . PHP_EOL;
            $returnData .= "    <winddirection>" . $day->wind_deg . "</winddirection>" . PHP_EOL;
            $returnData .= "    <windgust>" . $day->wind_gust . "</windgust>" . PHP_EOL;
            $returnData .= "    <maxuv>" . $day->uvi . "</maxuv>" . PHP_EOL;
            $returnData .= "    <rainamount>0</rainamount>" . PHP_EOL;
            $returnData .= "    <snowamount>0</snowamount>" . PHP_EOL;
            $returnData .= "    <iceamount>0</iceamount>" . PHP_EOL;
            $returnData .= "    <precipamount>0</precipamount>" . PHP_EOL;
            $returnData .= "    <tstormprob>0</tstormprob>" . PHP_EOL;
            $returnData .= "  </nighttime>";
            $returnData .= "</day>" . PHP_EOL;
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: dailyforecast</error>";
        }
    }
    return $returnData;
}

function get_hourly_forecast_asXml($serviceData, $useMetric, $locationId) {
    $returnData = "<hourly>" . PHP_EOL;
    $hourCount = 0;
    foreach($serviceData->hourly as $hour){
        if ($hourCount >= 12)
            break;
        else
            $hourCount++;
        try {
            $timestamp = $hour->dt + $serviceData->timezone_offset;
            //Note: original dataset used AM/PM or h A
            $returnData .= "<hour time=\"" . gmdate("H", $timestamp) . "\">" . PHP_EOL;
            $returnData .= "  <weathericon>" . map_weather_icon($hour->weather[0]->icon) . "</weathericon>" . PHP_EOL;
            $returnData .= "  <temperature>" . round($hour->temp) . "</temperature>" . PHP_EOL;
            $returnData .= "  <realfeel>" . round($hour->feels_like) . "</realfeel>" . PHP_EOL;
            $returnData .= "  <dewpoint>" . $hour->dew_point . "</dewpoint>" . PHP_EOL;
            $returnData .= "  <humidity>" . $hour->humidity . "</humidity>" . PHP_EOL;
            $returnData .= make_precip_amounts($hour, $useMetric, false);
            $returnData .= "  <windspeed>" . $hour->wind_speed . "</windspeed>" . PHP_EOL;
            $returnData .= "  <winddirection>" . $hour->wind_deg . "</winddirection>" . PHP_EOL;
            $returnData .= "  <windgust>" . $hour->wind_gust . "</windgust>" . PHP_EOL;
            $returnData .= "  <txtshort>" . $hour->weather[0]->main . "</txtshort>" . PHP_EOL;
            $returnData .= "  <traditionalLink>http://www.accuweather.com/forecast-hourly.asp?partner=blstreamhptablet&amp;" . $locationId . "&amp;fday=1&amp;hbhhour=" . $hourCount ."</traditionalLink>";
            $returnData .= "</hour>" . PHP_EOL;
            
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: hourlyforecast: " . $e . "</error>";
        }
    }
    $returnData .= "</hourly>" . PHP_EOL;
    return $returnData;
}

function get_indices_asXml($locationId, $apiKey) {
    global $indices, $accuweatherRoot;
    $serviceUrl = $accuweatherRoot . "/indices/v1/daily/1day/" . $locationId;
    $serviceRaw = get_remote_data($serviceUrl, $apiKey, $cacheHours=8);
    $returnData = "<indices>";
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData) && is_array($serviceData)) {
            foreach($serviceData as $indice){
                try {
                    // <indice name="grassGrowing" value="0"></indice>
                    if (array_key_exists($indice->Name, $indices)) {
                        $returnData .= "<indice name=\"" . $indices[$indice->Name] . "\" value=\"" . $indice->Value . "\"></indice>";
                    }                    
                } catch (Exception $e) {
                    return "<error>an error occurred while parsing remote service data. last attempted node was: hourlyforecast</error>";
                }
            }
        } else {
            if (null !== $serviceData && isset($serviceData->Message)) {
                return "<error>" . $serviceData->Message . "</error>";
            } else {
                $errormessage = "<error>response from remote service could not be parsed or had errors: ";
                $errormessage .= "<![CDATA[" . $serviceRaw . "]]>";
                $errormessage .= "</error>";
                return $errormessage;
            }
        }
    } else {
        return "<error>data could not be retreived from remote service";
    }
    $returnData .= "</indices>";
    return $returnData;
}

function get_remote_data($url, $apiKey, $cacheDuration) {
    global $cacheRoot, $accuweatherRoot;
    $cacheName = $cacheRoot . cleanFilename($url). ".json";
    //check if cache exists and is still usable
    if (file_exists($cacheName)) {
        $cacheHours = floor((time() - filemtime($cacheName))/3600);
        if ($cacheHours < $cacheDuration) {
            return file_get_contents($cacheName);
        }
    }
    //otherwise, proceed with remote call
    error_log("No suitable cache, calling remote API: " . $url);
    //  append API key
    if (strpos($url, "?") === false)
        $url = $url . "?apikey=" . $apiKey;
    else
        $url = $url . "&apikey=" . $apiKey;
    
    //  always use https
    $url = str_replace("http://", "https://", $url); 
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_PORT => 443,
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "GET",
    ));

    //  call remote service
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    // Return response, cache if appropriate
    if ($err) {
        return "{error:'" . $err . "'}";
    } else {
        if (!isset($response) || $response == "" || $response == null || $response == "null") {
            return "{error:'null response'}";
        }
        if (validateJSON($response)) {
            if ($cacheDuration > 0) {
                //cache response
                if (!file_exists($cacheRoot)) {
                    mkdir($cacheRoot, 0755, true);
                }
                file_put_contents($cacheName, $response);
            }
            return $response;
        } else {
            return "{'error':'" . $response . "'}";
        }
    }
}

function cleanFilename($url) {
    global $accuweatherRoot;
    global $openweatherRoot;
    $url = str_replace($accuweatherRoot, "", $url);
    $url = str_replace($openweatherRoot, "", $url);
    $fileName = base64_encode($url);
    $fileName = str_replace("=", "", $fileName);
    $fileName = str_replace("/", "", $fileName);
    $fileName = str_replace("\\", "", $fileName);
    $fileName = str_replace(".", "", $fileName);
    return $fileName;
}

function validateJSON(string $json): bool {
    try {
        $test = json_decode($json, null, flags: JSON_THROW_ON_ERROR);
        if (isset($test->Code)) {
            $exc = "Accuweather service response " . $test->Code;
            if (isset($test->Message)) {
                $exc .= ": " . $test->Message;
            }
            throw new ErrorException($exc);
        }
        return true;
    } catch  (Exception $e) {
        error_log($e->getMessage() . PHP_EOL);
        return false;
    }
}

function map_weather_icon($iconCode) {
    $i = array();       //Known icons from https://openweathermap.org/weather-conditions to app's icons
    $i['01d'] = '01';
    $i['02d'] = '04';
    $i['03d'] = '03';
    $i['04d'] = '07';
    $i['09d'] = '12';
    $i['10d'] = '18';
    $i['11d'] = '15';
    $i['13d'] = '19';
    $i['50d'] = '05';
    $i['01n'] = '33';
    $i['02n'] = '34';
    $i['03n'] = '36';
    $i['04n'] = '35';
    $i['09n'] = '39';
    $i['10n'] = '40';
    $i['11n'] = '42';
    $i['13n'] = '44';
    $i['50n'] = '37';
    if (isset($i[$iconCode]))
        return $i[$iconCode];
    else {
        //Maybe they added more icons. The app has more, so let's try to convert
        $startPos = 0;
        if (strpos($iconCode, "n") != -1)
            $startPos = 33; //App's night time icons start at 33
        $iconCode = str_replace($iconCode, "d", "");
        $iconCode = str_replace($iconCode, "n", "");
        $code = intval($iconCode);
        $code = $code + $startPos;
        $useCode = strval($code);
        if ($code < 0)
            $useCode = "0" . $useCode;
        return $useCode;
    }
}

//Map old index names to new ones
$indices = array(
    "Grass Growing Forecast" => "grassGrowing",
    "Arthritis Pain Forecast" => "arthritis_daytime",
    "Asthma Forecast" => "asthma",
    "Outdoor Barbecue" => "barbeque",
    "Beach & Pool Forecast" => "Beach Going",
    "Bicycling Forecast" => "Biking",
    "Common Cold Forecast" => "cold",
    "Outdoor Concert Forecast" => "Outdoor Concert",
    "Fishing Forecast" => "Fishing",
    "Flu Forecast" => "flu",
    "Golf Weather Forecast" => "Golf",
    "Hiking Forecast" => "Hiking",
    "Jogging Forecast" => "Jogging",
    "Kite Flying Forecast" => "Kite Flying",
    "Migraine Headache Forecast" => "migraine",
    "Mosquito Activity Forecast" => "mosquito",
    "Lawn Mowing Forecast"=> "lawnMowing",
    "Outdoor Activity Forecast" => "outdoor",
    "Running Forecast" => "Running",
    "Sailing Forecast" => "Sailing",
    "Sinus Headache Forecast" => "sinus",
    "Skateboarding Forecast" => "Skating",
    "Ski Weather Forecast" => "Skiing",
    "Stargazing Forecast" => "Star Gazing",
    "Tennis Forecast" => "Tennis",
    "Driving Travel Index" => "travel",
    "Dog Walking Comfort Forecast" => "dogWalking",
    "Indoor Activity Forecast" => "indoorActivity",
    "Hair Frizz Forecast" => "frizz",
);
?>
