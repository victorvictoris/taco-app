<?php

namespace App\Http\Controllers;

use App\Models\SlackEvent;
use App\Models\Taco;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SlackController extends Controller
{
    public function handleEvent(Request $request, SlackService $slackService)
    {
        // Provera izazova od strane Slack API-ja
        if ($request->has('challenge')) {
            return response($request->challenge, 200);
        }

        if ($request->type === 'event_callback') {
            $event = $request->event;

            // Pronađi odgovarajući ID (client_msg_id ili event_ts)
            $eventId = $event['client_msg_id'] ?? $event['event_ts'];

            // Provera da li je događaj već obrađen
            if ($this->isEventProcessed($eventId)) {
                return response()->json(['status' => 'SlackEvent already processed']);
            }

            if ($event['type'] === 'message' && !isset($event['subtype'])) {
                return $this->processMessageEvent($event, $slackService, $eventId);
            }
        }

        return response()->json(['status' => 'SlackEvent received']);
    }

    private function isEventProcessed($eventId): bool
    {
        if (SlackEvent::where('slack_event_id', $eventId)->exists()) {
            Log::info('SlackEvent is already processed: ' . $eventId);
            return true;
        }
        return false;
    }

    private function processMessageEvent($event, SlackService $slackService, $eventId)
    {
        $tacoCount = substr_count($event['text'], ':taco:');

        // Obavi provere i obaveštenja
        if ($this->handleTacoEmojiCheck($tacoCount, $event, $slackService)) {
            return response()->json(['status' => 'Validation failed']);
        }

        $giverSlackId = $event['user'];
        preg_match_all('/<@(\w+)>/', $event['text'], $matches);

        if (empty($matches[1])) {
            $slackService->sendEphemeralMessage(
                $event['channel'],
                $giverSlackId,
                "🚫 Nije pronađen primaoc takosa. Koristi @username da dodeliš takos!"
            );
            return response()->json(['status' => 'No recipient found']);
        }

        // Provera da davalac ne pokušava sebi da dodeli takos
        if (in_array($giverSlackId, $matches[1])) {
            $slackService->sendEphemeralMessage(
                $event['channel'],
                $giverSlackId,
                "🚫 Ne mogu da verujem da si sam sebi pokušao da udeliš takos! 🤦 🤦‍"
            );
            return response()->json(['status' => 'Self-taco assignment not allowed']);
        }

        // Dohvati davaoca
        $giver = $this->getUser($giverSlackId, $slackService);

        // Proveri da li davalac ima dovoljno takosa za sve primaoce
        $totalTacosNeeded = count($matches[1]) * $tacoCount;
        if ($giver->remaining_tacos < $totalTacosNeeded) {
            $slackService->sendEphemeralMessage(
                $event['channel'],
                $giver->slack_id,
                "🚫 Nemaš dovoljno takosa! Imaš samo {$giver->remaining_tacos} takosa preostalih."
            );
            return response()->json(['status' => 'Not enough tacos']);
        }

        // Kreiraj ili dohvatimo SlackEvent zapis za ovaj eventId
        $eventRecord = SlackEvent::firstOrCreate(['slack_event_id' => $eventId]);

        // Iteriraj kroz svakog primaoca i dodeli takose
        foreach ($matches[1] as $receiverSlackId) {
            $receiver = $this->getUser($receiverSlackId, $slackService);
            $this->assignTacos($giver, $receiver, $tacoCount, $event['text'], $slackService, $event['channel'], $eventRecord->id);
        }

        return response()->json(['status' => 'Tacos assigned to multiple recipients']);
    }

    private function handleTacoEmojiCheck($tacoCount, $event, $slackService): bool
    {
        if ($tacoCount === 0) {
            $slackService->sendEphemeralMessage(
                $event['channel'],
                $event['user'],
                "🚫 Dodaj 🌮 emoji!"
            );
            return true;
        }

        if ($tacoCount > 5) {
            $slackService->sendEphemeralMessage(
                $event['channel'],
                $event['user'],
                "🚫 Ne možeš dodeliti više od 5 takosa odjednom!"
            );
            return true;
        }

        return false;
    }

    private function getUser($slackId, SlackService $slackService)
    {
        $userInfo = $slackService->getUserInfo($slackId);
        return User::firstOrCreate(
            ['slack_id' => $slackId],
            [
                'name' => $userInfo['name'] ?? 'Unknown User',
                'email' => $userInfo['email'] ?? Str::random(10) . '@example.com',
                'password' => Hash::make(Str::random(16)),
                'remaining_tacos' => 5
            ]
        );
    }

    private function assignTacos($giver, $receiver, $tacoCount, $message, $slackService, $channel, $eventId)
    {
        // Smanji broj preostalih takosa za davaoca za trenutnog primaoca
        $giver->remaining_tacos -= $tacoCount;
        $giver->save();

        Taco::create([
            'giver_id' => $giver->id,
            'receiver_id' => $receiver->id,
            'message' => $message,
            'number_of_given_tacos' => $tacoCount,
            'slack_event_id' => $eventId,
        ]);

        // Notifikacija za primaoca takosa
        $slackService->sendDirectMessageToUser(
            $receiver->slack_id,
            "@{$giver->name} ti je poslao {$tacoCount} 🌮 takos(a)!"
        );

        // Notifikacija za davaoca takosa
        $slackService->sendDirectMessageToUser(
            $giver->slack_id,
            "Poslao si {$tacoCount} 🌮 takosa korisniku @{$receiver->name}! Preostalo ti je {$giver->remaining_tacos} takosa za dodelu danas."
        );

        // Ažuriraj App Home za oba korisnika
        $this->updateAppHomeForUsers($giver, $receiver, $slackService);
    }

    private function updateAppHomeForUsers($giver, $receiver, SlackService $slackService)
    {
        // Ažuriraj App Home za primaoca
        $slackService->updateAppHome(
            $receiver->slack_id,
            Taco::where('receiver_id', $receiver->id)->sum('number_of_given_tacos'), // Ukupno primljenih takosa
            Taco::where('giver_id', $receiver->id)->sum('number_of_given_tacos'), // Ukupno dodeljenih takosa od strane primaoca
            $receiver->remaining_tacos
        );

        // Ažuriraj App Home za davaoca
        $slackService->updateAppHome(
            $giver->slack_id,
            Taco::where('receiver_id', $giver->id)->sum('number_of_given_tacos'), // Ukupno primljenih takosa
            Taco::where('giver_id', $giver->id)->sum('number_of_given_tacos'), // Ukupno dodeljenih takosa
            $giver->remaining_tacos
        );
    }
}
