<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$pdo     = getPdo();
$user    = getUserById($pdo, $userId);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$errors      = [];
$successMsg  = '';

// Fallback POST (без JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $data = [
        'full_name'         => trim($_POST['full_name'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'birth_date'        => trim($_POST['birth_date'] ?? ''),
        'gender'            => trim($_POST['gender'] ?? ''),
        'languages'         => array_values(array_unique(array_map('strval', $_POST['languages'] ?? []))),
        'biography'         => trim($_POST['biography'] ?? ''),
        'contract_accepted' => true,
    ];

    $errors = validateUserData($data);

    if (empty($errors)) {
        try {
            updateUser($pdo, $userId, $data);
            $user       = getUserById($pdo, $userId);
            $successMsg = 'Данные успешно обновлены.';
        } catch (Throwable $e) {
            $errors['_db'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — Audi</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#BB0A30;--dark:#0a0a0a;--card:#161616;--border:#2a2a2a;--text:#f0f0f0;--muted:#888;--input:#1e1e1e}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 16px}
        a{color:var(--primary);text-decoration:none}
        a:hover{text-decoration:underline}

        .topbar{width:100%;max-width:680px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .topbar a{font-size:14px;font-weight:600}
        .logout{background:rgba(187,10,48,.15);color:var(--primary);border:1px solid rgba(187,10,48,.3);border-radius:8px;padding:8px 16px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:background .2s}
        .logout:hover{background:rgba(187,10,48,.3)}

        .card{width:100%;max-width:680px;background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden}
        .card__head{background:linear-gradient(135deg,#1a0008,#0a0a0a);padding:28px 36px;border-bottom:3px solid var(--primary)}
        .card__head h1{font-size:22px;font-weight:900;margin-bottom:4px}
        .login-badge{display:inline-block;background:rgba(187,10,48,.15);color:var(--primary);border:1px solid rgba(187,10,48,.3);border-radius:6px;padding:3px 10px;font-size:12px;font-weight:700;letter-spacing:.08em;margin-top:6px}
        .card__body{padding:32px 36px}

        .alert{padding:13px 18px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px;border-left:4px solid}
        .alert--success{background:rgba(0,200,80,.1);color:#0c8;border-color:#0c8}
        .alert--error{background:rgba(187,10,48,.1);color:#f55;border-color:var(--primary)}

        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        .full{grid-column:1/-1}
        .field{display:flex;flex-direction:column;gap:6px}
        label{font-size:13px;font-weight:600;color:var(--text)}
        input[type=text],input[type=tel],input[type=email],input[type=date],textarea,select{
            width:100%;background:var(--input);border:1.5px solid var(--border);border-radius:10px;
            padding:11px 14px;font:inherit;font-size:14px;color:var(--text);transition:border-color .2s}
        input:focus,textarea:focus,select:focus{outline:none;border-color:var(--primary)}
        input[readonly]{opacity:.5;cursor:not-allowed}
        select[multiple]{min-height:150px;padding:8px}
        select[multiple] option{padding:6px 8px;border-radius:6px;margin-bottom:2px}
        textarea{resize:vertical;min-height:100px;line-height:1.6}
        .is-invalid{border-color:var(--primary)!important}
        .err{font-size:12px;color:#f55;margin-top:2px}
        .hint{font-size:12px;color:var(--muted)}

        .submit-btn{width:100%;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:14px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;margin-top:24px;transition:background .2s,transform .15s;box-shadow:0 4px 16px rgba(187,10,48,.3)}
        .submit-btn:hover{background:#cc0033;transform:translateY(-1px)}
        .submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

        .note{font-size:12px;color:var(--muted);margin-top:6px}

        @media(max-width:560px){.grid{grid-template-columns:1fr}.full{grid-column:1}.card__head,.card__body{padding:24px 20px}}
    </style>
</head>
<body>

<div class="topbar">
    <a href="index.php">← На главную</a>
    <form action="logout.php" method="post">
        <button type="submit" class="logout">Выйти</button>
    </form>
</div>

<div class="card">
    <div class="card__head">
        <h1>Мой профиль</h1>
        <div class="login-badge">@<?= e($user['login']) ?></div>
    </div>
    <div class="card__body">

        <?php if ($successMsg): ?>
            <div class="alert alert--success"><?= e($successMsg) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors['_db'])): ?>
            <div class="alert alert--error"><?= e($errors['_db']) ?></div>
        <?php endif; ?>

        <div id="js-message" style="display:none" class="alert"></div>

        <form id="profileForm" action="profile.php" method="post" novalidate>

            <div class="grid">

                <div class="field full">
                    <label>Логин (нельзя изменить)</label>
                    <input type="text" value="<?= e($user['login']) ?>" readonly>
                </div>

                <div class="field full">
                    <label for="full_name">ФИО</label>
                    <input id="full_name" name="full_name" type="text" maxlength="150"
                           value="<?= e($user['full_name']) ?>"
                           class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['full_name'])): ?><div class="err"><?= e($errors['full_name']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="phone">Телефон</label>
                    <input id="phone" name="phone" type="tel"
                           value="<?= e($user['phone']) ?>"
                           class="<?= isset($errors['phone']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['phone'])): ?><div class="err"><?= e($errors['phone']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email"
                           value="<?= e($user['email']) ?>"
                           class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="birth_date">Дата рождения</label>
                    <input id="birth_date" name="birth_date" type="date"
                           value="<?= e($user['birth_date']) ?>"
                           class="<?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['birth_date'])): ?><div class="err"><?= e($errors['birth_date']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label>Пол</label>
                    <div style="display:flex;gap:16px;margin-top:4px">
                        <?php foreach (['male' => 'Мужской', 'female' => 'Женский'] as $val => $label): ?>
                        <label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer">
                            <input type="radio" name="gender" value="<?= $val ?>"
                                   <?= $user['gender'] === $val ? 'checked' : '' ?>
                                   style="accent-color:var(--primary);width:16px;height:16px">
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['gender'])): ?><div class="err"><?= e($errors['gender']) ?></div><?php endif; ?>
                </div>

                <div class="field full">
                    <label for="languages">Любимые языки программирования</label>
                    <select id="languages" name="languages[]" multiple
                            class="<?= isset($errors['languages']) ? 'is-invalid' : '' ?>">
                        <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
                        <option value="<?= e($lang) ?>" <?= in_array($lang, $user['languages'], true) ? 'selected' : '' ?>>
                            <?= e($lang) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Удерживайте Ctrl (или Cmd на Mac) для выбора нескольких</span>
                    <?php if (isset($errors['languages'])): ?><div class="err"><?= e($errors['languages']) ?></div><?php endif; ?>
                </div>

                <div class="field full">
                    <label for="biography">Биография</label>
                    <textarea id="biography" name="biography" rows="5"
                              class="<?= isset($errors['biography']) ? 'is-invalid' : '' ?>"><?= e($user['biography']) ?></textarea>
                    <?php if (isset($errors['biography'])): ?><div class="err"><?= e($errors['biography']) ?></div><?php endif; ?>
                </div>

            </div>

            <button type="submit" class="submit-btn" id="saveBtn">Сохранить изменения</button>
            <p class="note">Зарегистрирован: <?= e($user['created_at']) ?></p>
        </form>
    </div>
</div>

<script>
(function () {
    const form    = document.getElementById('profileForm');
    const msgBox  = document.getElementById('js-message');
    const saveBtn = document.getElementById('saveBtn');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const languages = [];
        document.querySelectorAll('#languages option:checked').forEach(o => languages.push(o.value));

        const payload = {
            full_name:         form.querySelector('[name=full_name]').value,
            phone:             form.querySelector('[name=phone]').value,
            email:             form.querySelector('[name=email]').value,
            birth_date:        form.querySelector('[name=birth_date]').value,
            gender:            (form.querySelector('[name=gender]:checked') || {}).value || '',
            languages:         languages,
            biography:         form.querySelector('[name=biography]').value,
            contract_accepted: true,
        };

        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';
        msgBox.style.display = 'none';

        try {
            const res  = await fetch('api.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body:   JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                showMsg('✓ Данные успешно обновлены.', false);
            } else {
                clearErrors();
                if (data.errors) showFieldErrors(data.errors);
                showMsg(data.error || 'Проверьте поля формы.', true);
            }
        } catch (err) {
            showMsg('Ошибка сети. Попробуйте ещё раз.', true);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить изменения';
        }
    });

    function showMsg(text, isError) {
        msgBox.className = 'alert ' + (isError ? 'alert--error' : 'alert--success');
        msgBox.textContent = text;
        msgBox.style.display = 'block';
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearErrors() {
        document.querySelectorAll('.err[data-js]').forEach(el => el.remove());
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function showFieldErrors(errors) {
        for (const [field, msg] of Object.entries(errors)) {
            const input = document.querySelector(`[name="${field}"], [name="${field}[]"]`);
            if (input) {
                input.classList.add('is-invalid');
                const div = document.createElement('div');
                div.className = 'err';
                div.dataset.js = '1';
                div.textContent = msg;
                input.closest('.field')?.appendChild(div);
            }
        }
    }
})();
</script>
</body>
</html>
