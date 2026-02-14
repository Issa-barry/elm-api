<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Verifier que la colonne role existe encore
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        // Creer les roles Spatie s'ils n'existent pas
        foreach (['admin', 'manager', 'employe'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Lire les roles existants et assigner les roles Spatie
        $userRoles = DB::table('users')->whereNull('deleted_at')->select('id', 'role')->get();

        foreach ($userRoles as $userData) {
            $user = User::find($userData->id);
            if ($user) {
                $roleName = $userData->role ?? 'employe';
                if (Role::where('name', $roleName)->exists()) {
                    $user->assignRole($roleName);
                } else {
                    $user->assignRole('employe');
                }
            }
        }

        // Supprimer la colonne role
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employe')->after('reference');
        });
    }
};
