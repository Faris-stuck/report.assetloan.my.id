<?php
require_once "../session-helper.php";
require_once "../response-helper.php";

header('Content-Type: application/json');

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

$allowedRoles = ['admin', 'manager', 'pic_barang', 'user'];
if (!in_array(SessionValidator::getRole(), $allowedRoles, true)) {
    apiBusinessError('Access denied', 403);
}

apiBusinessError('Profile photo upload is temporarily disabled.', 503);
