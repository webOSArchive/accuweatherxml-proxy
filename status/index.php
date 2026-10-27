<?php
// Health check for the proxy. Safe to make public: it never calls the upstream APIs
// (so it doesn't burn quota), and never shows API keys or server paths.
// Add ?format=json for machine-readable output. Responds 503 if any check fails.

$projectRoot = dirname(__DIR__);
// The .asp pages run from widget/<client>/, so relative paths in config.php are relative to there
$clientDir = $projectRoot . "/widget/blstreamhptablet/";
$clientFolders = array("accuwxiphonev4", "blstreamhptablet");
$endpoints = array("weather-data.asp", "city-find.asp");

$checks = array();
function add_check($group, $name, $state, $detail) {
    global $checks;
    $checks[] = array("group" => $group, "name" => $name, "state" => $state, "detail" => $detail);
}

function describe_age($timestamp) {
    $seconds = time() - $timestamp;
    if ($seconds < 120) return $seconds . " seconds ago";
    if ($seconds < 7200) return round($seconds / 60) . " minutes ago";
    if ($seconds < 172800) return round($seconds / 3600) . " hours ago";
    return round($seconds / 86400) . " days ago";
}

// --- PHP environment ---
add_check("PHP", "Version", "ok", PHP_VERSION);
foreach (array("curl", "json") as $ext) {
    if (extension_loaded($ext))
        add_check("PHP", "Extension: " . $ext, "ok", "loaded");
    else
        add_check("PHP", "Extension: " . $ext, "fail", "not loaded; the proxy cannot work without it");
}

// --- Configuration ---
$cacheRoot = null;
if (!file_exists($projectRoot . "/config.php")) {
    add_check("Config", "config.php", "fail", "missing; copy config-example.php to config.php");
} else {
    include($projectRoot . "/config.php");
    add_check("Config", "config.php", "ok", "present");
    foreach (array("AccuWeather" => "get_accuweatherApiKey", "OpenWeather" => "get_openweatherApiKey") as $service => $keyFunction) {
        $key = function_exists($keyFunction) ? $keyFunction() : "";
        if ($key == "" || strpos($key, "GET YOUR API KEY") !== false)
            add_check("Config", $service . " API key", "fail", "not configured");
        else
            add_check("Config", $service . " API key", "ok", "configured");
    }
    if (!empty($allowedIPs))
        add_check("Config", "IP whitelist", "ok", "enabled (" . count($allowedIPs) . " addresses); unlisted devices will get 403 Forbidden");
    else
        add_check("Config", "IP whitelist", "ok", "disabled; all devices allowed");
}

// --- Cache folder ---
if (isset($cacheRoot)) {
    $cachePath = (substr($cacheRoot, 0, 1) == "/") ? $cacheRoot : $clientDir . $cacheRoot;
    if (!is_dir($cachePath)) {
        add_check("Cache", "Folder exists", "warn", "not created yet; the proxy will try to create it on first use");
        if (!is_writable(dirname($cachePath)))
            add_check("Cache", "Folder creatable", "fail", "the web server user can't create it; create it manually and make it writable");
    } else {
        add_check("Cache", "Folder exists", "ok", "yes");
        $testFile = $cachePath . "/_status-write-test";
        if (@file_put_contents($testFile, time()) !== false && @unlink($testFile)) {
            add_check("Cache", "Writable", "ok", "yes");
        } else {
            add_check("Cache", "Writable", "fail", "the web server user can't write to the cache folder; check its owner and permissions");
        }
        $cacheFiles = glob($cachePath . "/*.json");
        $dataFiles = array_filter($cacheFiles, function($f) { return strpos(basename($f), "_status-") !== 0; });
        $unreadable = array_filter($dataFiles, function($f) { return !is_readable($f); });
        if (count($unreadable) > 0)
            add_check("Cache", "Readable", "fail", count($unreadable) . " cached files can't be read by the web server user");
        else
            add_check("Cache", "Readable", "ok", "yes");
        $newest = 0;
        foreach ($dataFiles as $f)
            $newest = max($newest, filemtime($f));
        add_check("Cache", "Entries", "ok", count($dataFiles) . " cached responses" . ($newest ? ", newest " . describe_age($newest) : ""));
    }
}

// --- .asp pages served by PHP ---
// Request each legacy endpoint from this same server, with no location so no upstream call is made.
// Pinning the host to 127.0.0.1 means a forged Host header can't point this at another server.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off") ? "https" : "http";
$port = isset($_SERVER['SERVER_PORT']) ? intval($_SERVER['SERVER_PORT']) : ($scheme == "https" ? 443 : 80);
$host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : "localhost";
if (!preg_match('/^[A-Za-z0-9.-]+$/', $host))
    $host = "localhost";
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), "/\\");
foreach ($clientFolders as $folder) {
    foreach ($endpoints as $endpoint) {
        $path = $basePath . "/widget/" . $folder . "/" . $endpoint;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $scheme . "://" . $host . ":" . $port . $path,
            CURLOPT_RESOLVE => array($host . ":" . $port . ":127.0.0.1"),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,  // loopback only, see above
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
        $body = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($err)
            add_check("ASP pages", $path, "warn", "couldn't test from the server itself: " . $err);
        else if (strpos($body, "<?php") !== false)
            add_check("ASP pages", $path, "fail", "served as plain text; PHP isn't handling .asp files");
        else if (strpos($body, "<adc_database") !== false)
            add_check("ASP pages", $path, "ok", "handled by PHP (HTTP " . $httpCode . ")");
        else if ($httpCode == 404)
            add_check("ASP pages", $path, "fail", "not found (HTTP 404); check the widget folder symlinks");
        else
            add_check("ASP pages", $path, "fail", "unexpected response (HTTP " . $httpCode . "); the PHP handler for .asp may be down");
    }
}

// --- Upstream APIs ---
// Read the outcome of the last real call, which the proxy records. Making a call here would use up quota.
foreach (array("accuweather" => "AccuWeather", "openweather" => "OpenWeather") as $service => $label) {
    $status = null;
    if (isset($cachePath) && file_exists($cachePath . "/_status-" . $service . ".json"))
        $status = json_decode(@file_get_contents($cachePath . "/_status-" . $service . ".json"), true);
    if (!$status) {
        add_check("Upstream", $label, "warn", "no calls recorded yet; fresh results may be coming from cache");
        continue;
    }
    $lastSuccess = isset($status['lastSuccess']) ? $status['lastSuccess'] : 0;
    $lastFailure = isset($status['lastFailure']) ? $status['lastFailure'] : 0;
    $failureText = $lastFailure ? "last error " . describe_age($lastFailure) . " (HTTP " . $status['lastFailureCode'] . "): " . $status['lastFailureMessage'] : "";
    if ($lastFailure > $lastSuccess) {
        $hint = "";
        if (in_array($status['lastFailureCode'], array(401, 403)))
            $hint = " The API key may be invalid or blocked.";
        else if (in_array($status['lastFailureCode'], array(429, 503)))
            $hint = " The daily request limit may have been reached; it usually resets within 24 hours.";
        add_check("Upstream", $label, "fail", "latest call failed: " . $failureText . "." . $hint);
    } else {
        add_check("Upstream", $label, "ok", "last successful call " . describe_age($lastSuccess) . ($failureText ? "; " . $failureText : ""));
    }
}

// --- Output ---
$overall = "ok";
foreach ($checks as $check) {
    if ($check['state'] == "fail") $overall = "fail";
    else if ($check['state'] == "warn" && $overall == "ok") $overall = "warn";
}
if ($overall == "fail")
    http_response_code(503);
header("Cache-Control: no-store");

if (isset($_GET['format']) && $_GET['format'] == "json") {
    header("Content-Type: application/json");
    echo json_encode(array("status" => $overall, "checked" => gmdate("c"), "checks" => $checks), JSON_PRETTY_PRINT);
    exit;
}

$labels = array("ok" => "OK", "warn" => "Warning", "fail" => "Problem");
$summaries = array(
    "ok" => "Everything looks healthy.",
    "warn" => "The proxy is up, but something needs attention.",
    "fail" => "The proxy has a problem. Please include this page when reporting an issue.",
);
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Proxy Status</title>
        <style>
            body { font-family: sans-serif; max-width: 860px; margin: 0 auto; padding: 16px; color: #222; background: #fff; }
            table { border-collapse: collapse; width: 100%; }
            th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
            td { overflow-wrap: anywhere; }
            .ok { color: #1a7f37; } .warn { color: #9a6700; } .fail { color: #cf222e; }
            .state { font-weight: bold; white-space: nowrap; }
            .group th { background: #f3f3f3; }
        </style>
    </head>
    <body>
        <h1>Accuweather XML Proxy Status</h1>
        <p class="<?php echo $overall; ?>"><strong><?php echo $labels[$overall]; ?>:</strong> <?php echo $summaries[$overall]; ?></p>
        <p>Checked <?php echo gmdate("Y-m-d H:i:s"); ?> UTC. Also available as <a href="?format=json">JSON</a>.</p>
        <table>
        <?php
        $lastGroup = "";
        foreach ($checks as $check) {
            if ($check['group'] != $lastGroup) {
                echo "<tr class=\"group\"><th colspan=\"3\">" . h($check['group']) . "</th></tr>\n";
                $lastGroup = $check['group'];
            }
            echo "<tr><td>" . h($check['name']) . "</td><td class=\"state " . $check['state'] . "\">" . $labels[$check['state']] . "</td><td>" . h($check['detail']) . "</td></tr>\n";
        }
        ?>
        </table>
        <p>Report issues at: <a href="https://github.com/webOSArchive/accuweatherxml-proxy/issues">https://github.com/webOSArchive/accuweatherxml-proxy/issues</a></p>
    </body>
</html>
