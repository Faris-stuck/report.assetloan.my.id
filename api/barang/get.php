<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
require_once "../session-helper.php";

function computeBarangStatus(array $row): string
{
    $stokTersedia = (int) ($row['stok_tersedia'] ?? 0);
    $safetyStock = (int) ($row['safety_stock'] ?? 0);

    if ($stokTersedia === 0) {
        return 'Habis';
    }

    if ($stokTersedia <= $safetyStock) {
        return 'Menipis';
    }

    return 'Tersedia';
}

function bindStatementParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $references = [$types];
    foreach ($params as $index => $value) {
        $references[] = &$params[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $references);
}

function getDistinctConditions(mysqli $conn): array
{
    $options = [];
    $result = $conn->query("
        SELECT DISTINCT kondisi
        FROM barang
        WHERE kondisi IS NOT NULL AND kondisi <> ''
        ORDER BY kondisi ASC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = $row['kondisi'];
        }
    }

    return $options;
}

try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang', 'user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$filters = [
    'no' => trim((string) ($_GET['no'] ?? '')),
    'item_code' => trim((string) ($_GET['item_code'] ?? '')),
    'item_name' => trim((string) ($_GET['item_name'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'safety_stock' => trim((string) ($_GET['safety_stock'] ?? '')),
    'condition' => trim((string) ($_GET['condition'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$requestedPerPage = (int) ($_GET['per_page'] ?? 10);
$perPage = $requestedPerPage > 0 ? min($requestedPerPage, 100) : 10;
$paginateParam = strtolower(trim((string) ($_GET['paginate'] ?? '0')));
$paginate = !in_array($paginateParam, ['0', 'false', 'no'], true);

$allowedSortColumns = [
    'id' => 'id',
    'kode_barang' => 'kode_barang',
    'nama_barang' => 'nama_barang',
    'kategori' => 'kategori',
    'lokasi' => 'lokasi',
    'safety_stock' => 'safety_stock',
    'stok_tersedia' => 'stok_tersedia',
    'stok_total' => 'stok_total',
    'kondisi' => 'kondisi',
    'created_at' => 'created_at',
];

$sortField = trim((string) ($_GET['sort'] ?? 'id'));
$sortColumn = $allowedSortColumns[$sortField] ?? 'id';
$sortOrder = strtolower((string) ($_GET['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

$totalResult = $conn->query("SELECT COUNT(*) AS total_records FROM barang");
if (!$totalResult) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to count item data"]);
    exit;
}

$totalRecords = (int) ($totalResult->fetch_assoc()['total_records'] ?? 0);

$where = [];
$types = '';
$params = [];

if ($filters['item_code'] !== '') {
    $where[] = "kode_barang LIKE ?";
    $params[] = '%' . $filters['item_code'] . '%';
    $types .= 's';
}

if ($filters['item_name'] !== '') {
    $where[] = "nama_barang LIKE ?";
    $params[] = '%' . $filters['item_name'] . '%';
    $types .= 's';
}

if ($filters['category'] !== '') {
    $where[] = "COALESCE(kategori, '') LIKE ?";
    $params[] = '%' . $filters['category'] . '%';
    $types .= 's';
}

if ($filters['location'] !== '') {
    $where[] = "COALESCE(lokasi, '') LIKE ?";
    $params[] = '%' . $filters['location'] . '%';
    $types .= 's';
}

if ($filters['safety_stock'] !== '') {
    $where[] = "CAST(safety_stock AS CHAR) LIKE ?";
    $params[] = '%' . $filters['safety_stock'] . '%';
    $types .= 's';
}

if ($filters['condition'] !== '') {
    $where[] = "LOWER(kondisi) = LOWER(?)";
    $params[] = $filters['condition'];
    $types .= 's';
}

$normalizedStatus = strtolower($filters['status']);
if ($normalizedStatus !== '') {
    if ($normalizedStatus === 'habis') {
        $where[] = "stok_tersedia = 0";
    } elseif ($normalizedStatus === 'menipis') {
        $where[] = "stok_tersedia > 0 AND stok_tersedia <= safety_stock";
    } elseif ($normalizedStatus === 'tersedia') {
        $where[] = "stok_tersedia > safety_stock";
    }
}

$sql = "
    SELECT id, kode_barang, nama_barang, kategori, lokasi,
           stok_total, stok_tersedia, safety_stock, kondisi, keterangan, created_at
    FROM barang
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY {$sortColumn} {$sortOrder}, id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to prepare item query"]);
    exit;
}

bindStatementParams($stmt, $types, $params);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to execute item query"]);
    exit;
}

$result = $stmt->get_result();
if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to read item query result"]);
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['status'] = computeBarangStatus($row);
    $rows[] = $row;
}
$stmt->close();

$filteredRows = [];
foreach ($rows as $index => $row) {
    $row['row_no'] = $index + 1;

    if ($filters['no'] !== '' && strpos((string) $row['row_no'], $filters['no']) === false) {
        continue;
    }

    $filteredRows[] = $row;
}

$filteredRecords = count($filteredRows);

if ($paginate) {
    $totalPages = $filteredRecords > 0 ? (int) ceil($filteredRecords / $perPage) : 0;

    if ($totalPages === 0) {
        $page = 1;
    } elseif ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = $filteredRecords > 0 ? ($page - 1) * $perPage : 0;
    $pagedRows = $filteredRecords > 0 ? array_slice($filteredRows, $offset, $perPage) : [];
    $from = $filteredRecords > 0 ? $offset + 1 : 0;
    $to = $filteredRecords > 0 ? $offset + count($pagedRows) : 0;
} else {
    $page = 1;
    $totalPages = $filteredRecords > 0 ? 1 : 0;
    $pagedRows = $filteredRows;
    $from = $filteredRecords > 0 ? 1 : 0;
    $to = $filteredRecords > 0 ? count($pagedRows) : 0;
}

echo json_encode([
    "status" => true,
    "data" => array_values($pagedRows),
    "meta" => [
        "total_records" => $totalRecords,
        "filtered_records" => $filteredRecords,
        "page" => $page,
        "per_page" => $paginate ? $perPage : ($filteredRecords > 0 ? $filteredRecords : $perPage),
        "total_pages" => $totalPages,
        "from" => $from,
        "to" => $to,
        "sort" => $sortField,
        "order" => strtolower($sortOrder),
        "paginate" => $paginate,
    ],
    "filter_options" => [
        "condition" => getDistinctConditions($conn),
        "status" => ['Tersedia', 'Menipis', 'Habis'],
    ],
]);
