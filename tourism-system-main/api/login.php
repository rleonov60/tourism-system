<?php
session_start();
$conn = new mysqli("localhost", "admin", "admin", "tourism_db");
if ($conn->connect_error) die("Ошибка подключения к БД. Проверьте логин/пароль в login.php");

if (isset($_SESSION['user_id'])) { header("Location: ui.php"); exit; }

$error = '';
$is_locked = false;

// Блокировка на 1 час (3 попытки)
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_time'])) $_SESSION['lockout_time'] = 0;

if ($_SESSION['login_attempts'] >= 3 && time() < $_SESSION['lockout_time']) {
    $is_locked = true;
    $time_left = ceil(($_SESSION['lockout_time'] - time()) / 60);
    $error = "Аккаунт заблокирован на 1 час. Осталось: $time_left мин.";
} elseif (time() >= $_SESSION['lockout_time']) {
    $_SESSION['login_attempts'] = 0;
}

// 2FA - шаг
if (!isset($_SESSION['step'])) $_SESSION['step'] = 1;

// Капча
if (!isset($_SESSION['captcha_missing'])) $_SESSION['captcha_missing'] = rand(1, 4);
$missing = $_SESSION['captcha_missing'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_locked) {

    // ---------- Шаг 1: логин, пароль, капча ----------
    if (isset($_POST['step1'])) {
        $login   = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';
        $captcha  = (int)($_POST['captcha_choice'] ?? 0);

        if ($captcha !== $missing) {
            $error = "Неверно собрана капча!";
        } else {
            $stmt = $conn->prepare("SELECT id, login, password_hash, role FROM users WHERE login = ?");
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $isValid = false;
            if ($user) {
                // 1) Обычная проверка bcrypt-хеша
                if (password_verify($password, $user['password_hash'])) {
                    $isValid = true;
                }
                // 2) Legacy: в БД лежит пароль в открытом виде — принимаем и сразу перехешируем
                elseif ($password === $user['password_hash']) {
                    $isValid = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $upd->bind_param("si", $newHash, $user['id']);
                    $upd->execute();
                }
            }

            if ($isValid) {
                $_SESSION['temp_user_id']    = $user['id'];
                $_SESSION['temp_user_login'] = $user['login'];
                $_SESSION['temp_user_role']  = $user['role'];
                $_SESSION['step']            = 2;
                $_SESSION['login_attempts']  = 0;
                header("Location: login.php");
                exit;
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 3) {
                    $_SESSION['lockout_time'] = time() + 3600;
                    $is_locked = true;
                    $error = "3 неудачные попытки. Блокировка на 1 час.";
                } else {
                    $error = "Неверный логин или пароль. Осталось попыток: " . (3 - $_SESSION['login_attempts']);
                }
            }
        }
    }

    // ---------- Шаг 2: код 2FA ----------
    if (isset($_POST['step2'])) {
        $code = $_POST['code'] ?? '';
        if ($code === '1234') {
            $_SESSION['user_id']    = $_SESSION['temp_user_id'];
            $_SESSION['user_login'] = $_SESSION['temp_user_login'];
            $_SESSION['user_role']  = $_SESSION['temp_user_role'];
            unset($_SESSION['step'], $_SESSION['temp_user_id'], $_SESSION['temp_user_login'], $_SESSION['temp_user_role'], $_SESSION['captcha_missing']);
            $_SESSION['step'] = 1;
            header("Location: ui.php");
            exit;
        } else {
            $error = "Неверный код подтверждения.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход - Горизонт</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f2ee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); width: 100%; max-width: 400px; }
        h1 { font-size: 24px; margin-bottom: 20px; color: #222; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #444; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 14px; background: #d9534f; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 15px; }
        button:hover { background: #c9302c; }
        .alert { background: #ffebee; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }

        /* Капча */
        .captcha-box { display: flex; align-items: center; gap: 15px; background: #f9f9f9; padding: 10px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 15px; }
        .puzzle-mini { display: grid; grid-template-columns: 40px 40px; grid-template-rows: 40px 40px; gap: 2px; background: #ccc; border-radius: 4px; }
        .puzzle-mini img, .hole { width: 40px; height: 40px; object-fit: cover; background: #fff; display: flex; align-items: center; justify-content: center; border: 1px dashed #aaa; }
        .pieces { display: flex; gap: 5px; }
        .pieces img { width: 30px; height: 30px; object-fit: cover; cursor: pointer; border: 2px solid transparent; border-radius: 4px; }
        .pieces img.active { border-color: #d9534f; }
    </style>
</head>
<body>
<div class="card">
    <div style="font-weight: 900; font-size: 20px; margin-bottom: 30px;">Горизонт</div>

    <?php if ($is_locked): ?>
        <h1>Заблокировано</h1>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
        <a href="login.php" style="display:block; text-align:center; color:#d9534f; text-decoration:none;">Попробовать позже</a>

    <?php elseif ($_SESSION['step'] == 1): ?>
        <h1>Вход в систему</h1>
        <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group"><label>Логин</label><input type="text" name="login" required placeholder="admin"></div>
            <div class="form-group"><label>Пароль</label><input type="password" name="password" required placeholder="••••••••"></div>

            <label>Капча</label>
            <div class="captcha-box">
                <div class="puzzle-mini">
                    <?php for ($i=1;$i<=4;$i++) { if ($i==$missing) echo '<div class="hole">?</div>'; else echo '<img src="'.$i.'captcha_bg.jpg" alt="">'; } ?>
                </div>
                <div class="pieces">
                    <?php for ($i=1;$i<=4;$i++) echo '<img src="'.$i.'captcha_bg.jpg" onclick="selectPiece('.$i.')" data-p="'.$i.'" alt="">'; ?>
                </div>
                <input type="hidden" name="captcha_choice" id="captcha_choice" required>
            </div>

            <button type="submit" name="step1">Войти</button>
        </form>

    <?php else: ?>
        <h1>2FA Подтверждение</h1>
        <p style="color: #666; font-size: 14px;">Введите код (тест: 1234).</p>
        <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group"><input type="text" name="code" required placeholder="1234" style="text-align:center; font-size: 24px; letter-spacing: 10px;"></div>
            <button type="submit" name="step2">Подтвердить</button>
        </form>
        <div style="text-align:center; margin-top:15px;"><a href="logout.php" style="color: #888; font-size: 13px; text-decoration: none;">Отмена</a></div>
    <?php endif; ?>
</div>
<script>
    function selectPiece(id) {
        document.querySelectorAll('.pieces img').forEach(i => i.classList.remove('active'));
        document.querySelector('.pieces img[data-p="'+id+'"]').classList.add('active');
        document.getElementById('captcha_choice').value = id;
        var hole = document.querySelector('.puzzle-mini .hole');
        if (hole) hole.outerHTML = '<img src="'+id+'captcha_bg.jpg" alt="">';
    }
</script>
</body>
</html>