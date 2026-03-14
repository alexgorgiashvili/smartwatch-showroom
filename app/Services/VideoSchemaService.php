<?php

namespace App\Services;

class VideoSchemaService
{
    /**
     * Generate VideoObject schema for product videos
     */
    public function generateProductVideoSchema(array $videoData): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $videoData['name'] ?? '',
            'description' => $videoData['description'] ?? '',
            'thumbnailUrl' => $videoData['thumbnail_url'] ?? '',
            'uploadDate' => $videoData['upload_date'] ?? now()->toIso8601String(),
            'duration' => $videoData['duration'] ?? null, // ISO 8601 format: PT1M30S
            'contentUrl' => $videoData['content_url'] ?? '',
            'embedUrl' => $videoData['embed_url'] ?? '',
        ];
    }

    /**
     * Generate HowTo schema for tutorial videos
     */
    public function generateHowToSchema(array $steps, string $name, string $description): array
    {
        $stepSchemas = [];
        
        foreach ($steps as $index => $step) {
            $stepSchemas[] = [
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => $step['name'] ?? '',
                'text' => $step['text'] ?? '',
                'image' => $step['image'] ?? null,
                'video' => isset($step['video']) ? [
                    '@type' => 'VideoObject',
                    'name' => $step['video']['name'] ?? '',
                    'contentUrl' => $step['video']['url'] ?? '',
                    'thumbnailUrl' => $step['video']['thumbnail'] ?? '',
                ] : null,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $name,
            'description' => $description,
            'step' => array_filter($stepSchemas),
        ];
    }

    /**
     * Parse YouTube URL and extract video ID
     */
    public function parseYouTubeUrl(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([^&]+)/',
            '/youtu\.be\/([^?]+)/',
            '/youtube\.com\/embed\/([^?]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Generate YouTube embed URL
     */
    public function getYouTubeEmbedUrl(string $videoId): string
    {
        return "https://www.youtube.com/embed/{$videoId}";
    }

    /**
     * Generate YouTube thumbnail URL
     */
    public function getYouTubeThumbnail(string $videoId, string $quality = 'maxresdefault'): string
    {
        // Quality options: default, mqdefault, hqdefault, sddefault, maxresdefault
        return "https://img.youtube.com/vi/{$videoId}/{$quality}.jpg";
    }

    /**
     * Format duration to ISO 8601
     */
    public function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $duration = 'PT';
        if ($hours > 0) {
            $duration .= "{$hours}H";
        }
        if ($minutes > 0) {
            $duration .= "{$minutes}M";
        }
        if ($secs > 0 || ($hours === 0 && $minutes === 0)) {
            $duration .= "{$secs}S";
        }

        return $duration;
    }
}
