<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    public function sendToDiscord(Request $request)
    {
        // Retrieve the challenge details from the request
        $challenge = $request->input('challenge');

        // Define the Discord webhook URL (replace with your actual webhook URL)
        $webhookUrl = env('DISCORD_WEBHOOK');

        // Construct the message to send to Discord
        // $message = [
        //     'content' => "A new challenge has been issued!\n" .
        //         "Challenger: {$challenge['challenger_name']}\n" .
        //         "Opponent: {$challenge['opponent_name']}\n" .
        //         "Witness: {$challenge['witness_name']}\n" .
        //         "Status: {$challenge['status']}\n" .
        //         "Banned Agent: {$challenge['banned_agent']}"
        // ];

        $message = [
            'content' => "A new challenge has been issued!\n",
            "embeds" => [
                [
                    "title" => "Embed Title",
                    "description" => "This is the description",
                    "url" => "https://example.com",
                    "color" => 15258703,
                    "fields" => [
                        [
                            "name" => "Field 1",
                            "value" => "Some value",
                            "inline" => true
                        ],
                        [
                            "name" => "Field 2",
                            "value" => "Another value",
                            "inline" => true
                        ]
                    ],
                    "image" => [
                        "url" => "https://cdn.pixabay.com/photo/2020/01/02/16/38/chatbot-4736275_1280.png"
                    ],
                    "footer" => [
                        "text" => "Footer text",
                        "icon_url" => "https://example.com/footer-icon.png"
                    ]
                ]
            ]
        ];


        // Send the message to Discord using HTTP POST
        $response = Http::post($webhookUrl, $message);

        if ($response->successful()) {
            return response()->json(['message' => 'Notification sent to Discord'], 200);
        } else {
            return response()->json(['error' => 'Failed to send notification'], 500);
        }
    }
}
