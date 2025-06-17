<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];

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
    $activeInputData = null;
    for ($input = 1; $input <= 2; $input++) {
        $prefix = "channel.{$id}.{$input}";

        $sc_error = memcache_get($memcache, "$prefix.sc_error");
        $pes_error = memcache_get($memcache, "$prefix.pes_error");
        $pcr_error = memcache_get($memcache, "$prefix.pcr_error");
        $bitrate = memcache_get($memcache, "$prefix.bitrate");
        $onair = memcache_get($memcache, "$prefix.onair");
        $timestamp = memcache_get($memcache, "$prefix.timestamp");

        $entry = [
            'name' => $name,
            'input' => $input,
            'sc_error' => $sc_error !== false ? $sc_error : 'N/A',
            'pes_error' => $pes_error !== false ? $pes_error : 'N/A',
            'pcr_error' => $pcr_error !== false ? $pcr_error : 'N/A',
            'bitrate' => $bitrate !== false ? $bitrate : 'N/A',
            'onair' => $onair !== false ? $onair : 'N/A',
            'timestamp' => $timestamp !== false ? $timestamp : 'N/A',
        ];

        if ($entry['onair']) {
            $activeInputData = $entry;
            break;
        }

        if ($input === 1 && $activeInputData === null) {
            $activeInputData = $entry;
        }
    }

    if ($activeInputData !== null) {
        $data[] = $activeInputData;
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
                    <td>Input <?= $row['input'] ?></td>

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