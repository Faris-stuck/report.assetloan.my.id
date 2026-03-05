<?php
/**
 * ============================================================
 * EMAIL CONFIGURATION - Komatsu Indonesia Borrowing System
 * ============================================================
 * 
 * This file contains:
 * - Gmail SMTP configuration
 * - Admin email for notifications
 * 
 * Used by:
 * - api/email/email-functions.php
 * - api/cron/send-reminder-h7.php (optional, can be refactored)
 * 
 * ============================================================
 */

// SMTP Gmail Configuration
// Only SMTP configuration here — NO hardcoded recipient emails
$smtpConfig = [
    'host'     => 'smtp.gmail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'openclaaw@gmail.com',           // ← SMTP sender (App Gmail)
    'password' => 'olok ffwy ojxx gyyj ',            // ← Gmail App Password (16 characters)
    'fromName' => 'Komatsu Indonesia - Borrowing System',
];

// ============================================================
// NO HARDCODED RECIPIENT EMAILS IN THIS FILE
// All recipient emails are retrieved DYNAMICALLY from the database:
//   - Admin  : SELECT email FROM users WHERE role = 'admin'
//   - Manager: SELECT email FROM users WHERE role = 'manager'
//   - User   : JOIN peminjaman → users
// See: api/email/email-functions.php → getAdminEmails(), etc.
// ============================================================
