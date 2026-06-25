<?php

namespace App\Support;

class BranchContext
{
    private static ?int $branchId = null;

    public static function setBranchId(?int $branchId): void
    {
        self::$branchId = $branchId;
    }

    public static function branchId(): ?int
    {
        return self::$branchId;
    }
}
