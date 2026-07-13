<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiSmartReportService
{
    private const DEFAULT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    public function isEnabled(): bool
    {
        return (string) Setting::getValue('smart_reports_enabled', '0') === '1';
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function model(): string
    {
        return Setting::getString('smart_reports_gemini_model', 'gemini-3.5-flash');
    }

    public function apiKey(): string
    {
        $stored = Setting::getValue('smart_reports_gemini_api_key', '');

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString($stored));
        } catch (Throwable $exception) {
            // Backward compatible fallback if a value was saved before encryption.
            return trim($stored);
        }
    }

    public function generate(string $prompt, ?string $model = null): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('التقارير الذكية غير مفعلة من الإعدادات.');
        }

        $apiKey = $this->apiKey();

        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API Key غير محفوظ في إعدادات النظام.');
        }

        $model = trim((string) ($model ?: $this->model()));
        if ($model === '') {
            $model = 'gemini-3.5-flash';
        }

        $payload = [
            'model' => $model,
            'system_instruction' => $this->systemInstruction(),
            'input' => $prompt,
            'generation_config' => [
                'temperature' => 0.35,
            ],
        ];

        try {
            $response = Http::timeout(90)
                ->retry(1, 700)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::DEFAULT_ENDPOINT, $payload);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('تعذر الاتصال بخدمة Gemini. تحقق من اتصال الإنترنت على جهاز السيرفر.');
        }

        $json = $response->json();

        if (! $response->successful()) {
            $message = data_get($json, 'error.message');

            if (! is_string($message) || trim($message) === '') {
                $message = mb_substr($response->body(), 0, 600);
            }

            throw new \RuntimeException('فشل طلب Gemini: ' . $message);
        }

        $text = $this->extractText($json);

        if ($text === '') {
            throw new \RuntimeException('تم الاتصال بـ Gemini لكن لم يتم استلام نص التقرير.');
        }

        return [
            'text' => $text,
            'model' => $model,
            'raw' => $json,
        ];
    }

    public function testConnection(?string $model = null): string
    {
        $result = $this->generate('اكتب كلمة: جاهز', $model);

        return $result['text'];
    }

    private function systemInstruction(): string
    {
        return implode("\n", [
            'أنت محلل تقارير إداري عربي متخصص في أنظمة الأرشفة والكتب الرسمية.',
            'اكتب بالعربية الفصحى الواضحة وبأسلوب رسمي مختصر.',
            'اعتمد فقط على الأرقام والبيانات المقدمة لك ولا تخترع أرقاماً أو أسماء غير موجودة.',
            'إذا كانت البيانات قليلة فاذكر ذلك صراحة.',
            'نظم التقرير بعناوين واضحة: ملخص تنفيذي، مؤشرات رقمية، ملاحظات، توصيات.',
            'لا تطلب صلاحيات ولا مفاتيح API ولا تذكر تفاصيل تقنية داخل التقرير النهائي.',
        ]);
    }

    private function extractText(mixed $json): string
    {
        if (is_array($json)) {
            $direct = data_get($json, 'output_text');
            if (is_string($direct) && trim($direct) !== '') {
                return trim($direct);
            }

            // Compatibility with generateContent-shaped responses.
            $candidateText = data_get($json, 'candidates.0.content.parts.0.text');
            if (is_string($candidateText) && trim($candidateText) !== '') {
                return trim($candidateText);
            }

            // Compatibility with multi-step Interactions responses.
            $texts = [];
            foreach ((array) data_get($json, 'steps', []) as $step) {
                $blocks = data_get($step, 'output', data_get($step, 'content', []));
                foreach ((array) $blocks as $block) {
                    $text = is_array($block) ? ($block['text'] ?? null) : null;
                    if (is_string($text) && trim($text) !== '') {
                        $texts[] = trim($text);
                    }
                }
            }

            if ($texts !== []) {
                return trim(implode("\n", $texts));
            }
        }

        return '';
    }
}
