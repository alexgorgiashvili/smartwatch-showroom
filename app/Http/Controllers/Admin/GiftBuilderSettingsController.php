<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GiftBuilder\GiftBuilderSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GiftBuilderSettingsController extends Controller
{
    public function edit(Request $request, GiftBuilderSettingsService $settings)
    {
        $view = view('admin.gift-boxes.settings', [
            'settings' => $settings->current(),
            'settingsTableReady' => Schema::hasTable('gift_builder_settings'),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function update(Request $request, GiftBuilderSettingsService $settings): RedirectResponse
    {
        if (! Schema::hasTable('gift_builder_settings')) {
            throw ValidationException::withMessages([
                'settings' => 'Gift Builder-ის პარამეტრების მიგრაცია ჯერ არ არის გაშვებული.',
            ]);
        }

        $data = $request->validate([
            'packaging' => ['required', 'array', 'min:1', 'max:10'],
            'packaging.*.slug' => ['required', 'alpha_dash', 'max:40', 'distinct'],
            'packaging.*.name_ka' => ['required', 'string', 'max:80'],
            'packaging.*.name_en' => ['required', 'string', 'max:80'],
            'packaging.*.active' => ['nullable', 'boolean'],
            'packaging.*.price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'packaging.*.cost' => ['nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'free_delivery_threshold' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'ad_cost_per_order' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'courier_cost_per_order' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        if (! collect($data['packaging'])->contains(fn (array $row): bool => (bool) ($row['active'] ?? false))) {
            throw ValidationException::withMessages([
                'packaging' => 'მინიმუმ ერთი შეფუთვა უნდა იყოს აქტიური.',
            ]);
        }

        $packaging = [];
        foreach ($data['packaging'] as $row) {
            $slug = $row['slug'];
            $packaging[$slug] = [
                'name_ka' => trim($row['name_ka']),
                'name_en' => trim($row['name_en']),
                'active' => (bool) ($row['active'] ?? false),
                'price' => $row['price'],
                'cost' => $row['cost'] ?? null,
            ];
        }

        $settings->save([
            'packaging_options' => $packaging,
            'delivery_fee' => $data['delivery_fee'],
            'free_delivery_threshold' => $data['free_delivery_threshold'],
            'ad_cost_per_order' => $data['ad_cost_per_order'] ?? null,
            'courier_cost_per_order' => $data['courier_cost_per_order'] ?? null,
        ]);

        return redirect()->route('admin.gift-boxes.settings.edit')->with('status', 'Gift Builder-ის პარამეტრები შენახულია.');
    }
}
