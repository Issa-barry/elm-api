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
            $table->string('civilite', 10)->nullable()->comment('M, Mme, Mlle');
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance')->nullable();

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
            $table->string('adresse')->nullable();

            // Référence (auto-généré)
            $table->string('reference')->unique();

            // Type de compte
            $table->string('type', 20)->default('staff')->comment('Nature du compte : staff, client, prestataire, investisseur');
            $table->index('type');

            // Préférences
            $table->string('language', 5)->default('fr');

            // Auth
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();

            // Pièce d'identité (KYC)
            $table->string('piece_type', 30)->nullable()->comment('cni, passeport, permis, carte_sejour');
            $table->string('piece_numero', 100)->nullable();
            $table->date('piece_delivree_le')->nullable();
            $table->date('piece_expire_le')->nullable();
            $table->string('piece_pays', 5)->nullable()->comment('Code ISO pays émetteur');
            $table->string('piece_fichier')->nullable()->comment('Recto - path/url');
            $table->string('piece_fichier_verso')->nullable()->comment('Verso - path/url');

            // Tracking / Onboarding
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
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