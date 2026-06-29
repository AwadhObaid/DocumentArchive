<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\ApplyRoutePermissions;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\NotificationCenterController;

use App\Http\Controllers\NotificationSettingsController;
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', ApplyRoutePermissions::class])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/system-health', [SystemHealthController::class, 'index'])
        ->name('system-health.index');
    Route::get('/data-quality', [DataQualityController::class, 'index'])
        ->name('data-quality.index');
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

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

    Route::get('/backups', [BackupController::class, 'index'])
        ->name('backups.index');

    Route::post('/backups/database', [BackupController::class, 'createDatabaseBackup'])
        ->name('backups.database');

    Route::post('/backups/files', [BackupController::class, 'createFilesBackup'])
        ->name('backups.files');

    Route::post('/backups/full', [BackupController::class, 'createFullBackup'])
        ->name('backups.full');

    Route::get('/backups/{fileName}/download', [BackupController::class, 'download'])
        ->name('backups.download');

    Route::delete('/backups/{fileName}', [BackupController::class, 'destroy'])
        ->name('backups.destroy');

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

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
Route::get('/documents/check-policy-duplicate', [DocumentController::class, 'checkPolicyDuplicate'])->name('documents.check-policy-duplicate');
Route::resource('documents', DocumentController::class);
});

Route::middleware(['auth', ApplyRoutePermissions::class])->group(function () {
    Route::get('/backups/{fileName}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('/backups/{fileName}/restore/database', [BackupController::class, 'restoreDatabase'])->name('backups.restore.database');
    Route::post('/backups/{fileName}/restore/files', [BackupController::class, 'restoreFiles'])->name('backups.restore.files');
    Route::post('/backups/{fileName}/restore/full', [BackupController::class, 'restoreFull'])->name('backups.restore.full');

    Route::get('/backups/{fileName}/inspect', [BackupController::class, 'inspect'])->name('backups.inspect');
});

// Professional printable data quality report
Route::get('/data-quality/print', [\App\Http\Controllers\DataQualityPrintController::class, 'index'])->middleware(['auth'])->name('data-quality.print');

// Professional printable documents report
Route::get('/reports/print', [\App\Http\Controllers\ReportPrintController::class, 'index'])->middleware(['auth'])->name('reports.print');

// notification-center-routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::delete('/notifications/{notification}', [NotificationCenterController::class, 'destroy'])->name('notifications.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
    Route::post('/notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
});
// Notification center management actions
Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [\App\Http\Controllers\NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::delete('/notifications/clear-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
});

/*
|--------------------------------------------------------------------------
| Notification management fallback routes
|--------------------------------------------------------------------------
| Safe compatibility routes for notification center actions.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markAsRead'])->whereNumber('notification')->name('notifications.read');
    Route::post('/notifications/{notification}/hide', [NotificationCenterController::class, 'hide'])->whereNumber('notification')->name('notifications.hide');
    Route::delete('/notifications/{notification}', [NotificationCenterController::class, 'destroy'])->whereNumber('notification')->name('notifications.destroy');
});

/*
|--------------------------------------------------------------------------
| Notification management compatibility routes - V4
|--------------------------------------------------------------------------
| These aliases keep old and new notification buttons working.
*/
Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.read_all');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.clear-hidden');
});

// Notification Center V5 stable compatibility routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'readAll'])->name('notifications.read_all');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationCenterController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [\App\Http\Controllers\NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationCenterController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/{notification}/hide', [\App\Http\Controllers\NotificationCenterController::class, 'hide'])->name('notifications.hide');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationCenterController::class, 'destroy'])->name('notifications.destroy');
});
