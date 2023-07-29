# What Is This?

Accuweather provided an XML-based API for more than a decade that is used by a bunch of legacy devices. Recently they deprecated the API in favor of a new (mostly non-free) JSON API, and have announced plans to shut down the old one. As of July 2023, the endpoint appears to be down permanently, after years of disrepair where it returned mal-formed XML with increasing frequency.

This project provides a proxy that calls the new JSON API and returns XML results structured to look like the old API. In effect, this is a compatibility layer for Accuweather, allowing older devices to continue to get Accuweather forecasts. Some limitations of the new API are present (forecasts are limited to 5 days), but generally keep things working the way they did a decade ago. I built it for [legacy webOS](https://www.webosarchive.org), but it should work for retro iOS and Android clients too.

# Authentication and Authorization

The old API identified clients with a custom URL -- if you knew the URL, you could use the endpoint. Not terribly secure.

The new API uses an API key, which you have to get from Accuweather:
* https://developer.accuweather.com

Once you have an API key, copy `config-example.php` to `config.php` and enter the key. Paid keys allow for more API calls in a 24 hour period, while free keys are limited to 50 calls/24 hours. 

If you were to sign-up for multiple free API keys, you could enter each one on its own line in `config.php` and a random key would be selected for each API call. This likely violates the terms of Accuweather's developer agreement, so try this at your own risk.

## Limitations

* Be aware that while the original API provided one end-point for a complete forecast payload, the new API requires multiple calls to different endpoints to construct the payload expected by older clients, increasing the rate at which your call limit is consumed. More aggressive [caching](#caching) may help.
* Free API access is limited to a 5-day forecast. To get more than this, you have to pay. If you pay for more than 5 days, update the `config.php` to increase the XML output.
* Free API access excludes weather alerts, so they have not been included in this project.

# Hosting

The original endpoint was provided via ASP -- a long-dead Microsoft technology. Unfortunately, old clients have this path hard-coded. This means you need to setup the folder structure on your server to match, and configure the server to serve ASP pages with PHP.

## Folder structure

As mentioned above, legacy clients were identified by the folder path. This project includes two empty folders (eg: `widget\blstreamhptablet`)that match the path expected by old webOS mobile devies. A symlink to `weather-data.asp` should be included in each folder.

## Caching

The service will attempt to create a cache folder in the root of the project (unless you change the path in `config.php`). Caching can reduce API calls, in exchange for potentially offering up stale data. If needed, create the cache folder manually, and ensure the web server user (eg: `www-data`) has write access to that folder.

Each function in `accuweather-proxy.php` specifies its cache duration in hours when it calls the Accuweather service. You may tune this to reduce data staleness.

## Impersonating ASP

Obviously this setup will vary depending on your server software of choice, but I've made it work with both nginx and Apache2. Generally, the steps are the same:

* Tell the server to send `.asp` extensions to PHP: 
  * nginx: [https://serverfault.com/questions/683496/pass-asp-as-php-on-nginx](https://serverfault.com/questions/683496/pass-asp-as-php-on-nginx)
  * Apache2: [https://stackoverflow.com/a/3303133](https://stackoverflow.com/a/3303133)

* Tell PHP that `.asp` is a safe extension to process:
  * [https://serverfault.com/a/683510](https://serverfault.com/a/683510)

## Spoofing the original URL

Fooling the client into connecting to your service, rather than Accuweather's service, usually involves changing the `hosts` file on the client's OS to point to your server's domain. You may achieve a similar outcome if you provide a proxy to legacy clients...

### Squid Setup
* Squid needs the domain set in no-proxy
* Hosts file on proxy server needs to point domain to localhost
* *Deprecated due to legacy API sunset:* At least one function needs a hostname for the actual Accuweather service, add this to your HOSTS file, and update the `realServiceDomain` variable in `config.php`

# Mapping to the new API

The old endpoint was called with a single URL. The response payload for such a call was monolithic, and included all the elements needed for the app. Example payloads can be found in the XMLPayloads folder of this project. They serve no other purpose.

* As of July, 2023 the old API is gone. The DNS entry has been removed, and if you work-around with a hosts file entry, you get an error that the subscription has expired.
* For historical reference, the old API endpoints resolved to `63.85.115.86` and supported URLs like:
  * `http://accuwxiphonev4.accu-weather.com/widget/accuwxiphonev4/city-find.asp?location=London`
  * `http://blstreamhptablet.accu-weather.com/widget/blstreamhptablet/weather-data.asp?location=37935_PC&metric=1&lang=en`

Accuweather's documentation for the new API is generally pretty good, and takes a more piece-meal approach -- different calls, for different sets of forecast data. Start with the flow chart that describes typical use:

* Find the Accuweather location ID, through one of their search APIs
* Fetch the various forecasts
  * Note that for most queries, you want to add `&details=true` to get extra info, like RealFeel
* Assemble a XML payload that mimics the old structure

## Location Examples
* Paris: cityId:623
  * `?location=cityId:623&metric=1&lang=en`
* LA: postalCode:90210|US
  * `?location=postalCode:90210|US&metric=0&lang=en`
  * `?location=37935_PC&metric=0&lang=en`

## Legacy XML Structure
* adc_database
    * units
    * local
    * watchwarnareas
    * currentconditions
    * forecast
        * url
        * day 1 (15 days: http://dataservice.accuweather.com/forecasts/v1/daily/15day/{locationKey})
        * ...
        * day 15
        * hourly (12 hours: http://dataservice.accuweather.com/forecasts/v1/hourly/12hour/{locationKey})
            * hour 12
            * ...
            * hour 11
    * hurricane
    * indices
    * video
    * copyright
    * use
    * product
    * redistribution

## Payload Comparisons

### Daily XML Portion
```
<day number="1">
    <url>
    http://www.accuweather.com/forecast-details.asp?partner=blstreamhptablet&zipcode=44113&fday=1
    </url>
    <obsdate>12/25/2020</obsdate>
    <daycode>Friday</daycode>
    <sunrise>7:52 AM</sunrise>
    <sunset>5:03 PM</sunset>
    <daytime>
        <txtshort>Snow showers, 3-6 in</txtshort>
        <txtlong>
        Cloudy; some snow, 3-6 in, breezy and much colder; untreated roads will be snow packed and slippery
        </txtlong>
        <weathericon>19</weathericon>
        <hightemperature>27</hightemperature>
        <lowtemperature>20</lowtemperature>
        <realfeelhigh>7</realfeelhigh>
        <realfeellow>3</realfeellow>
        <windspeed>16</windspeed>
        <winddirection>WSW</winddirection>
        <windgust>27</windgust>
        <maxuv>0</maxuv>
        <rainamount>0.00</rainamount>
        <snowamount>3.6</snowamount>
        <iceamount>0.00</iceamount>
        <precipamount>0.17</precipamount>
        <tstormprob>2</tstormprob>
    </daytime>
    <nighttime>
        <txtshort>Snow this evening, 1-3 in</txtshort>
        <txtlong>
        Early snow, 1-3 in, then flurries; storm total 8-12 in; hypothermia likely without protective clothing
        </txtlong>
        <weathericon>22</weathericon>
        <hightemperature>27</hightemperature>
        <lowtemperature>20</lowtemperature>
        <realfeelhigh>1</realfeelhigh>
        <realfeellow>-5</realfeellow>
        <windspeed>21</windspeed>
        <winddirection>WSW</winddirection>
        <windgust>28</windgust>
        <maxuv>0</maxuv>
        <rainamount>0.00</rainamount>
        <snowamount>2.0</snowamount>
        <iceamount>0.00</iceamount>
        <precipamount>0.11</precipamount>
        <tstormprob>0</tstormprob>
    </nighttime>
</day>
```

### Daily JSON Call
* `curl -X GET "http://dataservice.accuweather.com/forecasts/v1/daily/5day/349727?apikey=<YOURAPIKEY>`
```
{
  "Headline": {
    "EffectiveDate": "2021-04-02T08:00:00-04:00",
    "EffectiveEpochDate": 1617364800,
    "Severity": 7,
    "Text": "Noticeably cooler weather on the way tomorrow",
    "Category": "cooler",
    "EndDate": "2021-04-02T20:00:00-04:00",
    "EndEpochDate": 1617408000,
    "MobileLink": "http://m.accuweather.com/en/us/new-york-ny/10007/extended-weather-forecast/349727?lang=en-us",
    "Link": "http://www.accuweather.com/en/us/new-york-ny/10007/daily-weather-forecast/349727?lang=en-us"
  },
  "DailyForecasts": [
    {
      "Date": "2021-04-01T07:00:00-04:00",
      "EpochDate": 1617274800,
      "Temperature": {
        "Minimum": {
          "Value": 32,
          "Unit": "F",
          "UnitType": 18
        },
        "Maximum": {
          "Value": 50,
          "Unit": "F",
          "UnitType": 18
        }
      },
      "Day": {
        "Icon": 12,
        "IconPhrase": "Showers",
        "HasPrecipitation": true,
        "PrecipitationType": "Rain",
        "PrecipitationIntensity": "Light"
      },
      "Night": {
        "Icon": 35,
        "IconPhrase": "Partly cloudy",
        "HasPrecipitation": false
      },
      "Sources": [
        "AccuWeather"
      ],
      "MobileLink": "http://m.accuweather.com/en/us/new-york-ny/10007/daily-weather-forecast/349727?day=1&lang=en-us",
      "Link": "http://www.accuweather.com/en/us/new-york-ny/10007/daily-weather-forecast/349727?day=1&lang=en-us"
    },
```

### Hourly XML Portion
```
<hourly>
    <hour time="12 PM">
        <weathericon>19</weathericon>
        <temperature>24</temperature>
        <realfeel>4</realfeel>
        <dewpoint>19</dewpoint>
        <humidity>81</humidity>
        <precip>0.013</precip>
        <rain>0</rain>
        <snow>0.42</snow>
        <ice>0</ice>
        <windspeed>16</windspeed>
        <winddirection>SW</winddirection>
        <windgust>24</windgust>
        <txtshort>Flurries</txtshort>
        <traditionalLink>
        http://www.accuweather.com/forecast-hourly.asp?partner=blstreamhptablet&zipcode=44113&fday=1&hbhhour=12
        </traditionalLink>
    </hour>
```

### Hourly JSON Call
* `curl -X GET "http://dataservice.accuweather.com/forecasts/v1/hourly/12hour/349727?apikey=<YOURAPIKEY>`
```
[
    {
        "DateTime": "2021-04-01T19:00:00-04:00",
        "EpochDateTime": 1617318000,
        "WeatherIcon": 3,
        "IconPhrase": "Partly sunny",
        "HasPrecipitation": false,
        "IsDaylight": true,
        "Temperature": {
        "Value": 45,
        "Unit": "F",
        "UnitType": 18
        },
        "PrecipitationProbability": 22,
        "MobileLink": "http://m.accuweather.com/en/us/new-york-ny/10007/hourly-weather-forecast/349727?day=1&hbhhour=19&lang=en-us",
        "Link": "http://www.accuweather.com/en/us/new-york-ny/10007/hourly-weather-forecast/349727?day=1&hbhhour=19&lang=en-us"
    },
```

## Indices XML Portion
```
<indices>
    <indice name="grassGrowing" value="0"/>
    <indice name="arthritis_daytime" value="5"/>
    <indice name="arthritis_nighttime" value="5"/>
    <indice name="asthma" value="1"/>
    <indice name="barbeque" value="0"/>
    <indice name="Beach Going" value="0"/>
    <indice name="Biking" value="0"/>
    <indice name="cold" value="7"/>
    <indice name="Outdoor Concert" value="0"/>
    <indice name="Fishing" value="0"/>
    <indice name="flu" value="5"/>
    <indice name="Golf" value="0"/>
    <indice name="Hiking" value="0"/>
    <indice name="Jogging" value="0"/>
    <indice name="Kite Flying" value="0"/>
    <indice name="migraine" value="1"/>
    <indice name="mosquito" value="0"/>
    <indice name="lawnMowing" value="0"/>
    <indice name="outdoor" value="0"/>
    <indice name="Running" value="0"/>
    <indice name="Sailing" value="0"/>
    <indice name="sinus" value="5"/>
    <indice name="Skating" value="0"/>
    <indice name="Skiing" value="7"/>
    <indice name="Star Gazing" value="0"/>
    <indice name="Tennis" value="0"/>
    <indice name="travel" value="6"/>
    <indice name="dogWalking" value="0"/>
    <indice name="indoorActivity" value="10"/>
    <indice name="frizz" value="3"/>
</indices>
```

## Indices JSON Call
* `curl -X GET "http://dataservice.accuweather.com/indices/v1/daily/1day/349727?apikey=<YOURAPIKEY>`
```
[
  {
    "Name": "Flight Delays",
    "ID": -3,
    "Ascending": true,
    "LocalDateTime": "2021-04-01T07:00:00-04:00",
    "EpochDateTime": 1617274800,
    "Value": 8,
    "Category": "Very Unlikely",
    "CategoryValue": 5,
    "MobileLink": "http://m.accuweather.com/en/us/new-york-ny/10007/air-travel-weather/349727?lang=en-us",
    "Link": "http://www.accuweather.com/en/us/new-york-ny/10007/air-travel-weather/349727?lang=en-us"
  },
  {
    "Name": "Indoor Activity Forecast",
    "ID": -2,
    "Ascending": true,
    "LocalDateTime": "2021-04-01T07:00:00-04:00",
    "EpochDateTime": 1617274800,
    "Value": 6.7,
    "Category": "Good",
    "CategoryValue": 3,
    "MobileLink": null,
    "Link": null
  },
  {
    "Name": "Running Forecast",
    "ID": 1,
    "Ascending": true,
    "LocalDateTime": "2021-04-01T07:00:00-04:00",
    "EpochDateTime": 1617274800,
    "Value": 3.6,
    "Category": "Fair",
    "CategoryValue": 2,
    "MobileLink": "http://m.accuweather.com/en/us/new-york-ny/10007/running-weather/349727?lang=en-us",
    "Link": "http://www.accuweather.com/en/us/new-york-ny/10007/running-weather/349727?lang=en-us"
  },
  ```
