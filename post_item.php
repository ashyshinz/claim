<?php
session_start();
include 'config.php';
include 'notifications-helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get current user's full name
$user_id = $_SESSION['user_id'];
$userResult = $conn->query("SELECT name FROM users WHERE id=$user_id");
$userData = $userResult->fetch_assoc();
$userName = $userData['name'] ?? 'Unknown User';

// Get unread notification count
$unreadCount = getUnreadCount($conn, $user_id);

$success = '';
$error = '';
$matchSuggestions = [];
$matchTargetLabel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $category = $_POST['category'];
    $status = $_POST['status'];
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');
    $specific_location = trim($_POST['specific_location'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Validation
    if (empty($title) || empty($contact_info) || empty($specific_location)) {
        $error = 'Please fill in all required fields: Title, Contact Info, and Location.';
    }

    // Validate email or phone
    if (!empty($contact_info) && !filter_var($contact_info, FILTER_VALIDATE_EMAIL)) {
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $contact_info)) {
            $error = 'Please enter a valid phone number or email address.';
        }
    }

    $imageName = '';
    $allowedExtensions = ['jpg', 'jpeg', 'png'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'There was a problem uploading the image.';
        } else {
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = $_FILES['image']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                $error = 'Only JPG, JPEG, and PNG files are allowed.';
            } else {
                $imageName = uniqid('item_', true) . '.' . $extension;
                $targetPath = $uploadDir . $imageName;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $error = 'Failed to save the uploaded image.';
                }
            }
        }
    }

    if ($error === '') {
        $user_id = (int) $user_id;

        $sql = "INSERT INTO items (title, description, category, status, user_id, image, contact_name, contact_info, specific_location, keywords)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                'ssssisssss',
                $title,
                $desc,
                $category,
                $status,
                $user_id,
                $imageName,
                $contact_name,
                $contact_info,
                $specific_location,
                $keywords
            );
        }

        if ($stmt && $stmt->execute()) {
            $new_item_id = $stmt->insert_id;
            $stmt->close();
            $success = 'Item posted successfully.';
            $matchTargetLabel = $status === 'lost' ? 'found' : 'lost';
            $newItem = [
                'id' => $new_item_id,
                'title' => $title,
                'description' => $desc,
                'category' => $category,
                'status' => $status,
                'keywords' => $keywords,
                'specific_location' => $specific_location,
                'user_id' => $user_id,
            ];

            $matchSuggestions = findTopMatches($conn, $newItem);

            if (count($matchSuggestions) > 0) {
                notifyMatchParticipants($conn, $newItem, $matchSuggestions);
            }

            $matchSuggestions = array_map(static function ($match) {
                unset($match['user_id'], $match['claim_status']);
                return $match;
            }, $matchSuggestions);

            $_POST = [];
        } else {
            if ($stmt) {
                $stmt->close();
            }

            $error = 'Something went wrong while posting your item.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Item | ClaimIt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3eb;
            --surface: rgba(255, 255, 255, 0.78);
            --surface-strong: #ffffff;
            --text: #3f4740;
            --muted: #727d73;
            --primary: #aab99a;
            --primary-dark: #727d73;
            --accent: #b9b28a;
            --border: rgba(114, 125, 115, 0.2);
            --shadow: 0 28px 70px rgba(79, 88, 80, 0.14);
            --success-bg: #ecfff7;
            --success-text: #117a54;
            --error-bg: #fff2f2;
            --error-text: #c0392b;
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
                radial-gradient(circle at top left, rgba(185, 178, 138, 0.2), transparent 28%),
                radial-gradient(circle at bottom right, rgba(170, 185, 154, 0.18), transparent 30%),
                linear-gradient(135deg, #f7f4ea 0%, #fbfaf6 52%, #f1efe6 100%);
            padding: 28px 20px;
        }

        .page-shell {
            width: min(1220px, 100%);
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.68);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.64);
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
            font-size: 1.18rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--text);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: 0 12px 26px rgba(114, 125, 115, 0.24);
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
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 28px;
            padding: 30px;
        }

        .hero-panel {
            position: relative;
            padding: 34px;
            border-radius: 30px;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.14), transparent 24%),
                linear-gradient(160deg, #727d73 0%, #8f997f 58%, #b9b28a 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 640px;
        }

        .hero-panel::before,
        .hero-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-panel::before {
            width: 240px;
            height: 240px;
            top: -80px;
            right: -40px;
        }

        .hero-panel::after {
            width: 180px;
            height: 180px;
            bottom: -50px;
            left: -40px;
        }

        .hero-panel > * {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
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
            max-width: 520px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 1.04rem;
            line-height: 1.75;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .hero-card,
        .hero-stat {
            padding: 22px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(12px);
        }

        .hero-card strong,
        .hero-stat strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .hero-card p,
        .hero-stat p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.6;
            font-size: 0.94rem;
        }

        .hero-stat .big-number {
            display: block;
            margin-bottom: 10px;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1;
        }

        .form-panel {
            padding: 18px 0;
            display: flex;
            align-items: center;
        }

        .form-card {
            width: 100%;
            padding: 34px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(114, 125, 115, 0.14);
            box-shadow: 0 20px 45px rgba(79, 88, 80, 0.08);
        }

        .form-card h2 {
            margin: 0 0 10px;
            font-size: 2.15rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .form-card .intro {
            margin: 0 0 26px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 1rem;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 0.94rem;
        }

        .alert.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid rgba(17, 122, 84, 0.12);
        }

        .alert.error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid rgba(192, 57, 43, 0.12);
        }

        .matches-panel {
            margin-bottom: 18px;
            padding: 20px;
            border-radius: 22px;
            background: rgba(170, 185, 154, 0.12);
            border: 1px solid rgba(114, 125, 115, 0.14);
        }

        .matches-panel h3 {
            margin: 0 0 8px;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
        }

        .matches-panel p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .matches-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .match-card {
            display: grid;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(114, 125, 115, 0.12);
        }

        .match-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .match-card h4 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .match-card .meta {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .match-score {
            flex-shrink: 0;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(185, 178, 138, 0.22);
            color: #6d6544;
            font-weight: 800;
            font-size: 0.86rem;
        }

        .keyword-row {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .keyword-row strong {
            color: #4b554c;
        }

        form {
            display: grid;
            gap: 18px;
        }

        .field {
            display: grid;
            gap: 10px;
        }

        .field label {
            font-size: 0.92rem;
            font-weight: 700;
            color: #4b554c;
        }

        .field label .required {
            color: #c0392b;
            margin-left: 4px;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--text);
            font: inherit;
            padding: 16px 18px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .field-note {
            margin-top: -2px;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .image-preview-wrap {
            display: none;
            margin-top: 6px;
        }

        .image-preview {
            width: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: 18px;
            border: 1px solid rgba(114, 125, 115, 0.18);
            box-shadow: 0 14px 28px rgba(79, 88, 80, 0.08);
        }

        .field input:invalid:not(:placeholder-shown),
        .field textarea:invalid:not(:placeholder-shown),
        .field select:invalid {
            border-color: #c0392b;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 0 0 4px rgba(192, 57, 43, 0.12);
        }

        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            outline: none;
            border-color: rgba(170, 185, 154, 0.75);
            box-shadow: 0 0 0 4px rgba(185, 178, 138, 0.18);
            transform: translateY(-1px);
        }

        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .field-grid.three-cols {
            grid-template-columns: repeat(3, 1fr);
        }

        .helper-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(170, 185, 154, 0.2);
            color: #5e675f;
            font-weight: 700;
        }

        .status-chip[data-mode="lost"] {
            background: rgba(185, 178, 138, 0.24);
            color: #8a7f58;
        }

        .submit-btn {
            width: 100%;
            padding: 17px 18px;
            border: 0;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #ffffff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 16px 34px rgba(114, 125, 115, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px rgba(114, 125, 115, 0.28);
            filter: brightness(1.02);
        }

        .submit-btn.is-loading {
            pointer-events: none;
            opacity: 0.92;
        }

        .button-content {
            position: relative;
            display: inline-grid;
            place-items: center;
        }

        .button-loader {
            position: absolute;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 50%;
            opacity: 0;
            animation: spin 0.8s linear infinite;
        }

        .submit-btn.is-loading .button-text {
            opacity: 0.15;
        }

        .submit-btn.is-loading .button-loader {
            opacity: 1;
        }

        .form-card > *,
        .hero-panel > * {
            opacity: 0;
            transform: translateY(24px);
            animation: revealUp 0.75s ease forwards;
        }

        .hero-panel > *:nth-child(1) { animation-delay: 0.05s; }
        .hero-panel > *:nth-child(2) { animation-delay: 0.14s; }
        .hero-panel > *:nth-child(3) { animation-delay: 0.24s; }
        .form-card > *:nth-child(1) { animation-delay: 0.08s; }
        .form-card > *:nth-child(2) { animation-delay: 0.16s; }
        .form-card > *:nth-child(3) { animation-delay: 0.24s; }
        .form-card > *:nth-child(4) { animation-delay: 0.32s; }

        @keyframes revealUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 980px) {
            .content {
                grid-template-columns: 1fr;
            }

            .hero-panel {
                min-height: auto;
            }

            .field-grid {
                grid-template-columns: 1fr;
            }

            .field-grid.three-cols {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
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

            .hero-panel,
            .form-card {
                padding: 24px;
                border-radius: 24px;
            }

            .field-grid,
            .hero-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .form-card > *,
            .hero-panel > * {
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
                    <a href="dashboard.php">Dashboard</a>
                    <a href="post_item.php" class="active">Post Item</a>
                    <a href="view_items.php">Items</a>
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
            <section class="hero-panel">
                <div class="eyebrow">Item Submission</div>

                <div class="hero-copy">
                    <h1>Post a new item with clear, complete details.</h1>
                    <p>Help people identify the right item faster by adding a strong title, useful description, and the correct lost or found status.</p>
                </div>

                <div class="hero-grid">
                    <div class="hero-card">
                        <strong>Better matching</strong>
                        <p>Clear item names and descriptions make it easier for users to recognize and claim belongings.</p>
                    </div>
                    <div class="hero-stat">
                        <span class="big-number">Fast</span>
                        <p>One clean form keeps your item submissions consistent and easy to review later.</p>
                    </div>
                </div>
            </section>

            <section class="form-panel">
                <div class="form-card">
                    <h2>Create item post</h2>
                    <p class="intro">Fill out the form below to add a lost or found item to the system. Keep the details specific so matching becomes easier.</p>

                    <?php if ($success): ?>
                        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="matches-panel">
                            <h3>Smart match suggestions</h3>
                            <p>
                                The system checked this new <?php echo htmlspecialchars($_POST['status'] ?? $status ?? 'item'); ?> post
                                against existing <?php echo htmlspecialchars($matchTargetLabel ?: 'opposite-status'); ?> reports and ranked the closest matches.
                            </p>

                            <?php if (count($matchSuggestions) > 0): ?>
                                <div class="matches-list">
                                    <?php foreach ($matchSuggestions as $match): ?>
                                        <article class="match-card">
                                            <div class="match-card-top">
                                                <div>
                                                    <h4><?php echo htmlspecialchars($match['title']); ?></h4>
                                                    <p class="meta">
                                                        <?php echo htmlspecialchars($match['category']); ?> •
                                                        <?php echo htmlspecialchars(ucfirst($match['status'])); ?>
                                                    </p>
                                                </div>
                                                <span class="match-score"><?php echo (int) $match['match_score']; ?>% match</span>
                                            </div>

                                            <p class="meta">
                                                <?php echo htmlspecialchars($match['description'] ?: 'No description provided for this item.'); ?>
                                            </p>

                                            <?php if (!empty($match['shared_keywords'])): ?>
                                                <div class="keyword-row">
                                                    <strong>Shared keywords:</strong>
                                                    <?php echo htmlspecialchars(implode(', ', $match['shared_keywords'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="margin-top: 14px;">No strong matches were found yet. As more items are posted, this smart matching section will keep getting better.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="postItemForm" enctype="multipart/form-data">
                        <div class="field">
                            <label>Posted by</label>
                            <div style="padding: 16px 18px; border-radius: 18px; background: rgba(170, 185, 154, 0.12); border: 1px solid var(--border); color: #5e675f; font-weight: 600;">
                                <?php echo htmlspecialchars($userName); ?>
                            </div>
                        </div>

                        <div class="field">
                            <label for="title">Item title <span class="required">*</span></label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                placeholder="Example: Black wireless earbuds"
                                value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="description">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                placeholder="Describe the item, its condition, distinctive features, brand, model, color, or any detail that helps identify it."
                            ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="field">
                            <label for="contact_name">Your Name</label>
                            <input
                                id="contact_name"
                                type="text"
                                name="contact_name"
                                placeholder="Enter your full name"
                                value="<?php echo isset($_POST['contact_name']) ? htmlspecialchars($_POST['contact_name']) : ''; ?>"
                            >
                        </div>

                        <div class="field">
                            <label for="contact_info">Contact Information <span class="required">*</span></label>
                            <input
                                id="contact_info"
                                type="text"
                                name="contact_info"
                                placeholder="Enter phone number or email (e.g., +1-234-567-8900 or user@example.com)"
                                value="<?php echo isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : ''; ?>"
                                required
                            >
                            <div class="field-note">Required: Provide a phone number or email for others to contact you.</div>
                        </div>

                        <div class="field">
                            <label for="specific_location">Specific Location <span class="required">*</span></label>
                            <input
                                id="specific_location"
                                type="text"
                                name="specific_location"
                                placeholder="e.g., Near cafeteria, Room 204, Front desk, Platform 3"
                                value="<?php echo isset($_POST['specific_location']) ? htmlspecialchars($_POST['specific_location']) : ''; ?>"
                                required
                            >
                            <div class="field-note">Be specific: This helps others identify the exact location.</div>
                        </div>

                        <div class="field">
                            <label for="keywords">Keywords for Matching</label>
                            <input
                                id="keywords"
                                type="text"
                                name="keywords"
                                placeholder="e.g., black, leather, wallet, ID, Apple, Samsung"
                                value="<?php echo isset($_POST['keywords']) ? htmlspecialchars($_POST['keywords']) : ''; ?>"
                            >
                            <div class="field-note">Comma-separated keywords help with smart matching. Example: iPhone 14, silver, cracked screen</div>
                        </div>

                        <div class="field">
                            <label for="image">Item Image</label>
                            <input
                                id="image"
                                type="file"
                                name="image"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                            >
                            <div class="field-note">Allowed files: JPG, JPEG, PNG. Image preview will appear below.</div>
                            <div id="imagePreviewWrap" class="image-preview-wrap">
                                <img
                                    id="imagePreview"
                                    class="image-preview"
                                    src=""
                                    alt="Selected image preview"
                                >
                            </div>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="Electronics" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="Others" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="status">Status <span class="required">*</span></label>
                                <select id="status" name="status">
                                    <option value="lost" <?php echo (isset($_POST['status']) && $_POST['status'] === 'lost') ? 'selected' : ''; ?>>Lost</option>
                                    <option value="found" <?php echo (isset($_POST['status']) && $_POST['status'] === 'found') ? 'selected' : ''; ?>>Found</option>
                                </select>
                            </div>
                        </div>

                        <div class="helper-row">
                            <span>All fields marked with <strong style="color: #c0392b;">*</strong> are required.</span>
                            <span class="status-chip" id="statusChip" data-mode="<?php echo isset($_POST['status']) ? htmlspecialchars($_POST['status']) : 'lost'; ?>">
                                <?php echo isset($_POST['status']) && $_POST['status'] === 'found' ? 'Found item' : 'Lost item'; ?>
                            </span>
                        </div>

                        <button type="submit" class="submit-btn" id="submitPostBtn">
                            <span class="button-content">
                                <span class="button-text">Publish Item</span>
                                <span class="button-loader" aria-hidden="true"></span>
                            </span>
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const postItemForm = document.getElementById('postItemForm');
        const submitPostBtn = document.getElementById('submitPostBtn');
        const statusSelect = document.getElementById('status');
        const statusChip = document.getElementById('statusChip');
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewWrap = document.getElementById('imagePreviewWrap');
        const contactInfoInput = document.getElementById('contact_info');

        const updateStatusChip = () => {
            const mode = statusSelect.value;
            statusChip.dataset.mode = mode;
            statusChip.textContent = mode === 'found' ? 'Found item' : 'Lost item';
        };

        // Validate email or phone format
        const validateContactInfo = (value) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            
            if (!value.trim()) return false;
            return emailRegex.test(value) || phoneRegex.test(value);
        };

        if (contactInfoInput) {
            contactInfoInput.addEventListener('blur', () => {
                if (contactInfoInput.value && !validateContactInfo(contactInfoInput.value)) {
                    contactInfoInput.setCustomValidity('Please enter a valid phone number or email address');
                } else {
                    contactInfoInput.setCustomValidity('');
                }
            });
        }

        if (statusSelect && statusChip) {
            statusSelect.addEventListener('change', updateStatusChip);
            updateStatusChip();
        }

        if (postItemForm && submitPostBtn) {
            postItemForm.addEventListener('submit', (event) => {
                // Validate required fields
                const title = document.getElementById('title').value.trim();
                const contactInfo = document.getElementById('contact_info').value.trim();
                const specificLocation = document.getElementById('specific_location').value.trim();

                if (!title || !contactInfo || !specificLocation) {
                    event.preventDefault();
                    alert('Please fill in all required fields: Title, Contact Information, and Specific Location.');
                    return;
                }

                if (!validateContactInfo(contactInfo)) {
                    event.preventDefault();
                    alert('Please enter a valid phone number or email address.');
                    return;
                }

                submitPostBtn.classList.add('is-loading');
                submitPostBtn.setAttribute('aria-busy', 'true');
            });
        }

        if (imageInput && imagePreview && imagePreviewWrap) {
            imageInput.addEventListener('change', () => {
                const file = imageInput.files[0];

                if (!file) {
                    imagePreview.src = '';
                    imagePreviewWrap.style.display = 'none';
                    return;
                }

                const fileName = file.name.toLowerCase();
                const isAllowed = /\.(jpg|jpeg|png)$/.test(fileName);

                if (!isAllowed) {
                    imageInput.value = '';
                    imagePreview.src = '';
                    imagePreviewWrap.style.display = 'none';
                    alert('Only JPG, JPEG, and PNG files are allowed.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    imagePreview.src = event.target.result;
                    imagePreviewWrap.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        }

        // ============ NOTIFICATION SYSTEM ============
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
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
            } else {
                if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
            }
        }

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

        loadNotifications();
    </script>
</body>
</html>
