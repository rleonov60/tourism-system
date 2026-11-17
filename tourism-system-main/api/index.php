<?php
$conn = new mysqli("localhost", "root", "", "tourism_db");
if ($conn->connect_error) die("Ошибка БД");

// Получаем туры из базы
$tours = $conn->query("SELECT * FROM tours ORDER BY id DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Горизонт - Путешествия, которые меняют жизнь</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f6f4; color: #333; line-height: 1.5; }
        
        /* Контейнер */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        /* Шапка */
        header { background: #fff; padding: 15px 0; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 100; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: 900; color: #222; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #d9534f 0%, #ff8a80 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-links a { text-decoration: none; color: #555; font-weight: 500; font-size: 15px; }
        .nav-links a:hover { color: #d9534f; }
        .btn-login { padding: 8px 20px; background: #d9534f; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }

        /* Главный баннер */
        .hero { padding: 60px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; }
        .hero-text h1 { font-size: 48px; font-weight: 800; color: #222; line-height: 1.1; margin-bottom: 20px; }
        .hero-text p { font-size: 18px; color: #666; margin-bottom: 30px; }
        .hero-buttons { display: flex; gap: 15px; }
        .btn-main { padding: 14px 28px; background: #d9534f; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-outline { padding: 14px 28px; background: transparent; color: #d9534f; border: 2px solid #d9534f; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; }
        
        .hero-stats { display: flex; gap: 30px; margin-top: 40px; }
        .stat-item h3 { font-size: 32px; color: #222; font-weight: 800; margin-bottom: 5px; }
        .stat-item p { font-size: 14px; color: #888; }

        /* Категории */
        .section-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 25px; margin-top: 50px; }
        .categories { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .category-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #eaeaea; cursor: pointer; transition: 0.2s; }
        .category-card:hover { border-color: #d9534f; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .cat-icon { font-size: 32px; margin-bottom: 15px; }
        .category-card h3 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 5px; }
        .category-card p { font-size: 13px; color: #888; }

        /* Популярные туры */
        .tours-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .tour-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #eaeaea; display: flex; flex-direction: column; transition: 0.2s; }
        .tour-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .tour-image { width: 100%; height: 220px; object-fit: cover; background: #eee; }
        .tour-info { padding: 20px; display: flex; flex-direction: column; flex: 1; }
        .tour-country { font-size: 12px; color: #d9534f; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
        .tour-title { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 10px; }
        .tour-details { font-size: 14px; color: #888; margin-bottom: 20px; }
        .tour-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #f0f0f0; }
        .tour-price { font-size: 22px; font-weight: 800; color: #222; }
        .tour-btn { padding: 8px 16px; background: #f5f5f5; color: #333; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .tour-btn:hover { background: #d9534f; color: #fff; }

        /* Почему мы */
        .features-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 60px; }
        .feature-card { background: #fff; padding: 30px 20px; border-radius: 16px; text-align: center; border: 1px solid #eaeaea; }
        .feature-icon { width: 50px; height: 50px; background: rgba(217, 83, 79, 0.1); color: #d9534f; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px auto; }
        .feature-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; color: #222; }
        .feature-card p { font-size: 13px; color: #888; }

        /* Отзывы */
        .reviews-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 60px; }
        .review-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #eaeaea; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .review-author { display: flex; gap: 15px; align-items: center; }
        .avatar { width: 40px; height: 40px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #888; }
        .author-info h4 { font-size: 15px; color: #222; }
        .author-info p { font-size: 12px; color: #888; }
        .rating { color: #ffc107; font-weight: bold; }
        .review-text { font-size: 14px; color: #555; font-style: italic; line-height: 1.6; }

        /* Подвал */
        footer { background: #fff; border-top: 1px solid #eaeaea; padding: 40px 0; text-align: center; }
        footer p { color: #888; font-size: 14px; }
    </style>
</head>
<body>

    <header>
        <div class="container header-content">
            <a href="index.php" class="logo">
                <div class="logo-icon">▲</div> Горизонт
            </a>
            <div class="nav-links">
                <a href="#">Туры</a>
                <a href="#">Направления</a>
                <a href="#">О нас</a>
                <a href="#">Контакты</a>
            </div>
            <a href="login.php" class="btn-login">Вход для менеджеров</a>
        </div>
    </header>

    <div class="container">
        <!-- Главный баннер -->
        <section class="hero">
            <div class="hero-text">
                <h1>Путешествия, которые меняют жизнь</h1>
                <p>Откройте для себя новые страны, отели и впечатления. Наши менеджеры подберут идеальный тур под ваш бюджет и запросы.</p>
                <div class="hero-buttons">
                    <a href="#tours" class="btn-main">Подобрать тур</a>
                    <a href="#tours" class="btn-outline">Заявка</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item"><h3>12</h3><p>Стран</p></div>
                    <div class="stat-item"><h3>28</h3><p>Туров</p></div>
                    <div class="stat-item"><h3>98%</h3><p>Довольных клиентов</p></div>
                </div>
            </div>
            <div>
                <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80" style="width: 100%; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);" alt="Путешествие">
            </div>
        </section>

        <!-- Категории -->
        <h2 class="section-title">Категории туров</h2>
        <div class="categories">
            <div class="category-card">
                <div class="cat-icon">🏔️</div>
                <h3>Активный отдых</h3>
                <p>Горы, походы, экстрим</p>
            </div>
            <div class="category-card">
                <div class="cat-icon">🏖️</div>
                <h3>Пляжный отдых</h3>
                <p>Море, солнце, песок</p>
            </div>
            <div class="category-card">
                <div class="cat-icon">🏛️</div>
                <h3>Экскурсии</h3>
                <p>История и культура</p>
            </div>
            <div class="category-card">
                <div class="cat-icon">🧘</div>
                <h3>Оздоровление</h3>
                <p>Спа, лечение, йога</p>
            </div>
        </div>

        <!-- Популярные туры -->
        <h2 class="section-title" id="tours">Популярные туры</h2>
        <div class="tours-grid">
            <?php while($r = $tours->fetch_assoc()): ?>
                <div class="tour-card">
                    <?php if(!empty($r['image'])): $img_src = (strpos($r['image'], 'http') === 0) ? $r['image'] : 'uploads/' . $r['image']; ?>
                        <img src="<?= $img_src ?>" class="tour-image" alt="Фото" onerror="this.src='https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=600&q=80';">
                    <?php else: ?>
                        <img src="https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=600&q=80" class="tour-image" alt="Фото">
                    <?php endif; ?>
                    <div class="tour-info">
                        <div class="tour-country"><?= htmlspecialchars($r['country']) ?></div>
                        <div class="tour-title"><?= htmlspecialchars($r['title']) ?></div>
                        <div class="tour-details">Длительность: <?= $r['days'] ?> дней</div>
                        <div class="tour-footer">
                            <div class="tour-price"><?= number_format($r['price'],0,'',' ') ?> ₽</div>
                            <a href="login.php" class="tour-btn">Подробнее</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Почему выбирают нас -->
        <h2 class="section-title">Почему выбирают нас</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Гарантия качества</h3>
                <p>Только проверенные отели и перевозчики</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Честные цены</h3>
                <p>Без скрытых комиссий и доплат</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Быстрое бронирование</h3>
                <p>Подтверждение тура за 15 минут</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📞</div>
                <h3>Поддержка 24/7</h3>
                <p>Всегда на связи в любой точке мира</p>
            </div>
        </div>

        <!-- Отзывы -->
        <h2 class="section-title">Отзывы туристов</h2>
        <div class="reviews-grid">
            <div class="review-card">
                <div class="review-header">
                    <div class="review-author">
                        <div class="avatar">А</div>
                        <div class="author-info"><h4>Алексей</h4><p>Тур в Дагестан</p></div>
                    </div>
                    <div class="rating">★★★★★</div>
                </div>
                <p class="review-text">«Отличная поездка! Менеджеры помогли с выбором, отель был супер. Обязательно вернемся!»</p>
            </div>
            <div class="review-card">
                <div class="review-header">
                    <div class="review-author">
                        <div class="avatar">М</div>
                        <div class="author-info"><h4>Марина</h4><p>Тур в Турцию</p></div>
                    </div>
                    <div class="rating">★★★★★</div>
                </div>
                <p class="review-text">«Всё на высшем уровне. Трансферы вовремя, отель соответствует описанию. Спасибо Горизонт!»</p>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>© 2026 Туристическое агентство «Горизонт». Все права защищены.</p>
            <p style="margin-top: 10px;">+7 (800) 555-35-35 | support@gorizont.ru</p>
        </div>
    </footer>

</body>
</html>