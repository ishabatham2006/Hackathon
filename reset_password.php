<?php require_once 'functions.php';
if (empty($_SESSION['reset_user_id'])) { flash('Start by requesting an OTP.', 'error'); redirect('forgot_password.php'); }
$uid = (int)$_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if (strlen($pw) < 8 || $pw !== $pw2) {
        flash('Passwords must match and be at least 8 characters.', 'error');
    } elseif (!verify_otp($uid, $otp)) {
        flash('Invalid or expired OTP.', 'error');
    } else {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
        unset($_SESSION['reset_user_id']);
        flash('Password reset successful. Please log in.');
        redirect('login.php');
    }
}
$pageTitle = 'Reset Password';
require 'partials/header.php'; ?>
<div class="login-wrapper"><div class="login-box otp-box">
  <h2 class="login-title">Reset Password</h2>
  <form method="post" class="form-wrapper">
    <div class="form-group"><label class="form-label">OTP</label><input type="text" name="otp" class="form-input" maxlength="6" required></div>
    <div class="form-group"><label class="form-label">New Password</label><input type="password" name="password" class="form-input" minlength="8" required></div>
    <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" name="password2" class="form-input" minlength="8" required></div>
    <button class="login-button" type="submit">Reset Password</button>
    <p class="form-footer"><a href="forgot_password.php">Resend OTP</a></p>
  </form>
</div></div>
<?php require 'partials/footer.php'; ?>
