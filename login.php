<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if (!$email || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, fullname, email, password, role, is_active
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && (int) ($user['is_active'] ?? 1) !== 1) {
            $error = 'This account is inactive. Please contact the system administrator.';
        } elseif ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            if (!empty($_POST['remember'])) {
                setcookie('sedco_email', $user['email'], [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('sedco_email', '', time() - 3600, '/');
            }

            sts_audit('login_success', 'user', (int) $user['id'], [], (int) $user['id']);

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

$rememberedEmail = (string) ($_COOKIE['sedco_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#7b161c">
  <title>Sign In | SEDCO Smart Training System</title>
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-46">
  <link rel="stylesheet" href="login.css?v=20261013-01">
</head>
<body class="auth-page login-page">
  <main class="sts-login-shell">
    <div class="sts-login-card">

      <aside class="sts-login-brand" aria-label="About Smart Training System">
        <div class="sts-login-brand-orbit" aria-hidden="true"></div>
        <header class="sts-login-brand-header">
          <span class="sts-login-brand-logo">
            <img src="assets/sedco-logo.png" alt="SEDCO logo" width="38" height="38">
          </span>
          <span class="sts-login-brand-name">
            <strong>SEDCO</strong>
            <small>Smart Training System</small>
          </span>
        </header>

        <div class="sts-login-brand-main">
          <span class="sts-login-brand-kicker"><span class="sts-login-status-dot"></span> Your training management workspace</span>
          <h2>Smarter training.<br><span>Stronger teams.</span></h2>
          <p>Manage training requests, approvals and evaluations together in one streamlined experience.</p>

          <div class="sts-login-flow" aria-label="Training management workflow">
            <div class="sts-login-flow-title">Simple. Connected. Accountable.</div>
            <div class="sts-login-flow-steps">
              <div><span>01</span><strong>Apply</strong><small>Training requests</small></div>
              <div><span>02</span><strong>Approve</strong><small>Clear review flow</small></div>
              <div><span>03</span><strong>Evaluate</strong><small>Track outcomes</small></div>
            </div>
          </div>
        </div>

        <footer class="sts-login-brand-footer">
          <span class="sts-login-brand-footer-mark" aria-hidden="true"></span>
          A smarter way to manage learning at SEDCO.
        </footer>
      </aside>

      <div class="sts-login-panel">
        <div class="sts-login-mobile-brand">
          <img src="assets/sedco-logo.png" alt="SEDCO logo" width="36" height="36">
          <span><strong>SEDCO</strong><small>Smart Training System</small></span>
        </div>

        <div class="sts-login-form-wrap">
          <div class="sts-login-eyebrow"><span></span> WELCOME BACK</div>
          <h1>Sign in to your<br class="sts-login-desktop-break"> account</h1>
          <p class="sts-login-intro">Please enter your work account details to continue to your workspace.</p>

          <?php if ($error): ?>
            <div class="sts-login-alert sts-login-alert-error" id="sts-login-error" role="alert">
              <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7.5v5.5M12 16.5h.01" stroke-linecap="round"></path></svg>
              <span><?= e($error) ?></span>
            </div>
          <?php elseif (isset($_GET['registered'])): ?>
            <div class="sts-login-alert sts-login-alert-success" role="status">
              <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              <span>Registration successful. You can sign in now.</span>
            </div>
          <?php endif; ?>

          <form method="post" action="login.php" class="sts-login-form" aria-label="Sign in to SEDCO">
            <?= csrf_field() ?>
            <div class="sts-login-field">
              <label for="loginEmail">Email address</label>
              <div class="sts-login-input-wrap">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                <input id="loginEmail" type="email" name="email" placeholder="name@sedco.com.my"
                  value="<?= e($rememberedEmail) ?>" autocomplete="username" autocapitalize="none"
                  spellcheck="false" required>
              </div>
            </div>

            <div class="sts-login-field">
              <label for="password">Password</label>
              <div class="sts-login-input-wrap sts-login-password-wrap">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2.5"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"></path></svg>
                <input id="password" type="password" name="password" placeholder="Enter your password"
                  autocomplete="current-password" required>
                <button id="togglePassword" class="sts-login-visibility" type="button"
                  aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password">
                  <svg class="sts-login-eye-show" viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="sts-login-eye-hide" viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.6 6.1A12.4 12.4 0 0 1 12 6c6.4 0 10 6 10 6a16 16 0 0 1-3.2 3.6M6.2 7.6C3.4 9.3 2 12 2 12s3.6 6 10 6c1.7 0 3.2-.4 4.5-1.1"></path>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18"></path>
                  </svg>
                </button>
              </div>
            </div>

            <div class="sts-login-options">
              <label class="sts-login-remember" for="remember">
                <input type="checkbox" name="remember" id="remember" <?= $rememberedEmail !== '' ? 'checked' : '' ?>>
                <span>Remember my email</span>
              </label>
            </div>

            <button type="submit" class="sts-login-submit">
              <span>Sign in</span>
              <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"></path></svg>
            </button>
          </form>

          <div class="sts-login-register">
            New to Smart Training System? <a href="signup.php">Create an account <span aria-hidden="true">↗</span></a>
          </div>
        </div>

        <footer class="sts-login-panel-footer">
          <span>© <?= date('Y') ?> SEDCO Smart Training System</span>
          <span class="sts-login-secure"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2 4 5v7c0 5 3.3 8.1 8 10 4.7-1.9 8-5 8-10V5l-8-3Z"></path><path d="m9 12 2 2 4-4" stroke-linecap="round"></path></svg> Role based access</span>
        </footer>
      </div>
    </div>
  </main>

  <script>
    (() => {
      const input = document.getElementById('password');
      const toggle = document.getElementById('togglePassword');
      if (!input || !toggle) return;

      toggle.addEventListener('click', () => {
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        toggle.classList.toggle('is-visible', showing);
        toggle.setAttribute('aria-pressed', showing ? 'true' : 'false');
        toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
        toggle.title = showing ? 'Hide password' : 'Show password';
        input.focus({ preventScroll: true });
      });
    })();
  </script>
</body>
</html>
