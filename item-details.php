<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

$success = '';
$error = '';
$item_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($item_id <= 0) {
    header("Location: view_items.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$unreadCount = $user_id ? getUnreadCount($conn, $user_id) : 0;
$currentUser = null;

if (!$user_id) {
    header("Location: view_items.php?auth_required=1");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
if (!$stmt) {
    header("Location: view_items.php");
    exit;
}

$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$item) {
    header("Location: view_items.php");
    exit;
}

if ($user_id) {
    $userStmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    if ($userStmt) {
        $userStmt->bind_param('i', $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $currentUser = $userResult ? $userResult->fetch_assoc() : null;
        $userStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_claimed'])) {
    if (!$user_id) {
        $error = 'Please log in to mark an item as claimed.';
    } elseif (($item['claim_status'] ?? 'unclaimed') === 'claimed') {
        $error = 'This item has already been marked as claimed.';
    } else {
        $canMarkClaimed = false;

        if ($item['status'] === 'found' && (int) $item['user_id'] !== (int) $user_id) {
            $canMarkClaimed = true;
        }

        if ($item['status'] === 'lost' && (int) $item['user_id'] === (int) $user_id) {
            $canMarkClaimed = true;
        }

        if (!$canMarkClaimed) {
            $error = 'You are not allowed to mark this item as claimed.';
        } else {
            $updateStmt = $conn->prepare("UPDATE items SET claim_status = 'claimed' WHERE id = ? AND (claim_status IS NULL OR claim_status <> 'claimed')");

            if (!$updateStmt) {
                $error = 'Unable to update the claim status right now.';
            } else {
                $updateStmt->bind_param('i', $item_id);
                $updateStmt->execute();
                $updatedRows = $updateStmt->affected_rows;
                $updateStmt->close();

                if ($updatedRows > 0) {
                    $item['claim_status'] = 'claimed';
                    $matchedItem = findBestMatch($conn, $item);
                    notifyClaimParticipants($conn, $item, $matchedItem, $user_id);
                    $unreadCount = $user_id ? getUnreadCount($conn, $user_id) : 0;
                    $success = 'Item marked as claimed successfully.';
                } else {
                    $error = 'This item was already claimed.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_contact'])) {
    if (!$user_id) {
        $error = 'Please log in to notify the person who posted this item.';
    } elseif ((int) $item['user_id'] === (int) $user_id) {
        $error = 'You cannot send a request for your own item post.';
    } elseif (($item['claim_status'] ?? 'unclaimed') === 'claimed') {
        $error = 'This item has already been claimed.';
    } else {
        $requesterName = trim((string) ($currentUser['name'] ?? ''));
        $requesterEmail = trim((string) ($currentUser['email'] ?? ''));
        $requestMessage = buildItemRequestMessage($requesterName, $requesterEmail, $item['title'] ?? 'item');
        $targetUserId = (int) $item['user_id'];

        if ($targetUserId <= 0) {
            $error = 'This item post cannot receive requests right now.';
        } elseif (hasRecentMatchingNotification($conn, $targetUserId, $requestMessage, 'request', $item_id, 30)) {
            $error = 'You already sent a recent request for this item. Please give them a little time to respond.';
        } elseif (createNotification($conn, $targetUserId, $requestMessage, 'request', $item_id)) {
            $success = 'Your request was sent. The person who posted this item has been notified.';
        } else {
            $error = 'Unable to send your request right now. Please try again in a moment.';
        }
    }
}

$dateReported = !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : 'Not recorded';
$claimStatus = $item['claim_status'] ?? 'unclaimed';
$canShowClaimButton = $user_id
    && $claimStatus !== 'claimed'
    && (
        ($item['status'] === 'found' && (int) $item['user_id'] !== (int) $user_id)
        || ($item['status'] === 'lost' && (int) $item['user_id'] === (int) $user_id)
    );
$canRequestContact = $user_id
    && $claimStatus !== 'claimed'
    && (int) $item['user_id'] !== (int) $user_id;
$shouldShowRequestCard = true;
$requestHelperText = !empty($currentUser['email'])
    ? 'We will include your account email in the notification so the person who posted this item knows how to contact you.'
    : 'We will notify the person who posted this item that you think it may belong to you.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> | ClaimIt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3eb;
            --surface: rgba(255, 255, 255, 0.76);
            --surface-strong: #ffffff;
            --text: #3f4740;
            --muted: #727d73;
            --primary: #aab99a;
            --primary-dark: #727d73;
            --accent: #b9b28a;
            --border: rgba(114, 125, 115, 0.18);
            --shadow: 0 28px 70px rgba(79, 88, 80, 0.12);
            --lost-bg: #f5efdb;
            --lost-text: #8a7f58;
            --found-bg: #eef3e8;
            --found-text: #727d73;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Outfit", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(185, 178, 138, 0.22), transparent 26%),
                radial-gradient(circle at bottom right, rgba(170, 185, 154, 0.2), transparent 28%),
                linear-gradient(135deg, #f7f4ea 0%, #fbfaf6 54%, #f1efe6 100%);
            padding: 28px 20px;
        }

        .page-shell {
            width: min(1100px, 100%);
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.68);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.66);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 30px;
            border-bottom: 1px solid rgba(114, 125, 115, 0.14);
            background: rgba(255, 255, 255, 0.55);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: 1.18rem;
            font-weight: 800;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: 0 12px 24px rgba(114, 125, 115, 0.24);
        }

        .nav-group {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-links a,
        .back-button {
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            color: #4b554c;
            font-weight: 600;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .nav-links a:hover,
        .back-button:hover {
            background: rgba(185, 178, 138, 0.22);
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .notification-bell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            border-radius: 50%;
            transition: background 0.2s ease;
        }

        .notification-bell:hover { background: rgba(185, 178, 138, 0.2); }
        .notification-bell-icon { font-size: 1.3rem; }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #c0392b;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            border: 2px solid #ffffff;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 360px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(114, 125, 115, 0.14);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(79, 88, 80, 0.15);
            z-index: 1000;
            display: none;
            max-height: 450px;
            overflow-y: auto;
        }

        .notification-dropdown.active { display: block; }

        .notification-header {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(114, 125, 115, 0.1);
        }

        .notification-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .notification-item {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(114, 125, 115, 0.08);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-item:hover { background: rgba(170, 185, 154, 0.1); }
        .notification-item.unread { background: rgba(170, 185, 154, 0.08); font-weight: 600; }
        .notification-message { margin: 0 0 6px; font-size: 0.9rem; line-height: 1.4; }
        .notification-row { display: grid; grid-template-columns: 24px 1fr; gap: 10px; align-items: start; }
        .notification-icon { font-size: 1rem; line-height: 1.4; }
        .notification-time { margin: 0; color: var(--muted); font-size: 0.8rem; }
        .notification-empty { padding: 30px 20px; text-align: center; color: var(--muted); }
        .notification-empty p { margin: 0; }

        .content {
            padding: 30px;
            display: grid;
            gap: 24px;
        }

        .feedback {
            padding: 14px 18px;
            border-radius: 18px;
            font-weight: 600;
            line-height: 1.5;
        }

        .feedback.success {
            background: #ecfff7;
            color: #117a54;
            border: 1px solid rgba(17, 122, 84, 0.12);
        }

        .feedback.error {
            background: #fff2f2;
            color: #c0392b;
            border: 1px solid rgba(192, 57, 43, 0.12);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }

        .item-main,
        .item-sidebar {
            display: grid;
            gap: 24px;
        }

        .item-image-container,
        .detail-card,
        .contact-card,
        .item-description {
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(114, 125, 115, 0.14);
            box-shadow: 0 18px 42px rgba(79, 88, 80, 0.07);
        }

        .item-image-container {
            overflow: hidden;
            background: #f3f1e7;
        }

        .item-image {
            width: 100%;
            height: 420px;
            object-fit: cover;
            display: block;
        }

        .item-no-image {
            height: 420px;
            display: grid;
            place-items: center;
            color: var(--muted);
        }

        .item-description,
        .detail-card,
        .contact-card {
            padding: 24px;
        }

        .item-description h3,
        .detail-card h3,
        .contact-card h3 {
            margin: 0 0 16px;
            font-size: 1rem;
            font-weight: 700;
        }

        .item-description p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(114, 125, 115, 0.08);
        }

        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--muted); font-size: 0.92rem; }
        .detail-value { color: var(--text); font-weight: 600; text-align: right; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status-badge.lost { background: var(--lost-bg); color: var(--lost-text); }
        .status-badge.found { background: var(--found-bg); color: var(--found-text); }

        .contact-card {
            background: linear-gradient(135deg, rgba(170, 185, 154, 0.14), rgba(185, 178, 138, 0.08));
            border: 2px solid rgba(114, 125, 115, 0.12);
        }

        .contact-info { display: grid; gap: 14px; }
        .contact-label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .contact-value {
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            border: 1px solid rgba(114, 125, 115, 0.1);
            font-weight: 600;
            word-break: break-word;
        }

        .keywords-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .keyword-tag {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 12px;
            background: rgba(170, 185, 154, 0.2);
            color: var(--primary-dark);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .action-btn {
            width: 100%;
            padding: 14px 18px;
            border: 0;
            border-radius: 18px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(114, 125, 115, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(114, 125, 115, 0.28);
        }

        .action-note {
            margin: 0 0 18px;
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.92rem;
        }

        .action-note:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 1000px) {
            .detail-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 720px) {
            body { padding: 16px; }
            .topbar, .content { padding: 20px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .item-image, .item-no-image { height: 280px; }
            .notification-dropdown { width: min(340px, calc(100vw - 48px)); right: -20px; }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <header class="topbar">
            <a href="item-details.php?id=<?php echo (int) $item['id']; ?>" class="brand">
                <span class="brand-mark">C</span>
                <span>ClaimIt</span>
            </a>

            <div class="nav-group">
                <nav class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="post_item.php">Post Item</a>
                    <a href="view_items.php">Items</a>
                    <?php if ($user_id): ?>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                    <?php endif; ?>
                </nav>

                <?php if ($user_id): ?>
                    <div style="position: relative;">
                        <div class="notification-bell" id="notificationBell">
                            <span class="notification-bell-icon">🔔</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <div class="notification-empty">
                                    <p>Loading...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="content">
            <a href="view_items.php" class="back-button">← Back to Items</a>

            <?php if ($success !== ''): ?>
                <div class="feedback success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="feedback error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="detail-grid">
                <div class="item-main">
                    <div class="item-image-container">
                        <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                            <img class="item-image" src="<?php echo htmlspecialchars('uploads/' . $item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        <?php else: ?>
                            <div class="item-no-image">No image available for this item</div>
                        <?php endif; ?>
                    </div>

                    <div class="item-description">
                        <h3>Description</h3>
                        <p><?php echo htmlspecialchars($item['description'] ?: 'No description provided.'); ?></p>
                    </div>

                    <?php if (!empty($item['keywords'])): ?>
                        <div class="detail-card">
                            <h3>Keywords</h3>
                            <div class="keywords-list">
                                <?php foreach (array_filter(array_map('trim', explode(',', $item['keywords']))) as $keyword): ?>
                                    <span class="keyword-tag"><?php echo htmlspecialchars($keyword); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="item-sidebar">
                    <div class="detail-card">
                        <h3>Item Details</h3>
                        <div class="detail-row">
                            <span class="detail-label">Title</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['title']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="status-badge <?php echo htmlspecialchars($item['status']); ?>">
                                <?php echo htmlspecialchars($item['status']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Category</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['category']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Location</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['specific_location'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Reported</span>
                            <span class="detail-value"><?php echo htmlspecialchars($dateReported); ?></span>
                        </div>
                    </div>

                    <div class="contact-card">
                        <h3><?php echo $item['status'] === 'lost' ? 'Owner Info' : 'Finder Info'; ?></h3>
                        <div class="contact-info">
                            <?php if (!empty($item['contact_name'])): ?>
                                <div>
                                    <span class="contact-label"><?php echo $item['status'] === 'lost' ? 'Reported by' : 'Found by'; ?></span>
                                    <div class="contact-value"><?php echo htmlspecialchars($item['contact_name']); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['contact_info'])): ?>
                                <div>
                                    <span class="contact-label">Contact Method</span>
                                    <div class="contact-value"><?php echo htmlspecialchars($item['contact_info']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h3>Claim Status</h3>
                        <div class="detail-row">
                            <span class="detail-label">Current state</span>
                            <span class="detail-value"><?php echo htmlspecialchars($claimStatus); ?></span>
                        </div>

                        <?php if ($canShowClaimButton): ?>
                            <form method="POST" style="margin-top: 18px;">
                                <button type="submit" name="mark_claimed" value="1" class="action-btn">Mark as Claimed</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($shouldShowRequestCard): ?>
                        <div class="detail-card">
                            <h3>Poke Poster</h3>
                            <?php if (!$user_id): ?>
                                <p class="action-note">Log in first so we can notify the person who posted this found item for you.</p>
                            <?php elseif ((int) $item['user_id'] === (int) $user_id): ?>
                                <p class="action-note">This is your own found-item post, so there is no need to poke yourself.</p>
                            <?php elseif ($claimStatus === 'claimed'): ?>
                                <p class="action-note">This item has already been claimed, so poking the poster is disabled.</p>
                            <?php else: ?>
                                <p class="action-note"><?php echo htmlspecialchars($requestHelperText); ?></p>
                                <form method="POST">
                                    <button type="submit" name="request_contact" value="1" class="action-btn">Poke poster</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php if ($user_id): ?>
    <script>
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        let notificationBadge = document.getElementById('notificationBadge');

        if (notificationBell) {
            notificationBell.addEventListener('click', async (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');

                if (notificationDropdown.classList.contains('active')) {
                    await loadNotifications();
                    await markAllNotificationsRead();
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-bell') && !e.target.closest('.notification-dropdown')) {
                notificationDropdown.classList.remove('active');
            }
        });

        async function loadNotifications() {
            try {
                const response = await fetch('notifications-api.php?action=get-notifications');
                const data = await response.json();

                if (data.success) {
                    renderNotifications(data.notifications);
                    updateBadge(data.unread_count);
                }
            } catch (err) {
                console.error('Error loading notifications:', err);
            }
        }

        function renderNotifications(notifications) {
            if (!notifications.length) {
                notificationList.innerHTML = '<div class="notification-empty"><p>No notifications yet</p></div>';
                return;
            }

            notificationList.innerHTML = notifications.map(notif => `
                <div class="notification-item ${Number(notif.is_read) === 0 ? 'unread' : ''}" onclick="openNotification(${notif.id}, ${notif.related_item_id ?? 'null'})">
                    <div class="notification-row">
                        <span class="notification-icon">${getNotificationIcon(notif.type)}</span>
                        <div>
                            <p class="notification-message">${escapeHtml(notif.message)}</p>
                            <p class="notification-time">${notif.time_ago}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function openNotification(notificationId, relatedItemId) {
            try {
                await fetch('notifications-api.php?action=mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'notification_id=' + notificationId
                });
            } catch (err) {
                console.error('Error marking notification as read:', err);
            }

            if (relatedItemId) {
                window.location.href = 'item-details.php?id=' + relatedItemId;
                return;
            }

            loadNotifications();
        }

        async function markAllNotificationsRead() {
            try {
                await fetch('notifications-api.php?action=mark-all-read');
                updateBadge(0);
                await loadNotifications();
            } catch (err) {
                console.error('Error marking all notifications as read:', err);
            }
        }

        function updateBadge(count) {
            if (count > 0) {
                if (!notificationBadge) {
                    notificationBadge = document.createElement('span');
                    notificationBadge.className = 'notification-badge';
                    notificationBadge.id = 'notificationBadge';
                    notificationBell.appendChild(notificationBadge);
                }

                notificationBadge.textContent = count;
                notificationBadge.style.display = 'flex';
                return;
            }

            if (notificationBadge) {
                notificationBadge.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function getNotificationIcon(type) {
            if (type === 'match') return '🔍';
            if (type === 'claim') return '🎉';
            if (type === 'request') return '📩';
            return '🔔';
        }

        loadNotifications();
    </script>
    <?php endif; ?>
</body>
</html>
