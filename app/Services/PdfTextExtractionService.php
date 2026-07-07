<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class PdfTextExtractionService
{
    public function extract(string $filePath, bool $enableOcr = false): array
    {
        $native = $this->extractNativeText($filePath);
        $nativeText = $this->cleanText($native['text'] ?? '');
        $textLength = mb_strlen($nativeText, 'UTF-8');

        if ($textLength >= 20) {
            return [
                'status' => 'indexed',
                'extractor' => 'native',
                'text' => $nativeText,
                'needs_ocr' => false,
                'pages_count' => null,
                'error' => null,
            ];
        }

        if (! $enableOcr) {
            return [
                'status' => 'needs_ocr',
                'extractor' => 'none',
                'text' => $nativeText,
                'needs_ocr' => true,
                'pages_count' => null,
                'error' => $native['error'] ?: 'لم يتم العثور على نص قابل للاستخراج داخل PDF. فعّل OCR أو ثبّت أدوات Poppler/Tesseract لمعالجة ملفات السكانر.',
            ];
        }

        $ocr = $this->extractOcrText($filePath);
        $ocrText = $this->cleanText($ocr['text'] ?? '');
        $combined = trim($nativeText . "\n\n" . $ocrText);

        if (mb_strlen($combined, 'UTF-8') > 0) {
            return [
                'status' => 'indexed',
                'extractor' => $textLength > 0 ? 'mixed' : 'ocr',
                'text' => $combined,
                'needs_ocr' => false,
                'pages_count' => $ocr['pages_count'] ?? null,
                'error' => $ocr['error'] ?? null,
            ];
        }

        return [
            'status' => 'failed',
            'extractor' => 'ocr',
            'text' => $nativeText,
            'needs_ocr' => true,
            'pages_count' => $ocr['pages_count'] ?? null,
            'error' => $ocr['error'] ?: 'تعذر استخراج نص من الملف عبر OCR.',
        ];
    }

    public function toolsStatus(): array
    {
        return [
            'pdftotext' => $this->toolStatus($this->setting('pdf_search_pdftotext_path', 'pdftotext'), ['-v']),
            'pdftoppm' => $this->toolStatus($this->setting('pdf_search_pdftoppm_path', 'pdftoppm'), ['-v']),
            'tesseract' => $this->toolStatus($this->setting('pdf_search_tesseract_path', 'tesseract'), ['--version']),
            'ocr_enabled' => (string) Setting::getValue('pdf_search_enable_ocr', '0') === '1',
            'ocr_languages' => $this->setting('pdf_search_ocr_languages', 'ara+eng'),
            'pages_limit' => (int) Setting::getValue('pdf_search_pages_limit', '20'),
        ];
    }

    private function extractNativeText(string $filePath): array
    {
        $pdftotext = $this->setting('pdf_search_pdftotext_path', 'pdftotext');

        try {
            $process = new Process([$pdftotext, '-layout', '-enc', 'UTF-8', $filePath, '-']);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                return ['text' => $process->getOutput(), 'error' => null];
            }

            return [
                'text' => $process->getOutput(),
                'error' => trim($process->getErrorOutput()) ?: 'تعذر تشغيل pdftotext لاستخراج النص.',
            ];
        } catch (\Throwable $exception) {
            return ['text' => '', 'error' => 'تعذر تشغيل pdftotext: ' . $exception->getMessage()];
        }
    }

    private function extractOcrText(string $filePath): array
    {
        $pdftoppm = $this->setting('pdf_search_pdftoppm_path', 'pdftoppm');
        $tesseract = $this->setting('pdf_search_tesseract_path', 'tesseract');
        $languages = $this->setting('pdf_search_ocr_languages', 'ara+eng');
        $pagesLimit = max(1, min(200, (int) Setting::getValue('pdf_search_pages_limit', '20')));
        $workDir = storage_path('app/ocr-temp/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)));

        File::ensureDirectoryExists($workDir);

        try {
            $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
            $convert = new Process([$pdftoppm, '-png', '-r', '200', '-f', '1', '-l', (string) $pagesLimit, $filePath, $prefix]);
            $convert->setTimeout(180);
            $convert->run();

            if (! $convert->isSuccessful()) {
                return [
                    'text' => '',
                    'pages_count' => 0,
                    'error' => trim($convert->getErrorOutput()) ?: 'تعذر تحويل PDF إلى صور بواسطة pdftoppm.',
                ];
            }

            $images = glob($prefix . '-*.png') ?: [];
            natsort($images);
            $images = array_values($images);

            if (count($images) === 0) {
                return ['text' => '', 'pages_count' => 0, 'error' => 'لم يتم إنشاء صور من ملف PDF.'];
            }

            $texts = [];
            foreach ($images as $image) {
                $ocr = new Process([$tesseract, $image, 'stdout', '-l', $languages, '--psm', '6']);
                $ocr->setTimeout(120);
                $ocr->run();

                if ($ocr->isSuccessful()) {
                    $texts[] = $ocr->getOutput();
                } else {
                    $texts[] = '';
                }
            }

            return [
                'text' => implode("\n\n", $texts),
                'pages_count' => count($images),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'text' => '',
                'pages_count' => null,
                'error' => 'تعذر تشغيل OCR: ' . $exception->getMessage(),
            ];
        } finally {
            try {
                File::deleteDirectory($workDir);
            } catch (\Throwable $exception) {
                // لا نعطل الفهرسة بسبب تنظيف مجلد مؤقت.
            }
        }
    }

    private function cleanText(?string $text): string
    {
        $text = str_replace("\0", '', (string) $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?: $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?: $text;

        return trim($text);
    }

    private function toolStatus(string $binary, array $args): array
    {
        try {
            $process = new Process(array_merge([$binary], $args));
            $process->setTimeout(10);
            $process->run();
            $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

            return [
                'path' => $binary,
                'available' => $process->isSuccessful() || $output !== '',
                'message' => $output !== '' ? mb_substr($output, 0, 220, 'UTF-8') : 'متاح',
            ];
        } catch (\Throwable $exception) {
            return [
                'path' => $binary,
                'available' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function setting(string $key, string $default): string
    {
        $value = trim((string) Setting::getValue($key, $default));
        return $value !== '' ? $value : $default;
    }
}
