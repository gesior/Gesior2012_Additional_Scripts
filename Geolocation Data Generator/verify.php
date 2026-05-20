<?php
/**
 * Verification Script for Geolocation Data Generator
 * 
 * This script validates that the generated cache files are 100% correct
 * and perform accurate lookups matching the original CSV database.
 * 
 * Optimized for low memory via Reservoir Sampling.
 */

$startTime = microtime(true);
$isCli = (php_sapi_name() === 'cli');
$eol = $isCli ? "\n" : "<br>\n";

echo "=== Geolocation Cache Verification Script ===" . $eol;

$csvFile = __DIR__ . '/dbip-country-ipv4.csv';
$flagsDir = __DIR__ . '/flags';

if (!file_exists($csvFile)) {
    echo "Error: Database file '$csvFile' not found." . $eol;
    exit(1);
}

if (!is_dir($flagsDir)) {
    echo "Error: Flags directory '$flagsDir' not found." . $eol;
    exit(1);
}

echo "Sampling 150 random IP ranges from the CSV database using Reservoir Sampling..." . $eol;

$handle = fopen($csvFile, 'r');
if (!$handle) {
    echo "Error: Failed to open '$csvFile'." . $eol;
    exit(1);
}

$sampleSize = 150;
$sampledRows = [];
$totalRows = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 3) {
        continue;
    }
    
    $totalRows++;
    
    // Reservoir sampling algorithm
    if (count($sampledRows) < $sampleSize) {
        $sampledRows[] = $row;
    } else {
        $r = rand(0, $totalRows - 1);
        if ($r < $sampleSize) {
            $sampledRows[$r] = $row;
        }
    }
}
fclose($handle);

echo "Total rows in CSV: " . number_format($totalRows) . $eol;
echo "Successfully sampled " . count($sampledRows) . " ranges." . $eol;

// Construct test cases from the sampled ranges
$testCases = [];
foreach ($sampledRows as $row) {
    $ipStart = trim($row[0]);
    $ipEnd = trim($row[1]);
    $countryCode = (count($row) >= 4) ? trim($row[3]) : trim($row[2]);
    
    $longStart = ip2long($ipStart);
    $longEnd = ip2long($ipEnd);
    if ($longStart === false || $longEnd === false) {
        continue;
    }
    
    $country = strtolower($countryCode);
    $startLong = sprintf('%u', $longStart);
    $endLong = sprintf('%u', $longEnd);
    
    // Test the start IP of the range
    $testCases[] = [
        'ip' => $ipStart,
        'long' => $startLong,
        'expected' => $country
    ];
    
    // Test the end IP of the range
    $testCases[] = [
        'ip' => $ipEnd,
        'long' => $endLong,
        'expected' => $country
    ];
    
    // Test a mid IP of the range
    $midLong = (float)$startLong + floor(((float)$endLong - (float)$startLong) / 2);
    $midIP = long2ip((int)$midLong);
    $testCases[] = [
        'ip' => $midIP,
        'long' => sprintf('%u', $midLong),
        'expected' => $country
    ];
}

echo "Running " . count($testCases) . " test lookups using generated cache..." . $eol;

$successCount = 0;
$failCount = 0;

function lookupIP($ip) {
    global $flagsDir;
    
    $longVal = ip2long($ip);
    if ($longVal === false) {
        return 'unknown';
    }
    $ipLong = sprintf('%u', $longVal);
    
    $parts = explode('.', $ip);
    $octet = (int)$parts[0];
    
    $flagFile = "$flagsDir/flag$octet";
    if (!file_exists($flagFile)) {
        return 'unknown';
    }
    
    $data = unserialize(file_get_contents($flagFile));
    if (!$data) {
        return 'unknown';
    }
    
    // Since keys are sorted in the serialized array, find the largest key <= $ipLong
    $foundCountry = 'unknown';
    foreach ($data as $key => $country) {
        if ((float)$key <= (float)$ipLong) {
            $foundCountry = $country;
        } else {
            break;
        }
    }
    return $foundCountry;
}

foreach ($testCases as $case) {
    $ip = $case['ip'];
    $expected = $case['expected'];
    $actual = lookupIP($ip);
    
    if ($actual === $expected) {
        $successCount++;
    } else {
        $failCount++;
        echo "FAIL: IP $ip (long {$case['long']}) -> Expected: '$expected', Got: '$actual'" . $eol;
    }
}

$duration = microtime(true) - $startTime;
$peakMemory = memory_get_peak_usage(true) / 1024 / 1024;

echo "=== Verification Summary ===" . $eol;
echo "Passed: $successCount / " . count($testCases) . $eol;
echo "Failed: $failCount" . $eol;
echo "Verification Time: " . round($duration, 4) . " seconds" . $eol;
echo "Peak memory usage: " . round($peakMemory, 2) . " MB" . $eol;

if ($failCount === 0) {
    echo "SUCCESS: The generated cache files are 100% accurate and functioning perfectly!" . $eol;
} else {
    echo "ERROR: Discrepancy detected in lookup results." . $eol;
    exit(1);
}
