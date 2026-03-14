<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapIndexController extends Controller
{
    /**
     * Generate sitemap index
     */
    public function index(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Main sitemap
        $xml .= '<sitemap>';
        $xml .= '<loc>' . url('/sitemap.xml') . '</loc>';
        $xml .= '<lastmod>' . now()->toIso8601String() . '</lastmod>';
        $xml .= '</sitemap>';
        
        // Image sitemap
        $xml .= '<sitemap>';
        $xml .= '<loc>' . url('/sitemap-images.xml') . '</loc>';
        $xml .= '<lastmod>' . now()->toIso8601String() . '</lastmod>';
        $xml .= '</sitemap>';
        
        $xml .= '</sitemapindex>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
}
