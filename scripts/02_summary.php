<?php
date_default_timezone_set('Asia/Taipei');
$caseDir = __DIR__ . '/../docs/case';
$docsDir = __DIR__ . '/../docs';

if (!file_exists($caseDir)) {
    die("Case directory not found: $caseDir\n");
}

$cases = [];
$files = glob($caseDir . '/*.json');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if ($data) {
        $cases[] = $data;
    }
}

echo "Total disaster cases: " . count($cases) . "\n\n";

$stats = [
    'by_county' => [],
    'by_disaster_type' => [],
    'by_status' => [],
    'by_date' => [],
    'casualties' => [
        'injured' => 0,
        'death' => 0,
        'trapped' => 0,
        'missing' => 0,
        'shelter' => 0
    ],
    'special_cases' => [
        'traffic_obstruction' => 0,
        'serious_disaster' => 0
    ]
];

// Function to parse Chinese date format
function parseChineseDate($dateStr) {
    // Pattern: 2025/7/6 下午 08:57:00
    if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s*(上午|下午)\s*(\d{1,2}):(\d{2}):(\d{2})/', $dateStr, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        $ampm = $matches[4];
        $hour = intval($matches[5]);
        $minute = $matches[6];
        $second = $matches[7];
        
        // Convert to 24-hour format
        if ($ampm === '下午' && $hour !== 12) {
            $hour += 12;
        } elseif ($ampm === '上午' && $hour === 12) {
            $hour = 0;
        }
        
        $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
        
        // Create datetime string in standard format
        $standardDate = "$year-$month-$day $hour:$minute:$second";
        return strtotime($standardDate);
    }
    return false;
}

foreach ($cases as $case) {
    $county = $case['COUNTY_N'] ?? 'Unknown';
    $stats['by_county'][$county] = ($stats['by_county'][$county] ?? 0) + 1;
    
    $disasterType = $case['DISASTER_MAIN_TYPE'] ?? 'Unknown';
    $stats['by_disaster_type'][$disasterType] = ($stats['by_disaster_type'][$disasterType] ?? 0) + 1;
    
    $status = $case['CASE_STATUS'] ?? 'Unknown';
    $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
    
    if (isset($case['CASE_DT'])) {
        $timestamp = parseChineseDate($case['CASE_DT']);
        if ($timestamp !== false) {
            $date = date('Y-m-d', $timestamp);
            $stats['by_date'][$date] = ($stats['by_date'][$date] ?? 0) + 1;
        }
    }
    
    $stats['casualties']['injured'] += $case['INJURED_NO'] ?? 0;
    $stats['casualties']['death'] += $case['DEATH_NO'] ?? 0;
    $stats['casualties']['trapped'] += $case['TRAPPED_NO'] ?? 0;
    $stats['casualties']['missing'] += $case['MISSING_NO'] ?? 0;
    $stats['casualties']['shelter'] += $case['SHELTER_NO'] ?? 0;
    
    if ($case['IS_TRAFFIC'] ?? false) {
        $stats['special_cases']['traffic_obstruction']++;
    }
    if ($case['IS_SERIOUS'] ?? false) {
        $stats['special_cases']['serious_disaster']++;
    }
}

arsort($stats['by_county']);
arsort($stats['by_disaster_type']);
arsort($stats['by_status']);
ksort($stats['by_date']);

echo "=== Disaster Summary Report ===\n";
echo "Generated at: " . date('Y-m-d H:i:s') . "\n\n";

echo "1. Cases by County:\n";
foreach ($stats['by_county'] as $county => $count) {
    echo sprintf("   %-20s: %4d cases\n", $county, $count);
}
echo "\n";

echo "2. Cases by Disaster Type:\n";
foreach ($stats['by_disaster_type'] as $type => $count) {
    echo sprintf("   %-30s: %4d cases\n", $type, $count);
}
echo "\n";

echo "3. Cases by Status:\n";
foreach ($stats['by_status'] as $status => $count) {
    echo sprintf("   %-20s: %4d cases\n", $status, $count);
}
echo "\n";

echo "4. Casualties Summary:\n";
echo sprintf("   Injured: %d\n", $stats['casualties']['injured']);
echo sprintf("   Deaths: %d\n", $stats['casualties']['death']);
echo sprintf("   Trapped: %d\n", $stats['casualties']['trapped']);
echo sprintf("   Missing: %d\n", $stats['casualties']['missing']);
echo sprintf("   In Shelter: %d\n", $stats['casualties']['shelter']);
echo "\n";

echo "5. Special Cases:\n";
echo sprintf("   Traffic Obstruction Cases: %d\n", $stats['special_cases']['traffic_obstruction']);
echo sprintf("   Serious Disaster Cases: %d\n", $stats['special_cases']['serious_disaster']);
echo "\n";

echo "6. Cases by Date:\n";
foreach ($stats['by_date'] as $date => $count) {
    echo sprintf("   %s: %4d cases\n", $date, $count);
}
echo "\n";

$datetime = date('Y-m-d H:i:s');
$total_cases = count($cases);
$total_injured = $stats['casualties']['injured'];
$total_death = $stats['casualties']['death'];
$total_trapped = $stats['casualties']['trapped'];
$total_missing = $stats['casualties']['missing'];
$total_shelter = $stats['casualties']['shelter'];
$traffic_cases = $stats['special_cases']['traffic_obstruction'];
$serious_cases = $stats['special_cases']['serious_disaster'];

$county_rows = '';
foreach ($stats['by_county'] as $county => $count) {
    $percentage = round(($count / $total_cases) * 100, 1);
    $county_rows .= "<tr><td>$county</td><td>$count</td><td>$percentage%</td></tr>\n";
}

$disaster_type_rows = '';
foreach ($stats['by_disaster_type'] as $type => $count) {
    $percentage = round(($count / $total_cases) * 100, 1);
    $disaster_type_rows .= "<tr><td>$type</td><td>$count</td><td>$percentage%</td></tr>\n";
}

$status_rows = '';
foreach ($stats['by_status'] as $status => $count) {
    $percentage = round(($count / $total_cases) * 100, 1);
    $status_rows .= "<tr><td>$status</td><td>$count</td><td>$percentage%</td></tr>\n";
}

$date_rows = '';
foreach ($stats['by_date'] as $date => $count) {
    $date_rows .= "<tr><td>$date</td><td>$count</td></tr>\n";
}

$htmlReport = <<<HTML
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>災情統計報告</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        
        h1 {
            color: #1a202c;
            text-align: center;
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            text-align: center;
            color: #2d3748;
            font-size: 1.2rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .timestamp {
            text-align: center;
            color: #4a5568;
            margin-bottom: 40px;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 8px 16px;
            background: #edf2f7;
            border-radius: 20px;
            display: inline-block;
            margin-left: 50%;
            transform: translateX(-50%);
            border: 1px solid #cbd5e0;
        }
        
        .section {
            margin-bottom: 40px;
            animation: fadeIn 0.5s ease-in;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .section h2 {
            color: #1a202c;
            border-bottom: 3px solid #3182ce;
            padding-bottom: 15px;
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }
        
        .section h2 i {
            color: #3182ce;
            font-size: 1.6rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            padding: 25px 20px;
            border-radius: 12px;
            text-align: center;
            color: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #2b6cb0;
            box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 600;
        }
        
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }
        
        /* Simple CSS Charts */
        .simple-chart {
            margin: 25px 0;
            padding: 25px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .chart-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 180px;
            margin: 20px 0;
            border-bottom: 2px solid #cbd5e0;
            padding: 0 15px;
        }
        
        .bar {
            width: 40px;
            background: linear-gradient(to top, #3182ce, #2c5282);
            margin: 0 6px;
            border-radius: 6px 6px 0 0;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(49, 130, 206, 0.3);
        }
        
        .bar:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            white-space: nowrap;
            color: #2d3748;
            font-weight: 600;
        }
        
        .bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            font-weight: 700;
            color: #1a202c;
            background: rgba(255,255,255,0.9);
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .pie-chart-container {
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 30px;
        }
        
        .pie-chart {
            width: 200px;
            height: 200px;
            position: relative;
        }
        
        .pie-legend {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        th {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
            font-weight: 500;
            color: #2d3748;
        }
        
        tr:hover {
            background-color: #f7fafc;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 18px;
            background-color: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 6px;
            border: 1px solid #cbd5e0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3182ce 0%, #2c5282 100%);
            transition: width 1s ease;
            position: relative;
            border-radius: 10px;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: loading 2s linear infinite;
        }
        
        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-banner {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
            border: 1px solid #c53030;
            font-size: 1rem;
            font-weight: 600;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .alert-banner i {
            font-size: 2rem;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-line"></i> 災情統計報告</h1>
        <p class="subtitle">即時災情監控與分析系統</p>
        <p class="timestamp"><i class="far fa-clock"></i> 報告生成時間: $datetime</p>
        
        <!-- Alert Banner for Critical Info -->
        <div class="alert-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>重要提醒：</strong>目前共有 $total_cases 筆災情案件，其中 {$stats['by_status']['處理中']} 筆正在處理中
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-tachometer-alt"></i> 整體統計</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-folder stat-icon"></i>
                    <div class="stat-number">$total_cases</div>
                    <div class="stat-label">總案件數</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-user-injured stat-icon"></i>
                    <div class="stat-number">$total_injured</div>
                    <div class="stat-label">受傷人數</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-heart-broken stat-icon"></i>
                    <div class="stat-number">$total_death</div>
                    <div class="stat-label">死亡人數</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-hands-helping stat-icon"></i>
                    <div class="stat-number">$total_trapped</div>
                    <div class="stat-label">受困人數</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                    <i class="fas fa-question-circle stat-icon"></i>
                    <div class="stat-number">$total_missing</div>
                    <div class="stat-label">失蹤人數</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                    <i class="fas fa-home stat-icon"></i>
                    <div class="stat-number">$total_shelter</div>
                    <div class="stat-label">收容人數</div>
                </div>
            </div>
        </div>
        
        <!-- Simple CSS Charts Section -->
        <div class="section">
            <h2><i class="fas fa-chart-pie"></i> 視覺化分析</h2>
            
            <!-- Top Counties Bar Chart -->
            <div class="simple-chart">
                <div class="chart-title">各縣市災情分布 (前5名)</div>
                <div class="bar-chart">
HTML;

// Calculate top 5 counties for bar chart
$top_counties = array_slice($stats['by_county'], 0, 5);
$max_county_value = max($top_counties);
foreach ($top_counties as $county => $count) {
    $height_percentage = ($count / $max_county_value) * 100;
    $percentage = round(($count / $total_cases) * 100, 1);
    $htmlReport .= <<<HTML
                    <div class="bar" style="height: {$height_percentage}%;">
                        <span class="bar-value">$count</span>
                        <span class="bar-label">$county</span>
                    </div>
HTML;
}

$htmlReport .= <<<HTML
                </div>
            </div>
            
            <!-- Daily Trend Simple Line Chart -->
            <div class="simple-chart">
                <div class="chart-title">每日災情趨勢</div>
                <div style="display: flex; justify-content: space-around; align-items: flex-end; height: 160px; border-bottom: 2px solid #cbd5e0; padding: 20px;">
HTML;

// Create simple line chart for daily trends
$max_date_value = max($stats['by_date']);
foreach ($stats['by_date'] as $date => $count) {
    $height_percentage = ($count / $max_date_value) * 100;
    $htmlReport .= <<<HTML
                    <div style="display: flex; flex-direction: column; align-items: center;">
                        <div style="width: 60px; height: {$height_percentage}%; background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%); border-radius: 6px; position: relative; box-shadow: 0 2px 8px rgba(49, 130, 206, 0.3);">
                            <span style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-weight: 700; font-size: 0.8rem; color: #1a202c; background: rgba(255,255,255,0.9); padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">$count</span>
                        </div>
                        <span style="margin-top: 12px; font-size: 0.8rem; color: #2d3748; font-weight: 600;">$date</span>
                    </div>
HTML;
}

$htmlReport .= <<<HTML
                </div>
            </div>
            
            <!-- Status Distribution -->
            <div class="simple-chart">
                <div class="chart-title">處理狀態分布</div>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center;">
HTML;

// Create status cards with better contrast colors
$status_colors = ['#3182ce', '#2c5282', '#7c3aed', '#e53e3e', '#059669'];
$i = 0;
foreach (array_slice($stats['by_status'], 0, 5) as $status => $count) {
    $percentage = round(($count / $total_cases) * 100, 1);
    $color = $status_colors[$i % count($status_colors)];
    $htmlReport .= <<<HTML
                    <div style="text-align: center; padding: 20px 15px; background: $color; color: white; border-radius: 12px; min-width: 140px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.1);">
                        <div style="font-size: 1.6rem; font-weight: 800;">$count</div>
                        <div style="font-size: 0.85rem; opacity: 0.95; font-weight: 600; margin: 6px 0;">$status</div>
                        <div style="font-size: 0.8rem; opacity: 0.9; font-weight: 500;">$percentage%</div>
                    </div>
HTML;
    $i++;
}

$htmlReport .= <<<HTML
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-map-marker-alt"></i> 各縣市案件統計</h2>
            <table>
                <thead>
                    <tr>
                        <th>縣市</th>
                        <th>案件數</th>
                        <th>百分比</th>
                        <th>視覺化</th>
                    </tr>
                </thead>
                <tbody>
HTML;

// Add progress bars to county rows
$county_rows_with_progress = '';
foreach ($stats['by_county'] as $county => $count) {
    $percentage = round(($count / $total_cases) * 100, 1);
    $county_rows_with_progress .= <<<HTML
                    <tr>
                        <td>$county</td>
                        <td>$count</td>
                        <td>$percentage%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: $percentage%"></div>
                            </div>
                        </td>
                    </tr>
HTML;
}

$htmlReport .= <<<HTML
                    $county_rows_with_progress
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-fire"></i> 災情類型統計</h2>
            <table>
                <thead>
                    <tr>
                        <th>災情類型</th>
                        <th>案件數</th>
                        <th>百分比</th>
                    </tr>
                </thead>
                <tbody>
                    $disaster_type_rows
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-tasks"></i> 處理狀態統計</h2>
            <table>
                <thead>
                    <tr>
                        <th>處理狀態</th>
                        <th>案件數</th>
                        <th>百分比</th>
                    </tr>
                </thead>
                <tbody>
                    $status_rows
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-exclamation-circle"></i> 特殊案件</h2>
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);">
                    <i class="fas fa-car-crash stat-icon"></i>
                    <div class="stat-number">$traffic_cases</div>
                    <div class="stat-label">交通障礙案件</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #ee5a24 0%, #f53b57 100%);">
                    <i class="fas fa-bolt stat-icon"></i>
                    <div class="stat-number">$serious_cases</div>
                    <div class="stat-label">重大災情案件</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple animations without heavy libraries
        document.addEventListener('DOMContentLoaded', () => {
            // Animate progress bars only
            const progressFills = document.querySelectorAll('.progress-fill');
            progressFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 100);
            });
            
            // Simple fade in for sections
            const sections = document.querySelectorAll('.section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.5s ease';
                    section.style.opacity = '1';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
HTML;

file_put_contents($docsDir . '/disaster_report.html', $htmlReport);
echo "HTML report saved to: " . $docsDir . "/disaster_report.html\n";

$jsonReport = [
    'generated_at' => $datetime,
    'total_cases' => $total_cases,
    'statistics' => $stats
];
file_put_contents($docsDir . '/disaster_summary.json', json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "JSON summary saved to: " . $docsDir . "/disaster_summary.json\n";