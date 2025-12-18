<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ImportCSVTest extends TestCase
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
                name TEXT NOT NULL,
                price REAL NOT NULL,
                category TEXT NOT NULL,
                is_purchased INTEGER NOT NULL
            );
        ");

        $stmt = $this->pdo->prepare("INSERT INTO categories(name) VALUES (?)");
        foreach (['Продукти', 'Одяг', 'Інше'] as $cat) {
            $stmt->execute([$cat]);
        }
    }

    #[Test]
    public function import_valid_csv_success(): void
    {
        $csv = <<<CSV
name,price,category,is_purchased
Молоко,45.50,Продукти,false
Куртка,1500,Одяг,true
CSV;

        $service = new CsvImportServiceFake();

        $result = $service->importFromString($this->pdo, $this->userId, $csv);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function import_invalid_header_throws_exception(): void
    {
        $csv = <<<CSV
title,cost,cat,purchased
Молоко,45.50,Продукти,false
CSV;

        $service = new CsvImportServiceFake();

        $this->expectException(InvalidArgumentException::class);

        $service->importFromString($this->pdo, $this->userId, $csv);
    }

    #[Test]
    public function import_skips_invalid_rows(): void
    {
        $csv = <<<CSV
name,price,category,is_purchased
,10,Продукти,false
Телефон,abc,Продукти,false
Хліб,25,Продукти,true
CSV;

        $service = new CsvImportServiceFake();

        $result = $service->importFromString($this->pdo, $this->userId, $csv);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
    }
}
final class CsvImportServiceFake
{
    public function importFromString(PDO $pdo, int $userId, string $csv): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv));
        if (!$lines || count($lines) < 2) {
            throw new InvalidArgumentException("CSV is empty");
        }

        $header = str_getcsv(array_shift($lines));
        $expected = ['name', 'price', 'category', 'is_purchased'];

        if ($header !== $expected) {
            throw new InvalidArgumentException("Invalid CSV header");
        }

        $imported = 0;
        $skipped = 0;

        $stmt = $pdo->prepare("
            INSERT INTO items (user_id, name, price, category, is_purchased)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (count($row) !== 4) {
                $skipped++;
                continue;
            }

            [$name, $price, $category, $isPurchased] = $row;

            if (trim($name) === '' || !is_numeric($price)) {
                $skipped++;
                continue;
            }

            $catCheck = $pdo->prepare("SELECT 1 FROM categories WHERE name=?");
            $catCheck->execute([$category]);
            if (!$catCheck->fetchColumn()) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                $userId,
                $name,
                (float)$price,
                $category,
                strtolower($isPurchased) === 'true' ? 1 : 0
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped
        ];
    }
}