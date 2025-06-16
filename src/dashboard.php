<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];

// подключение мемкэша
$memcache = new Memcache();
$memcache->connect('localhost', 11211);

function getMetric($memcache, $key) {
    $value = $memcache->get($key);
    return $value !== false ? $value : 'N/A';
}

function getStatusClass($value) {
    if ($value === 'N/A') return 'neutral';
    $value = (int)$value;
    if ($value > 1000) return 'critical';
    if ($value > 100) return 'warning';
    return 'normal';
}

// каналы для отображения
$channels = [
    'a002' => 'RTG TV',
    'a004' => 'DRIVE',
    'a003' => 'TEST'
];
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
            <?php foreach ($channels as $id => $name): ?>
                <?php for ($input = 1; $input <= 2; $input++): ?>
                    <tr>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td>Input <?= $input ?></td>
                        
                        <td class="<?= getStatusClass(getMetric($memcache, "channel.{$id}.{$input}.sc_error")) ?>">
                            <?= getMetric($memcache, "channel.{$id}.{$input}.sc_error") ?>
                        </td>
                        
                        <td class="<?= getStatusClass(getMetric($memcache, "channel.{$id}.{$input}.pes_error")) ?>">
                            <?= getMetric($memcache, "channel.{$id}.{$input}.pes_error") ?>
                        </td>
                        
                        <td class="<?= getStatusClass(getMetric($memcache, "channel.{$id}.{$input}.pcr_error")) ?>">
                            <?= getMetric($memcache, "channel.{$id}.{$input}.pcr_error") ?>
                        </td>
                        
                        <td><?= getMetric($memcache, "channel.{$id}.{$input}.bitrate") ?> kbps</td>
                        
                        <td class="status <?= getMetric($memcache, "channel.{$id}.{$input}.onair") ? 'on-air' : 'off-air' ?>">
                            <?= getMetric($memcache, "channel.{$id}.{$input}.onair") ? 'ON AIR' : 'OFF' ?>
                        </td>
                        
                        <td><?= date('H:i:s', getMetric($memcache, "channel.{$id}.{$input}.timestamp")) ?></td>
                    </tr>
                <?php endfor; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>