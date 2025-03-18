<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\TagController;
use Illuminate\Support\Facades\Route;

// Routes publiques (non authentifiées)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées (authentifiées)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Routes pour les dépenses
    Route::apiResource('expenses', ExpenseController::class);
    Route::post('expenses/{id}/tags', [ExpenseController::class, 'attachTags']);
    
    // Routes pour les tags
    Route::apiResource('tags', TagController::class);
});