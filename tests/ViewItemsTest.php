<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ViewItemsTest extends TestCase
{
    private PDO $pdo;
    private int $userId = 1;
    private int $otherUserId = 2;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

        $this->seedItem($this->userId, $this->categoryId('Продукти'), 'Молоко 2л', 45.50, 0, '2025-01-01 10:00:00');
        $this->seedItem($this->userId, $this->categoryId('Одяг'), 'Куртка', 1500.00, 1, '2025-01-02 09:00:00');
        $this->seedItem($this->userId, $this->categoryId('Продукти'), 'Хліб', 19.99, 0, '2025-01-03 08:00:00');
        $this->seedItem($this->otherUserId, $this->categoryId('Продукти'), 'Чужий товар', 999.00, 0, '2025-01-04 12:00:00');
    }

    private function categoryId(string $name): int
    {
        $st = $this->pdo->prepare("SELECT id FROM categories WHERE name=?");
        $st->execute([$name]);
        $id = $st->fetchColumn();
        $this->assertNotFalse($id, "Category '$name' must exist in seed");
        return (int)$id;
    }

    private function seedItem(
        int $userId,
        int $categoryId,
        string $name,
        float $price,
        int $isPurchased,
        string $createdAt
    ): void {
        $st = $this->pdo->prepare("
            INSERT INTO items (user_id, category_id, name, price, is_purchased, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $st->execute([$userId, $categoryId, $name, $price, $isPurchased, $createdAt]);
    }

    #[Test]
    public function get_categories_returns_all_categories_ordered_by_id(): void
    {
        $cats = get_categories_fake($this->pdo);

        $this->assertCount(3, $cats);
        $this->assertSame('Продукти', $cats[0]['name']);
        $this->assertSame('Одяг', $cats[1]['name']);
        $this->assertSame('Інше', $cats[2]['name']);
    }

    #[Test]
    public function get_items_without_filter_returns_only_user_items_with_joined_category_name_sorted_desc(): void
    {
        $items = get_items_fake($this->pdo, $this->userId, null);
        $this->assertCount(3, $items);
        $this->assertSame('Хліб', $items[0]['name']);
        $this->assertSame('Продукти', $items[0]['category_name']);
        $this->assertSame('Куртка', $items[1]['name']);
        $this->assertSame('Одяг', $items[1]['category_name']);
        $this->assertSame('Молоко 2л', $items[2]['name']);
        $this->assertSame('Продукти', $items[2]['category_name']);
    }

    #[Test]
    public function get_items_with_category_filter_returns_only_that_category_for_user(): void
    {
        $prodId = $this->categoryId('Продукти');

        $items = get_items_fake($this->pdo, $this->userId, $prodId);

        $this->assertCount(2, $items);

        foreach ($items as $it) {
            $this->assertSame($prodId, (int)$it['category_id']);
            $this->assertSame('Продукти', $it['category_name']);
            $this->assertSame($this->userId, (int)$it['user_id']);
        }

        $this->assertSame('Хліб', $items[0]['name']);
        $this->assertSame('Молоко 2л', $items[1]['name']);
    }

    #[Test]
    public function get_items_filter_with_no_items_returns_empty_array(): void
    {
        $this->pdo->exec("INSERT INTO categories(name) VALUES ('Побут')");
        $catId = (int)$this->pdo->query("SELECT id FROM categories WHERE name='Побут'")->fetchColumn();

        $items = get_items_fake($this->pdo, $this->userId, $catId);

        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    #[Test]
    public function get_items_does_not_leak_items_of_other_users_even_when_category_matches(): void
    {
        $prodId = $this->categoryId('Продукти');

        $items = get_items_fake($this->pdo, $this->userId, $prodId);

        foreach ($items as $it) {
            $this->assertSame($this->userId, (int)$it['user_id']);
            $this->assertNotSame('Чужий товар', $it['name']);
        }
    }
}

function get_categories_fake(PDO $pdo): array
{
    return $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
}

function get_items_fake(PDO $pdo, int $userId, ?int $categoryId): array
{
    if ($categoryId) {
        $st = $pdo->prepare("
            SELECT i.*, c.name AS category_name
            FROM items i JOIN categories c ON c.id=i.category_id
            WHERE i.user_id=? AND i.category_id=?
            ORDER BY i.created_at DESC, i.id DESC
        ");
        $st->execute([$userId, $categoryId]);
    } else {
        $st = $pdo->prepare("
            SELECT i.*, c.name AS category_name
            FROM items i JOIN categories c ON c.id=i.category_id
            WHERE i.user_id=?
            ORDER BY i.created_at DESC, i.id DESC
        ");
        $st->execute([$userId]);
    }

    return $st->fetchAll();
}