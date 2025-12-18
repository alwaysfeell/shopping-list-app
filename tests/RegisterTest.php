<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RegisterTest extends TestCase
{
    private PDO $pdo;

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
                twofa_code TEXT NULL,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                lock_until TEXT NULL,
                created_at TEXT NOT NULL
            );
        ");

        $this->seedUser('user123', 'Abcdef1!');
    }

    private function seedUser(string $username, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $st = $this->pdo->prepare("
            INSERT INTO users (username, password_hash, twofa_enabled, twofa_code, failed_attempts, lock_until, created_at)
            VALUES (?, ?, 0, NULL, 0, NULL, CURRENT_TIMESTAMP)
        ");
        $st->execute([$username, $hash]);
    }

    private function userRow(string $username): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE username=?");
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    #[Test]
    public function register_valid_user_success_inserts_user_hashes_password_and_redirects(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, 'new_user', 'Abcdef1!', 'Abcdef1!');

        $this->assertTrue($result['ok']);
        $this->assertSame('login.php', $result['redirect_to']);
        $this->assertSame('success', $result['flash']['type']);
        $this->assertSame('Акаунт створено. Тепер можна увійти.', $result['flash']['msg']);

        $row = $this->userRow('new_user');
        $this->assertNotNull($row);

        $this->assertSame('new_user', $row['username']);
        $this->assertSame(0, (int)$row['twofa_enabled']);
        $this->assertSame(0, (int)$row['failed_attempts']);
        $this->assertNull($row['lock_until']);
        $this->assertNotEmpty($row['created_at']);

        $this->assertNotSame('Abcdef1!', $row['password_hash']);
        $this->assertTrue(password_verify('Abcdef1!', (string)$row['password_hash']));
    }

    #[Test]
    public function register_trims_username_before_check_and_insert(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, '   spaced_user   ', 'Abcdef1!', 'Abcdef1!');

        $this->assertTrue($result['ok']);
        $this->assertNotNull($this->userRow('spaced_user'));
        $this->assertNull($this->userRow('   spaced_user   '));
    }

    #[Test]
    public function register_rejects_duplicate_username(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, 'user123', 'Abcdef1!', 'Abcdef1!');

        $this->assertFalse($result['ok']);
        $this->assertContains('Такий username вже існує.', $result['errors']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE username='user123'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function register_rejects_invalid_username(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, 'ab', 'Abcdef1!', 'Abcdef1!');

        $this->assertFalse($result['ok']);
        $this->assertContains('Username має бути 3–20 символів.', $result['errors']);
        $this->assertNull($this->userRow('ab'));
    }

    #[Test]
    public function register_rejects_invalid_password(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, 'user777', 'short', 'short');

        $this->assertFalse($result['ok']);
        $this->assertContains('Пароль мінімум 8 символів.', $result['errors']);
        $this->assertNull($this->userRow('user777'));
    }

    #[Test]
    public function register_rejects_password_mismatch(): void
    {
        $service = new RegisterServiceFake();

        $result = $service->register($this->pdo, 'user888', 'Abcdef1!', 'DIFFERENT1!');

        $this->assertFalse($result['ok']);
        $this->assertContains('Паролі не співпадають.', $result['errors']);
        $this->assertNull($this->userRow('user888'));
    }
}

final class RegisterServiceFake
{
    public function register(PDO $pdo, string $username, string $password, string $confirm): array
    {
        $errors = [];

        if ($e = validate_username($username)) $errors[] = $e;
        if ($e = validate_password($password)) $errors[] = $e;
        if ($password !== $confirm) $errors[] = "Паролі не співпадають.";

        $usernameTrimmed = trim($username);

        if (!$errors) {
            $st = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $st->execute([$usernameTrimmed]);

            if ($st->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Такий username вже існує.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $st = $pdo->prepare("
                    INSERT INTO users (username, password_hash, twofa_enabled, twofa_code, failed_attempts, lock_until, created_at)
                    VALUES (?, ?, 0, NULL, 0, NULL, CURRENT_TIMESTAMP)
                ");
                $st->execute([$usernameTrimmed, $hash]);

                return [
                    'ok' => true,
                    'redirect_to' => 'login.php',
                    'flash' => ['type' => 'success', 'msg' => 'Акаунт створено. Тепер можна увійти.'],
                ];
            }
        }

        return ['ok' => false, 'errors' => $errors];
    }
}

function validate_username(string $username): ?string {
    $u = trim($username);
    if (strlen($u) < 3 || strlen($u) > 20) return "Username має бути 3–20 символів.";
    if (!preg_match('/^[A-Za-z0-9_]+$/', $u)) return "Username: тільки латиниця/цифри/_";
    return null;
}

function validate_password(string $password): ?string {
    if (strlen($password) < 8) return "Пароль мінімум 8 символів.";
    return null;
}