<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidPlatformId implements Rule
{
    protected string $platform;
    protected string $errorMessage = 'The sender ID format is invalid for the specified platform';

    /**
     * Create a new rule instance
     */
    public function __construct(string $platform)
    {
        $this->platform = strtolower($platform);
    }

    /**
     * Determine if the validation rule passes
     */
    public function passes($attribute, $value): bool
    {
        return match ($this->platform) {
            'facebook', 'instagram' => $this->isValidMetaId($value),
            'whatsapp' => $this->isValidWhatsAppId($value),
            default => false,
        };
    }

    /**
     * Get the validation error message
     */
    public function message(): string
    {
        return match ($this->platform) {
            'facebook', 'instagram' => 'The ' . $attribute . ' must be a valid Facebook/Instagram ID (15-20 numeric digits)',
            'whatsapp' => 'The ' . $attribute . ' must be a valid WhatsApp phone number in E.164 format (e.g., +1234567890)',
            default => $this->errorMessage,
        };
    }

    /**
     * Validate Facebook/Instagram PSID or IGID format
     * Must be numeric and between 15-20 digits
     */
    protected function isValidMetaId(string $id): bool
    {
        // Remove any whitespace
        $id = trim($id);

        // Check if numeric and within digit range
        if (!preg_match('/^\d{15,20}$/', $id)) {
            return false;
        }

        // Additional check: ensure it's not all zeros
        if (preg_match('/^0+$/', $id)) {
            return false;
        }

        return true;
    }

    /**
     * Validate WhatsApp phone number in E.164 format
     * Format: +1234567890 (+ followed by 1-15 digits, no spaces or special chars)
     */
    protected function isValidWhatsAppId(string $phone): bool
    {
        // Remove any whitespace
        $phone = trim($phone);

        // Check E.164 format: + followed by 1-15 digits
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return false;
        }

        // Length check: + plus at least 10 digits (some countries), at most 15 digits
        $digitsOnly = preg_replace('/\D/', '', $phone);
        if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15) {
            return false;
        }

        return true;
    }
}
