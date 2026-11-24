<?php
declare(strict_types=1);
namespace App\Production;
interface WorkCatalog
{
    public function works(): array;
    public function entities(string $work): array;
}
