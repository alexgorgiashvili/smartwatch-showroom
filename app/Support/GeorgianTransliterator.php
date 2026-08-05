<?php

namespace App\Support;

final class GeorgianTransliterator
{
    private const CHARACTER_MAP = [
        'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e',
        'ვ' => 'v', 'ზ' => 'z', 'თ' => 't', 'ი' => 'i', 'კ' => 'k',
        'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o', 'პ' => 'p',
        'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's', 'ტ' => 't', 'უ' => 'u',
        'ფ' => 'p', 'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'q', 'შ' => 'sh',
        'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch',
        'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',
    ];

    public static function transliterate(string $value): string
    {
        $normalized = preg_replace('/\s*>\s*/u', ' > ', trim($value)) ?? trim($value);
        $transliterated = strtr(mb_strtolower($normalized), self::CHARACTER_MAP);

        return implode(' > ', array_map(
            static fn (string $segment): string => mb_convert_case(trim($segment), MB_CASE_TITLE, 'UTF-8'),
            explode(' > ', $transliterated)
        ));
    }
}
