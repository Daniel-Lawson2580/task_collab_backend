<?php
$ch = curl_init('https://task-collab-backend-wgx2.onrender.com/auth/login.php?api=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'danny@gmail.com', 'password' => 'Nutjob56']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Origin: https://daniel-lawson2580.github.io'
]);
$response = curl_exec($ch);
echo "RESPONSE:\n" . $response . "\n";
