<?php
/**
 * API: Admin Role Management
 * Purpose: Get role list with user counts, update user roles, create new roles
 * Endpoint: /api/admin/roles.php
 * Methods: GET (list roles, users), POST (update role or create new role)
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin']);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // Get all roles from roles table with user counts
            $stmt = $conn->prepare("
                SELECT r.id, r.role_name, r.deskripsi, r.is_protected, r.badge_color, r.created_at,
                       COALESCE(u.jumlah_user, 0) AS jumlah_user
                FROM roles r
                LEFT JOIN (
                    SELECT role, COUNT(*) AS jumlah_user
                    FROM users
                    GROUP BY role
                ) u ON r.role_name = u.role
                ORDER BY r.id ASC
            ");
            $stmt->execute();
            $result = $stmt->get_result();

            $roles = [];
            while ($row = $result->fetch_assoc()) {
                $roles[] = [
                    'id' => (int)$row['id'],
                    'role' => $row['role_name'],
                    'deskripsi' => $row['deskripsi'] ?? '-',
                    'is_protected' => (bool)$row['is_protected'],
                    'badge_color' => $row['badge_color'] ?? 'secondary',
                    'jumlah_user' => (int)$row['jumlah_user'],
                    'created_at' => $row['created_at']
                ];
            }

            echo json_encode(['status' => true, 'data' => $roles]);

        } elseif ($action === 'users') {
            $role = $_GET['role'] ?? null;

            if ($role) {
                $stmt = $conn->prepare("
                    SELECT id, nama, nrp, email, role, created_at
                    FROM users
                    WHERE role = ?
                    ORDER BY nama ASC
                ");
                $stmt->bind_param('s', $role);
            } else {
                $stmt = $conn->prepare("
                    SELECT id, nama, nrp, email, role, created_at
                    FROM users
                    ORDER BY role, nama ASC
                ");
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }

            echo json_encode(['status' => true, 'data' => $users]);
        }

    } elseif ($method === 'POST') {
        $postAction = $_POST['action'] ?? 'update_role';

        if ($postAction === 'create_role') {
            // --- Create new role ---
            $roleName = trim($_POST['role_name'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if (empty($roleName)) {
                throw new Exception("Role name is required");
            }

            // Sanitize role name: lowercase, replace spaces with underscores, alphanumeric only
            $roleName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $roleName));
            $roleName = preg_replace('/_+/', '_', trim($roleName, '_'));

            if (strlen($roleName) < 2) {
                throw new Exception("Role name must be at least 2 characters");
            }

            if (strlen($roleName) > 50) {
                throw new Exception("Role name must be at most 50 characters");
            }

            // Check if role already exists in roles table
            $stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->bind_param('s', $roleName);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Role '$roleName' already exists");
            }

            // Get current ENUM values from users table
            $result = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
            $row = $result->fetch_assoc();
            $enumType = $row['Type']; // e.g. enum('admin','manager','user','pic_barang')

            // Parse existing enum values
            preg_match("/^enum\((.+)\)$/", $enumType, $matches);
            $existingValues = str_getcsv($matches[1], ',', "'");

            $badgeColor = trim($_POST['badge_color'] ?? 'secondary');
            // Validate badge color
            $validColors = ['primary','secondary','success','danger','warning','info','dark','light'];
            if (!in_array($badgeColor, $validColors)) {
                $badgeColor = 'secondary';
            }

            if (in_array($roleName, $existingValues)) {
                // Already in ENUM but not in roles table, just insert into roles table
                $stmt = $conn->prepare("INSERT INTO roles (role_name, deskripsi, badge_color) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $roleName, $deskripsi, $badgeColor);
                $stmt->execute();

                echo json_encode([
                    'status' => true,
                    'message' => "Role '$roleName' added successfully"
                ]);
                exit;
            }

            // Add new value to ENUM
            $existingValues[] = $roleName;
            $enumStr = "'" . implode("','", $existingValues) . "'";
            $alterSQL = "ALTER TABLE users MODIFY COLUMN role ENUM($enumStr) NOT NULL";

            if (!$conn->query($alterSQL)) {
                throw new Exception("Failed to add role to database: " . $conn->error);
            }

            // Insert into roles metadata table
            $stmt = $conn->prepare("INSERT INTO roles (role_name, deskripsi, badge_color) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $roleName, $deskripsi, $badgeColor);
            $stmt->execute();

            echo json_encode([
                'status' => true,
                'message' => "Role '$roleName' created successfully"
            ]);

        } elseif ($postAction === 'delete_role') {
            // --- Delete a role ---
            $roleName = trim($_POST['role_name'] ?? '');

            if (empty($roleName)) {
                throw new Exception("Role name is required");
            }

            // Check if role is protected (from database)
            $stmt = $conn->prepare("SELECT is_protected FROM roles WHERE role_name = ?");
            $stmt->bind_param('s', $roleName);
            $stmt->execute();
            $roleData = $stmt->get_result()->fetch_assoc();

            if (!$roleData) {
                throw new Exception("Role '$roleName' not found");
            }

            if ((bool)$roleData['is_protected']) {
                throw new Exception("Role '$roleName' is a default role and cannot be deleted");
            }

            // Check if any users still have this role
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = ?");
            $stmt->bind_param('s', $roleName);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['cnt'];

            if ($count > 0) {
                throw new Exception("Cannot delete role '$roleName' because $count users still use this role");
            }

            // Remove from ENUM
            $result = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
            $row = $result->fetch_assoc();
            preg_match("/^enum\((.+)\)$/", $row['Type'], $matches);
            $existingValues = str_getcsv($matches[1], ',', "'");
            $newValues = array_filter($existingValues, function($v) use ($roleName) {
                return $v !== $roleName;
            });

            if (count($newValues) === 0) {
                throw new Exception("Cannot delete all roles");
            }

            $enumStr = "'" . implode("','", $newValues) . "'";
            $alterSQL = "ALTER TABLE users MODIFY COLUMN role ENUM($enumStr) NOT NULL";
            if (!$conn->query($alterSQL)) {
                throw new Exception("Failed to remove role from database: " . $conn->error);
            }

            // Remove from roles table
            $stmt = $conn->prepare("DELETE FROM roles WHERE role_name = ?");
            $stmt->bind_param('s', $roleName);
            $stmt->execute();

            echo json_encode([
                'status' => true,
                'message' => "Role '$roleName' deleted successfully"
            ]);

        } else {
            // --- Default: Update user role ---
            $user_id = $_POST['user_id'] ?? null;
            $new_role = $_POST['role'] ?? null;

            if (!$user_id || !$new_role) {
                throw new Exception("user_id and role are required");
            }

            // Get valid roles from roles table
            $validResult = $conn->query("SELECT role_name FROM roles");
            $validRoles = [];
            while ($vr = $validResult->fetch_assoc()) {
                $validRoles[] = $vr['role_name'];
            }

            if (!in_array($new_role, $validRoles)) {
                throw new Exception("Invalid role: $new_role");
            }

            // Prevent admin from changing their own role
            $currentUserId = $_SESSION['user_id'] ?? null;
            if ($user_id == $currentUserId) {
                throw new Exception("You cannot change your own role");
            }

            // Check if user exists
            $stmt = $conn->prepare("SELECT id, nama, role FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                throw new Exception("User not found");
            }

            $oldRole = $user['role'];

            // Update role
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $new_role, $user_id);
            $stmt->execute();

            echo json_encode([
                'status' => true,
                'message' => "Role for '{$user['nama']}' changed from '$oldRole' to '$new_role'"
            ]);
        }

    } else {
        throw new Exception("Method not allowed");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
