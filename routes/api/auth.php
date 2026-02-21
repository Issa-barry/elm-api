<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes d'authentification
|--------------------------------------------------------------------------
*/

// Routes publiques (sans authentification)
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

// Routes protégées (avec authentification)
Route::middleware(['auth:sanctum', 'usine.context'])->group(function () {
    
    // Déconnexion
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::post('/logout-all', [LogoutController::class, 'logoutAll']);
    
    // Profil
    Route::get('/me', [ProfileController::class, 'me']);
    Route::put('/profile', [ProfileController::class, 'update']);
    
    // Mot de passe
    Route::post('/change-password', [PasswordController::class, 'change']);
    
    // Token
    Route::post('/refresh-token', [TokenController::class, 'refresh']);
    Route::get('/check-token', [TokenController::class, 'check']);
});