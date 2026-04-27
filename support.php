<?php
session_start();
require_once 'config.php';
require_once 'includes/security.php';

$error = '';
$success = '';
$csrfToken = Security::generateCSRFToken();

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Недействительный запрос';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $category = $_POST['category'] ?? '';
        $message = trim($_POST['message'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');

        if (empty($subject) || empty($category) || empty($message)) {
            $error = 'Пожалуйста, заполните все обязательные поля';
        } elseif (!isset($_SESSION['user_id']) && (empty($email) || empty($name))) {
            $error = 'Пожалуйста, укажите ваше имя и email';
        } else {
            try {
                $userId = $_SESSION['user_id'] ?? null;
                $ticketNumber = 'TKT-' . strtoupper(bin2hex(random_bytes(4)));

                $stmt = $pdo->prepare("
                    INSERT INTO support_tickets (user_id, ticket_number, subject, category, message, contact_email, contact_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
                ");
                $stmt->execute([
                    $userId,
                    $ticketNumber,
                    $subject,
                    $category,
                    $message,
                    $email ?: ($_SESSION['email'] ?? null),
                    $name ?: ($_SESSION['username'] ?? null)
                ]);

                $success = "Ваше обращение успешно отправлено! Номер тикета: <strong>$ticketNumber</strong>";

                Security::logSecurityEvent('Support ticket created', [
                    'ticket_number' => $ticketNumber,
                    'user_id' => $userId
                ]);
            } catch (PDOException $e) {
                $error = 'Ошибка отправки обращения. Попробуйте позже';
            }
        }
    }
}

// Get user's tickets if logged in
$userTickets = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*,
                   COUNT(tr.id) as replies_count,
                   MAX(tr.created_at) as last_reply
            FROM support_tickets t
            LEFT JOIN ticket_replies tr ON t.id = tr.ticket_id
            WHERE t.user_id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $userTickets = $stmt->fetchAll();
    } catch (PDOException $e) {
        $userTickets = [];
    }
}

$statusLabels = [
    'open' => 'Открыт',
    'in-progress' => 'В работе',
    'resolved' => 'Решён',
    'closed' => 'Закрыт'
];

$categoryLabels = [
    'technical' => 'Техническая проблема',
    'account' => 'Вопрос по аккаунту',
    'billing' => 'Оплата и заказы',
    'suggestion' => 'Предложение',
    'other' => 'Другое'
];

$hasUser = isset($_SESSION['user_id']);
$supportLoginUrl = 'login.php?redirect=' . urlencode('support.php');
$formValues = [
    'name' => $success ? '' : trim($_POST['name'] ?? ''),
    'email' => $success ? '' : trim($_POST['email'] ?? ''),
    'category' => $success ? '' : ($_POST['category'] ?? ''),
    'subject' => $success ? '' : trim($_POST['subject'] ?? ''),
    'message' => $success ? '' : trim($_POST['message'] ?? '')
];

$activeTickets = 0;
$resolvedTickets = 0;
$totalReplies = 0;

foreach ($userTickets as $ticket) {
    if (in_array($ticket['status'], ['open', 'in-progress'], true)) {
        $activeTickets++;
    }
    if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
        $resolvedTickets++;
    }
    $totalReplies += (int)($ticket['replies_count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поддержка - <?= SITE_NAME ?></title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/support.css?v=<?= time() ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="support-page support-hub-page">
        <div class="container">
            <section class="support-hero support-hero--hub">
                <div class="support-hero__main">
                    <span class="support-kicker">
                        <i class="fas fa-headset"></i>
                        Support Center
                    </span>
                    <h1>Поддержка HyperPC без лишних шагов</h1>
                    <p>Опишите вопрос, сохраните его в тикете и продолжайте переписку в одном рабочем потоке, без поиска писем и лишних переходов.</p>

                    <div class="support-points">
                        <div class="support-point">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <strong>Один поток на каждый вопрос</strong>
                                <span>Тема, статус и история ответов остаются в одной карточке.</span>
                            </div>
                        </div>
                        <div class="support-point">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Статус виден сразу</strong>
                                <span>Открыт, в работе или решён: текущее состояние считывается без раскрытия деталей.</span>
                            </div>
                        </div>
                        <div class="support-point">
                            <i class="fas fa-comments"></i>
                            <div>
                                <strong>Продолжение диалога на сайте</strong>
                                <span>Не нужно пересоздавать обращение, если у тикета уже идёт переписка.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="support-hero__stats">
                    <?php if ($hasUser): ?>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Всего тикетов</span>
                            <strong><?= count($userTickets) ?></strong>
                            <span class="support-stat-card__note">Последние обращения в личной ленте</span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Активные</span>
                            <strong><?= $activeTickets ?></strong>
                            <span class="support-stat-card__note">Открыты или сейчас в работе</span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Ответы</span>
                            <strong><?= $totalReplies ?></strong>
                            <span class="support-stat-card__note">Всего сообщений от команды поддержки</span>
                        </div>
                    <?php else: ?>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Как это работает</span>
                            <strong>1</strong>
                            <span class="support-stat-card__note">Создаёте обращение через форму слева</span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Дальше</span>
                            <strong>2</strong>
                            <span class="support-stat-card__note">Получаете номер тикета и продолжаете диалог по нему</span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Удобнее с аккаунтом</span>
                            <strong>3</strong>
                            <span class="support-stat-card__note">История обращений и ответы хранятся прямо в профиле</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="support-layout support-layout--hub">
                <section class="support-surface support-composer">
                    <div class="support-surface__header">
                        <div>
                            <span class="support-surface__eyebrow">Создать обращение</span>
                            <h2 class="section-title">Опишите вопрос один раз</h2>
                            <p class="section-text">Укажите категорию, тему и детали. Если проблема требует продолжения, всё общение останется в карточке тикета.</p>
                        </div>
                        <span class="support-surface__badge">
                            <i class="fas fa-ticket-alt"></i>
                            Новый тикет
                        </span>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?= $success ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="support-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <?php if (!$hasUser): ?>
                            <div class="support-form__grid support-form__grid--identity">
                                <div class="form-group">
                                    <label for="support-name">
                                        <i class="fas fa-user"></i>
                                        Ваше имя *
                                    </label>
                                    <input
                                        id="support-name"
                                        type="text"
                                        name="name"
                                        placeholder="Как к вам обращаться"
                                        value="<?= htmlspecialchars($formValues['name']) ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="support-email">
                                        <i class="fas fa-envelope"></i>
                                        Email *
                                    </label>
                                    <input
                                        id="support-email"
                                        type="email"
                                        name="email"
                                        placeholder="you@example.com"
                                        value="<?= htmlspecialchars($formValues['email']) ?>"
                                        required
                                    >
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="support-category">
                                <i class="fas fa-tag"></i>
                                Категория *
                            </label>
                            <select id="support-category" name="category" required>
                                <option value="">Выберите категорию</option>
                                <?php foreach ($categoryLabels as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $formValues['category'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="support-subject">
                                <i class="fas fa-heading"></i>
                                Тема обращения *
                            </label>
                            <input
                                id="support-subject"
                                type="text"
                                name="subject"
                                placeholder="Кратко сформулируйте проблему или вопрос"
                                maxlength="200"
                                value="<?= htmlspecialchars($formValues['subject']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="support-message">
                                <i class="fas fa-comment-dots"></i>
                                Сообщение *
                            </label>
                            <textarea
                                id="support-message"
                                name="message"
                                placeholder="Опишите проблему подробнее: что произошло, что вы ожидали и что уже пробовали сделать."
                                required
                            ><?= htmlspecialchars($formValues['message']) ?></textarea>
                        </div>

                        <div class="support-form__footer">
                            <p class="support-form__note">
                                Если тикет уже существует, продолжайте диалог в его карточке справа, а не создавайте новый вопрос заново.
                            </p>
                            <button type="submit" name="submit_ticket" class="btn-submit">
                                <i class="fas fa-paper-plane"></i>
                                Отправить обращение
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="support-side">
                    <section class="support-surface support-ticket-panel">
                        <div class="support-surface__header">
                            <div>
                                <span class="support-surface__eyebrow"><?= $hasUser ? 'Мои обращения' : 'Работа с тикетами' ?></span>
                                <h2 class="section-title"><?= $hasUser ? 'Последние тикеты' : 'Лента обращений доступна после входа' ?></h2>
                                <p class="section-text">
                                    <?= $hasUser
                                        ? 'Открывайте тикет, чтобы посмотреть статус, ответы и продолжить переписку.'
                                        : 'С аккаунтом проще отслеживать статусы и возвращаться к уже созданным обращениям.' ?>
                                </p>
                            </div>
                            <?php if ($hasUser): ?>
                                <span class="support-surface__badge">
                                    <i class="fas fa-inbox"></i>
                                    <?= count($userTickets) ?> в ленте
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasUser): ?>
                            <?php if (empty($userTickets)): ?>
                                <div class="support-empty">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Обращений пока нет</h3>
                                    <p>Когда вы отправите первый тикет, он появится здесь вместе со статусом и ответами поддержки.</p>
                                </div>
                            <?php else: ?>
                                <div class="ticket-list">
                                    <?php foreach ($userTickets as $ticket): ?>
                                        <?php
                                        $lastActivity = $ticket['last_reply'] ?: $ticket['created_at'];
                                        $statusLabel = $statusLabels[$ticket['status']] ?? $ticket['status'];
                                        $categoryLabel = $categoryLabels[$ticket['category']] ?? $ticket['category'];
                                        ?>
                                        <a class="ticket-card" href="ticket-details.php?id=<?= (int)$ticket['id'] ?>">
                                            <div class="ticket-card__top">
                                                <div class="ticket-card__identity">
                                                    <span class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                                                    <span class="ticket-category"><?= htmlspecialchars($categoryLabel) ?></span>
                                                </div>
                                                <span class="ticket-status status-<?= htmlspecialchars($ticket['status']) ?>">
                                                    <?= htmlspecialchars($statusLabel) ?>
                                                </span>
                                            </div>

                                            <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>

                                            <div class="ticket-meta">
                                                <span><i class="fas fa-calendar-alt"></i><?= date('d.m.Y', strtotime($ticket['created_at'])) ?></span>
                                                <span><i class="fas fa-comment"></i><?= (int)$ticket['replies_count'] ?> ответов</span>
                                            </div>

                                            <div class="ticket-card__footer">
                                                <span>Последняя активность</span>
                                                <strong><?= date('d.m.Y H:i', strtotime($lastActivity)) ?></strong>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="support-login-block">
                                <div class="support-login-block__icon">
                                    <i class="fas fa-user-lock"></i>
                                </div>
                                <h3>Войдите, чтобы видеть историю обращений</h3>
                                <p>После входа вы сможете открыть любой тикет, посмотреть ответы поддержки и продолжить диалог без повторного описания проблемы.</p>
                                <a class="support-login-button" href="<?= htmlspecialchars($supportLoginUrl) ?>">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Войти в аккаунт
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
