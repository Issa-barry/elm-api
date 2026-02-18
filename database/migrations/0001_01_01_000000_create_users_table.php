<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identité
            $table->string('nom');
            $table->string('prenom');

            // Contact
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Localisation
            $table->string('pays');
            $table->string('code_pays', 5);
            $table->string('code_phone_pays', 5);
            $table->string('ville');
            $table->string('quartier');

            // Référence (auto-généré)
            $table->string('reference')->unique();

            // Type de compte (staff|client|prestataire)
            $table->string('type', 20)->default('staff')->comment('Nature du compte : staff, client, prestataire, investisseur');
            $table->index('type');

            // Auth
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};