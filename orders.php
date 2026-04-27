<?php
session_start();
require_once 'config.php';
require_once 'includes/components_union.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$componentSource = getComponentsUnionSource();
$categoryNameMap = [];

try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
    foreach ($categories as $category) {
        $categoryNameMap[(int)$category['id']] = $category['name'];
    }
} catch (PDOException $e) {
    $categoryNameMap = [];
}

function resolveOrderItemCategory(array $item, PDO $pdo, string $componentSource, array $categoryNameMap, array &$cache): string
{
    $raw = trim((string)($item['component_category'] ?? ''));
    if ($raw !== '' && mb_strtolower($raw) !== 'unknown') {
        if (ctype_digit($raw)) {
            $categoryId = (int)$raw;
            if (isset($categoryNameMap[$categoryId])) {
                return $categoryNameMap[$categoryId];
            }
        }
        return $raw;
    }

    $nameKey = $item['component_name'] ?? null;
    if ($nameKey && isset($cache['name:' . $nameKey])) {
        return $cache['name:' . $nameKey];
    }

    $idKey = $item['component_id'] ?? null;
    if ($idKey && isset($cache['id:' . $idKey])) {
        return $cache['id:' . $idKey];
    }

    $categoryId = null;
    if ($componentSource) {
        $componentName = trim((string)($item['component_name'] ?? ''));
        $componentId = $item['component_id'] ?? null;
        $params = [];
        $conditions = [];
        $orderParts = [];

        if ($componentName !== '') {
            $conditions[] = "name = ?";
            $params[] = $componentName;
            $conditions[] = "model = ?";
            $params[] = $componentName;
            $conditions[] = "? LIKE CONCAT('%', model, '%')";
            $params[] = $componentName;
            $conditions[] = "? LIKE CONCAT('%', name, '%')";
            $params[] = $componentName;

            $orderParts[] = "(name = ?) DESC";
            $params[] = $componentName;
            $orderParts[] = "(model = ?) DESC";
            $params[] = $componentName;
            $orderParts[] = "(? LIKE CONCAT('%', model, '%')) DESC";
            $params[] = $componentName;
            $orderParts[] = "(? LIKE CONCAT('%', name, '%')) DESC";
            $params[] = $componentName;
        }

        if (!empty($componentId)) {
            $conditions[] = "id = ?";
            $params[] = $componentId;
            $orderParts[] = "(id = ?) DESC";
            $params[] = $componentId;
        }

        if (!empty($conditions)) {
            try {
                $where = implode(' OR ', $conditions);
                $order = !empty($orderParts) ? ' ORDER BY ' . implode(', ', $orderParts) : '';
                $stmt = $pdo->prepare("SELECT category_id FROM {$componentSource} AS components_union WHERE {$where}{$order} LIMIT 1");
                $stmt->execute($params);
                $categoryId = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $categoryId = null;
            }
        }
    }

    $label = $categoryId && isset($categoryNameMap[(int)$categoryId])
        ? $categoryNameMap[(int)$categoryId]
        : 'Комплектующее';

    if ($nameKey) {
        $cache['name:' . $nameKey] = $label;
    }
    if ($idKey) {
        $cache['id:' . $idKey] = $label;
    }

    return $label;
}

function getOrderStatusData(string $status): array
{
    static $statusMap = [
        'pending' => ['label' => 'Ожидает подтверждения', 'icon' => 'fa-clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Подтвержден', 'icon' => 'fa-check-circle', 'tone' => 'info'],
        'processing' => ['label' => 'В обработке', 'icon' => 'fa-gear', 'tone' => 'info'],
        'assembling' => ['label' => 'Собирается', 'icon' => 'fa-screwdriver-wrench', 'tone' => 'accent'],
        'shipping' => ['label' => 'В пути', 'icon' => 'fa-truck-fast', 'tone' => 'info'],
        'shipped' => ['label' => 'Отправлен', 'icon' => 'fa-truck-fast', 'tone' => 'info'],
        'ready_pickup' => ['label' => 'Ждет получения', 'icon' => 'fa-box-open', 'tone' => 'success'],
        'completed' => ['label' => 'Получен', 'icon' => 'fa-circle-check', 'tone' => 'success'],
        'delivered' => ['label' => 'Доставлен', 'icon' => 'fa-circle-check', 'tone' => 'success'],
        'cancelled' => ['label' => 'Отменён', 'icon' => 'fa-circle-xmark', 'tone' => 'danger'],
    ];

    return $statusMap[$status] ?? ['label' => $status, 'icon' => 'fa-circle', 'tone' => 'neutral'];
}

function getOrderProgressIndex(string $status): ?int
{
    return match ($status) {
        'pending' => 1,
        'confirmed', 'processing' => 2,
        'assembling' => 3,
        'shipping', 'shipped' => 4,
        'ready_pickup', 'completed', 'delivered' => 5,
        default => null,
    };
}

function getPaymentStatusLabel(?string $paymentStatus): string
{
    return match ($paymentStatus) {
        'paid' => 'Оплачен',
        'pending' => 'Ожидает оплаты',
        'failed' => 'Ошибка оплаты',
        'refunded' => 'Возврат',
        default => 'Не указан',
    };
}

$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*,
               COUNT(oi.id) as items_count,
               SUM(oi.quantity) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll();

        try {
            $stmt = $pdo->prepare("
                SELECT sm.*, u.username, u.avatar, u.role
                FROM support_messages sm
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.order_id = ?
                ORDER BY sm.created_at ASC
            ");
            $stmt->execute([$order['id']]);
            $order['support_messages'] = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count
                FROM support_messages
                WHERE order_id = ? AND is_support = 1 AND is_read = 0
            ");
            $stmt->execute([$order['id']]);
            $order['initial_unread_count'] = (int)$stmt->fetchColumn();
            $order['unread_count'] = $order['initial_unread_count'];

            if ($order['unread_count'] > 0) {
                $stmt = $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE order_id = ? AND is_support = 1 AND is_read = 0");
                $stmt->execute([$order['id']]);
                $order['unread_count'] = 0;
            }
        } catch (PDOException $e) {
            $order['support_messages'] = [];
            $order['initial_unread_count'] = 0;
            $order['unread_count'] = 0;
        }
    }
    unset($order);
} catch (PDOException $e) {
    $orders = [];
}

$totalOrdersCount = count($orders);
$activeOrdersCount = 0;
$completedOrdersCount = 0;
$actionRequiredCount = 0;
$activeStatuses = ['pending', 'confirmed', 'processing', 'assembling', 'shipping', 'shipped', 'ready_pickup'];
$completedStatuses = ['completed', 'delivered'];
$closedStatuses = ['cancelled', 'completed', 'delivered'];

foreach ($orders as $order) {
    if (in_array($order['status'], $activeStatuses, true)) {
        $activeOrdersCount++;
    }

    if (in_array($order['status'], $completedStatuses, true)) {
        $completedOrdersCount++;
    }

    $needsPayment = ($order['payment_status'] ?? '') !== 'paid' && !in_array($order['status'], $closedStatuses, true);
    $hasNewSupportReply = !empty($order['initial_unread_count']);

    if ($needsPayment || $hasNewSupportReply) {
        $actionRequiredCount++;
    }
}

$progressSteps = ['Заказ', 'Подтверждение', 'Сборка', 'Доставка', 'Получение'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы - <?= SITE_NAME ?></title>
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
    <link rel="stylesheet" href="css/orders.css">
    <link rel="stylesheet" href="css/chat-unified.css">
    <script src="js/orders.js" defer></script>
</head>
<body class="orders-page-view">
    <?php include 'includes/header.php'; ?>

    <main class="orders-page">
        <div class="container">
            <section class="orders-hero">
                <div class="orders-hero__copy">
                    <p class="orders-eyebrow">Личный кабинет</p>
                    <h1><i class="fas fa-box-archive"></i> Заказы</h1>
                    <p class="orders-hero__lead">Следите за этапом сборки, оплатой и ответами поддержки по каждому заказу в одном месте.</p>
                </div>

                <?php if (!empty($orders)): ?>
                <div class="orders-summary" aria-label="Сводка по заказам">
                    <div class="summary-card">
                        <span class="summary-card__label">Всего заказов</span>
                        <strong class="summary-card__value"><?= $totalOrdersCount ?></strong>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card__label">В работе</span>
                        <strong class="summary-card__value"><?= $activeOrdersCount ?></strong>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card__label">Завершено</span>
                        <strong class="summary-card__value"><?= $completedOrdersCount ?></strong>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card__label">Требуют действия</span>
                        <strong class="summary-card__value"><?= $actionRequiredCount ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <section class="orders-empty-state">
                        <div class="orders-empty-state__icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h2>Заказов пока нет</h2>
                        <p>Когда оформите первую сборку, здесь появятся этапы заказа, сумма, состав и переписка с поддержкой.</p>
                        <a href="builder.php" class="btn-build-pc">
                            <i class="fas fa-screwdriver-wrench"></i>
                            <span>Собрать ПК</span>
                        </a>
                    </section>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $status = getOrderStatusData((string)$order['status']);
                        $progressIndex = getOrderProgressIndex((string)$order['status']);
                        $reference = !empty($order['order_number']) ? $order['order_number'] : '#' . $order['id'];
                        $supportMessagesCount = count($order['support_messages']);
                        $paymentLabel = getPaymentStatusLabel($order['payment_status'] ?? null);

                        if (!empty($order['initial_unread_count'])) {
                            $supportSummary = 'Есть новый ответ поддержки';
                        } elseif ($supportMessagesCount > 0) {
                            $supportSummary = $supportMessagesCount . ' сообщений по заказу';
                        } else {
                            $supportSummary = 'Можно написать по оплате, сборке или доставке';
                        }
                        ?>
                        <article class="order-card">
                            <div class="order-card__header">
                                <div class="order-card__identity">
                                    <span class="order-card__eyebrow">Заказ</span>
                                    <div class="order-number"><?= htmlspecialchars($reference) ?></div>
                                    <div class="order-meta">
                                        <span><i class="fas fa-clock"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                                        <span><i class="fas fa-box"></i> <?= (int)($order['total_items'] ?? 0) ?> поз.</span>
                                    </div>
                                </div>

                                <div class="order-card__status">
                                    <div class="order-status-badge status-<?= htmlspecialchars($status['tone']) ?>">
                                        <i class="fas <?= htmlspecialchars($status['icon']) ?>"></i>
                                        <span><?= htmlspecialchars($status['label']) ?></span>
                                    </div>

                                    <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
                                    <div class="order-payment-badge paid">
                                        <i class="fas fa-circle-check"></i>
                                        <span>Оплачен</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="order-kpis">
                                <div class="order-kpi order-kpi--primary">
                                    <span class="order-kpi__label">Сумма</span>
                                    <strong class="order-kpi__value"><?= formatPrice($order['total_amount']) ?></strong>
                                </div>
                                <div class="order-kpi">
                                    <span class="order-kpi__label">Оплата</span>
                                    <strong class="order-kpi__value"><?= htmlspecialchars($paymentLabel) ?></strong>
                                </div>
                                <div class="order-kpi">
                                    <span class="order-kpi__label">Сценарий</span>
                                    <strong class="order-kpi__value">
                                        <?= !empty($order['delivery_method']) && $order['delivery_method'] === 'pickup' ? 'Самовывоз' : 'Доставка / выдача' ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="order-progress-panel">
                                <div class="order-progress-panel__copy">
                                    <span class="order-progress-panel__label">Текущий этап</span>
                                    <strong class="order-progress-panel__value"><?= htmlspecialchars($status['label']) ?></strong>
                                </div>

                                <?php if ($progressIndex !== null): ?>
                                <div class="order-progress-steps" aria-label="Этапы заказа">
                                    <?php foreach ($progressSteps as $stepIndex => $stepLabel): ?>
                                        <?php
                                        $stepNumber = $stepIndex + 1;
                                        $stepState = $stepNumber < $progressIndex ? 'done' : ($stepNumber === $progressIndex ? 'current' : 'pending');
                                        ?>
                                        <div class="progress-step progress-step--<?= $stepState ?>">
                                            <span class="progress-step__dot"></span>
                                            <span class="progress-step__label"><?= $stepLabel ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="order-progress-note order-progress-note--cancelled">
                                    <i class="fas <?= ($order['status'] ?? '') === 'cancelled' ? 'fa-circle-xmark' : 'fa-circle-info' ?>"></i>
                                    <span>
                                        <?= ($order['status'] ?? '') === 'cancelled'
                                            ? 'Заказ завершён без перехода по этапам выполнения.'
                                            : 'Текущий статус обновляется без пошаговой шкалы.' ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="order-actions">
                                <button type="button" class="btn-order order-btn-primary" onclick="toggleOrderDetails(<?= $order['id'] ?>)" id="details-btn-<?= $order['id'] ?>">
                                    <span class="btn-order__content">
                                        <i class="fas fa-chevron-down btn-icon"></i>
                                        <span class="btn-order__text">
                                            <span class="btn-order__label">Подробнее</span>
                                            <span class="btn-order__caption">Состав, доставка и комментарий</span>
                                        </span>
                                    </span>
                                </button>

                                <button type="button" class="btn-order btn-support" onclick="toggleSupportChat(<?= $order['id'] ?>)" id="chat-btn-<?= $order['id'] ?>">
                                    <span class="btn-order__content">
                                        <i class="fas fa-comments"></i>
                                        <span class="btn-order__text">
                                            <span class="btn-order__label">Поддержка</span>
                                            <span class="btn-order__caption"><?= htmlspecialchars($supportSummary) ?></span>
                                        </span>
                                    </span>
                                    <?php if (!empty($order['initial_unread_count'])): ?>
                                    <span class="order-notification-badge"><?= (int)$order['initial_unread_count'] ?></span>
                                    <?php endif; ?>
                                </button>

                                <div class="order-actions__secondary">
                                    <?php if (($order['payment_status'] ?? '') !== 'paid' && !in_array($order['status'], ['cancelled', 'completed', 'delivered'], true)): ?>
                                    <button type="button" class="btn-order btn-ghost btn-pay" onclick="payOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-credit-card"></i>
                                        <span>Оплатить</span>
                                    </button>
                                    <?php endif; ?>

                                    <?php if (in_array($order['status'], ['pending', 'confirmed'], true)): ?>
                                    <button type="button" class="btn-order btn-ghost btn-cancel" onclick="cancelOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                        <span>Отменить</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="order-expanded-details" id="details-<?= $order['id'] ?>">
                                <div class="expanded-content">
                                    <section class="order-panel">
                                        <div class="panel-header">
                                            <div>
                                                <h3>Состав заказа</h3>
                                                <p>Основные комплектующие и стоимость по каждой позиции.</p>
                                            </div>
                                        </div>

                                        <?php if (!empty($order['items'])): ?>
                                        <div class="order-items-list">
                                            <?php
                                            $categoryCache = [];
                                            foreach ($order['items'] as $item):
                                                $resolvedCategory = resolveOrderItemCategory($item, $pdo, $componentSource, $categoryNameMap, $categoryCache);
                                            ?>
                                            <div class="order-item">
                                                <div class="item-details">
                                                    <div class="item-name"><?= htmlspecialchars($item['component_name']) ?></div>
                                                    <div class="item-category"><?= htmlspecialchars($resolvedCategory) ?></div>
                                                </div>
                                                <div class="item-price"><?= formatPrice($item['price']) ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="order-panel__empty">Состав заказа пока не загружен.</div>
                                        <?php endif; ?>
                                    </section>

                                    <section class="order-panel">
                                        <div class="panel-header">
                                            <div>
                                                <h3>Дополнительно</h3>
                                                <p>Доставка, оплата и комментарий к заказу.</p>
                                            </div>
                                        </div>

                                        <div class="expanded-info-grid">
                                            <?php if (!empty($order['delivery_address'])): ?>
                                            <div class="info-block">
                                                <div class="info-block-title">
                                                    <i class="fas fa-location-dot"></i>
                                                    Адрес доставки
                                                </div>
                                                <div class="info-block-content"><?= htmlspecialchars($order['delivery_address']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['delivery_method'])): ?>
                                            <div class="info-block">
                                                <div class="info-block-title">
                                                    <i class="fas fa-truck"></i>
                                                    Способ доставки
                                                </div>
                                                <div class="info-block-content">
                                                    <?php
                                                    $deliveryMethods = [
                                                        'courier' => 'Курьером',
                                                        'pickup' => 'Самовывоз',
                                                        'express' => 'Экспресс-доставка',
                                                    ];
                                                    echo htmlspecialchars($deliveryMethods[$order['delivery_method']] ?? $order['delivery_method']);
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['payment_method'])): ?>
                                            <div class="info-block">
                                                <div class="info-block-title">
                                                    <i class="fas fa-credit-card"></i>
                                                    Способ оплаты
                                                </div>
                                                <div class="info-block-content">
                                                    <?php
                                                    $paymentMethods = [
                                                        'card' => 'Банковская карта',
                                                        'cash' => 'Наличными при получении',
                                                        'online' => 'Онлайн-оплата',
                                                    ];
                                                    echo htmlspecialchars($paymentMethods[$order['payment_method']] ?? $order['payment_method']);
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['notes'])): ?>
                                            <div class="info-block">
                                                <div class="info-block-title">
                                                    <i class="fas fa-comment"></i>
                                                    Комментарий
                                                </div>
                                                <div class="info-block-content"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <div class="order-support-chat" id="chat-<?= $order['id'] ?>">
                                <div class="chat-content">
                                    <section class="order-panel order-panel--chat">
                                        <div class="panel-header panel-header--chat">
                                            <div class="chat-header-copy">
                                                <h3>Поддержка по заказу</h3>
                                                <p>Пишите по вопросам оплаты, сборки и доставки. История переписки хранится в этом заказе.</p>
                                                <span class="chat-header-chip">#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></span>
                                            </div>
                                            <div class="chat-presence">
                                                <span class="chat-status-dot"></span>
                                                <span>Диалог активен</span>
                                            </div>
                                        </div>

                                        <div class="chat-messages" id="messages-<?= $order['id'] ?>">
                                            <?php if (empty($order['support_messages'])): ?>
                                            <div class="chat-empty">
                                                <i class="fas fa-comments"></i>
                                                <p>Пока нет сообщений. Напишите, если у вас есть вопросы по заказу.</p>
                                            </div>
                                            <?php else: ?>
                                                <?php
                                                $lastDate = null;
                                                foreach ($order['support_messages'] as $message):
                                                    $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                                    $today = date('Y-m-d');
                                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
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
                                                <div class="chat-message <?= $message['is_support'] ? 'support' : 'user' ?>">
                                                    <div class="chat-avatar">
                                                        <?php if ($message['is_support']): ?>
                                                            <i class="fas fa-headset"></i>
                                                        <?php elseif (!empty($message['avatar'])): ?>
                                                            <img src="<?= htmlspecialchars($message['avatar']) ?>" alt="Avatar" class="chat-avatar__image">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($message['username'] ?? $_SESSION['username'] ?? 'U', 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="chat-bubble">
                                                        <div class="chat-bubble-header">
                                                            <span class="chat-sender">
                                                                <?php if ($message['is_support']): ?>
                                                                    Техподдержка
                                                                    <?php if (!empty($message['role']) && in_array($message['role'], ['admin', 'high-admin', 'owner'], true)): ?>
                                                                        <i class="fas fa-shield-halved chat-role-badge" title="<?= ucfirst($message['role']) ?>"></i>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($message['username'] ?? $_SESSION['username'] ?? 'Вы') ?>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="chat-time">
                                                                <?php
                                                                $messageTime = strtotime($message['created_at']);
                                                                $todayStart = strtotime('today');
                                                                $yesterdayStart = strtotime('yesterday');

                                                                if ($messageTime >= $todayStart) {
                                                                    echo date('H:i', $messageTime);
                                                                } elseif ($messageTime >= $yesterdayStart) {
                                                                    echo 'Вчера ' . date('H:i', $messageTime);
                                                                } else {
                                                                    echo date('d.m.Y H:i', $messageTime);
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <div class="chat-text"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="chat-input-area">
                                            <div class="chat-input-wrapper">
                                                <textarea
                                                    class="chat-input"
                                                    id="chat-input-<?= $order['id'] ?>"
                                                    placeholder="Напишите сообщение по заказу..."
                                                    rows="1"
                                                ></textarea>
                                            </div>
                                            <button type="button" class="chat-send-btn" onclick="sendMessage(<?= $order['id'] ?>, event)">
                                                <i class="fas fa-paper-plane"></i>
                                                <span>Отправить</span>
                                            </button>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="js/main.js"></script>
</body>
</html>
