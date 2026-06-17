<?php
session_start();
require_once 'db.php';
require_once 'security.php';

$page_title = "Reset Password | BazaarHub";
$message = "";
$linkValid = false;

$email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

function find_valid_reset_token(mysqli $conn, string $token): ?array
{
    $stmt = mysqli_prepare($conn, "
        SELECT id, user_id, token_hash, expires_at
        FROM password_reset_tokens
        WHERE used_at IS NULL
        ORDER BY created_at DESC
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        if (strtotime($row['expires_at']) !== false && strtotime($row['expires_at']) > time() && password_verify($token, $row['token_hash'])) {
            return $row;
        }
    }

    return null;
}

$user = null;
$resetRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $token !== '') {
    $resetRow = find_valid_reset_token($conn, $token);

    if ($resetRow) {
        if ($email !== '') {
            $stmt = mysqli_prepare($conn, "SELECT id, email FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        }

        if (!$user || (int) $user['id'] !== (int) $resetRow['user_id']) {
            $stmt = mysqli_prepare($conn, "SELECT id, email FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $resetRow['user_id']);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        }

        if ($user) {
            $email = $user['email'];
            $linkValid = true;
        } else {
            $message = "That reset link is invalid or expired.";
        }
    } else {
        $message = "That reset link is invalid or expired.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $token === '') {
        $message = "Your reset link is incomplete. Please request a new one.";
    } elseif ($newPassword === '') {
        $message = "Please choose a new password.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
    } elseif ($passwordErrors = validate_password_rules($newPassword)) {
        $message = $passwordErrors[0];
    } else {
        $resetRow = find_valid_reset_token($conn, $token);

        if (!$resetRow) {
            $message = "That reset link is invalid or expired.";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id, email FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $resetRow['user_id']);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$user) {
                $message = "That reset link is invalid or expired.";
            } else {
                $linkValid = true;
                mysqli_begin_transaction($conn);
                try {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $hash, $user['id']);
                    mysqli_stmt_execute($stmt);

                    $stmt = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $resetRow['id']);
                    mysqli_stmt_execute($stmt);

                    $stmt = mysqli_prepare($conn, "DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?");
                    mysqli_stmt_bind_param($stmt, "ii", $user['id'], $resetRow['id']);
                    mysqli_stmt_execute($stmt);

                    mysqli_commit($conn);
                    $message = "Password updated. You can sign in with the new password now.";
                    $email = '';
                    $token = '';
                    $linkValid = false;
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    $message = "Unable to reset the password right now.";
                }
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
                <a href="forgot-password.php">Forgot password</a>
                <a href="customer/products.php">Shop</a>
            </nav>
        </div>
    </header>

    <main class="utility-main">
        <section class="utility-card utility-card--wide">
            <div class="utility-grid">
                <div>
                    <p class="eyebrow">Reset Password</p>
                    <h1>Choose a fresh password</h1>
                    <p class="muted-copy">Use something strong, memorable, and unique to your BazaarHub account.</p>

                    <?php if ($message): ?>
                        <div class="notice"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <?php if ($linkValid): ?>
                        <form class="auth-form" method="POST">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                            <div class="field">
                                <label for="reset-password">New password</label>
                                <div class="field__control field__control--password">
                                    <span class="field__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M17 9h-1V7a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2zm-6 6.7V17a1 1 0 1 0 2 0v-1.3a2 2 0 1 0-2 0zM10 9V7a2 2 0 1 1 4 0v2h-4z"/></svg>
                                    </span>
                                    <input id="reset-password" name="password" type="password" minlength="8" maxlength="255" placeholder="Create a new password" required>
                                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
                                </div>
                            </div>

                            <div class="field">
                                <label for="confirm-reset-password">Confirm password</label>
                                <div class="field__control field__control--password">
                                    <span class="field__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M17 9h-1V7a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2zm-6 6.7V17a1 1 0 1 0 2 0v-1.3a2 2 0 1 0-2 0zM10 9V7a2 2 0 1 1 4 0v2h-4z"/></svg>
                                    </span>
                                    <input id="confirm-reset-password" name="confirm_password" type="password" minlength="8" maxlength="255" placeholder="Confirm your new password" required>
                                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
                                </div>
                            </div>

                            <button class="submit-button" type="submit" data-loading-text="Resetting Password">Reset Password</button>
                        </form>
                    <?php else: ?>
                        <p class="muted-copy">Use the reset link from the password recovery page to continue.</p>
                    <?php endif; ?>
                </div>

                <aside class="strength-card" aria-label="Password strength guidance">
                    <p class="eyebrow">Strength Checklist</p>
                    <h2>Make it strong</h2>
                    <ul class="strength-list">
                        <li data-strength-check="length">At least 8 characters</li>
                        <li data-strength-check="uppercase">One uppercase letter</li>
                        <li data-strength-check="number">One number</li>
                        <li data-strength-check="symbol">One special character</li>
                    </ul>
                </aside>
            </div>

            <div class="utility-actions">
                <a class="text-link" href="login.php">Back to sign in</a>
                <a class="text-link" href="forgot-password.php">Need another reset link?</a>
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
