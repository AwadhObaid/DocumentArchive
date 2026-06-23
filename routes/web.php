<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('documents.index');
});

Route::resource('documents', DocumentController::class);

Route::get('/documents/{document}/print-reference', [DocumentController::class, 'printReference'])
    ->name('documents.print-reference');