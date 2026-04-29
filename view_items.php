<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

// Get current user's ID for notification count
$user_id = $_SESSION['user_id'] ?? null;
$unreadCount = $user_id ? getUnreadCount($conn, $user_id) : 0;
$showAuthRequiredModal = isset($_GET['auth_required']) && $_GET['auth_required'] === '1';

$status = $_GET['status'] ?? '';
$ownerFilter = $_GET['owner'] ?? '';

$result = null;

if ($ownerFilter === 'me' && $user_id) {
    if ($status === 'claimed') {
        $stmt = $conn->prepare("SELECT * FROM items WHERE user_id = ? AND claim_status = 'claimed' ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    } elseif ($status !== '') {
        $stmt = $conn->prepare("SELECT * FROM items WHERE user_id = ? AND status = ? AND (claim_status IS NULL OR claim_status <> 'claimed') ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $status);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    } else {
        $stmt = $conn->prepare("SELECT * FROM items WHERE user_id = ? AND (claim_status IS NULL OR claim_status <> 'claimed') ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    }
} elseif ($status === 'claimed') {
    $result = $conn->query("SELECT * FROM items WHERE claim_status = 'claimed' ORDER BY id DESC");
} elseif ($status !== '') {
    $stmt = $conn->prepare("SELECT * FROM items WHERE status = ? AND (claim_status IS NULL OR claim_status <> 'claimed') ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query("SELECT * FROM items WHERE (claim_status IS NULL OR claim_status <> 'claimed') ORDER BY id DESC");
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
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 520px);
            align-items: start;
            gap: 22px;
            padding: 28px;
            border-radius: 30px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(252, 251, 247, 0.86));
            border: 1px solid rgba(124, 144, 172, 0.12);
            box-shadow: 0 22px 46px rgba(18, 32, 51, 0.06);
        }

        .toolbar-copy {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .toolbar-kicker {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(170, 185, 154, 0.14);
            color: var(--primary-dark);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .toolbar-copy h2 {
            margin: 0;
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

        .toolbar-actions {
            display: grid;
            gap: 14px;
            padding: 18px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(114, 125, 115, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .toolbar-search-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toolbar-label {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .toolbar-divider {
            height: 1px;
            background: linear-gradient(90deg, rgba(114, 125, 115, 0.12), rgba(114, 125, 115, 0.04));
        }

        .filter-chip {
            padding: 11px 18px;
            border-radius: 999px;
            text-decoration: none;
            color: #44536c;
            font-weight: 700;
            background: #f5f4ef;
            border: 1px solid rgba(114, 125, 115, 0.08);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: rgba(170, 185, 154, 0.18);
            color: var(--primary-dark);
            border-color: rgba(114, 125, 115, 0.16);
            box-shadow: 0 10px 18px rgba(79, 88, 80, 0.08);
            transform: translateY(-1px);
        }

        .search-wrap {
            position: relative;
            min-width: 0;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 14px 18px 14px 44px;
            border-radius: 18px;
            border: 1px solid rgba(114, 125, 115, 0.14);
            background: #fcfcf9;
            color: var(--text);
            font: inherit;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-input::placeholder {
            color: rgba(114, 125, 115, 0.82);
        }

        .search-input:focus {
            border-color: rgba(114, 125, 115, 0.36);
            box-shadow: 0 0 0 4px rgba(170, 185, 154, 0.18);
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
            font-size: 0.95rem;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .item-card-link {
            display: grid;
        }

        .item-card-button {
            padding: 0;
            border: 0;
            background: none;
            cursor: pointer;
            font: inherit;
            text-align: left;
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

        .item-card.own-post {
            background:
                linear-gradient(180deg, rgba(238, 243, 232, 0.98), rgba(248, 251, 245, 0.94));
            border-color: rgba(114, 125, 115, 0.28);
            box-shadow:
                0 18px 42px rgba(79, 88, 80, 0.08),
                inset 0 0 0 1px rgba(170, 185, 154, 0.18);
        }

        .item-card.own-post .item-image {
            border-color: rgba(114, 125, 115, 0.2);
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

        .item-heading {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .item-title {
            margin: 0;
            font-size: 1.22rem;
            line-height: 1.2;
        }

        .owner-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 7px 12px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(114, 125, 115, 0.18), rgba(170, 185, 154, 0.3));
            color: #5f6a60;
            border: 1px solid rgba(114, 125, 115, 0.2);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
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

        .card-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
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

        .item-action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 0;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
        }

        .item-action-link:hover {
            transform: translateY(-1px);
        }

        .item-action-link.secondary {
            color: var(--primary-dark);
            background: rgba(185, 178, 138, 0.18);
        }

        .item-action-link.primary {
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: 0 8px 20px rgba(114, 125, 115, 0.18);
        }

        .auth-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(44, 52, 45, 0.36);
            backdrop-filter: blur(6px);
            z-index: 1200;
        }

        .auth-modal.show {
            display: flex;
        }

        .auth-modal-card {
            width: min(520px, 100%);
            padding: 30px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(114, 125, 115, 0.14);
            box-shadow: 0 24px 60px rgba(79, 88, 80, 0.2);
        }

        .auth-modal-card h3 {
            margin: 0 0 10px;
            font-size: 1.8rem;
            letter-spacing: -0.03em;
        }

        .auth-modal-card p {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.7;
        }

        .auth-modal-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .auth-modal-link,
        .auth-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 12px 18px;
            border-radius: 999px;
            border: 0;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .auth-modal-link.primary {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #ffffff;
        }

        .auth-modal-link.secondary,
        .auth-modal-close {
            background: rgba(185, 178, 138, 0.18);
            color: var(--primary-dark);
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

        .search-empty-state {
            display: none;
        }

        .search-empty-state.show {
            display: block;
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

            .toolbar-actions {
                width: 100%;
                padding: 16px;
            }

            .search-wrap {
                width: 100%;
                min-width: 0;
            }

            .toolbar {
                grid-template-columns: 1fr;
                padding: 22px;
            }

            .toolbar-search-row {
                flex-direction: column;
                align-items: stretch;
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
            const isLoggedIn = <?php echo $user_id ? 'true' : 'false'; ?>;
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            let notificationBadge = document.getElementById('notificationBadge');
            const authModal = document.getElementById('authModal');
            const authModalClose = document.getElementById('authModalClose');
            const detailButtons = Array.from(document.querySelectorAll('.details-trigger'));

            const openAuthModal = () => {
                if (authModal) {
                    authModal.classList.add('show');
                }
            };

            const closeAuthModal = () => {
                if (authModal) {
                    authModal.classList.remove('show');
                }
            };

            detailButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const detailUrl = button.dataset.detailUrl;
                    if (!detailUrl) {
                        return;
                    }

                    if (isLoggedIn) {
                        window.location.href = detailUrl;
                        return;
                    }

                    openAuthModal();
                });
            });

            if (authModalClose) {
                authModalClose.addEventListener('click', closeAuthModal);
            }

            if (authModal) {
                authModal.addEventListener('click', (event) => {
                    if (event.target === authModal) {
                        closeAuthModal();
                    }
                });
            }

            if (<?php echo $showAuthRequiredModal ? 'true' : 'false'; ?>) {
                openAuthModal();
            }

            if (notificationBell && notificationDropdown && notificationList) {
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
            }

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

            if (notificationList) {
                notificationList.addEventListener('click', (e) => {
                    const item = e.target.closest('.notification-item');
                    if (!item) {
                        return;
                    }

                    openNotification(item.dataset.notificationId, item.dataset.relatedItemId);
                });
            }

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

            if (notificationBell && notificationDropdown && notificationList) {
                loadNotifications();
            }

            const searchInput = document.getElementById('itemSearch');
            const itemCards = Array.from(document.querySelectorAll('.item-card-link'));
            const visibleItemsValue = document.getElementById('visibleItemsValue');
            const searchEmptyState = document.getElementById('searchEmptyState');
            const itemsGrid = document.getElementById('itemsGrid');

            if (searchInput && itemCards.length > 0 && visibleItemsValue) {
                const applySearch = () => {
                    const query = searchInput.value.trim().toLowerCase();
                    let visibleCount = 0;

                    itemCards.forEach((card) => {
                        const searchText = card.dataset.search || '';
                        const matches = query === '' || searchText.includes(query);
                        card.style.display = matches ? '' : 'none';
                        if (matches) {
                            visibleCount += 1;
                        }
                    });

                    visibleItemsValue.textContent = visibleCount;

                    if (searchEmptyState) {
                        searchEmptyState.classList.toggle('show', visibleCount === 0);
                    }

                    if (itemsGrid) {
                        itemsGrid.style.display = visibleCount === 0 ? 'none' : 'grid';
                    }
                };

                searchInput.addEventListener('input', applySearch);
                applySearch();
            }
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
                    <a href="<?php echo $user_id ? 'post_item.php' : 'login.php'; ?>">Post Item</a>
                    <a href="view_items.php" class="active">Items</a>
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
                    <span class="eyebrow">Item Browser</span>
                    <h1>Browse lost and found reports in one clean view.</h1>
                    <p>Review posted items faster, filter by status, and scan important details without digging through a plain list.</p>
                </div>

                <div class="hero-stats">
                    <div class="stat-card">
                        <strong>Current filter</strong>
                        <span class="value">
                            <?php
                                if ($ownerFilter === 'me' && $user_id) {
                                    echo 'Your Posts';
                                } else {
                                    echo $status ? htmlspecialchars(ucfirst($status)) : 'All';
                                }
                            ?>
                        </span>
                        <p>Switch between active lost, found, or claimed records to focus on the cases you need.</p>
                    </div>
                    <div class="stat-card">
                        <strong>Visible items</strong>
                        <span class="value" id="visibleItemsValue"><?php echo count($items); ?></span>
                        <p>Each card shows the title, description, category, and current report status.</p>
                    </div>
                </div>
            </section>

            <section class="toolbar">
                <div class="toolbar-copy">
                    <span class="toolbar-kicker">Controls</span>
                    <h2>Browse Items</h2>
                    <p>Filter the list below and keep active posts separate from items that have already been claimed.</p>
                </div>
                <div class="toolbar-actions">
                    <div class="toolbar-search-row">
                        <span class="toolbar-label">Search</span>
                        <div class="search-wrap">
                            <span class="search-icon">🔎</span>
                            <input
                                type="search"
                                id="itemSearch"
                                class="search-input"
                                placeholder="Search by title, category, description, status, or location"
                                aria-label="Search items"
                            >
                        </div>
                    </div>
                    <div class="toolbar-divider"></div>
                    <div class="filters">
                        <a class="filter-chip <?php echo $status === '' ? 'active' : ''; ?>" href="view_items.php">All</a>
                        <a class="filter-chip <?php echo $status === 'lost' ? 'active' : ''; ?>" href="view_items.php?status=lost">Lost</a>
                        <a class="filter-chip <?php echo $status === 'found' ? 'active' : ''; ?>" href="view_items.php?status=found">Found</a>
                        <a class="filter-chip <?php echo $status === 'claimed' ? 'active' : ''; ?>" href="view_items.php?status=claimed">Claimed</a>
                        <?php if ($user_id): ?>
                            <a class="filter-chip <?php echo $ownerFilter === 'me' ? 'active' : ''; ?>" href="view_items.php?owner=me">Your Posts</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if (count($items) > 0): ?>
                <section class="items-grid" id="itemsGrid">
                    <?php foreach ($items as $row): ?>
                        <?php
                            $searchContent = implode(' ', [
                                $row['title'] ?? '',
                                $row['description'] ?? '',
                                $row['category'] ?? '',
                                $row['status'] ?? '',
                                $row['specific_location'] ?? '',
                                $row['keywords'] ?? '',
                            ]);
                            $detailsUrl = 'item-details.php?id=' . (int) $row['id'];
                            $isOwnPost = $user_id && ((int) ($row['user_id'] ?? 0) === (int) $user_id);
                            $canPokePoster = (($row['claim_status'] ?? 'unclaimed') !== 'claimed')
                                && (!$isOwnPost);
                        ?>
                        <article
                            class="item-card-link"
                            data-search="<?php echo htmlspecialchars(strtolower($searchContent)); ?>"
                        >
                            <div class="item-card <?php echo $isOwnPost ? 'own-post' : ''; ?>">
                                <?php if (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])): ?>
                                    <button type="button" class="item-card-button details-trigger" data-detail-url="<?php echo htmlspecialchars($detailsUrl); ?>">
                                        <img
                                            class="item-image"
                                            src="<?php echo htmlspecialchars('uploads/' . $row['image']); ?>"
                                            alt="<?php echo htmlspecialchars($row['title']); ?>"
                                        >
                                    </button>
                                <?php endif; ?>

                                <div class="item-top">
                                    <div class="item-heading">
                                        <h3 class="item-title">
                                            <button type="button" class="item-card-button details-trigger" data-detail-url="<?php echo htmlspecialchars($detailsUrl); ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['title']); ?>
                                            </button>
                                        </h3>
                                        <?php if ($isOwnPost): ?>
                                            <span class="owner-badge">Your Post</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>

                                <p class="item-description">
                                    <?php echo htmlspecialchars(substr($row['description'] ?: 'No description provided.', 0, 120)); ?>...
                                </p>

                                <div class="card-actions">
                                    <span class="category-pill"><?php echo htmlspecialchars($row['category']); ?></span>
                                    <div class="meta-row">
                                        <button type="button" class="item-action-link secondary details-trigger" data-detail-url="<?php echo htmlspecialchars($detailsUrl); ?>">View details</button>
                                        <?php if ($canPokePoster): ?>
                                            <button type="button" class="item-action-link primary details-trigger" data-detail-url="<?php echo htmlspecialchars($detailsUrl); ?>">Poke poster</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                <section class="empty-state search-empty-state" id="searchEmptyState">
                    <h3>No matching items</h3>
                    <p>Try a different keyword or switch the status filter to widen the search.</p>
                </section>
            <?php else: ?>
                <section class="empty-state">
                    <h3>No items found</h3>
                    <p>There are no records for this filter yet. Try switching between active and claimed items or post a new item to get started.</p>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <div class="auth-modal" id="authModal" aria-hidden="true">
        <div class="auth-modal-card">
            <h3>See full item details?</h3>
            <p>Browse the posted items freely, but you need an account to open the full item details and contact the poster.</p>
            <div class="auth-modal-actions">
                <a class="auth-modal-link primary" href="login.php">Login</a>
                <a class="auth-modal-link secondary" href="register.php">Register</a>
                <button type="button" class="auth-modal-close" id="authModalClose">Maybe later</button>
            </div>
        </div>
    </div>
</body>
</html>
