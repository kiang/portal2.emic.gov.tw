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

// Arrays to store features
$geoJsonFeatures = []; // minimal properties for GeoJSON
$kmlFeatures = [];     // full properties for KML only

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
            // Minimal properties for GeoJSON
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

            // Full properties for KML details
            $kmlFeature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($coords[0]),
                        floatval($coords[1])
                    ]
                ],
                'properties' => [
                    'CASE_ID' => $caseData['CASE_ID'] ?? '',
                    'CASE_DT' => $caseData['CASE_DT'] ?? '',
                    'DISASTER_MAIN_TYPE' => $caseData['DISASTER_MAIN_TYPE'] ?? '',
                    'DISASTER_SUB_TYPE' => $caseData['DISASTER_SUB_TYPE'] ?? '',
                    'CASE_STATUS' => $caseData['CASE_STATUS'] ?? '',
                    'COUNTY_N' => $caseData['COUNTY_N'] ?? '',
                    'TOWN_N' => $caseData['TOWN_N'] ?? '',
                    'CASE_LOC' => $caseData['CASE_LOC'] ?? '',
                    'CASE_DESCRIPTION' => $caseData['CASE_DESCRIPTION'] ?? '',
                    'CASE_TYPE' => $caseData['CASE_TYPE'] ?? '',
                    'PERSON_ID' => $caseData['PERSON_ID'] ?? '',
                    'INJURED_NO' => $caseData['INJURED_NO'] ?? 0,
                    'DEATH_NO' => $caseData['DEATH_NO'] ?? 0,
                    'TRAPPED_NO' => $caseData['TRAPPED_NO'] ?? 0,
                    'MISSING_NO' => $caseData['MISSING_NO'] ?? 0,
                    'SHELTER_NO' => $caseData['SHELTER_NO'] ?? 0,
                    'IS_TRAFFIC' => $caseData['IS_TRAFFIC'] ?? false,
                    'IS_SERIOUS' => $caseData['IS_SERIOUS'] ?? false
                ]
            ];
            $kmlFeatures[] = $kmlFeature;
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

// Build KML from full-detail features
foreach ($kmlFeatures as $feature) {
    $props = $feature['properties'];
    $coords = $feature['geometry']['coordinates'];

    $placemark = $dom->createElement('Placemark');

    // Use 災情類別 as Placemark name/title
    $name = $dom->createElement('name', htmlspecialchars($props['DISASTER_MAIN_TYPE'], ENT_XML1, 'UTF-8'));
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
    $description = '';
    $description .= '<b>案件編號：</b>' . htmlspecialchars($props['CASE_ID'], ENT_XML1, 'UTF-8') . '<br/>';
    $description .= '<b>災情類別：</b>' . htmlspecialchars($props['DISASTER_MAIN_TYPE'], ENT_XML1, 'UTF-8');
    if (!empty($props['DISASTER_SUB_TYPE'])) {
        $description .= '（' . htmlspecialchars($props['DISASTER_SUB_TYPE'], ENT_XML1, 'UTF-8') . '）';
    }
    $description .= '<br/>';
    if (!empty($props['CASE_DT'])) {
        $description .= '<b>發生時間：</b>' . htmlspecialchars($props['CASE_DT'], ENT_XML1, 'UTF-8') . '<br/>';
    }
    if (!empty($props['COUNTY_N']) || !empty($props['TOWN_N'])) {
        $region = trim((string)($props['COUNTY_N'] ?? '')) . (empty($props['TOWN_N']) ? '' : ' ' . trim((string)$props['TOWN_N']));
        if (!empty($region)) {
            $description .= '<b>行政區：</b>' . htmlspecialchars($region, ENT_XML1, 'UTF-8') . '<br/>';
        }
    }
    if (!empty($props['CASE_LOC'])) {
        $description .= '<b>發生地點：</b>' . htmlspecialchars($props['CASE_LOC'], ENT_XML1, 'UTF-8') . '<br/>';
    }
    if (!empty($props['CASE_DESCRIPTION'])) {
        $descText = htmlspecialchars($props['CASE_DESCRIPTION'], ENT_XML1, 'UTF-8');
        // Convert newlines to <br/>
        $descText = str_replace(["\r\n", "\n", "\r"], '<br/>', $descText);
        $description .= '<b>災情描述：</b>' . $descText . '<br/>';
    }
    if (!empty($props['CASE_TYPE'])) {
        $description .= '<b>通報類別：</b>' . htmlspecialchars($props['CASE_TYPE'], ENT_XML1, 'UTF-8') . '<br/>';
    }
    if (!empty($props['PERSON_ID'])) {
        $description .= '<b>上傳單位：</b>' . htmlspecialchars($props['PERSON_ID'], ENT_XML1, 'UTF-8') . '<br/>';
    }
    $description .= '<b>處理狀態：</b>' . htmlspecialchars($props['CASE_STATUS'], ENT_XML1, 'UTF-8') . '<br/>';
    $description .= '<b>交通障礙：</b>' . (!empty($props['IS_TRAFFIC']) ? '是' : '否') . '<br/>';
    $description .= '<b>重大災情：</b>' . (!empty($props['IS_SERIOUS']) ? '是' : '否') . '<br/>';
    // People impact
    $description .= '<b>人員受傷：</b>' . intval($props['INJURED_NO'] ?? 0) . '，';
    $description .= '<b>死亡：</b>' . intval($props['DEATH_NO'] ?? 0) . '，';
    $description .= '<b>受困：</b>' . intval($props['TRAPPED_NO'] ?? 0) . '，';
    $description .= '<b>失蹤：</b>' . intval($props['MISSING_NO'] ?? 0) . '，';
    $description .= '<b>收容：</b>' . intval($props['SHELTER_NO'] ?? 0);

    $descElement = $dom->createElement('description');
    $descElement->appendChild($dom->createCDATASection("\n    " . $description . "\n  "));
    $placemark->appendChild($descElement);

    // Add ExtendedData with richer case details
    $extendedData = $dom->createElement('ExtendedData');

    $addData = function ($name, $display, $value) use ($dom, $extendedData) {
        if ($value === null || $value === '') {
            return;
        }
        $data = $dom->createElement('Data');
        $data->setAttribute('name', $name);
        $data->appendChild($dom->createElement('displayName', $display));
        $data->appendChild($dom->createElement('value', htmlspecialchars((string)$value, ENT_XML1, 'UTF-8')));
        $extendedData->appendChild($data);
    };

    $addData('case_id', '案件編號', $props['CASE_ID'] ?? '');
    $addData('case_dt', '發生時間', $props['CASE_DT'] ?? '');
    $addData('disaster_type', '災情類別', $props['DISASTER_MAIN_TYPE'] ?? '');
    $addData('disaster_sub_type', '災情類別細項', $props['DISASTER_SUB_TYPE'] ?? '');
    $addData('county', '縣市', $props['COUNTY_N'] ?? '');
    $addData('town', '鄉鎮市區', $props['TOWN_N'] ?? '');
    $addData('location', '發生地點', $props['CASE_LOC'] ?? '');
    $addData('case_description', '災情描述', $props['CASE_DESCRIPTION'] ?? '');
    $addData('case_type', '通報類別', $props['CASE_TYPE'] ?? '');
    $addData('person_id', '上傳單位', $props['PERSON_ID'] ?? '');
    $addData('status', '處理狀態', $props['CASE_STATUS'] ?? '');
    $addData('is_traffic', '交通障礙', (!empty($props['IS_TRAFFIC']) ? '是' : '否'));
    $addData('is_serious', '重大災情', (!empty($props['IS_SERIOUS']) ? '是' : '否'));
    $addData('injured_no', '人員受傷', intval($props['INJURED_NO'] ?? 0));
    $addData('death_no', '人員死亡', intval($props['DEATH_NO'] ?? 0));
    $addData('trapped_no', '人員受困', intval($props['TRAPPED_NO'] ?? 0));
    $addData('missing_no', '人員失蹤', intval($props['MISSING_NO'] ?? 0));
    $addData('shelter_no', '人員收容', intval($props['SHELTER_NO'] ?? 0));

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
echo "Created KML with " . count($kmlFeatures) . " features\n";

echo "Processing completed\n";
