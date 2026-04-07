<?php

use Illuminate\Support\Facades\Route;
use ShopSavvy\Laravel\Http\Controllers\ShopSavvyController;

/*
|--------------------------------------------------------------------------
| ShopSavvy Optional API Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when shopsavvy.routes.enabled is true.
| Enable them by setting SHOPSAVVY_ROUTES_ENABLED=true in your .env file,
| or by setting 'routes' => ['enabled' => true] in config/shopsavvy.php.
|
*/

Route::get('/search', [ShopSavvyController::class, 'search']);
Route::get('/offers/{identifier}', [ShopSavvyController::class, 'offers']);
Route::get('/history/{identifier}', [ShopSavvyController::class, 'history']);
Route::get('/product/{identifier}', [ShopSavvyController::class, 'product']);
Route::get('/deals', [ShopSavvyController::class, 'deals']);
