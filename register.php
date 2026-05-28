<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

// Уже авторизован — в профиль
if (!empty($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$values = [
    'full_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
    'gender' => '', 'languages' => [], 'biography' => '', 'contract_accepted' => false,
];
$errors       = [];
$successData  = null; // ['login', 'password', 'profile_url']

// Fallback: обычный POST без JS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $values = [
        'full_name'         => trim($_POST['full_name'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'birth_date'        => trim($_POST['birth_date'] ?? ''),
        'gender'            => trim($_POST['gender'] ?? ''),
        'languages'         => array_values(array_unique(array_map('strval', $_POST['languages'] ?? []))),
        'biography'         => trim($_POST['biography'] ?? ''),
        'contract_accepted' => isset($_POST['contract_accepted']),
    ];

    $errors = validateUserData($values);

    if (empty($errors)) {
        try {
            $pdo    = getPdo();
            $result = createUser($pdo, $values);

            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . dirname($_SERVER['REQUEST_URI']);

            $successData = [
                'login'       => $result['login'],
                'password'    => $result['password'],
                'profile_url' => profileUrl($base),
            ];
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
    <title>Регистрация — Audi</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#BB0A30; --dark:#0a0a0a; --card:#161616; --border:#2a2a2a; --text:#f0f0f0; --muted:#888; --input:#1e1e1e; }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px}
        a{color:var(--primary);text-decoration:none}
        a:hover{text-decoration:underline}

        .back{align-self:flex-start;margin-bottom:24px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px}
        .card{width:100%;max-width:640px;background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden}
        .card__head{background:linear-gradient(135deg,#1a0008,#0a0a0a);padding:32px 36px;border-bottom:3px solid var(--primary)}
        .card__head h1{font-size:24px;font-weight:900;margin-bottom:6px}
        .card__head p{font-size:13px;color:var(--muted)}
        .card__body{padding:32px 36px}

        .alert{padding:14px 18px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:24px;border-left:4px solid}
        .alert--success{background:rgba(0,200,80,.1);color:#0c8;border-color:#0c8}
        .alert--error{background:rgba(187,10,48,.1);color:#f55;border-color:var(--primary)}

        .success-box{background:rgba(0,200,80,.07);border:1px solid rgba(0,200,80,.3);border-radius:14px;padding:28px;text-align:center}
        .success-box h2{color:#0c8;font-size:20px;margin-bottom:16px}
        .cred{background:var(--input);border-radius:10px;padding:16px 20px;margin:12px 0;display:flex;justify-content:space-between;align-items:center;font-size:15px}
        .cred span{color:var(--muted);font-size:13px}
        .cred strong{font-size:18px;letter-spacing:.05em;color:#fff}
        .btn{display:inline-block;background:var(--primary);color:#fff;padding:13px 28px;border-radius:10px;font-weight:700;font-size:15px;margin-top:20px;border:none;cursor:pointer;transition:background .2s}
        .btn:hover{background:#cc0033}

        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        .full{grid-column:1/-1}
        .field{display:flex;flex-direction:column;gap:6px}
        label{font-size:13px;font-weight:600;color:var(--text)}
        input[type=text],input[type=tel],input[type=email],input[type=date],textarea,select{
            width:100%;background:var(--input);border:1.5px solid var(--border);border-radius:10px;
            padding:11px 14px;font:inherit;font-size:14px;color:var(--text);transition:border-color .2s}
        input:focus,textarea:focus,select:focus{outline:none;border-color:var(--primary)}
        input::placeholder,textarea::placeholder{color:#555}
        select[multiple]{min-height:150px;padding:8px}
        select[multiple] option{padding:6px 8px;border-radius:6px;margin-bottom:2px}
        textarea{resize:vertical;min-height:100px;line-height:1.6}
        .is-invalid{border-color:var(--primary)!important}
        .err{font-size:12px;color:#f55;margin-top:2px}
        .hint{font-size:12px;color:var(--muted)}

        .check-wrap{display:flex;align-items:flex-start;gap:12px;background:var(--input);border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;cursor:pointer;font-size:13px;color:var(--text);line-height:1.5}
        .check-wrap input{width:16px;height:16px;margin-top:2px;accent-color:var(--primary);flex-shrink:0;cursor:pointer}

        .submit-wrap{margin-top:24px}
        .submit-btn{width:100%;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:14px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s,transform .15s;box-shadow:0 4px 16px rgba(187,10,48,.3)}
        .submit-btn:hover{background:#cc0033;transform:translateY(-1px)}
        .submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

        .nav-foot{margin-top:24px;font-size:14px;color:var(--muted)}

        @media(max-width:560px){.grid{grid-template-columns:1fr}.full{grid-column:1}.card__head,.card__body{padding:24px 20px}}
    </style>
</head>
<body>

<a class="back" href="index.php">← Вернуться на сайт</a>

<div class="card">
    <div class="card__head">
        <h1>Анкета разработчика</h1>
        <p>Заполните форму для регистрации в системе Audi</p>
    </div>
    <div class="card__body">

        <?php if ($successData): ?>
            <div class="success-box">
                <h2>✓ Регистрация прошла успешно!</h2>
                <p style="color:var(--muted);font-size:13px;margin-bottom:8px">Сохраните данные для входа:</p>
                <div class="cred"><span>Логин</span><strong><?= e($successData['login']) ?></strong></div>
                <div class="cred"><span>Пароль</span><strong><?= e($successData['password']) ?></strong></div>
                <a class="btn" href="<?= e($successData['profile_url']) ?>">Перейти в профиль →</a>
            </div>

        <?php else: ?>

            <?php if (!empty($errors['_db'])): ?>
                <div class="alert alert--error"><?= e($errors['_db']) ?></div>
            <?php endif; ?>

            <div id="js-message" style="display:none" class="alert"></div>
            <div id="js-success" style="display:none" class="success-box"></div>

            <form id="regForm" action="register.php" method="post" novalidate>
                <div class="grid">

                    <div class="field full">
                        <label for="full_name">ФИО</label>
                        <input id="full_name" name="full_name" type="text" maxlength="150"
                               value="<?= e($values['full_name']) ?>"
                               class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                               placeholder="Например, Петров Алексей Сергеевич">
                        <?php if (isset($errors['full_name'])): ?><div class="err"><?= e($errors['full_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" name="phone" type="tel"
                               value="<?= e($values['phone']) ?>"
                               class="<?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               placeholder="+7 999 000-00-00">
                        <?php if (isset($errors['phone'])): ?><div class="err"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input id="email" name="email" type="email"
                               value="<?= e($values['email']) ?>"
                               class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               placeholder="developer@example.com">
                        <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="birth_date">Дата рождения</label>
                        <input id="birth_date" name="birth_date" type="date"
                               value="<?= e($values['birth_date']) ?>"
                               class="<?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['birth_date'])): ?><div class="err"><?= e($errors['birth_date']) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Пол</label>
                        <div style="display:flex;gap:16px;margin-top:4px">
                            <?php foreach (['male' => 'Мужской', 'female' => 'Женский'] as $val => $label): ?>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer">
                                <input type="radio" name="gender" value="<?= $val ?>"
                                       <?= $values['gender'] === $val ? 'checked' : '' ?>
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
                            <option value="<?= e($lang) ?>" <?= in_array($lang, $values['languages'], true) ? 'selected' : '' ?>>
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
                                  class="<?= isset($errors['biography']) ? 'is-invalid' : '' ?>"
                                  placeholder="Расскажите об опыте, проектах, увлечениях..."><?= e($values['biography']) ?></textarea>
                        <?php if (isset($errors['biography'])): ?><div class="err"><?= e($errors['biography']) ?></div><?php endif; ?>
                    </div>

                    <div class="field full">
                        <label class="check-wrap <?= isset($errors['contract_accepted']) ? 'is-invalid' : '' ?>">
                            <input type="checkbox" name="contract_accepted" value="1"
                                   <?= $values['contract_accepted'] ? 'checked' : '' ?>>
                            Я ознакомился(-лась) с условиями контракта и согласен(-на) с его положениями
                        </label>
                        <?php if (isset($errors['contract_accepted'])): ?><div class="err"><?= e($errors['contract_accepted']) ?></div><?php endif; ?>
                    </div>

                </div>

                <div class="submit-wrap">
                    <button class="submit-btn" type="submit" id="submitBtn">Отправить анкету</button>
                </div>
            </form>

        <?php endif; ?>

        <p class="nav-foot">Уже зарегистрированы? <a href="login.php">Войти</a></p>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('regForm');
    if (!form) return;

    const msgBox    = document.getElementById('js-message');
    const successBox = document.getElementById('js-success');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const fd = new FormData(form);
        const languages = [];
        document.querySelectorAll('#languages option:checked').forEach(o => languages.push(o.value));

        const payload = {
            full_name:          fd.get('full_name'),
            phone:              fd.get('phone'),
            email:              fd.get('email'),
            birth_date:         fd.get('birth_date'),
            gender:             fd.get('gender'),
            languages:          languages,
            biography:          fd.get('biography'),
            contract_accepted:  fd.has('contract_accepted'),
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        msgBox.style.display = 'none';

        try {
            const res  = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:   JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                form.style.display = 'none';
                successBox.style.display = 'block';
                successBox.innerHTML = `
                    <h2>✓ Регистрация прошла успешно!</h2>
                    <p style="color:#888;font-size:13px;margin-bottom:8px">Сохраните данные для входа:</p>
                    <div class="cred"><span>Логин</span><strong>${escHtml(data.login)}</strong></div>
                    <div class="cred"><span>Пароль</span><strong>${escHtml(data.password)}</strong></div>
                    <a class="btn" href="${escHtml(data.profile_url)}">Перейти в профиль →</a>
                `;
            } else {
                showErrors(data.errors || {});
                if (data.error) showMsg(data.error, true);
            }
        } catch (err) {
            showMsg('Ошибка сети. Попробуйте ещё раз.', true);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Отправить анкету';
        }
    });

    function showMsg(text, isError) {
        msgBox.className = 'alert ' + (isError ? 'alert--error' : 'alert--success');
        msgBox.textContent = text;
        msgBox.style.display = 'block';
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function showErrors(errors) {
        // Clear old
        document.querySelectorAll('.err[data-js]').forEach(el => el.remove());
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

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
        if (Object.keys(errors).length) {
            document.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
})();
</script>
</body>
</html>
