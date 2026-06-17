<?php
session_start();
require_once 'db.php';
require_once 'security.php';
require_once 'app_helpers.php';

$page_title = "Forgot Password | BazaarHub";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $message = "Please enter the email address for your account.";
    } else {
        if (!validate_email_address($email)) {
            $message = "Please enter a valid email address.";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);
                $resetLink = app_url('reset-password.php?email=' . urlencode($email) . '&token=' . urlencode($token));

                mysqli_begin_transaction($conn);
                try {
                    $stmt = mysqli_prepare($conn, "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iss", $user['id'], $tokenHash, $expiresAt);
                    mysqli_stmt_execute($stmt);

                    $subject = 'BazaarHub password reset';
                    $htmlBody = '
                        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                            <h2 style="margin: 0 0 16px;">BazaarHub password reset</h2>
                            <p>We received a request to reset your BazaarHub password.</p>
                            <p><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">Reset your password</a></p>
                            <p>This link expires in 60 minutes.</p>
                            <p>If you did not request this, you can ignore this email.</p>
                        </div>
                    ';
                    $textBody = "BazaarHub password reset\n\nOpen this link to reset your password:\n{$resetLink}\n\nThis link expires in 60 minutes.\nIf you did not request this, you can ignore this email.";

                    if (!send_app_email($email, $subject, $htmlBody, $textBody)) {
                        throw new RuntimeException('Unable to send the reset email.');
                    }

                    mysqli_commit($conn);
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    $message = "Unable to send the reset email right now.";
                }
            }

            if ($message === "") {
                $message = "If that email exists in BazaarHub, we sent a password reset email.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page auth-page--utility">
    <div class="page-glow page-glow--top" aria-hidden="true"></div>
    <div class="page-glow page-glow--bottom" aria-hidden="true"></div>

    <header class="site-header">
        <div class="site-header__inner">
            <a class="wordmark" href="login.php" aria-label="BazaarHub home">
                <span>Bazaar</span><span class="wordmark__accent">Hub</span>
            </a>
            <nav class="site-nav" aria-label="Primary">
                <a href="login.php">Sign in</a>
                <a href="register.php">Register</a>
                <a href="customer/products.php">Shop</a>
            </nav>
        </div>
    </header>

    <main class="utility-main">
        <section class="utility-card">
            <p class="eyebrow">Password Recovery</p>
            <h1>Forgot your password?</h1>
            <p class="muted-copy">Enter the email tied to your BazaarHub account and we will create a reset link.</p>

            <?php if ($message): ?>
                <div class="notice"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="POST">
                <?= csrf_input() ?>
                <div class="field">
                    <label for="recovery-email">Email address</label>
                    <div class="field__control">
                        <span class="field__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 6h16a2 2 0 0 1 2 2v.4l-10 5.6L2 8.4V8a2 2 0 0 1 2-2zm18 4.7V16a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5.3l9.5 5.3a1 1 0 0 0 1 0L22 10.7z"/></svg>
                        </span>
                        <input id="recovery-email" name="email" type="email" maxlength="100" placeholder="you@example.com" required>
                    </div>
                    <p class="field__message">We will prepare a reset link for the account tied to this address.</p>
                </div>

                <button class="submit-button" type="submit" data-loading-text="Creating Link">Send Reset Link</button>
            </form>

            <div class="utility-actions">
                <a class="text-link" href="login.php">Back to sign in</a>
                <a class="text-link" href="reset-password.php">Open reset page</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <p>&copy; BazaarHub</p>
            <p>Marrakech | Istanbul | Jaipur | Oaxaca</p>
        </div>
    </footer>

    <div class="toast-host" aria-live="polite" aria-atomic="true"></div>
    <script src="assets/js/auth.js"></script>
</body>
</html>
