<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_roles')
            ->where('name', 'operator')
            ->update(['name' => 'moderator']);
    }

    public function down(): void
    {
        DB::table('user_roles')
            ->where('name', 'moderator')
            ->where('id', 4)
            ->update(['name' => 'operator']);
    }
};
