<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

$isAuth = !empty($_SESSION['user_id']);
$user   = $isAuth ? getUserById(getPdo(), (int)$_SESSION['user_id']) : null;

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function val(?array $user, string $key): string { return e((string)($user[$key] ?? '')); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета — Audi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #BB0A30;
            --primary-h: #99091f;
            --bg: #f4f4f6;
            --surface: #ffffff;
            --border: #e2e2e8;
            --text: #111;
            --muted: #6b6b80;
            --danger: #d93025;
            --success: #1a7f4b;
            --radius: 12px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topnav { background: #000; border-bottom: 2px solid var(--primary); height: 60px; display: flex; align-items: center; }
        .topnav__inner { max-width: 1100px; margin: 0 auto; padding: 0 24px; width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 900; font-size: 20px; text-decoration: none; letter-spacing: 3px; }
        .brand span { color: var(--primary); }
        .topnav__menu { display: flex; gap: 28px; list-style: none; }
        .topnav__menu a { color: rgba(255,255,255,.7); font-size: 14px; font-weight: 600; text-decoration: none; transition: color .2s; }
        .topnav__menu a:hover, .topnav__menu a.active { color: #fff; }

        .page { max-width: 1100px; margin: 0 auto; padding: 48px 24px 80px; }
        .page__head { margin-bottom: 36px; }
        .page__head h1 { font-size: 28px; font-weight: 900; margin-bottom: 8px; }
        .page__head p { font-size: 15px; color: var(--muted); }

        .layout { display: grid; grid-template-columns: 1fr 320px; gap: 28px; align-items: start; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 32px; }

        .alert { padding: 13px 18px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 20px; border-left: 4px solid; }
        .alert--ok  { background: #edfaf3; color: var(--success); border-color: var(--success); }
        .alert--err { background: #fef1f0; color: var(--danger); border-color: var(--danger); }
        .hidden { display: none !important; }

        .creds { background: #0d1f2d; border: 1.5px solid #1e6fa8; border-radius: 12px; padding: 24px 28px; margin-bottom: 28px; }
        .creds h3 { font-size: 15px; font-weight: 700; margin-bottom: 16px; color: #4fc3f7; }
        .cred-row { display: flex; align-items: center; gap: 14px; padding: 8px 0; }
        .cred-row span { font-size: 13px; color: #90b8d0; min-width: 64px; }
        .cred-row strong { font-size: 17px; font-family: monospace; letter-spacing: .06em; color: #7de8c8; }
        .creds p { margin-top: 14px; font-size: 13px; color: #6fa8c0; line-height: 1.6; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field__label { font-size: 13px; font-weight: 600; }
        .field__label .req { color: var(--primary); }
        .field__input {
            width: 100%; background: #f9f9fb; border: 1.5px solid var(--border); border-radius: 8px;
            padding: 10px 13px; font: inherit; font-size: 14px; color: var(--text);
            transition: border-color .2s, box-shadow .2s;
        }
        .field__input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(187,10,48,.1); background: #fff; }
        .field__input[readonly] { opacity: .5; cursor: not-allowed; }
        .field__input::placeholder { color: #b0b0b8; }
        .field__error { font-size: 12px; color: var(--danger); min-height: 16px; }
        textarea.field__input { resize: vertical; min-height: 100px; line-height: 1.6; }
        select.field__input[multiple] { min-height: 140px; padding: 6px; }
        select.field__input[multiple] option { padding: 5px 8px; border-radius: 4px; }
        .is-invalid { border-color: var(--danger) !important; background: #fef1f0 !important; }
        .hint { font-size: 12px; color: var(--muted); }

        .radio-row { display: flex; gap: 20px; margin-top: 2px; }
        .radio-opt { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .radio-opt input { accent-color: var(--primary); width: 16px; height: 16px; }

        .check-label { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; line-height: 1.5; }
        .check-label input { accent-color: var(--primary); width: 16px; height: 16px; margin-top: 2px; flex-shrink: 0; }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 28px; border-radius: 8px; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; border: none; }
        .btn--primary { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(187,10,48,.25); }
        .btn--primary:hover { background: var(--primary-h); transform: translateY(-1px); }
        .btn--primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn--ghost { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn--ghost:hover { background: rgba(187,10,48,.06); }
        .btn--full { width: 100%; }
        .form-foot { display: flex; align-items: center; gap: 16px; margin-top: 24px; flex-wrap: wrap; }
        .form-note { font-size: 12px; color: var(--muted); }

        .infoCard { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; }
        .infoCard__title { font-size: 17px; font-weight: 800; margin-bottom: 8px; }
        .infoCard__text { font-size: 13px; color: var(--muted); margin-bottom: 18px; line-height: 1.6; }
        .infoCard .field { margin-bottom: 12px; }
        .login-name { font-size: 16px; font-weight: 700; color: var(--primary); margin: 8px 0 4px; }
        .login-sub { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

        footer { border-top: 1px solid var(--border); padding: 24px; margin-top: 40px; }
        .footer__inner { max-width: 1100px; margin: 0 auto; display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); }

        @media (max-width: 820px) {
            .layout { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full { grid-column: 1; }
            .page { padding: 28px 16px 60px; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <div class="topnav__inner">
        <a class="brand" href="index.html"><span>AUDI</span></a>
        <ul class="topnav__menu">
            <li><a href="index.html">Главная</a></li>
            <li><a href="register.php" class="active">Анкета</a></li>
        </ul>
    </div>
</nav>

<div class="page">
    <div class="page__head">
        <h1>Оставить заявку</h1>
        <p>Заполните форму — после отправки вы получите логин и пароль для редактирования данных.</p>
    </div>

    <div id="credsBox" class="creds hidden"></div>
    <div id="alertBox" class="alert hidden" style="margin-bottom:20px"></div>

    <div class="layout">

        <!-- ФОРМА -->
        <div>
            <div class="card">
                <form id="mainForm" novalidate>

                    <?php if ($isAuth && $user): ?>
                    <div class="field full" style="margin-bottom:20px">
                        <label class="field__label">Логин (нельзя изменить)</label>
                        <input class="field__input" type="text" value="<?= val($user,'login') ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <div class="form-grid">

                        <div class="field full">
                            <label class="field__label" for="full_name">ФИО <span class="req">*</span></label>
                            <input class="field__input" id="full_name" name="full_name" type="text" maxlength="150"
                                   value="<?= $user ? val($user,'full_name') : '' ?>"
                                   placeholder="Петров Алексей Сергеевич">
                            <span class="field__error" data-error-for="full_name"></span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="phone">Телефон <span class="req">*</span></label>
                            <input class="field__input" id="phone" name="phone" type="tel"
                                   value="<?= $user ? val($user,'phone') : '' ?>"
                                   placeholder="+7 999 000-00-00">
                            <span class="field__error" data-error-for="phone"></span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="email">E-mail <span class="req">*</span></label>
                            <input class="field__input" id="email" name="email" type="email"
                                   value="<?= $user ? val($user,'email') : '' ?>"
                                   placeholder="developer@example.com">
                            <span class="field__error" data-error-for="email"></span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="birth_date">Дата рождения <span class="req">*</span></label>
                            <input class="field__input" id="birth_date" name="birth_date" type="date"
                                   value="<?= $user ? val($user,'birth_date') : '' ?>">
                            <span class="field__error" data-error-for="birth_date"></span>
                        </div>

                        <div class="field">
                            <label class="field__label">Пол <span class="req">*</span></label>
                            <div class="radio-row">
                                <?php foreach (['male' => 'Мужской', 'female' => 'Женский'] as $v => $l): ?>
                                <label class="radio-opt">
                                    <input type="radio" name="gender" value="<?= $v ?>"
                                           <?= ($user['gender'] ?? '') === $v ? 'checked' : '' ?>>
                                    <?= $l ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <span class="field__error" data-error-for="gender"></span>
                        </div>

                        <div class="field full">
                            <label class="field__label" for="languages">Любимые языки программирования <span class="req">*</span></label>
                            <select class="field__input" id="languages" name="languages[]" multiple>
                                <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
                                <option value="<?= e($lang) ?>"
                                    <?= in_array($lang, $user['languages'] ?? [], true) ? 'selected' : '' ?>>
                                    <?= e($lang) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких</span>
                            <span class="field__error" data-error-for="languages"></span>
                        </div>

                        <div class="field full">
                            <label class="field__label" for="biography">Биография <span class="req">*</span></label>
                            <textarea class="field__input" id="biography" name="biography" rows="5"
                                      placeholder="Расскажите об опыте, проектах, увлечениях..."><?= $user ? val($user,'biography') : '' ?></textarea>
                            <span class="field__error" data-error-for="biography"></span>
                        </div>

                        <?php if (!$isAuth): ?>
                        <div class="field full">
                            <label class="check-label">
                                <input type="checkbox" name="contract_accepted" value="1">
                                Я ознакомился(-лась) с условиями контракта и согласен(-на) с его положениями
                            </label>
                            <span class="field__error" data-error-for="contract_accepted"></span>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="form-foot">
                        <button class="btn btn--primary" type="submit" id="submitBtn">
                            <?= $isAuth ? 'Обновить данные' : 'Отправить заявку' ?>
                        </button>
                        <?php if (!$isAuth): ?>
                        <p class="form-note">Нажимая «Отправить», вы соглашаетесь на обработку данных.</p>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <!-- КАРТОЧКА ВХОДА -->
        <div>
            <div class="infoCard" id="authCard">
                <?php if ($isAuth && $user): ?>
                    <div class="infoCard__title">Личный кабинет</div>
                    <div class="infoCard__text">Вы вошли как</div>
                    <div class="login-name">@<?= val($user,'login') ?></div>
                    <div class="login-sub">Зарегистрирован: <?= e(substr($user['created_at'] ?? '',0,10)) ?></div>
                    <form id="logoutForm">
                        <button class="btn btn--ghost btn--full" type="submit">Выйти</button>
                    </form>
                <?php else: ?>
                    <div class="infoCard__title">Уже есть аккаунт?</div>
                    <p class="infoCard__text">Войдите, чтобы загрузить и изменить ранее отправленные данные.</p>
                    <div id="authAlert" class="alert alert--err hidden"></div>
                    <form id="authForm" novalidate>
                        <div class="field">
                            <label class="field__label" for="authLogin">Логин</label>
                            <input class="field__input" id="authLogin" type="text" name="login" autocomplete="username">
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label class="field__label" for="authPass">Пароль</label>
                            <input class="field__input" id="authPass" type="password" name="password" autocomplete="current-password">
                        </div>
                        <button class="btn btn--ghost btn--full" type="submit" style="margin-top:16px" id="loginBtn">Войти</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<footer>
    <div class="footer__inner">
        <span>© 2026 Audi</span>
        <a href="index.html">← На главную</a>
    </div>
</footer>

<script>
(function () {
    const isAuth = <?= $isAuth ? 'true' : 'false' ?>;

    function qs(sel) { return document.querySelector(sel); }
    function qsa(sel) { return Array.from(document.querySelectorAll(sel)); }

    function showAlert(text, isError) {
        const box = qs('#alertBox');
        box.className = 'alert ' + (isError ? 'alert--err' : 'alert--ok');
        box.innerHTML = text;
        box.classList.remove('hidden');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearErrors() {
        qsa('.field__error').forEach(el => el.textContent = '');
        qsa('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function showFieldErrors(errors) {
        for (const [field, msg] of Object.entries(errors)) {
            const errEl = qs(`[data-error-for="${field}"]`);
            if (errEl) errEl.textContent = msg;
            const input = qs(`[name="${field}"], [name="${field}[]"]`);
            if (input) input.classList.add('is-invalid');
        }
        const first = qs('.is-invalid');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function showFormNote(text) {
        let note = qs('#formNote');
        if (!note) {
            note = document.createElement('p');
            note.id = 'formNote';
            note.style.cssText = 'margin-top:12px;font-size:13px;font-weight:600;color:var(--success)';
            qs('.form-foot')?.appendChild(note);
        }
        note.textContent = text;
    }

    // ── Основная форма ─────────────────────────────────────
    qs('#mainForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const languages = [];
        qsa('#languages option:checked').forEach(o => languages.push(o.value));

        const payload = {
            full_name:        (qs('[name=full_name]')?.value || '').trim(),
            phone:            (qs('[name=phone]')?.value || '').trim(),
            email:            (qs('[name=email]')?.value || '').trim(),
            birth_date:       (qs('[name=birth_date]')?.value || '').trim(),
            gender:           qs('[name=gender]:checked')?.value || '',
            languages,
            biography:        (qs('[name=biography]')?.value || '').trim(),
            contract_accepted: isAuth || !!(qs('[name=contract_accepted]')?.checked),
        };

        const btn = qs('#submitBtn');
        btn.disabled = true;
        btn.textContent = isAuth ? 'Сохранение...' : 'Отправка...';

        try {
            const res  = await fetch('api.php', {
                method:  isAuth ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                if (!isAuth) {
                    const box = qs('#credsBox');
                    box.innerHTML = `
                        <h3>Сохраните данные для входа:</h3>
                        <div class="cred-row"><span>Логин:</span><strong>${escHtml(data.login)}</strong></div>
                        <div class="cred-row"><span>Пароль:</span><strong>${escHtml(data.password)}</strong></div>
                        <p>Используйте их для входа в правой карточке, чтобы редактировать анкету.</p>
                    `;
                    box.classList.remove('hidden');
                    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    qs('#mainForm').reset();
                    qs('#alertBox').classList.add('hidden');
                    showFormNote('Данные сохранены. Запишите логин и пароль!');
                } else {
                    showAlert('✓ Данные успешно обновлены.', false);
                }
            } else {
                if (data.errors) showFieldErrors(data.errors);
                if (data.error)  showAlert(data.error, true);
            }
        } catch {
            showAlert('Ошибка сети. Попробуйте ещё раз.', true);
        } finally {
            btn.disabled = false;
            btn.textContent = isAuth ? 'Обновить данные' : 'Отправить заявку';
        }
    });

    // ── Форма входа ────────────────────────────────────────
    qs('#authForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const authAlert = qs('#authAlert');
        if (authAlert) authAlert.classList.add('hidden');

        const fd = new FormData();
        fd.append('action',   'login');
        fd.append('login',    qs('#authLogin')?.value || '');
        fd.append('password', qs('#authPass')?.value || '');

        const btn = qs('#loginBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Вход...'; }

        try {
            const res  = await fetch('auth.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                if (authAlert) {
                    authAlert.textContent = data.error || 'Ошибка входа.';
                    authAlert.classList.remove('hidden');
                }
            }
        } catch {
            if (authAlert) { authAlert.textContent = 'Ошибка сети.'; authAlert.classList.remove('hidden'); }
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Войти'; }
        }
    });

    // ── Выход ──────────────────────────────────────────────
    qs('#logoutForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'logout');
        await fetch('auth.php', { method: 'POST', body: fd });
        window.location.reload();
    });

})();
</script>
</body>
</html>
