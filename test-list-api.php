<?php
/**
 * Test the list.php API behavior
 * Shows what returns are in which status
 */

// Setup database connection using centralized configuration
require_once __DIR__ . '/config/database.php';

echo "=== Pengembalian Status Summary ===\n";
echo "All returns in database with their statuses:\n\n";

$result = $conn->query(
    "SELECT id, kode_pengembalian, status, diajukan_at 
     FROM pengembalian 
     ORDER BY id DESC 
     LIMIT 20"
);

if (!$result) {
    die("Query error: " . $conn->error);
}

$statuses = [];
while ($row = $result->fetch_assoc()) {
    $status = $row['status'];
    if (!isset($statuses[$status])) {
        $statuses[$status] = [];
    }
    $statuses[$status][] = [
        'id' => $row['id'],
        'kode' => $row['kode_pengembalian'],
        'time' => $row['diajukan_at']
    ];
}

foreach ($statuses as $status => $items) {
    echo "[{$status}] - " . count($items) . " record(s)\n";
    foreach ($items as $item) {
        echo "  - ID:" . $item['id'] . " | " . $item['kode'] . " | " . $item['time'] . "\n";
    }
    echo "\n";
}

echo "=== Testing API with status=Diajukan,Dicek ===\n";

// Now test the parsing logic
$status_input = 'Diajukan,Dicek';
$statuses_test = array_map('trim', explode(',', $status_input));
$statuses_test = array_filter(array_unique($statuses_test));
echo "Parsed statuses: " . implode(', ', $statuses_test) . "\n";
echo "Placeholders: " . implode(',', array_fill(0, count($statuses_test), '?')) . "\n\n";

// Try the actual query
$placeholders = implode(',', array_fill(0, count($statuses_test), '?'));
$sql = "
    SELECT k.id, k.kode_pengembalian, k.status, k.diajukan_at
    FROM pengembalian k
    WHERE k.status IN ($placeholders)
    ORDER BY k.diajukan_at DESC
";

echo "SQL: $sql\n\n";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare error: " . $conn->error);
}

$types = str_repeat('s', count($statuses_test));
$refs = [];
foreach ($statuses_test as $k => $v) {
    $refs[$k] = $statuses_test[$k];
}
$bindParams = [$types];
foreach ($refs as $k => $v) {
    $bindParams[] = &$refs[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);

if (!$stmt->execute()) {
    die("Execute error: " . $stmt->error);
}

$queryResult = $stmt->get_result();
echo "Query returned " . $queryResult->num_rows . " row(s):\n";
while ($row = $queryResult->fetch_assoc()) {
    echo "  - " . $row['kode_pengembalian'] . " (" . $row['status'] . ")\n";
}

// Connection will be closed automatically
