<?php
/**
 * Modern Geolocation Data Generator for Gesior2012 Account Maker
 * 
 * This script downloads the latest free DB-IP Lite Country CSV database
 * and converts it into individual cached flag files (e.g. cache/flags/flag<octet>)
 * containing serialized arrays of long IP keys mapped to lowercase country codes.
 * 
 * Optimized for low memory (< 2MB) and high performance via streaming parser and on-the-fly serialization.
 * 
 * DB-IP Lite is licensed under Creative Commons Attribution 4.0 International License.
 * Attribution to DB-IP.com is required when using this data in your application.
 */

// Increase execution time limit since downloading and parsing can take a bit
set_time_limit(300);

$startTime = microtime(true);
$isCli = (php_sapi_name() === 'cli');
$eol = $isCli ? "\n" : "<br>\n";

echo "=== Modern Geolocation Data Generator ===" . $eol;

$csvFile = __DIR__ . '/dbip-country-ipv4.csv';
$downloadUrl = 'https://cdn.jsdelivr.net/gh/sapics/ip-location-db@main/dbip-country/dbip-country-ipv4.csv';

// 1. Download database if it doesn't exist
if (!file_exists($csvFile)) {
    echo "Downloading modern IP-to-country database from sapics/ip-location-db on GitHub..." . $eol;
    echo "URL: $downloadUrl" . $eol;
    
    // Create streaming context to handle potential redirects and set timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 60,
            'follow_location' => true,
            'header' => "User-Agent: Gesior2012-Flag-Generator/2.0\r\n"
        ]
    ]);

    $downloaded = false;

    // Try standard copy first
    try {
        if (@copy($downloadUrl, $csvFile, $context)) {
            $downloaded = true;
        }
    } catch (Exception $e) {
        // Fall through to curl
    }

    // Try cURL as fallback
    if (!$downloaded && function_exists('curl_init')) {
        echo "Native streams copy failed. Trying cURL fallback..." . $eol;
        $ch = curl_init($downloadUrl);
        $fp = fopen($csvFile, 'wb');
        if ($ch && $fp) {
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Gesior2012-Flag-Generator/2.0');
            if (curl_exec($ch)) {
                $downloaded = true;
            }
            curl_close($ch);
            fclose($fp);
        }
    }

    if (!$downloaded) {
        if (file_exists($csvFile)) {
            unlink($csvFile);
        }
        echo "Error: Failed to download the database automatically." . $eol;
        echo "Please download the country IPv4 CSV database manually from:" . $eol;
        echo "https://github.com/sapics/ip-location-db/blob/main/dbip-country/dbip-country-ipv4.csv" . $eol;
        echo "Save it in this directory as '$csvFile' and run the script again." . $eol;
        exit(1);
    }
    
    echo "Download completed successfully! Size: " . round(filesize($csvFile) / 1024 / 1024, 2) . " MB" . $eol;
} else {
    echo "Using existing local database: $csvFile" . $eol;
}

// 2. Ensure flags/ output directory exists
$outputDir = __DIR__ . '/flags';
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        echo "Error: Failed to create output directory '$outputDir'." . $eol;
        exit(1);
    }
}

if (!is_writable($outputDir)) {
    echo "Error: Output directory '$outputDir' is not writable." . $eol;
    exit(1);
}

// 3. Parse CSV and generate flag files on-the-fly
echo "Parsing CSV database and generating flag files..." . $eol;

$handle = fopen($csvFile, 'r');
if (!$handle) {
    echo "Error: Failed to open '$csvFile' for reading." . $eol;
    exit(1);
}

$lastIP = 0;
$lastCountry = '';
$currentOctet = -1;
$activeData = [];
$recordsProcessed = 0;
$filesGenerated = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 3) {
        continue;
    }
    
    $ipStart = trim($row[0]);
    // Handle both 3-column (ip_start, ip_end, country_code) and 4-column (ip_start, ip_end, continent, country_code) formats
    $countryCode = (count($row) >= 4) ? trim($row[3]) : trim($row[2]);
    
    $longVal = ip2long($ipStart);
    if ($longVal === false) {
        continue; // Skip invalid IPs or headers
    }
    
    // Ensure unsigned 32-bit integer representation
    $iIP = sprintf('%u', $longVal);
    $iC = strtolower($countryCode);
    
    $startOfIP = explode('.', $ipStart);
    $fileC = (int)$startOfIP[0];
    
    if ($fileC !== $currentOctet) {
        // If we transition to a new octet, save the active data for the previous octet
        if ($currentOctet !== -1) {
            file_put_contents("$outputDir/flag$currentOctet", serialize($activeData));
            $filesGenerated++;
        }
        
        $currentOctet = $fileC;
        $activeData = [];
        
        // Parity carry-over logic:
        // Set the start IP of the new octet to the last IP and country from the previous octet
        $activeData[$lastIP] = $lastCountry;
    }
    
    $activeData[$iIP] = $iC;
    $lastIP = $iIP;
    $lastCountry = $iC;
    
    $recordsProcessed++;
}

// Write the very last octet's data
if ($currentOctet !== -1) {
    file_put_contents("$outputDir/flag$currentOctet", serialize($activeData));
    $filesGenerated++;
}

fclose($handle);

$duration = microtime(true) - $startTime;
$peakMemory = memory_get_peak_usage(true) / 1024 / 1024;

echo "=== Generation Completed Successfully ===" . $eol;
echo "Processed records: " . number_format($recordsProcessed) . $eol;
echo "Generated flag files: $filesGenerated (saved in '$outputDir/')" . $eol;
echo "Execution time: " . round($duration, 4) . " seconds" . $eol;
echo "Peak memory usage: " . round($peakMemory, 2) . " MB" . $eol;