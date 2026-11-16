<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$conn = new mysqli("localhost", "root", "", "tourism_db");

$voucher_id = (int)($_GET['voucher_id'] ?? 0);
$stmt = $conn->prepare("
    SELECT v.id, v.created_at, c.full_name, c.phone, c.email, t.title as tour_title, t.price
    FROM vouchers v
    JOIN clients c ON c.id = v.client_id
    JOIN tours t ON t.id = v.tour_id
    WHERE v.id = ?
");
$stmt->bind_param("i", $voucher_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if (!$data) die("Путёвка не найдена");

// Записываем факт создания документа
$stmt = $conn->prepare("INSERT INTO documents (voucher_id, doc_type, created_at) VALUES (?, 'Договор', ?)");
$today = date('Y-m-d');
$stmt->bind_param("is", $voucher_id, $today);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Договор № <?= $data['id'] ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 40px; color: #000; line-height: 1.6; max-width: 800px; margin: 0 auto; }
        h1 { text-align: center; font-size: 22px; margin-bottom: 30px; text-transform: uppercase; }
        h2 { font-size: 18px; margin-top: 20px; }
        p { margin-bottom: 10px; text-align: justify; }
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .signature-block { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-line { width: 200px; border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px; }
        .signature-label { font-size: 12px; color: #555; }
        .btn-print { display: block; margin: 20px auto; padding: 12px 30px; background: #d9534f; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Распечатать / Сохранить в PDF</button>
    
    <table class="header-table">
        <tr>
            <td><strong>Туристическое агентство «Горизонт»</strong><br>
            ИНН: 1234567890<br>
            Адрес: г. Москва, ул. Ленина, д. 1<br>
            Тел: 8 (800) 555-35-35</td>
            <td style="text-align: right;">
                <strong>Договор № <?= str_pad($data['id'], 5, '0', STR_PAD_LEFT) ?></strong><br>
                от <?= date('d.m.Y', strtotime($data['created_at'])) ?>
            </td>
        </tr>
    </table>

    <h1>Договор оказания туристических услуг</h1>
    
    <p><strong>Исполнитель:</strong> Туристическое агентство «Горизонт» в лице генерального директора Кристины Сергеевой, действующей на основании Устава, с одной стороны, и</p>
    
    <p><strong>Заказчик:</strong> <?= htmlspecialchars($data['full_name']) ?>, телефон: <?= htmlspecialchars($data['phone']) ?>, email: <?= htmlspecialchars($data['email']) ?>, с другой стороны, заключили настоящий договор о нижеследующем.</p>

    <h2>1. Предмет договора</h2>
    <p>1.1. Исполнитель обязуется организовать туристическую поездку: <strong>«<?= htmlspecialchars($data['tour_title']) ?>»</strong>, а Заказчик обязуется оплатить данную услугу.</p>
    
    <h2>2. Стоимость услуги</h2>
    <p>2.1. Полная стоимость тура составляет <strong><?= number_format($data['price'], 0, '', ' ') ?> рублей</strong>.</p>
    <p>2.2. Оплата производится в порядке 100% предоплаты.</p>

    <h2>3. Подписи сторон</h2>
    <div class="signature-block">
        <div>
            <div class="signature-line"></div>
            <div class="signature-label">Исполнитель: Сергеева К. А.</div>
        </div>
        <div>
            <div class="signature-line"></div>
            <div class="signature-label">Заказчик: <?= htmlspecialchars($data['full_name']) ?></div>
        </div>
    </div>
</body>
</html>