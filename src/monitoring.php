<?php

$jsonFile = __DIR__ . '/monitoring_data.json';

function __log($msg) {
    date_default_timezone_set('Europe/Minsk');
    $logFile = __DIR__ . '/ast-mon.log';
    if (is_array($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    }
    $logMessage = date("Y-m-d H:i:s") . " " . getmypid() . "  " . $msg . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function save_json_if_changed($path, $newData) {
    $newJson = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_exists($path)) {
        $existingJson = file_get_contents($path);
        if (json_decode($existingJson, true) === $newData) {
            __log("No changes detected in $path — skipping write");
            return true;
        }
    }

    if (file_put_contents($path, $newJson) !== false) {
        __log("File $path updated");
        return true;
    } else {
        __log("ERROR writing to $path");
        return false;
    }
}

function get_from_cache_or_file($memcache, $key, $fileData) {
    $value = $memcache->get($key);
    if ($value === false && isset($fileData[$key])) {
        $value = $fileData[$key];
        $memcache->set($key, $value, 0, 3600);
        __log("Loaded $key from file to memcache");
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    __log("Error: Only POST requests are allowed");
    exit;
}

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

$data = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    __log("Invalid JSON: " . json_last_error_msg());
    exit;
}

// загрузка данных при старте
$fileData = [];
if (file_exists($jsonFile)) {
    $fileData = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($fileData)) {
        $fileData = [];
    }
}

if (isset($data[0]['dvb'])) {
    $changesDetected = false;
    $newFileData = $fileData;

    foreach ($data as $entry) {
        $dvb = $entry['dvb'];
        $hostname = $entry['hostname'] ?? 'unknown';
        $timestamp = $entry['timestamp'] ?? time();
        $dvbId = $dvb['id'] ?? uniqid('dvb_');

        $keyPrefix = "dvbconfig.{$dvbId}";

        // проверка измененийй
        $fields = [
            'name' => $dvb['name'],
            'frequency' => $dvb['frequency'],
            'symbolrate' => $dvb['symbolrate'],
            'polarization' => $dvb['polarization'],
            'adapter' => $dvb['adapter'],
            'device' => $dvb['device'],
            'hostname' => $hostname,
            'timestamp' => $timestamp
        ];

        foreach ($fields as $field => $newValue) {
            $key = "{$keyPrefix}.{$field}";
            $oldValue = get_from_cache_or_file($memcache, $key, $fileData);

            if ($oldValue !== $newValue) {
                $memcache->set($key, $newValue, 0, 3600);
                $changesDetected = true;
                __log("Updated {$key} in memcache");
            }
        }

        // обновление
        $newFileData["dvb_config_{$dvbId}"] = [
            'id' => $dvbId,
            'name' => $dvb['name'] ?? 'unknown',
            'type' => 'dvb_config'
        ];
    }

    if ($changesDetected) {
        save_json_if_changed($jsonFile, $newFileData);
    }

    http_response_code(200);
    exit;
}

if (isset($data[0]['dvb_id'])) {
    $changesDetected = false;
    $newFileData = $fileData;

    foreach ($data as $entry) {
        $dvbId = $entry['dvb_id'];
        $keyPrefix = "dvbmetrics.{$dvbId}";

        $fields = ['count', 'unc', 'signal', 'ber', 'timestamp', 'status', 'snr'];
        foreach ($fields as $field) {
            if (isset($entry[$field])) {
                $key = "{$keyPrefix}.{$field}";
                $newValue = $entry[$field];
                $oldValue = get_from_cache_or_file($memcache, $key, $fileData);

                if ($oldValue !== $newValue) {
                    $memcache->set($key, $newValue, 0, 3600);
                    $changesDetected = true;
                    __log("Updated {$key} in memcache");
                }
            }
        }

        $newFileData["dvb_metrics_{$dvbId}"] = [
            'id' => $dvbId,
            'type' => 'dvb_metrics'
        ];
    }

    if ($changesDetected) {
        save_json_if_changed($jsonFile, $newFileData);
    }

    http_response_code(200);
    exit;
}

if (!empty($data)) {
    try {
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        if (isset($data['channel_id']) && isset($data['input_id'])) {
            $channelId = $data['channel_id'];
            $inputId = $data['input_id'];
            $channelName = $data['channel_name'] ?? "Channel {$channelId}";
            $prefix = "channel.{$channelId}.{$inputId}";

            $metrics = [
                'sc_error', 'pes_error', 'pcr_error',
                'cc_error', 'bitrate', 'packets', 'onair'
            ];

            $changesDetected = false;
            $newFileData = $fileData;

            foreach ($metrics as $metric) {
                if (isset($data[$metric])) {
                    $key = "$prefix.$metric";
                    $newValue = $data[$metric];
                    $oldValue = get_from_cache_or_file($memcache, $key, $fileData);

                    if ($oldValue !== $newValue) {
                        $memcache->set($key, $newValue, 0, 3600);
                        $changesDetected = true;
                        __log("Updated {$key} in memcache");
                    }
                }
            }

            $newFileData[$channelId] = [
                'id' => $channelId,
                'name' => $channelName
            ];

            if ($changesDetected) {
                save_json_if_changed($jsonFile, $newFileData);
            }

            $timestampKey = "$prefix.timestamp";
            $timestamp = time();
            $memcache->set($timestampKey, $timestamp, 0, 3600);

            $lastInputKey = "channel{$channelId}.lastInput";
            $memcache->set($lastInputKey, $inputId, 0, 3600);

            http_response_code(200);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        __log("Processing error: " . $e->getMessage());
        exit;
    }
}

http_response_code(400);
__log("Empty or unsupported data received");
?>