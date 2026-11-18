<?php

use App\Http\Controllers\Api\ListScansController;
use App\Http\Controllers\Api\RunScanController;
use App\Http\Controllers\Api\ShowLatestScanController;
use App\Http\Controllers\Api\ShowScanController;
use Illuminate\Support\Facades\Route;

Route::post('/scans', RunScanController::class);
Route::get('/scans', ListScansController::class);
Route::get('/scans/latest', ShowLatestScanController::class);
Route::get('/scans/{scan}', ShowScanController::class)->whereNumber('scan');
