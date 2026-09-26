<?php

namespace Tests\Unit;

use App\Models\FacebookPost;
use App\Models\User;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use App\Services\SocialPostPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SocialPostPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_failure_keeps_successful_channel_without_duplicate_post(): void
    {
        $post = FacebookPost::create(['user_id' => User::factory()->create()->id, 'message' => 'Test', 'media_type' => 'image', 'image_url' => 'https://cdn.example.test/image.jpg', 'post_to_facebook' => true, 'post_to_instagram' => true, 'status' => 'draft']);
        $facebook = Mockery::mock(FacebookPageService::class);
        $facebook->shouldReceive('isConfigured')->once()->andReturn(true);
        $facebook->shouldReceive('publishPost')->once()->andReturn(['success' => true, 'post_id' => 'fb-1']);
        $instagram = Mockery::mock(InstagramPageService::class);
        $instagram->shouldReceive('isConfigured')->once()->andReturn(true);
        $instagram->shouldReceive('publishPost')->once()->andReturn(['success' => false, 'error' => 'temporary failure']);
        $result = (new SocialPostPublisher($facebook, $instagram))->publish($post);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('facebook_posts', ['id' => $post->id, 'facebook_post_id' => 'fb-1', 'facebook_publish_status' => 'published', 'instagram_publish_status' => 'failed']);
    }
}
