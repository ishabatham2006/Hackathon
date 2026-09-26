<?php require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email || strlen($password) < 8) {
        flash('Enter a valid email and password with at least 8 characters.', 'error');
    } else {
        try {
            $q = db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)');
            $q->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('Registration successful. Please log in.');
            redirect('login.php');
        } catch (PDOException $e) {
            flash('This email may already be registered.', 'error');
        }
    }
}
$pageTitle = 'Register';
require 'partials/header.php'; ?><div class="register-wrapper">
    <form method="post" class="register-box">
        <h2 class="register-title">Register</h2><input class="register-input" type="email" name="email" placeholder="Email" required><input class="register-input" type="password" name="password" placeholder="Password (minimum 8 characters)" minlength="8" required><button class="register-button" type="submit">Register</button>
        <p>Already registered? <a href="login.php">Login</a></p>
    </form>
</div><?php require 'partials/footer.php'; ?>