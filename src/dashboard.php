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
    $maxAttemptsWithoutData = 5; 
    $emptyAttempts = 0;

    while ($emptyAttempts < $maxAttemptsWithoutData) {
        $prefix = "channel.{$id}.{$input}";
        $key = "$prefix.sc_error"; 
        $new = memcache_get($memcache, $key);

        if ($new === false || $new === null) {
            $emptyAttempts++;
            $input++;
            continue;
        }

        $emptyAttempts = 0;
        $metrics = ['sc_error', 'pes_error', 'pcr_error', 'bitrate', 'onair', 'timestamp'];
        $values = [];

        foreach ($metrics as $metric) {
            $key = "$prefix.$metric";
            $values[$metric] = memcache_get($memcache, $key);
        }

        $entry = [
            'name' => $name,
            'input' => $input,
            'sc_error' => ($values['sc_error'] === null || $values['sc_error'] === false) ? 'N/A' : $values['sc_error'],
            'pes_error' => ($values['pes_error'] === null || $values['pes_error'] === false) ? 'N/A' : $values['pes_error'],
            'pcr_error' => ($values['pcr_error'] === null || $values['pcr_error'] === false) ? 'N/A' : $values['pcr_error'],
            'bitrate' => ($values['bitrate'] === null || $values['bitrate'] === false) ? 'N/A' : $values['bitrate'],
            'onair' => $values['onair'] ?: false,
            'timestamp' => $values['timestamp'] ?: 'N/A',
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
