<?php

function get_postalcode_locationId($locationId, $apiKey) {
    //TODO: This needs to be cached to avoid extra API calls
    $locParts = explode("|", $locationId);
    $pcode = str_replace("postalCode:", "", $locParts[0]);
    $ccode = "US";
    if (count($locParts) > 1) {
        $ccode = $locParts[1];
    }
    $serviceUrl = "http://dataservice.accuweather.com/locations/v1/postalcodes/" . $ccode . "/search?apikey=" . $apiKey . "&q=" . $pcode;
    $serviceRaw = get_remote_data($serviceUrl);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (is_array($serviceData) && isset($serviceData[0]) && isset($serviceData[0]->Key)) {
            return $serviceData[0]->Key;
        } else {
            return 0;
        }
    }
}

function get_units_asXml($useMetric) {
    if (!$useMetric) {
        return "<units>\r\n<temp>F</temp>\r\n<dist>MI</dist>\r\n<speed>MPH</speed>\r\n<pres>IN</pres>\r\n<prec>IN</prec>\r\n</units>";
    } else {
        return "<units>\r\n<temp>C</temp>\r\n<dist>KM</dist>\r\n<speed>KPH</speed>\r\n<pres>CM</pres>\r\n<prec>CM</prec>\r\n</units>";
    }
}

function get_local_data($locationId, $apiKey) {
    $serviceUrl = "http://dataservice.accuweather.com/locations/v1/" . $locationId . "?apikey=" . $apiKey . "&details=true";
    $serviceRaw = get_remote_data($serviceUrl);
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData)) {
            return $serviceData;
        }
    }
    return null;
}

function convert_local_data_toXml($localData) {
    $serviceData = $localData;
    $returnData = "";
    if (isset($serviceData)) {
        try {
            $returnData .= "<local>\r\n";
            $returnData .= "  <city>" . $serviceData->LocalizedName  . "</city>\r\n";
            $returnData .= "  <adminArea code=\"" . $serviceData->AdministrativeArea->ID . "\">" .  $serviceData->AdministrativeArea->LocalizedName  . "</adminArea>\r\n";
            $returnData .= "  <country code=\"" . $serviceData->Country->ID . "\">" .  $serviceData->Country->LocalizedName . "</country>\r\n";
            $returnData .= "  <cityId>" . $serviceData->Key . "</cityId>\r\n";
            $returnData .= "  <primaryPostalCode>" . $serviceData->PrimaryPostalCode . "</primaryPostalCode>\r\n";
            $returnData .= "  <locationKey>" . $serviceData->Key . "</locationKey>\r\n";
            $returnData .= "  <lat>" . $serviceData->GeoPosition->Latitude . "</lat>\r\n";
            $returnData .= "  <lon>" . $serviceData->GeoPosition->Longitude . "</lon>\r\n";
            $returnData .= "  <time>" . date("h:i") . "</time>\r\n";
            $returnData .= "  <timeZone>" . $serviceData->TimeZone->GmtOffset . "</timeZone>\r\n";
            $returnData .= "  <obsDaylight>" . $serviceData->TimeZone->IsDaylightSaving . "</obsDaylight>\r\n";
            $returnData .= "  <timeZoneAbbreviation>" . $serviceData->TimeZone->Code . "</timeZoneAbbreviation>\r\n";
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

function get_current_conditions_asXml($locationId, $useMetric, $apiKey) {
    $serviceUrl = "http://dataservice.accuweather.com/currentconditions/v1/" . $locationId . "?apikey=" . $apiKey . "&details=true";
    $serviceRaw = get_remote_data($serviceUrl);
    $returnData = "";
    $units = "Metric";
    if (!$useMetric)
        $units = "Imperial";
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (is_array($serviceData) && isset($serviceData[0])) {
            try {
                $returnData .= "<currentconditions daylight=\"" . ucfirst(var_export($serviceData[0]->IsDayTime, true)) . "\">\r\n";
                $returnData .= "<url>" . str_replace("&", "&amp;", $serviceData[0]->MobileLink) . "</url>\r\n";
                //Note: original dataset used AM/PM or h:i A
                $returnData .= "<observationtime>" . date("H:m", strtotime($serviceData[0]->LocalObservationDateTime)) . "</observationtime>\r\n";
                $returnData .= "<pressure state=\"" . $serviceData[0]->PressureTendency->LocalizedText . "\">" .  $serviceData[0]->Pressure->Imperial->Value . "</pressure>\r\n";
                $returnData .= "<temperature>" . $serviceData[0]->Temperature->$units->Value . "</temperature>\r\n";
                $returnData .= "<realfeel>" . $serviceData[0]->RealFeelTemperature->$units->Value . "</realfeel>\r\n";
                $returnData .= "<humidity>" . $serviceData[0]->RelativeHumidity . "</humidity>\r\n";
                $returnData .= "<weathertext>" . $serviceData[0]->WeatherText . "</weathertext>\r\n";
                $returnData .= "<weathericon>" . sprintf("%02d", $serviceData[0]->WeatherIcon) . "</weathericon>\r\n";
                $returnData .= "<windgusts>" . $serviceData[0]->WindGust->Speed->$units->Value . "</windgusts>\r\n";
                $returnData .= "<windspeed>" . $serviceData[0]->Wind->Speed->$units->Value . "</windspeed>\r\n";
                $returnData .= "<winddirection>" . $serviceData[0]->Wind->Direction->Degrees . "°</winddirection>\r\n";
                $returnData .= "<visibility>" . $serviceData[0]->Visibility->$units->Value . "</visibility>\r\n";
                $returnData .= "<precip>" . $serviceData[0]->PrecipitationSummary->Precipitation->$units->Value . "</precip>\r\n";
                $returnData .= "<uvindex index=\"" . $serviceData[0]->UVIndexText . "\">" .  $serviceData[0]->UVIndex . "</uvindex>\r\n";
                $returnData .= "<dewpoint>" . $serviceData[0]->DewPoint->$units->Value . "</dewpoint>\r\n";
                $returnData .= "<cloudcover>" . $serviceData[0]->CloudCover . "</cloudcover>\r\n";
                $returnData .= "<apparenttemp>" . $serviceData[0]->ApparentTemperature->$units->Value . "</apparenttemp>\r\n";
                $returnData .= "<windchill>" . $serviceData[0]->WindChillTemperature->$units->Value . "</windchill>\r\n";
                $returnData .= "</currentconditions>\r\n";
            } catch (Exception $e) {
                return "<error>an error occurred while parsing remote service data. last attempted node was: currentconditions</error>";
            }
        } else {
            if (null !== $serviceData && isset($serviceData->Message)) {
                return "<error>" . $serviceData->Message . "</error>";
            } else {
                $errormessage = "<error>response from remote service could not be parsed: ";
                $errormessage .= "<![CDATA[" . $serviceRaw . "]]>";
                $errormessage .= "</error>";
                return $errormessage;
            }
        }
    } else {
        return "<error>data could not be retreived from remote service";
    }
    return $returnData;
}

function get_daily_forecast_asXml($locationId, $forecastDays, $useMetric, $apiKey) {
    $serviceUrl = "http://dataservice.accuweather.com/forecasts/v1/daily/" . $forecastDays . "day/" . $locationId . "?apikey=" . $apiKey . "&details=true&metric=" . var_export($useMetric, true);
    $serviceRaw = get_remote_data($serviceUrl);
    $returnData = "";
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData->Headline) && isset($serviceData->Headline->MobileLink)) {
            $returnData .= "<url>" . str_replace("&", "&amp;", $serviceData->Headline->MobileLink) . "</url>\r\n";
        }
        if (isset($serviceData->DailyForecasts) && is_array($serviceData->DailyForecasts)) {
            $dayCount = 0;
            foreach($serviceData->DailyForecasts as $day){
                $dayCount++;
                try {
                    $returnData .= "<day number=\"" . $dayCount . "\">\r\n";
                    $returnData .= "  <url>" . str_replace("&", "&amp;", $day->MobileLink) . "</url>\r\n";
                    //Note: original dataset used AM/PM or h:i A
                    $returnData .= "  <obsdate>" . date("m/d/Y", strtotime($day->Date)) . "</obsdate>\r\n";
                    $returnData .= "  <daycode>" . date('l', strtotime($day->Date)) . "</daycode>\r\n";
                    $returnData .= "  <sunrise>" . date("H:m", strtotime($day->Sun->Rise)) . "</sunrise>\r\n";
                    $returnData .= "  <sunset>" . date("H:m", strtotime($day->Sun->Set)) . "</sunset>\r\n";
                    $returnData .= "  <daytime>\r\n";
                    $returnData .= "    <txtshort>" . $day->Day->ShortPhrase . "</txtshort>\r\n";
                    $returnData .= "    <txtlong>" . $day->Day->LongPhrase . "</txtlong>\r\n";
                    $returnData .= "    <weathericon>" . sprintf("%02d", $day->Day->Icon) . "</weathericon>\r\n";
                    $returnData .= "    <hightemperature>" . $day->Temperature->Maximum->Value . "</hightemperature>\r\n";
                    $returnData .= "    <lowtemperature>" . $day->Temperature->Minimum->Value . "</lowtemperature>\r\n";
                    $returnData .= "    <realfeelhigh>" . $day->RealFeelTemperature->Maximum->Value . "</realfeelhigh>\r\n";
                    $returnData .= "    <realfeellow>" . $day->RealFeelTemperature->Minimum->Value . "</realfeellow>\r\n";
                    $returnData .= "    <windspeed>" . $day->Day->Wind->Speed->Value . "</windspeed>\r\n";
                    $returnData .= "    <winddirection>" . $day->Day->Wind->Direction->Degrees . "</winddirection>\r\n";
                    $returnData .= "    <windgust>" . $day->Day->WindGust->Speed->Value . "</windgust>\r\n";
                    $returnData .= "    <maxuv>" . "TODO" . "</maxuv>\r\n";
                    $returnData .= "    <rainamount>" . $day->Day->Rain->Value . "</rainamount>\r\n";
                    $returnData .= "    <snowamount>" . $day->Day->Snow->Value . "</snowamount>\r\n";
                    $returnData .= "    <iceamount>" . $day->Day->Ice->Value . "</iceamount>\r\n";
                    $returnData .= "    <precipamount>" . $day->Day->PrecipitationProbability . "</precipamount>\r\n";
                    $returnData .= "    <tstormprob>" . $day->Day->ThunderstormProbability . "</tstormprob>\r\n";
                    $returnData .= "  </daytime>\r\n";
                    $returnData .= "  <nighttime>\r\n";
                    $returnData .= "    <txtshort>" . $day->Night->ShortPhrase . "</txtshort>\r\n";
                    $returnData .= "    <txtlong>" . $day->Night->LongPhrase . "</txtlong>\r\n";
                    $returnData .= "    <weathericon>" . sprintf("%02d", $day->Night->Icon) . "</weathericon>\r\n";
                    $returnData .= "    <hightemperature>" . $day->Temperature->Maximum->Value . "</hightemperature>\r\n";
                    $returnData .= "    <lowtemperature>" . $day->Temperature->Minimum->Value . "</lowtemperature>\r\n";
                    $returnData .= "    <realfeelhigh>" . $day->RealFeelTemperature->Maximum->Value . "</realfeelhigh>\r\n";
                    $returnData .= "    <realfeellow>" . $day->RealFeelTemperature->Minimum->Value . "</realfeellow>\r\n";
                    $returnData .= "    <windspeed>" . $day->Night->Wind->Speed->Value . "</windspeed>\r\n";
                    $returnData .= "    <winddirection>" . $day->Night->Wind->Direction->Degrees . "</winddirection>\r\n";
                    $returnData .= "    <windgust>" . $day->Night->WindGust->Speed->Value . "</windgust>\r\n";
                    $returnData .= "    <maxuv>" . "TODO" . "</maxuv>\r\n";
                    $returnData .= "    <rainamount>" . $day->Night->Rain->Value . "</rainamount>\r\n";
                    $returnData .= "    <snowamount>" . $day->Night->Snow->Value . "</snowamount>\r\n";
                    $returnData .= "    <iceamount>" . $day->Night->Ice->Value . "</iceamount>\r\n";
                    $returnData .= "    <precipamount>" . $day->Night->PrecipitationProbability . "</precipamount>\r\n";
                    $returnData .= "    <tstormprob>" . $day->Night->ThunderstormProbability . "</tstormprob>\r\n";
                    $returnData .= "  </nighttime>";
                    $returnData .= "</day>\r\n";
                } catch (Exception $e) {
                    return "<error>an error occurred while parsing remote service data. last attempted node was: dailyforecast</error>";
                }
            }
        } else {
            if (null !== $serviceData && isset($serviceData->Message)) {
                return "<error>" . $serviceData->Message . "</error>";
            } else {
                $errormessage = "<error>response from remote service could not be parsed: ";
                $errormessage .= "<![CDATA[" . $serviceRaw . "]]>";
                $errormessage .= "</error>";
                return $errormessage;
            }
        }
    } else {
        return "<error>data could not be retreived from remote service";
    }
    return $returnData;
}

function get_hourly_forecast_asXml($locationId, $tzOffset, $useMetric, $apiKey) {
    $tzOffset = sprintf("%+d",$tzOffset);
    $serviceUrl = "http://dataservice.accuweather.com/forecasts/v1/hourly/12hour/" . $locationId . "?apikey=" . $apiKey . "&details=true&metric=" . var_export($useMetric, true);
    $serviceRaw = get_remote_data($serviceUrl);
    $returnData = "<hourly>";
    if (isset($serviceRaw)) {
        $serviceData = json_decode($serviceRaw);
        if (isset($serviceData) && is_array($serviceData)) {
            foreach($serviceData as $hour){
                try {
                    //Note: original dataset used AM/PM or h A
                    $localDateTime = new DateTime($hour->DateTime, new DateTimeZone('UTC'));
                    $localDateTime->setTimezone(new DateTimeZone($tzOffset));
                    $returnData .= "<hour time=\"" . $localDateTime->format('H'). "\">\r\n";
                    $returnData .= "  <weathericon>" . sprintf("%02d", $hour->WeatherIcon) . "</weathericon>\r\n";
                    $returnData .= "  <temperature>" . $hour->Temperature->Value . "</temperature>\r\n";
                    $returnData .= "  <realfeel>" . $hour->RealFeelTemperature->Value . "</realfeel>\r\n";
                    $returnData .= "  <dewpoint>" . $hour->DewPoint->Value . "</dewpoint>\r\n";
                    $returnData .= "  <humidity>" . $hour->RelativeHumidity . "</humidity>\r\n";
                    $returnData .= "  <precip>" . $hour->PrecipitationProbability . "</precip>\r\n";
                    $returnData .= "  <rain>" . $hour->Rain->Value . "</rain>\r\n";
                    $returnData .= "  <snow>" . $hour->Snow->Value . "</snow>\r\n";
                    $returnData .= "  <ice>" . $hour->Ice->Value . "</ice>\r\n";
                    $returnData .= "  <windspeed>" . $hour->Wind->Speed->Value . "</windspeed>\r\n";
                    $returnData .= "  <winddirection>" . $hour->Wind->Direction->Degrees . "</winddirection>\r\n";
                    $returnData .= "  <windgust>" . $hour->WindGust->Speed->Value . "</windgust>\r\n";
                    $returnData .= "  <txtshort>" . $hour->IconPhrase . "</txtshort>\r\n";
                    $returnData .= "  <traditionalLink>" . str_replace("&", "&amp;", $hour->MobileLink) . "</traditionalLink>\r\n";
                    $returnData .= "</hour>\r\n";
                    
                } catch (Exception $e) {
                    return "<error>an error occurred while parsing remote service data. last attempted node was: hourlyforecast: " . $e . "</error>";
                }
            }
        } else {
            if (null !== $serviceData && isset($serviceData->Message)) {
                return "<error>" . $serviceData->Message . "</error>";
            } else {
                $errormessage = "<error>response from remote service could not be parsed: ";
                $errormessage .= "<![CDATA[" . $serviceRaw . "]]>";
                $errormessage .= "</error>";
                return $errormessage;
            }
        }
    } else {
        return "<error>data could not be retreived from remote service";
    }
    $returnData .= "</hourly>";
    return $returnData;
}

function get_indices_asXml($locationId, $apiKey) {
    global $indices;
    $serviceUrl = "http://dataservice.accuweather.com/indices/v1/daily/1day/" . $locationId . "?apikey=" . $apiKey;
    $serviceRaw = get_remote_data($serviceUrl);
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
                $errormessage = "<error>response from remote service could not be parsed: ";
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

function get_remote_data($url) {
    //make outbound request to service
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

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return "{error:'" . $err . "'}";
    } else {
        return $response;
    }
}

//Map old index names to new ones
$indices = array(
    "Grass Growing Forecast" => "grassGrowing",
    "Arthritis Pain Forecast" => "arthritis_daytime",
    "Arthritis Pain Forecast" => "arthritis_nighttime",
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