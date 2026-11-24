<?php
declare(strict_types=1);
namespace App\Production;
interface WorkCatalog
{
    public function works(): array;
    public function revision(string $work): int;
    public function entities(string $work): array;
}
