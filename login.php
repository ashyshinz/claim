<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    $user = $result ? $result->fetch_assoc() : null;

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ClaimIt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3eb;
            --panel: rgba(255, 255, 255, 0.8);
            --panel-strong: #ffffff;
            --text: #3f4740;
            --muted: #727d73;
            --primary: #aab99a;
            --primary-dark: #727d73;
            --accent: #b9b28a;
            --border: rgba(114, 125, 115, 0.22);
            --shadow: 0 30px 80px rgba(79, 88, 80, 0.16);
            --danger-bg: #fff0f0;
            --danger-text: #b42318;
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
                radial-gradient(circle at top left, rgba(185, 178, 138, 0.22), transparent 30%),
                radial-gradient(circle at bottom right, rgba(170, 185, 154, 0.2), transparent 26%),
                linear-gradient(135deg, #f7f4ea 0%, #fbfaf6 50%, #f1efe6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }

        .login-shell {
            width: min(1180px, 100%);
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .login-brand {
            position: relative;
            padding: 56px 56px 42px;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.12), transparent 26%),
                linear-gradient(180deg, rgba(114, 125, 115, 0.95), rgba(170, 185, 154, 0.96)),
                linear-gradient(135deg, #727d73, #b9b28a);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 40px;
        }

        .login-brand::before,
        .login-brand::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.09);
        }

        .login-brand::before {
            width: 240px;
            height: 240px;
            top: -90px;
            right: -60px;
        }

        .login-brand::after {
            width: 220px;
            height: 220px;
            bottom: -30px;
            left: -60px;
        }

        .brand-mark,
        .brand-copy,
        .brand-points {
            position: relative;
            z-index: 1;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
            font-size: 1.05rem;
        }

        .brand-mark span {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 1.15rem;
        }

        .brand-copy h1 {
            margin: 0 0 18px;
            font-size: clamp(2.3rem, 4vw, 3.8rem);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .brand-copy p {
            margin: 0;
            max-width: 470px;
            color: rgba(255, 255, 255, 0.76);
            font-size: 1.03rem;
            line-height: 1.75;
        }

        .brand-insights {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
            align-items: end;
        }

        .brand-point,
        .brand-stat {
            padding: 20px 22px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }

        .brand-point strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .brand-point p {
            margin: 0;
            color: rgba(255, 255, 255, 0.76);
            line-height: 1.6;
            font-size: 0.94rem;
        }

        .brand-stat {
            text-align: left;
        }

        .brand-stat .stat-number {
            display: block;
            margin-bottom: 8px;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
        }

        .brand-stat p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.6;
            font-size: 0.92rem;
        }

        .mini-card {
            position: relative;
            right: auto;
            top: auto;
            width: 100%;
            min-height: 100%;
            padding: 18px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(12px);
            z-index: 1;
        }

        .mini-card .mini-label {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .mini-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .mini-card p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.86rem;
            line-height: 1.55;
        }

        .login-panel {
            padding: 56px 52px;
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: min(430px, 100%);
        }

        .login-card > * {
            opacity: 0;
            transform: translateY(22px);
            animation: revealUp 0.7s ease forwards;
        }

        .login-card > *:nth-child(1) { animation-delay: 0.05s; }
        .login-card > *:nth-child(2) { animation-delay: 0.12s; }
        .login-card > *:nth-child(3) { animation-delay: 0.19s; }
        .login-card > *:nth-child(4) { animation-delay: 0.26s; }
        .login-card > *:nth-child(5) { animation-delay: 0.33s; }

        .login-brand > * {
            opacity: 0;
            transform: translateY(26px);
            animation: revealUp 0.8s ease forwards;
        }

        .login-brand > *:nth-child(1) { animation-delay: 0.06s; }
        .login-brand > *:nth-child(2) { animation-delay: 0.14s; }
        .login-brand > *:nth-child(3) { animation-delay: 0.22s; }
        .login-brand > *:nth-child(4) { animation-delay: 0.3s; }

        .eyebrow {
            display: inline-block;
            margin-bottom: 16px;
            padding: 9px 13px;
            border-radius: 999px;
            background: rgba(185, 178, 138, 0.22);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .login-card h2 {
            margin: 0 0 12px;
            font-size: 2.4rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .login-card .intro {
            margin: 0 0 30px;
            color: var(--muted);
            line-height: 1.75;
            font-size: 1rem;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 0.94rem;
            font-weight: 600;
            border: 1px solid rgba(180, 35, 24, 0.08);
        }

        form {
            display: grid;
            gap: 18px;
        }

        .field {
            display: grid;
            gap: 10px;
            position: relative;
        }

        .field.is-focused label,
        .field.is-filled label {
            color: var(--primary-dark);
        }

        label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #4b554c;
        }

        input {
            width: 100%;
            padding: 17px 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.92);
            color: var(--text);
            font: inherit;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .field.has-toggle input {
            padding-right: 60px;
        }

        input::placeholder {
            color: #8c958c;
        }

        input:focus {
            outline: none;
            border-color: rgba(170, 185, 154, 0.75);
            box-shadow: 0 0 0 4px rgba(185, 178, 138, 0.18);
            transform: translateY(-1px);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 44px;
            border: 0;
            background: transparent;
            color: #727d73;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: none;
            padding: 0;
        }

        .password-toggle:hover {
            transform: none;
            box-shadow: none;
            filter: none;
            background: rgba(185, 178, 138, 0.18);
            color: var(--primary-dark);
        }

        .password-toggle:focus-visible {
            outline: 2px solid rgba(170, 185, 154, 0.45);
            outline-offset: 2px;
        }

        .field-hint {
            margin-top: -4px;
            text-align: right;
            font-size: 0.84rem;
            color: var(--muted);
        }

        .field-hint a {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
        }

        .field-hint a:hover {
            text-decoration: underline;
        }

        .form-actions {
            display: grid;
            gap: 18px;
            margin-top: 8px;
        }

        button {
            width: 100%;
            padding: 17px 18px;
            border: 0;
            border-radius: 18px;
            background: linear-gradient(135deg, #727d73, #aab99a);
            color: #ffffff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 16px 32px rgba(114, 125, 115, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(114, 125, 115, 0.28);
            filter: brightness(1.02);
        }

        button.is-loading {
            pointer-events: none;
            opacity: 0.92;
        }

        button.is-loading .button-text {
            opacity: 0.15;
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

        button.is-loading .button-loader {
            opacity: 1;
        }

        .register-link {
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .mini-card.is-pulsing {
            animation: pulseCard 1.8s ease-in-out infinite;
        }

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

        @keyframes pulseCard {
            0%, 100% {
                transform: translateY(0);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.08);
            }
            50% {
                transform: translateY(-4px);
                box-shadow: 0 16px 30px 0 rgba(7, 18, 43, 0.14);
            }
        }

        @media (max-width: 920px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .login-brand,
            .login-panel {
                padding: 36px 28px;
            }

            .brand-insights {
                grid-template-columns: 1fr;
            }

            .mini-card {
                width: 100%;
                margin-top: 0;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 14px;
            }

            .login-brand,
            .login-panel {
                padding: 28px 20px;
            }

            .login-card h2 {
                font-size: 1.7rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-card > *,
            .login-brand > *,
            .mini-card.is-pulsing {
                animation: none;
                opacity: 1;
                transform: none;
            }

            * {
                scroll-behavior: auto;
            }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="login-brand">
            <div class="brand-mark">
                <span>C</span>
                <div>ClaimIt</div>
            </div>

            <div class="brand-copy">
                <h1>Manage every claim with clarity and confidence.</h1>
                <p>
                    Organize reports, review submissions faster, and reconnect people with their belongings through a clean workspace built for daily use.
                </p>
            </div>

            <div class="brand-insights">
                <div class="brand-point">
                    <strong>Organized reporting</strong>
                    <p>Track lost and found records in a way that feels structured, searchable, and easy to manage.</p>
                </div>
                <div class="mini-card brand-stat" id="liveOverviewCard">
                    <span class="mini-label">Live Overview</span>
                    <strong id="liveOverviewValue">12 pending reports</strong>
                    <p id="liveOverviewText">Stay on top of new submissions and continue where you left off without losing context.</p>
                </div>
                <div class="brand-stat">
                    <span class="stat-number">24/7</span>
                    <p>Access your dashboard anytime and keep every update in one place.</p>
                </div>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <span class="eyebrow">Secure Access</span>
                <h2>Sign in to ClaimIt</h2>
                <p class="intro">Use your account to access the dashboard, review active cases, and keep your lost and found workflow moving.</p>

                <?php if ($error): ?>
                    <div class="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="loginForm" novalidate>
                    <div class="field" data-field>
                        <label for="email">Email address</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            placeholder="you@example.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required
                        >
                    </div>

                    <div class="field has-toggle" data-field>
                        <label for="password">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false">👁</button>
                        <div class="field-hint">
                            <a href="#">Forgot password?</a>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" id="loginSubmit">
                            <span class="button-content">
                                <span class="button-text">Login to Dashboard</span>
                                <span class="button-loader" aria-hidden="true"></span>
                            </span>
                        </button>
                        <div class="register-link">
                            Don&apos;t have an account? <a href="register.php">Create one here</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <script>
        const loginForm = document.getElementById('loginForm');
        const passwordInput = document.getElementById('password');
        const emailInput = document.getElementById('email');
        const passwordToggle = document.getElementById('passwordToggle');
        const loginSubmit = document.getElementById('loginSubmit');
        const liveOverviewCard = document.getElementById('liveOverviewCard');
        const liveOverviewValue = document.getElementById('liveOverviewValue');
        const liveOverviewText = document.getElementById('liveOverviewText');
        const fields = document.querySelectorAll('[data-field]');

        const refreshFieldState = (field) => {
            const input = field.querySelector('input');
            if (!input) return;
            field.classList.toggle('is-filled', input.value.trim() !== '');
        };

        fields.forEach((field) => {
            const input = field.querySelector('input');
            if (!input) return;

            refreshFieldState(field);

            input.addEventListener('focus', () => field.classList.add('is-focused'));
            input.addEventListener('blur', () => {
                field.classList.remove('is-focused');
                refreshFieldState(field);
            });
            input.addEventListener('input', () => refreshFieldState(field));
        });

        if (passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                passwordToggle.textContent = isPassword ? '🙈' : '👁';
                passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                passwordToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            });
        }

        const updateOverview = () => {
            const hasEmail = emailInput.value.trim() !== '';
            const hasPassword = passwordInput.value.trim() !== '';

            if (hasEmail && hasPassword) {
                liveOverviewValue.textContent = 'Ready to sign in';
                liveOverviewText.textContent = 'Your account details look complete. Continue to access your active dashboard.';
                liveOverviewCard.classList.add('is-pulsing');
            } else if (hasEmail) {
                liveOverviewValue.textContent = 'Email detected';
                liveOverviewText.textContent = 'Nice, your email is filled in. Add your password to continue securely.';
                liveOverviewCard.classList.remove('is-pulsing');
            } else {
                liveOverviewValue.textContent = '12 pending reports';
                liveOverviewText.textContent = 'Stay on top of new submissions and continue where you left off without losing context.';
                liveOverviewCard.classList.remove('is-pulsing');
            }
        };

        emailInput.addEventListener('input', updateOverview);
        passwordInput.addEventListener('input', updateOverview);
        updateOverview();

        loginForm.addEventListener('submit', (event) => {
            if (!loginForm.checkValidity()) {
                event.preventDefault();
                loginForm.reportValidity();
                return;
            }

            loginSubmit.classList.add('is-loading');
            loginSubmit.setAttribute('aria-busy', 'true');
        });
    </script>
</body>
</html>
