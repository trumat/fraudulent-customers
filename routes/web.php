<?php

use Illuminate\Support\Facades\Route;

// The Vue SPA handles every other path; vue-router decides what to render.
Route::view('/{any?}', 'app')->where('any', '^(?!(api|docs|up)(/|$)).*$');
