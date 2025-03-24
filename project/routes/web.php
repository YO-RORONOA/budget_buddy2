<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BalanceController;
use App\Http\Controllers\API\GroupExpenseController;
use App\Http\Controllers\API\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
});




Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('groups', \App\Http\Controllers\API\GroupController::class);
    
    Route::get('groups/{id}/expenses', [GroupExpenseController::class, 'index']);
    Route::post('groups/{id}/expenses', [GroupExpenseController::class, 'store']);
    Route::delete('groups/{id}/expenses/{expenseId}', [GroupExpenseController::class, 'destroy']);
    
    Route::get('groups/{id}/balances', [BalanceController::class, 'index']);
    
    Route::post('groups/{id}/settle', [PaymentController::class, 'store']);
    Route::get('groups/{id}/history', [PaymentController::class, 'history']);
});