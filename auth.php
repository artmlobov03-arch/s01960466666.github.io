<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$login || !$password) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Введите логин и пароль.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo  = getPdo();
        $stmt = $pdo->prepare('SELECT id, login, password_hash FROM users WHERE login = ?');
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            echo json_encode(['success' => true, 'login' => $user['login']], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль.'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка сервера.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Неизвестное действие.'], JSON_UNESCAPED_UNICODE);
