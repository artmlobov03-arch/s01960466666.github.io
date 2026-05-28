<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        try {
            $pdo  = getPdo();
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE login = ?');
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: profile.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль.';
            }
        } catch (Throwable $e) {
            $error = 'Ошибка сервера. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Audi</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#BB0A30;--dark:#0a0a0a;--card:#161616;--border:#2a2a2a;--text:#f0f0f0;--muted:#888;--input:#1e1e1e}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 16px}
        a{color:var(--primary);text-decoration:none}
        a:hover{text-decoration:underline}
        .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden}
        .card__head{background:linear-gradient(135deg,#1a0008,#0a0a0a);padding:28px 32px;border-bottom:3px solid var(--primary);text-align:center}
        .logo{font-size:32px;font-weight:900;color:var(--primary);letter-spacing:3px;margin-bottom:8px}
        .card__head p{font-size:13px;color:var(--muted)}
        .card__body{padding:28px 32px}
        .alert--error{background:rgba(187,10,48,.1);color:#f55;border-left:4px solid var(--primary);border-radius:8px;padding:12px 16px;font-size:14px;font-weight:600;margin-bottom:20px}
        .field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
        label{font-size:13px;font-weight:600}
        input[type=text],input[type=password]{width:100%;background:var(--input);border:1.5px solid var(--border);border-radius:10px;padding:12px 14px;font:inherit;font-size:14px;color:var(--text);transition:border-color .2s}
        input:focus{outline:none;border-color:var(--primary)}
        .btn{width:100%;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:14px;font:inherit;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px;transition:background .2s}
        .btn:hover{background:#cc0033}
        .foot{margin-top:20px;text-align:center;font-size:13px;color:var(--muted)}
    </style>
</head>
<body>
<div class="card">
    <div class="card__head">
        <div class="logo">AUDI</div>
        <p>Войдите в аккаунт разработчика</p>
    </div>
    <div class="card__body">
        <?php if ($error): ?>
            <div class="alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <div class="field">
                <label for="login">Логин</label>
                <input id="login" type="text" name="login" placeholder="Ваш логин" autocomplete="username"
                       value="<?= htmlspecialchars($_POST['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" type="password" name="password" placeholder="Ваш пароль" autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>

        <div class="foot">
            Нет аккаунта? <a href="register.php">Зарегистрироваться</a><br><br>
            <a href="index.php">← На главную</a>
        </div>
    </div>
</div>
</body>
</html>
