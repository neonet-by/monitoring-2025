<?php

function __log($msg) {
  date_default_timezone_set('Europe/Minsk');
  error_log(date("Y-m-d H:i:s")." ".getmypid()."  ".$msg."\n",3,"/tmp/ast-mon.log");
}


// проверка на Post запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    __log(['error' => 'Only POST requests are allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');

// декодер
$data = json_decode($rawInput, true);

// волидность для Json файла
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    __log(json_encode(['error' => 'Invalid JSON input.']));
    exit;
}

__log($data);

?>
