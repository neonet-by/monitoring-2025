<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления</title>
    <style>
        table {
            border-collapse: collapse;
            width: 60%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #444;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #eee;
        }
    </style>
</head>
<body>
    <p>Роль: <strong><?= $user['role'] ?></strong></p>

    

    <table>
        <thead>
            <tr>
                <?php if ($user['role'] === 'admin'): ?>
                    <th>ID</th>
                <?php endif; ?>
                <th>Имя пользователя</th>
                <th>Телефон</th>
                <?php if ($user['role'] === 'admin'): ?>
                    <th>Дата регистрации</th>
                    <th>Статус</th>
                <?php else: ?>
                    <th>Дата регистрации</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php if ($user['role'] === 'admin'): ?>
                    <td>1</td>
                <?php endif; ?>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['phone'] ?? 'неизвестно') ?></td>
                <td><?= htmlspecialchars($user['created_at'] ?? 'неизвестно') ?></td>
                <?php if ($user['role'] === 'admin'): ?>
                    <td>Активен</td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>

    <p><a href="logout.php">Выйти</a></p>
</body>
</html>