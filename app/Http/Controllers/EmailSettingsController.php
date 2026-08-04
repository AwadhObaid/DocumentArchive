<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\EmailSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    public function edit(EmailSettingsService $service): View
    {
        return view('settings.email', [
            'settings' => $service->settingsForView(),
        ]);
    }

    public function update(Request $request, EmailSettingsService $service): RedirectResponse
    {
        $enabled = $request->boolean('email_enabled');

        $validated = $request->validate([
            'email_enabled' => ['nullable', 'boolean'],
            'email_smtp_host' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'email_smtp_port' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'min:1', 'max:65535'],
            'email_smtp_security' => [Rule::requiredIf($enabled), 'nullable', Rule::in(['tls', 'ssl', 'none'])],
            'email_smtp_username' => ['nullable', 'string', 'max:255'],
            'email_smtp_password' => ['nullable', 'string', 'max:1000'],
            'email_clear_password' => ['nullable', 'boolean'],
            'email_import_env_password' => ['nullable', 'boolean'],
            'email_from_address' => [Rule::requiredIf($enabled), 'nullable', 'email:rfc', 'max:255'],
            'email_from_name' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'email_timeout' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'min:5', 'max:120'],
        ], [
            'email_smtp_host.required' => 'خادم SMTP مطلوب عند تفعيل البريد الإلكتروني.',
            'email_smtp_port.required' => 'منفذ SMTP مطلوب عند تفعيل البريد الإلكتروني.',
            'email_smtp_security.in' => 'نوع الحماية المحدد غير مدعوم.',
            'email_from_address.required' => 'عنوان البريد المرسل مطلوب عند تفعيل البريد الإلكتروني.',
            'email_from_address.email' => 'عنوان البريد المرسل غير صحيح.',
            'email_from_name.required' => 'اسم المرسل مطلوب عند تفعيل البريد الإلكتروني.',
            'email_timeout.min' => 'مهلة الاتصال يجب ألا تقل عن 5 ثوانٍ.',
            'email_timeout.max' => 'مهلة الاتصال يجب ألا تزيد عن 120 ثانية.',
        ]);

        $validated['email_enabled'] = $enabled ? '1' : '0';
        $validated['email_clear_password'] = $request->boolean('email_clear_password');
        $validated['email_import_env_password'] = $request->boolean('email_import_env_password');

        $result = $service->save($validated);

        ActivityLogger::log(
            'email.settings.updated',
            'تم تعديل إعدادات البريد الإلكتروني.',
            null,
            [
                'changed_keys' => $result['changed_keys'],
                'password_changed' => $result['password_changed'],
            ]
        );

        return redirect()
            ->route('settings.email.edit')
            ->with('success', 'تم حفظ إعدادات البريد الإلكتروني بنجاح.');
    }

    public function test(Request $request, EmailSettingsService $service): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'test_email.required' => 'أدخل البريد الذي تريد إرسال رسالة الاختبار إليه.',
            'test_email.email' => 'عنوان بريد الاختبار غير صحيح.',
        ]);

        $previousSocketTimeout = ini_get('default_socket_timeout');

        try {
            $runtime = $service->apply();
            @ini_set('default_socket_timeout', (string) $runtime['timeout']);

            $sentAt = now()->format('Y-m-d H:i:s');
            $host = (string) $runtime['email_smtp_host'];
            $port = (string) $runtime['email_smtp_port'];

            Mail::raw(
                "هذه رسالة اختبار من نظام الأرشيف الإلكتروني.\n\n"
                . "وقت الاختبار: {$sentAt}\n"
                . "الخادم: {$host}:{$port}\n\n"
                . "تم إرسال الرسالة من صفحة إعدادات البريد الإلكتروني.",
                function ($message) use ($validated): void {
                    $message
                        ->to($validated['test_email'])
                        ->subject('اختبار إعدادات البريد - نظام الأرشيف الإلكتروني');
                }
            );

            $service->recordTestResult(true, 'تم إرسال رسالة الاختبار بنجاح إلى ' . $validated['test_email']);

            ActivityLogger::log(
                'email.settings.test_succeeded',
                'نجح اختبار إعدادات البريد الإلكتروني.',
                null,
                [
                    'test_email' => $validated['test_email'],
                    'host' => $host,
                    'port' => $port,
                ]
            );

            return redirect()
                ->route('settings.email.edit')
                ->with('success', 'تم إرسال رسالة الاختبار بنجاح. تحقق من البريد الوارد ومجلد الرسائل غير المرغوب فيها.');
        } catch (\Throwable $exception) {
            report($exception);
            $friendly = $service->friendlyError($exception);
            $service->recordTestResult(false, $friendly);

            ActivityLogger::log(
                'email.settings.test_failed',
                'فشل اختبار إعدادات البريد الإلكتروني.',
                null,
                [
                    'test_email' => $validated['test_email'],
                    'error' => $exception->getMessage(),
                ]
            );

            return redirect()
                ->route('settings.email.edit')
                ->withErrors(['test_email' => $friendly]);
        } finally {
            if ($previousSocketTimeout !== false && $previousSocketTimeout !== '') {
                @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
            }
        }
    }
}
