<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FacebookMessengerService
{
    private $pageAccessToken;
    private $apiVersion = 'v18.0';
    private $graphApiUrl;

    public function __construct()
    {
        $this->pageAccessToken = config('services.facebook.page_access_token');
        $this->graphApiUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Send a text message to a Facebook user
     *
     * @param string $recipientId Facebook user ID (PSID)
     * @param string $message Message text
     * @return array Response from Facebook API
     */
    public function sendMessage($recipientId, $message)
    {
        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'recipient' => [
                    'id' => $recipientId
                ],
                'message' => [
                    'text' => $message
                ],
                'access_token' => $this->pageAccessToken
            ]);

            $result = $response->json();

            if ($response->successful()) {
                Log::info('Facebook message sent successfully', [
                    'recipient_id' => $recipientId,
                    'message_id' => $result['message_id'] ?? null
                ]);
                return [
                    'success' => true,
                    'message_id' => $result['message_id'] ?? null,
                    'data' => $result
                ];
            } else {
                Log::error('Failed to send Facebook message', [
                    'recipient_id' => $recipientId,
                    'error' => $result
                ]);
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Unknown error'
                ];
            }
        } catch (Exception $e) {
            Log::error('Exception while sending Facebook message', [
                'recipient_id' => $recipientId,
                'exception' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send typing indicator to show bot is typing
     *
     * @param string $recipientId Facebook user ID (PSID)
     * @param string $action 'typing_on' or 'typing_off'
     * @return array Response from Facebook API
     */
    public function sendTypingIndicator($recipientId, $action = 'typing_on')
    {
        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'recipient' => [
                    'id' => $recipientId
                ],
                'sender_action' => $action,
                'access_token' => $this->pageAccessToken
            ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json()
            ];
        } catch (Exception $e) {
            Log::error('Exception while sending typing indicator', [
                'recipient_id' => $recipientId,
                'exception' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mark message as seen/read
     *
     * @param string $recipientId Facebook user ID (PSID)
     * @return array Response from Facebook API
     */
    public function markSeen($recipientId)
    {
        return $this->sendTypingIndicator($recipientId, 'mark_seen');
    }

    /**
     * Send message with attachment (image, file, etc.)
     *
     * @param string $recipientId Facebook user ID (PSID)
     * @param string $attachmentType 'image', 'audio', 'video', 'file'
     * @param string $attachmentUrl URL of the attachment
     * @return array Response from Facebook API
     */
    public function sendAttachment($recipientId, $attachmentType, $attachmentUrl)
    {
        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'recipient' => [
                    'id' => $recipientId
                ],
                'message' => [
                    'attachment' => [
                        'type' => $attachmentType,
                        'payload' => [
                            'url' => $attachmentUrl,
                            'is_reusable' => true
                        ]
                    ]
                ],
                'access_token' => $this->pageAccessToken
            ]);

            $result = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $result['message_id'] ?? null,
                    'data' => $result
                ];
            } else {
                Log::error('Failed to send Facebook attachment', [
                    'recipient_id' => $recipientId,
                    'error' => $result
                ]);
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Unknown error'
                ];
            }
        } catch (Exception $e) {
            Log::error('Exception while sending Facebook attachment', [
                'recipient_id' => $recipientId,
                'exception' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user profile information from Facebook
     *
     * @param string $userId Facebook user ID (PSID)
     * @return array User profile data
     */
    public function getUserProfile($userId)
    {
        try {
            $response = Http::get("{$this->graphApiUrl}/{$userId}", [
                'fields' => 'first_name,last_name,profile_pic',
                'access_token' => $this->pageAccessToken
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->json()
                ];
            }
        } catch (Exception $e) {
            Log::error('Exception while fetching user profile', [
                'user_id' => $userId,
                'exception' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
