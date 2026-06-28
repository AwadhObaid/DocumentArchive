<?php

declare(strict_types=1);

$root = getcwd();
if (!is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: يجب تشغيل السكربت من جذر مشروع Laravel حيث يوجد ملف artisan.\n");
    exit(1);
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function writeFile(string $path, string $content): void
{
    ensureDir(dirname($path));
    if (is_file($path)) {
        $backup = $path . '.before-qr-' . date('Ymd_His') . '.bak';
        @copy($path, $backup);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function patchOnce(string $path, callable $callback): void
{
    if (!is_file($path)) {
        return;
    }
    $content = file_get_contents($path);
    $new = $callback($content);
    if ($new !== $content) {
        @copy($path, $path . '.before-qr-' . date('Ymd_His') . '.bak');
        file_put_contents($path, $new);
    }
}

$service = <<<'PHP_SERVICE'
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * مولد QR بسيط بصيغة SVG بدون مكتبات خارجية.
 * يدعم بيانات Byte بطول مناسب للروابط الداخلية للنظام.
 * يستخدم QR Version 4 - ECC Low؛ مناسب لرابط مثل /documents/{id}.
 */
class SimpleQrCodeSvg
{
    private const VERSION = 4;
    private const SIZE = 33; // 4 * version + 17
    private const DATA_CODEWORDS = 80;
    private const ECC_CODEWORDS = 20;

    public static function make(string $text, int $scale = 6, int $border = 4): string
    {
        $bytes = array_values(unpack('C*', $text));
        if (count($bytes) > 78) {
            // اختصار آمن إذا كان الرابط طويلاً جداً.
            $bytes = array_values(unpack('C*', substr($text, 0, 78)));
        }

        [$modules, $isFunction] = self::buildFunctionMatrix();
        $dataCodewords = self::encodeDataCodewords($bytes);
        $ecc = self::reedSolomonRemainder($dataCodewords, self::reedSolomonDivisor(self::ECC_CODEWORDS));
        $allCodewords = array_merge($dataCodewords, $ecc);
        self::drawCodewords($modules, $isFunction, $allCodewords);
        self::applyMask($modules, $isFunction, 0);
        self::drawFormatBits($modules, $isFunction, 1, 0); // ECC L = 1, mask 0

        $size = self::SIZE;
        $viewSize = ($size + ($border * 2)) * $scale;
        $rects = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!empty($modules[$y][$x])) {
                    $rx = ($x + $border) * $scale;
                    $ry = ($y + $border) * $scale;
                    $rects[] = '<rect x="' . $rx . '" y="' . $ry . '" width="' . $scale . '" height="' . $scale . '"/>';
                }
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $viewSize . '" height="' . $viewSize . '" viewBox="0 0 ' . $viewSize . ' ' . $viewSize . '" role="img" aria-label="QR Code">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<g fill="#000000">' . implode('', $rects) . '</g>'
            . '</svg>';
    }

    private static function encodeDataCodewords(array $bytes): array
    {
        $bits = [];
        self::appendBits($bits, 0x4, 4); // Byte mode
        self::appendBits($bits, count($bytes), 8);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        $capacityBits = self::DATA_CODEWORDS * 8;
        $terminator = min(4, $capacityBits - count($bits));
        self::appendBits($bits, 0, $terminator);
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 1) | $bits[$i + $j];
            }
            $codewords[] = $value;
        }

        for ($pad = 0; count($codewords) < self::DATA_CODEWORDS; $pad ^= 1) {
            $codewords[] = $pad === 0 ? 0xEC : 0x11;
        }

        return $codewords;
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function buildFunctionMatrix(): array
    {
        $size = self::SIZE;
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $f = array_fill(0, $size, array_fill(0, $size, false));

        self::drawFinder($m, $f, 3, 3);
        self::drawFinder($m, $f, $size - 4, 3);
        self::drawFinder($m, $f, 3, $size - 4);

        for ($i = 0; $i < $size; $i++) {
            self::setFunction($m, $f, 6, $i, $i % 2 === 0);
            self::setFunction($m, $f, $i, 6, $i % 2 === 0);
        }

        // Alignment pattern for Version 4: positions 6 and 26; only center 26,26 is not overlapping finders.
        self::drawAlignment($m, $f, 26, 26);

        // Reserve format areas.
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $f[8][$i] = true;
                $f[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $f[8][$size - 1 - $i] = true;
            $f[$size - 1 - $i][8] = true;
        }
        self::setFunction($m, $f, 8, 4 * self::VERSION + 9, true); // Dark module

        return [$m, $f];
    }

    private static function drawFinder(array &$m, array &$f, int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
                    continue;
                }
                $dist = max(abs($dx), abs($dy));
                self::setFunction($m, $f, $x, $y, $dist !== 2 && $dist !== 4);
            }
        }
    }

    private static function drawAlignment(array &$m, array &$f, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                self::setFunction($m, $f, $cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private static function setFunction(array &$m, array &$f, int $x, int $y, bool $dark): void
    {
        if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
            return;
        }
        $m[$y][$x] = $dark;
        $f[$y][$x] = true;
    }

    private static function drawCodewords(array &$m, array $f, array $codewords): void
    {
        $bits = [];
        foreach ($codewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($cw >> $i) & 1;
            }
        }

        $size = self::SIZE;
        $bitIndex = 0;
        $upward = true;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $y = $upward ? ($size - 1 - $vert) : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if (!$f[$y][$x] && $bitIndex < count($bits)) {
                        $m[$y][$x] = (bool) $bits[$bitIndex++];
                    }
                }
            }
            $upward = !$upward;
        }
    }

    private static function applyMask(array &$m, array $f, int $mask): void
    {
        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if (!$f[$y][$x] && (($x + $y) % 2 === 0)) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
    }

    private static function drawFormatBits(array &$m, array &$f, int $ecl, int $mask): void
    {
        $data = ($ecl << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem <<= 1;
            if (($rem >> 10) & 1) {
                $rem ^= 0x537;
            }
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $size = self::SIZE;

        for ($i = 0; $i <= 5; $i++) self::setFunction($m, $f, 8, $i, self::bit($bits, $i));
        self::setFunction($m, $f, 8, 7, self::bit($bits, 6));
        self::setFunction($m, $f, 8, 8, self::bit($bits, 7));
        self::setFunction($m, $f, 7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) self::setFunction($m, $f, 14 - $i, 8, self::bit($bits, $i));

        for ($i = 0; $i < 8; $i++) self::setFunction($m, $f, $size - 1 - $i, 8, self::bit($bits, $i));
        for ($i = 8; $i < 15; $i++) self::setFunction($m, $f, 8, $size - 15 + $i, self::bit($bits, $i));
        self::setFunction($m, $f, 8, $size - 8, true);
    }

    private static function bit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }

    private static function reedSolomonDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    private static function reedSolomonRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ $result[0];
            array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMultiply($coef, $factor);
            }
        }
        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            if ((($y >> $i) & 1) !== 0) {
                $z ^= $x;
            }
        }
        return $z;
    }
}
PHP_SERVICE;

$controller = <<<'PHP_CONTROLLER'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\SimpleQrCodeSvg;
use Illuminate\Http\Response;

class DocumentQrController extends Controller
{
    public function show(Document $document): Response
    {
        $url = route('documents.show', $document);
        $svg = SimpleQrCodeSvg::make($url, 5, 4);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
PHP_CONTROLLER;

writeFile($root . '/app/Services/SimpleQrCodeSvg.php', $service);
writeFile($root . '/app/Http/Controllers/DocumentQrController.php', $controller);

// Patch routes/web.php
$routesPath = $root . '/routes/web.php';
if (!is_file($routesPath)) {
    fwrite(STDERR, "ERROR: ملف routes/web.php غير موجود.\n");
    exit(1);
}
patchOnce($routesPath, function (string $content): string {
    if (!str_contains($content, 'DocumentQrController')) {
        $content = preg_replace('/<\?php\s*/', "<?php\n\nuse App\\Http\\Controllers\\DocumentQrController;\n", $content, 1) ?? $content;
    }
    if (!str_contains($content, "documents.qr")) {
        $route = "\nRoute::get('/documents/{document}/qr.svg', [DocumentQrController::class, 'show'])->middleware('auth')->name('documents.qr');\n";
        $content .= $route;
    }
    return $content;
});

$qrSnippet = <<<'BLADE'

{{-- QR Code للكتاب --}}
<div class="document-qr-panel no-print" style="margin:16px 0;padding:14px;border:1px solid rgba(148,163,184,.25);border-radius:16px;display:flex;align-items:center;gap:14px;background:rgba(15,23,42,.35);">
    <img src="{{ route('documents.qr', $document) }}" alt="QR Code" width="116" height="116" style="background:#fff;padding:8px;border-radius:12px;">
    <div>
        <strong>QR Code للكتاب</strong>
        <div style="font-size:13px;color:#94a3b8;margin-top:4px;">امسح الرمز للوصول مباشرة إلى صفحة الكتاب داخل النظام.</div>
    </div>
</div>
BLADE;

$printQrSnippet = <<<'BLADE'

{{-- QR Code للكتاب في الطباعة --}}
<div class="document-print-qr" style="margin-top:18px;text-align:center;">
    <img src="{{ route('documents.qr', $document) }}" alt="QR Code" width="105" height="105" style="background:#fff;padding:6px;border:1px solid #d1d5db;border-radius:8px;">
    <div style="font-size:11px;margin-top:6px;color:#374151;">رمز الوصول الإلكتروني للكتاب</div>
</div>
BLADE;

$showPath = $root . '/resources/views/documents/show.blade.php';
patchOnce($showPath, function (string $content) use ($qrSnippet): string {
    if (str_contains($content, 'documents.qr')) {
        return $content;
    }
    if (str_contains($content, '</x-app-layout>')) {
        return str_replace('</x-app-layout>', $qrSnippet . "\n</x-app-layout>", $content);
    }
    if (str_contains($content, '@endsection')) {
        return preg_replace('/@endsection\s*$/', $qrSnippet . "\n@endsection", $content, 1) ?? ($content . $qrSnippet);
    }
    return $content . $qrSnippet;
});

$possiblePrintViews = [
    $root . '/resources/views/documents/print-reference.blade.php',
    $root . '/resources/views/documents/print_number.blade.php',
    $root . '/resources/views/documents/print-number.blade.php',
    $root . '/resources/views/documents/print.blade.php',
];
foreach ($possiblePrintViews as $view) {
    patchOnce($view, function (string $content) use ($printQrSnippet): string {
        if (str_contains($content, 'documents.qr')) {
            return $content;
        }
        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $printQrSnippet . "\n</body>", $content);
        }
        if (str_contains($content, '@endsection')) {
            return preg_replace('/@endsection\s*$/', $printQrSnippet . "\n@endsection", $content, 1) ?? ($content . $printQrSnippet);
        }
        return $content . $printQrSnippet;
    });
}

echo "DONE: تم إضافة QR Code للكتب.\n";
echo "NEXT: composer dump-autoload && php artisan route:clear && php artisan view:clear && php artisan optimize:clear\n";
