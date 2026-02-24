<?php
/**
 * ============================================================
 * KONFIGURASI EMAIL - Sistem Peminjaman Komatsu Indonesia
 * ============================================================
 * 
 * File ini berisi:
 * - Konfigurasi SMTP Gmail
 * - Email admin untuk notifikasi
 * 
 * Digunakan oleh:
 * - api/email/email-functions.php
 * - api/cron/send-reminder-h7.php (opsional, bisa di-refactor)
 * 
 * ============================================================
 */

// SMTP Gmail Configuration
// Hanya konfigurasi SMTP di sini — TIDAK ADA hardcode email penerima
$smtpConfig = [
    'host'     => 'smtp.gmail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'openclaaw@gmail.com',           // ← SMTP sender (App Gmail)
    'password' => 'olok ffwy ojxx gyyj ',            // ← App Password Gmail (16 karakter)
    'fromName' => 'Komatsu Indonesia - Sistem Peminjaman',
];

// ============================================================
// TIDAK ADA HARDCODE EMAIL PENERIMA DI FILE INI
// Semua email penerima diambil DINAMIS dari database:
//   - Admin  : SELECT email FROM users WHERE role = 'admin'
//   - Manager: SELECT email FROM users WHERE role = 'manager'
//   - User   : JOIN peminjaman → users
// Lihat: api/email/email-functions.php → getAdminEmails(), dll.
// ============================================================
