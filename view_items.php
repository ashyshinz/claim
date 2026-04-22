<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

// Get current user's ID for notification count
$user_id = $_SESSION['user_id'] ?? null;
$unreadCount = $user_id ? getUnreadCount($conn, $user_id) : 0;

$status = $_GET['status'] ?? '';

$result = null;

if ($status !== '') {
    $stmt = $conn->prepare("SELECT * FROM items WHERE status = ?");
    if ($stmt) {
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query("SELECT * FROM items");
}

$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Items | ClaimIt</title>
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
            --border: rgba(114, 125, 115, 0.18);
            --shadow: 0 28px 70px rgba(79, 88, 80, 0.12);
            --lost-bg: #f5efdb;
            --lost-text: #8a7f58;
            --found-bg: #eef3e8;
            --found-text: #727d73;
        }

        * {
            box-sizing: border-box;
        }

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
            width: min(1220px, 100%);
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

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            color: #4b554c;
            font-weight: 600;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
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

        .notification-bell:hover {
            background: rgba(185, 178, 138, 0.2);
        }

        .notification-bell-icon {
            font-size: 1.3rem;
        }

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

        .notification-dropdown.active {
            display: block;
        }

        .notification-header {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(114, 125, 115, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
        }

        .notification-header button {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .notification-header button:hover {
            background: rgba(185, 178, 138, 0.2);
        }

        .notification-list {
            display: grid;
            gap: 0;
        }

        .notification-item {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(114, 125, 115, 0.08);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-item:hover {
            background: rgba(170, 185, 154, 0.1);
        }

        .notification-item.unread {
            background: rgba(170, 185, 154, 0.08);
            font-weight: 600;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-message {
            color: var(--text);
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 0 0 6px;
        }

        .notification-row {
            display: grid;
            grid-template-columns: 24px 1fr;
            gap: 10px;
            align-items: start;
        }

        .notification-icon {
            font-size: 1rem;
            line-height: 1.4;
        }

        .notification-time {
            color: var(--muted);
            font-size: 0.8rem;
            margin: 0;
        }

        .notification-empty {
            padding: 30px 20px;
            text-align: center;
            color: var(--muted);
        }

        .notification-empty p {
            margin: 0;
            font-size: 0.9rem;
        }

        .content {
            padding: 30px;
            display: grid;
            gap: 26px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 24px;
        }

        .hero-copy {
            position: relative;
            padding: 34px;
            border-radius: 30px;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.12), transparent 24%),
                linear-gradient(160deg, #727d73 0%, #8f997f 60%, #b9b28a 100%);
            color: #ffffff;
        }

        .hero-copy::before,
        .hero-copy::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-copy::before {
            width: 220px;
            height: 220px;
            top: -70px;
            right: -40px;
        }

        .hero-copy::after {
            width: 160px;
            height: 160px;
            bottom: -40px;
            left: -30px;
        }

        .hero-copy > * {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .hero-copy h1 {
            margin: 18px 0 16px;
            font-size: clamp(2.3rem, 5vw, 3.8rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .hero-copy p {
            margin: 0;
            max-width: 560px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 1.03rem;
            line-height: 1.75;
        }

        .hero-stats {
            display: grid;
            gap: 18px;
        }

        .stat-card {
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(114, 125, 115, 0.14);
            box-shadow: 0 18px 40px rgba(79, 88, 80, 0.07);
        }

        .stat-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: var(--muted);
        }

        .stat-card .value {
            display: block;
            margin-bottom: 8px;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .stat-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            padding: 22px 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.06);
        }

        .toolbar-copy h2 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .toolbar-copy p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 11px 16px;
            border-radius: 999px;
            text-decoration: none;
            color: #2b3950;
            font-weight: 700;
            background: rgba(170, 185, 154, 0.12);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: rgba(185, 178, 138, 0.24);
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .item-card-link {
            text-decoration: none;
            color: inherit;
            display: grid;
        }

        .item-card {
            display: grid;
            gap: 18px;
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(114, 125, 115, 0.14);
            box-shadow: 0 18px 42px rgba(79, 88, 80, 0.07);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .item-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 22px;
            background: #f3f1e7;
            border: 1px solid rgba(114, 125, 115, 0.12);
        }

        .item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 46px rgba(79, 88, 80, 0.1);
        }

        .item-card-link:hover .item-card {
            transform: translateY(-4px);
            box-shadow: 0 22px 46px rgba(79, 88, 80, 0.1);
        }

        .item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .item-title {
            margin: 0;
            font-size: 1.22rem;
            line-height: 1.2;
        }

        .status-badge {
            flex-shrink: 0;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status-badge.lost {
            background: var(--lost-bg);
            color: var(--lost-text);
        }

        .status-badge.found {
            background: var(--found-bg);
            color: var(--found-text);
        }

        .item-description {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.97rem;
        }

        .meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            border-radius: 999px;
            background: rgba(185, 178, 138, 0.2);
            color: var(--primary-dark);
            font-weight: 700;
        }

        .empty-state {
            padding: 42px 28px;
            border-radius: 30px;
            text-align: center;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.06);
        }

        .empty-state h3 {
            margin: 0 0 10px;
            font-size: 1.6rem;
            letter-spacing: -0.03em;
        }

        .empty-state p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .page-shell > *,
        .hero > * ,
        .toolbar,
        .items-grid > *,
        .empty-state {
            opacity: 0;
            transform: translateY(24px);
            animation: revealUp 0.75s ease forwards;
        }

        .topbar { animation-delay: 0.05s; }
        .hero > *:nth-child(1) { animation-delay: 0.14s; }
        .hero > *:nth-child(2) { animation-delay: 0.22s; }
        .toolbar { animation-delay: 0.3s; }
        .items-grid > *:nth-child(1) { animation-delay: 0.36s; }
        .items-grid > *:nth-child(2) { animation-delay: 0.42s; }
        .items-grid > *:nth-child(3) { animation-delay: 0.48s; }
        .items-grid > *:nth-child(4) { animation-delay: 0.54s; }
        .items-grid > *:nth-child(5) { animation-delay: 0.6s; }
        .items-grid > *:nth-child(6) { animation-delay: 0.66s; }
        .empty-state { animation-delay: 0.34s; }

        @keyframes revealUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1020px) {
            .hero,
            .items-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 16px;
            }

            .topbar,
            .content {
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-copy,
            .stat-card,
            .toolbar,
            .item-card,
            .empty-state {
                padding: 22px;
                border-radius: 24px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .page-shell > *,
            .hero > *,
            .toolbar,
            .items-grid > *,
            .empty-state {
                animation: none;
                opacity: 1;
                transform: none;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            let notificationBadge = document.getElementById('notificationBadge');

            if (!notificationBell || !notificationDropdown || !notificationList) {
                return;
            }

            notificationBell.addEventListener('click', async (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                if (notificationDropdown.classList.contains('active')) {
                    await loadNotifications();
                    await markAllNotificationsRead();
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.notification-bell') && !e.target.closest('.notification-dropdown')) {
                    notificationDropdown.classList.remove('active');
                }
            });

            function loadNotifications() {
                return fetch('notifications-api.php?action=get-notifications')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            renderNotifications(data.notifications);
                            updateBadge(data.unread_count);
                        }
                    })
                    .catch(err => console.error('Error loading notifications:', err));
            }

            function renderNotifications(notifications) {
                if (notifications.length === 0) {
                    notificationList.innerHTML = '<div class="notification-empty"><p>No notifications yet</p></div>';
                    return;
                }
                notificationList.innerHTML = notifications.map(notif => `
                    <div class="notification-item ${Number(notif.is_read) === 0 ? 'unread' : ''}" data-notification-id="${notif.id}" data-related-item-id="${notif.related_item_id ?? ''}">
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

            notificationList.addEventListener('click', (e) => {
                const item = e.target.closest('.notification-item');
                if (!item) {
                    return;
                }

                openNotification(item.dataset.notificationId, item.dataset.relatedItemId);
            });

            function openNotification(notificationId, relatedItemId) {
                fetch('notifications-api.php?action=mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'notification_id=' + notificationId
                })
                .then(() => {
                    if (relatedItemId) {
                        window.location.href = 'item-details.php?id=' + relatedItemId;
                        return;
                    }
                    loadNotifications();
                })
                .catch(err => console.error('Error:', err));
            }

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', () => markAllNotificationsRead());
            }

            function markAllNotificationsRead() {
                return fetch('notifications-api.php?action=mark-all-read')
                    .then(() => {
                        updateBadge(0);
                        return loadNotifications();
                    })
                    .catch(err => console.error('Error:', err));
            }

            function updateBadge(count) {
                if (count > 0) {
                    if (!notificationBadge) {
                        const badge = document.createElement('span');
                        badge.className = 'notification-badge';
                        badge.id = 'notificationBadge';
                        badge.textContent = count;
                        notificationBell.appendChild(badge);
                        notificationBadge = badge;
                    } else {
                        notificationBadge.textContent = count;
                        notificationBadge.style.display = 'flex';
                    }
                } else if (notificationBadge) {
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
        });
    </script>
</head>
<body>
    <main class="page-shell">
        <header class="topbar">
            <a href="dashboard.php" class="brand">
                <span class="brand-mark">C</span>
                <span>ClaimIt</span>
            </a>
            <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
                <nav class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="post_item.php">Post Item</a>
                    <a href="view_items.php" class="active">Items</a>
                    <a href="logout.php">Logout</a>
                </nav>
                
                <!-- Notification Bell -->
                <div style="position: relative;">
                    <div class="notification-bell" id="notificationBell">
                        <span class="notification-bell-icon">🔔</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge" id="notificationBadge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button id="markAllReadBtn">Mark all as read</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <p>Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow">Item Browser</span>
                    <h1>Browse lost and found reports in one clean view.</h1>
                    <p>Review posted items faster, filter by status, and scan important details without digging through a plain list.</p>
                </div>

                <div class="hero-stats">
                    <div class="stat-card">
                        <strong>Current filter</strong>
                        <span class="value"><?php echo $status ? htmlspecialchars(ucfirst($status)) : 'All'; ?></span>
                        <p>Switch between lost, found, or all records to focus on the cases you need.</p>
                    </div>
                    <div class="stat-card">
                        <strong>Visible items</strong>
                        <span class="value"><?php echo count($items); ?></span>
                        <p>Each card shows the title, description, category, and current report status.</p>
                    </div>
                </div>
            </section>

            <section class="toolbar">
                <div class="toolbar-copy">
                    <h2>Browse Items</h2>
                    <p>Filter the list below and review reports in a more readable card layout.</p>
                </div>
                <div class="filters">
                    <a class="filter-chip <?php echo $status === '' ? 'active' : ''; ?>" href="view_items.php">All</a>
                    <a class="filter-chip <?php echo $status === 'lost' ? 'active' : ''; ?>" href="view_items.php?status=lost">Lost</a>
                    <a class="filter-chip <?php echo $status === 'found' ? 'active' : ''; ?>" href="view_items.php?status=found">Found</a>
                </div>
            </section>

            <?php if (count($items) > 0): ?>
                <section class="items-grid">
                    <?php foreach ($items as $row): ?>
                        <a href="item-details.php?id=<?php echo (int)$row['id']; ?>" class="item-card-link">
                            <article class="item-card">
                                <?php if (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])): ?>
                                    <img
                                        class="item-image"
                                        src="<?php echo htmlspecialchars('uploads/' . $row['image']); ?>"
                                        alt="<?php echo htmlspecialchars($row['title']); ?>"
                                    >
                                <?php endif; ?>

                                <div class="item-top">
                                    <h3 class="item-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>

                                <p class="item-description">
                                    <?php echo htmlspecialchars(substr($row['description'] ?: 'No description provided.', 0, 120)); ?>...
                                </p>

                                <div class="meta-row">
                                    <span class="category-pill"><?php echo htmlspecialchars($row['category']); ?></span>
                                    <span>View details →</span>
                                </div>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="empty-state">
                    <h3>No items found</h3>
                    <p>There are no records for this filter yet. Try switching the status filter or post a new item to get started.</p>
                </section>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
