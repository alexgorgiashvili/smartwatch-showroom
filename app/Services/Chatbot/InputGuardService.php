<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\InputGuardResult;

class InputGuardService
{
    public function sanitize(string $message): string
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return '';
        }

        return $this->redactPii($trimmed);
    }

    public function inspect(string $message): InputGuardResult
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return InputGuardResult::block(
                '',
                'empty_message',
                'გთხოვთ, მოგვწეროთ კონკრეტულად რა გაინტერესებთ სმარტსაათზე და სიამოვნებით დაგეხმარებით.'
            );
        }

        $sanitized = $this->sanitize($trimmed);

        if ($this->looksLikePromptInjection($trimmed)) {
            return InputGuardResult::block(
                $sanitized,
                'prompt_injection',
                'უსაფრთხოების წესების გამო მხოლოდ MyTechnic-ის სმარტსაათებზე დაგეხმარებით. გთხოვთ, მომწეროთ რომელი მოდელი ან ფუნქცია გაინტერესებთ.'
            );
        }

        if ($this->isHarmfulContent($trimmed)) {
            return InputGuardResult::block(
                $sanitized,
                'harmful_content',
                'სამწუხაროდ ამ შინაარსზე პასუხს ვერ გაგცემთ. სიამოვნებით დაგეხმარებით MyTechnic-ის სმარტსაათების შერჩევაში.'
            );
        }

        if (!$this->isDomainRelevant($trimmed) && $this->isClearlyOffTopic($trimmed)) {
            return InputGuardResult::block(
                $sanitized,
                'off_topic',
                'მე მხოლოდ MyTechnic-ის სმარტსაათებზე გეხმარებით. გთხოვთ, მომწეროთ მოდელი, ბიუჯეტი ან ფუნქცია (GPS, SOS, ზარები, კამერა).'
            );
        }

        return InputGuardResult::allow($sanitized);
    }

    private function looksLikePromptInjection(string $message): bool
    {
        $patterns = [
            '/ignore\s+(all\s+)?previous\s+instructions/iu',
            '/disregard\s+(the\s+)?system/iu',
            '/you\s+are\s+now\s+/iu',
            '/act\s+as\s+/iu',
            '/reveal\s+(the\s+)?system\s+prompt/iu',
            '/show\s+(the\s+)?hidden\s+instructions/iu',
            '/დაივიწყე\s+წინა\s+ინსტრუქცი/iu',
            '/შენ\s+ახლა\s+ხარ/iu',
            '/სისტემურ\s+ინსტრუქცი/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
    private function redactPii(string $message): string
    {
        $redacted = $message;

        $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[REDACTED_EMAIL]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\+\d[\d\s\-\(\)]{7,}\d/u', '[REDACTED_PHONE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b(?:\d[ -]*?){13,19}\b/u', '[REDACTED_CARD]', $redacted) ?? $redacted;

        return $redacted;
    }

    private function isDomainRelevant(string $message): bool
    {
        $normalized = mb_strtolower($message);

        $patterns = [
            '/\b(mytechnic|smart\s?watch|watch|gps|sos|sim|battery|camera|model|price|stock|delivery|warranty|store|shop|location|hours|contact|phone|whatsapp|return|refund|address)\b/iu',
            '/(საათ|სმარტსაათ|გპს|gps|sos|სიმ|ზარ|კამერა|ბატარე|მოდელ|ფასი|მარაგ|მიწოდ|გარანტ|რომელი|რომელს|მირჩიე|მინდა|მაჩვენე|საუკეთესო|ბავშვ|მისამართ|სამუშაო\s+საათ|კონტაქტ|საკონტაქტო|მაღაზი|დაბრუნებ)/u',
            '/\b(which|recommend|show|need|best|kids?)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isHarmfulContent(string $message): bool
    {
        $normalized = mb_strtolower($message);

        $patterns = [
            // Georgian: PII / data theft
            '/(მოვიპარო|მოპარვა|მოიპარე|გამოტყუება)/u',
            '/(ბარათის\s+მონაცემ|საკრედიტო\s+ბარათ|პინ\s*კოდ)/u',
            '/(პაროლ|სეკრეტულ|კონფიდენციალ)/u',
            '/(მომხმარებლ\w+\s+მონაცემ|პერსონალურ\s+ინფორმაცი)/u',
            '/(შეკვეთ\w+\s+დეტალ|სხვა\s+მომხმარებელ)/u',
            // Georgian: DB / System attacks
            '/(წაშალე?\s+database|წაშალე?\s+ბაზა|ბაზა\s+წაშალე)/u',
            '/(ჰაკი|ჰაკერ|გატეხვა|გატეხე|შეღწევა)/u',
            '/(ადმინ\s*პანელ)/u',
            // Georgian: Violence / illegal
            '/(აფეთქება|იარაღ|მოკვლა|თავს\s+დაესხ)/u',
            // English: DB / data theft
            '/\b(drop\s+database|send\s+(all\s+)?customer\s+data|dump\s+database)\b/iu',
            '/\b(delete\s+(all\s+)?data|export\s+users?)\b/iu',
            '/\b(steal|hack|exploit|breach)\s+(credit\s+card|password|user\s+data)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isClearlyOffTopic(string $message): bool
    {
        $normalized = mb_strtolower($message);

        $offTopicPatterns = [
            '/\b(weather|football|soccer|crypto|bitcoin|politics|election|movie|music|recipe|cooking)\b/iu',
            '/(ამინდი|ფეხბურთ|კრიპტო|ბიტკოინ|პოლიტიკ|არჩევნ|ფილმ|მუსიკ|რეცეპტ|სამზარეულ)/u',
        ];

        foreach ($offTopicPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
