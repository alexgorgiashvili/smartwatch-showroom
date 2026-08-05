<?php

namespace Tests\Unit;

use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Support\Arr;
use Tests\TestCase;

class StorefrontTranslationParityTest extends TestCase
{
    public function test_storefront_and_ui_translation_keys_match_between_locales(): void
    {
        foreach (['storefront', 'ui'] as $file) {
            $english = Arr::dot(trans($file, [], 'en'));
            $georgian = Arr::dot(trans($file, [], 'ka'));

            $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($georgian))), "{$file}.php has English-only keys.");
            $this->assertSame([], array_values(array_diff(array_keys($georgian), array_keys($english))), "{$file}.php has Georgian-only keys.");
        }
    }

    public function test_english_storefront_translations_do_not_contain_georgian_unicode(): void
    {
        foreach (['storefront', 'ui'] as $file) {
            $english = Arr::dot(trans($file, [], 'en'));

            foreach ($english as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression('/\p{Georgian}/u', $value, "English translation {$file}.{$key} contains Georgian text.");
            }
        }
    }

    public function test_chatbot_policy_enforces_the_active_english_locale(): void
    {
        app()->setLocale('en');
        $policy = app(UnifiedAiPolicyService::class);

        $greeting = $policy->websiteGreetingReply();
        $fallback = $policy->localeFallback();

        $this->assertTrue($policy->passesLocaleQa($greeting));
        $this->assertTrue($policy->passesLocaleQa($fallback));
        $this->assertFalse($policy->passesLocaleQa('ეს პასუხი ინგლისურ რეჟიმში არ უნდა გავიდეს.'));
        $this->assertDoesNotMatchRegularExpression('/\p{Georgian}/u', $greeting . $fallback);
    }
}
