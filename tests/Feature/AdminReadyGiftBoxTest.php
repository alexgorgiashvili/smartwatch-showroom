<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ReadyGiftBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReadyGiftBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_box_with_cover_and_stable_item_ids_on_text_update(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $main = $this->product('Admin Main', 'main');
        $addon = $this->product('Admin Add-on', 'addon');

        $this->actingAs($admin)->post(route('admin.gift-boxes.store'), $this->payload($main, $addon, [
            'hero_image' => UploadedFile::fake()->image('cover.webp', 1200, 900),
        ]))->assertRedirect();

        $box = ReadyGiftBox::query()->where('slug', 'admin-ready-box')->with('items')->firstOrFail();
        $this->assertCount(2, $box->items);
        $this->assertSame(1, $box->items->where('role', 'main')->count());
        Storage::disk('public')->assertExists($box->cover_image_path);
        $itemIds = $box->items->pluck('id', 'product_id')->all();

        $this->actingAs($admin)->put(route('admin.gift-boxes.update', $box), $this->payload($main, $addon, [
            'title_ka' => 'განახლებული სათაური',
        ]))->assertRedirect(route('admin.gift-boxes.edit', $box));

        $box->refresh()->load('items');
        $this->assertSame('განახლებული სათაური', $box->title_ka);
        $this->assertSame($itemIds, $box->items->pluck('id', 'product_id')->all());
        Storage::disk('public')->assertExists($box->cover_image_path);
    }

    public function test_invalid_draft_cannot_be_activated_and_admin_preview_grants_session_without_key(): void
    {
        config()->set('gift_builder.enabled', true);
        config()->set('gift_builder.public_enabled', false);
        $admin = User::factory()->create(['is_admin' => true]);
        $main = $this->product('Out of Stock Main', 'main', quantity: 0);
        $box = ReadyGiftBox::query()->create([
            'slug' => 'draft-box',
            'title_ka' => 'Draft Box',
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'none',
            'is_active' => false,
        ]);
        $box->items()->create([
            'product_id' => $main->id,
            'default_variant_id' => $main->variants->first()->id,
            'role' => 'main',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.gift-boxes.toggle-status', $box))
            ->assertRedirect(route('admin.gift-boxes.edit', $box))
            ->assertSessionHasErrors('is_active');
        $this->assertFalse($box->fresh()->is_active);

        $this->actingAs($admin)
            ->get(route('admin.gift-boxes.preview-box', $box))
            ->assertRedirect(route('gift-builder.boxes'))
            ->assertSessionHas('gift_builder_preview_access', true);
        $this->get(route('gift-builder.boxes'))->assertOk();
    }

    public function test_product_used_by_ready_box_returns_conflict_before_delete(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $main = $this->product('Protected Main', 'main');
        $box = ReadyGiftBox::query()->create([
            'slug' => 'protected-box',
            'title_ka' => 'Protected Box',
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'none',
        ]);
        $box->items()->create([
            'product_id' => $main->id,
            'default_variant_id' => $main->variants->first()->id,
            'role' => 'main',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.products.destroy', $main))
            ->assertStatus(409);

        $this->assertDatabaseHas('products', ['id' => $main->id]);
    }

    public function test_deleting_a_ready_box_removes_its_items_and_cover(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $main = $this->product('Disposable Main', 'main');
        $coverPath = 'ready-gift-boxes/disposable.webp';
        Storage::disk('public')->put($coverPath, 'image-bytes');

        $box = ReadyGiftBox::query()->create([
            'slug' => 'disposable-box',
            'title_ka' => 'Disposable Box',
            'cover_image_path' => $coverPath,
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'none',
        ]);
        $item = $box->items()->create([
            'product_id' => $main->id,
            'default_variant_id' => $main->variants->first()->id,
            'role' => 'main',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.gift-boxes.destroy', $box))
            ->assertRedirect(route('admin.gift-boxes.index'));

        $this->assertDatabaseMissing('ready_gift_boxes', ['id' => $box->id]);
        $this->assertDatabaseMissing('ready_gift_box_items', ['id' => $item->id]);
        $this->assertFalse($main->readyGiftBoxItems()->exists());
        Storage::disk('public')->assertMissing($coverPath);
    }

    private function product(string $name, string $role, int $quantity = 5): Product
    {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'price' => 100,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => $role,
            'gift_capacity_units' => 1,
        ]);
        $product->variants()->create([
            'name' => 'Default',
            'quantity' => $quantity,
            'low_stock_threshold' => 1,
        ]);

        return $product->fresh('variants');
    }

    private function payload(Product $main, Product $addon, array $overrides = []): array
    {
        return array_replace([
            'slug' => 'admin-ready-box',
            'title_ka' => 'Admin Ready Box',
            'title_en' => 'Admin Ready Box',
            'short_description_ka' => 'აღწერა',
            'short_description_en' => 'Description',
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'sort_order' => 10,
            'is_active' => 1,
            'is_featured' => 1,
            'main_product_id' => $main->id,
            'main_default_variant_id' => $main->variants->first()->id,
            'addons' => [[
                'product_id' => $addon->id,
                'default_variant_id' => $addon->variants->first()->id,
            ]],
        ], $overrides);
    }
}
