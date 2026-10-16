<?php

namespace App\Services\GiftBuilder;

use App\Models\GiftBuilderSetting;
use Illuminate\Support\Facades\Schema;

class GiftBuilderSettingsService
{
    /** @return array<string, mixed> */
    public function current(): array
    {
        if (Schema::hasTable('gift_builder_settings')) {
            $setting = GiftBuilderSetting::query()->find(1);
            if ($setting) {
                return $setting->toArray();
            }
        }

        return [
            'packaging_options' => [
                'standard' => [
                    'name_ka' => 'სტანდარტული',
                    'name_en' => 'Standard',
                    'active' => true,
                    'price' => '5.00',
                    'cost' => null,
                ],
            ],
            'delivery_fee' => '7.00',
            'free_delivery_threshold' => '100.00',
            'ad_cost_per_order' => null,
            'courier_cost_per_order' => null,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function packagingOptions(): array
    {
        $options = (array) ($this->current()['packaging_options'] ?? []);

        return collect($options)->mapWithKeys(function ($option, $slug): array {
            $option = (array) $option;
            return [(string) $slug => [
                'label_ka' => $option['name_ka'] ?? $option['label_ka'] ?? $slug,
                'label_en' => $option['name_en'] ?? $option['label_en'] ?? $slug,
                'active' => (bool) ($option['active'] ?? true),
                'price' => (float) ($option['price'] ?? 0),
                'cost' => array_key_exists('cost', $option) && $option['cost'] !== null ? (float) $option['cost'] : null,
            ]];
        })->all();
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): GiftBuilderSetting
    {
        return GiftBuilderSetting::query()->updateOrCreate(['id' => 1], $data);
    }
}
