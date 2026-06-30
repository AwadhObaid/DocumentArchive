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
        'documents.trash' => 'documents.restore',
        'documents.restore' => 'documents.restore',
        'documents.force-delete' => 'documents.delete',
        'documents.print-reference' => 'documents.print',
        'documents.activity' => 'activity_logs.view',
        'documents.check-policy-duplicate' => 'documents.create',

        'attachments.preview' => 'attachments.preview',
        'attachments.data' => 'attachments.preview',
        'attachments.inline' => 'attachments.preview',
        'attachments.download' => 'attachments.download',

        'activity-logs.*' => 'activity_logs.view',

        'reports.index' => 'reports.view',
        'reports.print' => 'reports.view',
        'reports.export' => 'reports.export',
        'reports.pdf' => 'reports.export',
        'data-quality.index' => 'data_quality.view',
        'data-quality.print' => 'data_quality.view',

        'departments.*' => 'departments.manage',
        'document-types.*' => 'document_types.manage',

        'settings.*' => 'settings.manage',
        'system-health.*' => 'system_health.view',

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

        if ($permission && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            abort(403, 'ليست لديك صلاحية الوصول إلى هذه الصفحة أو تنفيذ هذا الإجراء.');
        }

        return $next($request);
    }

    private function permissionFor(string $routeName): ?string
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