<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$notifications = [];
$unreadNotificationCount = 0;
$authUser = null;
$showBlockedOverlay = false;
$blockedOverlayMeta = [];
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, order_id, type, title, message, is_read, created_at
            FROM order_notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($notifications as $notification) {
            if (empty($notification['is_read'])) {
                $unreadNotificationCount++;
            }
        }

        $userStmt = $pdo->prepare("SELECT username, email, avatar, role, status, blocked_at, blocked_until, block_reason FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$_SESSION['user_id']]);
        $authUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $allowPages = ['support.php', 'privacy.php', 'terms.php', 'cookie-policy.php'];
        if ($authUser && ($authUser['status'] ?? '') === 'blocked' && !in_array($currentPage, $allowPages, true)) {
            $showBlockedOverlay = true;
            $reasonText = trim($authUser['block_reason'] ?? '') ?: 'Причина не указана';

            if (!empty($authUser['blocked_until'])) {
                $blockedUntilDate = new DateTime($authUser['blocked_until'], new DateTimeZone('UTC'));
                $blockedOverlayMeta['duration'] = 'До ' . $blockedUntilDate->format('d.m.Y H:i') . ' (UTC)';
            } else {
                $blockedOverlayMeta['duration'] = 'На неопределённый срок';
            }

            $blockedOverlayMeta['reason'] = $reasonText;
            $blockedOverlayMeta['blocked_at'] = !empty($authUser['blocked_at'])
                ? (new DateTime($authUser['blocked_at']))->format('d.m.Y H:i')
                : null;
        }
    } catch (PDOException $e) {
        $notifications = [];
        $unreadNotificationCount = 0;
        $authUser = null;
    }
}

$navigationItems = [
    'index.php' => 'Главная',
    'catalog.php' => 'Каталог',
    'builder.php' => 'Сборка ПК',
    'builds.php' => 'Готовые сборки',
    'reviews.php' => 'Обзоры',
];

$profileLink = 'profile.php';
if (!empty($_SESSION['username'])) {
    $profileLink = 'profile.php?username=' . urlencode($_SESSION['username']);
}

$typeIcons = [
    'status' => 'fa-arrows-rotate',
    'support' => 'fa-headset',
    'system' => 'fa-circle-info',
];

$typeLabels = [
    'status' => 'Обновление заказа',
    'support' => 'Техподдержка',
    'system' => 'Система',
];

$userAvatar = $authUser['avatar'] ?? null;
$userRole = $authUser['role'] ?? 'user';
$userEmail = $authUser['email'] ?? ($_SESSION['email'] ?? '');
?>

<?php if ($showBlockedOverlay): ?>
<div class="blocked-self-overlay" aria-hidden="false">
    <div class="blocked-self-modal">
        <div class="blocked-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h2>Аккаунт временно недоступен</h2>
        <p class="blocked-subtitle">Ваш профиль заблокирован модератором. Доступ к сайту ограничен до окончания блокировки.</p>
        <div class="blocked-meta">
            <div>
                <span>Статус</span>
                <strong><?= htmlspecialchars($blockedOverlayMeta['duration'] ?? '') ?></strong>
            </div>
            <?php if (!empty($blockedOverlayMeta['blocked_at'])): ?>
            <div>
                <span>Дата блокировки</span>
                <strong><?= htmlspecialchars($blockedOverlayMeta['blocked_at']) ?></strong>
            </div>
            <?php endif; ?>
        </div>
        <div class="blocked-reason">
            <span>Причина</span>
            <p><?= nl2br(htmlspecialchars($blockedOverlayMeta['reason'] ?? 'Причина не указана')) ?></p>
        </div>
        <div class="blocked-note">
            Обратитесь в <a href="support.php">поддержку</a>, если считаете блокировку ошибочной.
        </div>
    </div>
</div>
<?php endif; ?>

<header class="header site-header">
    <div class="container">
        <nav class="navbar header-bar" aria-label="Основная навигация">
            <a href="index.php" class="logo logo-image header-brand" aria-label="HyperPC">
                <img class="logo-dark" src="pictures/JKT_full_clear.png" alt="HyperPC">
                <img class="logo-light" src="pictures/JKT_full_clear_bl.png" alt="HyperPC">
            </a>

            <ul class="nav-menu header-nav">
                <?php foreach ($navigationItems as $path => $label): ?>
                    <li>
                        <a href="<?= $path ?>" <?= $currentPage === $path ? 'class="active"' : '' ?>><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="nav-actions header-actions">
                <button class="btn-icon header-icon-button header-theme-toggle" id="themeToggle" type="button" title="Переключить тему" aria-label="Переключить тему">
                    <i class="fas fa-moon"></i>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="header-dropdown-group notifications-menu">
                        <button
                            class="header-notification-button<?= $unreadNotificationCount ? ' has-unread' : '' ?>"
                            id="notificationsButton"
                            type="button"
                            aria-label="Открыть уведомления"
                            aria-controls="notificationsDropdown"
                            aria-expanded="false"
                        >
                            <span class="header-notification-button__icon">
                                <i class="fas fa-bell"></i>
                            </span>
                            <?php if ($unreadNotificationCount > 0): ?>
                                <span class="notification-dot"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="notification-dropdown header-dropdown-panel" id="notificationsDropdown">
                            <div class="notification-dropdown-header">
                                <div>
                                    <div class="notification-title">Уведомления</div>
                                    <div class="notification-subtitle">
                                        <?= $unreadNotificationCount > 0 ? 'Непрочитанных: ' . $unreadNotificationCount : 'Все уведомления прочитаны' ?>
                                    </div>
                                </div>
                                <button class="notification-settings" id="notificationFiltersToggle" type="button" title="Фильтры" aria-expanded="false">
                                    <i class="fas fa-sliders"></i>
                                </button>
                                <div class="notification-filters" id="notificationFilters">
                                    <div class="notification-filters-header">
                                        <span>Фильтровать</span>
                                        <button class="notification-filters-reset" id="notificationFiltersReset" type="button">
                                            Сбросить
                                        </button>
                                    </div>
                                    <div class="notification-filters-group">
                                        <label class="notification-filter-option">
                                            <input type="checkbox" class="notification-filter-checkbox" data-filter="status" checked>
                                            <span>Обновления заказа</span>
                                        </label>
                                        <label class="notification-filter-option">
                                            <input type="checkbox" class="notification-filter-checkbox" data-filter="support" checked>
                                            <span>Техподдержка</span>
                                        </label>
                                        <label class="notification-filter-option">
                                            <input type="checkbox" class="notification-filter-checkbox" data-filter="system" checked>
                                            <span>Система</span>
                                        </label>
                                    </div>
                                    <div class="notification-filters-divider"></div>
                                    <label class="notification-filter-option notification-filter-toggle">
                                        <span>Показывать прочитанные</span>
                                        <input type="checkbox" class="notification-filter-checkbox" data-filter="showRead" id="filterShowRead" checked>
                                    </label>
                                </div>
                            </div>

                            <div class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-empty">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>Пока нет уведомлений</p>
                                        <span>Мы сообщим, когда появятся новости по вашим заказам</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <?php $type = $notification['type'] ?? 'system'; ?>
                                        <div
                                            class="notification-item<?= empty($notification['is_read']) ? ' unread' : '' ?> header-notification-item"
                                            data-type="<?= htmlspecialchars($type) ?>"
                                            data-read="<?= !empty($notification['is_read']) ? '1' : '0' ?>"
                                            data-order-id="<?= $notification['order_id'] ? (int)$notification['order_id'] : 0 ?>"
                                            data-notification-type="<?= htmlspecialchars($type) ?>"
                                        >
                                            <div class="notification-icon">
                                                <i class="fas <?= $typeIcons[$type] ?? 'fa-circle-info' ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <div class="notification-type"><?= $typeLabels[$type] ?? 'Уведомление' ?></div>
                                                <div class="notification-title-line"><?= htmlspecialchars($notification['title'] ?? 'Новость по заказу') ?></div>
                                                <div class="notification-text"><?= htmlspecialchars($notification['message'] ?? '') ?></div>
                                                <div class="notification-date"><?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="notification-empty notification-empty-filter" id="notificationFiltersEmpty" style="display: none;">
                                        <i class="fas fa-filter"></i>
                                        <p>Нет уведомлений по выбранным фильтрам</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="notification-footer">
                                <a href="orders.php" class="notification-link">
                                    <span>Перейти к заказам</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="header-dropdown-group user-menu">
                        <button
                            class="header-user-button"
                            id="userMenuButton"
                            type="button"
                            aria-label="Открыть меню профиля"
                            aria-controls="userDropdown"
                            aria-expanded="false"
                        >
                            <?php if ($userAvatar): ?>
                                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="header-user-button__avatar">
                            <?php else: ?>
                                <span class="header-user-button__avatar header-user-button__avatar--fallback">
                                    <i class="fas fa-user-circle"></i>
                                </span>
                            <?php endif; ?>
                            <span class="header-user-button__body">
                                <span class="header-user-button__name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                                <small>Аккаунт</small>
                            </span>
                            <i class="fas fa-chevron-down header-user-button__chevron"></i>
                        </button>

                        <div class="user-dropdown header-dropdown-panel" id="userDropdown">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php if ($userAvatar): ?>
                                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="user-avatar__image">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <a href="<?= $profileLink ?>" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>Мой профиль</span>
                            </a>
                            <a href="orders.php" class="dropdown-item">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Мои заказы</span>
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Настройки</span>
                            </a>

                            <?php if (in_array($userRole, ['support', 'admin', 'high-admin', 'owner'], true)): ?>
                                <div class="dropdown-divider"></div>
                                <a href="support_orders.php" class="dropdown-item dropdown-item--service">
                                    <i class="fas fa-headset"></i>
                                    <span>Проверка заказов</span>
                                </a>
                                <?php if (in_array($userRole, ['support', 'owner'], true)): ?>
                                    <a href="support_tickets.php" class="dropdown-item dropdown-item--service">
                                        <i class="fas fa-comments"></i>
                                        <span>Проверка обращений</span>
                                    </a>
                                    <a href="moderate_reviews.php" class="dropdown-item dropdown-item--service">
                                        <i class="fas fa-star-half-stroke"></i>
                                        <span>Проверка обзоров</span>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item dropdown-item--logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Выйти</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-login header-login" title="Войти в аккаунт">
                        <i class="fas fa-user"></i>
                        <span>Войти</span>
                    </a>
                <?php endif; ?>

                <button
                    class="btn-icon mobile-menu-toggle header-mobile-toggle"
                    id="mobileMenuToggle"
                    type="button"
                    title="Открыть меню"
                    aria-label="Открыть мобильное меню"
                    aria-controls="mobileNav"
                    aria-expanded="false"
                >
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </nav>

        <div class="mobile-nav header-mobile-nav" id="mobileNav">
            <div class="mobile-nav-links">
                <?php foreach ($navigationItems as $path => $label): ?>
                    <a href="<?= $path ?>" <?= $currentPage === $path ? 'class="active"' : '' ?>><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <div class="mobile-nav-meta">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $profileLink ?>" class="mobile-nav-meta__link">
                        <i class="fas fa-user"></i>
                        <span>Профиль</span>
                    </a>
                    <a href="orders.php" class="mobile-nav-meta__link">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Заказы</span>
                    </a>
                    <a href="settings.php" class="mobile-nav-meta__link">
                        <i class="fas fa-sliders-h"></i>
                        <span>Настройки</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="mobile-nav-meta__link">
                        <i class="fas fa-user"></i>
                        <span>Войти</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
