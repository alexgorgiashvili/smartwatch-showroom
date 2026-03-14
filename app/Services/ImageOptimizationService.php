<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageOptimizationService
{
    /**
     * Generate responsive image srcset
     */
    public function generateSrcset(string $imagePath, array $sizes = [320, 640, 768, 1024, 1280]): string
    {
        $srcset = [];
        
        foreach ($sizes as $width) {
            $resizedPath = $this->getResizedPath($imagePath, $width);
            if ($resizedPath) {
                $srcset[] = asset('storage/' . $resizedPath) . " {$width}w";
            }
        }
        
        return implode(', ', $srcset);
    }

    /**
     * Generate sizes attribute for responsive images
     */
    public function generateSizes(string $type = 'default'): string
    {
        $sizeMap = [
            'hero' => '100vw',
            'product-grid' => '(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw',
            'product-detail' => '(max-width: 768px) 100vw, 50vw',
            'thumbnail' => '(max-width: 640px) 100px, 150px',
            'default' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw',
        ];

        return $sizeMap[$type] ?? $sizeMap['default'];
    }

    /**
     * Get optimized image path (placeholder for actual implementation)
     */
    private function getResizedPath(string $imagePath, int $width): ?string
    {
        // Remove 'storage/' prefix if present
        $cleanPath = str_replace('storage/', '', $imagePath);
        
        // Check if original exists
        if (!Storage::disk('public')->exists($cleanPath)) {
            return null;
        }

        // For now, return original path
        // In production, this would generate/return resized versions
        return $cleanPath;
    }

    /**
     * Generate WebP version path
     */
    public function getWebPPath(string $imagePath): ?string
    {
        $pathInfo = pathinfo($imagePath);
        $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
        
        // Check if WebP version exists
        $cleanPath = str_replace('storage/', '', $webpPath);
        if (Storage::disk('public')->exists($cleanPath)) {
            return 'storage/' . $cleanPath;
        }

        return null;
    }

    /**
     * Generate picture element with WebP support
     */
    public function generatePictureMarkup(string $imagePath, string $alt, array $attributes = []): string
    {
        $webpPath = $this->getWebPPath($imagePath);
        $srcset = $this->generateSrcset($imagePath);
        $sizes = $this->generateSizes($attributes['sizes-type'] ?? 'default');
        
        $attrString = '';
        foreach ($attributes as $key => $value) {
            if ($key !== 'sizes-type') {
                $attrString .= " {$key}=\"{$value}\"";
            }
        }

        $html = '<picture>';
        
        if ($webpPath) {
            $html .= "<source type=\"image/webp\" srcset=\"{$webpPath}\">";
        }
        
        $html .= "<img src=\"{$imagePath}\" srcset=\"{$srcset}\" sizes=\"{$sizes}\" alt=\"{$alt}\"{$attrString}>";
        $html .= '</picture>';

        return $html;
    }

    /**
     * Get optimized image attributes for lazy loading
     */
    public function getLazyAttributes(string $imagePath, string $alt, string $type = 'default'): array
    {
        return [
            'data-src' => asset('storage/' . $imagePath),
            'data-srcset' => $this->generateSrcset($imagePath),
            'sizes' => $this->generateSizes($type),
            'alt' => $alt,
            'loading' => 'lazy',
            'decoding' => 'async',
            'class' => 'lazy',
        ];
    }
}
