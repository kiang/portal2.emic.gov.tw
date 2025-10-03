<?php
$csvUrl = 'https://portal2.emic.gov.tw/Pub/DIM2/OpenData/Disaster.csv';
$docsDir = __DIR__ . '/../docs';
$caseDir = $docsDir . '/case';

// Create directories if they don't exist
if (!file_exists($docsDir)) {
    mkdir($docsDir, 0755, true);
}
if (!file_exists($caseDir)) {
    mkdir($caseDir, 0755, true);
}

// Download CSV file
$csvContent = file_get_contents($csvUrl);
if ($csvContent === false) {
    die("Failed to download CSV file\n");
}

// Parse TSV (tab-separated values)
$lines = explode("\n", $csvContent);

// Skip the first line (title)
array_shift($lines);

// Define field positions based on the data structure
$fieldPositions = [
    'CASE_ID' => 0,          // 災情案件編號
    'CASE_DT' => 1,          // 發生時間
    'COUNTY_N' => 2,         // 縣市名稱
    'TOWN_N' => 3,           // 鄉鎮市區名稱
    'CASE_LOC' => 4,         // 發生地點
    'GEOMETRY_TYPE' => 5,    // 幾何形狀
    'COORDINATE' => 6,       // 座標值
    'DISASTER_MAIN_TYPE' => 7,  // 災情類別_大項
    'DISASTER_SUB_TYPE' => 8,   // 災情類別_細項
    'CASE_DESCRIPTION' => 9,    // 災情描述
    'CASE_STATUS' => 10,        // 處理狀態
    'CASE_TYPE' => 11,          // 通報類別
    'PERSON_ID' => 12,          // 上傳單位名稱
    'INJURED_NO' => 13,         // 人員受傷
    'DEATH_NO' => 14,           // 人員死亡
    'TRAPPED_NO' => 15,         // 人員受困
    'MISSING_NO' => 16,         // 人員失蹤
    'SHELTER_NO' => 17,         // 人員收容
    'IS_TRAFFIC' => 18,         // 交通障礙案
    'IS_SERIOUS' => 19          // 重大災情案件
];

// Array to store features for GeoJSON
$geoJsonFeatures = [];

// Process each row
foreach ($lines as $line) {
    if (trim($line) === '') {
        continue;
    }
    
    $row = str_getcsv($line, "\t");
    if (count($row) < 20) {
        continue;
    }
    
    // Build case data
    $caseData = [];
    foreach ($fieldPositions as $jsonKey => $position) {
        if (isset($row[$position])) {
            $value = trim($row[$position]);
            // Convert numeric strings to numbers for appropriate fields
            if (in_array($jsonKey, ['INJURED_NO', 'DEATH_NO', 'TRAPPED_NO', 'MISSING_NO', 'SHELTER_NO'])) {
                $caseData[$jsonKey] = is_numeric($value) ? intval($value) : 0;
            } elseif (in_array($jsonKey, ['IS_TRAFFIC', 'IS_SERIOUS'])) {
                $caseData[$jsonKey] = $value === 'Y' || $value === '是' || $value === 'true' || $value === '1';
            } else {
                $caseData[$jsonKey] = $value;
            }
        }
    }
    
    if (empty($caseData['CASE_ID'])) {
        continue;
    }
    
    $caseFile = $caseDir . '/' . $caseData['CASE_ID'] . '.json';
    
    // Check if file exists and compare for changes
    $changes = [];
    if (file_exists($caseFile)) {
        $existingData = json_decode(file_get_contents($caseFile), true);
        
        // Compare fields
        foreach ($caseData as $key => $newValue) {
            if (!isset($existingData[$key]) || $existingData[$key] !== $newValue) {
                $oldValue = isset($existingData[$key]) ? $existingData[$key] : null;
                $changes[] = [
                    'field' => $key,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Preserve existing logs
        if (isset($existingData['logs'])) {
            $caseData['logs'] = $existingData['logs'];
        } else {
            $caseData['logs'] = [];
        }
        
        // If there are changes, add them to the logs
        if (!empty($changes)) {
            $caseData['logs'] = array_merge($caseData['logs'], $changes);
            echo "Updated case {$caseData['CASE_ID']} with " . count($changes) . " changes\n";
        }
    } else {
        // New case
        $caseData['logs'] = [];
        echo "Created new case {$caseData['CASE_ID']}\n";
    }
    
    // Save to JSON file
    file_put_contents($caseFile, json_encode($caseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Add to GeoJSON if coordinates are valid
    if (!empty($caseData['COORDINATE']) && $caseData['COORDINATE'] !== ',') {
        $coords = explode(',', $caseData['COORDINATE']);
        if (count($coords) === 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($coords[0]), // longitude
                        floatval($coords[1])  // latitude
                    ]
                ],
                'properties' => [
                    'CASE_ID' => $caseData['CASE_ID'],
                    'CASE_DT' => $caseData['CASE_DT'],
                    'DISASTER_MAIN_TYPE' => $caseData['DISASTER_MAIN_TYPE'],
                    'CASE_STATUS' => $caseData['CASE_STATUS'],
                    'IS_TRAFFIC' => $caseData['IS_TRAFFIC'],
                    'IS_SERIOUS' => $caseData['IS_SERIOUS']
                ]
            ];
            $geoJsonFeatures[] = $feature;
        }
    }
}

// Create GeoJSON
$geoJson = [
    'type' => 'FeatureCollection',
    'features' => $geoJsonFeatures
];

// Save GeoJSON file
file_put_contents($docsDir . '/cases.json', json_encode($geoJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Created GeoJSON with " . count($geoJsonFeatures) . " features\n";

// Create KML using DOMDocument for proper XML formatting
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$kml = $dom->createElementNS('http://www.opengis.net/kml/2.2', 'kml');
$dom->appendChild($kml);

$document = $dom->createElement('Document');
$kml->appendChild($document);

$docName = $dom->createElement('name', '災情案例');
$document->appendChild($docName);

$docDesc = $dom->createElement('description', '災情通報資料');
$document->appendChild($docDesc);

foreach ($geoJsonFeatures as $feature) {
    $props = $feature['properties'];
    $coords = $feature['geometry']['coordinates'];

    $placemark = $dom->createElement('Placemark');

    $name = $dom->createElement('name', htmlspecialchars($props['CASE_ID'], ENT_XML1, 'UTF-8'));
    $placemark->appendChild($name);

    $visibility = $dom->createElement('visibility', '1');
    $placemark->appendChild($visibility);

    // Add timestamp if available
    if (!empty($props['CASE_DT'])) {
        $timeStamp = $dom->createElement('TimeStamp');
        $when = $dom->createElement('when', htmlspecialchars($props['CASE_DT'], ENT_XML1, 'UTF-8'));
        $timeStamp->appendChild($when);
        $placemark->appendChild($timeStamp);
    }

    // Create description with HTML formatting
    $description = '<b>災情類別：</b>' . htmlspecialchars($props['DISASTER_MAIN_TYPE'], ENT_XML1, 'UTF-8') . '<br/>';
    $description .= '<b>處理狀態：</b>' . htmlspecialchars($props['CASE_STATUS'], ENT_XML1, 'UTF-8') . '<br/>';
    $description .= '<b>交通障礙：</b>' . ($props['IS_TRAFFIC'] ? '是' : '否') . '<br/>';
    $description .= '<b>重大災情：</b>' . ($props['IS_SERIOUS'] ? '是' : '否');

    $descElement = $dom->createElement('description');
    $descElement->appendChild($dom->createCDATASection("\n    " . $description . "\n  "));
    $placemark->appendChild($descElement);

    // Add ExtendedData
    $extendedData = $dom->createElement('ExtendedData');

    $dataType = $dom->createElement('Data');
    $dataType->setAttribute('name', 'disaster_type');
    $displayName = $dom->createElement('displayName', '災情類別');
    $value = $dom->createElement('value', htmlspecialchars($props['DISASTER_MAIN_TYPE'], ENT_XML1, 'UTF-8'));
    $dataType->appendChild($displayName);
    $dataType->appendChild($value);
    $extendedData->appendChild($dataType);

    $dataStatus = $dom->createElement('Data');
    $dataStatus->setAttribute('name', 'status');
    $displayNameStatus = $dom->createElement('displayName', '處理狀態');
    $valueStatus = $dom->createElement('value', htmlspecialchars($props['CASE_STATUS'], ENT_XML1, 'UTF-8'));
    $dataStatus->appendChild($displayNameStatus);
    $dataStatus->appendChild($valueStatus);
    $extendedData->appendChild($dataStatus);

    $placemark->appendChild($extendedData);

    // Add Point coordinates
    $point = $dom->createElement('Point');
    $coordinates = $dom->createElement('coordinates', $coords[0] . ',' . $coords[1] . ',0');
    $point->appendChild($coordinates);
    $placemark->appendChild($point);

    $document->appendChild($placemark);
}

// Save KML file
$dom->save($docsDir . '/cases.kml');
echo "Created KML with " . count($geoJsonFeatures) . " features\n";

echo "Processing completed\n";