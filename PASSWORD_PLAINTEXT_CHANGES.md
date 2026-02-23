# Sistem Password Plaintext - Dokumentasi Perubahan
## 📋 Ringkasan Lengkap (Development/Testing Only)

---

## ✅ Perubahan yang Dilakukan

### 1. File yang Dimodifikasi

#### **api/auth/login.php** (Line 35)
**Sebelum:**
```php
if (!password_verify($password, $stored)) {
    http_response_code(401);
    echo json_encode(["error" => "Login gagal"]);
    exit;
}
```

**Sesudah:**
```php
// Verify password (plaintext comparison for development)
if ($password !== $stored) {
    http_response_code(401);
    echo json_encode(["error" => "Login gagal"]);
    exit;
}
```
**Perubahan:** `password_verify()` → Perbandingan string plaintext `!==`

---

#### **api/auth/register.php** (Line 47)
**Sebelum:**
```php
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
if (!$hashed_password) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Gagal memproses password"]);
    exit;
}
...
$insert_stmt->bind_param("ssss", $nama, $nrp, $email, $hashed_password);
```

**Sesudah:**
```php
// Store password as plaintext (development only)
$plain_password = $password;

// Insert user baru dengan role default 'user'
$insert_stmt = $conn->prepare("INSERT INTO users (nama, nrp, email, password, role) VALUES (?, ?, ?, ?, 'user')");
...
$insert_stmt->bind_param("ssss", $nama, $nrp, $email, $plain_password);
```
**Perubahan:** `password_hash()` dihapus → password langsung disimpan sebagai plaintext

---

#### **api/auth/forgot-password.php** (Line 42)
**Sebelum:**
```php
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
if (!$hashed_password) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Gagal memproses password"]);
    exit;
}
...
$update_stmt->bind_param("ss", $hashed_password, $email);
```

**Sesudah:**
```php
// Store new password as plaintext (development only)
$plain_password = $new_password;

// Update password
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
...
$update_stmt->bind_param("ss", $plain_password, $email);
```
**Perubahan:** `password_hash()` dihapus → password disimpan plaintext

---

#### **api/user/create.php** (Line 70)
**Sebelum:**
```php
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
if (!$hashed_password) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Gagal memproses password"]);
    exit;
}
...
$insert_stmt->bind_param("sssss", $nama, $nrp, $email, $hashed_password, $role);
```

**Sesudah:**
```php
// Store password as plaintext (development only)
$plain_password = $password;

// Insert user baru (terhubung dengan tabel peminjaman via user_id)
$insert_stmt = $conn->prepare("INSERT INTO users (nama, nrp, email, password, role) VALUES (?, ?, ?, ?, ?)");
...
$insert_stmt->bind_param("sssss", $nama, $nrp, $email, $plain_password, $role);
```
**Perubahan:** `password_hash()` dihapus → plaintext storage

---

#### **api/user/change_password.php** (Line 51)
**Sebelum:**
```php
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed, $id);
```

**Sesudah:**
```php
// Update password as plaintext (development only)
$plain_password = $password;
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $plain_password, $id);
```
**Perubahan:** `password_hash()` dihapus → plaintext update

---

### 2. File Baru Dibuat

#### **api/reset_password_plaintext.php**
Fungsi: Reset semua password di database ke default `123456`

```php
// Update all users dengan default plaintext password
$sql = "UPDATE users SET password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $default_password);
$stmt->execute();
```

**URL untuk menjalankan:**
- Browser: `http://localhost/PROJECT/api/reset_password_plaintext.php`
- CLI: `php api/reset_password_plaintext.php`

---

## 🔐 Status Database Setelah Perubahan

| ID | Nama | Email | Password | Status |
|--|--|--|--|--|
| 1 | Admin Sistem | admin@komatsu.co.id | **123456** | ✅ Plaintext |
| 1004 | Muhammad Faris | azmiariffaris@gmail.com | **123456** | ✅ Plaintext |
| 1005 | Manager | manager@komatsu.co.id | **123456** | ✅ Plaintext |
| 1006 | PIC Barang | picbarang@komatsu.co.id | **123456** | ✅ Plaintext |

**Catatan:** Semua password sebelumnya yang ter-hash sudah dikonversi ke `123456`

---

## 🧪 Testing & Verifikasi

### Login Test
```bash
Email: admin@komatsu.co.id
Password: 123456
Status: ✅ LOGIN BERHASIL
```

### Password Tidak Lagi Aman
SEBELUM (hashed):
```
$2y$10$ksdhfksdhfksdhf...
```

SESUDAH (plaintext):
```
123456
```

---

## ⚠️ PENTING - SECURITY DISCLAIMER

### ⛔ HANYA UNTUK DEVELOPMENT/TESTING
- ❌ Jangan gunakan di production
- ❌ Jangan gunakan dengan real user data
- ❌ Tidak aman untuk live website

### ✅ Safe for:
- Development environment
- Testing & QA
- Learning purposes
- Internal demo

---

## 📝 Ringkasan Fungsi Yang Dihapus

| Fungsi | Dihapus? | File Terpengaruh |
|--|--|--|
| `password_hash()` | ✅ Ya | 4 file |
| `password_verify()` | ✅ Ya | 1 file |
| BCrypt hashing | ✅ Ya | All auth files |

---

## 🚀 Cara Menggunakan

### 1. Default Password untuk semua user: `123456`

### 2. Login Normal
```javascript
// fetch POST ke login.php
fetch(BASE_URL + '/api/auth/login.php', {
    method: 'POST',
    body: new FormData({
        email: 'admin@komatsu.co.id',
        password: '123456'  // plaintext
    })
})
```

### 3. Register User Baru
Password akan disimpan plaintext:
```javascript
fetch(BASE_URL + '/api/auth/register.php', {
    method: 'POST',
    body: new FormData({
        nama: 'User Baru',
        nrp: '12345',
        email: 'user@example.com',
        password: 'mypassword'  // akan disimpan plaintext
    })
})
```

---

## 📊 File Verifikasi

Jalankan command untuk verifikasi tidak ada hash function tersisa:

```bash
grep -rn "password_hash\|password_verify" api/

# Output: (empty) = ✅ SUCCESS
```

---

## 🔄 Jika Ingin Kembali ke Hashing

Untuk mengembalikan ke BCrypt hashing, revert perubahan di:
1. `api/auth/login.php` - gunakan `password_verify()`
2. `api/auth/register.php` - gunakan `password_hash()`
3. `api/auth/forgot-password.php` - gunakan `password_hash()`
4. `api/user/create.php` - gunakan `password_hash()`
5. `api/user/change_password.php` - gunakan `password_hash()`

Selain itu gunakan:
```php
password_hash($password, PASSWORD_DEFAULT)
password_verify($input, $stored)
```

---

## ✅ Checklist Verifikasi

- [x] Semua `password_hash()` dihapus
- [x] Semua `password_verify()` dihapus
- [x] Login menggunakan perbandingan plaintext `===`
- [x] Register menyimpan plaintext
- [x] Forgot password menyimpan plaintext
- [x] User create/change password plaintext
- [x] Database sudah diupdate
- [x] Test login = SUCCESS ✅

---

**Status:** ✅ **SELESAI - Sistem Password Plaintext Active**
**Environment:** Development/Testing Only
**Date:** 2026-02-23
