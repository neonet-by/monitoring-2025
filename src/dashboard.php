<?php
session_start();
date_default_timezone_set('Europe/Minsk');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
$isAdmin = isset($user['role']) && $user['role'] === 'admin';

$memcache = memcache_connect('localhost', 11211);
if (!$memcache) {
    die("Не удалось подключиться к Memcached");
}

function getStatusClass($value) {
    if ($value === 'N/A') return 'neutral';
    if ($value > 1000) return 'critical';
    if ($value > 100) return 'warning';
    return 'normal';
}

function getMemcacheValue($memcache, $key) {
    $value = memcache_get($memcache, $key);
    return ($value === false || $value === null) ? 'N/A' : $value;
}

$channels = [
    'a002' => 'RTG TV',
    'a004' => 'DRIVE',
    'a003' => 'TEST'
];

$data = [];

foreach ($channels as $id => $name) {
    $inputData = [];
    $input = 1;
    $maxAttemptsWithoutData = 5; 
    $emptyAttempts = 0;

    while ($emptyAttempts < $maxAttemptsWithoutData) {
        $prefix = "channel.{$id}.{$input}";
        $key = "$prefix.sc_error"; 
        $new = memcache_get($memcache, $key);

        if ($new === false && $new !== 0 && $new !== '0') { 
            $emptyAttempts++;
            $input++;
            continue;
        }

        $emptyAttempts = 0;
        $metrics = ['sc_error', 'pes_error', 'pcr_error', 'bitrate', 'onair', 'timestamp'];
        $values = [];

        foreach ($metrics as $metric) {
            $key = "$prefix.$metric";
            $val = memcache_get($memcache, $key);
            
            if ($metric === 'onair') {
                $values[$metric] = ($val === false || $val === null) ? false : (bool)$val;
            } elseif ($metric === 'timestamp') {
                $values[$metric] = ($val === false || $val === null) ? 'N/A' : $val;
            } else {
                $values[$metric] = ($val === false && $val !== 0 && $val !== '0') ? 'N/A' : $val;
            }
        }

        $entry = [
            'name' => $name,
            'input' => $input,
            'sc_error' => $values['sc_error'],
            'pes_error' => $values['pes_error'],
            'pcr_error' => $values['pcr_error'],
            'bitrate' => $values['bitrate'],
            'onair' => $values['onair'],
            'timestamp' => $values['timestamp'],
        ];

        $inputData[$input] = $entry;
        $input++;
    }

    foreach ($inputData as $entry) {
        if ($isAdmin || $entry['onair']) {
            $data[] = $entry;
        }
    }
}

$dvbChannels = [
    'a001' => 'DVB Channel 1',
];

$dvbData = [];

foreach ($dvbChannels as $dvbId => $dvbName) {
    $configPrefix = "dvbconfig.{$dvbId}";
    $configMetrics = [
        'name', 'frequency', 'symbolrate', 'polarization', 
        'adapter', 'device', 'hostname', 'type', 
        'lof1', 'lof2', 'slof', 'lnb_sharing'
    ];
    
    $configValues = [];
    foreach ($configMetrics as $metric) {
        $val = memcache_get($memcache, "{$configPrefix}.{$metric}");
        $configValues[$metric] = ($val === false && $val !== 0 && $val !== '0') ? 'N/A' : $val;
    }

    $metricsPrefix = "dvbmetrics.{$dvbId}";
    $dynamicMetrics = ['count', 'unc', 'signal', 'ber', 'status', 'snr', 'timestamp'];
    
    $dynamicValues = [];
    foreach ($dynamicMetrics as $metric) {
        $val = memcache_get($memcache, "{$metricsPrefix}.{$metric}");
        if ($metric === 'timestamp') {
            $dynamicValues[$metric] = ($val === false || $val === null) ? 'N/A' : date('H:i:s', $val);
        } else {
            $dynamicValues[$metric] = ($val === false && $val !== 0 && $val !== '0') ? 'N/A' : $val;
        }
    }

    $dvbData[] = array_merge(
        ['id' => $dvbId],
        $configValues,
        $dynamicValues
    );
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .channel-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .channel-table th, .channel-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .channel-table th { background-color: #f2f2f2; }
        .normal { background-color: #d4edda; }
        .warning { background-color: #fff3cd; }
        .critical { background-color: #f8d7da; }
        .neutral { background-color: #e2e3e5; }
        .status { font-weight: bold; }
        .on-air { color: green; }
        .off-air { color: red; }

        .tabs { margin-top: 20px; }
        .tabs button {
            margin-right: 10px;
            padding: 8px 16px;
            cursor: pointer;
            border: 1px solid #aaa;
            background-color: #f8f9fa;
        }
        .tabs button.active {
            background-color: #007bff;
            color: white;
        }

        .table-section { display: none; }
        .table-section.active { display: block; }
    </style>
</head>
<body>
    <p>Пользователь: <strong><?= htmlspecialchars($user['username']) ?></strong></p>

    <div class="tabs">
        <button class="tab-btn active" onclick="showTable('table1', this)">Основные каналы</button>
        <button class="tab-btn" onclick="showTable('table2', this)">DVB устройства</button>
    </div>

    <div id="table1" class="table-section active">
        <table class="channel-table">
            <thead>
                <tr>
                    <th>Канал</th>
                    <th>Вход</th>
                    <th>SC Errors</th>
                    <th>PES Errors</th>
                    <th>PCR Errors</th>
                    <th>Bitrate</th>
                    <th>Статус</th>
                    <th>Последнее обновление</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['input'] ?></td>
                        <td class="<?= getStatusClass($row['sc_error']) ?>"><?= $row['sc_error'] === 'N/A' ? 'N/A' : (int)$row['sc_error'] ?></td>
                        <td class="<?= getStatusClass($row['pes_error']) ?>"><?= $row['pes_error'] === 'N/A' ? 'N/A' : (int)$row['pes_error'] ?></td>
                        <td class="<?= getStatusClass($row['pcr_error']) ?>"><?= $row['pcr_error'] === 'N/A' ? 'N/A' : (int)$row['pcr_error'] ?></td>
                        <td><?= $row['bitrate'] === 'N/A' ? 'N/A' : (int)$row['bitrate'] ?> kbps</td>
                        <td class="status <?= $row['onair'] ? 'on-air' : 'off-air' ?>"><?= $row['onair'] ? 'ON AIR' : 'OFF' ?></td>
                        <td><?= is_numeric($row['timestamp']) ? date('H:i:s', $row['timestamp']) : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="table2" class="table-section">
        <table class="channel-table">
            <thead>
                <tr>
                    <th rowspan="2">Имя</th>
                    <th rowspan="2">Хост</th>
                    <th colspan="4">Конфигурация</th>
                    <th colspan="7">Метрики</th>
                </tr>
                <tr>
                    <!-- Конфигурация -->
                    <th>Частота</th>
                    <th>Скорость</th>
                    <th>Поляризация</th>
                    <th>Адаптер</th>
                    
                    <!-- Метрики -->
                    <th>Count</th>
                    <th>Unc</th>
                    <th>Signal</th>
                    <th>BER</th>
                    <th>Status</th>
                    <th>SNR</th>
                    <th>Время</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dvbData as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['hostname']) ?></td>
                        
                        <!-- Конфигурация -->
                        <td><?= htmlspecialchars($row['frequency']) ?></td>
                        <td><?= htmlspecialchars($row['symbolrate']) ?></td>
                        <td><?= htmlspecialchars($row['polarization']) ?></td>
                        <td><?= htmlspecialchars($row['adapter']) ?></td>
                        
                        <!-- Метрики -->
                        <td><?= $row['count'] === 'N/A' ? 'N/A' : (int)$row['count'] ?></td>
                        <td><?= $row['unc'] === 'N/A' ? 'N/A' : (int)$row['unc'] ?></td>
                        <td><?= $row['signal'] === 'N/A' ? 'N/A' : (int)$row['signal'] ?></td>
                        <td><?= $row['ber'] === 'N/A' ? 'N/A' : (float)$row['ber'] ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= $row['snr'] === 'N/A' ? 'N/A' : (float)$row['snr'] ?></td>
                        <td><?= htmlspecialchars($row['timestamp']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function showTable(tableId, btn) {
            document.querySelectorAll('.table-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(tableId).classList.add('active');

            document.querySelectorAll('.tab-btn').forEach(button => {
                button.classList.remove('active');
            });
            btn.classList.add('active');
        }
    </script>
</body>
</html>