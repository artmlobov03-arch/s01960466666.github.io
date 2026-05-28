<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function parseInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
        $body = file_get_contents('php://input');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) return [];
        $data = json_decode(json_encode($xml), true);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function requireAuth(): int
{
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'error' => 'Требуется авторизация'], 401);
    }
    return (int)$_SESSION['user_id'];
}

$method = $_SERVER['REQUEST_METHOD'];

// POST — регистрация нового пользователя
if ($method === 'POST') {
    $data = parseInput();

    $errors = validateUserData($data);
    if ($errors) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $pdo = getPdo();
        $result = createUser($pdo, $data);

        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . dirname($_SERVER['REQUEST_URI']);

        jsonResponse([
            'success'     => true,
            'login'       => $result['login'],
            'password'    => $result['password'],
            'profile_url' => profileUrl($base),
        ], 201);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()], 500);
    }
}

// GET — получить профиль авторизованного пользователя
if ($method === 'GET') {
    $userId = requireAuth();

    try {
        $pdo  = getPdo();
        $user = getUserById($pdo, $userId);
        if (!$user) jsonResponse(['success' => false, 'error' => 'Пользователь не найден'], 404);

        jsonResponse(['success' => true, 'user' => $user]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Ошибка сервера'], 500);
    }
}

// PUT — обновить профиль (логин и пароль не меняются)
if ($method === 'PUT') {
    $userId = requireAuth();
    $data   = parseInput();

    // contract_accepted по умолчанию true для уже зарегистрированных
    $data['contract_accepted'] = true;

    $errors = validateUserData($data);
    if ($errors) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $pdo = getPdo();
        updateUser($pdo, $userId, $data);
        jsonResponse(['success' => true, 'message' => 'Данные обновлены.']);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Метод не поддерживается'], 405);
