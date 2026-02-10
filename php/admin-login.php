<?php
/**
 * Heaven Nails - Admin Login
 */

require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            session_regenerate_id(true);
            error_log("Login successful for user: $username. Redirecting to dashboard.");
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
            error_log("Login failed for user: $username. User found: " . ($user ? 'Yes' : 'No'));
        }
    } catch (PDOException $e) {
        error_log("Login DB error: " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Heaven Nails</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo/logo.png.jpg?v=2">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        /* ... existing styles ... */
        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo-img {
            max-width: 150px;
            max-height: 80px;
            mix-blend-mode: multiply; /* Removes white bg */
        }
        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--color-primary);
            font-family: 'Cormorant Garamond', serif;
        }
        .form-group { margin-bottom: 1.5rem; }
        .back-link:hover { color: #c9a66b; }
        /* Add button styles explicitly just in case CSS fails */
        .btn-login {
            width: 100%;
            padding: 0.8rem;
            background-color: #c9a66b;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-login:hover {
            background-color: #b8956a;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="../assets/images/logo/logo.png.jpg" alt="Heaven Nails" class="logo-img">
            <h1 class="login-title" style="margin-top: 0.5rem; margin-bottom: 0;">The Heaven <span>Nails</span></h1>
        </div>
        <p class="login-subtitle">Admin Dashboard Access</p>
        
        <?php if ($error): ?>
            <div class="error-message" style="color: red; text-align: center; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
        
        <a href="../" class="back-link" style="display: block; text-align: center; margin-top: 1rem; text-decoration: none; color: #666;">← Back to Website</a>
    </div>
</body>
</html>
