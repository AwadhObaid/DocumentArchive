<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('documents.index');
});

Route::get('/settings', [SettingsController::class, 'edit'])
    ->name('settings.edit');

Route::post('/settings', [SettingsController::class, 'update'])
    ->name('settings.update');

Route::get('/documents/{document}/print-reference', [DocumentController::class, 'printReference'])
    ->name('documents.print-reference');

Route::get('/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])
    ->name('attachments.download');

Route::resource('documents', DocumentController::class);