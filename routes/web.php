<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MailGeneratorController;

Route::get('/', [MailGeneratorController::class, 'index']);
Route::post('/generate', [MailGeneratorController::class, 'generate'])->name('generate');
