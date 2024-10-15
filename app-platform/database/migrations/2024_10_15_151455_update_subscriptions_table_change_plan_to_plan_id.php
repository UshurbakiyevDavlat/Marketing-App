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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Удаляем старое поле 'plan'
            $table->dropColumn('plan');

            // Добавляем новое поле 'plan_id' с внешним ключом на таблицу subscription_plans
            $table->unsignedBigInteger('plan_id')->nullable()->after('user_id');

            // Создаем внешний ключ на таблицу subscription_plans
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Удаляем внешний ключ и поле 'plan_id'
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');

            // Восстанавливаем поле 'plan'
            $table->enum('plan', ['free', 'basic', 'pro', 'enterprise'])->default('free');
        });
    }
};
