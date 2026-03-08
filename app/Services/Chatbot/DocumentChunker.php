<?php

namespace App\Services\Chatbot;

class DocumentChunker
{
    private const MAX_CHARS = 2400;
    private const OVERLAP_CHARS = 240;

    /**
     * @return array<int, array{section: string, text: string, chunk_index: int}>
     */
    public function chunk(string $content, string $type): array
    {
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if ($type !== 'product') {
            return $this->sliceSection('general', $content, 0);
        }

        $lines = preg_split('/\r?\n/u', $content) ?: [];
        $sections = $this->extractProductSections($lines);

        $chunks = [];
        $chunkIndex = 0;

        foreach ($sections as $section => $sectionText) {
            $sectionText = trim($sectionText);

            if ($sectionText === '') {
                continue;
            }

            $sectionChunks = $this->sliceSection($section, $sectionText, $chunkIndex);
            $chunks = array_merge($chunks, $sectionChunks);
            $chunkIndex = count($chunks);
        }

        if ($chunks === []) {
            return $this->sliceSection('product', $content, 0);
        }

        return $chunks;
    }

    /**
     * @param array<int, string> $lines
     * @return array<string, string>
     */
    private function extractProductSections(array $lines): array
    {
        $sections = [
            'overview' => [],
            'description' => [],
            'technical_specs' => [],
            'variants_stock' => [],
            'reviews' => [],
        ];

        $current = 'overview';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $lower = mb_strtolower($trimmed);

            if (str_starts_with($lower, 'მოკლე აღწერა:') || str_starts_with($lower, 'აღწერა:')) {
                $current = 'description';
            } elseif (
                str_starts_with($lower, 'sim მხარდაჭერა:') ||
                str_starts_with($lower, 'gps ფუნქციები:') ||
                str_starts_with($lower, 'წყალგამძლეობა:') ||
                str_starts_with($lower, 'ბატარე') ||
                str_starts_with($lower, 'ოპერაციული სისტემა:') ||
                str_starts_with($lower, 'ეკრანის') ||
                str_starts_with($lower, 'დისპლეის') ||
                str_starts_with($lower, 'კორპუსის') ||
                str_starts_with($lower, 'სამაჯურის') ||
                str_starts_with($lower, 'კამერა:') ||
                str_starts_with($lower, 'ფუნქციები:')
            ) {
                $current = 'technical_specs';
            } elseif (str_starts_with($lower, 'ვარიანტები:') || str_starts_with($lower, 'საერთო მარაგი:')) {
                $current = 'variants_stock';
            } elseif (str_starts_with($lower, 'მიმოხილვ') || str_starts_with($lower, 'reviews:')) {
                $current = 'reviews';
            }

            $sections[$current][] = $trimmed;
        }

        $joined = [];

        foreach ($sections as $name => $rows) {
            $text = trim(implode("\n", array_values(array_filter($rows, static fn (string $row): bool => $row !== ''))));
            if ($text !== '') {
                $joined[$name] = $text;
            }
        }

        return $joined;
    }

    /**
     * @return array<int, array{section: string, text: string, chunk_index: int}>
     */
    private function sliceSection(string $section, string $text, int $startIndex): array
    {
        $length = mb_strlen($text);

        if ($length <= self::MAX_CHARS) {
            return [[
                'section' => $section,
                'text' => $text,
                'chunk_index' => $startIndex,
            ]];
        }

        $chunks = [];
        $offset = 0;
        $index = $startIndex;

        while ($offset < $length) {
            $slice = mb_substr($text, $offset, self::MAX_CHARS);
            if ($slice === '') {
                break;
            }

            $chunks[] = [
                'section' => $section,
                'text' => trim($slice),
                'chunk_index' => $index,
            ];

            $index++;
            $offset += max(1, self::MAX_CHARS - self::OVERLAP_CHARS);
        }

        return $chunks;
    }
}
