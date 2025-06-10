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
</head>
<body>
    <h1>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h1>
    <p>Роль: <strong><?= $user['role'] ?></strong></p>

    <?php if ($user['role'] === 'admin'): ?>
        <h2>Админ-панель</h2>
        <ul>
            <li>Управление пользователями</li>
            <li>Просмотр логов</li>
            <li>Настройки системы</li>
        </ul>
    <?php else: ?>
        <h2>Панель пользователя</h2>
        <ul>
            <li>Просмотр профиля</li>
            <li>Редактировать данные</li>
            <li>История активности</li>
        </ul>
    <?php endif; ?>

    <p><a href="logout.php">Выйти</a></p>
</body>
</html>