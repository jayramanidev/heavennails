<?php
/**
 * Heaven Nails - Admin Login
 */

require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // For demo purposes, using hardcoded credentials
    // In production, verify against database with password_verify()
    $validUsername = 'admin';
    $validPasswordHash = password_hash('heaven2026', PASSWORD_DEFAULT);
    
    if ($username === $validUsername && password_verify($password, $validPasswordHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin-dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: linear-gradient(135deg, #f5f0ea 0%, #e5dcd0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .login-container { background: white; padding: 3rem 2rem; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-logo { text-align: center; margin-bottom: 2rem; }
        .login-logo h1 { font-family: 'Cormorant Garamond', serif; font-size: 2rem; color: #2d2d2d; }
        .login-logo span { color: #c9a66b; }
        .login-subtitle { text-align: center; color: #6b6b6b; font-size: 0.875rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #2d2d2d; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.875rem 1rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #c9a66b; }
        .btn-login { width: 100%; padding: 1rem; background: #c9a66b; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-login:hover { background: #b8956a; }
        .error-message { background: #fef2f2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.5rem; text-align: center; }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #6b6b6b; text-decoration: none; font-size: 0.875rem; }
        .back-link:hover { color: #c9a66b; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h1>Heaven<span>Nails</span></h1>
        </div>
        <p class="login-subtitle">Admin Dashboard Access</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
        
        <a href="../index.html" class="back-link">← Back to Website</a>
    </div>
</body>
</html>
