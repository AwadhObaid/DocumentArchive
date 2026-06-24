<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'edit'])
        ->name('settings.edit');

    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');

    Route::get('/documents/{document}/activity', [ActivityLogController::class, 'document'])
        ->name('documents.activity');

    Route::get('/documents/trash', [DocumentController::class, 'trash'])
        ->name('documents.trash');

    Route::post('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->withTrashed()
        ->name('documents.restore');

    Route::delete('/documents/{document}/force-delete', [DocumentController::class, 'forceDelete'])
        ->withTrashed()
        ->name('documents.force-delete');

    Route::get('/documents/{document}/print-reference', [DocumentController::class, 'printReference'])
        ->name('documents.print-reference');

    Route::get('/attachments/{attachment}/preview', [DocumentController::class, 'previewAttachment'])
        ->name('attachments.preview');

    Route::get('/attachments/{attachment}/data', [DocumentController::class, 'attachmentData'])
        ->name('attachments.data');

    Route::get('/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])
        ->name('attachments.download');

    Route::get('/attachments/{attachment}/inline', [DocumentController::class, 'inlineAttachment'])
        ->name('attachments.inline');

    Route::resource('departments', DepartmentController::class)->except(['show']);

    Route::resource('document-types', DocumentTypeController::class)
        ->except(['show'])
        ->parameters([
            'document-types' => 'documentType',
        ]);

    Route::get('/users/{user}/password', [UserController::class, 'editPassword'])
        ->name('users.password.edit');

    Route::put('/users/{user}/password', [UserController::class, 'updatePassword'])
        ->name('users.password.update');

    Route::patch('/users/{user}/activate', [UserController::class, 'activate'])
        ->name('users.activate');

    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->name('users.deactivate');

    Route::resource('users', UserController::class)->except(['show']);

    Route::resource('documents', DocumentController::class);
});
