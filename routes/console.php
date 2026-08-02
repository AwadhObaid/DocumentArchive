<?php

use App\Services\PdfTextIndexingService;
use App\Services\FileBridgeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('archive:index-pdfs {--source=all : all|documents|memos|circulars|misc_books} {--limit=50 : عدد الملفات في التشغيل الواحد} {--force : إعادة فهرسة الملفات المفهرسة سابقاً} {--ocr : تشغيل OCR للملفات التي لا تحتوي نصاً قابلاً للنسخ}', function () {
    $source = (string) $this->option('source');
    $limit = (int) $this->option('limit');
    $force = (bool) $this->option('force');
    $ocr = (bool) $this->option('ocr');

    $allowedSources = ['all', 'documents', 'memos', 'circulars', 'misc_books'];

    if (! in_array($source, $allowedSources, true)) {
        $this->error('مصدر الفهرسة غير صحيح. القيم المتاحة: ' . implode(', ', $allowedSources));

        return 1;
    }

    /** @var PdfTextIndexingService $service */
    $service = app(PdfTextIndexingService::class);

    $this->info('بدء فهرسة مرفقات PDF...');
    $summary = $service->indexAll($source, $limit, $force, $ocr);

    $this->table(
        ['البند', 'العدد'],
        [
            ['المعالجة', $summary['processed']],
            ['مفهرس', $summary['indexed']],
            ['يحتاج OCR', $summary['needs_ocr']],
            ['فشل', $summary['failed']],
            ['مفقود', $summary['missing']],
            ['متجاوز', $summary['skipped']],
        ]
    );

    $this->info('انتهت الفهرسة.');
})->purpose('فهرسة نصوص مرفقات PDF للكتب والمذكرات والتعاميم والكتب المتفرقة مع OCR اختياري');


// file-bridge-v94-2-console:start
Artisan::command('file-bridge:purge', function () {
    $count = app(FileBridgeService::class)->purgeExpired();
    $this->info('Expired File Bridge requests purged: ' . $count);
})->purpose('حذف طلبات وملفات File Bridge المؤقتة المنتهية');
// file-bridge-v94-2-console:end
