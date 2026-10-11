<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->char('preferred_currency', 3)->nullable()->after('avatar_path');
            $table->boolean('is_protected')->default(false)->after('preferred_currency');
            $table->boolean('is_active')->default(true)->after('is_protected');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'deleted_at']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone',
                'avatar_path',
                'preferred_currency',
                'is_protected',
                'is_active',
                'deactivated_at',
                'last_login_at',
            ]);
        });
    }
};
