<?php

namespace App\Services\Chatbot;

class QueryFilterExtractor
{
    public function extract(string $query): array
    {
        $normalized = trim(mb_strtolower($query));

        if ($normalized === '') {
            return [];
        }

        $conditions = [];

        $priceMax = $this->extractPriceMax($normalized);
        if ($priceMax !== null) {
            $conditions[] = ['price' => ['$lte' => $priceMax]];
        }

        $priceMin = $this->extractPriceMin($normalized);
        if ($priceMin !== null) {
            $conditions[] = ['price' => ['$gte' => $priceMin]];
        }

        if ($this->mentionsInStock($normalized)) {
            $conditions[] = ['is_in_stock' => true];
        }

        $category = $this->extractCategory($normalized);
        if ($category !== null) {
            $conditions[] = ['category' => $category];
        }

        $brand = $this->extractBrand($normalized);
        if ($brand !== null) {
            $conditions[] = ['brand' => $brand];
        }

        if ($conditions === []) {
            return [];
        }

        if (count($conditions) === 1) {
            return $conditions[0];
        }

        return ['$and' => $conditions];
    }

    private function extractPriceMax(string $query): ?float
    {
        if (preg_match('/(?:under|below|less\s+than|up\s+to)\s*(\d+(?:[\.,]\d+)?)/iu', $query, $matches) === 1) {
            return $this->toNumber($matches[1]);
        }

        if (preg_match('/(?:მდე|ნაკლებ|ქვემოთ|მაქსიმუმ)\s*(\d+(?:[\.,]\d+)?)/u', $query, $matches) === 1) {
            return $this->toNumber($matches[1]);
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:gel|lari|ლარ|₾)\s*(?:or\s+less|and\s+under|მდე)/iu', $query, $matches) === 1) {
            return $this->toNumber($matches[1]);
        }

        return null;
    }

    private function extractPriceMin(string $query): ?float
    {
        if (preg_match('/(?:over|above|more\s+than|at\s+least)\s*(\d+(?:[\.,]\d+)?)/iu', $query, $matches) === 1) {
            return $this->toNumber($matches[1]);
        }

        if (preg_match('/(?:მეტ|ზემოთ|მინიმუმ)\s*(\d+(?:[\.,]\d+)?)/u', $query, $matches) === 1) {
            return $this->toNumber($matches[1]);
        }

        return null;
    }

    private function mentionsInStock(string $query): bool
    {
        return preg_match('/\b(in\s*stock|available|availability)\b/iu', $query) === 1
            || preg_match('/(მარაგში|ხელმისაწვდომ|არსებობს\s+მარაგში)/u', $query) === 1;
    }

    private function extractCategory(string $query): ?string
    {
        if (preg_match('/\b(watch|smartwatch|gps\s*watch)\b/iu', $query) === 1) {
            return 'smartwatch';
        }

        if (preg_match('/(საათი|სმარტ\s*საათი|gps\s*საათი)/u', $query) === 1) {
            return 'smartwatch';
        }

        return null;
    }

    private function extractBrand(string $query): ?string
    {
        $brandMap = [
            'mytechnic' => 'MyTechnic',
            'apple' => 'Apple',
            'samsung' => 'Samsung',
            'huawei' => 'Huawei',
            'xiaomi' => 'Xiaomi',
        ];

        foreach ($brandMap as $needle => $brand) {
            if (str_contains($query, $needle)) {
                return $brand;
            }
        }

        return null;
    }

    private function toNumber(string $raw): ?float
    {
        $normalized = str_replace(',', '.', $raw);

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
