<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function findById(int $id): ?Customer
    {
        return Customer::query()
            ->with(['conversations'])
            ->find($id);
    }

    public function findByPlatformId(string $platform, string $platformId): ?Customer
    {
        return Customer::query()
            ->whereJsonContains("platform_user_ids->{$platform}", $platformId)
            ->first();
    }

    public function findByEmail(string $email): ?Customer
    {
        return Customer::query()
            ->where('email', $email)
            ->first();
    }

    public function findByPhone(string $phone): ?Customer
    {
        return Customer::query()
            ->where('phone', $phone)
            ->first();
    }

    public function createOrUpdateCustomer(array $data): Customer
    {
        if (array_key_exists('avatar_url', $data)) {
            $data['avatar_url'] = $this->sanitizeAvatarUrl($data['avatar_url'] ?? null);
        }

        $platform = $data['platform'] ?? null;
        $platformId = $data['platform_id'] ?? null;

        if ($platform && $platformId) {
            $customer = $this->findByPlatformId($platform, $platformId);

            if ($customer) {
                $this->updateProfile($customer->id, $data);
                return $customer->fresh();
            }
        }

        if (isset($data['email']) && $data['email']) {
            $customer = $this->findByEmail($data['email']);

            if ($customer) {
                if ($platform && $platformId) {
                    $this->addPlatformId($customer->id, $platform, $platformId);
                }
                $this->updateProfile($customer->id, $data);
                return $customer->fresh();
            }
        }

        if (isset($data['phone']) && $data['phone']) {
            $customer = $this->findByPhone($data['phone']);

            if ($customer) {
                if ($platform && $platformId) {
                    $this->addPlatformId($customer->id, $platform, $platformId);
                }
                $this->updateProfile($customer->id, $data);
                return $customer->fresh();
            }
        }

        $platformUserIds = [];
        if ($platform && $platformId) {
            $platformUserIds[$platform] = $platformId;
        }

        return Customer::create([
            'name' => $data['name'] ?? 'Unknown Customer',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'platform_user_ids' => $platformUserIds,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function updateProfile(int $customerId, array $data): void
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['avatar_url'])) {
            $updateData['avatar_url'] = $this->sanitizeAvatarUrl($data['avatar_url']);
        }

        if (isset($data['metadata'])) {
            $customer = $this->findById($customerId);
            $existingMetadata = $customer->metadata ?? [];
            $updateData['metadata'] = array_merge($existingMetadata, $data['metadata']);
        }

        if (!empty($updateData)) {
            Customer::query()
                ->where('id', $customerId)
                ->update($updateData);
        }
    }

    public function addPlatformId(int $customerId, string $platform, string $platformId): void
    {
        $customer = $this->findById($customerId);

        if (!$customer) {
            return;
        }

        $platformUserIds = $customer->platform_user_ids ?? [];
        $platformUserIds[$platform] = $platformId;

        $customer->update(['platform_user_ids' => $platformUserIds]);
    }

    protected function sanitizeAvatarUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : null;

        if (!$url) {
            return null;
        }

        if (mb_strlen($url) <= 255) {
            return $url;
        }

        $withoutQuery = strtok($url, '?');
        if (is_string($withoutQuery) && mb_strlen($withoutQuery) <= 255) {
            return $withoutQuery;
        }

        return mb_substr($url, 0, 255);
    }

    public function search(string $query, int $limit = 50): Collection
    {
        return Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    public function getRecentCustomers(int $limit = 20): Collection
    {
        return Customer::query()
            ->has('conversations')
            ->withCount('conversations')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCustomerStats(int $customerId): array
    {
        $customer = $this->findById($customerId);

        if (!$customer) {
            return [];
        }

        return [
            'total_conversations' => $customer->conversations()->count(),
            'total_messages' => $customer->messages()->count(),
            'unread_messages' => $customer->conversations()->sum('unread_count'),
            'platforms' => array_keys($customer->platform_user_ids ?? []),
            'first_contact' => $customer->created_at,
            'last_contact' => $customer->updated_at,
        ];
    }
}
