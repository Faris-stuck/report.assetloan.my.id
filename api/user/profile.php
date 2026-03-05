<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("
    SELECT nama, nrp, email, COALESCE(avatar, '') AS avatar
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(["error" => "User not found"]);
}
