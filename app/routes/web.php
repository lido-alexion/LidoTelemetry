<?php

use Illuminate\Support\Facades\Route;

/*
| SPA fallback: React Router handles client paths.
| Never match /api/* — unknown API paths must 404 as JSON, not return this HTML shell.
*/
Route::view('/{any?}', 'app')->where('any', '^(?!api(?:/|$)).*');
