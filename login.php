<?php
require_once 'includes/db.php';

const MAX_FAILED_ATTEMPTS = 3;
const LOCKOUT_MINUTES = 3;

$errors = [];
$lockoutSecondsLeft = null;
$old = ['email' => ''];
$justRegistered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $errors[] = 'Your session expired before this form was submitted. Please try again.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old['email'] = $email;

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both your email and password.';
    }

    if (empty($errors) && !$pdo) {
        $errors[] = 'The database is not connected right now, so no one can log in. Check includes/db.php.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'SELECT user_id, first_name, last_name, email, password_hash, role,
                        failed_attempts, lockout_until, status
                 FROM USERS WHERE email = ?'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = 'Invalid email or password.';
            } else {

                $isLocked = $user['status'] === 'Locked';
                if ($isLocked && $user['lockout_until'] !== null) {
                    $lockoutUntil = strtotime($user['lockout_until']);
                    if ($lockoutUntil <= time()) {
                        $reset = $pdo->prepare(
                            'UPDATE USERS SET failed_attempts = 0, lockout_until = NULL, status = ?
                             WHERE user_id = ?'
                        );
                        $reset->execute(['Active', $user['user_id']]);
                        $isLocked = false;
                        $user['failed_attempts'] = 0;
                    } else {
                        $lockoutSecondsLeft = $lockoutUntil - time();
                    }
                }

                if ($isLocked) {
                    $mins = ceil($lockoutSecondsLeft / 60);
                    $errors[] = "This account is locked after too many failed attempts. Try again in about {$mins} minute" . ($mins == 1 ? '' : 's') . '.';

                } elseif (password_verify($password, $user['password_hash'])) {
                    $pdo->prepare(
                        'UPDATE USERS SET failed_attempts = 0, lockout_until = NULL, status = ?
                         WHERE user_id = ?'
                    )->execute(['Active', $user['user_id']]);

                    $logStmt = $pdo->prepare(
                        'INSERT INTO SESSION_LOG (user_id, login_time, ip_address, device_info)
                         VALUES (?, NOW(), ?, ?)'
                    );
                    $logStmt->execute([
                        $user['user_id'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['user_id']        = $user['user_id'];
                    $_SESSION['first_name']     = $user['first_name'];
                    $_SESSION['role']           = $user['role'];
                    $_SESSION['session_log_id'] = $pdo->lastInsertId();
                    unset($_SESSION['csrf_token']);

                    header('Location: index.php');
                    exit;

                } else {
                    $attempts = (int) $user['failed_attempts'] + 1;

                    if ($attempts >= MAX_FAILED_ATTEMPTS) {
                        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                        $pdo->prepare(
                            'UPDATE USERS SET failed_attempts = ?, status = ?, lockout_until = ?
                             WHERE user_id = ?'
                        )->execute([$attempts, 'Locked', $lockUntil, $user['user_id']]);

                        $errors[] = 'Too many failed attempts. This account is now locked for ' . LOCKOUT_MINUTES . ' minutes.';
                    } else {
                        $pdo->prepare(
                            'UPDATE USERS SET failed_attempts = ? WHERE user_id = ?'
                        )->execute([$attempts, $user['user_id']]);

                        $remaining = MAX_FAILED_ATTEMPTS - $attempts;
                        $errors[] = "Invalid email or password. {$remaining} attempt" . ($remaining == 1 ? '' : 's') . ' remaining before this account is locked.';
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Something went wrong while logging in. Please try again.';
        }
    }
}

$authPageTitle = 'Log in';
require_once 'includes/auth-header.php';
?>

<div class="auth-page">
  <div class="auth-brand-panel">
    <a href="index.php" class="auth-logo">
      <span class="brand-mark">FF</span> FoodFusion
    </a>
    <div class="auth-brand-content">
      <h2>Where culinary creativity meets community.</h2>
      <p>Log back in to pick up where you left off — your saved recipes, your posts, your table.</p>
    </div>
    <div class="auth-brand-footer">&copy; <?php echo date('Y'); ?> FoodFusion</div>
  </div>

  <div class="auth-form-panel">
    <div class="auth-form-card">
      <a href="index.php" class="auth-mobile-logo"><span class="brand-mark">FF</span> FoodFusion</a>

      <h1>Welcome back</h1>
      <p class="lede">Log in to continue cooking.</p>

      <?php if ($justRegistered && empty($errors)): ?>
        <div style="background: rgba(63,125,104,0.08); border: 1px solid rgba(63,125,104,0.3); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
          <p style="color: var(--mint); font-size: 0.88rem; margin: 0;">Account created — log in below to get started.</p>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div style="background: rgba(177,74,42,0.08); border: 1px solid rgba(177,74,42,0.3); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
          <?php foreach ($errors as $error): ?>
            <p style="color: var(--paprika); font-size: 0.88rem; margin: 0 0 4px;">• <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form action="login.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email" autofocus value="<?php echo htmlspecialchars($old['email']); ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Log in</button>
      </form>

      <p class="modal-switch">New to FoodFusion? <a href="register.php">Create an account</a></p>
      <a href="index.php" class="auth-back-link">&larr; Back to home</a>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>