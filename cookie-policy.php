<?php
session_start();
require_once 'config.php';

$pageTitle = 'Политика использования Cookie';
$pageDescription = 'Подробная информация о том, как HyperPC использует cookie и как вы можете управлять этими настройками.';
$updatedAt = '17 января 2026 года';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= SITE_NAME ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/legal-pages.css">
    <link rel="stylesheet" href="css/cookie-policy.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="legal-page cookie-policy-page">
        <div class="container">
            <div class="legal-shell">
                <section class="legal-hero">
                    <div class="legal-hero__copy">
                        <span class="legal-hero__kicker">Cookie preferences</span>
                        <div class="legal-hero__title">
                            <div class="legal-hero__icon">
                                <i class="fas fa-cookie-bite"></i>
                            </div>
                            <div>
                                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                            </div>
                        </div>
                        <p class="legal-hero__lead">
                            Здесь описано, какие типы cookie использует HyperPC, зачем они нужны и как вы можете управлять функциональными, аналитическими и маркетинговыми настройками.
                        </p>
                        <div class="legal-hero__points">
                            <span><i class="fas fa-sliders"></i> Управление предпочтениями</span>
                            <span><i class="fas fa-shield-halved"></i> Обязательные и необязательные cookie</span>
                            <span><i class="fas fa-circle-info"></i> Связь с privacy и terms</span>
                        </div>
                    </div>

                    <div class="legal-hero__meta">
                        <div class="legal-meta-card">
                            <span>Последнее обновление</span>
                            <strong><?= htmlspecialchars($updatedAt) ?></strong>
                            <p>Страница объединяет юридическое описание cookie и интерфейс управления пользовательскими предпочтениями.</p>
                        </div>

                        <div class="legal-cross-links">
                            <span>Связанные документы</span>
                            <p>Если вы хотите посмотреть полную юридическую рамку работы сервиса, откройте связанные документы.</p>
                            <ul>
                                <li><a href="privacy.php">Политика конфиденциальности</a></li>
                                <li><a href="terms.php">Условия использования</a></li>
                                <li><a href="support.php">Связаться с поддержкой</a></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <div class="legal-layout">
                    <aside class="legal-toc" aria-label="Навигация по документу">
                        <div class="legal-toc__title">Содержание</div>
                        <ul>
                            <li><a href="#what-are-cookies">Что такое cookie</a></li>
                            <li><a href="#cookie-categories">Категории cookie</a></li>
                            <li><a href="#cookie-controls">Управление настройками</a></li>
                            <li><a href="#cookie-retention">Срок хранения</a></li>
                            <li><a href="#cookie-privacy">Безопасность и данные</a></li>
                            <li><a href="#cookie-contacts">Контакты</a></li>
                        </ul>
                    </aside>

                    <div class="legal-document">
                        <section class="legal-intro">
                            <p>
                                Cookie помогают HyperPC сохранять настройки, поддерживать авторизацию и улучшать качество сервиса. Обязательные cookie всегда активны, а остальные категории вы можете настроить вручную на этой странице.
                            </p>
                        </section>

                        <section class="legal-section" id="what-are-cookies">
                            <h2><i class="fas fa-info-circle"></i> 1. Что такое cookie?</h2>
                            <p>
                                Cookie — это небольшие текстовые файлы, которые сохраняются на устройстве при посещении сайта. Они позволяют сервису запоминать информацию о вашем визите, например тему оформления, язык интерфейса и другие настройки.
                            </p>
                            <p>
                                Cookie не содержат вирусов и не дают прямого доступа к вашим личным файлам. Они используются для корректной работы сайта, улучшения пользовательского опыта и анализа использования сервиса.
                            </p>
                        </section>

                        <section class="legal-section" id="cookie-categories">
                            <h2><i class="fas fa-list-check"></i> 2. Какие cookie мы используем?</h2>
                            <p class="cookie-section-intro">Мы используем четыре группы cookie, чтобы сервис работал корректно, сохранял ваши настройки и позволял нам улучшать продукт.</p>

                            <div class="cookie-categories">
                                <article class="cookie-category cookie-category--required">
                                    <div class="category-header">
                                        <div class="category-info">
                                            <div>
                                                <h3><i class="fas fa-shield-halved"></i> Обязательные cookie</h3>
                                                <p class="category-description">
                                                    Нужны для работы сайта, авторизации и безопасности. Их нельзя отключить.
                                                </p>
                                            </div>
                                            <span class="category-badge required">Всегда активны</span>
                                        </div>
                                    </div>
                                    <div class="cookie-list">
                                        <div class="cookie-item">
                                            <strong>PHPSESSID, csrf_token</strong>
                                            <span>Сеанс пользователя и защита от подделки запросов</span>
                                        </div>
                                        <div class="cookie-item">
                                            <strong>cookieConsent</strong>
                                            <span>Сохранение вашего выбора по cookie</span>
                                        </div>
                                    </div>
                                </article>

                                <article class="cookie-category">
                                    <div class="category-header">
                                        <div class="category-info">
                                            <div>
                                                <h3><i class="fas fa-sliders"></i> Функциональные cookie</h3>
                                                <p class="category-description">
                                                    Запоминают тему, конфигурации и помогают восстановить рабочее состояние сервиса.
                                                </p>
                                            </div>
                                            <label class="cookie-toggle">
                                                <input type="checkbox" id="functionalCookies" onchange="updateCookiePreferences()">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="cookie-list">
                                        <div class="cookie-item">
                                            <strong>theme, currentBuild, compareBuilds</strong>
                                            <span>Тема оформления, конфигурации и сравнение сборок</span>
                                        </div>
                                    </div>
                                </article>

                                <article class="cookie-category">
                                    <div class="category-header">
                                        <div class="category-info">
                                            <div>
                                                <h3><i class="fas fa-chart-line"></i> Аналитические cookie</h3>
                                                <p class="category-description">
                                                    Помогают понять, как пользователи взаимодействуют с сайтом, чтобы мы могли улучшать сервис.
                                                </p>
                                            </div>
                                            <label class="cookie-toggle">
                                                <input type="checkbox" id="analyticsCookies" onchange="updateCookiePreferences()">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="cookie-list">
                                        <div class="cookie-item">
                                            <strong>_ga, _gid, _ym_*</strong>
                                            <span>Аналитика трафика и поведения пользователей</span>
                                        </div>
                                    </div>
                                </article>

                                <article class="cookie-category">
                                    <div class="category-header">
                                        <div class="category-info">
                                            <div>
                                                <h3><i class="fas fa-bullhorn"></i> Маркетинговые cookie</h3>
                                                <p class="category-description">
                                                    Нужны для персонализации предложений и оценки эффективности рекламных кампаний.
                                                </p>
                                            </div>
                                            <label class="cookie-toggle">
                                                <input type="checkbox" id="marketingCookies" onchange="updateCookiePreferences()">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="cookie-list">
                                        <div class="cookie-item">
                                            <strong>_fbp, ads/ga-audiences</strong>
                                            <span>Ремаркетинг и персонализация рекламных сценариев</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>

                        <section class="legal-section" id="cookie-controls">
                            <h2><i class="fas fa-gears"></i> 3. Управление настройками cookie</h2>
                            <p>
                                Вы можете в любой момент изменить предпочтения относительно использования cookie. Отключение некоторых категорий может повлиять на функциональность сайта и персонализацию интерфейса.
                            </p>

                            <div class="cookie-actions-panel">
                                <div class="preference-actions">
                                    <button type="button" class="btn-preference btn-accept-all" onclick="acceptAllCookies()">
                                        <i class="fas fa-check-double"></i>
                                        <span>Принять все cookie</span>
                                    </button>
                                    <button type="button" class="btn-preference btn-save" onclick="saveCookiePreferences()">
                                        <i class="fas fa-floppy-disk"></i>
                                        <span>Сохранить настройки</span>
                                    </button>
                                    <button type="button" class="btn-preference btn-decline-all" onclick="declineAllCookies()">
                                        <i class="fas fa-ban"></i>
                                        <span>Отклонить необязательные</span>
                                    </button>
                                </div>
                                <p class="cookie-actions-note">Функциональные cookie по умолчанию остаются активными, так как без них часть пользовательских сценариев будет недоступна.</p>
                            </div>

                            <div class="browser-settings">
                                <h3><i class="fas fa-browser"></i> Управление через браузер</h3>
                                <p>Вы также можете управлять cookie через настройки браузера:</p>
                                <ul>
                                    <li><strong>Google Chrome:</strong> Настройки → Конфиденциальность и безопасность → Файлы cookie</li>
                                    <li><strong>Mozilla Firefox:</strong> Настройки → Приватность и защита → Куки и данные сайтов</li>
                                    <li><strong>Safari:</strong> Настройки → Конфиденциальность → Управление данными веб-сайтов</li>
                                    <li><strong>Microsoft Edge:</strong> Настройки → Файлы cookie и разрешения сайтов</li>
                                </ul>
                            </div>
                        </section>

                        <section class="legal-section" id="cookie-retention">
                            <h2><i class="fas fa-clock-rotate-left"></i> 4. Срок хранения cookie</h2>
                            <p>Различные типы cookie хранятся в течение разных периодов времени:</p>
                            <ul>
                                <li><strong>Сеансовые cookie:</strong> удаляются автоматически при закрытии браузера</li>
                                <li><strong>Постоянные cookie:</strong> могут храниться от нескольких дней до 2 лет в зависимости от категории</li>
                                <li><strong>Согласие на cookie:</strong> действует 6 месяцев, после чего запрашивается повторно</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="cookie-privacy">
                            <h2><i class="fas fa-shield-alt"></i> 5. Безопасность и конфиденциальность</h2>
                            <p>
                                Мы серьёзно относимся к защите пользовательских данных. Cookie, которые используются на сайте, не содержат персональной информации, которая могла бы напрямую идентифицировать вас без дополнительных данных.
                            </p>
                            <p>
                                Для получения более полной информации о том, как мы обрабатываем персональные данные, ознакомьтесь с нашей <a href="privacy.php">Политикой конфиденциальности</a>. Общие правила использования сервиса изложены в <a href="terms.php">Условиях использования</a>.
                            </p>
                        </section>

                        <section class="legal-contact-card" id="cookie-contacts">
                            <h2><i class="fas fa-envelope"></i> Контакты</h2>
                            <p>Если у вас есть вопросы о нашей политике использования cookie, свяжитесь с нами:</p>
                            <ul>
                                <li><strong>Email:</strong> <a href="mailto:privacy@hyperpc.ru">privacy@hyperpc.ru</a></li>
                                <li><strong>Телефон:</strong> +7 (495) 123-45-67</li>
                                <li><strong>Поддержка:</strong> <a href="support.php">Форма обратной связи</a></li>
                            </ul>
                        </section>

                        <div class="legal-footer-note">
                            <p>Эта страница помогает управлять cookie-настройками без выхода из общей legal-системы HyperPC. Если у вас остались вопросы, используйте форму поддержки или связанные юридические документы.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="js/main.js"></script>
    <script src="js/cookie-preferences.js"></script>
</body>
</html>
