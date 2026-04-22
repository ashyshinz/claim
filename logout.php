<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout | ClaimIt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --text: #3f4740;
            --muted: #727d73;
            --primary: #aab99a;
            --primary-dark: #727d73;
            --accent: #b9b28a;
            --border: rgba(114, 125, 115, 0.18);
            --shadow: 0 28px 70px rgba(79, 88, 80, 0.12);
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
                radial-gradient(circle at top left, rgba(185, 178, 138, 0.22), transparent 28%),
                radial-gradient(circle at bottom right, rgba(170, 185, 154, 0.2), transparent 30%),
                linear-gradient(135deg, #f7f4ea 0%, #fbfaf6 52%, #f1efe6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirm-card {
            width: min(520px, 100%);
            padding: 36px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .badge {
            display: inline-block;
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(185, 178, 138, 0.22);
            color: var(--primary-dark);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(2rem, 5vw, 2.8rem);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        p {
            margin: 0 0 28px;
            color: var(--muted);
            line-height: 1.75;
            font-size: 1rem;
        }

        .actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn,
        .link-btn {
            min-width: 170px;
            padding: 15px 18px;
            border-radius: 18px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .btn {
            border: 0;
            cursor: pointer;
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: 0 16px 32px rgba(114, 125, 115, 0.24);
        }

        .btn:hover,
        .link-btn:hover {
            transform: translateY(-2px);
        }

        .link-btn {
            color: var(--text);
            background: rgba(170, 185, 154, 0.14);
            border: 1px solid rgba(114, 125, 115, 0.16);
        }

        @media (max-width: 560px) {
            .confirm-card {
                padding: 28px 22px;
                border-radius: 24px;
            }

            .actions {
                flex-direction: column;
            }

            .btn,
            .link-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="confirm-card">
        <span class="badge">Logout Confirmation</span>
        <h1>Are you sure you want to log out?</h1>
        <p>You’ll be signed out of your ClaimIt session and returned to the login page. You can always sign back in whenever you need.</p>

        <div class="actions">
            <form method="POST">
                <button class="btn" type="submit" name="confirm_logout" value="1">Yes, Log Me Out</button>
            </form>
            <a class="link-btn" href="dashboard.php">Cancel</a>
        </div>
    </main>
</body>
</html>
