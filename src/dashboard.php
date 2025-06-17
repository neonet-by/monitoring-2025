<?php
session_start();
date_default_timezone_set('Europe/Minsk');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
$isAdmin = isset($user['role']) && $user['role'] === 'admin';

$memcache = new Memcache();
$memcache->connect('localhost', 11211);

function getStatusClass($value) {
    if ($value === 'N/A') return 'neutral';
    $value = (int)$value;
    if ($value > 1000) return 'critical';
    if ($value > 100) return 'warning';
    return 'normal';
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

    $maxInputs = 5; 
while ($input <= $maxInputs) {
    $prefix = "channel.{$id}.{$input}";

    $sc_error = memcache_get($memcache, "$prefix.sc_error");

    
    if ($sc_error === false) {
        $input++;
        continue;
    }

    $entry = [
        'name' => $name,
        'input' => $input,
        'sc_error' => $sc_error ?: 'N/A',
        'pes_error' => memcache_get($memcache, "$prefix.pes_error") ?: 'N/A',
        'pcr_error' => memcache_get($memcache, "$prefix.pcr_error") ?: 'N/A',
        'bitrate' => memcache_get($memcache, "$prefix.bitrate") ?: 'N/A',
        'onair' => memcache_get($memcache, "$prefix.onair") ?: false,
        'timestamp' => memcache_get($memcache, "$prefix.timestamp") ?: 'N/A',
    ];

    $inputData[$input] = $entry;
    $input++;
}


    if ($isAdmin) {
        foreach ($inputData as $entry) {
            $data[] = $entry;
        }
    } else {
        foreach ($inputData as $entry) {
            if ($entry['onair']) {
                $data[] = $entry;
            }
        }
    }
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
    </style>
</head>
<body>
    <p>Пользователь: <strong><?= htmlspecialchars($user['username']) ?></strong></p>

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

                    <td class="<?= getStatusClass($row['sc_error']) ?>">
                        <?= $row['sc_error'] ?>
                    </td>

                    <td class="<?= getStatusClass($row['pes_error']) ?>">
                        <?= $row['pes_error'] ?>
                    </td>

                    <td class="<?= getStatusClass($row['pcr_error']) ?>">
                        <?= $row['pcr_error'] ?>
                    </td>

                    <td><?= $row['bitrate'] ?> kbps</td>

                    <td class="status <?= $row['onair'] ? 'on-air' : 'off-air' ?>">
                        <?= $row['onair'] ? 'ON AIR' : 'OFF' ?>
                    </td>
                    <td><?= is_numeric($row['timestamp']) ? date('H:i:s', $row['timestamp']) : 'N/A' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
