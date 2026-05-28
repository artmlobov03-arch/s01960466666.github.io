<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const AVAILABLE_LANGUAGES = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];

function validateUserData(array $data): array
{
    $errors = [];

    $fullName = trim($data['full_name'] ?? '');
    if ($fullName === '') {
        $errors['full_name'] = 'Укажите ФИО.';
    } elseif (mb_strlen($fullName) > 150) {
        $errors['full_name'] = 'ФИО не более 150 символов.';
    } elseif (!preg_match('/^[\p{L}\s\-]+$/u', $fullName)) {
        $errors['full_name'] = 'ФИО: только буквы, пробелы и дефис.';
    }

    $phone = trim($data['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Укажите телефон.';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone)) {
        $errors['phone'] = 'Недопустимый формат телефона.';
    }

    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Укажите e-mail.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = 'Некорректный e-mail.';
    } elseif (mb_strlen($email) > 255) {
        $errors['email'] = 'E-mail не более 255 символов.';
    }

    $birthDate = trim($data['birth_date'] ?? '');
    if ($birthDate === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
        $errors['birth_date'] = 'Формат даты: ГГГГ-ММ-ДД.';
    } else {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        $errs = DateTimeImmutable::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
        if (!$date || $date->format('Y-m-d') !== $birthDate || $errs['warning_count'] || $errs['error_count']) {
            $errors['birth_date'] = 'Некорректная дата.';
        } elseif ($date > new DateTimeImmutable('today')) {
            $errors['birth_date'] = 'Дата не может быть в будущем.';
        }
    }

    if (!in_array(trim($data['gender'] ?? ''), ['male', 'female'], true)) {
        $errors['gender'] = 'Выберите пол.';
    }

    $languages = array_values(array_unique(array_map('strval', (array)($data['languages'] ?? []))));
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, AVAILABLE_LANGUAGES, true)) {
                $errors['languages'] = 'Недопустимое значение языка.';
                break;
            }
        }
    }

    $bio = trim($data['biography'] ?? '');
    if ($bio === '') {
        $errors['biography'] = 'Напишите биографию.';
    } elseif (mb_strlen($bio) > 2000) {
        $errors['biography'] = 'Биография не более 2000 символов.';
    }

    if (!isset($data['contract_accepted']) || !$data['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо согласие с контрактом.';
    }

    return $errors;
}

function transliterate(string $str): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $result = '';
    foreach (mb_str_split(mb_strtolower($str)) as $char) {
        if (isset($map[$char])) {
            $result .= $map[$char];
        } elseif (preg_match('/[a-z0-9]/', $char)) {
            $result .= $char;
        }
    }
    return $result ?: 'user';
}

function generateLogin(PDO $pdo, string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName));
    $base = transliterate($parts[0] ?? 'user');
    if (empty($base)) $base = 'user';
    $login = $base;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ?');
        $stmt->execute([$login]);
        if (!$stmt->fetch()) break;
        $login = $base . $i++;
    }
    return $login;
}

function generatePassword(): string
{
    return bin2hex(random_bytes(5));
}

function createUser(PDO $pdo, array $data): array
{
    $login    = generateLogin($pdo, trim($data['full_name']));
    $password = generatePassword();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (login, password_hash, full_name, phone, email, birth_date, gender, biography, contract_accepted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $login,
            password_hash($password, PASSWORD_BCRYPT),
            trim($data['full_name']),
            trim($data['phone']),
            trim($data['email']),
            trim($data['birth_date']),
            trim($data['gender']),
            trim($data['biography']),
        ]);
        $userId = (int)$pdo->lastInsertId();

        saveUserLanguages($pdo, $userId, (array)($data['languages'] ?? []));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['login' => $login, 'password' => $password, 'user_id' => $userId];
}

function updateUser(PDO $pdo, int $userId, array $data): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE users SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=? WHERE id=?'
        );
        $stmt->execute([
            trim($data['full_name']),
            trim($data['phone']),
            trim($data['email']),
            trim($data['birth_date']),
            trim($data['gender']),
            trim($data['biography']),
            $userId,
        ]);

        $pdo->prepare('DELETE FROM user_languages WHERE user_id = ?')->execute([$userId]);
        saveUserLanguages($pdo, $userId, (array)($data['languages'] ?? []));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function saveUserLanguages(PDO $pdo, int $userId, array $languages): void
{
    $sel = $pdo->prepare('SELECT id FROM programming_languages WHERE name = ?');
    $ins = $pdo->prepare('INSERT IGNORE INTO user_languages (user_id, language_id) VALUES (?, ?)');
    foreach ($languages as $lang) {
        $sel->execute([$lang]);
        $langId = $sel->fetchColumn();
        if ($langId !== false) {
            $ins->execute([$userId, (int)$langId]);
        }
    }
}

function getUserById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, login, full_name, phone, email, birth_date, gender, biography, created_at FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return null;

    $stmt = $pdo->prepare(
        'SELECT pl.name FROM user_languages ul JOIN programming_languages pl ON pl.id = ul.language_id WHERE ul.user_id = ?'
    );
    $stmt->execute([$userId]);
    $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return $user;
}

function profileUrl(string $base): string
{
    return rtrim($base, '/') . '/profile.php';
}
