<?php

namespace App\Support;

/**
 * Central limit for normal archive attachments uploaded through the web UI.
 *
 * 50 MB = 51,200 KB = 52,428,800 bytes.
 *
 * Smart Attachment Browser and File Bridge have their own configurable
 * limits (1-100 MB) and use 50 MB as their new default.
 */
final class AttachmentUploadLimits
{
    public const MAX_MB = 50;
    public const MAX_KB = self::MAX_MB * 1024;
    public const MAX_BYTES = self::MAX_MB * 1024 * 1024;

    private function __construct()
    {
    }
}
