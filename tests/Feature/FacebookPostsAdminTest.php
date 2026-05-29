<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FacebookPostsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_publish_requires_media(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.facebook-posts.store'), [
                'message' => 'Test',
                'post_to_instagram' => 1,
                'media_type' => 'none',
                'action' => 'publish',
            ])
            ->assertStatus(302)
            ->assertSessionHas('error');
    }

    public function test_video_upload_requires_mp4(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $file = UploadedFile::fake()->create('test.txt', 10, 'text/plain');

        $this->actingAs($admin)
            ->postJson(route('admin.media.upload-video'), ['video' => $file])
            ->assertStatus(422);
    }
}

