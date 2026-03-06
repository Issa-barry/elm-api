<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organisation_billing_events')) {
            return;
        }

        Schema::create('organisation_billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Idempotence : un seul event par (type, user)
            $table->unique(['event_type', 'user_id']);

            // Index pour les requêtes de listing/filtrage (nom court : limite MySQL 64 chars)
            $table->index(['organisation_id', 'status', 'occurred_at'], 'billing_events_org_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_billing_events');
    }
};
