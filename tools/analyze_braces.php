<?php
$path = __DIR__ . '/../app/Http/Controllers/PublicReportController.php';
$lines = file($path);
$bal = 0;
foreach ($lines as $n => $l) {
    $oc = substr_count($l, '{');
    $cc = substr_count($l, '}');
    $bal += $oc - $cc;
    if ($bal < 0) {
        echo "Negative balance at line " . ($n + 1) . "\n";
    }
}
echo "Final balance: $bal\n";
for ($i = 0; $i < count($lines); $i++) {
    echo ($i+1) . ': ' . rtrim($lines[$i]) . "\n";
}
