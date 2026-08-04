<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EmailSettingsService
{
    private const GROUP = 'email';

    private const DEFAULTS = [
        'email_enabled' => '0',
        'email_smtp_host' => 'smtp.gmail.com',
        'email_smtp_port' => '587',
        'email_smtp_security' => 'tls',
        'email_smtp_username' => '',
        'email_from_address' => '',
        'email_from_name' => 'نظام الأرشيف الإلكتروني',
        'email_timeout' => '30',
    ];

    public function settingsForView(): array
    {
        $resolved = $this->resolvedSettings(false);
        $storedPassword = $this->storedPasswordValue();
        $environmentPassword = trim((string) config('mail.mailers.smtp.password', ''));
        $databaseConfigured = $this->databaseSettingsExist();

        return array_merge($resolved, [
            'source' => $databaseConfigured ? 'database' : 'environment',
            'source_label' => $databaseConfigured ? 'قاعدة البيانات' : 'ملف .env الاحتياطي',
            'password_configured' => $storedPassword !== '' || $environmentPassword !== '',
            'password_source' => $storedPassword !== ''
                ? 'database'
                : ($environmentPassword !== '' ? 'environment' : 'none'),
            'password_source_label' => $storedPassword !== ''
                ? 'محفوظة ومشفرة في قاعدة البيانات'
                : ($environmentPassword !== '' ? 'متاحة من ملف .env فقط' : 'غير محفوظة'),
            'last_test_status' => (string) Setting::getValue('email_last_test_status', ''),
            'last_test_at' => (string) Setting::getValue('email_last_test_at', ''),
            'last_test_message' => (string) Setting::getValue('email_last_test_message', ''),
        ]);
    }

    public function save(array $values): array
    {
        $before = $this->settingsForView();

        $normalized = [
            'email_enabled' => ! empty($values['email_enabled']) ? '1' : '0',
            'email_smtp_host' => trim((string) ($values['email_smtp_host'] ?? '')),
            'email_smtp_port' => (string) max(1, min(65535, (int) ($values['email_smtp_port'] ?? 587))),
            'email_smtp_security' => in_array(($values['email_smtp_security'] ?? 'tls'), ['tls', 'ssl', 'none'], true)
                ? (string) $values['email_smtp_security']
                : 'tls',
            'email_smtp_username' => trim((string) ($values['email_smtp_username'] ?? '')),
            'email_from_address' => trim((string) ($values['email_from_address'] ?? '')),
            'email_from_name' => trim((string) ($values['email_from_name'] ?? '')) ?: 'نظام الأرشيف الإلكتروني',
            'email_timeout' => (string) max(5, min(120, (int) ($values['email_timeout'] ?? 30))),
        ];

        $definitions = [
            'email_enabled' => ['boolean', 'تفعيل إرسال البريد الإلكتروني عبر SMTP'],
            'email_smtp_host' => ['text', 'عنوان خادم SMTP'],
            'email_smtp_port' => ['number', 'منفذ خادم SMTP'],
            'email_smtp_security' => ['text', 'نوع حماية اتصال SMTP'],
            'email_smtp_username' => ['text', 'اسم مستخدم SMTP'],
            'email_from_address' => ['text', 'عنوان البريد المرسل'],
            'email_from_name' => ['text', 'اسم المرسل الظاهر للمستلمين'],
            'email_timeout' => ['number', 'مهلة اتصال SMTP بالثواني'],
        ];

        foreach ($definitions as $key => [$type, $description]) {
            Setting::setValue($key, $normalized[$key], self::GROUP, $type, $description);
        }

        $passwordInput = $this->normalizePasswordInput((string) ($values['email_smtp_password'] ?? ''));
        $clearPassword = ! empty($values['email_clear_password']);
        $importEnvironmentPassword = ! empty($values['email_import_env_password']);

        if ($clearPassword) {
            Setting::setValue(
                'email_smtp_password',
                '',
                self::GROUP,
                'password',
                'كلمة مرور SMTP مشفرة؛ القيمة الفارغة تعني استخدام إعداد .env الاحتياطي إن وجد'
            );
        } elseif ($passwordInput !== '') {
            Setting::setValue(
                'email_smtp_password',
                Crypt::encryptString($passwordInput),
                self::GROUP,
                'password',
                'كلمة مرور SMTP محفوظة بشكل مشفر'
            );
        } elseif ($importEnvironmentPassword && trim((string) config('mail.mailers.smtp.password', '')) !== '') {
            Setting::setValue(
                'email_smtp_password',
                Crypt::encryptString($this->normalizePasswordInput((string) config('mail.mailers.smtp.password'))),
                self::GROUP,
                'password',
                'كلمة مرور SMTP منقولة من إعداد .env ومحفوظة بشكل مشفر'
            );
        }

        Setting::setValue('email_settings_version', '95.3', self::GROUP, 'text', 'إصدار إعدادات البريد الإلكتروني');

        $after = $this->settingsForView();

        return [
            'before' => $this->safeAuditValues($before),
            'after' => $this->safeAuditValues($after),
            'changed_keys' => $this->changedKeys(
                $this->safeAuditValues($before),
                $this->safeAuditValues($after)
            ),
            'password_changed' => $clearPassword || $passwordInput !== '' || $importEnvironmentPassword,
        ];
    }

    public function apply(): array
    {
        $settings = $this->resolvedSettings(true);

        if ((string) $settings['email_enabled'] !== '1') {
            throw new \RuntimeException('إرسال البريد الإلكتروني معطل من إعدادات النظام.');
        }

        $host = trim((string) $settings['email_smtp_host']);
        $fromAddress = trim((string) $settings['email_from_address']);
        $username = trim((string) $settings['email_smtp_username']);
        $password = $this->resolvedPassword();

        if ($host === '') {
            throw new \RuntimeException('خادم SMTP غير محدد في إعدادات البريد الإلكتروني.');
        }

        if ($fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('عنوان البريد المرسل غير صحيح في إعدادات البريد الإلكتروني.');
        }

        if ($username !== '' && $password === '') {
            throw new \RuntimeException('كلمة مرور SMTP غير محفوظة. افتح إعدادات البريد وأدخل كلمة مرور التطبيق.');
        }

        $security = (string) $settings['email_smtp_security'];
        $scheme = $security === 'ssl' ? 'smtps' : null;
        $timeout = max(5, min(120, (int) $settings['email_timeout']));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $settings['email_smtp_port'],
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.timeout' => $timeout,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => (string) $settings['email_from_name'],
        ]);

        // The mail manager may already have created the SMTP transport earlier
        // in the request. Purging it guarantees that the database settings are used.
        try {
            app('mail.manager')->purge('smtp');
        } catch (Throwable $exception) {
            // A fresh request normally has no cached SMTP mailer. Nothing to purge.
        }

        return array_merge($settings, [
            'timeout' => $timeout,
            'password_source' => $this->storedPasswordValue() !== '' ? 'database' : 'environment',
        ]);
    }

    public function recordTestResult(bool $success, string $message): void
    {
        Setting::setValue('email_last_test_status', $success ? 'success' : 'failed', self::GROUP, 'text', 'نتيجة آخر اختبار للبريد');
        Setting::setValue('email_last_test_at', now()->format('Y-m-d H:i:s'), self::GROUP, 'datetime', 'وقت آخر اختبار للبريد');
        Setting::setValue(
            'email_last_test_message',
            mb_substr(trim($message), 0, 1000, 'UTF-8'),
            self::GROUP,
            'text',
            'رسالة نتيجة آخر اختبار للبريد'
        );
    }

    public function friendlyError(Throwable $exception): string
    {
        $message = trim((string) $exception->getMessage());
        $normalized = strtolower($message);

        if (str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout') || str_contains($normalized, 'time out')) {
            return 'انتهت مهلة الاتصال بخادم البريد دون استجابة. تحقق من الخادم والمنفذ والاتصال بالشبكة.';
        }

        if (str_contains($normalized, 'authentication') || str_contains($normalized, 'authenticate') || str_contains($normalized, '535')) {
            return 'رفض خادم البريد بيانات تسجيل الدخول. تحقق من اسم المستخدم وكلمة مرور التطبيق.';
        }

        if (str_contains($normalized, 'connection refused') || str_contains($normalized, 'could not connect') || str_contains($normalized, 'failed to connect')) {
            return 'تعذر الاتصال بخادم البريد. تحقق من عنوان SMTP والمنفذ والجدار الناري.';
        }

        if (str_contains($normalized, 'certificate') || str_contains($normalized, 'ssl') || str_contains($normalized, 'tls')) {
            return 'فشل إنشاء الاتصال الآمن مع خادم البريد. تحقق من نوع الحماية والمنفذ.';
        }

        if ($message === '') {
            return 'فشل إرسال البريد لسبب غير محدد. راجع سجل Laravel للمزيد من التفاصيل.';
        }

        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?: $message;

        return 'فشل إرسال البريد: ' . mb_substr($message, 0, 450, 'UTF-8');
    }

    private function resolvedSettings(bool $strictDatabasePriority): array
    {
        $hasDatabaseSettings = $this->databaseSettingsExist();
        $configPort = (int) config('mail.mailers.smtp.port', 587);
        $configScheme = strtolower((string) config('mail.mailers.smtp.scheme', ''));

        $environmentFallbacks = [
            'email_enabled' => config('mail.default') === 'smtp' ? '1' : self::DEFAULTS['email_enabled'],
            'email_smtp_host' => (string) config('mail.mailers.smtp.host', self::DEFAULTS['email_smtp_host']),
            'email_smtp_port' => (string) ($configPort > 0 ? $configPort : 587),
            'email_smtp_security' => $configScheme === 'smtps'
                ? 'ssl'
                : (($configPort === 465) ? 'ssl' : 'tls'),
            'email_smtp_username' => (string) config('mail.mailers.smtp.username', ''),
            'email_from_address' => (string) config('mail.from.address', ''),
            'email_from_name' => (string) config('mail.from.name', self::DEFAULTS['email_from_name']),
            'email_timeout' => (string) max(5, min(120, (int) config('mail.mailers.smtp.timeout', 30))),
        ];

        $resolved = [];

        foreach (self::DEFAULTS as $key => $default) {
            $fallback = $environmentFallbacks[$key] ?? $default;
            $resolved[$key] = $hasDatabaseSettings
                ? Setting::getValue($key, $fallback)
                : $fallback;
        }

        $resolved['email_smtp_port'] = (string) max(1, min(65535, (int) $resolved['email_smtp_port']));
        $resolved['email_timeout'] = (string) max(5, min(120, (int) $resolved['email_timeout']));
        $resolved['email_smtp_security'] = in_array($resolved['email_smtp_security'], ['tls', 'ssl', 'none'], true)
            ? $resolved['email_smtp_security']
            : 'tls';

        return $resolved;
    }

    private function resolvedPassword(): string
    {
        $stored = $this->storedPasswordValue();

        if ($stored !== '') {
            try {
                return $this->normalizePasswordInput(Crypt::decryptString($stored));
            } catch (Throwable $exception) {
                throw new \RuntimeException(
                    'تعذر فك تشفير كلمة مرور SMTP. تحقق من APP_KEY أو أعد حفظ كلمة المرور من إعدادات البريد.'
                );
            }
        }

        return $this->normalizePasswordInput((string) config('mail.mailers.smtp.password', ''));
    }

    private function normalizePasswordInput(string $password): string
    {
        $password = trim($password);

        // Google displays app passwords as four groups. Remove only this
        // presentation whitespace; preserve spaces for other SMTP providers.
        if (preg_match('/^(?:[A-Za-z0-9]{4}\s+){3}[A-Za-z0-9]{4}$/', $password) === 1) {
            return preg_replace('/\s+/', '', $password) ?: $password;
        }

        return $password;
    }

    private function storedPasswordValue(): string
    {
        return trim((string) Setting::getValue('email_smtp_password', ''));
    }

    private function databaseSettingsExist(): bool
    {
        try {
            return Setting::query()
                ->whereIn('key', ['email_enabled', 'email_smtp_host', 'email_from_address'])
                ->exists();
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function safeAuditValues(array $settings): array
    {
        return [
            'email_enabled' => (string) ($settings['email_enabled'] ?? '0'),
            'email_smtp_host' => (string) ($settings['email_smtp_host'] ?? ''),
            'email_smtp_port' => (string) ($settings['email_smtp_port'] ?? ''),
            'email_smtp_security' => (string) ($settings['email_smtp_security'] ?? ''),
            'email_smtp_username' => (string) ($settings['email_smtp_username'] ?? ''),
            'email_from_address' => (string) ($settings['email_from_address'] ?? ''),
            'email_from_name' => (string) ($settings['email_from_name'] ?? ''),
            'email_timeout' => (string) ($settings['email_timeout'] ?? ''),
            'password_configured' => ! empty($settings['password_configured']) ? '1' : '0',
            'password_source' => (string) ($settings['password_source'] ?? 'none'),
        ];
    }

    private function changedKeys(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ((string) $oldValue !== (string) $newValue) {
                $changed[$key] = [
                    'old' => is_scalar($oldValue) ? (string) $oldValue : null,
                    'new' => is_scalar($newValue) ? (string) $newValue : null,
                ];
            }
        }

        return $changed;
    }
}
