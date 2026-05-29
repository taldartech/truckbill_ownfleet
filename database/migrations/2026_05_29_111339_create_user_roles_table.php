<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const ROLES = [
        1 => 'master_admin',
        2 => 'super_admin',
        3 => 'admin',
        4 => 'moderator',
        5 => 'accountant',
        6 => 'transporter',
        7 => 'customer',
    ];

    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        foreach (self::ROLES as $id => $name) {
            DB::table('user_roles')->insert([
                'id' => $id,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
