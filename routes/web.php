<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/export/orders', [ExportController::class, 'exportOrders'])
    ->name('export.orders');
