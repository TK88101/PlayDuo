<?php

declare(strict_types=1);

// PHPUnit 11 にはカバレッジの下限チェックが無いので、clover.xml の行カバレッジで判定する
// 使い方: php tools/coverage_check.php build/clover.xml 80
if ($argc !== 3) {
    fwrite(STDERR, "usage: php tools/coverage_check.php <clover.xml> <min-percent>\n");
    exit(2);
}

[, $cloverPath, $minPercent] = $argv;
if (!is_file($cloverPath)) {
    fwrite(STDERR, "clover ファイルがありません: {$cloverPath}\n");
    exit(2);
}

$xml = simplexml_load_file($cloverPath);
$metrics = $xml === false ? null : $xml->project->metrics;
if ($metrics === null) {
    fwrite(STDERR, "clover の形式が読めません: {$cloverPath}\n");
    exit(2);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 0.0 : $covered / $statements * 100;

printf("Line coverage: %.2f%% (%d/%d), minimum %s%%\n", $percent, $covered, $statements, $minPercent);
exit($percent >= (float) $minPercent ? 0 : 1);
