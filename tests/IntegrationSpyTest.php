<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ItemInsertSpy
{
    public int $calledTimes = 0;

    public function insert(): void
    {
        $this->calledTimes++;
    }
}
final class FakeImportService
{
    public function __construct(private ItemInsertSpy $spy) {}

    public function import(array $items): void
    {
        foreach ($items as $item) {
            $this->spy->insert();
        }
    }
}

final class IntegrationSpyTest extends TestCase
{
    #[Test]
    public function import_calls_insert_for_each_item(): void
    {
        $spy = new ItemInsertSpy();
        $service = new FakeImportService($spy);

        $items = [
            ['name' => 'Хліб'],
            ['name' => 'Молоко'],
            ['name' => 'Сир'],
        ];

        $service->import($items);

        $this->assertSame(3, $spy->calledTimes);
    }
}