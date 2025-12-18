<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CreateItemTest extends TestCase
{
    private PDO $pdo;
    private int $userId = 1;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL
            );
        ");

        $this->pdo->exec("
            CREATE TABLE items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                is_purchased INTEGER NOT NULL,
                created_at TEXT NOT NULL
            );
        ");

        $stmt = $this->pdo->prepare("INSERT INTO categories(name) VALUES (?)");
        foreach (['Продукти', 'Одяг', 'Інше'] as $cat) {
            $stmt->execute([$cat]);
        }
    }

    private function categoryId(string $name): int
    {
        $st = $this->pdo->prepare("SELECT id FROM categories WHERE name=?");
        $st->execute([$name]);
        $id = $st->fetchColumn();
        $this->assertNotFalse($id, "Category '$name' must exist in seed");
        return (int)$id;
    }

    #[Test]
    public function create_valid_item_success(): void
    {
        $service = new ItemServiceFake();

        $categoryId = $this->categoryId('Продукти');

        $result = $service->createItem($this->pdo, $this->userId, 'Молоко 2л', '45.50', $categoryId);

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('errors', $result);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(1, $count);

        $row = $this->pdo->query("SELECT * FROM items")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($this->userId, (int)$row['user_id']);
        $this->assertSame($categoryId, (int)$row['category_id']);
        $this->assertSame('Молоко 2л', $row['name']);
        $this->assertSame(45.50, (float)$row['price']);
        $this->assertSame(0, (int)$row['is_purchased']);
        $this->assertNotEmpty($row['created_at']);
    }

    #[Test]
    public function create_rejects_empty_name(): void
    {
        $service = new ItemServiceFake();
        $categoryId = $this->categoryId('Інше');

        $result = $service->createItem($this->pdo, $this->userId, '   ', '10', $categoryId);

        $this->assertFalse($result['ok']);
        $this->assertContains("Назва обов'язкова.", $result['errors']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(0, $count);
    }

    #[Test]
    public function create_rejects_invalid_price_format(): void
    {
        $service = new ItemServiceFake();
        $categoryId = $this->categoryId('Продукти');

        $result = $service->createItem($this->pdo, $this->userId, 'Хліб', 'сорок', $categoryId);

        $this->assertFalse($result['ok']);
        $this->assertContains("Ціна має бути числом (можна з крапкою).", $result['errors']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(0, $count);
    }

    #[Test]
    public function create_normalizes_comma_and_rounds_to_2_decimals(): void
    {
        $service = new ItemServiceFake();
        $categoryId = $this->categoryId('Продукти');

        $result = $service->createItem($this->pdo, $this->userId, 'Округлення', '0,105', $categoryId);

        $this->assertTrue($result['ok']);

        $price = (float)$this->pdo->query("SELECT price FROM items LIMIT 1")->fetchColumn();
        $this->assertSame(0.11, $price);
    }
}

final class ItemServiceFake
{
    public function createItem(PDO $pdo, int $userId, string $name, string $price, int $categoryId): array
    {
        $errors = [];
        if ($e = validate_item_name($name)) $errors[] = $e;
        if ($e = validate_price($price)) $errors[] = $e;

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $p = round((float) normalize_price($price), 2);

        $st = $pdo->prepare("
            INSERT INTO items (user_id, category_id, name, price, is_purchased, created_at)
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ");
        $st->execute([$userId, $categoryId, trim($name), $p]);

        return ['ok' => true];
    }
}
function validate_item_name(string $name): ?string {
    $n = trim($name);
    if ($n === '') return "Назва обов'язкова.";
    if (mb_strlen($n) > 100) return "Назва максимум 100 символів.";
    if (!preg_match('/^[\p{L}\p{N} ]+$/u', $n)) return "Назва: дозволено лише літери/цифри/пробіли.";
    return null;
}

function normalize_price(string $price): ?string {
    $p = trim($price);
    $p = str_replace(',', '.', $p);
    return $p;
}

function validate_price(string $price): ?string {
    $p = normalize_price($price);
    if ($p === '') return "Ціна обов'язкова.";
    if (!preg_match('/^\d+(\.\d{1,3})?$/', $p)) return "Ціна має бути числом (можна з крапкою).";
    $val = floatval($p);
    if ($val < 0 || $val > 9999.99) return "Ціна має бути в діапазоні 0–9999.99.";
    return null;
}