<?php
session_start();
include 'db.php';
require_once 'security.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_validate_or_fail();
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = in_array($_POST['role'] ?? 'customer', ['admin', 'seller', 'customer'], true) ? $_POST['role'] : 'customer';
    if ($name === '' || $email === '' || $password === '') {
        $error = "Name, email, and password are required.";
    } elseif (!validate_email_address($email)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $check = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($check) > 0) {
            $error = "Email already registered.";
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed, $role);
                mysqli_stmt_execute($stmt);

                $new_user_id = mysqli_insert_id($conn);
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
                $error = strpos($e->getMessage(), 'Duplicate entry') !== false
                    ? "Email already registered."
                    : "Something went wrong. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - BazaarHub</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 60px auto; }
        input, select { width: 100%; padding: 8px; margin: 8px 0 16px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #e44d26; color: white; border: none; cursor: pointer; }
        .error { color: red; } .success { color: green; }
    </style>
</head>
<body>
    <h2>Register - BazaarHub</h2>

    <?php if($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <form method="POST">
        <?= csrf_input() ?>
        <label>Full Name</label>
        <input type="text" name="name" maxlength="100" required>

        <label>Email</label>
        <input type="email" name="email" maxlength="100" required>

        <label>Password</label>
        <input type="password" name="password" maxlength="255" required>

        <label>Register As</label>
        <select name="role">
            <option value="customer">Customer</option>
            <option value="seller">Seller</option>
            <option value="admin">Admin</option>
        </select>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
</body>
</html>
