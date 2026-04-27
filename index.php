<?php
session_start();
require_once 'config.php';

// Get statistics - count all components from different tables
$totalComponents = 0;
$componentTables = ['components_cpu', 'components_gpu', 'components_mobo', 'components_ram', 
                    'components_storage', 'components_psu', 'components_case', 'components_cooling'];
foreach ($componentTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $totalComponents += $stmt->fetch()['total'];
    } catch (PDOException $e) {
        // Table doesn't exist, skip
    }
}

// Get total builds (table may not exist yet)
$totalBuilds = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_builds WHERE is_public = 1");
    $totalBuilds = $stmt->fetch()['total'];
} catch (PDOException $e) {
    // Table doesn't exist yet
}

// Get featured components
$featuredGPU = null;
$featuredCPU = null;
try {
    $featuredGPU = $pdo->query("SELECT * FROM components_gpu ORDER BY performance_score DESC LIMIT 1")->fetch();
    $featuredCPU = $pdo->query("SELECT * FROM components_cpu ORDER BY performance_score DESC LIMIT 1")->fetch();
} catch (PDOException $e) {
    // Tables don't exist yet
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Конфигуратор премиальных ПК</title>
    <script>
        // Apply theme before page renders to prevent flash
        (function() {
            const storedTheme = localStorage.getItem('theme');
            const savedTheme = storedTheme || 'dark';
            if (!storedTheme) {
                localStorage.setItem('theme', savedTheme);
            }
            const root = document.documentElement;
            root.setAttribute('data-theme', savedTheme);
            root.style.backgroundColor = savedTheme === 'light' ? '#ffffff' : '#0f172a';
            root.style.colorScheme = savedTheme;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=optional" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="home-page">
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="container hero-shell">
            <div class="hero-content">
                <div class="hero-kicker">HyperPC Configurator</div>
                <h1 class="hero-title">
                    Премиальная сборка ПК
                    <span class="gradient-text">под игры, работу и апгрейд без ошибок</span>
                </h1>
                <p class="hero-subtitle">Подберите систему по реальным сценариям: конфигуратор проверит совместимость, покажет производительность, рассчитает питание и поможет довести сборку до заказа без лишней рутины.</p>
                <div class="hero-highlights">
                    <span><i class="fas fa-shield-halved"></i> Контроль совместимости</span>
                    <span><i class="fas fa-gauge-high"></i> Оценка FPS и мощности</span>
                    <span><i class="fas fa-box-open"></i> Путь от идеи до заказа</span>
                </div>
                <div class="hero-buttons">
                    <a href="builder.php" class="btn btn-primary">
                        <i class="fas fa-screwdriver-wrench"></i>
                        Начать сборку
                    </a>
                    <a href="#featured-components" class="btn btn-secondary">
                        <i class="fas fa-arrow-down"></i>
                        Посмотреть топовые компоненты
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= $totalComponents ?>+</div>
                        <div class="stat-label">Комплектующих</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $totalBuilds ?>+</div>
                        <div class="stat-label">Готовых сборок</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">100%</div>
                        <div class="stat-label">Совместимость</div>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-preview">
                    <div class="hero-preview-head">
                        <div class="hero-preview-head-copy">
                            <span class="hero-preview-label">Как работает конфигуратор</span>
                            <p class="hero-preview-head-note">Небольшое превью того, что вы получите при подборе сборки.</p>
                        </div>
                        <span class="hero-preview-status">Демо-поток</span>
                    </div>
                    <div class="hero-build-art">
                        <img class="hero-main-pc" src="pictures/PC_main.png" alt="Игровой ПК HyperPC">
                    </div>
                    <div class="hero-preview-card">
                        <div class="hero-preview-copy">
                            <span class="hero-preview-kicker">Сборка под задачу</span>
                            <h3>Игровой ПК без ручной сверки совместимости</h3>
                            <p>Конфигуратор сразу показывает ключевые проверки и помогает быстрее перейти к готовой сборке.</p>
                        </div>
                        <div class="hero-preview-list">
                            <div class="hero-preview-item">
                                <span>Сценарий</span>
                                <strong>Игры / работа / стриминг</strong>
                            </div>
                            <div class="hero-preview-item">
                                <span>Проверки</span>
                                <strong>Сокет, питание, габариты</strong>
                            </div>
                            <div class="hero-preview-item">
                                <span>Результат</span>
                                <strong>FPS, цена, запас БП</strong>
                            </div>
                        </div>
                    </div>
                    <div class="hero-preview-footer">
                        <div class="hero-preview-pill">
                            <i class="fas fa-check-circle"></i>
                            Совместимость проверяется автоматически
                        </div>
                        <div class="hero-preview-metrics">
                            <div>
                                <span>FPS</span>
                                <strong>до покупки</strong>
                            </div>
                            <div>
                                <span>Цена</span>
                                <strong>в реальном времени</strong>
                            </div>
                        </div>
                    </div>
                    <div class="hero-mini-cards">
                        <div class="hero-mini-card">
                            <span>Этап 1</span>
                            <strong>Задаёте сценарий и бюджет</strong>
                        </div>
                        <div class="hero-mini-card">
                            <span>Этап 2</span>
                            <strong>Получаете готовый результат</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="build-showcase" aria-labelledby="build-showcase-title">
        <div class="container">
            <div class="build-showcase-head">
                <div>
                    <span class="section-label">Примеры сборок</span>
                    <h2 class="section-title" id="build-showcase-title">Живые сценарии, которые можно быстро повторить</h2>
                </div>
                <a href="builds.php" class="btn btn-outline">
                    <i class="fas fa-layer-group"></i>
                    Все сборки
                </a>
            </div>
        </div>
        <div class="build-marquee" aria-hidden="true">
            <div class="build-marquee-track">
                <article class="build-slide build-slide-performance">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/gpu.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>4K Gaming</span>
                        <h3>Core Ultra + RTX 5080</h3>
                        <p>Высокая частота кадров, запас по питанию и тихий корпус.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 245 000 ₽</strong>
                        <span>144+ FPS</span>
                    </div>
                </article>
                <article class="build-slide build-slide-work">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/cpu.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Workstation</span>
                        <h3>Ryzen 9 + 96 ГБ RAM</h3>
                        <p>Монтаж, 3D, код и тяжелые рабочие проекты без просадок.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 198 000 ₽</strong>
                        <span>16 ядер</span>
                    </div>
                </article>
                <article class="build-slide build-slide-compact">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/case.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Compact</span>
                        <h3>Mini-ITX для стола</h3>
                        <p>Минимум места, аккуратная вентиляция и чистый внешний вид.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 126 000 ₽</strong>
                        <span>18 л</span>
                    </div>
                </article>
                <article class="build-slide build-slide-balanced">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/motherboard.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Balanced</span>
                        <h3>1440p без переплаты</h3>
                        <p>Сбалансированная платформа для игр, учебы и апгрейда.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 94 000 ₽</strong>
                        <span>2K Ready</span>
                    </div>
                </article>
                <article class="build-slide build-slide-performance">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/gpu.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>4K Gaming</span>
                        <h3>Core Ultra + RTX 5080</h3>
                        <p>Высокая частота кадров, запас по питанию и тихий корпус.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 245 000 ₽</strong>
                        <span>144+ FPS</span>
                    </div>
                </article>
                <article class="build-slide build-slide-work">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/cpu.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Workstation</span>
                        <h3>Ryzen 9 + 96 ГБ RAM</h3>
                        <p>Монтаж, 3D, код и тяжелые рабочие проекты без просадок.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 198 000 ₽</strong>
                        <span>16 ядер</span>
                    </div>
                </article>
                <article class="build-slide build-slide-compact">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/case.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Compact</span>
                        <h3>Mini-ITX для стола</h3>
                        <p>Минимум места, аккуратная вентиляция и чистый внешний вид.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 126 000 ₽</strong>
                        <span>18 л</span>
                    </div>
                </article>
                <article class="build-slide build-slide-balanced">
                    <div class="build-slide-visual">
                        <img src="pictures/catalog/motherboard.svg" alt="">
                    </div>
                    <div class="build-slide-copy">
                        <span>Balanced</span>
                        <h3>1440p без переплаты</h3>
                        <p>Сбалансированная платформа для игр, учебы и апгрейда.</p>
                    </div>
                    <div class="build-slide-meta">
                        <strong>от 94 000 ₽</strong>
                        <span>2K Ready</span>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="experience-strip">
        <div class="container">
            <div class="experience-grid">
                <div class="experience-item">
                    <span class="experience-number">01</span>
                    <div>
                        <h3>Сценарий вместо хаоса</h3>
                        <p>Сразу понятно, для чего создаётся сборка: competitive gaming, 4K, монтаж, 3D или универсальная рабочая станция.</p>
                    </div>
                </div>
                <div class="experience-item">
                    <span class="experience-number">02</span>
                    <div>
                        <h3>Техническая ясность</h3>
                        <p>Ключевые ограничения и сильные стороны видны сразу: совместимость, запас по питанию, баланс производительности и цены.</p>
                    </div>
                </div>
                <div class="experience-item">
                    <span class="experience-number">03</span>
                    <div>
                        <h3>Дальше можно оформлять</h3>
                        <p>Готовую конфигурацию легко сохранить, сравнить, показать другу или перевести в заказ без повторного ручного ввода.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-heading">
                <span class="section-label">Преимущества</span>
                <h2 class="section-title">Инструменты, которые делают подбор ПК спокойным и точным</h2>
                <p class="section-intro">Главная страница теперь ведёт к действию быстрее: меньше визуального шума, яснее ценность сервиса и заметнее ключевые сценарии использования.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Проверка совместимости</h3>
                    <p>Автоматическая проверка совместимости всех компонентов. Никаких ошибок при сборке.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h3>Расчет FPS</h3>
                    <p>Узнайте производительность вашей сборки в популярных играх перед покупкой.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Расчет мощности</h3>
                    <p>Автоматический подбор блока питания с учетом энергопотребления компонентов.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h3>Контроль бюджета</h3>
                    <p>Отслеживайте стоимость сборки в реальном времени и оптимизируйте затраты.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-share-nodes"></i>
                    </div>
                    <h3>Поделиться сборкой</h3>
                    <p>Сохраняйте и делитесь своими конфигурациями с друзьями и сообществом.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Оформление заказа</h3>
                    <p>Сборку можно оформить как заказ: мы подготовим комплект и сопроводим доставку.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Актуальные цены</h3>
                    <p>Регулярное обновление цен и наличия комплектующих от проверенных продавцов.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-site">
        <div class="container">
            <div class="about-site-inner">
                <div class="about-site-copy">
                    <span class="section-label">Сервис</span>
                    <h2>HyperPC — сервис для тех, кому важны детали</h2>
                    <p>Прозрачные характеристики, актуальные цены и понятные рекомендации по каждой сборке — без лишнего шума. Конфигуратор показывает, где можно усилить систему и где уже достигнут баланс.</p>
                    <div class="about-site-points">
                        <div class="about-point">
                            <i class="fas fa-diagram-project"></i>
                            <span>Прозрачные параметры: сокеты, форм‑факторы, слоты</span>
                        </div>
                        <div class="about-point">
                            <i class="fas fa-gauge-high"></i>
                            <span>Понятная оценка производительности и сценариев</span>
                        </div>
                        <div class="about-point">
                            <i class="fas fa-bolt"></i>
                            <span>Аккуратный подбор питания и запаса по мощности</span>
                        </div>
                    </div>
                    <div class="about-site-actions">
                        <a href="builder.php" class="btn btn-primary">
                            <i class="fas fa-rocket"></i>
                            Начать сборку
                        </a>
                        <a href="catalog.php" class="btn btn-outline">
                            <i class="fas fa-layer-group"></i>
                            Каталог компонентов
                        </a>
                    </div>
                </div>
                <div class="about-site-cards">
                    <div class="about-card">
                        <div class="about-card-header">
                            <span>Рекомендации по сборке</span>
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <p>Система подсвечивает слабые места, чтобы сборка была сбалансированной по производительности.</p>
                    </div>
                    <div class="about-card">
                        <div class="about-card-header">
                            <span>Фильтры по совместимости</span>
                            <i class="fas fa-filter"></i>
                        </div>
                        <p>Авто‑фильтры под материнскую плату: сокеты, тип памяти и охлаждение можно менять вручную.</p>
                    </div>
                    <div class="about-card">
                        <div class="about-card-header">
                            <span>Живые сценарии</span>
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <p>Проверяйте, как сборка ведет себя в играх и рабочих задачах, прежде чем оформлять заказ.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Components -->
    <section class="featured" id="featured-components">
        <div class="container">
            <div class="section-heading">
                <span class="section-label">Фокус</span>
                <h2 class="section-title">Топовые комплектующие для сборок высокого класса</h2>
            </div>
            <div class="featured-grid">
                <?php if ($featuredCPU): ?>
                <div class="product-card featured-product">
                    <div class="product-badge">Топ CPU</div>
                    <div class="product-image">
                        <img src="pictures/catalog/cpu.svg" alt="CPU">
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars($featuredCPU['name']) ?></h3>
                        <div class="product-specs">
                            <?php 
                            $specs = json_decode($featuredCPU['specs'], true);
                            echo $specs['cores'] . ' ядер / ' . $specs['threads'] . ' потоков';
                            ?>
                        </div>
                        <div class="product-footer">
                            <div class="product-price"><?= formatPrice($featuredCPU['price']) ?></div>
                            <div class="product-score">
                                <i class="fas fa-star"></i>
                                <?= $featuredCPU['performance_score'] ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($featuredGPU): ?>
                <div class="product-card featured-product">
                    <div class="product-badge">Топ GPU</div>
                    <div class="product-image">
                        <img src="pictures/catalog/gpu.svg" alt="GPU">
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars($featuredGPU['name']) ?></h3>
                        <div class="product-specs">
                            <?php 
                            $specs = json_decode($featuredGPU['specs'], true);
                            echo $specs['memory'];
                            ?>
                        </div>
                        <div class="product-footer">
                            <div class="product-price"><?= formatPrice($featuredGPU['price']) ?></div>
                            <div class="product-score">
                                <i class="fas fa-star"></i>
                                <?= $featuredGPU['performance_score'] ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="js/main.js"></script>
</body>
</html>
