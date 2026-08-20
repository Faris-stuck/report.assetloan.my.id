<?php
/**
 * Dynamic Base URL for PHP
 * ═══════════════════════════════════════════════════════════════
 * Detects whether the project runs in a subfolder (localhost)
 * or at the document root (VPS), and builds $base_url accordingly.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/base_url.php';  // adjust relative path
 *   header("Location: " . $base_url . "/user/dashboard.html");
 */

// Detect protocol (http vs https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

// CLI commands (e.g. artisan config:cache) do not provide HTTP_HOST.
// Prefer the production/app URL when available, and only use the request
// host during an actual HTTP request.
$requestHost = $_SERVER['HTTP_HOST'] ?? null;
$configuredUrl = getenv('APP_URL') ?: null;

if ($requestHost !== null && $requestHost !== '') {
    $base_url = $protocol . '://' . $requestHost;
} elseif ($configuredUrl !== null && $configuredUrl !== '') {
    $base_url = rtrim($configuredUrl, '/');
} else {
    $base_url = $protocol . '://report.assetloan.my.id';
}

// Detect subfolder from SCRIPT_NAME
// e.g. /PROJECT/api/auth/login.php → folder = "PROJECT"
// e.g. /api/auth/login.php          → folder = "api" (known app folder, skip)
$segments = explode("/", trim($_SERVER['SCRIPT_NAME'], "/"));
$knownFolders = ['admin', 'user', 'manager', 'pic-barang', 'api', 'assets', 'config'];

if (!empty($segments[0]) && !in_array($segments[0], $knownFolders)) {
    // First segment is a project subfolder (e.g. "PROJECT")
    $base_url .= "/" . $segments[0];
}
// else: running at root, $base_url stays as protocol://host
