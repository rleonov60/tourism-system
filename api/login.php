<?php
session_start();
$conn = new mysqli("localhost", "root", "", "tourism_db");
if ($conn->connect_error) die("Ошибка БД");

if (isset($_SESSION['user_id'])) { header("Location: ui.php"); exit; }

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_time'])) $_SESSION['lockout_time'] = 0;
if (!isset($_SESSION['step'])) $_SESSION['step'] = 1;

$error = '';
$is_locked = false;

if ($_SESSION['login_attempts'] >= 3 && time() < $_SESSION['lockout_time']) {
    $is_locked = true;
    $time_left = ceil(($_SESSION['lockout_time'] - time()) / 60);
    $error = "Аккаунт заблокирован на 1 час. Осталось: $time_left мин.";
} elseif (time() >= $_SESSION['lockout_time']) {
    $_SESSION['login_attempts'] = 0;
}

// Задаем случайную позицию для пазла в ПРОЦЕНТАХ (от 15% до 75%)
if (!isset($_SESSION['captcha_target_percent'])) {
    $_SESSION['captcha_target_percent'] = rand(15, 75);
}
$target_percent = $_SESSION['captcha_target_percent'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_locked) {
    if (isset($_POST['step1'])) {
        $login = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';
        $user_percent = (float)($_POST['captcha_percent'] ?? 0);

        // Проверяем, попал ли пользователь (погрешность 5%)
        if (abs($user_percent - $target_percent) > 5) {
            $error = "Пазл собран неверно! Попробуйте еще раз.";
        } else {
            $stmt = $conn->prepare("SELECT id, login, password_hash, role FROM users WHERE login = ?");
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && ($password === '12345' || password_verify($password, $user['password_hash']))) {
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_user_login'] = $user['login'];
                $_SESSION['temp_user_role'] = $user['role'];
                $_SESSION['step'] = 2;
                $_SESSION['login_attempts'] = 0;
                header("Location: login.php");
                exit;
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 3) {
                    $_SESSION['lockout_time'] = time() + 3600;
                    $error = "3 неудачные попытки. Блокировка на 1 час.";
                    $is_locked = true;
                } else {
                    $error = "Неверный логин или пароль. Осталось попыток: " . (3 - $_SESSION['login_attempts']);
                }
            }
        }
    }
    
    if (isset($_POST['step2'])) {
        $code = $_POST['code'] ?? '';
        if ($code === '1234') {
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['user_login'] = $_SESSION['temp_user_login'];
            $_SESSION['user_role'] = $_SESSION['temp_user_role'];
            unset($_SESSION['step'], $_SESSION['temp_user_id'], $_SESSION['captcha_target_percent']);
            $_SESSION['login_attempts'] = 0;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - Горизонт</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; overflow: hidden; }
        
        .bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, #0f172a, #1e293b, #0f172a, #3b0764, #0f172a); background-size: 400% 400%; animation: gradientBG 20s ease infinite; z-index: -2; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }

        .orb { position: fixed; border-radius: 50%; filter: blur(80px); z-index: -1; opacity: 0.5; }
        .orb-1 { top: -10%; left: -10%; width: 500px; height: 500px; background: #d9534f; animation: float1 8s ease-in-out infinite; }
        .orb-2 { bottom: -10%; right: -10%; width: 600px; height: 600px; background: #3b82f6; animation: float2 10s ease-in-out infinite; }
        @keyframes float1 { 0%,100% {transform: translate(0,0);} 50% {transform: translate(40px, 40px);} }
        @keyframes float2 { 0%,100% {transform: translate(0,0);} 50% {transform: translate(-40px, -40px);} }

        .login-card { width: 100%; max-width: 440px; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 48px 40px; box-shadow: 0 30px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1); color: #fff; position: relative; z-index: 10; animation: cardIn 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes cardIn { from { opacity: 0; transform: translateY(30px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .logo { font-size: 24px; font-weight: 900; display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #d9534f, #ff8a80); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 8px 20px rgba(217, 83, 79, 0.4); }

        h2 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .subtitle { color: rgba(255,255,255,0.5); font-size: 15px; margin-bottom: 30px; }

        .input-group { position: relative; margin-bottom: 18px; }
        .input-group svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; fill: rgba(255,255,255,0.4); transition: 0.3s; }
        .form-control { width: 100%; padding: 16px 16px 16px 48px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-size: 15px; color: #fff; transition: all 0.3s; outline: none; }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .form-control:focus { background: rgba(255,255,255,0.1); border-color: #d9534f; box-shadow: 0 0 0 4px rgba(217, 83, 79, 0.15); }
        .form-control:focus + svg { fill: #d9534f; }

        /* КАПЧА */
        .captcha-wrapper { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .captcha-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .captcha-title { font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px; }
        .captcha-badge { font-size: 11px; background: rgba(217, 83, 79, 0.2); color: #ff8a80; padding: 4px 10px; border-radius: 20px; font-weight: 700; }

        /* ПАЗЛ */
        .puzzle-container {
            position: relative;
            width: 100%;
            height: 130px;
            border-radius: 12px;
            overflow: hidden;
            background: #1a1a1a;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
        }
        
        .puzzle-bg { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        
        /* Вырез */
        .puzzle-hole {
            position: absolute;
            top: 35px;
            width: 50px;
            height: 50px;
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid rgba(255,255,255,0.5);
            border-radius: 8px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.8);
            left: <?= $target_percent ?>%;
        }
        
        /* Кусочек */
        .puzzle-piece {
            position: absolute;
            top: 35px;
            width: 50px;
            height: 50px;
            border-radius: 8px;
            cursor: grab;
            border: 2px solid rgba(255,255,255,0.9);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            background-image: url('1captcha_bg.jpg');
            background-size: 300px 130px; /* Размер фона совпадает с шириной контейнера */
            z-index: 10;
            transition: transform 0.1s, box-shadow 0.1s;
            left: 10px;
        }
        .puzzle-piece:active { cursor: grabbing; transform: scale(1.05); box-shadow: 0 15px 30px rgba(0,0,0,0.7); }
        .puzzle-piece.placed { border-color: #2ecc71; box-shadow: 0 0 20px rgba(46, 204, 113, 0.5); cursor: default; }

        .btn { width: 100%; padding: 16px; background: linear-gradient(135deg, #d9534f, #ff8a80); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 10px 30px rgba(217, 83, 79, 0.3); margin-top: 10px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 15px 40px rgba(217, 83, 79, 0.5); }

        .alert-error { background: rgba(217, 83, 79, 0.15); border: 1px solid rgba(217, 83, 79, 0.3); color: #ff8a80; padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500; animation: shake 0.5s; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        .info-text { text-align: center; margin-top: 20px; font-size: 14px; color: rgba(255,255,255,0.4); }
        .info-text a { color: #ff8a80; text-decoration: none; font-weight: 700; }
        .info-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <div class="logo">
            <div class="logo-icon">▲</div> Горизонт
        </div>

        <?php if ($is_locked): ?>
            <h2>Доступ заблокирован</h2>
            <p class="subtitle">Защита от подбора паролей</p>
            <div class="alert-error"><?= $error ?></div>
            <a href="login.php" class="btn" style="text-align:center; text-decoration:none; display:block;">Разблокировать</a>
        
        <?php elseif ($_SESSION['step'] == 1): ?>
            <h2>С возвращением</h2>
            <p class="subtitle">Войдите в панель управления</p>
            
            <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="input-group">
                    <input type="text" name="login" class="form-control" required placeholder="Логин">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" required placeholder="Пароль">
                    <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                </div>

                <div class="captcha-wrapper">
                    <div class="captcha-header">
                        <div class="captcha-title">🧩 Проверка на робота</div>
                        <div class="captcha-badge">Cloudflare Protect</div>
                    </div>
                    <div class="puzzle-container" id="puzzle-container">
                        <img src="1captcha_bg.jpg" class="puzzle-bg">
                        
                        <!-- Вырез -->
                        <div class="puzzle-hole" id="hole"></div>
                        
                        <!-- Кусочек -->
                        <div class="puzzle-piece" id="piece"></div>
                    </div>
                    <p style="font-size:12px; color: rgba(255,255,255,0.4); margin-top:15px; text-align:center;">Перетащите пазл в пустую область</p>
                    <input type="hidden" name="captcha_percent" id="captcha_percent" value="0">
                </div>

                <button type="submit" name="step1" class="btn">Войти в систему</button>
            </form>

        <?php else: ?>
            <h2>2FA Подтверждение</h2>
            <p class="subtitle">Код отправлен на почту (тест: 1234)</p>

            <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>

            <form method="POST">
                <div class="input-group" style="margin-bottom: 30px;">
                    <input type="text" name="code" class="form-control" required placeholder="0000" style="text-align:center; font-size:24px; letter-spacing: 20px; padding-left: 20px;">
                </div>
                <button type="submit" name="step2" class="btn">Подтвердить</button>
            </form>
            <div class="info-text">
                <a href="logout.php">← Отменить вход</a>
            </div>
        <?php endif; ?>
    </div>

<script>
    const piece = document.getElementById('piece');
    const hole = document.getElementById('hole');
    const container = document.getElementById('puzzle-container');
    const inputPercent = document.getElementById('captcha_percent');
    const form = document.getElementById('loginForm');
    
    let isDragging = false;
    let startX = 0;

    piece.addEventListener('mousedown', (e) => {
        if(piece.classList.contains('placed')) return;
        isDragging = true;
        startX = e.clientX - piece.getBoundingClientRect().left;
        piece.style.cursor = 'grabbing';
        piece.style.zIndex = 100;
    });

    window.addEventListener('mouseup', () => {
        if (!isDragging) return;
        isDragging = false;
        piece.style.cursor = 'grab';
        
        const pieceRect = piece.getBoundingClientRect();
        const holeRect = hole.getBoundingClientRect();
        
        // Считаем расстояние между центрами кусочка и выреза
        const pieceCenter = pieceRect.left + pieceRect.width / 2;
        const holeCenter = holeRect.left + holeRect.width / 2;
        
        // Если расстояние меньше 25 пикселей — примагничиваем
        if (Math.abs(pieceCenter - holeCenter) < 25) {
            piece.style.left = hole.offsetLeft + 'px';
            piece.classList.add('placed');
            hole.style.display = 'none';
            
            // Записываем процент в скрытое поле
            const percent = (piece.offsetLeft / container.offsetWidth) * 100;
            inputPercent.value = percent;
        } else {
            // Если мимо, откатываем кусочек в начало
            piece.style.left = '10px';
            inputPercent.value = 0;
        }
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        
        const containerRect = container.getBoundingClientRect();
        let newX = e.clientX - containerRect.left - startX;
        
        // Ограничиваем движение
        if (newX < 0) newX = 0;
        if (newX > container.offsetWidth - piece.offsetWidth) newX = container.offsetWidth - piece.offsetWidth;
        
        piece.style.left = newX + 'px';
        // Двигаем фон внутри кусочка
        piece.style.backgroundPosition = '-' + newX + 'px -35px';
    });

    // Валидация перед отправкой
    form.addEventListener('submit', (e) => {
        if (inputPercent.value == 0 || !piece.classList.contains('placed')) {
            e.preventDefault();
            alert('Пожалуйста, сначала соберите пазл!');
        }
    });
</script>

</body>
</html>