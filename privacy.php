<?php
session_start();
require_once 'config.php';

$pageTitle = 'Политика конфиденциальности';
$pageDescription = 'Как HyperPC собирает, использует и защищает персональные данные пользователей.';
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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/legal-pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="legal-page">
        <div class="container">
            <div class="legal-shell">
                <section class="legal-hero">
                    <div class="legal-hero__copy">
                        <span class="legal-hero__kicker">Legal document</span>
                        <div class="legal-hero__title">
                            <div class="legal-hero__icon">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <div>
                                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                            </div>
                        </div>
                        <p class="legal-hero__lead">
                            В этом документе описано, какие данные HyperPC получает от пользователей, как они используются, хранятся и защищаются в рамках работы сервиса.
                        </p>
                        <div class="legal-hero__points">
                            <span><i class="fas fa-lock"></i> Персональные данные и безопасность</span>
                            <span><i class="fas fa-database"></i> Правила хранения и обработки</span>
                            <span><i class="fas fa-scale-balanced"></i> Права пользователя</span>
                        </div>
                    </div>

                    <div class="legal-hero__meta">
                        <div class="legal-meta-card">
                            <span>Последнее обновление</span>
                            <strong><?= htmlspecialchars($updatedAt) ?></strong>
                            <p>Документ применяется ко всем пользователям HyperPC и связан с условиями использования и политикой cookie.</p>
                        </div>

                        <div class="legal-cross-links">
                            <span>Связанные документы</span>
                            <p>Если вы хотите понять правила использования сервиса целиком, посмотрите и другие юридические документы.</p>
                            <ul>
                                <li><a href="terms.php">Условия использования</a></li>
                                <li><a href="cookie-policy.php">Политика cookie</a></li>
                                <li><a href="support.php">Связаться с поддержкой</a></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <div class="legal-layout">
                    <aside class="legal-toc" aria-label="Навигация по документу">
                        <div class="legal-toc__title">Содержание</div>
                        <ul>
                            <li><a href="#general">Общие положения</a></li>
                            <li><a href="#data">Какие данные мы собираем</a></li>
                            <li><a href="#usage">Цели использования</a></li>
                            <li><a href="#protection">Защита данных</a></li>
                            <li><a href="#sharing">Передача третьим лицам</a></li>
                            <li><a href="#cookies">Файлы cookie</a></li>
                            <li><a href="#rights">Ваши права</a></li>
                            <li><a href="#retention">Хранение данных</a></li>
                            <li><a href="#contacts">Контакты</a></li>
                        </ul>
                    </aside>

                    <div class="legal-document">
                        <section class="legal-intro">
                            <p>
                                <strong>HyperPC</strong> серьёзно относится к защите персональных данных. Настоящая Политика конфиденциальности объясняет, как сервис собирает, использует и защищает информацию пользователей в соответствии с законодательством Российской Федерации.
                            </p>
                        </section>

                        <section class="legal-section" id="general">
                            <h2><i class="fas fa-info-circle"></i> 1. Общие положения</h2>
                            <p>Настоящая Политика конфиденциальности применяется ко всем пользователям сервиса HyperPC и регулируется:</p>
                            <ul>
                                <li>Федеральным законом РФ № 152-ФЗ «О персональных данных»</li>
                                <li>Федеральным законом РФ № 149-ФЗ «Об информации, информационных технологиях и о защите информации»</li>
                                <li>Федеральным законом РФ № 242-ФЗ о локализации баз персональных данных</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="data">
                            <h2><i class="fas fa-database"></i> 2. Какие данные мы собираем</h2>

                            <h3>2.1. Данные, которые вы предоставляете</h3>
                            <ul>
                                <li><strong>Регистрационные данные:</strong> имя пользователя, адрес электронной почты, пароль в зашифрованном виде</li>
                                <li><strong>Данные профиля:</strong> информация о ваших сборках ПК и сохранённых конфигурациях</li>
                                <li><strong>Коммуникации:</strong> обращения в поддержку, отзывы и сообщения</li>
                            </ul>

                            <h3>2.2. Данные, собираемые автоматически</h3>
                            <ul>
                                <li><strong>Технические данные:</strong> IP-адрес, тип браузера, операционная система</li>
                                <li><strong>Данные об использовании:</strong> страницы, которые вы посещаете, время на сайте и клики</li>
                                <li><strong>Файлы cookie:</strong> данные для улучшения работы сервиса и персонализации</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="usage">
                            <h2><i class="fas fa-bullseye"></i> 3. Цели использования данных</h2>
                            <p>Мы используем ваши персональные данные для следующих целей:</p>
                            <ol>
                                <li><strong>Предоставление услуг:</strong> создание и управление аккаунтом, сохранение сборок ПК</li>
                                <li><strong>Улучшение сервиса:</strong> анализ использования для развития функционала</li>
                                <li><strong>Безопасность:</strong> предотвращение мошенничества и несанкционированного доступа</li>
                                <li><strong>Коммуникация:</strong> отправка важных уведомлений о сервисе с вашего согласия</li>
                                <li><strong>Соблюдение законодательства:</strong> выполнение юридических обязательств</li>
                            </ol>
                        </section>

                        <section class="legal-section" id="protection">
                            <h2><i class="fas fa-lock"></i> 4. Защита персональных данных</h2>
                            <p>Мы применяем современные технологии защиты информации:</p>
                            <ul>
                                <li><strong>Шифрование:</strong> пароли хранятся с использованием алгоритма Argon2ID</li>
                                <li><strong>SSL/TLS:</strong> данные передаются через защищённое соединение</li>
                                <li><strong>Защита от атак:</strong> используются механизмы против SQL-инъекций, XSS и CSRF</li>
                                <li><strong>Ограничение доступа:</strong> доступ к данным имеет только авторизованный персонал</li>
                                <li><strong>Регулярный аудит:</strong> проводится проверка систем безопасности</li>
                                <li><strong>Резервное копирование:</strong> выполняются бэкапы для предотвращения потери данных</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="sharing">
                            <h2><i class="fas fa-share-nodes"></i> 5. Передача данных третьим лицам</h2>
                            <p>Мы <strong>не продаём</strong> ваши персональные данные третьим лицам. Передача возможна только в следующих случаях:</p>
                            <ul>
                                <li><strong>С вашего согласия:</strong> когда вы явно разрешаете передачу</li>
                                <li><strong>Поставщики услуг:</strong> надёжные партнёры, которые помогают в работе сервиса</li>
                                <li><strong>Юридические требования:</strong> по запросу государственных органов РФ в рамках закона</li>
                                <li><strong>Защита прав:</strong> для предотвращения мошенничества или нарушения правил использования</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="cookies">
                            <h2><i class="fas fa-cookie-bite"></i> 6. Использование файлов cookie</h2>
                            <p>Мы используем cookie для:</p>
                            <ul>
                                <li>Сохранения пользовательских настроек, включая тему оформления</li>
                                <li>Поддержания авторизованной сессии</li>
                                <li>Анализа посещаемости и поведения пользователей</li>
                                <li>Улучшения функциональности сервиса</li>
                            </ul>
                            <p>Вы можете управлять cookie в настройках браузера. Отключение cookie может ограничить часть функциональности сайта.</p>
                        </section>

                        <section class="legal-section" id="rights">
                            <h2><i class="fas fa-user-shield"></i> 7. Ваши права</h2>
                            <p>В соответствии с законодательством Российской Федерации вы имеете право:</p>
                            <ul>
                                <li><strong>На доступ:</strong> запросить копию ваших персональных данных</li>
                                <li><strong>На исправление:</strong> обновить неточные или неполные данные</li>
                                <li><strong>На удаление:</strong> запросить удаление данных при наличии законных оснований</li>
                                <li><strong>На ограничение обработки:</strong> ограничить использование ваших данных</li>
                                <li><strong>На возражение:</strong> отказаться от обработки данных в маркетинговых целях</li>
                                <li><strong>На отзыв согласия:</strong> отозвать ранее данное согласие в любое время</li>
                            </ul>
                        </section>

                        <section class="legal-section" id="retention">
                            <h2><i class="fas fa-clock"></i> 8. Хранение и дополнительные условия</h2>
                            <p>Мы храним ваши персональные данные только в течение необходимого периода:</p>
                            <ul>
                                <li><strong>Активные аккаунты:</strong> данные хранятся до удаления аккаунта</li>
                                <li><strong>Неактивные аккаунты:</strong> удаляются через 3 года бездействия с предварительным уведомлением</li>
                                <li><strong>Логи безопасности:</strong> хранятся 1 год</li>
                                <li><strong>Резервные копии:</strong> удаляются в течение 90 дней после удаления основных данных</li>
                            </ul>
                            <p>Базы персональных данных пользователей из РФ хранятся на серверах, расположенных на территории Российской Федерации.</p>

                            <h3>8.1. Защита данных несовершеннолетних</h3>
                            <p>Наш сервис предназначен для лиц старше 18 лет. Мы не собираем намеренно персональные данные несовершеннолетних. Если вы являетесь родителем или законным представителем и обнаружили, что ребёнок предоставил нам данные, свяжитесь с нами для их удаления.</p>

                            <h3>8.2. Международная передача данных</h3>
                            <p>Основная обработка данных производится на территории Российской Федерации. Трансграничная передача возможна только при наличии законных оснований и обеспечении необходимого уровня защиты персональных данных.</p>

                            <h3>8.3. Изменения документа</h3>
                            <p>Мы можем обновлять настоящую Политику конфиденциальности. О существенных изменениях мы уведомим вас по электронной почте или через уведомление на сайте за 30 дней до вступления изменений в силу.</p>

                            <h3>8.4. Применимое право и юрисдикция</h3>
                            <p>Настоящая Политика конфиденциальности регулируется законодательством Российской Федерации. Любые споры подлежат рассмотрению в судах г. Москвы.</p>
                        </section>

                        <section class="legal-contact-card" id="contacts">
                            <h2><i class="fas fa-envelope"></i> Контактная информация</h2>
                            <p>Если у вас есть вопросы о настоящей Политике конфиденциальности или вы хотите воспользоваться своими правами, свяжитесь с нами:</p>
                            <ul>
                                <li><strong>Email:</strong> <a href="mailto:privacy@hyperpc.ru">privacy@hyperpc.ru</a></li>
                                <li><strong>Адрес:</strong> Москва, Россия</li>
                                <li><strong>Ответственный за обработку данных:</strong> отдел по работе с персональными данными HyperPC</li>
                            </ul>
                        </section>

                        <div class="legal-footer-note">
                            <p>Мы обязуемся ответить на запрос, связанный с обработкой персональных данных, в течение 30 дней с момента получения обращения.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
