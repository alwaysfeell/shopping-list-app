<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LoginServiceTest extends TestCase
{
    private PDO $pdo;
    private int $now = 1700000000;
    private int $lockMinutes = 5;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                twofa_enabled INTEGER NOT NULL DEFAULT 0,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                lock_until TEXT NULL
            );
        ");

        $this->seedUser('user123', 'Abcdef1!', false, 0, null);
        $this->seedUser('twofa_user', 'Abcdef1!', true, 0, null);
    }

    private function seedUser(
        string $username,
        string $plainPassword,
        bool $twofaEnabled,
        int $failedAttempts,
        ?string $lockUntil
    ): void {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $st = $this->pdo->prepare("
            INSERT INTO users(username, password_hash, twofa_enabled, failed_attempts, lock_until)
            VALUES(?,?,?,?,?)
        ");
        $st->execute([$username, $hash, $twofaEnabled ? 1 : 0, $failedAttempts, $lockUntil]);
    }

    private function getUser(string $username): array
    {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE username=?");
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    #[Test]
    public function login_success_without_2fa_sets_session_and_resets_attempts(): void
    {
        $service = new LoginServiceFake($this->lockMinutes);

        $session = [];
        $result = $service->login($this->pdo, 'user123', 'Abcdef1!', $session, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame('app.php', $result['redirect_to']);
        $this->assertSame('success', $result['flash']['type']);
        $this->assertSame('Вхід успішний.', $result['flash']['msg']);

        $this->assertArrayHasKey('user', $session);
        $this->assertSame('user123', $session['user']['username']);

        $u = $this->getUser('user123');
        $this->assertSame(0, (int)$u['failed_attempts']);
        $this->assertNull($u['lock_until']);
    }

    #[Test]
    public function login_success_with_2fa_sets_pending_user_and_redirects_to_twofa(): void
    {
        $service = new LoginServiceFake($this->lockMinutes);

        $session = [];
        $result = $service->login($this->pdo, 'twofa_user', 'Abcdef1!', $session, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame('twofa.php', $result['redirect_to']);
        $this->assertSame('info', $result['flash']['type']);
        $this->assertSame('Введи 2FA код.', $result['flash']['msg']);

        $this->assertArrayHasKey('pending_2fa_user_id', $session);
        $this->assertIsInt($session['pending_2fa_user_id']);

        $u = $this->getUser('twofa_user');
        $this->assertSame(0, (int)$u['failed_attempts']);
        $this->assertNull($u['lock_until']);
    }

    #[Test]
    public function login_wrong_password_increments_attempts_and_returns_try_message(): void
    {
        $this->seedUser('try_user', 'Correct1!', false, 0, null);

        $service = new LoginServiceFake($this->lockMinutes);

        $session = [];
        $result = $service->login($this->pdo, 'try_user', 'WrongPass!', $session, $this->now);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Невірний username або пароль. Спроба: 1/3', $result['error']);

        $u = $this->getUser('try_user');
        $this->assertSame(1, (int)$u['failed_attempts']);
        $this->assertNull($u['lock_until']);
    }

    #[Test]
    public function login_third_fail_locks_account_and_blocks_even_correct_password_while_locked(): void
    {
        $this->seedUser('lock_user', 'Correct1!', false, 0, null);

        $service = new LoginServiceFake($this->lockMinutes);
        $session = [];

        $service->login($this->pdo, 'lock_user', 'bad1', $session, $this->now);
        $service->login($this->pdo, 'lock_user', 'bad2', $session, $this->now);
        $third = $service->login($this->pdo, 'lock_user', 'bad3', $session, $this->now);

        $this->assertFalse($third['ok']);
        $this->assertStringContainsString('3 помилки. Акаунт заблоковано до', $third['error']);

        $u = $this->getUser('lock_user');
        $this->assertSame(3, (int)$u['failed_attempts']);
        $this->assertNotNull($u['lock_until']);

        $correctWhileLocked = $service->login(
            $this->pdo,
            'lock_user',
            'Correct1!',
            $session,
            $this->now + 60
        );

        $this->assertFalse($correctWhileLocked['ok']);
        $this->assertStringContainsString('Акаунт тимчасово заблоковано до', $correctWhileLocked['error']);
    }

    #[Test]
    public function login_unknown_user_returns_generic_error(): void
    {
        $service = new LoginServiceFake($this->lockMinutes);

        $session = [];
        $result = $service->login($this->pdo, 'no_such_user', 'whatever', $session, $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame('Невірний username або пароль.', $result['error']);
    }
}

final class LoginServiceFake
{
    public function __construct(private int $lockMinutes) {}

    public function login(PDO $pdo, string $username, string $password, array &$session, int $now): array
    {
        $username = trim($username);

        $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
        $st->execute([$username]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'error' => "Невірний username або пароль."];
        }

        if (!empty($user['lock_until']) && strtotime((string)$user['lock_until']) > $now) {
            return ['ok' => false, 'error' => "Акаунт тимчасово заблоковано до " . $user['lock_until']];
        }

        if (password_verify($password, (string)$user['password_hash'])) {
            $pdo->prepare("UPDATE users SET failed_attempts=0, lock_until=NULL WHERE id=?")
                ->execute([(int)$user['id']]);

            if ((int)$user['twofa_enabled'] === 1) {
                $session['pending_2fa_user_id'] = (int)$user['id'];
                return [
                    'ok' => true,
                    'redirect_to' => 'twofa.php',
                    'flash' => ['type' => 'info', 'msg' => 'Введи 2FA код.']
                ];
            }

            $session['user'] = [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'twofa_enabled' => (int)$user['twofa_enabled'],
            ];

            return [
                'ok' => true,
                'redirect_to' => 'app.php',
                'flash' => ['type' => 'success', 'msg' => 'Вхід успішний.']
            ];
        }

        $failed = ((int)$user['failed_attempts']) + 1;

        $lockUntil = null;
        if ($failed >= 3) {
            $lockUntil = date('Y-m-d H:i:s', $now + $this->lockMinutes * 60);
            $failed = 3;
        }

        $pdo->prepare("UPDATE users SET failed_attempts=?, lock_until=? WHERE id=?")
            ->execute([$failed, $lockUntil, (int)$user['id']]);

        if ($lockUntil) {
            return ['ok' => false, 'error' => "3 помилки. Акаунт заблоковано до $lockUntil"];
        }

        return ['ok' => false, 'error' => "Невірний username або пароль. Спроба: $failed/3"];
    }
}