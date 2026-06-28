<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class QrViewDefaultsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // لا شيء هنا حالياً.
    }

    public function boot(): void
    {
        /**
         * قيم احتياطية تمنع تعطل أي صفحة وصلها أثر قديم من QR.
         * القيمة الافتراضية: QR مخفي في كل الصفحات العامة.
         * صفحة طباعة رقم الكتاب هي وحدها التي يجب أن تضبط القيم الحقيقية محلياً.
         */
        View::share('daQrUrl', '');
        View::share('daQrDocument', (object) [
            'id' => 0,
            'reference_number' => '',
        ]);
        View::share('daQrSettings', [
            'show_qr' => '0',
            'show_label' => '0',
            'x' => '55',
            'y' => '48',
            'size' => '16',
            'padding' => '1',
            'label_font_size' => '7',
        ]);
        View::share('daQrEnabled', false);
        View::share('daQrVisible', false);
        View::share('daQrPrintOnly', true);
    }
}