<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WebController;

Route::get('/', [WebController::class, 'index']);
