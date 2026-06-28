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