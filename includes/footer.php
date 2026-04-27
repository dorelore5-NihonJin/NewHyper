<?php $currentYear = date('Y'); ?>

<footer class="site-footer">
    <div class="container">
        <div class="site-footer__shell">
            <div class="site-footer__grid">
                <section class="site-footer__brand" aria-labelledby="siteFooterBrand">
                    <span class="site-footer__eyebrow">HyperPC</span>
                    <h2 id="siteFooterBrand">Конфигуратор, каталог и готовые сборки в одном месте</h2>
                    <p>
                        Подбор совместимых комплектующих, оценка FPS и оформление заказа без ручной проверки десятков параметров.
                    </p>

                    <div class="site-footer__actions">
                        <a href="builder.php" class="btn btn-primary site-footer__cta">
                            <i class="fas fa-screwdriver-wrench"></i>
                            <span>Собрать ПК</span>
                        </a>
                    </div>
                </section>

                <nav class="site-footer__column site-footer__column--nav" aria-labelledby="siteFooterNav">
                    <h3 id="siteFooterNav">Разделы</h3>
                    <ul class="site-footer__list">
                        <li><a href="catalog.php">Каталог</a></li>
                        <li><a href="builder.php">Сборка ПК</a></li>
                        <li><a href="builds.php">Готовые сборки</a></li>
                        <li><a href="reviews.php">Обзоры</a></li>
                        <li><a href="support.php">Поддержка</a></li>
                    </ul>
                </nav>

                <section class="site-footer__column site-footer__column--meta" aria-labelledby="siteFooterMeta">
                    <h3 id="siteFooterMeta">Контакты и документы</h3>
                    <ul class="site-footer__list site-footer__list--details">
                        <li>
                            <span class="site-footer__label">Почта</span>
                            <a href="mailto:info@hyperpc.ru">info@hyperpc.ru</a>
                        </li>
                        <li>
                            <span class="site-footer__label">Поддержка</span>
                            <a href="mailto:support@hyperpc.ru">support@hyperpc.ru</a>
                        </li>
                        <li>
                            <span class="site-footer__label">Локация</span>
                            <span class="site-footer__text">Москва, Россия</span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="site-footer__bottom">
                <div class="site-footer__bottom-copy">
                    <p>&copy; <?= $currentYear ?> HyperPC. Все права защищены.</p>
                    <span>Премиальный подбор ПК под игры, работу и апгрейд.</span>
                </div>

                <div class="site-footer__bottom-links" aria-label="Юридическая информация">
                    <a href="privacy.php">Конфиденциальность</a>
                    <a href="terms.php">Условия использования</a>
                    <a href="cookie-policy.php">Политика cookie</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="js/timezone.js"></script>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
    function updateActivity() {
        fetch('api/update_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        }).catch(() => {});
    }

    updateActivity();

    setInterval(updateActivity, 120000);

    let activityTimeout;
    function scheduleActivityUpdate() {
        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(updateActivity, 5000);
    }

    document.addEventListener('mousemove', scheduleActivityUpdate);
    document.addEventListener('keypress', scheduleActivityUpdate);
    document.addEventListener('click', scheduleActivityUpdate);
    document.addEventListener('scroll', scheduleActivityUpdate);
</script>
<?php endif; ?>

<?php include __DIR__ . '/cookie-banner.php'; ?>
