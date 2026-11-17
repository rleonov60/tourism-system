<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "admin", "admin", "tourism_db");
if ($conn->connect_error) die("Ошибка БД");

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = "Пользователь не найден.";
    } else {
        // Проверка текущего пароля: bcrypt-хеш или legacy plain-text
        $currentIsValid = false;
        if (password_verify($current, $user['password_hash'])) {
            $currentIsValid = true;
        } elseif ($current === $user['password_hash']) {
            $currentIsValid = true;
        }

        if (!$currentIsValid) {
            $error = "Текущий пароль неверен.";
        } elseif ($new !== $confirm) {
            $error = "Новые пароли не совпадают.";
        } elseif (strlen($new) < 6 || !preg_match('/[0-9]/', $new) || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new)) {
            $error = "Пароль не соответствует требованиям безопасности.";
        } else {
            $date    = date('Y-m-d');
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ?, last_password_change = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newHash, $date, $_SESSION['user_id']);
            $stmt->execute();

            $message = "Пароль успешно изменен! Перенаправление...";
            header("refresh:2;url=ui.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Смена пароля - Горизонт</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f2ee; display: flex; justify-content: center; align-items: center; min-height: 100vh; }

        .card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 480px;
            border: 1px solid #f0f0f0;
        }

        .logo { font-size: 20px; font-weight: 800; color: #222; margin-bottom: 30px; }

        .subtitle { font-size: 12px; color: #d9534f; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 10px; color: #111; }
        p.desc { color: #777; font-size: 15px; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
            transition: 0.2s;
        }
        .form-control:focus { border-color: #d9534f; outline: none; box-shadow: 0 0 0 3px rgba(217, 83, 79, 0.1); }

        /* Блок требований */
        .requirements {
            background: #f7fcf9;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e6f4ea;
            margin: 25px 0;
        }
        .requirements h4 { color: #1e7e34; margin: 0 0 12px 0; font-size: 15px; font-weight: 600; }
        .requirements ul { list-style: none; padding: 0; margin: 0; }
        .requirements li { font-size: 14px; color: #4a4a4a; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; }
        .requirements li::before { content: "✓"; color: #28a745; font-weight: bold; font-size: 14px; }

        /* Плашка про 3 месяца */
        .warning-box {
            background: #fff9f9;
            border: 1px solid #fce4e4;
            padding: 15px;
            border-radius: 10px;
            font-size: 13px;
            color: #c62828;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn:hover { background: #c9302c; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">Горизонт</div>

    <div class="subtitle">04 / СМЕНА ПАРОЛЯ</div>
    <h1>Обновите пароль</h1>
    <p class="desc">Срок действия текущего пароля истёк.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Текущий пароль</label>
            <input type="password" name="current_password" class="form-control" required placeholder="••••••••••">
        </div>

        <div class="form-group">
            <label>Новый пароль</label>
            <input type="password" name="new_password" class="form-control" required placeholder="••••••••••">
        </div>

        <div class="requirements">
            <h4>Надёжный пароль</h4>
            <ul>
                <li>Минимум 6 символов</li>
                <li>Не менее 1 цифры</li>
                <li>Не менее 1 заглавной латинской буквы</li>
                <li>Не менее 1 строчной латинской буквы</li>
            </ul>
        </div>

        <div class="form-group">
            <label>Повторите новый пароль</label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••••">
        </div>

        <div class="warning-box">
            Смена пароля требуется 1 раз в 3 месяца
        </div>

        <button type="submit" class="btn">Сохранить пароль</button>
    </form>
</div>

</body>
</html>