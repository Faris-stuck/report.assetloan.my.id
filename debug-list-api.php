<?php
/**
 * Debug endpoint to test the list.php API directly
 */
header('Content-Type: application/json');

// Simulate the same request the frontend makes
echo "Testing: /PROJECT/api/pengembalian/list.php?status=Diajukan,Dicek\n\n";

// Make the request
$url = 'http://localhost/PROJECT/api/pengembalian/list.php?status=Diajukan,Dicek';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=test');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n\n";
echo "Response:\n";
echo $response;
