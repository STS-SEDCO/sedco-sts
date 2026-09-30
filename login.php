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
            'SELECT id, fullname, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Training Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-14">
</head>
<body class="auth-page login-page">
  <section class="vh-100 d-flex align-items-center">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <div class="card text-black">
            <div class="card-body p-md-5">
              <div class="row align-items-center justify-content-center">
                <div class="col-md-10 col-lg-6">
                  <p class="text-center h1 fw-bold mb-4">TRAINING MANAGEMENT SYSTEM</p>
                  <p class="text-center text-muted mb-4">Sign in to continue to your workspace.</p>

                  <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                  <?php elseif (isset($_GET['registered'])): ?>
                    <div class="alert alert-success" role="alert">
                      Registration successful. You can sign in now.
                    </div>
                  <?php endif; ?>

                  <form method="post" action="login.php">
                    <?= csrf_field() ?>
                    <div class="form-outline mb-3">
                      <input
                        type="email"
                        name="email"
                        class="form-control bg-light"
                        placeholder="Email address"
                        autocomplete="email"
                        required
                        value="<?= e($rememberedEmail) ?>"
                      >
                    </div>

                    <div class="form-outline mb-3 password-container position-relative">
                      <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control bg-light"
                        placeholder="Password"
                        autocomplete="current-password"
                        required
                      >
                      <button
                        class="btn border-0 position-absolute top-50 end-0 translate-middle-y me-1"
                        type="button"
                        id="togglePassword"
                        aria-label="Show or hide password"
                      >
                        <i class="fa fa-eye"></i>
                      </button>
                    </div>

                    <div class="form-check mb-4">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember"
                        <?= $rememberedEmail !== '' ? 'checked' : '' ?>
                      >
                      <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>

                    <div class="text-center">
                      <small>
                        New to Training Management System?
                        <a href="signup.php" class="text-primary text-decoration-none fw-bold">Sign up</a>
                      </small>
                    </div>
                  </form>
                </div>

                <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center">
                  <img
                    src="https://tmr.scione.com/login-assetts/images/scione-login-.svg"
                    class="img-fluid"
                    alt=""
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script>
    document.getElementById('togglePassword').addEventListener('click', () => {
      const input = document.getElementById('password');
      const icon = document.querySelector('#togglePassword i');
      const show = input.type === 'password';

      input.type = show ? 'text' : 'password';
      icon.classList.toggle('fa-eye', !show);
      icon.classList.toggle('fa-eye-slash', show);
    });
  </script>
</body>
</html>
