<?php
session_start();
require_once 'db.php';
require_once 'security.php';

$page_title = "BazaarHub - Sign In";
$error = "";
$success = "";
$active_mode = "signin";

if (!empty($_SESSION['auth_flash_error'])) {
    $error = $_SESSION['auth_flash_error'];
    unset($_SESSION['auth_flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = $_POST['action'] ?? '';

    if ($action === 'signin') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = mysqli_prepare($conn, "SELECT id, name, password, role, account_status FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user && ($user['account_status'] ?? 'active') === 'suspended') {
            $error = "This account is suspended. Please contact the admin.";
        } elseif ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } elseif ($user['role'] === 'seller') {
                header("Location: seller/dashboard.php");
            } else {
                header("Location: customer/dashboard.php");
            }
            exit();
        } elseif ($user) {
            $passwordInfo = password_get_info($user['password']);

            if (($passwordInfo['algo'] ?? 0) === 0 && hash_equals($user['password'], $password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($updateStmt, "si", $newHash, $user['id']);
                mysqli_stmt_execute($updateStmt);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] === 'seller') {
                    header("Location: seller/dashboard.php");
                } else {
                    header("Location: customer/dashboard.php");
                }
                exit();
            }
        }

        $error = "Invalid email or password.";
    }

    if ($action === 'signup') {
        $active_mode = "signup";
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $requested_role = $_POST['role'] ?? 'customer';
        $role = in_array($requested_role, ['admin', 'seller', 'customer'], true) ? $requested_role : 'customer';
        $name = trim($first_name . ' ' . $last_name);

        if ($name === '' || $email === '' || $password === '') {
            $error = "Name, email, and password are required.";
        } elseif (!validate_email_address($email)) {
            $error = "Please enter a valid email address.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif ($phone !== '' && !validate_phone_number($phone)) {
            $error = "Please enter a valid phone number.";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                mysqli_begin_transaction($conn);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $role);
                mysqli_stmt_execute($stmt);
                $new_user_id = mysqli_insert_id($conn);

                if ($address !== '') {
                    $normalized_phone = normalize_phone_number($phone);
                    $stmt = mysqli_prepare($conn, "INSERT INTO user_addresses (user_id, phone, city, address, is_default) VALUES (?, ?, ?, ?, TRUE)");
                    mysqli_stmt_bind_param($stmt, "isss", $new_user_id, $normalized_phone, $city, $address);
                    mysqli_stmt_execute($stmt);
                }

                mysqli_commit($conn);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;

                if ($role === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($role === 'seller') {
                    header("Location: seller/dashboard.php");
                } else {
                    header("Location: customer/dashboard.php");
                }
                exit();
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = strpos($e->getMessage(), 'Duplicate entry') !== false
                    ? "This email is already registered."
                    : "Unable to create the account right now.";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
</head>

<body class="auth-page">
    <div class="bg-pattern" aria-hidden="true"></div>
    <div class="page-glow page-glow--top" aria-hidden="true"></div>
    <div class="page-glow page-glow--bottom" aria-hidden="true"></div>

    <main class="auth-main">
        <section class="auth-card" data-auth-shell data-mode="<?= htmlspecialchars($active_mode) ?>" aria-label="BazaarHub authentication">
            <aside class="brand-panel">
                <div class="brand-content">
                    <div class="brand-header">
                        <h1 class="brand-logo">bazaarhub</h1>
                        <p class="brand-tagline">Style. Choice. Connection.</p>
                        <div class="tagline-icon">
                            <div class="line"></div>
                            <span class="material-symbols-outlined">spa</span>
                            <div class="line"></div>
                        </div>
                    </div>

                    <div class="brand-description">
                        <div class="features">
                            <div class="feature">
                                <span class="material-symbols-outlined">apparel</span>
                                <p>Discover unique styles</p>
                            </div>
                            <div class="feature">
                                <span class="material-symbols-outlined">shopping_bag</span>
                                <p>Shop from multiple sellers</p>
                            </div>
                            <div class="feature">
                                <span class="material-symbols-outlined">shield_lock</span>
                                <p>Safe & secure shopping</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="auth-card-shell">
                <div class="auth-card-inner">
                    <div class="auth-tabs" role="tablist" aria-label="Authentication tabs">
                        <button class="auth-tab is-active" id="signin-tab" type="button" role="tab" aria-selected="true" data-tab-target="signin">Sign In</button>
                        <button class="auth-tab" id="signup-tab" type="button" role="tab" aria-selected="false" data-tab-target="signup">Sign Up</button>
                    </div>

                    <div class="auth-panel is-active" data-panel="signin" role="tabpanel" aria-labelledby="signin-tab">
                        <?php if ($error): ?><p class="auth-message auth-message--error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
                        <?php if ($success): ?><p class="auth-message auth-message--success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
                        <form class="auth-form" method="POST">
                            <input type="hidden" name="action" value="signin">
                            <?= csrf_input() ?>
                            <div class="field">
                                <div class="field__control">
                                    <span class="material-symbols-outlined field__icon">mail</span>
                                    <input id="signin-email" name="email" type="email" placeholder="Email address" required>
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control field__control--password">
                                    <span class="material-symbols-outlined field__icon">lock</span>
                                    <input id="signin-password" name="password" type="password" placeholder="Password" required>
                                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">
                                        <svg class="eye-open" viewBox="0 0 24 24">
                                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                                        </svg>
                                        <svg class="eye-closed" viewBox="0 0 24 24">
                                            <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="form-row">
                                <label class="checkbox">
                                    <input type="checkbox" name="remember_me">
                                    <span class="checkbox-mark"></span>
                                    <span>Remember me</span>
                                </label>
                                <a class="text-link" href="forgot-password.php">Forgot password?</a>
                            </div>

                            <button class="submit-button" type="submit" data-loading-text="Signing In">Sign In</button>
                        </form>
                    </div>

                    <div class="auth-panel" data-panel="signup" role="tabpanel" aria-labelledby="signup-tab" hidden>
                        <form class="auth-form" method="POST">
                            <input type="hidden" name="action" value="signup">
                            <?= csrf_input() ?>
                            <div class="field-row">
                                <div class="field">
                                    <div class="field__control">
                                        <span class="material-symbols-outlined field__icon">person</span>
                                        <input id="signup-firstname" name="first_name" type="text" maxlength="50" placeholder="First name" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="field__control">
                                        <span class="material-symbols-outlined field__icon">person</span>
                                        <input id="signup-lastname" name="last_name" type="text" maxlength="50" placeholder="Last name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control">
                                    <span class="material-symbols-outlined field__icon">mail</span>
                                    <input id="signup-email" name="email" type="email" maxlength="100" placeholder="Email address" required>
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control field__control--password">
                                    <span class="material-symbols-outlined field__icon">lock</span>
                                    <input id="signup-password" name="password" type="password" maxlength="255" placeholder="Create password" required>
                                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">
                                        <svg class="eye-open" viewBox="0 0 24 24">
                                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                                        </svg>
                                        <svg class="eye-closed" viewBox="0 0 24 24">
                                            <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control field__control--password">
                                    <span class="material-symbols-outlined field__icon">lock</span>
                                    <input id="signup-confirm-password" name="confirm_password" type="password" maxlength="255" placeholder="Confirm password" required>
                                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">
                                        <svg class="eye-open" viewBox="0 0 24 24">
                                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                                        </svg>
                                        <svg class="eye-closed" viewBox="0 0 24 24">
                                            <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control">
                                    <span class="material-symbols-outlined field__icon">phone_enabled</span>
                                    <input id="signup-phone" name="phone" type="tel" inputmode="tel" maxlength="15" pattern="[0-9+\-\s()]{10,20}" placeholder="Phone number">
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control">
                                    <span class="material-symbols-outlined field__icon">location_on</span>
                                    <input id="signup-city" name="city" type="text" maxlength="100" placeholder="City">
                                </div>
                            </div>

                            <div class="field">
                                <div class="field__control">
                                    <span class="material-symbols-outlined field__icon">add_home</span>
                                    <input id="signup-address" name="address" type="text" maxlength="255" placeholder="Address">
                                </div>
                            </div>

                            <div class="role-selection">
                                <p class="role-label">I want to:</p>
                                <div class="role-options">
                                    <label class="role-option">
                                        <input type="radio" name="role" value="customer" checked>
                                        <span class="role-card">
                                            <span class="material-symbols-outlined role-icon">shopping_bag</span>
                                            <span class="role-text">Register as Buyer</span>
                                            <span class="role-desc">Shop from multiple trusted sellers</span>
                                        </span>
                                    </label>
                                    <label class="role-option">
                                        <input type="radio" name="role" value="seller">
                                        <span class="role-card">
                                            <span class="material-symbols-outlined role-icon">storefront</span>
                                            <span class="role-text">Register as Seller</span>
                                            <span class="role-desc">Sell products to thousands of buyers</span>
                                        </span>
                                    </label>
                                    <label class="role-option">
                                        <input type="radio" name="role" value="admin">
                                        <span class="role-card">
                                            <span class="material-symbols-outlined role-icon">admin_panel_settings</span>
                                            <span class="role-text">Register as Admin</span>
                                            <span class="role-desc">Manage users, products, orders, and reports</span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <button class="submit-button" type="submit" data-loading-text="Creating Account">Sign Up</button>
                        </form>
                    </div>
                </div>
            </section>
        </section>
    </main>

    <div class="toast-host" aria-live="polite" aria-atomic="true"></div>
    <script src="assets/js/auth.js"></script>
</body>

</html>
