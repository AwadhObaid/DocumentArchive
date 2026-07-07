<?php

use App\Services\PdfTextIndexingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('archive:index-pdfs {--source=all : all|documents|memos} {--limit=50 : عدد الملفات في التشغيل الواحد} {--force : إعادة فهرسة الملفات المفهرسة سابقاً} {--ocr : تشغيل OCR للملفات التي لا تحتوي نصاً قابلاً للنسخ}', function () {
    $source = (string) $this->option('source');
    $limit = (int) $this->option('limit');
    $force = (bool) $this->option('force');
    $ocr = (bool) $this->option('ocr');

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
})->purpose('فهرسة نصوص مرفقات PDF للكتب والمذكرات مع OCR اختياري');
