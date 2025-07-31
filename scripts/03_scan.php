<?php
/**
 * Scan git history to track case count changes over time
 * Outputs to docs/count.csv with format: commit_time, count, commit_hash
 */

$repoPath = dirname(__DIR__);
$casesFile = 'docs/cases.json';
$outputFile = $repoPath . '/docs/count.csv';

// Change to repo directory
chdir($repoPath);

// Get all commits that modified cases.json
$cmd = "git log --pretty=format:'%H|%ai' --follow -- " . escapeshellarg($casesFile);
$output = shell_exec($cmd);

if (empty($output)) {
    echo "No commits found for {$casesFile}\n";
    exit(1);
}

$commits = explode("\n", trim($output));
$results = [];

echo "Found " . count($commits) . " commits that modified {$casesFile}\n";
echo "Processing commits...\n";

$processed = 0;
$errors = 0;

foreach ($commits as $commitLine) {
    list($hash, $timestamp) = explode('|', $commitLine);
    
    // Checkout the cases.json file from this commit
    $checkoutCmd = "git show {$hash}:{$casesFile} 2>/dev/null";
    $jsonContent = shell_exec($checkoutCmd);
    
    if (empty($jsonContent)) {
        echo "  - Skipping commit {$hash} (file not found or empty)\n";
        $errors++;
        continue;
    }
    
    // Parse JSON and count cases
    $data = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  - Error parsing JSON for commit {$hash}: " . json_last_error_msg() . "\n";
        $errors++;
        continue;
    }
    
    // Count total cases
    $count = 0;
    if (isset($data['features']) && is_array($data['features'])) {
        $count = count($data['features']);
    } elseif (is_array($data)) {
        // If the data is directly an array of cases
        $count = count($data);
    } else {
        echo "  - Warning: Unexpected data structure for commit {$hash}\n";
    }
    
    // Convert timestamp to more readable format
    $commitTime = date('Y-m-d H:i:s', strtotime($timestamp));
    
    $results[] = [
        'commit_time' => $commitTime,
        'count' => $count,
        'commit_hash' => $hash
    ];
    
    $processed++;
    
    // Show progress every 10 commits
    if ($processed % 10 == 0) {
        echo "  - Processed {$processed} commits...\n";
    }
}

echo "\nProcessing complete:\n";
echo "  - Total commits: " . count($commits) . "\n";
echo "  - Successfully processed: {$processed}\n";
echo "  - Errors: {$errors}\n";

// Sort by commit time (oldest first)
usort($results, function($a, $b) {
    return strcmp($a['commit_time'], $b['commit_time']);
});

// Write to CSV
$fp = fopen($outputFile, 'w');

if (!$fp) {
    echo "Error: Cannot open output file {$outputFile}\n";
    exit(1);
}

// Write header
fputcsv($fp, ['commit_time', 'count', 'commit_hash']);

// Write data
foreach ($results as $row) {
    fputcsv($fp, [
        $row['commit_time'],
        $row['count'],
        $row['commit_hash']
    ]);
}

fclose($fp);

echo "\nResults written to: {$outputFile}\n";

// Show summary statistics
if (!empty($results)) {
    $firstCommit = $results[0];
    $lastCommit = $results[count($results) - 1];
    
    echo "\nSummary:\n";
    echo "  - First commit: {$firstCommit['commit_time']} (Count: {$firstCommit['count']})\n";
    echo "  - Latest commit: {$lastCommit['commit_time']} (Count: {$lastCommit['count']})\n";
    echo "  - Total change: " . ($lastCommit['count'] - $firstCommit['count']) . " cases\n";
    
    // Find the commit with maximum cases
    $maxCases = 0;
    $maxCommit = null;
    foreach ($results as $row) {
        if ($row['count'] > $maxCases) {
            $maxCases = $row['count'];
            $maxCommit = $row;
        }
    }
    
    if ($maxCommit) {
        echo "  - Peak cases: {$maxCases} on {$maxCommit['commit_time']}\n";
    }
}

echo "\nDone!\n";