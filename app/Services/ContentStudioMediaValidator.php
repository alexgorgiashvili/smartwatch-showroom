<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ContentStudioMediaValidator
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const VIDEO_MIMES = ['video/mp4', 'video/quicktime'];

    public function validateUpload(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true) || $file->getSize() > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Only JPEG, PNG, WebP, MP4 or MOV media up to 20 MB is accepted.']);
        }
        $dimensions = in_array($mime, self::IMAGE_MIMES, true) ? @getimagesize($file->getRealPath()) : null;
        if ($dimensions && (($dimensions[0] / max(1, $dimensions[1])) < 0.8 || ($dimensions[0] / max(1, $dimensions[1])) > 1.91)) {
            throw ValidationException::withMessages(['file' => 'Image aspect ratio must be between 0.8 and 1.91 for social review.']);
        }
        return ['mime_type' => $mime, 'size_bytes' => $file->getSize(), 'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'social_compatible' => $dimensions ? true : in_array($mime, self::VIDEO_MIMES, true)];
    }

    public function validatePublicUrl(?string $url, string $channel): array
    {
        $isHttps = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://');
        if ($channel === 'instagram' && !$isHttps) throw ValidationException::withMessages(['media_url' => 'Instagram requires a publicly reachable HTTPS media URL.']);
        return ['public_https' => (bool) $isHttps, 'requires_public_https_before_publish' => in_array($channel, ['facebook', 'instagram'], true)];
    }
}
