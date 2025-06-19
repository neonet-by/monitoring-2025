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
    exit;
}

// подключение к мемкеш
try {
    $memcache = new Memcache();
    if (!$memcache->connect('localhost', 11211)) {
        throw new Exception('Memcache connection failed');
    }
} catch (Exception $e) {
    http_response_code(500);
    __log("Memcache error: " . $e->getMessage());
    exit;
}

$rawInput = file_get_contents('php://input');
__log("Raw input: " . $rawInput);

// декодер
$data = json_decode($rawInput, true);

if (isset($data[0]['dvb'])) {
    foreach ($data as $entry) {
        $dvb = $entry['dvb'];
        $hostname = $entry['hostname'] ?? 'unknown';
        $timestamp = $entry['timestamp'] ?? time();
        $dvbId = $dvb['id'] ?? uniqid('dvb_');

        // Ключ сохраняем по ID или hostname
        $keyPrefix = "dvbconfig.{$dvbId}";

        $memcache->set("{$keyPrefix}.name", $dvb['name'], 0, 3600);
        $memcache->set("{$keyPrefix}.frequency", $dvb['frequency'], 0, 3600);
        $memcache->set("{$keyPrefix}.symbolrate", $dvb['symbolrate'], 0, 3600);
        $memcache->set("{$keyPrefix}.polarization", $dvb['polarization'], 0, 3600);
        $memcache->set("{$keyPrefix}.adapter", $dvb['adapter'], 0, 3600);
        $memcache->set("{$keyPrefix}.device", $dvb['device'], 0, 3600);
        $memcache->set("{$keyPrefix}.hostname", $hostname, 0, 3600);
        $memcache->set("{$keyPrefix}.timestamp", $timestamp, 0, 3600);

        __log("Saved DVB config for ID {$dvbId}");
    }

    http_response_code(200);
    exit;
}


if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    __log("Invalid JSON: " . json_last_error_msg());
    exit;
}

// сохранение данных в мемкеш
if (!empty($data)) {
    try {
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        if (isset($data['channel_id']) && isset($data['input_id'])) {
            $channelId = $data['channel_id'];
            $inputId = $data['input_id'];
            $prefix = "channel.{$channelId}.{$inputId}";
            $metrics = [
                'sc_error', 'pes_error', 'pcr_error',
                'cc_error', 'bitrate', 'packets', 'onair'
            ];

            $changes = [];

            foreach ($metrics as $metric) {
                if (isset($data[$metric])) {
                    $key = "$prefix.$metric";
                    $newValue = $data[$metric];
                    $oldValue = $memcache->get($key);

                    if ($oldValue !== $newValue) {
                        $changes[$metric] = ['old' => $oldValue, 'new' => $newValue];
                        $memcache->set($key, $newValue, 0, 3600);
                    }
                }
            }

            $timestampKey = "$prefix.timestamp";
            $timestamp = time();
            $memcache->set($timestampKey, $timestamp, 0, 3600);

            // последний инпут
            $lastInputKey = "channel{$channelId}.lastInput";
            $memcache->set($lastInputKey, $inputId, 0, 3600);

            if (!empty($changes)) {
                __log("Changes for channel {$channelId} input {$inputId}: " . json_encode($changes, JSON_UNESCAPED_UNICODE));
            } else {
                __log("No changes for channel {$channelId} input {$inputId}");
            }
        }

    } catch (Exception $e) {
        http_response_code(500);
        __log("Processing error: " . $e->getMessage());
    }
} else {
    http_response_code(400);
    __log("Empty data received");
}
?>
