<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    protected $token;

    public function __construct()
    {
        $this->token = config('services.slack.token');
    }

    public function sendMessage($channel, $text)
    {
        $response = Http::withToken($this->token)->post('https://slack.com/api/chat.postMessage', [
            'channel' => $channel,
            'text' => $text,
        ]);

        $data = $response->json();
        //Log::info('Slack API Response:', $data);

        return $data;
    }

    public function getUserInfo($slackId)
    {
        $response = Http::withToken($this->token)->get("https://slack.com/api/users.info", [
            'user' => $slackId,
        ]);

        $data = $response->json();
        //Log::info($data);

        if ($data['ok']) {
            $user = $data['user'];
            return [
                'name' => $user['real_name'] ?? 'Unknown',
                'email' => $user['profile']['email'] ?? null,
            ];
        }

        Log::error("Failed to fetch user info for Slack ID {$slackId}", ['response' => $data]);
        return null;
    }

    public function updateAppHome($userId, $receivedTacos, $givenTacos, $remainingTacos)
    {
        // Dohvati top 20 korisnika po broju primljenih takosa
        $leaderboard = User::withSum('receivedTacos', 'number_of_given_tacos')
            ->having('received_tacos_sum_number_of_given_tacos', '>', 0)
            ->orderBy('received_tacos_sum_number_of_given_tacos', 'desc')
            ->limit(20)
            ->get();

        // Formiraj Leaderboard prikaz
        $leaderboardText = "";
        foreach ($leaderboard as $index => $user) {
            $leaderboardText .= ($index + 1) . ". @" . $user->name . " - " . $user->received_tacos_sum_number_of_given_tacos . " 🌮\n";
        }

        //Log::info($leaderboardText);

        // Izgradi blokove za Home tab sa osnovnim informacijama i leaderboard-om
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Dobrodošao u TacoApp!*"
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Tvoj profil*\nPrimljeni takosi: $receivedTacos 🌮\nDodeljeni takosi: $givenTacos 🌮\nPreostali takosi za danas: $remainingTacos 🌮"
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '🏆 *Leaderboard* 🏆'
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $leaderboardText
                ]
            ],
            [
                'type' => 'divider'
            ],
        ];

        // Ažuriraj Home tab korisnika
        $response = Http::withToken($this->token)->post('https://slack.com/api/views.publish', [
            'user_id' => $userId,
            'view' => [
                'type' => 'home',
                'blocks' => $blocks
            ]
        ]);

        //Log::info($response);
    }

    public function sendDirectMessageToUser($userSlackId, $text)
    {
        // Otvorite DM kanal sa korisnikom
        $response = Http::withToken($this->token)->post('https://slack.com/api/conversations.open', [
            'users' => $userSlackId,
        ]);

        $data = $response->json();
        //Log::info('Ovo je sendDirectMessageToUser:'. $response);
        if ($data['ok'] && isset($data['channel']['id'])) {
            $channelId = $data['channel']['id'];

            // Pošaljite poruku unutar DM kanala
            $response = Http::withToken($this->token)->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text' => $text,
            ]);

            $data = $response->json();
            //Log::info('Slack Direct Message Response:', $data);

            return $data;
        }

        Log::error("Failed to open DM channel with user $userSlackId", ['response' => $data]);
        return null;
    }

    public function sendEphemeralMessage($channel, $user, $text)
    {
        $response = Http::withToken($this->token)->post('https://slack.com/api/chat.postEphemeral', [
            'channel' => $channel,
            'user' => $user,
            'text' => $text,
        ]);

        $data = $response->json();
        //Log::info('Slack Ephemeral Message Response:', $data);

        return $data;
    }

}
