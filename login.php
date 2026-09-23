<?php
require_once 'includes/db.php';

const MAX_FAILED_ATTEMPTS = 3;
const LOCKOUT_MINUTES = 3;

// Used only to burn roughly the same bcrypt cost on a nonexistent-email
// attempt as a real password check costs — see the !$user branch below.
// Never a real account's hash.
const DUMMY_PASSWORD_HASH = '$2y$12$OvJAk.ZCiJ6wP5TgOCsRuuFq3eYZl41NKi4C64E19U5NUad/DbIMm';

// There is only one login FORM in the whole site — the Login modal in
// includes/header.php. This file just processes the submission and sends
// the person back with either a session (success) or an error (failure).
// See includes/header.php ($authOpenModal / $authError / $authOld) and
// includes/footer.php (the script that reopens the modal) for the other half.

function fail_login($message, $email = '') {
    $_SESSION['auth_error'] = $message;
    $_SESSION['auth_old'] = ['email' => $email];
    header('Location: index.php?auth=login');
    exit;
}

// A plain visit (no form submitted) — just open the modal, nothing to process.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?auth=login');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    fail_login('Your session expired before this form was submitted. Please try again.');
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    fail_login('Please enter both your email and password.', $email);
}

if (!$pdo) {
    fail_login('The database is not connected right now, so no one can log in. Check includes/db.php.', $email);
}

try {
    $stmt = $pdo->prepare(
        'SELECT user_id, first_name, last_name, email, password_hash, role,
                failed_attempts, lockout_until, status
         FROM USERS WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Run a password_verify() anyway, against a hash nothing matches, so
        // this path takes about as long as a real wrong-password check.
        // Without it, "email not registered" responds near-instantly while
        // "wrong password" takes bcrypt's ~100ms, letting someone measuring
        // response time enumerate which emails have accounts.
        password_verify($password, DUMMY_PASSWORD_HASH);
        fail_login('Invalid email or password.', $email);
    }

    $isLocked = $user['status'] === 'Locked';
    $lockoutSecondsLeft = null;

    if ($isLocked && $user['lockout_until'] !== null) {
        $lockoutUntil = strtotime($user['lockout_until']);
        if ($lockoutUntil <= time()) {
            $pdo->prepare(
                'UPDATE USERS SET failed_attempts = 0, lockout_until = NULL, status = ? WHERE user_id = ?'
            )->execute(['Active', $user['user_id']]);
            $isLocked = false;
        } else {
            $lockoutSecondsLeft = $lockoutUntil - time();
        }
    }

    if ($isLocked) {
        $mins = ceil($lockoutSecondsLeft / 60);
        fail_login(
            "This account is locked after too many failed attempts. Try again in about {$mins} minute" . ($mins == 1 ? '' : 's') . '.',
            $email
        );
    }

    if (password_verify($password, $user['password_hash'])) {
        $pdo->prepare(
            'UPDATE USERS SET failed_attempts = 0, lockout_until = NULL, status = ? WHERE user_id = ?'
        )->execute(['Active', $user['user_id']]);

        $logStmt = $pdo->prepare(
            'INSERT INTO SESSION_LOG (user_id, login_time, ip_address, device_info) VALUES (?, NOW(), ?, ?)'
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
    }

    $attempts = (int) $user['failed_attempts'] + 1;

    if ($attempts >= MAX_FAILED_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $pdo->prepare(
            'UPDATE USERS SET failed_attempts = ?, status = ?, lockout_until = ? WHERE user_id = ?'
        )->execute([$attempts, 'Locked', $lockUntil, $user['user_id']]);

        fail_login('Too many failed attempts. This account is now locked for ' . LOCKOUT_MINUTES . ' minutes.', $email);
    }

    $pdo->prepare('UPDATE USERS SET failed_attempts = ? WHERE user_id = ?')->execute([$attempts, $user['user_id']]);
    $remaining = MAX_FAILED_ATTEMPTS - $attempts;
    fail_login(
        "Invalid email or password. {$remaining} attempt" . ($remaining == 1 ? '' : 's') . ' remaining before this account is locked.',
        $email
    );

} catch (PDOException $e) {
    error_log('FoodFusion login: ' . $e->getMessage());
    fail_login('Something went wrong while logging in. Please try again.', $email);
}