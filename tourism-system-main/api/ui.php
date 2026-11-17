<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$conn = new mysqli("localhost", "admin", "admin", "tourism_db");
if ($conn->connect_error) die("Ошибка БД");

// --- ПРОВЕРКА СРОКА ДЕЙСТВИЯ ПАРОЛЯ ---
$stmt_check = $conn->prepare("SELECT last_password_change FROM users WHERE id = ?");
$userId = $_SESSION['user_id'];
$stmt_check->bind_param("i", $userId);
$stmt_check->execute();
$res_check = $stmt_check->get_result()->fetch_assoc();

if ($res_check && !empty($res_check['last_password_change'])) {
    $last_date = new DateTime($res_check['last_password_change']);
    $today = new DateTime();
    $interval = $last_date->diff($today);
    $months_passed = ($interval->y * 12) + $interval->m;
    
    if ($months_passed >= 3) {
        header("Location: change_password.php");
        exit;
    }
}

// Инициализация всех переменных (чтобы VS Code не ругался)
$page = $_GET['page'] ?? 'dashboard';
$error_message = '';
$success_msg = '';
$import_msg = '';
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$total_tours = 0; $avg_price = 0; $max_days = 0; $tours = null;
$total_clients = 0; $month_clients = 0; $clients = null;
$total_vouchers = 0; $active_vouchers = 0; $total_revenue = 0; $vouchers = null;
$total_sum = 0; $total_count = 0; $avg_payment = 0; $payments = null;
$total_refund_sum = 0; $total_refund_count = 0; $cancelled_vouchers_count = 0; $refunds = null;
$documents = null; $logs = []; $users = null; $profile_data = null; $my_notes = 0;
$all_clients = null; $all_tours = null; $all_vouchers = null;
$dash_clients = 0; $dash_vouchers = 0; $dash_revenue = 0; $dash_tours = 0; $notes = null;

// --- ТУРЫ ---
if ($page == 'tours') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_tour'])) {
        $title = $_POST['title']; $country = $_POST['country']; $price = (float)$_POST['price']; $days = (int)$_POST['days']; $created_at = date('Y-m-d'); $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp']; $filename = $_FILES['image']['name']; $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) { $image_name = uniqid() . '.' . $ext; move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $image_name); }
        }
        $stmt = $conn->prepare("INSERT INTO tours (title, country, price, days, image, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdiss", $title, $country, $price, $days, $image_name, $created_at);
        $stmt->execute(); header("Location: ui.php?page=tours"); exit;
    }
    if (isset($_GET['delete_tour'])) { 
        $id = (int)$_GET['delete_tour']; $res = $conn->query("SELECT image FROM tours WHERE id = $id")->fetch_assoc();
        if ($res && $res['image'] && strpos($res['image'], 'http') !== 0 && file_exists('uploads/' . $res['image'])) unlink('uploads/' . $res['image']);
        $conn->query("DELETE FROM tours WHERE id = $id"); header("Location: ui.php?page=tours"); exit; 
    }
    $total_tours = $conn->query("SELECT COUNT(*) as c FROM tours")->fetch_assoc()['c'];
    $avg_price = $conn->query("SELECT AVG(price) as p FROM tours")->fetch_assoc()['p'] ?? 0;
    $max_days = $conn->query("SELECT MAX(days) as d FROM tours")->fetch_assoc()['d'] ?? 0;
    $tours = $conn->query("SELECT * FROM tours ORDER BY id DESC");
}

// --- КЛИЕНТЫ ---
if ($page == 'clients') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_client'])) {
        $full_name = $_POST['full_name']; $phone = $_POST['phone']; $email = $_POST['email']; $created_at = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO clients (full_name, phone, email, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $full_name, $phone, $email, $created_at);
        $stmt->execute(); header("Location: ui.php?page=clients"); exit;
    }
    if (isset($_GET['delete_client'])) { $conn->query("DELETE FROM clients WHERE id = " . (int)$_GET['delete_client']); header("Location: ui.php?page=clients"); exit; }
    $total_clients = $conn->query("SELECT COUNT(*) as c FROM clients")->fetch_assoc()['c'];
    $month_clients = $conn->query("SELECT COUNT(*) as c FROM clients WHERE MONTH(created_at) = MONTH(CURRENT_DATE())")->fetch_assoc()['c'];
    
    $where = $search ? "WHERE full_name LIKE '%" . $conn->real_escape_string($search) . "%'" : "";
    $clients = $conn->query("SELECT * FROM clients $where ORDER BY id DESC");
}

// --- ПУТЕВКИ ---
if ($page == 'vouchers') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_voucher'])) {
        $client_id = $_POST['client_id']; $tour_id = $_POST['tour_id']; $created_at = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO vouchers (client_id, tour_id, status, created_at) VALUES (?, ?, 'Оформлена', ?)");
        $stmt->bind_param("iis", $client_id, $tour_id, $created_at);
        $stmt->execute(); header("Location: ui.php?page=vouchers"); exit;
    }
    if (isset($_GET['delete_voucher'])) { $conn->query("DELETE FROM vouchers WHERE id = " . (int)$_GET['delete_voucher']); header("Location: ui.php?page=vouchers"); exit; }
    $total_vouchers = $conn->query("SELECT COUNT(*) as c FROM vouchers")->fetch_assoc()['c'];
    $active_vouchers = $conn->query("SELECT COUNT(*) as c FROM vouchers WHERE status = 'Оформлена'")->fetch_assoc()['c'];
    $total_revenue = $conn->query("SELECT SUM(t.price) as s FROM vouchers v JOIN tours t ON t.id = v.tour_id WHERE v.status = 'Оформлена'")->fetch_assoc()['s'] ?? 0;
    
    $where = $filter_status ? "WHERE v.status = '" . $conn->real_escape_string($filter_status) . "'" : "";
    $vouchers = $conn->query("SELECT v.*, c.full_name, t.title, t.price FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id $where ORDER BY v.id DESC");
    
    $all_clients = $conn->query("SELECT * FROM clients");
    $all_tours = $conn->query("SELECT * FROM tours");
}

// --- ПЛАТЕЖИ ---
if ($page == 'payments') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
        $voucher_id = $_POST['voucher_id']; $amount = $_POST['amount']; $created_at = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO payments (voucher_id, amount, status, created_at) VALUES (?, ?, 'Оплачено', ?)");
        $stmt->bind_param("ids", $voucher_id, $amount, $created_at);
        $stmt->execute(); header("Location: ui.php?page=payments"); exit;
    }
    $total_sum = $conn->query("SELECT SUM(amount) as s FROM payments")->fetch_assoc()['s'] ?? 0;
    $total_count = $conn->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'];
    $avg_payment = $conn->query("SELECT AVG(amount) as a FROM payments")->fetch_assoc()['a'] ?? 0;
    $payments = $conn->query("SELECT p.*, v.id as vid, c.full_name, t.title FROM payments p JOIN vouchers v ON v.id = p.voucher_id JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id ORDER BY p.id DESC");
    $all_vouchers = $conn->query("SELECT v.id, c.full_name, t.title FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id");
}

// --- БРОНИРОВАНИЕ ---
if ($page == 'bookings') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_booking'])) {
        $voucher_id = $_POST['voucher_id']; $booking_date = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO bookings (voucher_id, booking_date, status) VALUES (?, ?, 'Подтверждено')");
        $stmt->bind_param("is", $voucher_id, $booking_date);
        $stmt->execute(); header("Location: ui.php?page=bookings"); exit;
    }
    if (isset($_GET['delete_booking'])) { $conn->query("DELETE FROM bookings WHERE id = " . (int)$_GET['delete_booking']); header("Location: ui.php?page=bookings"); exit; }
    $bookings = $conn->query("SELECT b.*, c.full_name, t.title FROM bookings b JOIN vouchers v ON v.id = b.voucher_id JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id ORDER BY b.id DESC");
    $all_vouchers = $conn->query("SELECT v.id, c.full_name, t.title FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id");
}

// --- ОТМЕНА И ВОЗВРАТ ---
if ($page == 'refunds') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_refund'])) {
        $voucher_id = $_POST['voucher_id']; $amount = $_POST['amount']; $reason = $_POST['reason']; $refund_date = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO refunds (voucher_id, refund_amount, reason, refund_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $voucher_id, $amount, $reason, $refund_date);
        $stmt->execute();
        $conn->query("UPDATE vouchers SET status = 'Отменена' WHERE id = " . (int)$voucher_id);
        header("Location: ui.php?page=refunds"); exit;
    }
    $total_refund_sum = $conn->query("SELECT SUM(refund_amount) as s FROM refunds")->fetch_assoc()['s'] ?? 0;
    $total_refund_count = $conn->query("SELECT COUNT(*) as c FROM refunds")->fetch_assoc()['c'];
    $cancelled_vouchers_count = $conn->query("SELECT COUNT(*) as c FROM vouchers WHERE status = 'Отменена'")->fetch_assoc()['c'];
    $refunds = $conn->query("SELECT r.*, c.full_name, t.title FROM refunds r JOIN vouchers v ON v.id = r.voucher_id JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id ORDER BY r.id DESC");
    $all_vouchers = $conn->query("SELECT v.id, c.full_name, t.title FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id WHERE v.status = 'Оформлена'");
}

// --- ДОКУМЕНТЫ ---
if ($page == 'docs') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_doc'])) {
        $voucher_id = $_POST['voucher_id']; $doc_type = $_POST['doc_type']; $created_at = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO documents (voucher_id, doc_type, created_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $voucher_id, $doc_type, $created_at);
        $stmt->execute(); header("Location: ui.php?page=docs"); exit;
    }
    $documents = $conn->query("SELECT d.*, c.full_name, t.title FROM documents d JOIN vouchers v ON v.id = d.voucher_id JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id ORDER BY d.id DESC");
    $all_vouchers = $conn->query("SELECT v.id, c.full_name, t.title FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id");
}

// --- ИМПОРТ И ЭКСПОРТ ---
if ($page == 'import_export') {
    if (isset($_GET['export_json'])) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="tours_export.json"');
        $tours_data = $conn->query("SELECT * FROM tours")->fetch_all(MYSQLI_ASSOC);
        echo json_encode($tours_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['json_file'])) {
        $json = file_get_contents($_FILES['json_file']['tmp_name']);
        $data = json_decode($json, true);
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $tour) {
                $t_title = $tour['title']; $t_country = $tour['country']; $t_price = $tour['price']; $t_days = $tour['days']; $t_date = $tour['created_at'] ?? date('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO tours (title, country, price, days, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdis", $t_title, $t_country, $t_price, $t_days, $t_date);
                $stmt->execute();
                $count++;
            }
            $import_msg = "Успешно импортировано $count туров!";
        } else {
            $import_msg = "Ошибка: неверный формат JSON.";
        }
    }
}

// --- АДМИНИСТРИРОВАНИЕ ---
if ($page == 'admin') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
        $login = $_POST['login']; $role = $_POST['role'];
        $hash = password_hash('12345', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (login, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $login, $hash, $role);
        $stmt->execute(); header("Location: ui.php?page=admin"); exit;
    }
    if (isset($_GET['delete_user'])) { 
        $uid = (int)$_GET['delete_user'];
        $check_role = $conn->query("SELECT role FROM users WHERE id = $uid")->fetch_assoc();
        if ($check_role['role'] !== 'admin') { $conn->query("DELETE FROM users WHERE id = $uid"); }
        header("Location: ui.php?page=admin"); exit; 
    }
    $users = $conn->query("SELECT * FROM users ORDER BY id ASC");
}

// --- ЛОГИ ---
if ($page == 'logs') {
    $logs = [];
    $res = $conn->query("SELECT n.title as name, n.created_at, u.login as author FROM notes n JOIN users u ON u.id = n.id_user");
    while($r = $res->fetch_assoc()) $logs[] = ['type' => 'Заметка', 'name' => $r['name'], 'date' => $r['created_at'], 'author' => $r['author']];
    $res = $conn->query("SELECT title as name, created_at FROM tours");
    while($r = $res->fetch_assoc()) $logs[] = ['type' => 'Тур', 'name' => $r['name'], 'date' => $r['created_at'], 'author' => 'Система'];
    $res = $conn->query("SELECT CONCAT(c.full_name, ' - ', t.title) as name, v.created_at, u.login as author FROM vouchers v JOIN clients c ON c.id = v.client_id JOIN tours t ON t.id = v.tour_id JOIN users u ON u.id = 1");
    while($r = $res->fetch_assoc()) $logs[] = ['type' => 'Путёвка', 'name' => $r['name'], 'date' => $r['created_at'], 'author' => $r['author']];
    $res = $conn->query("SELECT CONCAT('Оплата ', p.amount, ' ₽') as name, p.created_at FROM payments p");
    while($r = $res->fetch_assoc()) $logs[] = ['type' => 'Платеж', 'name' => $r['name'], 'date' => $r['created_at'], 'author' => 'Система'];
    usort($logs, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
}

// --- ПРОФИЛЬ ---
if ($page == 'profile') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
        $login = $_POST['login'];
        $stmt = $conn->prepare("UPDATE users SET login = ? WHERE id = ?");
        $stmt->bind_param("si", $login, $userId);
        if ($stmt->execute()) { $_SESSION['user_login'] = $login; $success_msg = "Логин обновлен!"; }
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'];
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $today = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, last_password_change = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_hash, $today, $userId);
        if ($stmt->execute()) { $success_msg = "Пароль изменен!"; } else { $error_message = "Ошибка"; }
    }
    $stmt = $conn->prepare("SELECT login, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId); $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();
    $my_notes = $conn->query("SELECT COUNT(*) as c FROM notes WHERE id_user = " . (int)$userId)->fetch_assoc()['c'];
}

// --- ДАШБОРД ---
if ($page == 'dashboard') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_note'])) {
        $title = $_POST['title']; $content = $_POST['content']; $created_at = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO notes (title, content, id_user, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $title, $content, $userId, $created_at);
        $stmt->execute(); header("Location: ui.php?page=dashboard"); exit;
    }
    if (isset($_GET['delete'])) { $conn->query("DELETE FROM notes WHERE id = " . (int)$_GET['delete']); header("Location: ui.php?page=dashboard"); exit; }
    
    $dash_clients = $conn->query("SELECT COUNT(*) as c FROM clients")->fetch_assoc()['c'];
    $dash_vouchers = $conn->query("SELECT COUNT(*) as c FROM vouchers")->fetch_assoc()['c'];
    $dash_revenue = $conn->query("SELECT SUM(amount) as s FROM payments")->fetch_assoc()['s'] ?? 0;
    $dash_tours = $conn->query("SELECT COUNT(*) as c FROM tours")->fetch_assoc()['c'];
    $notes = $conn->query("SELECT n.id, n.title, n.content, u.login, n.created_at FROM notes n JOIN users u ON u.id = n.id_user ORDER BY n.id DESC LIMIT 5");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Горизонт - Панель управления</title>
    <style>
        :root { --bg-body: #f9f9f9; --bg-sidebar: #ffffff; --bg-card: #ffffff; --text-main: #333333; --text-title: #222222; --text-muted: #888888; --border-color: #eaeaea; --input-bg: #ffffff; --input-border: #dddddd; --hover-bg: #f5f5f5; --table-hover: #fafafa; --badge-bg: #f0f0f0; --badge-text: #555555; }
        body.dark-theme { --bg-body: #121212; --bg-sidebar: #1e1e1e; --bg-card: #1e1e1e; --text-main: #e0e0e0; --text-title: #ffffff; --text-muted: #aaaaaa; --border-color: #333333; --input-bg: #2c2c2c; --input-border: #444444; --hover-bg: #2c2c2c; --table-hover: #2a2a2a; --badge-bg: #333333; --badge-text: #cccccc; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; transition: 0.3s; }
        
        .sidebar { width: 260px; background-color: var(--bg-sidebar); border-right: 1px solid var(--border-color); padding: 20px 0; display: flex; flex-direction: column; }
        .logo { padding: 0 20px 30px 20px; font-size: 22px; font-weight: 900; color: var(--text-title); display: flex; align-items: center; gap: 10px; }
        .logo-icon { width: 32px; height: 32px; background: linear-gradient(135deg, #d9534f 0%, #ff8a80 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; }
        .menu-section { padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-top: 10px; }
        .menu-item { padding: 12px 20px; color: var(--text-main); text-decoration: none; display: block; font-weight: 500; transition: 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: rgba(217, 83, 79, 0.1); color: #d9534f; border-right: 3px solid #d9534f; }
        
        .sidebar-footer { margin-top: auto; padding: 20px; border-top: 1px solid var(--border-color); font-size: 13px; }
        .sidebar-footer p { color: var(--text-muted); margin-bottom: 10px; font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .contact-item { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--text-main); text-decoration: none; font-weight: 600; }
        .contact-icon { width: 28px; height: 28px; background: var(--hover-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        
        .main-content { flex: 1; padding: 30px; max-width: 1400px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; font-weight: 700; color: var(--text-title); margin-bottom: 5px; }
        .header p { color: var(--text-muted); font-size: 14px; }
        
        .theme-toggle { background: var(--bg-card); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; margin-right: 10px; }
        .top-profile { display: flex; align-items: center; gap: 15px; background: var(--bg-card); padding: 8px 15px; border-radius: 30px; border: 1px solid var(--border-color); }
        .top-profile-avatar { width: 36px; height: 36px; background: var(--badge-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #d9534f; font-size: 16px; }
        .top-profile-info b { font-size: 14px; color: var(--text-title); } .top-profile-info span { font-size: 11px; color: var(--text-muted); }
        .top-profile-logout { margin-left: 10px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 600; }

        .card { background: var(--bg-card); border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; border: 1px solid var(--border-color); }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: var(--text-title); display: flex; justify-content: space-between; align-items: center; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(217, 83, 79, 0.1); color: #d9534f; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .stat-title { font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 24px; font-weight: 700; color: var(--text-title); }
        .stat-value span { font-size: 14px; color: var(--text-muted); font-weight: 500; }

        .quick-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .action-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; text-align: center; text-decoration: none; color: var(--text-main); transition: 0.2s; display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .action-card:hover { border-color: #d9534f; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(217,83,79,0.1); }
        .action-icon { width: 60px; height: 60px; border-radius: 50%; background: rgba(217, 83, 79, 0.1); color: #d9534f; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .action-title { font-weight: 700; font-size: 16px; color: var(--text-title); }

        .tours-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .tour-card { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .tour-image { width: 100%; height: 200px; object-fit: cover; background: var(--hover-bg); border-bottom: 1px solid var(--border-color); }
        .no-image { width: 100%; height: 200px; background: var(--hover-bg); display: flex; align-items: center; justify-content: center; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
        .tour-content { padding: 20px; display: flex; flex-direction: column; flex: 1; }
        .tour-title { font-size: 18px; font-weight: 700; color: var(--text-title); margin-bottom: 5px; }
        .tour-country { font-size: 13px; color: var(--text-muted); margin-bottom: 15px; text-transform: uppercase; }
        .tour-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid var(--border-color); }
        .tour-price { font-size: 20px; font-weight: 800; color: #d9534f; }

        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #d9534f; color: #fff; } .btn-primary:hover { background-color: #c9302c; }
        .btn-danger { background-color: rgba(217, 83, 79, 0.1); color: #d9534f; padding: 6px 12px; font-size: 12px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-main); margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--input-border); border-radius: 8px; font-size: 14px; background: var(--input-bg); color: var(--text-main); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 12px; font-size: 12px; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px 12px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-main); }
        tr:hover td { background-color: var(--table-hover); }
        .client-avatar { width: 32px; height: 32px; background: rgba(46, 125, 50, 0.1); color: #2e7d32; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; margin-right: 10px; float: left; }

        .log-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .log-note { background: rgba(25, 118, 210, 0.1); color: #1976d2; }
        .log-tour { background: rgba(46, 125, 50, 0.1); color: #2e7d32; }
        .log-voucher { background: rgba(156, 39, 176, 0.1); color: #9c27b0; }
        .log-payment { background: rgba(255, 193, 7, 0.1); color: #ffc107; }

        .notes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .note-card { background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.02); position: relative; transition: 0.2s; display: flex; flex-direction: column; }
        .note-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .note-title { font-size: 16px; font-weight: 700; color: var(--text-title); margin-bottom: 10px; }
        .note-content { font-size: 14px; color: var(--text-main); margin-bottom: 20px; line-height: 1.5; flex: 1; }
        .note-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 10px; font-size: 12px; color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><div class="logo-icon">▲</div>Горизонт</div>
        <div class="menu-section">Панель управления</div>
        <a href="ui.php?page=dashboard" class="menu-item <?= $page == 'dashboard' ? 'active' : '' ?>">Дашборд</a>
        <div class="menu-section">Основное</div>
        <a href="ui.php?page=tours" class="menu-item <?= $page == 'tours' ? 'active' : '' ?>">Туры</a>
        <a href="ui.php?page=clients" class="menu-item <?= $page == 'clients' ? 'active' : '' ?>">Клиенты</a>
        <a href="ui.php?page=bookings" class="menu-item <?= $page == 'bookings' ? 'active' : '' ?>">Бронирование</a>
        <a href="ui.php?page=vouchers" class="menu-item <?= $page == 'vouchers' ? 'active' : '' ?>">Путёвки</a>
        <a href="ui.php?page=payments" class="menu-item <?= $page == 'payments' ? 'active' : '' ?>">Платежи</a>
        <a href="ui.php?page=refunds" class="menu-item <?= $page == 'refunds' ? 'active' : '' ?>">Отмена и возврат</a>
        <a href="ui.php?page=docs" class="menu-item <?= $page == 'docs' ? 'active' : '' ?>">Документы</a>
        <div class="menu-section">Система</div>
        <a href="ui.php?page=import_export" class="menu-item <?= $page == 'import_export' ? 'active' : '' ?>">Импорт и экспорт</a>
        <a href="ui.php?page=logs" class="menu-item <?= $page == 'logs' ? 'active' : '' ?>">Логи и восстановление</a>
        <a href="ui.php?page=admin" class="menu-item <?= $page == 'admin' ? 'active' : '' ?>">Администрирование</a>

        <div class="sidebar-footer">
            <p>Служба поддержки</p>
            <a href="tel:88005553535" class="contact-item"><div class="contact-icon">📞</div><div>8 (800) 555-35-35</div></a>
            <a href="mailto:support@gorizont.ru" class="contact-item"><div class="contact-icon">✉️</div><div>support@gorizont.ru</div></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1><?php 
                    if($page == 'dashboard') echo 'Рабочая панель';
                    elseif($page == 'tours') echo 'Управление турами';
                    elseif($page == 'clients') echo 'База клиентов';
                    elseif($page == 'bookings') echo 'Бронирование';
                    elseif($page == 'vouchers') echo 'Оформленные путёвки';
                    elseif($page == 'payments') echo 'Платежи';
                    elseif($page == 'refunds') echo 'Отмена и возврат';
                    elseif($page == 'docs') echo 'Документы';
                    elseif($page == 'import_export') echo 'Импорт и экспорт';
                    elseif($page == 'logs') echo 'Системные логи';
                    elseif($page == 'admin') echo 'Администрирование';
                    else echo 'Профиль';
                ?></h1>
                <p>Обзор актуальных задач и записей</p>
            </div>
            <div style="display: flex; align-items: center;">
                <div class="theme-toggle" id="theme-btn" onclick="toggleTheme()">🌙</div>
                <div class="top-profile">
                    <div class="top-profile-avatar"><?= mb_substr($_SESSION['user_login'], 0, 1) ?></div>
                    <div class="top-profile-info"><b><?= htmlspecialchars($_SESSION['user_login']) ?></b><span><?= htmlspecialchars($_SESSION['user_role']) ?></span></div>
                    <a href="ui.php?page=profile" style="margin-left: 15px; color: var(--text-main); text-decoration: none; font-size: 13px; font-weight: 600;">Профиль</a>
                    <a href="logout.php" class="top-profile-logout">Выйти</a>
                </div>
            </div>
        </div>

        <?php if ($page == 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: rgba(46, 125, 50, 0.1); color: #2e7d32;">💰</div><div><div class="stat-title">Выручка</div><div class="stat-value"><?= number_format($dash_revenue, 0, '', ' ') ?> <span>₽</span></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: #1976d2;">👥</div><div><div class="stat-title">Клиентов</div><div class="stat-value"><?= $dash_clients ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">🎫</div><div><div class="stat-title">Путёвок</div><div class="stat-value"><?= $dash_vouchers ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">🗺️</div><div><div class="stat-title">Туров</div><div class="stat-value"><?= $dash_tours ?></div></div></div>
            </div>

            <div class="quick-actions">
                <a href="ui.php?page=tours" class="action-card"><div class="action-icon">🗺️</div><div class="action-title">Создать тур</div><div style="font-size: 13px; color: var(--text-muted);">Добавить новый турпродукт</div></a>
                <a href="ui.php?page=clients" class="action-card"><div class="action-icon">👥</div><div class="action-title">Добавить клиента</div><div style="font-size: 13px; color: var(--text-muted);">Зарегистрировать нового клиента</div></a>
                <a href="ui.php?page=vouchers" class="action-card"><div class="action-icon">🎫</div><div class="action-title">Оформить путёвку</div><div style="font-size: 13px; color: var(--text-muted);">Продать тур клиенту</div></a>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
                <div>
                    <div class="card-title" style="margin-bottom: 20px;">Последние заметки</div>
                    <div class="notes-grid">
                        <?php if($notes): while($r = $notes->fetch_assoc()): ?>
                            <div class="note-card">
                                <div class="note-title"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="note-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
                                <div class="note-footer"><span><?= $r['login'] ?> • <?= date('d.m.Y', strtotime($r['created_at'])) ?></span><a href="ui.php?page=dashboard&delete=<?= $r['id'] ?>" style="color: #d9534f; text-decoration: none; font-weight: 600;" onclick="return confirm('Удалить?')">Удалить</a></div>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                </div>
                <div class="card" style="align-self: start;">
                    <div class="card-title">Новая заметка</div>
                    <form method="POST" action="ui.php?page=dashboard">
                        <div class="form-group"><label>Заголовок</label><input type="text" name="title" class="form-control" required></div>
                        <div class="form-group"><label>Содержание</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
                        <button type="submit" name="add_note" class="btn btn-primary" style="width: 100%;">Добавить заметку</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page == 'tours'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">🗺️</div><div><div class="stat-title">Всего туров</div><div class="stat-value"><?= $total_tours ?></div></div></div>
                <div class="stat-card"><div class="stat-icon">💰</div><div><div class="stat-title">Средняя цена</div><div class="stat-value"><?= number_format($avg_price, 0, '', ' ') ?> <span>₽</span></div></div></div>
                <div class="stat-card"><div class="stat-icon">⏱️</div><div><div class="stat-title">Макс. дней</div><div class="stat-value"><?= $max_days ?> <span>дн.</span></div></div></div>
            </div>
            <div class="card">
                <div class="card-title">Добавить тур</div>
                <form method="POST" action="ui.php?page=tours" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label>Название</label><input type="text" name="title" class="form-control" required></div>
                        <div class="form-group"><label>Страна</label><input type="text" name="country" class="form-control" required></div>
                        <div class="form-group"><label>Цена</label><input type="number" name="price" class="form-control" required></div>
                        <div class="form-group"><label>Дней</label><input type="number" name="days" class="form-control" required></div>
                    </div>
                    <div class="form-group"><input type="file" name="image" class="form-control" accept="image/*"></div>
                    <button type="submit" name="add_tour" class="btn btn-primary">Добавить</button>
                </form>
            </div>
            <div class="tours-grid">
                <?php if($tours): while($r = $tours->fetch_assoc()): ?>
                    <div class="tour-card">
                        <?php if(!empty($r['image'])): $img_src = (strpos($r['image'], 'http') === 0) ? $r['image'] : 'uploads/' . $r['image']; ?>
                            <img src="<?= $img_src ?>" class="tour-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="no-image" style="display:none;">Нет фото</div>
                        <?php else: ?><div class="no-image">Нет фото</div><?php endif; ?>
                        <div class="tour-content">
                            <div class="tour-country"><?= htmlspecialchars($r['country']) ?></div>
                            <div class="tour-title"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="tour-footer"><div class="tour-price"><?= number_format($r['price'],0,'',' ') ?> ₽</div><div style="font-size:12px; color:var(--text-muted);"><?= $r['days'] ?> дней</div></div>
                            <a href="ui.php?page=tours&delete_tour=<?= $r['id'] ?>" class="btn btn-danger" style="width:100%; text-align:center; margin-top:15px; box-sizing:border-box;" onclick="return confirm('Удалить?')">Удалить</a>
                        </div>
                    </div>
                <?php endwhile; endif; ?>
            </div>

        <?php elseif ($page == 'clients'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">👥</div><div><div class="stat-title">Всего</div><div class="stat-value"><?= $total_clients ?></div></div></div>
                <div class="stat-card"><div class="stat-icon">✨</div><div><div class="stat-title">За месяц</div><div class="stat-value" style="color:#2e7d32;">+<?= $month_clients ?></div></div></div>
                <div class="stat-card"><div class="stat-icon">🤝</div><div><div class="stat-title">Активных</div><div class="stat-value">0</div></div></div>
            </div>
            <div class="card">
                <form method="POST" action="ui.php?page=clients">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom:0;"><label>ФИО</label><input type="text" name="full_name" class="form-control" required></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Телефон</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    </div>
                    <button type="submit" name="add_client" class="btn btn-primary" style="margin-top:15px;">Добавить</button>
                </form>
            </div>
            
            <div class="card">
                <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <input type="hidden" name="page" value="clients">
                    <input type="text" name="search" class="form-control" placeholder="Поиск по ФИО..." value="<?= htmlspecialchars($search) ?>" style="max-width: 300px;">
                    <button type="submit" class="btn btn-primary">Найти</button>
                </form>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Телефон</th><th>Email</th><th></th></tr></thead>
                    <tbody>
                        <?php if($clients): while($r = $clients->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><div style="display:flex; align-items:center;"><div class="client-avatar"><?= mb_substr($r['full_name'], 0, 1) ?></div><b><?= htmlspecialchars($r['full_name']) ?></b></div></td>
                                <td><?= htmlspecialchars($r['phone']) ?></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><a href="ui.php?page=clients&delete_client=<?= $r['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">Удалить</a></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'bookings'): ?>
            <div class="card">
                <div class="card-title">Новое бронирование</div>
                <form method="POST" action="ui.php?page=bookings">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;"><label>Выберите путёвку</label><select name="voucher_id" class="form-control"><?php if($all_vouchers) while($v = $all_vouchers->fetch_assoc()) echo "<option value='{$v['id']}'>{$v['full_name']} - {$v['title']}</option>"; ?></select></div>
                        <button type="submit" name="add_booking" class="btn btn-primary" style="height:42px;">Забронировать</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Список бронирований</div>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Тур</th><th>Дата</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                        <?php if($bookings): while($r = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['full_name']) ?></b></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= date('d.m.Y', strtotime($r['booking_date'])) ?></td>
                                <td><span style="color:green;"><?= $r['status'] ?></span></td>
                                <td><a href="ui.php?page=bookings&delete_booking=<?= $r['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">Удалить</a></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'vouchers'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">🎫</div><div><div class="stat-title">Всего</div><div class="stat-value"><?= $total_vouchers ?></div></div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div><div class="stat-title">Активные</div><div class="stat-value" style="color:#2e7d32;"><?= $active_vouchers ?></div></div></div>
                <div class="stat-card"><div class="stat-icon">💵</div><div><div class="stat-title">Сумма</div><div class="stat-value"><?= number_format($total_revenue, 0, '', ' ') ?> <span>₽</span></div></div></div>
            </div>
            <div class="card">
                <div class="card-title">Оформить путёвку</div>
                <form method="POST" action="ui.php?page=vouchers">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Клиент</label><select name="client_id" class="form-control"><?php if($all_clients) while($c = $all_clients->fetch_assoc()) echo "<option value='{$c['id']}'>{$c['full_name']}</option>"; ?></select></div>
                        <div class="form-group"><label>Тур</label><select name="tour_id" class="form-control"><?php if($all_tours) while($t = $all_tours->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['title']} (" . number_format($t['price'],0,'',' ') . " ₽)</option>"; ?></select></div>
                    </div>
                    <button type="submit" name="add_voucher" class="btn btn-primary">Оформить</button>
                </form>
            </div>
            
            <div class="card">
                <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <input type="hidden" name="page" value="vouchers">
                    <select name="status" class="form-control" style="max-width: 200px;">
                        <option value="">Все статусы</option>
                        <option value="Оформлена" <?= $filter_status == 'Оформлена' ? 'selected' : '' ?>>Оформлена</option>
                        <option value="Отменена" <?= $filter_status == 'Отменена' ? 'selected' : '' ?>>Отменена</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Фильтровать</button>
                </form>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Тур</th><th>Стоимость</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                        <?php if($vouchers): while($r = $vouchers->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['full_name']) ?></b></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><b><?= number_format($r['price'],0,'',' ') ?> ₽</b></td>
                                <td><span style="background:rgba(46,125,50,0.1); color:#2e7d32; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?= $r['status'] ?></span></td>
                                <td><a href="ui.php?page=vouchers&delete_voucher=<?= $r['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">Удалить</a></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'payments'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: rgba(46,125,50,0.1); color: #2e7d32;">💰</div><div><div class="stat-title">Общая сумма</div><div class="stat-value"><?= number_format($total_sum, 0, '', ' ') ?> <span>₽</span></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(25,118,210,0.1); color: #1976d2;">📊</div><div><div class="stat-title">Платежей</div><div class="stat-value"><?= $total_count ?> <span>шт</span></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(217,83,79,0.1); color: #d9534f;">📈</div><div><div class="stat-title">Средний чек</div><div class="stat-value"><?= number_format($avg_payment, 0, '', ' ') ?> <span>₽</span></div></div></div>
            </div>
            <div class="card">
                <div class="card-title">Внести платеж</div>
                <form method="POST" action="ui.php?page=payments">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;"><label>Путёвка</label><select name="voucher_id" class="form-control"><?php if($all_vouchers) while($v = $all_vouchers->fetch_assoc()) echo "<option value='{$v['id']}'>{$v['full_name']} - {$v['title']}</option>"; ?></select></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Сумма (₽)</label><input type="number" name="amount" class="form-control" required></div>
                        <button type="submit" name="add_payment" class="btn btn-primary" style="height:42px;">Оплатить</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">История</div>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Тур</th><th>Сумма</th><th>Дата</th></tr></thead>
                    <tbody>
                        <?php if($payments): while($r = $payments->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['full_name']) ?></b></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><b style="color:#2e7d32;"><?= number_format($r['amount'],0,'',' ') ?> ₽</b></td>
                                <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'refunds'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: rgba(217, 83, 79, 0.1); color: #d9534f;">📉</div><div><div class="stat-title">Сумма возвратов</div><div class="stat-value"><?= number_format($total_refund_sum, 0, '', ' ') ?> <span>₽</span></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: #1976d2;">↩️</div><div><div class="stat-title">Оформлено возвратов</div><div class="stat-value"><?= $total_refund_count ?> <span>шт</span></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">⚠️</div><div><div class="stat-title">Отменено путёвок</div><div class="stat-value"><?= $cancelled_vouchers_count ?></div></div></div>
            </div>
            <div class="card">
                <div class="card-title">Оформить возврат</div>
                <form method="POST" action="ui.php?page=refunds">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr 1fr; gap: 15px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;"><label>Путёвка</label><select name="voucher_id" class="form-control"><?php if($all_vouchers) while($v = $all_vouchers->fetch_assoc()) echo "<option value='{$v['id']}'>{$v['full_name']} - {$v['title']}</option>"; ?></select></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Сумма (₽)</label><input type="number" name="amount" class="form-control" required></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Причина</label><input type="text" name="reason" class="form-control" required></div>
                        <button type="submit" name="add_refund" class="btn btn-primary" style="height:42px;">Вернуть</button>
                    </div>
                </form>
            </div>
            <div class="card" style="background: rgba(217, 83, 79, 0.05); border-color: rgba(217, 83, 79, 0.2);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 24px;">💡</div>
                    <div><div style="font-weight: 600; color: #d9534f; margin-bottom: 5px;">Правила возврата</div><div style="font-size: 13px; color: var(--text-muted);">При отмене путёвки статус автоматически меняется на «Отменена».</div></div>
                </div>
            </div>
            <div class="card">
                <div class="card-title">История возвратов</div>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Тур</th><th>Сумма</th><th>Причина</th><th>Дата</th></tr></thead>
                    <tbody>
                        <?php if($refunds): while($r = $refunds->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['full_name']) ?></b></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><b style="color:#d9534f;"><?= number_format($r['refund_amount'],0,'',' ') ?> ₽</b></td>
                                <td><?= htmlspecialchars($r['reason']) ?></td>
                                <td><?= date('d.m.Y', strtotime($r['refund_date'])) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'docs'): ?>
            <div class="card">
                <div class="card-title">Сформировать документ</div>
                <form method="POST" action="ui.php?page=docs">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;"><label>Выберите путёвку</label><select name="voucher_id" class="form-control"><?php if($all_vouchers) while($v = $all_vouchers->fetch_assoc()) echo "<option value='{$v['id']}'>{$v['full_name']} - {$v['title']}</option>"; ?></select></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Тип документа</label><select name="doc_type" class="form-control"><option value="Договор">Договор</option><option value="Ваучер">Ваучер</option></select></div>
                        <button type="submit" name="add_doc" class="btn btn-primary" style="height:42px;">Создать</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Список документов</div>
                <table>
                    <thead><tr><th>ID</th><th>Клиент</th><th>Тур</th><th>Тип документа</th><th>Дата</th><th>Действие</th></tr></thead>
                    <tbody>
                        <?php if($documents): while($r = $documents->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['full_name']) ?></b></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><span style="background:rgba(25,118,210,0.1); color:#1976d2; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?= $r['doc_type'] ?></span></td>
                                <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                                <td><a href="print_contract.php?voucher_id=<?= $r['voucher_id'] ?>" target="_blank" style="color:#d9534f; text-decoration:none; font-weight:600;">Открыть документ</a></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'import_export'): ?>
            <div class="card">
                <div class="card-title">Экспорт данных</div>
                <p style="color:var(--text-muted); margin-bottom:15px;">Вы можете скачать все туры в формате JSON для резервного копирования.</p>
                <a href="ui.php?page=import_export&export_json=1" class="btn btn-primary">Скачать JSON (Туры)</a>
            </div>
            <div class="card">
                <div class="card-title">Импорт данных</div>
                <p style="color:var(--text-muted); margin-bottom:15px;">Загрузите файл JSON для восстановления базы.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="json_file" accept=".json" class="form-control" style="margin-bottom:15px;" required>
                    <button type="submit" class="btn btn-primary">Загрузить файл</button>
                </form>
                <?php if($import_msg): ?>
                    <div style="margin-top: 15px; padding: 10px; border-radius: 8px; background: rgba(46,125,50,0.1); color: #2e7d32; font-weight: 600;">
                        <?= $import_msg ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($page == 'logs'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: #1976d2;">📝</div><div><div class="stat-title">Всего событий</div><div class="stat-value"><?= count($logs) ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(46, 125, 50, 0.1); color: #2e7d32;">✅</div><div><div class="stat-title">Успешных операций</div><div class="stat-value"><?= count($logs) ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">⏱️</div><div><div class="stat-title">Последнее событие</div><div class="stat-value" style="font-size: 16px;"><?= count($logs) > 0 ? date('d.m.Y', strtotime($logs[0]['date'])) : 'Нет' ?></div></div></div>
            </div>
            <div class="card">
                <div class="card-title">Журнал действий</div>
                <table>
                    <thead><tr><th>Тип</th><th>Действие</th><th>Автор</th><th>Дата</th></tr></thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php $badge_class = 'log-note';
                                    if($log['type'] == 'Тур') $badge_class = 'log-tour';
                                    elseif($log['type'] == 'Путёвка') $badge_class = 'log-voucher';
                                    elseif($log['type'] == 'Платеж') $badge_class = 'log-payment'; ?>
                                    <span class="log-badge <?= $badge_class ?>"><?= $log['type'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($log['name']) ?></td>
                                <td><?= htmlspecialchars($log['author']) ?></td>
                                <td><?= date('d.m.Y', strtotime($log['date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($logs) == 0): ?><tr><td colspan="4" style="text-align:center;">Событий пока нет</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'admin'): ?>
            <div class="card">
                <div class="card-title">Добавить пользователя</div>
                <form method="POST" action="ui.php?page=admin">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;"><label>Логин</label><input type="text" name="login" class="form-control" required></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Роль</label><select name="role" class="form-control"><option value="manager">Менеджер</option><option value="admin">Администратор</option></select></div>
                        <button type="submit" name="add_user" class="btn btn-primary" style="height:42px;">Создать</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Пользователи системы</div>
                <table>
                    <thead><tr><th>ID</th><th>Логин</th><th>Роль</th><th>Пароль</th><th>Действие</th></tr></thead>
                    <tbody>
                        <?php if($users): while($r = $users->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><b><?= htmlspecialchars($r['login']) ?></b></td>
                                <td><span style="background:rgba(25,118,210,0.1); color:#1976d2; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?= $r['role'] ?></span></td>
                                <td><i style="color:#888;">Хэширован</i></td>
                                <td>
                                    <?php if($r['role'] !== 'admin'): ?>
                                        <a href="ui.php?page=admin&delete_user=<?= $r['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
                                    <?php else: ?>
                                        <span style="color:#888; font-size:12px;">Защищён</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'profile'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: #1976d2;">📝</div>
                    <div>
                        <div class="stat-title">Моих заметок</div>
                        <div class="stat-value"><?= $my_notes ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(46, 125, 50, 0.1); color: #2e7d32;">🕒</div>
                    <div>
                        <div class="stat-title">В системе с</div>
                        <div class="stat-value" style="font-size: 18px;"><?= date('d.m.Y', strtotime($profile_data['created_at'])) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">🛡️</div>
                    <div>
                        <div class="stat-title">Статус</div>
                        <div class="stat-value" style="font-size: 18px; color: #2e7d32;">Активен</div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start;">
                <div class="card" style="text-align: center; padding: 40px 20px;">
                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, rgba(217, 83, 79, 0.1) 0%, rgba(255, 138, 128, 0.2) 100%); border-radius: 50%; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: bold; color: #d9534f; border: 4px solid var(--bg-card); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <?= mb_substr($profile_data['login'], 0, 1) ?>
                    </div>
                    <h2 style="font-size: 24px; color: var(--text-title); margin-bottom: 5px;"><?= htmlspecialchars($profile_data['login']) ?></h2>
                    <div style="font-size: 14px; color: #d9534f; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;"><?= htmlspecialchars($profile_data['role']) ?></div>
                    <div style="border-top: 1px solid var(--border-color); padding-top: 20px; font-size: 14px; color: var(--text-muted);">
                        <p style="margin-bottom: 10px;">📧 email@gorizont.ru</p>
                        <p>📱 +7 (999) 000-00-00</p>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card-title">Личные данные</div>
                        <?php if($success_msg): ?><div style="background:rgba(46,125,50,0.1); color:#2e7d32; padding:10px; border-radius:8px; margin-bottom:15px; font-size:14px;"><?= $success_msg ?></div><?php endif; ?>
                        <?php if($error_message): ?><div style="background:rgba(217,83,79,0.1); color:#d9534f; padding:10px; border-radius:8px; margin-bottom:15px; font-size:14px;"><?= $error_message ?></div><?php endif; ?>
                        
                        <form method="POST" action="ui.php?page=profile">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group"><label>Логин</label><input type="text" name="login" class="form-control" value="<?= htmlspecialchars($profile_data['login']) ?>" required></div>
                                <div class="form-group"><label>Роль (только чтение)</label><input type="text" class="form-control" value="<?= htmlspecialchars($profile_data['role']) ?>" disabled style="background: var(--hover-bg); color: var(--text-muted);"></div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">Сохранить изменения</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-title">Безопасность</div>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Смена пароля требуется 1 раз в 3 месяца. 2FA включена.</p>
                        <form method="POST" action="ui.php?page=profile">
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                                <div class="form-group"><label>Текущий пароль</label><input type="password" name="current_password" class="form-control" required></div>
                                <div class="form-group"><label>Новый пароль</label><input type="password" name="new_password" class="form-control" required></div>
                                <div class="form-group"><label>Повторите пароль</label><input type="password" name="confirm_password" class="form-control" required></div>
                            </div>
                            <div style="background: rgba(46,125,50,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(46,125,50,0.2); margin-bottom: 20px;">
                                <h4 style="color: #2e7d32; margin: 0 0 10px 0; font-size: 13px;">Требования к паролю:</h4>
                                <ul style="list-style: none; padding: 0; margin: 0; font-size: 12px; color: #2e7d32; display: flex; gap: 20px;">
                                    <li>✓ Минимум 6 символов</li>
                                    <li>✓ 1 цифра</li>
                                    <li>✓ 1 заглавная буква</li>
                                    <li>✓ 1 строчная буква</li>
                                </ul>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">Обновить пароль</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') { document.body.classList.add('dark-theme'); document.getElementById('theme-btn').innerText = '☀️'; } else { document.getElementById('theme-btn').innerText = '🌙'; }
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            if (document.body.classList.contains('dark-theme')) { localStorage.setItem('theme', 'dark'); document.getElementById('theme-btn').innerText = '☀️'; } 
            else { localStorage.setItem('theme', 'light'); document.getElementById('theme-btn').innerText = '🌙'; }
        }
    </script>
</body>
</html>