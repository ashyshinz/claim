<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

$user_id = $_SESSION['user_id'] ?? null;
$unreadCount = $user_id ? getUnreadCount($conn, $user_id) : 0;

/* ========== ANALYTICS DATA ========== */

// Basic counts
$lost = $conn->query("SELECT COUNT(*) as total FROM items WHERE status='lost'")->fetch_assoc()['total'];
$found = $conn->query("SELECT COUNT(*) as total FROM items WHERE status='found'")->fetch_assoc()['total'];
$total = $conn->query("SELECT COUNT(*) as total FROM items")->fetch_assoc()['total'];
$claimed = $conn->query("SELECT COUNT(*) as total FROM items WHERE claim_status='claimed'")->fetch_assoc()['total'];

// Recovery rate
$recoveryRate = ($lost > 0) ? round(($found / $lost) * 100, 1) : 0;

// Items reported this week
$week_ago = date('Y-m-d', strtotime('-7 days'));
$itemsThisWeek = $conn->query("SELECT COUNT(*) as total FROM items WHERE created_at >= '$week_ago'")->fetch_assoc()['total'];

// Most common lost item category
$mostCommonLostCategory = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM items 
    WHERE status='lost' 
    GROUP BY category 
    ORDER BY count DESC 
    LIMIT 1
");
$topLostCat = $mostCommonLostCategory ? $mostCommonLostCategory->fetch_assoc() : null;

// Most common location (hotspot) - Not available in current schema
$topLocation = null;

// Daily item reports (last 30 days)
$dailyReports = $conn->query("
    SELECT DATE(created_at) as report_date, COUNT(*) as count
    FROM items
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY report_date ASC
");
$dailyData = [];
$dailyLabels = [];
if ($dailyReports) {
    while ($row = $dailyReports->fetch_assoc()) {
        $dailyLabels[] = date('M d', strtotime($row['report_date']));
        $dailyData[] = $row['count'];
    }
}

// Pie chart data (lost vs found)
$pieLabels = ['Lost Items', 'Found Items'];
$pieData = [$lost, $found];

// Lost vs Found trend (last 30 days by status)
$trendQuery = $conn->query("
    SELECT DATE(created_at) as report_date, status, COUNT(*) as count
    FROM items
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at), status
    ORDER BY report_date ASC
");

$trendData = [];
if ($trendQuery) {
    while ($row = $trendQuery->fetch_assoc()) {
        $date = $row['report_date'];
        $status = $row['status'];
        $count = $row['count'];
        
        if (!isset($trendData[$date])) {
            $trendData[$date] = ['lost' => 0, 'found' => 0];
        }
        $trendData[$date][$status] = $count;
    }
}

// Category distribution
$categoryDistribution = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM items 
    GROUP BY category 
    ORDER BY count DESC 
    LIMIT 6
");
$categoryLabels = [];
$categoryData = [];
if ($categoryDistribution) {
    while ($row = $categoryDistribution->fetch_assoc()) {
        $categoryLabels[] = $row['category'];
        $categoryData[] = $row['count'];
    }
}

// Recent items
$recent = $conn->query("SELECT title, category, status FROM items ORDER BY id DESC LIMIT 4");
$recentItems = [];
if ($recent) {
    while ($row = $recent->fetch_assoc()) {
        $recentItems[] = $row;
    }
}

/* ========== INSIGHTS ========== */
$insights = [];

// Insight 1: Lost vs Found comparison
if ($lost > $found && $lost > 0) {
    $ratio = round($lost / $found, 1);
    $insights[] = "⚠️ More items are being lost than found - currently $lost lost items vs $found found items.";
} elseif ($found >= $lost && $found > 0) {
    $insights[] = "✅ Great recovery! Found items ($found) are meeting or exceeding lost items ($lost).";
}

// Insight 2: Recovery rate trend
if ($recoveryRate >= 80) {
    $insights[] = "🚀 Excellent recovery rate of $recoveryRate% - users are successfully matching lost and found items!";
} elseif ($recoveryRate >= 50) {
    $insights[] = "📈 Recovery rate is at $recoveryRate% - steady progress in matching items.";
} elseif ($recoveryRate > 0) {
    $insights[] = "🔍 Recovery rate is $recoveryRate% - encourage more lost item reports or found item submissions.";
}

// Insight 3: Weekly activity
if ($itemsThisWeek > 0) {
    $insights[] = "📊 This week: $itemsThisWeek new items reported - average of " . round($itemsThisWeek / 7, 1) . " per day.";
}

// Insight 5: Most common category
if ($topLostCat) {
    $insights[] = "🏷️ Most commonly lost category: <strong>" . htmlspecialchars($topLostCat['category']) . "</strong> (" . $topLostCat['count'] . " items)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ClaimIt</title>
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
            width: min(1240px, 100%);
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

        .content {
            padding: 30px;
            display: grid;
            gap: 26px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
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
            font-size: clamp(2.4rem, 5vw, 4rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .hero-copy p {
            margin: 0;
            max-width: 580px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 1.03rem;
            line-height: 1.75;
        }

        .hero-side {
            display: grid;
            gap: 18px;
        }

        .hero-card,
        .hero-stat {
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.07);
        }

        .hero-card strong,
        .hero-stat strong {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: var(--muted);
        }

        .hero-card p,
        .hero-stat p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .hero-stat .value {
            display: block;
            margin-bottom: 8px;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .stats-grid.primary {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .stat-tile {
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.06);
        }

        .stat-tile .label {
            display: block;
            margin-bottom: 10px;
            font-size: 0.95rem;
            color: var(--muted);
            font-weight: 700;
        }

        .stat-tile .value {
            display: block;
            margin-bottom: 10px;
            font-size: 2.5rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .stat-tile p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .stat-tile.lost .value {
            color: var(--lost-text);
        }

        .stat-tile.found .value {
            color: var(--found-text);
        }

        .stat-tile.claimed .value {
            color: #4f8a5b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 18px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .insights-panel {
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.06);
        }

        .insights-panel h2 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .insights-list {
            display: grid;
            gap: 12px;
        }

        .insight-item {
            padding: 16px 18px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(170, 185, 154, 0.14), rgba(185, 178, 138, 0.08));
            border-left: 4px solid var(--primary-dark);
        }

        .insight-item p {
            margin: 0;
            color: var(--text);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .stat-tile.recovery-rate .value {
            color: #10b981;
        }

        .stat-tile .category-name {
            font-size: 1.8rem;
            text-transform: capitalize;
        }

        .panel {
            padding: 24px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(124, 144, 172, 0.14);
            box-shadow: 0 18px 40px rgba(18, 32, 51, 0.06);
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .panel .panel-copy {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.65;
        }

        .chart-wrap {
            height: 320px;
        }

        .recent-list {
            display: grid;
            gap: 14px;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(170, 185, 154, 0.14);
        }

        .recent-item h3 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .recent-item p {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
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

        .empty-note {
            margin: 0;
            padding: 18px;
            border-radius: 20px;
            background: rgba(170, 185, 154, 0.14);
            color: var(--muted);
            line-height: 1.65;
        }

        .quick-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .quick-links a {
            padding: 12px 16px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            background: rgba(185, 178, 138, 0.2);
            color: var(--primary-dark);
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .quick-links a:hover {
            transform: translateY(-2px);
            background: rgba(18, 109, 255, 0.12);
        }

        .topbar,
        .hero > *,
        .stats-grid > *,
        .insights-panel,
        .charts-grid > *,
        .dashboard-grid > * {
            opacity: 0;
            transform: translateY(24px);
            animation: revealUp 0.75s ease forwards;
        }

        .topbar { animation-delay: 0.05s; }
        .hero > *:nth-child(1) { animation-delay: 0.14s; }
        .hero > *:nth-child(2) { animation-delay: 0.22s; }
        .stats-grid > *:nth-child(1) { animation-delay: 0.3s; }
        .stats-grid > *:nth-child(2) { animation-delay: 0.38s; }
        .stats-grid > *:nth-child(3) { animation-delay: 0.46s; }
        .insights-panel { animation-delay: 0.54s; }
        .charts-grid > *:nth-child(1) { animation-delay: 0.62s; }
        .charts-grid > *:nth-child(2) { animation-delay: 0.7s; }
        .charts-grid > *:nth-child(3) { animation-delay: 0.78s; }
        .dashboard-grid > *:nth-child(1) { animation-delay: 0.86s; }
        .dashboard-grid > *:nth-child(2) { animation-delay: 0.94s; }

        @keyframes revealUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1040px) {
            .hero,
            .dashboard-grid,
            .stats-grid,
            .charts-grid {
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
            .hero-card,
            .hero-stat,
            .stat-tile,
            .panel {
                padding: 22px;
                border-radius: 24px;
            }

            .recent-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .topbar,
            .hero > *,
            .stats-grid > *,
            .dashboard-grid > * {
                animation: none;
                opacity: 1;
                transform: none;
            }
        }
    </style>
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
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="<?php echo $user_id ? 'post_item.php' : 'login.php'; ?>">Post Item</a>
                    <a href="view_items.php">Items</a>
                    <?php if ($user_id): ?>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
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
                <?php endif; ?>
            </div>
        </header>

        <div class="content">
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow">Analytics Overview</span>
                    <h1>Track your lost and found activity from one clear dashboard.</h1>
                    <p>Monitor item trends, review recent submissions, and move between posting and browsing without switching to plain utility screens.</p>
                </div>

                <div class="hero-side">
                    <div class="hero-card">
                        <strong>System summary</strong>
                        <p>Your workspace brings item reporting, browsing, and analytics into one cleaner flow for faster follow-up.</p>
                    </div>
                    <div class="hero-stat">
                        <strong>Items monitored</strong>
                        <span class="value"><?php echo $total; ?></span>
                        <p>Current records across both lost and found submissions in your ClaimIt system.</p>
                    </div>
                </div>
            </section>

            <!-- OVERVIEW CARDS -->
            <section class="stats-grid primary">
                <article class="stat-tile">
                    <span class="label">Total Items</span>
                    <span class="value"><?php echo $total; ?></span>
                    <p>All item records currently stored in the system.</p>
                </article>

                <article class="stat-tile lost">
                    <span class="label">Lost Items</span>
                    <span class="value"><?php echo $lost; ?></span>
                    <p>Reports that still need matching or follow-up.</p>
                </article>

                <article class="stat-tile found">
                    <span class="label">Found Items</span>
                    <span class="value"><?php echo $found; ?></span>
                    <p>Recovered belongings recorded for identification.</p>
                </article>

                <article class="stat-tile claimed">
                    <span class="label">Claimed Items</span>
                    <span class="value"><?php echo $claimed; ?></span>
                    <p>Posts that have already been resolved and moved into the claimed area.</p>
                </article>
            </section>

            <!-- EXTENDED OVERVIEW CARDS -->
            <section class="stats-grid">
                <article class="stat-tile">
                    <span class="label">Recovery Rate</span>
                    <span class="value recovery-rate"><?php echo $recoveryRate; ?>%</span>
                    <p><?php echo $recoveryRate >= 50 ? '✅ Strong recovery performance' : '🔍 Room for improvement'; ?></p>
                </article>

                <article class="stat-tile">
                    <span class="label">This Week</span>
                    <span class="value"><?php echo $itemsThisWeek; ?></span>
                    <p><?php echo $itemsThisWeek > 0 ? 'Items reported in the last 7 days.' : 'No items reported this week.'; ?></p>
                </article>

                <article class="stat-tile">
                    <span class="label">Top Category</span>
                    <span class="value category-name"><?php echo $topLostCat ? htmlspecialchars(substr($topLostCat['category'], 0, 12)) : 'N/A'; ?></span>
                    <p><?php echo $topLostCat ? 'Most commonly lost item type.' : 'No data available.'; ?></p>
                </article>
            </section>

            <!-- INSIGHTS -->
            <?php if (count($insights) > 0): ?>
            <section class="insights-panel">
                <h2>📊 Insights</h2>
                <p class="panel-copy">Data-driven analysis of your lost and found activity.</p>
                <div class="insights-list">
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-item">
                            <p><?php echo $insight; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- ANALYTICS CHARTS -->
            <section class="charts-grid">
                <div class="panel">
                    <h2>Daily Reports (30 Days)</h2>
                    <p class="panel-copy">Item reports trend over the last month.</p>
                    <div class="chart-wrap">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <div class="panel">
                    <h2>Lost vs Found</h2>
                    <p class="panel-copy">Distribution of items by status.</p>
                    <div class="chart-wrap">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

                <div class="panel">
                    <h2>Items by Category</h2>
                    <p class="panel-copy">Most common item categories reported.</p>
                    <div class="chart-wrap">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- RECENT ACTIVITY & QUICK ACTIONS -->
            <section class="dashboard-grid">

                <div class="panel">
                    <h2>Recent Activity</h2>
                    <p class="panel-copy">The latest item submissions added to the system.</p>

                    <?php if (count($recentItems) > 0): ?>
                        <div class="recent-list">
                            <?php foreach ($recentItems as $item): ?>
                                <div class="recent-item">
                                    <div>
                                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($item['category']); ?></p>
                                    </div>
                                    <span class="status-badge <?php echo htmlspecialchars($item['status']); ?>">
                                        <?php echo htmlspecialchars($item['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-note">No items have been posted yet. Add your first item to start seeing dashboard activity.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <h2>Quick Actions</h2>
                <p class="panel-copy">Jump directly to the most common workflows in your ClaimIt workspace.</p>
                <div class="quick-links">
                    <a href="post_item.php">Post a New Item</a>
                    <a href="view_items.php">Browse All Items</a>
                    <a href="view_items.php?status=lost">View Lost Items</a>
                    <a href="view_items.php?status=found">View Found Items</a>
                </div>
            </section>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const chartDefaults = {
    font: {
        family: 'Outfit'
    }
};

// 1. DAILY TREND LINE CHART (Items over time)
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dailyLabels); ?>,
            datasets: [{
                label: 'Items Reported',
                data: <?php echo json_encode($dailyData); ?>,
                borderColor: '#727d73',
                backgroundColor: 'rgba(114, 125, 115, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#727d73',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// 2. PIE CHART (Lost vs Found)
const pieCtx = document.getElementById('pieChart');
if (pieCtx) {
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($pieLabels); ?>,
            datasets: [{
                label: 'Items',
                data: <?php echo json_encode($pieData); ?>,
                backgroundColor: ['#f59e0b', '#10b981'],
                borderColor: ['#ffffff', '#ffffff'],
                borderWidth: 6,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 18,
                        color: '#425066',
                        font: chartDefaults.font
                    }
                }
            }
        }
    });
}

// 3. CATEGORY BAR CHART
const categoryCtx = document.getElementById('categoryChart');
if (categoryCtx) {
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($categoryLabels); ?>,
            datasets: [{
                label: 'Number of Items',
                data: <?php echo json_encode($categoryData); ?>,
                backgroundColor: [
                    'rgba(185, 178, 138, 0.8)',
                    'rgba(170, 185, 154, 0.8)',
                    'rgba(114, 125, 115, 0.8)',
                    'rgba(139, 150, 125, 0.8)',
                    'rgba(159, 169, 147, 0.8)',
                    'rgba(94, 108, 94, 0.8)'
                ],
                borderColor: [
                    '#b9b28a',
                    '#aab99a',
                    '#727d73',
                    '#8b969d',
                    '#9fa993',
                    '#5e6c5e'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// ============ NOTIFICATION SYSTEM ============

const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationList = document.getElementById('notificationList');
const markAllReadBtn = document.getElementById('markAllReadBtn');
let notificationBadge = document.getElementById('notificationBadge');

// Toggle dropdown
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

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.notification-bell') && !e.target.closest('.notification-dropdown')) {
        notificationDropdown.classList.remove('active');
    }
});

// Load notifications
function loadNotifications() {
    fetch('notifications-api.php?action=get-notifications')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
            }
        })
        .catch(err => console.error('Error loading notifications:', err));
}

// Render notifications
function renderNotifications(notifications) {
    if (notifications.length === 0) {
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

// Mark single notification as read
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
    .catch(err => console.error('Error marking notification as read:', err));
}

// Mark all as read
if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', () => markAllNotificationsRead());
}

function markAllNotificationsRead() {
    return fetch('notifications-api.php?action=mark-all-read')
        .then(() => {
            updateBadge(0);
            return loadNotifications();
        })
        .catch(err => console.error('Error marking all as read:', err));
}

// Update badge count
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
    } else {
        if (notificationBadge) {
            notificationBadge.style.display = 'none';
        }
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function getNotificationIcon(type) {
    if (type === 'match') return '🔍';
    if (type === 'claim') return '🎉';
    if (type === 'request') return '📩';
    return '🔔';
}

// Load notifications on page load
loadNotifications();

</script>
</body>
</html>
