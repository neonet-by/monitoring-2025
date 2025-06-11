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
    <h1>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h1>
    <p>Роль: <strong><?= $user['role'] ?></strong></p>

    <?php if ($user['role'] === 'admin'): ?>
        <h2>Админ-панель</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя пользователя</th>
                    <th>Email</th>
                    <th>Дата регистрации</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>admin</td>
                    <td>admin@example.com</td>
                    <td>2024-01-01</td>
                    <td>Активен</td>
                </tr>
                <!-- Добавьте другие строки при необходимости -->
            </tbody>
        </table>
       
    <?php else: ?>
        <h2>Панель пользователя</h2>
        <table>
            <thead>
                <tr>
                    <th>Имя пользователя</th>
                    <th>Email</th>
                    <th>Дата регистрации</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['created_at'] ?? 'неизвестно') ?></td>
                </tr>
            </tbody>
        </table>
        
    <?php endif; ?>

    <p><a href="logout.php">Выйти</a></p>
</body>
</html>