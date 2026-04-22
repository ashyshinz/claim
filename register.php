<?php
include 'config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name,email,password)
            VALUES ('$name','$email','$password')";

    if ($conn->query($sql)) {
        $success = 'Account created successfully. You can sign in now.';
        $_POST = [];
    } else {
        $error = 'Something went wrong while creating your account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | ClaimIt</title>
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
            --success-bg: #eef3e8;
            --success-text: #5e675f;
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

        .register-shell {
            width: min(1180px, 100%);
            display: grid;
            grid-template-columns: 1.02fr 0.98fr;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.66);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .register-brand {
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

        .register-brand::before,
        .register-brand::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.09);
        }

        .register-brand::before {
            width: 240px;
            height: 240px;
            top: -90px;
            right: -60px;
        }

        .register-brand::after {
            width: 220px;
            height: 220px;
            bottom: -30px;
            left: -60px;
        }

        .register-brand > * {
            position: relative;
            z-index: 1;
            opacity: 0;
            transform: translateY(26px);
            animation: revealUp 0.8s ease forwards;
        }

        .register-brand > *:nth-child(1) { animation-delay: 0.06s; }
        .register-brand > *:nth-child(2) { animation-delay: 0.14s; }
        .register-brand > *:nth-child(3) { animation-delay: 0.22s; }

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
            font-size: clamp(2.35rem, 4vw, 3.8rem);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .brand-copy p {
            margin: 0;
            max-width: 480px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 1.03rem;
            line-height: 1.75;
        }

        .brand-insights {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .brand-card {
            padding: 20px 22px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }

        .brand-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .brand-card p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.6;
            font-size: 0.94rem;
        }

        .register-panel {
            padding: 56px 52px;
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-card {
            width: min(440px, 100%);
        }

        .register-card > * {
            opacity: 0;
            transform: translateY(22px);
            animation: revealUp 0.72s ease forwards;
        }

        .register-card > *:nth-child(1) { animation-delay: 0.08s; }
        .register-card > *:nth-child(2) { animation-delay: 0.16s; }
        .register-card > *:nth-child(3) { animation-delay: 0.24s; }
        .register-card > *:nth-child(4) { animation-delay: 0.32s; }
        .register-card > *:nth-child(5) { animation-delay: 0.4s; }

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

        .register-card h2 {
            margin: 0 0 12px;
            font-size: 2.35rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .intro {
            margin: 0 0 28px;
            color: var(--muted);
            line-height: 1.75;
            font-size: 1rem;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            font-size: 0.94rem;
            font-weight: 600;
        }

        .alert.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid rgba(114, 125, 115, 0.14);
        }

        .alert.error {
            background: var(--danger-bg);
            color: var(--danger-text);
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
            padding-right: 72px;
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
            min-width: 42px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            cursor: pointer;
            padding: 0 8px;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .password-toggle:hover {
            background: rgba(185, 178, 138, 0.18);
            color: var(--primary-dark);
        }

        .password-toggle:focus-visible {
            outline: 2px solid rgba(170, 185, 154, 0.45);
            outline-offset: 2px;
        }

        .password-note {
            margin-top: -4px;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .form-actions {
            display: grid;
            gap: 18px;
            margin-top: 8px;
        }

        button[type="submit"] {
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

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(114, 125, 115, 0.28);
            filter: brightness(1.02);
        }

        button[type="submit"].is-loading {
            pointer-events: none;
            opacity: 0.92;
        }

        button[type="submit"].is-loading .button-text {
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

        button[type="submit"].is-loading .button-loader {
            opacity: 1;
        }

        .login-link {
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
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

        @media (max-width: 920px) {
            .register-shell {
                grid-template-columns: 1fr;
            }

            .register-brand,
            .register-panel {
                padding: 36px 28px;
            }

            .brand-insights {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 14px;
            }

            .register-brand,
            .register-panel {
                padding: 28px 20px;
            }

            .register-card h2 {
                font-size: 1.8rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .register-brand > *,
            .register-card > * {
                animation: none;
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>
<body>
    <main class="register-shell">
        <section class="register-brand">
            <div class="brand-mark">
                <span>C</span>
                <div>ClaimIt</div>
            </div>

            <div class="brand-copy">
                <h1>Create your account and start managing claims smoothly.</h1>
                <p>Join the workspace, organize lost and found records, and keep every report easier to post, track, and review.</p>
            </div>

            <div class="brand-insights">
                <div class="brand-card">
                    <strong>Simple onboarding</strong>
                    <p>Set up your account in one quick step and jump straight into the dashboard.</p>
                </div>
                <div class="brand-card">
                    <strong>Consistent workflow</strong>
                    <p>Keep posting, browsing, and analytics connected inside one cleaner app experience.</p>
                </div>
            </div>
        </section>

        <section class="register-panel">
            <div class="register-card">
                <span class="eyebrow">New Account</span>
                <h2>Register for ClaimIt</h2>
                <p class="intro">Fill in your details to create an account and access the lost and found workspace.</p>

                <?php if ($success): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="registerForm" novalidate>
                    <div class="field" data-field>
                        <label for="name">Full name</label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            placeholder="Enter your full name"
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                            required
                        >
                    </div>

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
                            placeholder="Create a password"
                            required
                        >
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false">Show</button>
                        <div class="password-note">Use a password you can remember easily but keep private.</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" id="registerSubmit">
                            <span class="button-content">
                                <span class="button-text">Create Account</span>
                                <span class="button-loader" aria-hidden="true"></span>
                            </span>
                        </button>
                        <div class="login-link">
                            Already have an account? <a href="login.php">Login here</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        const registerForm = document.getElementById('registerForm');
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');
        const registerSubmit = document.getElementById('registerSubmit');
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
                passwordToggle.textContent = isPassword ? 'Hide' : 'Show';
                passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                passwordToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            });
        }

        registerForm.addEventListener('submit', (event) => {
            if (!registerForm.checkValidity()) {
                event.preventDefault();
                registerForm.reportValidity();
                return;
            }

            registerSubmit.classList.add('is-loading');
            registerSubmit.setAttribute('aria-busy', 'true');
        });
    </script>
</body>
</html>
