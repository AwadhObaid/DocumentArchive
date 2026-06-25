<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthController extends Controller
{
    public function index()
    {
        $checks = $this->buildChecks();

        $summary = [
            'ok' => collect($checks)->where('status', 'ok')->count(),
            'warning' => collect($checks)->where('status', 'warning')->count(),
            'error' => collect($checks)->where('status', 'error')->count(),
        ];

        return view('system-health.index', compact('checks', 'summary'));
    }

    private function buildChecks(): array
    {
        $checks = [];

        $checks[] = $this->checkDatabaseConnection();
        $checks[] = $this->checkDatabaseDriver();
        $checks[] = $this->checkRequiredTables();
        $checks[] = $this->checkAdminUser();
        $checks[] = $this->checkPermissionsColumn();
        $checks[] = $this->checkDocumentsStorage();
        $checks[] = $this->checkBackupsStorage();
        $checks[] = $this->checkZipExtension();
        $checks[] = $this->checkSessionDriver();
        $checks[] = $this->checkCacheDriver();
        $checks[] = $this->checkQueueDriver();
        $checks[] = $this->checkAppKey();
        $checks[] = $this->checkAppDebug();
        $checks[] = $this->checkBasicDataCounts();

        return $checks;
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::select('select 1 as ok');

            return $this->ok('اتصال قاعدة البيانات', 'الاتصال بقاعدة البيانات يعمل بنجاح.');
        } catch (Throwable $exception) {
            return $this->error('اتصال قاعدة البيانات', 'فشل الاتصال بقاعدة البيانات: ' . $exception->getMessage());
        }
    }

    private function checkDatabaseDriver(): array
    {
        $driver = config('database.default');

        if ($driver === 'mysql') {
            return $this->ok('نوع قاعدة البيانات', 'DB_CONNECTION=mysql وهذا هو الإعداد المطلوب للنظام حالياً.');
        }

        return $this->error('نوع قاعدة البيانات', "الإعداد الحالي DB_CONNECTION={$driver}. يجب أن يكون mysql لتجنب مشاكل SQLite القديمة.");
    }

    private function checkRequiredTables(): array
    {
        $required = [
            'users',
            'departments',
            'document_types',
            'documents',
            'document_attachments',
            'settings',
            'reference_counters',
            'activity_logs',
            'migrations',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ];

        try {
            $missing = [];
            foreach ($required as $table) {
                if (!Schema::hasTable($table)) {
                    $missing[] = $table;
                }
            }

            if ($missing === []) {
                return $this->ok('جداول قاعدة البيانات', 'كل الجداول الأساسية موجودة وعددها ' . count($required) . '.');
            }

            return $this->error('جداول قاعدة البيانات', 'الجداول الناقصة: ' . implode(', ', $missing));
        } catch (Throwable $exception) {
            return $this->error('جداول قاعدة البيانات', 'تعذر فحص الجداول: ' . $exception->getMessage());
        }
    }

    private function checkAdminUser(): array
    {
        try {
            if (!Schema::hasTable('users')) {
                return $this->error('حساب المدير', 'جدول users غير موجود.');
            }

            $admin = User::query()->where('username', 'admin')->first();

            if (!$admin) {
                return $this->error('حساب المدير', 'حساب admin غير موجود.');
            }

            if (!($admin->is_active ?? false)) {
                return $this->warning('حساب المدير', 'حساب admin موجود لكنه غير مفعل.');
            }

            $role = $admin->role ?? '-';
            return $this->ok('حساب المدير', "حساب admin موجود ومفعل. الدور الحالي: {$role}.");
        } catch (Throwable $exception) {
            return $this->error('حساب المدير', 'تعذر فحص حساب المدير: ' . $exception->getMessage());
        }
    }

    private function checkPermissionsColumn(): array
    {
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
                return $this->ok('الصلاحيات التفصيلية', 'عمود users.permissions موجود.');
            }

            return $this->warning('الصلاحيات التفصيلية', 'عمود users.permissions غير موجود. نفّذ تحديث الصلاحيات أو php artisan migrate.');
        } catch (Throwable $exception) {
            return $this->warning('الصلاحيات التفصيلية', 'تعذر فحص الصلاحيات: ' . $exception->getMessage());
        }
    }

    private function checkDocumentsStorage(): array
    {
        $path = storage_path('app/private/documents');

        if (!is_dir($path)) {
            return $this->warning('مجلد المرفقات', 'المجلد غير موجود حالياً: ' . $path . ' — سيُنشأ عند أول رفع مرفق أو عند الاستعادة.');
        }

        if (!is_writable($path)) {
            return $this->error('مجلد المرفقات', 'المجلد موجود لكنه غير قابل للكتابة: ' . $path);
        }

        return $this->ok('مجلد المرفقات', 'مجلد المرفقات موجود وقابل للكتابة.');
    }

    private function checkBackupsStorage(): array
    {
        $path = storage_path('app/private/backups');

        if (!is_dir($path)) {
            return $this->warning('مجلد النسخ الاحتياطي', 'المجلد غير موجود حالياً: ' . $path . ' — سيُنشأ عند إنشاء أول نسخة احتياطية.');
        }

        if (!is_writable($path)) {
            return $this->error('مجلد النسخ الاحتياطي', 'المجلد موجود لكنه غير قابل للكتابة: ' . $path);
        }

        return $this->ok('مجلد النسخ الاحتياطي', 'مجلد النسخ الاحتياطي موجود وقابل للكتابة.');
    }

    private function checkZipExtension(): array
    {
        if (class_exists('ZipArchive')) {
            return $this->ok('إضافة ZIP في PHP', 'ZipArchive مفعّل ويستطيع النظام إنشاء وفحص النسخ المضغوطة.');
        }

        return $this->error('إضافة ZIP في PHP', 'ZipArchive غير مفعّل. فعّل extension=zip من إعدادات PHP في Laragon.');
    }

    private function checkSessionDriver(): array
    {
        $driver = config('session.driver');

        if ($driver === 'file') {
            return $this->ok('Session Driver', 'SESSION_DRIVER=file وهذا مناسب لتجنب تعطل الجلسات أثناء الاستعادة.');
        }

        return $this->warning('Session Driver', "SESSION_DRIVER={$driver}. الموصى به حالياً: file.");
    }

    private function checkCacheDriver(): array
    {
        $store = config('cache.default');

        if ($store === 'file') {
            return $this->ok('Cache Store', 'CACHE_STORE=file وهذا مناسب للوضع الحالي.');
        }

        return $this->warning('Cache Store', "CACHE_STORE={$store}. الموصى به حالياً: file.");
    }

    private function checkQueueDriver(): array
    {
        $connection = config('queue.default');

        if ($connection === 'sync') {
            return $this->ok('Queue Connection', 'QUEUE_CONNECTION=sync وهذا مناسب للتشغيل المحلي الحالي.');
        }

        return $this->warning('Queue Connection', "QUEUE_CONNECTION={$connection}. الموصى به حالياً: sync.");
    }

    private function checkAppKey(): array
    {
        if ((string) config('app.key') !== '') {
            return $this->ok('APP_KEY', 'مفتاح التطبيق موجود.');
        }

        return $this->error('APP_KEY', 'مفتاح التطبيق غير موجود. نفّذ: php artisan key:generate');
    }

    private function checkAppDebug(): array
    {
        if (config('app.debug')) {
            return $this->warning('APP_DEBUG', 'APP_DEBUG=true مناسب أثناء التطوير، لكن يجب جعله false عند النشر النهائي.');
        }

        return $this->ok('APP_DEBUG', 'APP_DEBUG=false مناسب للإنتاج.');
    }

    private function checkBasicDataCounts(): array
    {
        try {
            $parts = [];

            if (Schema::hasTable('documents')) {
                $parts[] = 'الكتب: ' . DB::table('documents')->count();
            }

            if (Schema::hasTable('document_attachments')) {
                $parts[] = 'المرفقات: ' . DB::table('document_attachments')->count();
            }

            if (Schema::hasTable('departments')) {
                $parts[] = 'الإدارات: ' . DB::table('departments')->count();
            }

            if (Schema::hasTable('document_types')) {
                $parts[] = 'أنواع الكتب: ' . DB::table('document_types')->count();
            }

            if ($parts === []) {
                return $this->warning('ملخص البيانات', 'لم يتم العثور على جداول بيانات الأرشيف الأساسية لعرض الملخص.');
            }

            return $this->ok('ملخص البيانات', implode(' — ', $parts));
        } catch (Throwable $exception) {
            return $this->warning('ملخص البيانات', 'تعذر قراءة ملخص البيانات: ' . $exception->getMessage());
        }
    }

    private function ok(string $title, string $message): array
    {
        return ['status' => 'ok', 'title' => $title, 'message' => $message];
    }

    private function warning(string $title, string $message): array
    {
        return ['status' => 'warning', 'title' => $title, 'message' => $message];
    }

    private function error(string $title, string $message): array
    {
        return ['status' => 'error', 'title' => $title, 'message' => $message];
    }
}
