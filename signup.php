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
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($fullname === '' || !$email || strlen($password) < 6) {
        $error = 'Enter your name, a valid email, and a password with at least 6 characters.';
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'staff';

            $stmt = db()->prepare(
                'INSERT INTO users (fullname, email, phone_number, role, password)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssss',
                $fullname,
                $email,
                $phoneNumber,
                $role,
                $passwordHash
            );
            $stmt->execute();
            $stmt->close();

            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-14">
</head>
<body class="auth-page signup-page">
  <section class="vh-100 d-flex align-items-center justify-content-center">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
          <div class="card p-4 p-md-5">
            <p class="text-center h3 fw-bold mb-2">Create account</p>
            <p class="text-center text-muted mb-4">New accounts are created as Staff.</p>

            <?php if ($error): ?>
              <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="signup.php">
                    <?= csrf_field() ?>
              <div class="form-outline mb-3">
                <input
                  type="text"
                  name="fullname"
                  class="form-control bg-light"
                  placeholder="Full name"
                  autocomplete="name"
                  required
                  value="<?= e((string) ($_POST['fullname'] ?? '')) ?>"
                >
              </div>

              <div class="form-outline mb-3">
                <input
                  type="email"
                  name="email"
                  class="form-control bg-light"
                  placeholder="Email"
                  autocomplete="email"
                  required
                  value="<?= e((string) ($_POST['email'] ?? '')) ?>"
                >
              </div>

              <div class="form-outline mb-3">
                <input
                  type="tel"
                  name="phone_number"
                  class="form-control bg-light"
                  placeholder="Phone number"
                  autocomplete="tel"
                  value="<?= e((string) ($_POST['phone_number'] ?? '')) ?>"
                >
              </div>

              <div class="form-outline mb-4 password-container position-relative">
                <input
                  type="password"
                  id="password"
                  name="password"
                  class="form-control bg-light"
                  placeholder="Password (minimum 6 characters)"
                  autocomplete="new-password"
                  minlength="6"
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

              <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>

              <div class="text-center">
                <small>
                  Already have an account?
                  <a href="login.php" class="text-primary text-decoration-none fw-bold">Sign in</a>
                </small>
              </div>
            </form>
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
