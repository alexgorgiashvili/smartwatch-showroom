<?php

namespace App\Services\Contracts;

interface CompetitorCategoryScraper
{
    public function scrapeCategory(string $categoryUrl): array;
}
