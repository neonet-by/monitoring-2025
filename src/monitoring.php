<?php
// заголовок
header('Content-Type: application/json');

// проверка на Post запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');

// декодер
$data = json_decode($rawInput, true);

// волидность для Json файла
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON input.']);
    exit;
}

// дропаем
http_response_code(200); 
echo json_encode(['status' => 'received and ignored for now']);

/* пример для Post запроса с Json через powershall
$headers = @{
    "Content-Type" = "application/json"
}

$body = '{"temperature": 72, "cpu": "Intel"}'

Invoke-WebRequest -Uri http://localhost:8000/monitoring.php -Method POST -Headers $headers -Body $body 
*/
