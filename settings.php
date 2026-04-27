<?php
session_start();
require_once 'config.php';
require_once 'includes/security.php';

function ensureSettingsColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $columns = [
        'username_changed_at' => "ALTER TABLE `users` ADD COLUMN `username_changed_at` datetime DEFAULT NULL AFTER `profile_updated`",
        'notify_order_updates' => "ALTER TABLE `users` ADD COLUMN `notify_order_updates` tinyint(1) NOT NULL DEFAULT 1 AFTER `username_changed_at`",
        'notify_support_replies' => "ALTER TABLE `users` ADD COLUMN `notify_support_replies` tinyint(1) NOT NULL DEFAULT 1 AFTER `notify_order_updates`",
        'profile_visibility' => "ALTER TABLE `users` ADD COLUMN `profile_visibility` enum('public','members','private') NOT NULL DEFAULT 'public' AFTER `notify_support_replies`",
        'show_online_status' => "ALTER TABLE `users` ADD COLUMN `show_online_status` tinyint(1) NOT NULL DEFAULT 1 AFTER `profile_visibility`",
        'session_version' => "ALTER TABLE `users` ADD COLUMN `session_version` int(11) NOT NULL DEFAULT 1 AFTER `show_online_status`",
        'session_invalidated_at' => "ALTER TABLE `users` ADD COLUMN `session_invalidated_at` datetime DEFAULT NULL AFTER `session_version`",
    ];

    foreach ($columns as $column => $alterSql) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `users` LIKE ?");
            $stmt->execute([$column]);
            if (!$stmt->fetch()) {
                $pdo->exec($alterSql);
            }
        } catch (PDOException $e) {
            error_log('Failed ensuring column ' . $column . ': ' . $e->getMessage());
        }
    }

    $checked = true;
}

ensureSettingsColumns($pdo);

function ensureSessionTable(PDO $pdo): void
{
    static $created = false;
    if ($created) {
        return;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS `user_sessions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `session_hash` VARCHAR(128) NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `platform` VARCHAR(50) DEFAULT NULL,
        `browser` VARCHAR(50) DEFAULT NULL,
        `device` VARCHAR(50) DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        `last_seen` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_session_hash` (`session_hash`),
        KEY `idx_user_sessions_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($createSql);
    } catch (PDOException $e) {
        Security::logSecurityEvent('Failed to ensure user_sessions table', ['error' => $e->getMessage()]);
    }

    $created = true;
}

ensureSessionTable($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$messages = [
    'success' => [],
    'error' => [],
];
$schemaIssues = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    die('Database error');
}

$csrfToken = Security::generateCSRFToken();
$now = new DateTime();
$usernameCooldownDays = 90;
$usernameChangedAt = !empty($user['username_changed_at']) ? new DateTime($user['username_changed_at']) : null;
$nextUsernameChange = $usernameChangedAt ? (clone $usernameChangedAt)->modify('+' . $usernameCooldownDays . ' days') : null;
$canChangeUsername = !$usernameChangedAt || $now >= $nextUsernameChange;
$profileVisibilityOptions = [
    'public' => 'Виден всем',
    'members' => 'Только зарегистрированные',
    'private' => 'Только по прямой ссылке',
];
$currentVisibility = $user['profile_visibility'] ?? 'public';
$currentShowOnline = !empty($user['show_online_status']);
$currentSessionHash = session_id() ? hash('sha256', session_id()) : null;
$sessionHistory = [];

try {
    $sessionStmt = $pdo->prepare("SELECT session_hash, ip_address, ip_location, platform, browser, device, created_at, last_seen FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
    $sessionStmt->execute([$userId]);
    $sessionHistory = $sessionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $sessionHistory = [];
}

function addSuccess(array &$messages, string $text): void
{
    $messages['success'][] = $text;
}

function addError(array &$messages, string $text): void
{
    $messages['error'][] = $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $messages['error'][] = 'Недействительный CSRF токен';
    } else {
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!Security::verifyPassword($currentPassword, $user['password'])) {
                $messages['error'][] = 'Текущий пароль введён неверно';
            } elseif ($newPassword !== $confirmPassword) {
                $messages['error'][] = 'Новый пароль и подтверждение не совпадают';
            } else {
                $validationResult = Security::validatePassword($newPassword);
                if ($validationResult !== true) {
                    $messages['error'][] = is_array($validationResult)
                        ? implode('<br>', $validationResult)
                        : $validationResult;
                } else {
                    $hashed = Security::hashPassword($newPassword);
                    try {
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$hashed, $userId]);
                        addSuccess($messages, 'Пароль успешно обновлён');
                    } catch (PDOException $e) {
                        addError($messages, 'Не удалось обновить пароль');
                    }
                }
            }
        }

        if (isset($_POST['change_username'])) {
            $newUsername = trim($_POST['new_username'] ?? '');
            if (!$canChangeUsername) {
                addError($messages, 'Изменение имени будет доступно ' . ($nextUsernameChange ? $nextUsernameChange->format('d.m.Y') : 'позже'));
            } else {
                $validation = Security::validateUsername($newUsername);
                if ($validation !== true) {
                    addError($messages, $validation);
                } elseif (strcasecmp($newUsername, $user['username']) === 0) {
                    addError($messages, 'Новое имя совпадает с текущим');
                } else {
                    try {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
                        $checkStmt->execute([$newUsername, $userId]);
                        if ($checkStmt->fetchColumn() > 0) {
                            addError($messages, 'Пользователь с таким именем уже существует');
                        } else {
                            $pdo->beginTransaction();
                            $updateStmt = $pdo->prepare("UPDATE users SET username = ?, username_changed_at = NOW(), updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$newUsername, $userId]);
                            $_SESSION['username'] = $newUsername;
                            $pdo->commit();
                            addSuccess($messages, 'Имя пользователя успешно изменено');
                            $user['username'] = $newUsername;
                            $user['username_changed_at'] = date('Y-m-d H:i:s');
                            $canChangeUsername = false;
                            $nextUsernameChange = (new DateTime())->modify('+' . $usernameCooldownDays . ' days');
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        addError($messages, 'Ошибка при изменении имени пользователя');
                    }
                }
            }
        }

        if (isset($_POST['update_preferences'])) {
            $orderNotifications = isset($_POST['notify_order_updates']) ? 1 : 0;
            $supportNotifications = isset($_POST['notify_support_replies']) ? 1 : 0;
            try {
                $prefStmt = $pdo->prepare("UPDATE users SET notify_order_updates = ?, notify_support_replies = ?, updated_at = NOW() WHERE id = ?");
                $prefStmt->execute([$orderNotifications, $supportNotifications, $userId]);
                addSuccess($messages, 'Настройки уведомлений обновлены');
                $user['notify_order_updates'] = $orderNotifications;
                $user['notify_support_replies'] = $supportNotifications;
            } catch (PDOException $e) {
                addError($messages, 'Не удалось сохранить настройки уведомлений');
            }
        }

        if (isset($_POST['update_privacy'])) {
            $visibility = $_POST['profile_visibility'] ?? 'public';
            if (!array_key_exists($visibility, $profileVisibilityOptions)) {
                addError($messages, 'Недопустимое значение видимости профиля');
            } else {
                $showOnline = isset($_POST['show_online_status']) ? 1 : 0;
                try {
                    $privacyStmt = $pdo->prepare("UPDATE users SET profile_visibility = ?, show_online_status = ?, updated_at = NOW() WHERE id = ?");
                    $privacyStmt->execute([$visibility, $showOnline, $userId]);
                    addSuccess($messages, 'Параметры приватности обновлены');
                    $user['profile_visibility'] = $visibility;
                    $user['show_online_status'] = $showOnline;
                    $currentVisibility = $visibility;
                    $currentShowOnline = (bool)$showOnline;
                } catch (PDOException $e) {
                    addError($messages, 'Не удалось сохранить приватность');
                }
            }
        }

        if (isset($_POST['invalidate_sessions'])) {
            try {
                $invalidateStmt = $pdo->prepare("UPDATE users SET session_version = session_version + 1, session_invalidated_at = NOW(), remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
                $invalidateStmt->execute([$userId]);
                $user['session_version'] = ($user['session_version'] ?? 1) + 1;
                $user['session_invalidated_at'] = date('Y-m-d H:i:s');
                $_SESSION['session_version'] = $user['session_version'];
                Security::forgetRememberMe($pdo, $userId);
                if ($currentSessionHash) {
                    $deleteStmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_hash <> ?");
                    $deleteStmt->execute([$userId, $currentSessionHash]);
                } else {
                    $deleteStmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                    $deleteStmt->execute([$userId]);
                }
                $sessionStmt = $pdo->prepare("SELECT session_hash, ip_address, ip_location, platform, browser, device, created_at, last_seen FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
                $sessionStmt->execute([$userId]);
                $sessionHistory = $sessionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                Security::logSecurityEvent('Sessions invalidated by user', ['user_id' => $userId]);
                addSuccess($messages, 'Все активные сессии будут закрыты. Перелогиньтесь на других устройствах.');
            } catch (PDOException $e) {
                addError($messages, 'Не удалось завершить другие сессии');
            }
        }
    }
}

$sections = [
    'profile' => ['label' => 'Профиль', 'icon' => 'fa-user-pen'],
    'security' => ['label' => 'Безопасность', 'icon' => 'fa-shield-keyhole'],
    'notifications' => ['label' => 'Уведомления', 'icon' => 'fa-bell'],
    'privacy' => ['label' => 'Приватность', 'icon' => 'fa-user-shield'],
    'interface' => ['label' => 'Интерфейс', 'icon' => 'fa-laptop'],
    'sessions' => ['label' => 'Сессии', 'icon' => 'fa-right-from-bracket'],
];

$enabledNotifications = (int)!empty($user['notify_order_updates']) + (int)!empty($user['notify_support_replies']);
$sessionCount = count($sessionHistory);
$visibilityLabel = $profileVisibilityOptions[$currentVisibility] ?? 'Виден всем';
$roleClass = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string)($user['role'] ?? 'user')));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки аккаунта - <?= SITE_NAME ?></title>
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
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/settings.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="settings-page-view">
    <?php include 'includes/header.php'; ?>

    <main class="settings-page">
        <div class="container">
            <header class="settings-hero">
                <div class="settings-hero__copy">
                    <p class="settings-eyebrow">Настройки аккаунта</p>
                    <h1>Меняйте данные и безопасность без лишнего шума</h1>
                    <p class="settings-subtitle">Все основные параметры HyperPC собраны в одном рабочем экране: имя, пароль, уведомления, приватность и активные сессии.</p>
                    <div class="settings-summary-row">
                        <div class="settings-summary-pill">
                            <span>Уведомления</span>
                            <strong><?= $enabledNotifications ?>/2 активны</strong>
                        </div>
                        <div class="settings-summary-pill">
                            <span>Приватность</span>
                            <strong><?= htmlspecialchars($visibilityLabel) ?></strong>
                        </div>
                        <div class="settings-summary-pill">
                            <span>Сессии</span>
                            <strong><?= $sessionCount ?> устройств</strong>
                        </div>
                    </div>
                </div>

                <div class="account-summary">
                    <div class="summary-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="account-summary__body">
                        <h2><?= htmlspecialchars($user['username']) ?></h2>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <div class="account-summary__meta">
                            <span class="settings-role-chip role-<?= htmlspecialchars($roleClass) ?>"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
                            <?php if ($canChangeUsername): ?>
                                <span class="settings-status-chip is-positive">Имя можно изменить</span>
                            <?php elseif ($nextUsernameChange): ?>
                                <span class="settings-status-chip">Имя доступно с <?= $nextUsernameChange->format('d.m.Y') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <div class="settings-shell">
                <aside class="settings-sidebar">
                    <div class="settings-sidebar__inner">
                        <div class="settings-sidebar__header">
                            <h2>Разделы</h2>
                            <p>Переключайтесь между настройками без длинного скролла.</p>
                        </div>
                        <nav class="settings-nav" role="tablist" aria-label="Разделы настроек">
                            <?php foreach ($sections as $slug => $info): ?>
                                <button class="settings-nav__button" data-tab="<?= $slug ?>" role="tab" type="button">
                                    <i class="fas <?= $info['icon'] ?>"></i>
                                    <span><?= $info['label'] ?></span>
                                </button>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </aside>

                <div class="settings-main">
                    <?php if (!empty($schemaIssues)): ?>
                        <div class="settings-alert settings-alert--error">
                            <i class="fas fa-database"></i>
                            <span>Базе данных не хватает обязательных колонок. Выполните запросы вручную и обновите страницу.</span>
                        </div>
                        <pre class="schema-alert-code"><?php foreach ($schemaIssues as $statement): ?><?= htmlspecialchars($statement) ?>;
<?php endforeach; ?></pre>
                    <?php endif; ?>

                    <?php foreach ($messages as $type => $list): ?>
                        <?php foreach ($list as $message): ?>
                            <div class="settings-alert settings-alert--<?= $type === 'success' ? 'success' : 'error' ?>">
                                <i class="fas <?= $type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                                <span><?= $message ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <section class="settings-content">
                        <article class="settings-section-card" data-section="profile">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Профиль</p>
                                    <h2><i class="fas fa-user-pen"></i> Имя пользователя</h2>
                                    <p>Сменить никнейм можно раз в 3 месяца. Это помогает защититься от злоупотреблений и путаницы в профилях.</p>
                                </div>
                                <?php if (!$canChangeUsername && $nextUsernameChange): ?>
                                    <span class="settings-badge settings-badge--cooldown">Доступно после <?= $nextUsernameChange->format('d.m.Y') ?></span>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="settings-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form-field">
                                    <label for="new_username">Новое имя</label>
                                    <input type="text" id="new_username" name="new_username" placeholder="Например, NihonJinPro" minlength="3" maxlength="50" <?= $canChangeUsername ? '' : 'disabled' ?>>
                                </div>
                                <button type="submit" name="change_username" class="settings-btn settings-btn--primary" <?= $canChangeUsername ? '' : 'disabled' ?>>
                                    <i class="fas fa-rotate"></i>
                                    <span>Обновить имя</span>
                                </button>
                                <?php if (!$canChangeUsername && $nextUsernameChange): ?>
                                    <div class="settings-note settings-note--cooldown">
                                        <i class="fas fa-hourglass-half"></i>
                                        <div>
                                            <strong>Следующее изменение будет доступно <?= $nextUsernameChange->format('d.m.Y') ?></strong>
                                            <small>Осталось <?= $now->diff($nextUsernameChange)->days ?> дней ожидания.</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </article>

                        <article class="settings-section-card" data-section="security">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Безопасность</p>
                                    <h2><i class="fas fa-shield-keyhole"></i> Смена пароля</h2>
                                    <p>Обновите пароль, если давно этого не делали или если заметили необычную активность в аккаунте.</p>
                                </div>
                            </div>

                            <form method="POST" class="settings-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form-field">
                                    <label for="current_password">Текущий пароль</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <label for="new_password">Новый пароль</label>
                                        <input type="password" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="form-field">
                                        <label for="confirm_password">Подтверждение</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="settings-btn settings-btn--primary">
                                    <i class="fas fa-key"></i>
                                    <span>Сохранить пароль</span>
                                </button>
                            </form>
                        </article>

                        <article class="settings-section-card" data-section="notifications">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Уведомления</p>
                                    <h2><i class="fas fa-bell"></i> Что присылать</h2>
                                    <p>Оставьте только те уведомления, которые реально помогают следить за заказами и ответами поддержки.</p>
                                </div>
                            </div>

                            <form method="POST" class="settings-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <label class="settings-toggle">
                                    <input type="checkbox" name="notify_order_updates" <?= !empty($user['notify_order_updates']) ? 'checked' : '' ?>>
                                    <span>
                                        <strong>Изменения по заказам</strong>
                                        <small>Новые статусы, отправка, счёт и подтверждение заказа.</small>
                                    </span>
                                </label>
                                <label class="settings-toggle">
                                    <input type="checkbox" name="notify_support_replies" <?= !empty($user['notify_support_replies']) ? 'checked' : '' ?>>
                                    <span>
                                        <strong>Ответы поддержки</strong>
                                        <small>Новые сообщения по заказу или обращению в поддержку.</small>
                                    </span>
                                </label>
                                <button type="submit" name="update_preferences" class="settings-btn settings-btn--secondary">
                                    <i class="fas fa-save"></i>
                                    <span>Сохранить уведомления</span>
                                </button>
                            </form>
                        </article>

                        <article class="settings-section-card" data-section="privacy">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Приватность</p>
                                    <h2><i class="fas fa-user-shield"></i> Видимость профиля</h2>
                                    <p>Определите, кто видит ваш профиль и можно ли показывать другим пользователям ваш онлайн-статус.</p>
                                </div>
                            </div>

                            <form method="POST" class="settings-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="settings-radio-group">
                                    <?php foreach ($profileVisibilityOptions as $slug => $label): ?>
                                        <label class="settings-radio-card">
                                            <input type="radio" name="profile_visibility" value="<?= $slug ?>" <?= $currentVisibility === $slug ? 'checked' : '' ?>>
                                            <span>
                                                <strong><?= $label ?></strong>
                                                <small>
                                                    <?php if ($slug === 'public'): ?>
                                                        Профиль доступен всем посетителям.
                                                    <?php elseif ($slug === 'members'): ?>
                                                        Профиль видят только авторизованные пользователи.
                                                    <?php else: ?>
                                                        Профиль скрыт из публичных разделов.
                                                    <?php endif; ?>
                                                </small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <label class="settings-toggle">
                                    <input type="checkbox" name="show_online_status" <?= $currentShowOnline ? 'checked' : '' ?>>
                                    <span>
                                        <strong>Показывать статус "Онлайн"</strong>
                                        <small>Если отключить, другие увидят только время последнего визита.</small>
                                    </span>
                                </label>

                                <button type="submit" name="update_privacy" class="settings-btn settings-btn--secondary">
                                    <i class="fas fa-lock"></i>
                                    <span>Сохранить приватность</span>
                                </button>
                            </form>
                        </article>

                        <article class="settings-section-card" data-section="interface">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Интерфейс</p>
                                    <h2><i class="fas fa-laptop"></i> Отображение HyperPC</h2>
                                    <p>Базовые параметры интерфейса и времени, которые влияют на то, как вы видите сайт каждый день.</p>
                                </div>
                            </div>

                            <div class="settings-form">
                                <div class="settings-inline-row">
                                    <div>
                                        <h3>Тема сайта</h3>
                                        <p>Сменить тему можно и в шапке, но здесь это тоже доступно как отдельная настройка.</p>
                                    </div>
                                    <button class="settings-btn settings-btn--outline" id="toggleThemeButton" type="button">
                                        <i class="fas fa-adjust"></i>
                                        <span>Переключить тему</span>
                                    </button>
                                </div>

                                <div class="settings-inline-row">
                                    <div>
                                        <h3>Часовой пояс</h3>
                                        <p>Время на сайте автоматически подстраивается под зону вашего браузера.</p>
                                    </div>
                                    <span class="timezone-label" id="timezoneLabel"></span>
                                </div>
                            </div>
                        </article>

                        <article class="settings-section-card settings-section-card--service" data-section="sessions">
                            <div class="settings-section-card__header">
                                <div>
                                    <p class="settings-section-card__eyebrow">Сессии</p>
                                    <h2><i class="fas fa-right-from-bracket"></i> Активные устройства</h2>
                                    <p>Используйте этот раздел, если нужно быстро завершить все другие входы и проверить последние авторизации.</p>
                                </div>
                                <?php if (!empty($user['session_invalidated_at'])): ?>
                                    <span class="settings-badge settings-badge--muted">Последнее обнуление: <?= (new DateTime($user['session_invalidated_at']))->format('d.m.Y H:i') ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="settings-session-panel">
                                <div class="settings-session-panel__copy">
                                    <h3>Что произойдёт после сброса</h3>
                                    <ul>
                                        <li>Мы отключим все токены "Запомнить меня".</li>
                                        <li>На других устройствах потребуется повторный вход.</li>
                                        <li>Текущая сессия останется активной.</li>
                                    </ul>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <button type="submit" name="invalidate_sessions" class="settings-btn settings-btn--danger">
                                        <i class="fas fa-right-from-bracket"></i>
                                        <span>Разлогинить все устройства</span>
                                    </button>
                                </form>
                            </div>

                            <div class="session-history">
                                <div class="session-history__header">
                                    <h3>Последние входы</h3>
                                    <p>Список недавних устройств и мест, откуда входили в аккаунт.</p>
                                </div>

                                <?php if (empty($sessionHistory)): ?>
                                    <p class="session-empty">История пока пуста.</p>
                                <?php else: ?>
                                    <ul class="session-history__list">
                                        <?php foreach ($sessionHistory as $record): ?>
                                            <li class="session-item <?= $currentSessionHash && $record['session_hash'] === $currentSessionHash ? 'current' : '' ?>">
                                                <div class="session-device">
                                                    <span class="session-device-label">
                                                        <?= htmlspecialchars($record['device'] ?? 'Устройство') ?> · <?= htmlspecialchars($record['platform'] ?? 'Платформа') ?>
                                                    </span>
                                                    <small><?= htmlspecialchars($record['browser'] ?? '') ?></small>
                                                </div>
                                                <div class="session-meta">
                                                    <span><?= htmlspecialchars($record['ip_address'] ?? 'IP неизвестен') ?></span>
                                                    <span><?= htmlspecialchars($record['ip_location'] ?? 'Локация неизвестна') ?></span>
                                                    <span><?= date('d.m.Y H:i', strtotime($record['created_at'])) ?></span>
                                                </div>
                                                <?php if ($currentSessionHash && $record['session_hash'] === $currentSessionHash): ?>
                                                    <span class="session-tag">Текущее устройство</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <script>
        const navButtons = document.querySelectorAll('.settings-nav__button');
        const sections = document.querySelectorAll('.settings-section-card');
        const availableSections = Array.from(sections).map((section) => section.dataset.section);
        const storedSection = localStorage.getItem('settings_section');
        const activeSection = availableSections.includes(storedSection) ? storedSection : 'profile';

        function activateSection(slug) {
            navButtons.forEach((button) => {
                const match = button.dataset.tab === slug;
                button.classList.toggle('is-active', match);
                button.setAttribute('aria-selected', match ? 'true' : 'false');
            });

            sections.forEach((section) => {
                section.classList.toggle('is-active', section.dataset.section === slug);
            });

            localStorage.setItem('settings_section', slug);
        }

        navButtons.forEach((button) => {
            button.addEventListener('click', () => activateSection(button.dataset.tab));
        });

        activateSection(activeSection);

        const themeButton = document.getElementById('toggleThemeButton');
        if (themeButton) {
            themeButton.addEventListener('click', () => {
                const root = document.documentElement;
                const newTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }

        const timezoneLabel = document.getElementById('timezoneLabel');
        if (timezoneLabel) {
            timezoneLabel.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }
    </script>
    <script src="js/main.js"></script>
</body>
</html>
