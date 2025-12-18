<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

$title = "Логін";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $st->execute([$username]);
    $user = $st->fetch();

    if (!$user) {
        $errors[] = "Невірний username або пароль.";
    } else {
        // lock check
        if (!empty($user['lock_until']) && strtotime($user['lock_until']) > time()) {
            $errors[] = "Акаунт тимчасово заблоковано до " . $user['lock_until'];
        } else {
            if (password_verify($password, $user['password_hash'])) {
                // reset failed attempts
                $pdo->prepare("UPDATE users SET failed_attempts=0, lock_until=NULL WHERE id=?")->execute([$user['id']]);

                if ((int)$user['twofa_enabled'] === 1) {
                    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                    $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
                    flash_set('info', 'Введи 2FA код.');
                    redirect('twofa.php');
                } else {
                    auth_login($user);
                    flash_set('success', 'Вхід успішний.');
                    redirect('app.php');
                }
            } else {
                // bump attempts
                $lockMinutes = (require __DIR__ . '/../app/config.php')['lock_minutes'];
                $failed = (int)$user['failed_attempts'] + 1;
                $lockUntil = null;
                if ($failed >= 3) {
                    $lockUntil = date('Y-m-d H:i:s', time() + $lockMinutes*60);
                    $failed = 3;
                }
                $pdo->prepare("UPDATE users SET failed_attempts=?, lock_until=? WHERE id=?")
                    ->execute([$failed, $lockUntil, $user['id']]);

                if ($lockUntil) {
                    $errors[] = "3 помилки. Акаунт заблоковано до $lockUntil";
                } else {
                    $errors[] = "Невірний username або пароль. Спроба: $failed/3";
                }
            }
        }
    }
}

include __DIR__ . '/_header.php';
?>
<h1>Логін</h1>

<?php if ($errors): ?>
  <div class="alert alert--danger">
    <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>Username
    <input name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
  </label>
  <label>Password
    <input type="password" name="password" required>
  </label>
  <button class="btn btn--primary" type="submit">Login</button>
</form>

<p class="muted">Нема акаунта? <a href="register.php">Зареєструйся</a></p>

<?php include __DIR__ . '/_footer.php'; ?>
