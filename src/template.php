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
