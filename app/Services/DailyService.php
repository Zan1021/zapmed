<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DailyService
{
    private string $apiKey;
    private string $domain;
    private string $baseUrl = 'https://api.daily.co/v1';

    public function __construct()
    {
        $this->apiKey = config('services.daily.api_key');
        $this->domain = config('services.daily.domain');
    }

    /**
     * Create a new Daily.co room for a consultation.
     *
     * @param string $appointmentReference Used to generate a unique room name
     * @param int $expiryMinutes How long the room should stay active
     * @return array{name: string, url: string}
     */
    public function createRoom(string $appointmentReference, int $expiryMinutes = 120): array
    {
        $roomName = 'zap-' . Str::lower(Str::replace('-', '', $appointmentReference)) . '-' . Str::random(4);

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/rooms", [
                'name' => $roomName,
                'privacy' => 'private', // Requires token to join
                'properties' => [
                    'exp' => now()->addMinutes($expiryMinutes)->timestamp,
                    'max_participants' => 2,
                    'enable_chat' => true,
                    'enable_screenshare' => true,
                    'enable_knocking' => false,
                    'start_video_off' => false,
                    'start_audio_off' => false,
                    'eject_at_room_exp' => true,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Failed to create Daily.co room: ' . $response->body()
            );
        }

        $data = $response->json();

        return [
            'name' => $data['name'],
            'url' => $data['url'],
        ];
    }

    /**
     * Generate a meeting token for a participant.
     *
     * @param string $roomName The room to join
     * @param string $userName Display name in the call
     * @param bool $isOwner Whether this participant has owner privileges (doctor)
     * @param int $expiryMinutes Token validity
     * @return string The meeting token
     */
    public function createMeetingToken(
        string $roomName,
        string $userName,
        bool $isOwner = false,
        int $expiryMinutes = 120
    ): string {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/meeting-tokens", [
                'properties' => [
                    'room_name' => $roomName,
                    'user_name' => $userName,
                    'is_owner' => $isOwner,
                    'exp' => now()->addMinutes($expiryMinutes)->timestamp,
                    'enable_screenshare' => true,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Failed to create Daily.co meeting token: ' . $response->body()
            );
        }

        return $response->json('token');
    }

    /**
     * Delete a room when the consultation ends.
     *
     * @param string $roomName
     * @return bool
     */
    public function deleteRoom(string $roomName): bool
    {
        $response = Http::withToken($this->apiKey)
            ->delete("{$this->baseUrl}/rooms/{$roomName}");

        return $response->successful();
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->domain);
    }
}
