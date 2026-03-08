<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdentityResolutionService
{
    public function resolveCustomer(
        string $platform,
        string $platformId,
        ?string $phone = null,
        ?string $email = null,
        array $data = []
    ): Customer {
        return DB::transaction(function () use ($platform, $platformId, $phone, $email, $data): Customer {
            $platformCustomer = Customer::query()
                ->whereJsonContains('platform_user_ids->' . $platform, $platformId)
                ->first();

            $phone = $this->normalizePhone($phone);
            $email = $this->normalizeEmail($email);

            $identityCustomer = null;

            if ($email !== null) {
                $identityCustomer = Customer::query()->where('email', $email)->first();
            }

            if (!$identityCustomer && $phone !== null) {
                $identityCustomer = Customer::query()->where('phone', $phone)->first();
            }

            if ($platformCustomer && $identityCustomer && $platformCustomer->id !== $identityCustomer->id) {
                return $this->mergeCustomers($identityCustomer, $platformCustomer, $platform, $platformId, $phone, $email, $data);
            }

            $customer = $identityCustomer ?? $platformCustomer;

            if (!$customer) {
                $customer = Customer::create([
                    'name' => $data['name'] ?? "Customer {$platformId}",
                    'email' => $email,
                    'phone' => $phone,
                    'avatar_url' => $data['avatar_url'] ?? null,
                    'platform_user_ids' => [$platform => $platformId],
                    'metadata' => $data['metadata'] ?? null,
                    'global_user_id' => (string) Str::uuid(),
                ]);

                return $customer;
            }

            $this->applyIdentityData($customer, $platform, $platformId, $phone, $email, $data);

            return $customer->fresh();
        });
    }

    private function mergeCustomers(
        Customer $target,
        Customer $source,
        string $platform,
        string $platformId,
        ?string $phone,
        ?string $email,
        array $data
    ): Customer {
        Conversation::query()
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        $platformIds = array_merge(
            is_array($target->platform_user_ids) ? $target->platform_user_ids : [],
            is_array($source->platform_user_ids) ? $source->platform_user_ids : []
        );

        $platformIds[$platform] = $platformId;

        $metadata = array_merge(
            is_array($target->metadata) ? $target->metadata : [],
            is_array($source->metadata) ? $source->metadata : [],
            is_array($data['metadata'] ?? null) ? $data['metadata'] : []
        );

        $target->update([
            'platform_user_ids' => $platformIds,
            'phone' => $target->phone ?: $phone,
            'email' => $target->email ?: $email,
            'avatar_url' => $target->avatar_url ?: ($data['avatar_url'] ?? null),
            'metadata' => $metadata !== [] ? $metadata : null,
            'global_user_id' => $target->global_user_id ?: $source->global_user_id ?: (string) Str::uuid(),
        ]);

        Conversation::query()
            ->where('customer_id', $target->id)
            ->update(['customer_id' => $target->id]);

        $source->delete();

        return $target->fresh();
    }

    private function applyIdentityData(
        Customer $customer,
        string $platform,
        string $platformId,
        ?string $phone,
        ?string $email,
        array $data
    ): void {
        $platformIds = is_array($customer->platform_user_ids) ? $customer->platform_user_ids : [];
        $platformIds[$platform] = $platformId;

        $metadata = array_merge(
            is_array($customer->metadata) ? $customer->metadata : [],
            is_array($data['metadata'] ?? null) ? $data['metadata'] : []
        );

        $customer->update([
            'platform_user_ids' => $platformIds,
            'phone' => $customer->phone ?: $phone,
            'email' => $customer->email ?: $email,
            'avatar_url' => $customer->avatar_url ?: ($data['avatar_url'] ?? null),
            'metadata' => $metadata !== [] ? $metadata : null,
            'global_user_id' => $customer->global_user_id ?: (string) Str::uuid(),
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }
}
