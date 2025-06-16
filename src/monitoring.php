<?php

function __log($msg) {
    date_default_timezone_set('Europe/Minsk');
    $logFile = __DIR__ . '/ast-mon.log';
    if (is_array($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    }
    $logMessage = date("Y-m-d H:i:s") . " " . getmypid() . "  " . $msg . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// проверка на Post запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    __log("Error: Only POST requests are allowed");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// подключение к мемхэш
try {
    $memcache = new Memcache();
    if (!$memcache->connect('localhost', 11211)) {
        throw new Exception('Memcache connection failed');
    }
} catch (Exception $e) {
    http_response_code(500);
    __log("Memcache error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Service unavailable']);
    exit;
}


$rawInput = file_get_contents('php://input');
__log("Raw input: " . $rawInput);

// декодер
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    __log("Invalid JSON: " . json_last_error_msg());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// сохранение данных в мемхэш
if (!empty($data)) {
    try {
        if (isset($data['channel_id']) && isset($data['input_id'])) {
            $prefix = "channel.{$data['channel_id']}.{$data['input_id']}";
            
            $metrics = [
                'sc_error', 'pes_error', 'pcr_error', 
                'cc_error', 'bitrate', 'packets', 'onair'
            ];
            
            foreach ($metrics as $metric) {
                if (isset($data[$metric])) {
                    $memcache->set("$prefix.$metric", $data[$metric], 0, 3600);
                }
            }
            
            $memcache->set("$prefix.timestamp", time(), 0, 3600);
            
            __log("Data saved for channel {$data['channel_id']} input {$data['input_id']}");
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Data processed',
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        __log("Processing error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Processing failed']);
    }
} else {
    http_response_code(400);
    __log("Empty data received");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}
?>