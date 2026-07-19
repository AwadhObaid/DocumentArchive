<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookSubjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\LiteController;
use App\Http\Controllers\InternalMessageController;
use App\Http\Controllers\InternalChatController;
use App\Http\Controllers\InternalChatAdminController;
use App\Http\Controllers\SessionActivityController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\SharedAttachmentLinkController;
use App\Http\Controllers\FormLinkController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemAboutController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\PdfSearchController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\ApplyRoutePermissions;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\LeaveCalculatorController;
use App\Http\Controllers\SmartReportController;
use App\Http\Controllers\LegacyArchiveImportController;


Route::get('/shared/attachments/{token}', [SharedAttachmentLinkController::class, 'publicShow'])
    ->name('shared-attachments.public.show');

Route::post('/shared/attachments/{token}/unlock', [SharedAttachmentLinkController::class, 'publicUnlock'])
    ->name('shared-attachments.public.unlock');

Route::get('/shared/attachments/{token}/files/{item}/download', [SharedAttachmentLinkController::class, 'publicDownload'])
    ->name('shared-attachments.public.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::get('/logout', [AuthController::class, 'logoutNotice'])
    ->name('logout.notice');
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', ApplyRoutePermissions::class])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::post('/session/activity', [SessionActivityController::class, 'ping'])
        ->name('session.activity');

    Route::prefix('lite')->name('lite.')->group(function () {
        Route::get('/', [LiteController::class, 'index'])->name('index');
        Route::get('/documents', [LiteController::class, 'documents'])->name('documents.index');
        Route::get('/documents/{document}', [LiteController::class, 'documentShow'])->name('documents.show');
        Route::get('/memos', [LiteController::class, 'memos'])->name('memos.index');
        Route::get('/memos/{memo}', [LiteController::class, 'memoShow'])->name('memos.show');
        Route::get('/notifications', [LiteController::class, 'notifications'])->name('notifications.index');
        Route::get('/notifications/poll', [LiteController::class, 'poll'])->name('notifications.poll');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tools/leave-calculator', [LeaveCalculatorController::class, 'index'])
        ->name('tools.leave-calculator.index');

// attachments-scanner-v75-v80-routes:start
    Route::prefix('tools/attachments-migration')->name('attachments-migration.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AttachmentRelocationController::class, 'index'])->name('index');
        Route::post('/dry-run', [\App\Http\Controllers\AttachmentRelocationController::class, 'dryRun'])->name('dry-run');
        Route::post('/execute', [\App\Http\Controllers\AttachmentRelocationController::class, 'execute'])->name('execute');
        Route::get('/runs/{run}', [\App\Http\Controllers\AttachmentRelocationController::class, 'show'])
            ->whereNumber('run')
            ->name('show');
    });

    Route::prefix('scanner-inbox')->name('scanner-inbox.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ScannerInboxController::class, 'index'])->name('index');
        Route::post('/path', [\App\Http\Controllers\ScannerInboxController::class, 'updatePath'])->name('update-path');
        Route::post('/attach', [\App\Http\Controllers\ScannerInboxController::class, 'attach'])->name('attach');
    });
    // attachments-scanner-v75-v80-routes:end

// legacy-archive-import-v81-routes:start
    Route::prefix('tools/legacy-archive-import')->name('legacy-archive-import.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LegacyArchiveImportController::class, 'index'])->name('index');
        Route::post('/dry-run', [\App\Http\Controllers\LegacyArchiveImportController::class, 'dryRun'])->name('dry-run');
        Route::post('/execute', [\App\Http\Controllers\LegacyArchiveImportController::class, 'execute'])->name('execute');
        Route::get('/runs/{run}', [\App\Http\Controllers\LegacyArchiveImportController::class, 'show'])
            ->whereNumber('run')
            ->name('show');
    });
// legacy-archive-import-v81-routes:end
    // Smart Reports - Gemini
    Route::get('/smart-reports', [SmartReportController::class, 'index'])
        ->name('smart-reports.index');
    Route::post('/smart-reports/generate', [SmartReportController::class, 'generate'])
        ->name('smart-reports.generate');
    Route::post('/smart-reports/test-gemini', [SmartReportController::class, 'testGemini'])
        ->name('smart-reports.test-gemini');
    Route::get('/smart-reports/{smartReportRun}', [SmartReportController::class, 'show'])
        ->whereNumber('smartReportRun')
        ->name('smart-reports.show');
    Route::get('/smart-reports/{smartReportRun}/word', [SmartReportController::class, 'exportWord'])
        ->whereNumber('smartReportRun')
        ->name('smart-reports.export-word');
    Route::get('/smart-reports/{smartReportRun}/pdf', [SmartReportController::class, 'exportPdf'])
        ->whereNumber('smartReportRun')
        ->name('smart-reports.export-pdf');


    Route::prefix('internal-chat')->name('internal-chat.')->group(function () {
        Route::get('/bootstrap', [InternalChatController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/users', [InternalChatController::class, 'users'])->name('users');
        Route::get('/poll', [InternalChatController::class, 'poll'])->name('poll');
        Route::get('/messages/{user}', [InternalChatController::class, 'messages'])->name('messages');
        Route::get('/conversations/{conversation}/messages', [InternalChatController::class, 'conversationMessages'])->name('conversations.messages');
        Route::post('/groups', [InternalChatController::class, 'createGroup'])->name('groups.store');
        Route::get('/archived', [InternalChatController::class, 'archivedConversations'])->name('archived');
        Route::post('/conversations/{conversation}/archive', [InternalChatController::class, 'archiveConversation'])->name('conversations.archive');
        Route::post('/conversations/{conversation}/restore', [InternalChatController::class, 'restoreConversation'])->name('conversations.restore');
        Route::post('/conversations/{conversation}/delete', [InternalChatController::class, 'deleteConversation'])->name('conversations.delete');
        Route::get('/search', [InternalChatController::class, 'search'])->name('search');
        Route::get('/lookup/documents', [InternalChatController::class, 'lookupDocuments'])->name('lookup.documents');
        Route::get('/lookup/memos', [InternalChatController::class, 'lookupMemos'])->name('lookup.memos');
        Route::post('/messages', [InternalChatController::class, 'send'])->name('send');
        Route::post('/typing', [InternalChatController::class, 'typing'])->name('typing');
        Route::post('/read/{user}', [InternalChatController::class, 'markRead'])->name('read');
    });



    Route::prefix('settings/internal-chat')->name('settings.internal-chat.')->group(function () {
        Route::post('/backup', [InternalChatAdminController::class, 'backup'])->name('backup');
        Route::post('/restore-backup', [InternalChatAdminController::class, 'restoreBackup'])->name('restore-backup');
        Route::post('/restore-deleted', [InternalChatAdminController::class, 'restoreDeleted'])->name('restore-deleted');
        Route::post('/purge-deleted', [InternalChatAdminController::class, 'purgeDeleted'])->name('purge-deleted');
    });

    Route::get('/internal-messages', [InternalMessageController::class, 'index'])->name('internal-messages.index');
    Route::get('/internal-messages/create', [InternalMessageController::class, 'create'])->name('internal-messages.create');
    Route::post('/internal-messages', [InternalMessageController::class, 'store'])->name('internal-messages.store');
    Route::get('/internal-messages/{internalMessage}', [InternalMessageController::class, 'show'])->name('internal-messages.show');
    Route::patch('/internal-messages/{internalMessage}/archive', [InternalMessageController::class, 'archive'])->name('internal-messages.archive');
    Route::get('/internal-messages/{internalMessage}/attachments/{attachment}/preview', [InternalMessageController::class, 'previewAttachment'])->name('internal-messages.attachments.preview');
    Route::get('/internal-messages/{internalMessage}/attachments/{attachment}/data', [InternalMessageController::class, 'attachmentData'])->name('internal-messages.attachments.data');
    Route::get('/internal-messages/{internalMessage}/attachments/{attachment}/inline', [InternalMessageController::class, 'inlineAttachment'])->name('internal-messages.attachments.inline');
    Route::get('/internal-messages/{internalMessage}/attachments/{attachment}/download', [InternalMessageController::class, 'downloadAttachment'])->name('internal-messages.attachments.download');
    Route::get('/documents/{document}/internal-send', [InternalMessageController::class, 'createForDocument'])->name('documents.internal-message.create');
    Route::get('/memos/{memo}/internal-send', [InternalMessageController::class, 'createForMemo'])->name('memos.internal-message.create');
    Route::get('/system-health', [SystemHealthController::class, 'index'])
        ->name('system-health.index');


    Route::get('/pdf-search', [PdfSearchController::class, 'index'])
        ->name('pdf-search.index');
    Route::post('/pdf-search/run', [PdfSearchController::class, 'run'])
        ->name('pdf-search.run');
    Route::post('/pdf-search/indexes/{attachmentTextIndex}/reindex', [PdfSearchController::class, 'reindex'])
        ->name('pdf-search.reindex');

    Route::get('/system-rights', [SystemAboutController::class, 'index'])
        ->name('system-rights.index');
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

    Route::get('/settings/book-attachment-storage/roots', [SettingsController::class, 'bookAttachmentStorageRoots'])
        ->name('settings.book-attachment-storage.roots');

    Route::get('/settings/book-attachment-storage/directories', [SettingsController::class, 'bookAttachmentStorageDirectories'])
        ->name('settings.book-attachment-storage.directories');

    Route::post('/settings/book-attachment-storage/directories', [SettingsController::class, 'createBookAttachmentStorageDirectory'])
        ->name('settings.book-attachment-storage.directories.create');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');

    Route::get('/documents/{document}/activity', [ActivityLogController::class, 'document'])
        ->name('documents.activity');


    Route::post('/documents/{document}/workflow/submit', [WorkflowController::class, 'submitDocument'])->name('documents.workflow.submit');
    Route::post('/documents/{document}/workflow/approve', [WorkflowController::class, 'approveDocument'])->name('documents.workflow.approve');
    Route::post('/documents/{document}/workflow/reject', [WorkflowController::class, 'rejectDocument'])->name('documents.workflow.reject');
    Route::post('/documents/{document}/workflow/return', [WorkflowController::class, 'returnDocument'])->name('documents.workflow.return');
    Route::post('/documents/{document}/workflow/finalize', [WorkflowController::class, 'finalizeDocument'])->name('documents.workflow.finalize');
    Route::post('/documents/{document}/workflow/reopen', [WorkflowController::class, 'reopenDocument'])->name('documents.workflow.reopen');

    Route::post('/memos/{memo}/workflow/submit', [WorkflowController::class, 'submitMemo'])->name('memos.workflow.submit');
    Route::post('/memos/{memo}/workflow/approve', [WorkflowController::class, 'approveMemo'])->name('memos.workflow.approve');
    Route::post('/memos/{memo}/workflow/reject', [WorkflowController::class, 'rejectMemo'])->name('memos.workflow.reject');
    Route::post('/memos/{memo}/workflow/return', [WorkflowController::class, 'returnMemo'])->name('memos.workflow.return');
    Route::post('/memos/{memo}/workflow/finalize', [WorkflowController::class, 'finalizeMemo'])->name('memos.workflow.finalize');
    Route::post('/memos/{memo}/workflow/reopen', [WorkflowController::class, 'reopenMemo'])->name('memos.workflow.reopen');


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

    Route::get('/shared-attachment-links', [SharedAttachmentLinkController::class, 'index'])
        ->name('shared-attachment-links.index');

    Route::get('/shared-attachment-links/create', [SharedAttachmentLinkController::class, 'create'])
        ->name('shared-attachment-links.create');

    Route::post('/shared-attachment-links', [SharedAttachmentLinkController::class, 'store'])
        ->name('shared-attachment-links.store');

    Route::get('/shared-attachment-links/{sharedAttachmentLink}', [SharedAttachmentLinkController::class, 'show'])
        ->name('shared-attachment-links.show');

    Route::patch('/shared-attachment-links/{sharedAttachmentLink}/revoke', [SharedAttachmentLinkController::class, 'revoke'])
        ->name('shared-attachment-links.revoke');

    Route::delete('/shared-attachment-links/{sharedAttachmentLink}', [SharedAttachmentLinkController::class, 'destroy'])
        ->name('shared-attachment-links.destroy');

    Route::get('/documents/{document}/shared-attachments/create', [SharedAttachmentLinkController::class, 'create'])
        ->name('documents.shared-attachments.create');

    Route::get('/memos/{memo}/shared-attachments/create', [SharedAttachmentLinkController::class, 'createMemo'])
        ->name('memos.shared-attachments.create');

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

    Route::resource('book-subjects', BookSubjectController::class)
        ->except(['show'])
        ->parameters([
            'book-subjects' => 'bookSubject',
        ]);

    Route::get('/memos/{memo}/attachments/{attachment}/preview', [MemoController::class, 'previewAttachment'])
        ->name('memos.attachments.preview');

    Route::get('/memos/{memo}/attachments/{attachment}/data', [MemoController::class, 'attachmentData'])
        ->name('memos.attachments.data');

    Route::get('/memos/{memo}/attachments/{attachment}/inline', [MemoController::class, 'inlineAttachment'])
        ->name('memos.attachments.inline');

    Route::get('/memos/{memo}/attachments/{attachment}/download', [MemoController::class, 'downloadAttachment'])
        ->name('memos.attachments.download');


    Route::post('/memos/{memo}/restore', [MemoController::class, 'restore'])
        ->withTrashed()
        ->name('memos.restore');

    Route::delete('/memos/{memo}/force-delete', [MemoController::class, 'forceDelete'])
        ->withTrashed()
        ->name('memos.force-delete');

    Route::resource('memos', MemoController::class);

    Route::get('/users/{user}/password', [UserController::class, 'editPassword'])
        ->name('users.password.edit');

    Route::put('/users/{user}/password', [UserController::class, 'updatePassword'])
        ->name('users.password.update');

    Route::patch('/users/{user}/activate', [UserController::class, 'activate'])
        ->name('users.activate');

    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->name('users.deactivate');


    Route::get('/form-links/{formLink}/preview', [FormLinkController::class, 'preview'])
        ->name('form-links.preview');

    Route::get('/form-links/{formLink}/inline/{filename}', [FormLinkController::class, 'inline'])
        ->where('filename', '.*')
        ->name('form-links.inline.file');

    Route::get('/form-links/{formLink}/inline', [FormLinkController::class, 'inline'])
        ->name('form-links.inline');

    Route::get('/form-links/{formLink}/download', [FormLinkController::class, 'download'])
        ->name('form-links.download');

    Route::get('/form-links/{formLink}/print', [FormLinkController::class, 'print'])
        ->name('form-links.print');

    Route::resource('form-links', FormLinkController::class)
        ->except(['show'])
        ->parameters([
            'form-links' => 'formLink',
        ]);

    Route::resource('contacts', ContactController::class)->except(['show']);

    Route::resource('message-templates', MessageTemplateController::class)
        ->except(['show'])
        ->parameters([
            'message-templates' => 'messageTemplate',
        ]);

    Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
    Route::get('/emails/compose', [EmailController::class, 'compose'])->name('emails.compose');
    Route::post('/emails/send', [EmailController::class, 'send'])->name('emails.send');
    Route::get('/emails/{emailMessage}', [EmailController::class, 'show'])->name('emails.show');
    Route::get('/documents/{document}/send-email', [EmailController::class, 'composeDocument'])->name('documents.email.compose');
    Route::get('/memos/{memo}/send-email', [EmailController::class, 'composeMemo'])->name('memos.email.compose');

    Route::get('/whatsapp', [WhatsappController::class, 'index'])->name('whatsapp.index');
    Route::get('/whatsapp/compose', [WhatsappController::class, 'compose'])->name('whatsapp.compose');
    Route::post('/whatsapp/send', [WhatsappController::class, 'send'])->name('whatsapp.send');
    Route::get('/whatsapp/{whatsappMessage}', [WhatsappController::class, 'show'])->name('whatsapp.show');
    Route::get('/documents/{document}/send-whatsapp', [WhatsappController::class, 'composeDocument'])->name('documents.whatsapp.compose');
    Route::get('/memos/{memo}/send-whatsapp', [WhatsappController::class, 'composeMemo'])->name('memos.whatsapp.compose');

    Route::resource('users', UserController::class)->except(['show']);

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
Route::get('/documents/next-reference-number', [DocumentController::class, 'nextReferenceNumber'])->name('documents.next-reference-number');
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
Route::get('/data-quality/print', [\App\Http\Controllers\DataQualityPrintController::class, 'index'])->middleware(['auth', ApplyRoutePermissions::class])->name('data-quality.print');

// Professional printable documents report
Route::get('/reports/print', [\App\Http\Controllers\ReportPrintController::class, 'index'])->middleware(['auth', ApplyRoutePermissions::class])->name('reports.print');

// notifications-polish-routes:start
Route::middleware(['auth', ApplyRoutePermissions::class])->group(function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');

    Route::post('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read_all');
    Route::post('/notifications/read-all-legacy', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    Route::post('/notifications/hide-read', [NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');

    Route::post('/notifications/delete-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');

    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'read'])->whereNumber('notification')->name('notifications.read');
    Route::post('/notifications/{notification}/mark-read', [NotificationCenterController::class, 'markAsRead'])->whereNumber('notification')->name('notifications.mark-read');
    Route::post('/notifications/{notification}/hide', [NotificationCenterController::class, 'hide'])->whereNumber('notification')->name('notifications.hide');
    Route::delete('/notifications/{notification}', [NotificationCenterController::class, 'destroy'])->whereNumber('notification')->name('notifications.destroy');

    Route::get('/notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
    Route::post('/notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
});
// notifications-polish-routes:end
