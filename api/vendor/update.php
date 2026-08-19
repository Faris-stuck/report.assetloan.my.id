<?php
session_start();
require_once "../koneksi.php";
header("Content-Type: application/json");

// Validasi session
require_once "../session-helper.php";

try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "PHP Error: " . $errstr
    ]);
    exit;
});

try {
    $action = $_POST['action'] ?? 'update';
    
    if ($action === 'add') {
        // ADD NEW VENDOR
        $nama_vendor = $_POST['nama_vendor'] ?? null;
        $alamat = $_POST['alamat'] ?? null;
        $kontak = $_POST['kontak'] ?? null;
        
        if (!$nama_vendor) {
            echo json_encode([
                "status" => false,
                "message" => "Vendor name is required"
            ]);
            exit;
        }
        
        // Check for duplicate vendor name
        $cek = $conn->prepare("SELECT id FROM vendor WHERE nama_vendor = ?");
        $cek->bind_param("s", $nama_vendor);
        $cek->execute();
        $cek->store_result();
        
        if ($cek->num_rows > 0) {
            echo json_encode([
                "status" => false,
                "message" => "A vendor with this name already exists"
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO vendor (nama_vendor, alamat, kontak) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_vendor, $alamat, $kontak);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Vendor successfully added",
                "id" => $conn->insert_id
            ]);
        } else {
            throw new Exception("Failed to add vendor: " . $stmt->error);
        }
        
    } else if ($action === 'update') {
        // UPDATE VENDOR
        $id = $_POST['id'] ?? null;
        $nama_vendor = $_POST['nama_vendor'] ?? null;
        $alamat = $_POST['alamat'] ?? null;
        $kontak = $_POST['kontak'] ?? null;
        
        if (!$id || !$nama_vendor) {
            echo json_encode([
                "status" => false,
                "message" => "ID and vendor name are required"
            ]);
            exit;
        }
        
        // Check if vendor exists
        $cek = $conn->prepare("SELECT id FROM vendor WHERE id = ?");
        $cek->bind_param("i", $id);
        $cek->execute();
        $cek->store_result();
        
        if ($cek->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Vendor not found"
            ]);
            exit;
        }
        
        // Check for duplicate vendor name (excluding current vendor)
        $cek2 = $conn->prepare("SELECT id FROM vendor WHERE nama_vendor = ? AND id != ?");
        $cek2->bind_param("si", $nama_vendor, $id);
        $cek2->execute();
        $cek2->store_result();
        
        if ($cek2->num_rows > 0) {
            echo json_encode([
                "status" => false,
                "message" => "A vendor with this name already exists"
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE vendor SET nama_vendor = ?, alamat = ?, kontak = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nama_vendor, $alamat, $kontak, $id);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Vendor successfully updated"
            ]);
        } else {
            throw new Exception("Failed to update vendor: " . $stmt->error);
        }
        
    } else if ($action === 'delete') {
        // DELETE VENDOR
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            echo json_encode([
                "status" => false,
                "message" => "Vendor ID is required"
            ]);
            exit;
        }
        
        // Check if vendor exists
        $cek = $conn->prepare("SELECT id FROM vendor WHERE id = ?");
        $cek->bind_param("i", $id);
        $cek->execute();
        $cek->store_result();
        
        if ($cek->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Vendor not found"
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM vendor WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Vendor successfully deleted"
            ]);
        } else {
            throw new Exception("Failed to delete vendor: " . $stmt->error);
        }
        
    } else if ($action === 'get_all') {
        // GET ALL VENDORS (for table loading)
        $result = $conn->query("SELECT id, nama_vendor, alamat, kontak FROM vendor ORDER BY nama_vendor ASC");
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "data" => $data
        ]);
        
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Invalid action"
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
