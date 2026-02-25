<?php
session_start();
require_once "../koneksi.php";
header("Content-Type: application/json");

/**
 * GET /api/admin/get-statuses.php
 * Purpose: Return all peminjaman statuses with their badge colors
 * 
 * This endpoint replaces hardcoded status definitions
 * Allows dynamic status management from database
 */

try {
    // Query distinct status values from peminjaman table
    $stmt = $conn->prepare("
        SELECT DISTINCT status FROM peminjaman 
        WHERE status IS NOT NULL
        ORDER BY status ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $statuses = [];
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        
        // Map status to badge color dynamically
        $badgeColor = "bg-secondary"; // default
        
        // Dynamic badge color mapping
        if ($status === "Menunggu Persetujuan") {
            $badgeColor = "bg-warning";
        } elseif ($status === "Disetujui") {
            $badgeColor = "bg-info";
        } elseif ($status === "Ditolak") {
            $badgeColor = "bg-danger";
        } elseif ($status === "Selesai" || $status === "Dikembalikan") {
            $badgeColor = "bg-success";
        } elseif ($status === "Sedang Dipinjam") {
            $badgeColor = "bg-primary";
        } elseif ($status === "Overdue" || $status === "Due Today") {
            $badgeColor = "bg-danger";
        } elseif (strpos($status, "Due") === 0) {
            $badgeColor = "bg-warning text-dark";
        } elseif ($status === "Sebagian Dikembalikan") {
            $badgeColor = "bg-warning text-dark";
        } elseif ($status === "Proses Return") {
            $badgeColor = "bg-warning";
        }
        
        $statuses[] = [
            "status" => $status,
            "badge_color" => $badgeColor,
            "label" => ucfirst(str_replace("_", " ", $status))
        ];
    }
    
    echo json_encode([
        "status" => true,
        "data" => $statuses,
        "total" => count($statuses)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>
