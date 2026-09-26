<?php require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $q = db()->prepare('SELECT * FROM users WHERE email=?');
    $q->execute([$email]);
    $u = $q->fetch();
    if ($u) {
        $otp = create_otp((int)$u['id']);
        $_SESSION['reset_user_id'] = (int)$u['id'];
        // NOTE: This build has no SMTP/SMS gateway configured, so the OTP is shown here
        // for demo purposes. In production this would be emailed/SMS'd to the user instead.
        flash('OTP generated: ' . $otp . ' (valid 10 minutes). In production this is emailed, not shown on-screen.');
        redirect('reset_password.php');
    }
    flash('If that email is registered, an OTP has been generated.', 'error');
}
$pageTitle = 'Forgot Password';
require 'partials/header.php'; ?>
<div class="login-wrapper"><div class="login-box otp-box">
  <h2 class="login-title">Forgot Password</h2>
  <p class="small">Enter your account email and we'll generate a one-time password (OTP) to reset your password.</p>
  <form method="post" class="form-wrapper">
    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" required></div>
    <button class="login-button" type="submit">Send OTP</button>
    <p class="form-footer"><a href="login.php">Back to login</a></p>
  </form>
</div></div>
<?php require 'partials/footer.php'; ?>
