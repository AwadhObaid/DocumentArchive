<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRoutePermissions
{
    /**
     * Route-name pattern => permission key.
     * Keep this centralized so hiding menu items and direct URL protection stay aligned.
     */
    private array $permissions = [
        'dashboard' => 'dashboard.view',
        'profile.*' => 'profile.manage',

        'documents.index' => 'documents.view',
        'documents.show' => 'documents.view',
        'documents.create' => 'documents.create',
        'documents.store' => 'documents.create',
        'documents.edit' => 'documents.edit',
        'documents.update' => 'documents.edit',
        'documents.destroy' => 'documents.delete',
        'documents.trash' => ['documents.restore', 'memos.restore'],
        'documents.restore' => 'documents.restore',
        'documents.force-delete' => 'documents.force_delete',
        'documents.print-reference' => 'documents.print',
        'documents.activity' => 'activity_logs.view',
        'documents.check-policy-duplicate' => 'documents.create',
        'documents.next-reference-number' => 'documents.create',

        'documents.workflow.submit' => 'workflow.submit',
        'documents.workflow.approve' => 'workflow.approve',
        'documents.workflow.reject' => 'workflow.reject',
        'documents.workflow.return' => 'workflow.reject',
        'documents.workflow.finalize' => 'workflow.finalize',
        'documents.workflow.reopen' => 'workflow.override',

        'memos.index' => 'memos.view',
        'memos.show' => 'memos.view',
        'memos.create' => 'memos.create',
        'memos.store' => 'memos.create',
        'memos.edit' => 'memos.edit',
        'memos.update' => 'memos.edit',
        'memos.destroy' => 'memos.delete',
        'memos.restore' => ['documents.restore', 'memos.restore'],
        'memos.force-delete' => 'memos.force_delete',
        'memos.attachments.preview' => 'memos.attachments',
        'memos.attachments.data' => 'memos.attachments',
        'memos.attachments.inline' => 'memos.attachments',
        'memos.attachments.download' => 'memos.attachments',

        'memos.workflow.submit' => 'workflow.submit',
        'memos.workflow.approve' => 'workflow.approve',
        'memos.workflow.reject' => 'workflow.reject',
        'memos.workflow.return' => 'workflow.reject',
        'memos.workflow.finalize' => 'workflow.finalize',
        'memos.workflow.reopen' => 'workflow.override',

        'attachments.preview' => 'attachments.preview',
        'attachments.data' => 'attachments.preview',
        'attachments.inline' => 'attachments.preview',
        'attachments.download' => 'attachments.download',

        'shared-attachment-links.index' => 'attachment_shares.view',
        'shared-attachment-links.create' => 'attachment_shares.create',
        'shared-attachment-links.store' => 'attachment_shares.create',
        'shared-attachment-links.show' => 'attachment_shares.view',
        'shared-attachment-links.revoke' => 'attachment_shares.revoke',
        'shared-attachment-links.destroy' => 'attachment_shares.revoke',
        'documents.shared-attachments.create' => 'attachment_shares.create',

        'activity-logs.*' => 'activity_logs.view',

        'internal-chat.bootstrap' => 'internal_chat.view',
        'internal-chat.users' => 'internal_chat.view',
        'internal-chat.poll' => 'internal_chat.view',
        'internal-chat.messages' => 'internal_chat.view',
        'internal-chat.send' => 'internal_chat.send',
        'internal-chat.typing' => 'internal_chat.send',
        'internal-chat.read' => 'internal_chat.view',


        'internal-messages.index' => 'internal_messages.view',
        'internal-messages.create' => 'internal_messages.send',
        'internal-messages.store' => 'internal_messages.send',
        'internal-messages.show' => 'internal_messages.view',
        'internal-messages.archive' => 'internal_messages.view',
        'internal-messages.attachments.inline' => 'internal_messages.view',
        'internal-messages.attachments.download' => 'internal_messages.view',
        'documents.internal-message.create' => 'internal_messages.send',
        'memos.internal-message.create' => 'internal_messages.send',

        'reports.index' => 'reports.view',
        'reports.print' => 'reports.view',
        'reports.export' => 'reports.export',
        'reports.pdf' => 'reports.export',
        'data-quality.index' => 'data_quality.view',
        'data-quality.print' => 'data_quality.view',

        'departments.*' => 'departments.manage',
        'document-types.*' => 'document_types.manage',
        'book-subjects.*' => 'book_subjects.manage',

        'form-links.index' => 'form_links.view',
        'form-links.preview' => 'form_links.view',
        'form-links.inline' => 'form_links.view',
        'form-links.download' => 'form_links.view',
        'form-links.print' => 'form_links.view',
        'form-links.create' => 'form_links.manage',
        'form-links.store' => 'form_links.manage',
        'form-links.edit' => 'form_links.manage',
        'form-links.update' => 'form_links.manage',
        'form-links.destroy' => 'form_links.manage',

        'contacts.index' => 'contacts.view',
        'contacts.create' => 'contacts.manage',
        'contacts.store' => 'contacts.manage',
        'contacts.edit' => 'contacts.manage',
        'contacts.update' => 'contacts.manage',
        'contacts.destroy' => 'contacts.manage',

        'message-templates.index' => 'message_templates.view',
        'message-templates.create' => 'message_templates.manage',
        'message-templates.store' => 'message_templates.manage',
        'message-templates.edit' => 'message_templates.manage',
        'message-templates.update' => 'message_templates.manage',
        'message-templates.destroy' => 'message_templates.manage',

        'emails.index' => 'emails.view',
        'emails.compose' => 'emails.send',
        'emails.send' => 'emails.send',
        'emails.show' => 'emails.view',
        'documents.email.compose' => 'emails.send',

        'whatsapp.index' => 'whatsapp.view',
        'whatsapp.compose' => 'whatsapp.send',
        'whatsapp.send' => 'whatsapp.send',
        'whatsapp.show' => 'whatsapp.view',
        'documents.whatsapp.compose' => 'whatsapp.send',

        'smart-reports.index' => 'smart_reports.view',
        'smart-reports.show' => 'smart_reports.view',
        'smart-reports.generate' => 'smart_reports.generate',
        'smart-reports.test-gemini' => 'smart_reports.settings',
        'smart-reports.export-word' => 'smart_reports.export',
        'smart-reports.export-pdf' => 'smart_reports.export',

        'settings.internal-chat.backup' => 'internal_chat.backup',
        'settings.internal-chat.restore-backup' => 'internal_chat.restore_backup',
        'settings.internal-chat.restore-deleted' => 'internal_chat.restore_deleted',
        'settings.internal-chat.purge-deleted' => 'internal_chat.force_delete',
        'settings.*' => 'settings.manage',
        'system-health.*' => 'system_health.view',
        'system-rights.*' => 'system_about.view',
        'pdf-search.index' => 'pdf_search.view',
        'pdf-search.run' => 'pdf_search.index',
        'pdf-search.reindex' => 'pdf_search.index',

        'users.*' => 'users.manage',

        'backups.index' => 'backups.view',
        'backups.inspect' => 'backups.view',
        'backups.download' => 'backups.download',
        'backups.database' => 'backups.create',
        'backups.files' => 'backups.create',
        'backups.full' => 'backups.create',
        'backups.destroy' => 'backups.delete',
        'backups.restore' => 'backups.restore',
        'backups.restore.database' => 'backups.restore',
        'backups.restore.files' => 'backups.restore',
        'backups.restore.full' => 'backups.restore',

        'notifications.index' => 'notifications.view',
        'notifications.read' => 'notifications.view',
        'notifications.read_all' => 'notifications.view',
        'notifications.read-all' => 'notifications.view',
        'notifications.mark-all-read' => 'notifications.view',
        'notifications.hide-read' => 'notifications.view',
        'notifications.hide' => 'notifications.view',
        'notifications.destroy' => 'notifications.manage',
        'notifications.delete-hidden' => 'notifications.manage',
        'notifications.purge-hidden' => 'notifications.manage',
        'notifications.clear-hidden' => 'notifications.manage',
        'notification-settings.*' => 'notifications.manage',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();
        $routeName = $route?->getName();

        if (!$user || !$routeName) {
            return $next($request);
        }

        $permission = $this->permissionFor($routeName);

        if ($permission && method_exists($user, 'hasPermission') && ! $this->userHasAnyPermission($user, $permission)) {
            abort(403, 'ليست لديك صلاحية الوصول إلى هذه الصفحة أو تنفيذ هذا الإجراء.');
        }

        return $next($request);
    }

    private function userHasAnyPermission($user, string|array $permission): bool
    {
        $permissions = is_array($permission) ? $permission : [$permission];

        foreach ($permissions as $item) {
            if ($user->hasPermission($item)) {
                return true;
            }
        }

        return false;
    }

    private function permissionFor(string $routeName): string|array|null
    {
        foreach ($this->permissions as $pattern => $permission) {
            if ($pattern === $routeName) {
                return $permission;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                if ($routeName === $prefix || str_starts_with($routeName, $prefix . '.')) {
                    return $permission;
                }
            }
        }

        return null;
    }
}
