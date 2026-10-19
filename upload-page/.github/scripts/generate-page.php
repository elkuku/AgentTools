<?php

declare(strict_types=1);

$root    = dirname(__DIR__, 2);
$docsDir = $root.'/docs';

// --- Coverage data from Clover XML ---
$coveragePercent   = null;
$coveredStatements = 0;
$totalStatements   = 0;

$cloverFile = $docsDir.'/coverage.xml';
if (file_exists($cloverFile)) {
    $xml = simplexml_load_file($cloverFile);
    if ($xml) {
        $metrics           = $xml->project->metrics;
        $totalStatements   = (int) $metrics['statements'];
        $coveredStatements = (int) $metrics['coveredstatements'];
        if ($totalStatements > 0) {
            $coveragePercent = round($coveredStatements / $totalStatements * 100, 1);
        }
    }
}

$coverageBadgeClass = 'bg-danger';
if (null !== $coveragePercent) {
    $coverageBadgeClass = match (true) {
        $coveragePercent >= 80 => 'bg-success',
        $coveragePercent >= 60 => 'bg-warning text-dark',
        default                => 'bg-danger',
    };
}
$coverageDisplay = null !== $coveragePercent ? $coveragePercent.'%' : 'N/A';

// --- Composer metadata ---
/** @var array{require: array<string, string>, extra: array{symfony: array{require: string}}} $composer */
$composer        = json_decode((string) file_get_contents($root.'/composer.json'), true);
$phpVersion      = $composer['require']['php'] ?? 'N/A';
$sfVersion       = $composer['extra']['symfony']['require'] ?? 'N/A';
$doctrineVersion = $composer['require']['doctrine/orm'] ?? 'N/A';

// --- Source file counts ---
$srcFiles  = count(glob($root.'/src/**/*.php') ?: []);
$testFiles = count(glob($root.'/tests/**/*.php') ?: []);

// --- Build date ---
$buildDate = date('Y-m-d H:i').' UTC';

// --- Render ---
ob_start();
include __DIR__.'/template.php';
$html = (string) ob_get_clean();

file_put_contents($docsDir.'/index.html', $html);
echo "Generated docs/index.html\n";
