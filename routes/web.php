<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/documents/trash', [DocumentController::class, 'trash'])->name('documents.trash');
    Route::post('/documents/{id}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
    Route::delete('/documents/{id}/force-delete', [DocumentController::class, 'forceDelete'])->name('documents.force-delete');

    Route::get('/documents/{document}/print-reference', [DocumentController::class, 'printReference'])
        ->name('documents.print-reference');

    Route::get('/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])
        ->name('attachments.download');

    Route::resource('departments', DepartmentController::class)->except(['show']);

    Route::resource('document-types', DocumentTypeController::class)
        ->except(['show'])
        ->parameters([
            'document-types' => 'documentType',
        ]);

    Route::resource('documents', DocumentController::class);
});
