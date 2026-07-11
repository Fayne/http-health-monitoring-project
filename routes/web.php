<?php

use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', config('nova.path'));

Route::get('/metrics', MetricsController::class);
