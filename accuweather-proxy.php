<?php
$accuweatherRoot = "http://dataservice.accuweather.com";
$openweatherRoot = "https://api.openweathermap.org/data/3.0/";

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
        return "<units>\r\n<temp>F</temp>\r\n<dist>MI</dist>\r\n<speed>MPH</speed>\r\n<pres>IN</pres>\r\n<prec>IN</prec>\r\n</units>\r\n";
    } else {
        return "<units>\r\n<temp>C</temp>\r\n<dist>KM</dist>\r\n<speed>KPH</speed>\r\n<pres>CM</pres>\r\n<prec>CM</prec>\r\n</units>\r\n";
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
            $returnData .= "<local>\r\n";
            $returnData .= "  <city>" . $accuweatherData->LocalizedName  . "</city>\r\n";
            $returnData .= "  <adminArea code=\"" . $accuweatherData->AdministrativeArea->ID . "\">" .  $accuweatherData->AdministrativeArea->LocalizedName  . "</adminArea>\r\n";
            $returnData .= "  <country code=\"" . $accuweatherData->Country->ID . "\">" .  $accuweatherData->Country->LocalizedName . "</country>\r\n";
            $returnData .= "  <cityId>" . $accuweatherData->Key . "</cityId>\r\n";
            $returnData .= "  <primaryPostalCode>" . $accuweatherData->PrimaryPostalCode . "</primaryPostalCode>\r\n";
            $returnData .= "  <locationKey>" . $accuweatherData->Key . "</locationKey>\r\n";
            $returnData .= "  <lat>" . $openweatherData->lat . "</lat>\r\n";
            $returnData .= "  <lon>" . $openweatherData->lon . "</lon>\r\n";
            $timestamp = $openweatherData->current->dt + $openweatherData->timezone_offset;
            $useTime = gmdate("H:i", $timestamp);
            $returnData .= "  <time>" . $useTime . "</time>\r\n";
            $returnData .= "  <timeZone>" . $openweatherData->timezone . "</timeZone>\r\n";
            $returnData .= "  <obsDaylight>" . $accuweatherData->TimeZone->IsDaylightSaving . "</obsDaylight>\r\n";
            $returnData .= "  <timeZoneAbbreviation>" . $accuweatherData->TimeZone->Code . "</timeZoneAbbreviation>\r\n";
            $returnData .= "</local>\r\n";
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: location</error>";
        }
    } else {
        $errormessage = "<error>location data from remote service could not be parsed</error>";
        return $errormessage;
    }
    return $returnData;
}

function get_current_conditions_asXml($serviceData, $useMetric) {
    $units = "Metric";
    $returnData = "";
    if (!$useMetric)
        $units = "Imperial";
    try {
        $isDaylight = false;
        if ($serviceData->current->dt > $serviceData->current->sunrise && $serviceData->current->dt < $serviceData->current->sunset)
            $isDaylight = true;
        $returnData .= "<currentconditions daylight=\"" . $isDaylight . "\">\r\n";
        
        //$returnData .= "<url>" . str_replace("&", "&amp;", $serviceData[0]->MobileLink) . "</url>\r\n";
        //Note: original dataset used AM/PM or h:i A
        $timestamp = $serviceData->current->dt + $serviceData->timezone_offset;
        $returnData .= "    <observationtime>" . gmdate("H:i", $timestamp) . "</observationtime>\r\n";
        //TODO: this pressure conversion should be double-checked!
        $returnData .= "    <pressure state=\"Unknown\">" .  ($serviceData->current->pressure * 0.0294) . "</pressure>\r\n";
        $returnData .= "    <temperature>" . $serviceData->current->temp . "</temperature>\r\n";
        $returnData .= "    <realfeel>" . $serviceData->current->feels_like . "</realfeel>\r\n";
        $returnData .= "    <humidity>" . $serviceData->current->humidity . "</humidity>\r\n";
        $returnData .= "    <weathertext>" . $serviceData->current->weather[0]->description . "</weathertext>\r\n";
        $returnData .= "    <weathericon>" . map_weather_icon($serviceData->current->weather[0]->icon) . "</weathericon>\r\n";
        $returnData .= "    <windgusts>" . $serviceData->hourly[0]->wind_gust . "</windgusts>\r\n";
        $returnData .= "    <windspeed>" . $serviceData->current->wind_speed . "</windspeed>\r\n";
        $returnData .= "    <winddirection>" . $serviceData->current->wind_deg . "°</winddirection>\r\n";
        //TODO: openweather returns in meters, is this a good conversion?
        $returnData .= "    <visibility>" . ($serviceData->current->visibility / 1000). "</visibility>\r\n";
        $precipAmt = 0;
        if (isset($serviceData->current->rain))
            $precipAmt = $precipAmt + $serviceData->current->rain;
        if (isset($serviceData->current->snow))
            $precipAmt = $precipAmt + $serviceData->current->snow;
        $returnData .= "    <precip>" . $precipAmt . "</precip>\r\n";
        $returnData .= "    <uvindex index=\"" . $serviceData->current->uvi . "\">" .  map_uvi_text($serviceData->current->uvi) . "</uvindex>\r\n";
        $returnData .= "    <dewpoint>" . $serviceData->current->dew_point . "</dewpoint>\r\n";
        $returnData .= "    <cloudcover>" . $serviceData->current->clouds . "%</cloudcover>\r\n";
        $returnData .= "    <apparenttemp>" . $serviceData->current->feels_like . "</apparenttemp>\r\n";
        //TODO: missing: $returnData .= "<windchill>" . $serviceData[0]->WindChillTemperature->$units->Value . "</windchill>\r\n";
        $returnData .= "</currentconditions>\r\n";
    } catch (Exception $e) {
        return "<error>an error occurred while parsing remote service data. last attempted node was: currentconditions</error>";
    }
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

function get_daily_forecast_asXml($serviceData, $useMetric) {
    $returnData = "";
    $dayCount = 0;
    foreach($serviceData->daily as $day){
        //if ($dayCount == 0 && $day->summary)
        //    $returnData .= "<url>" . str_replace("&", "&amp;", $serviceData->Headline->MobileLink) . "</url>\r\n";
        $dayCount++;
        try {
            $returnData .= "<day number=\"" . $dayCount . "\">\r\n";
            //$returnData .= "  <url>" . str_replace("&", "&amp;", $day->MobileLink) . "</url>\r\n";
            //Note: original dataset used AM/PM or h:i A
            $timestamp = $day->dt + $serviceData->timezone_offset;
            $returnData .= "  <obsdate>" . gmdate("m/d/Y", $timestamp) . "</obsdate>\r\n";
            $returnData .= "  <daycode>" . gmdate('l', $timestamp) . "</daycode>\r\n";
            $timestamp = $day->sunrise + $serviceData->timezone_offset;
            $returnData .= "  <sunrise>" . gmdate("H:i", $timestamp) . "</sunrise>\r\n";
            $timestamp = $day->sunset + $serviceData->timezone_offset;
            $returnData .= "  <sunset>" . gmdate("H:i", $timestamp) . "</sunset>\r\n";
            $returnData .= "  <daytime>\r\n";
            $returnData .= "    <txtshort>" . $day->weather[0]->description . "</txtshort>\r\n";
            $returnData .= "    <txtlong>" . $day->summary . "</txtlong>\r\n";
            $returnData .= "    <weathericon>" . map_weather_icon($day->weather[0]->icon) . "</weathericon>\r\n";
            $returnData .= "    <hightemperature>" . $day->temp->max . "</hightemperature>\r\n";
            $returnData .= "    <lowtemperature>" . $day->temp->min . "</lowtemperature>\r\n";
            $returnData .= "    <realfeelhigh>" . $day->feels_like->day . "</realfeelhigh>\r\n";
            $returnData .= "    <realfeellow>" . $day->feels_like->night . "</realfeellow>\r\n";
            $returnData .= "    <windspeed>" . $day->wind_speed . "</windspeed>\r\n";
            $returnData .= "    <winddirection>" . $day->wind_deg . "</winddirection>\r\n";
            $returnData .= "    <windgust>" . $day->wind_gust . "</windgust>\r\n";
            $returnData .= "    <maxuv>" . $day->uvi . "</maxuv>\r\n";
            $precipAmount = 0;
            if (isset($day->rain)) {
                $returnData .= "    <rainamount>" . $day->rain . "</rainamount>\r\n";
                $precipAmount = $precipAmount + $day->rain;
            } else {
                $returnData .= "    <rainamount>0</rainamount>\r\n";
            }
            if (isset($day->snow)) {
                $returnData .= "    <snowamount>" . $day->snow . "</snowamount>\r\n";
                $precipAmount = $precipAmount + $day->snow;
            } else {
                $returnData .= "    <snowamount>0</snowamount>\r\n";
            }
            $returnData .= "    <iceamount>0</iceamount>\r\n";
            $returnData .= "    <precipamount>" . $precipAmount . "</precipamount>\r\n";
            //TODO: this is actually precipitation probability, not storm probability
            $returnData .= "    <tstormprob>" . $day->pop . "</tstormprob>\r\n";
            $returnData .= "  </daytime>\r\n";
            $returnData .= "  <nighttime>\r\n";
            $returnData .= "    <txtshort>" . $day->weather[0]->description . "</txtshort>\r\n";
            $returnData .= "    <txtlong>" . $day->summary . "</txtlong>\r\n";
            //TODO: map icons including moon
            $returnData .= "    <weathericon>" . map_weather_icon($day->weather[0]->icon) . "</weathericon>\r\n";
            $returnData .= "    <hightemperature>" . $day->temp->night . "</hightemperature>\r\n";
            $returnData .= "    <lowtemperature>" . $day->temp->min . "</lowtemperature>\r\n";
            $returnData .= "    <realfeelhigh>" . $day->feels_like->eve . "</realfeelhigh>\r\n";
            $returnData .= "    <realfeellow>" . $day->feels_like->night . "</realfeellow>\r\n";
            $returnData .= "    <windspeed>" . $day->wind_speed . "</windspeed>\r\n";
            $returnData .= "    <winddirection>" . $day->wind_deg . "</winddirection>\r\n";
            $returnData .= "    <windgust>" . $day->wind_gust . "</windgust>\r\n";
            $returnData .= "    <maxuv>" . $day->uvi . "</maxuv>\r\n";
            if (isset($day->rain)) {
                $returnData .= "    <rainamount>" . $day->rain . "</rainamount>\r\n";
            } else {
                $returnData .= "    <rainamount>0</rainamount>\r\n";
            }
            if (isset($day->snow)) {
                $returnData .= "    <snowamount>" . $day->snow . "</snowamount>\r\n";
            } else {
                $returnData .= "    <snowamount>0</snowamount>\r\n";
            }
            $returnData .= "    <iceamount>0</iceamount>\r\n";
            $returnData .= "    <precipamount>" . $precipAmount . "</precipamount>\r\n";
            //TODO: this is actually precipitation probability, not storm probability
            $returnData .= "    <tstormprob>" . $day->pop . "</tstormprob>\r\n";
            $returnData .= "  </nighttime>";
            $returnData .= "</day>\r\n";
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: dailyforecast</error>";
        }
    }
    return $returnData;
}

function get_hourly_forecast_asXml($serviceData, $useMetric) {
    $returnData = "<hourly>\r\n";
    foreach($serviceData->hourly as $hour){
        try {
            
            $timestamp = $hour->dt + $serviceData->timezone_offset;
            //Note: original dataset used AM/PM or h A
            $returnData .= "<hour time=\"" . gmdate("H", $timestamp) . "\">\r\n";
            //TODO: map icons
            $returnData .= "  <weathericon>" . map_weather_icon($hour->weather[0]->icon) . "</weathericon>\r\n";
            $returnData .= "  <temperature>" . $hour->temp . "</temperature>\r\n";
            $returnData .= "  <realfeel>" . $hour->feels_like . "</realfeel>\r\n";
            $returnData .= "  <dewpoint>" . $hour->dew_point . "</dewpoint>\r\n";
            $returnData .= "  <humidity>" . $hour->humidity . "</humidity>\r\n";

            $precipAmount = 0;
            if (isset($hour->rain)) {
                if (isset($hour->rain->{'1h'}))
                    $rain = $hour->rain->{'1h'};
                else
                    $rain = $hour->rain;
                $returnData .= "  <rain>" . $rain . "</rain>\r\n";
                $precipAmount = $precipAmount + $rain;
            } else {
                $returnData .= "  <rain>0</rain>\r\n";
            }
            if (isset($hour->snow)) {
                if (isset($hour->snow->{'1h'}))
                    $snow = $hour->snow->{'1h'};
                else
                    $snow = $hour->snow;
                $returnData .= "  <snow>" . $snow . "</snow>\r\n";
                $precipAmount = $precipAmount + $snow;
            } else {
                $returnData .= "  <snow>0</snow>\r\n";
            }
            $returnData .= "  <ice>0</ice>\r\n";
            $returnData .= "  <precip>" . $precipAmount . "</precip>\r\n";
            $returnData .= "  <windspeed>" . $hour->wind_speed . "</windspeed>\r\n";
            $returnData .= "  <winddirection>" . $hour->wind_deg . "</winddirection>\r\n";
            $returnData .= "  <windgust>" . $hour->wind_gust . "</windgust>\r\n";
            $returnData .= "  <txtshort>" . $hour->weather[0]->main . "</txtshort>\r\n";
            //$returnData .= "  <traditionalLink>" . str_replace("&", "&amp;", $hour->MobileLink) . "</traditionalLink>\r\n";
            $returnData .= "</hour>\r\n";
            
        } catch (Exception $e) {
            return "<error>an error occurred while parsing remote service data. last attempted node was: hourlyforecast: " . $e . "</error>";
        }
    }
    $returnData .= "</hourly>\r\n";
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
    $i['04d'] = '06';
    $i['09d'] = '07';
    $i['10d'] = '12';
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