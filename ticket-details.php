<?php
session_start();
require_once 'config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticketId <= 0) {
    header('Location: support.php');
    exit;
}

$isStaff = false;
$hasAccess = false;
$userId = (int)$_SESSION['user_id'];
$userRole = 'user';

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userRole = $stmt->fetchColumn() ?: 'user';
    $isStaff = in_array($userRole, ['support', 'admin', 'high-admin', 'owner'], true);
} catch (PDOException $e) {
    $isStaff = false;
}

try {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.avatar FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ticket = false;
}

if (!$ticket) {
    header('Location: support.php');
    exit;
}

$accessDenied = true;
if ($isStaff || ($ticket['user_id'] && (int)$ticket['user_id'] === $userId)) {
    $hasAccess = true;
    $accessDenied = false;
} else {
    http_response_code(403);
}

$csrfToken = Security::generateCSRFToken();

$conversation = [];
if (!$accessDenied) {
    $initialMessage = [
        'id' => 'ticket-' . $ticket['id'],
        'is_staff' => 0,
        'username' => $ticket['contact_name'] ?: ($ticket['username'] ?? 'Пользователь'),
        'avatar' => $ticket['avatar'] ?? null,
        'message' => $ticket['message'],
        'created_at' => $ticket['created_at'],
    ];
    $conversation[] = $initialMessage;

    try {
        $stmt = $pdo->prepare("SELECT tr.*, COALESCE(u.username, 'Пользователь') AS username, u.avatar FROM ticket_replies tr LEFT JOIN users u ON tr.user_id = u.id WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
        $stmt->execute([$ticketId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($replies as $reply) {
            $conversation[] = $reply;
        }
    } catch (PDOException $e) {
        $replies = [];
    }
}

$statusLabels = [
    'open' => 'Открыт',
    'in-progress' => 'В работе',
    'resolved' => 'Решён',
    'closed' => 'Закрыт'
];

$statusNotes = [
    'open' => 'Обращение зарегистрировано и ждёт обработки командой поддержки.',
    'in-progress' => 'По тикету уже идёт работа. Все обновления появятся в этой переписке.',
    'resolved' => 'Основной вопрос считается решённым, но переписка и история остаются доступными.',
    'closed' => 'Тикет закрыт. Если понадобится, вы всё равно сможете вернуться к истории обращения.'
];

$categoryLabels = [
    'technical' => 'Техническая проблема',
    'account' => 'Вопрос по аккаунту',
    'billing' => 'Оплата и заказы',
    'suggestion' => 'Предложение',
    'other' => 'Другое'
];

$categoryLabel = $categoryLabels[$ticket['category']] ?? $ticket['category'];
$authorName = $ticket['contact_name'] ?: ($ticket['username'] ?? 'Пользователь');
$authorEmail = $ticket['contact_email'] ?: ($ticket['email'] ?? '—');
$serverOffsetMinutes = (int)((new DateTime())->getOffset() / 60);
$messageCount = count($conversation);
$lastUpdatedAt = $ticket['updated_at'] ?: $ticket['created_at'];
$statusLabel = $statusLabels[$ticket['status']] ?? $ticket['status'];
$statusNote = $statusNotes[$ticket['status']] ?? 'Следите за обновлениями в ленте сообщений.';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тикет <?= htmlspecialchars($ticket['ticket_number']) ?> - <?= SITE_NAME ?></title>
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
    <link rel="stylesheet" href="css/chat-unified.css?v=<?= time() ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="support-page ticket-details-page">
        <div class="container">
            <?php if ($accessDenied): ?>
                <section class="support-surface support-access-denied">
                    <div class="support-access-denied__icon">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <h1>Недостаточно прав</h1>
                    <p>Вы не можете просматривать это обращение. Откройте тикет под аккаунтом владельца или вернитесь в общий центр поддержки.</p>
                    <a href="support.php" class="support-inline-btn">
                        <i class="fas fa-arrow-left"></i>
                        Вернуться к поддержке
                    </a>
                </section>
            <?php else: ?>
                <section class="support-hero support-hero--ticket">
                    <div class="support-hero__main">
                        <a href="support.php" class="support-back-link">
                            <i class="fas fa-arrow-left"></i>
                            Все обращения
                        </a>

                        <span class="support-kicker">
                            <i class="fas fa-ticket-alt"></i>
                            <?= htmlspecialchars($ticket['ticket_number']) ?>
                        </span>
                        <h1><?= htmlspecialchars($ticket['subject']) ?></h1>
                        <p><?= htmlspecialchars($statusNote) ?></p>

                        <div class="ticket-summary-grid">
                            <div class="ticket-summary-item">
                                <span class="ticket-summary-item__label">Статус</span>
                                <span class="ticket-status status-<?= htmlspecialchars($ticket['status']) ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </div>
                            <div class="ticket-summary-item">
                                <span class="ticket-summary-item__label">Категория</span>
                                <strong><?= htmlspecialchars($categoryLabel) ?></strong>
                            </div>
                            <div class="ticket-summary-item">
                                <span class="ticket-summary-item__label">Создан</span>
                                <strong data-utc-time="<?= htmlspecialchars($ticket['created_at']) ?>"><?= TimezoneHelper::toUserTime($ticket['created_at']) ?></strong>
                            </div>
                            <div class="ticket-summary-item">
                                <span class="ticket-summary-item__label">Последнее обновление</span>
                                <strong data-utc-time="<?= htmlspecialchars($lastUpdatedAt) ?>"><?= TimezoneHelper::toUserTime($lastUpdatedAt) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="support-hero__stats support-hero__stats--ticket">
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Сообщений в треде</span>
                            <strong><?= $messageCount ?></strong>
                            <span class="support-stat-card__note">Стартовое сообщение и ответы по тикету</span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Автор</span>
                            <strong><?= htmlspecialchars($authorName) ?></strong>
                            <span class="support-stat-card__note"><?= htmlspecialchars($authorEmail) ?></span>
                        </div>
                        <div class="support-stat-card">
                            <span class="support-stat-card__label">Режим доступа</span>
                            <strong><?= $isStaff ? 'Staff view' : 'Owner view' ?></strong>
                            <span class="support-stat-card__note">Только владелец тикета и support-роль</span>
                        </div>
                    </div>
                </section>

                <div class="support-layout support-layout--ticket">
                    <section class="support-surface chat-panel">
                        <div class="support-surface__header support-surface__header--chat">
                            <div>
                                <span class="support-surface__eyebrow">Переписка</span>
                                <h2 class="section-title">Рабочий тред по обращению</h2>
                                <p class="section-text">Все ответы по этому тикету собираются в одном потоке, чтобы не терять контекст проблемы.</p>
                            </div>
                            <span class="support-surface__badge">
                                <i class="fas fa-comments"></i>
                                <?= $messageCount ?> сообщений
                            </span>
                        </div>

                        <div class="chat-content">
                            <div class="chat-header">
                                <div class="chat-header-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div class="chat-header-info">
                                    <div class="chat-header-title">Диалог по обращению</div>
                                    <div class="chat-header-status">
                                        <span class="status-dot"></span>
                                        <span>В потоке <?= $messageCount ?> <?= $messageCount === 1 ? 'сообщение' : ($messageCount < 5 ? 'сообщения' : 'сообщений') ?></span>
                                    </div>
                                </div>
                                <div class="chat-header-meta">
                                    <span class="chat-header-chip">#<?= htmlspecialchars($ticket['ticket_number']) ?></span>
                                </div>
                            </div>

                            <div class="chat-messages" id="chatThread">
                                <?php if (empty($conversation)): ?>
                                    <div class="chat-empty">
                                        <i class="fas fa-comments"></i>
                                        <h3>Переписка пока не началась</h3>
                                        <p>Напишите сообщение, если хотите уточнить детали по этому обращению.</p>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $lastDate = null;
                                    foreach ($conversation as $message):
                                        $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                        $today = date('Y-m-d');
                                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                                        $isSupportMessage = (bool)$message['is_staff'];
                                        $username = $isSupportMessage ? 'Техподдержка' : ($message['username'] ?? 'Пользователь');
                                        $displayTime = TimezoneHelper::toUserTime($message['created_at']);
                                        $initial = mb_strtoupper(mb_substr($username, 0, 1));
                                        $messageId = $message['id'] ?? ('ticket-' . $ticket['id']);

                                        if ($lastDate !== $messageDate):
                                            $lastDate = $messageDate;
                                    ?>
                                        <div class="chat-date-separator">
                                            <span>
                                                <?php
                                                if ($messageDate === $today) {
                                                    echo 'Сегодня';
                                                } elseif ($messageDate === $yesterday) {
                                                    echo 'Вчера';
                                                } else {
                                                    echo date('d.m.Y', strtotime($message['created_at']));
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>

                                        <div class="chat-message <?= $isSupportMessage ? 'support' : 'user' ?>" data-timestamp="<?= htmlspecialchars($message['created_at']) ?>" data-message-id="<?= htmlspecialchars($messageId) ?>">
                                            <div class="chat-avatar">
                                                <?php if ($isSupportMessage): ?>
                                                    <i class="fas fa-headset"></i>
                                                <?php elseif (!empty($message['avatar'])): ?>
                                                    <img src="<?= htmlspecialchars($message['avatar']) ?>" alt="Avatar" class="chat-avatar__image">
                                                <?php else: ?>
                                                    <?= htmlspecialchars($initial) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="chat-bubble">
                                                <div class="chat-bubble-header">
                                                    <span class="chat-sender"><?= htmlspecialchars($username) ?></span>
                                                    <span class="chat-time" data-utc-time="<?= htmlspecialchars($message['created_at']) ?>"><?= $displayTime ?></span>
                                                </div>
                                                <div class="chat-text">
                                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="chat-input-area">
                                <div class="chat-input-wrapper">
                                    <textarea
                                        class="chat-input"
                                        id="replyMessage"
                                        placeholder="Напишите сообщение по существу: уточнение, ответ на вопрос поддержки или новую деталь по проблеме."
                                        rows="1"
                                    ></textarea>
                                    <div class="reply-error" id="replyError" role="alert" aria-live="polite"></div>
                                </div>
                                <button class="chat-send-btn" type="button" id="sendReplyBtn">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Отправить</span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <aside class="ticket-sidebar">
                        <section class="support-surface ticket-sidebar-card">
                            <div class="support-surface__header">
                                <div>
                                    <span class="support-surface__eyebrow">Карточка обращения</span>
                                    <h2 class="section-title">Служебная информация</h2>
                                </div>
                            </div>

                            <div class="support-info-list">
                                <div class="support-info-item">
                                    <span class="support-info-item__label">Автор</span>
                                    <strong><?= htmlspecialchars($authorName) ?></strong>
                                </div>
                                <div class="support-info-item">
                                    <span class="support-info-item__label">Email</span>
                                    <strong><?= htmlspecialchars($authorEmail) ?></strong>
                                </div>
                                <div class="support-info-item">
                                    <span class="support-info-item__label">Номер тикета</span>
                                    <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong>
                                </div>
                                <div class="support-info-item">
                                    <span class="support-info-item__label">Категория</span>
                                    <strong><?= htmlspecialchars($categoryLabel) ?></strong>
                                </div>
                            </div>
                        </section>

                        <section class="support-surface ticket-sidebar-card">
                            <div class="support-surface__header">
                                <div>
                                    <span class="support-surface__eyebrow">Что дальше</span>
                                    <h2 class="section-title">Как вести этот тред</h2>
                                </div>
                            </div>

                            <div class="ticket-guidance">
                                <p><?= htmlspecialchars($statusNote) ?></p>
                                <ul class="ticket-guidance__list">
                                    <li>Пишите продолжение по этой же теме в одном треде, а не в новом тикете.</li>
                                    <li>Если меняются детали проблемы, добавьте их сообщением, чтобы история оставалась полной.</li>
                                    <li>Проверяйте статус и время последнего обновления в верхнем блоке страницы.</li>
                                </ul>
                            </div>
                        </section>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
    <script>
        const ticketId = <?= json_encode($ticketId) ?>;
        const csrfToken = <?= json_encode($csrfToken) ?>;
        const serverTzOffsetMinutes = <?= json_encode($serverOffsetMinutes) ?>;
    </script>
    <script src="js/ticket.js?v=<?= time() ?>"></script>
</body>
</html>
