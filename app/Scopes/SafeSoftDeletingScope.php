<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class SafeSoftDeletingScope extends SoftDeletingScope
{
    private static ?bool $hasColumn = null;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$hasColumn !== true) {
            try {
                self::$hasColumn = Schema::hasColumn($model->getTable(), $model->getDeletedAtColumn());
            } catch (\Throwable) {
                self::$hasColumn = false;
            }
        }

        if (self::$hasColumn) {
            parent::apply($builder, $model);
        }
    }
}
