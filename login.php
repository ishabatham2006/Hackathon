<?php require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    $q = db()->prepare('SELECT * FROM users WHERE email=?');
    $q->execute([$email]);
    $u = $q->fetch();
    if ($u && password_verify($pw, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['email'] = $u['email'];
        redirect('inventory_dashboard.php');
    }
    flash('Login failed. Check your email and password.', 'error');
}
$pageTitle = 'Login';
require 'partials/header.php'; ?><div class="login-wrapper">
    <div class="login-box">
        <h2 class="login-title">Login</h2>
        <form method="post" class="form-wrapper">
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-input" required></div><button class="login-button" type="submit">Login</button>
            <p class="form-footer">Don't have an account?</p><a class="register-link" href="register.php">Register</a>
            <p class="form-footer"><a href="forgot_password.php">Forgot password?</a></p>
        </form>
    </div>
</div><?php require 'partials/footer.php'; ?>