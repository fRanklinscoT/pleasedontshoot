<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http; // Import Http facade for making requests
use function Livewire\Volt\{state};

new class extends Component {
    public $challenges;

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        $userId = Auth::id();
        $this->challenges = Challenge::where('opponent_id', $userId)->where('status', 'pending')->get();
    }

    public function acceptChallenge($challengeId)
    {
        $challenge = Challenge::find($challengeId);
        if ($challenge) {
            $challenge->update(['status' => 'accepted']);
            $this->sendDiscordNotification("Challenge from {$challenge->challenger->name} has been **Accepted** by {$challenge->opponent->name}.");

            $this->dispatch('challenge-updated'); // Dispatch event to notify success
            $this->loadChallenges(); // Reload challenges
        }
    }

    public function declineChallenge($challengeId)
    {
        $challenge = Challenge::find($challengeId);
        if ($challenge) {
            $challenge->update(['status' => 'declined']);
            $this->sendDiscordNotification("Challenge from {$challenge->challenger->name} has been **Declined** by {$challenge->opponent->name}.");

            $this->dispatch('challenge-updated'); // Dispatch event to notify success
            $this->loadChallenges(); // Reload challenges
        }
    }

    private function sendDiscordNotification($message)
    {
        // Assuming you have your Discord webhook URL set in your .env file
        $webhookUrl = env('DISCORD_WEBHOOK');

        Http::post($webhookUrl, [
            'content' => $message,
        ]);
    }
};
?>

<div x-data="{ showSuccess: false }" x-init="$wire.on('challenge-updated', () => {
    showSuccess = true;
    setTimeout(() => showSuccess = false, 3000);
})" class="container mt-1 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h1 class="text-xl">Pending Challenges</h1>
    <div class="mx-auto p-5">
        @if ($challenges->isEmpty())
            <p class="text-gray-600 dark:text-gray-400">No pending challenges.</p>
        @else
            <ul class="list-disc">
                @foreach ($challenges as $challenge)
                    <li class="mb-3">
                        <p class="text-gray-800 dark:text-white">Challenge from:
                            <strong>{{ optional($challenge->challenger)->name }}</strong>
                        </p>
                        <p class="text-gray-600 dark:text-gray-400">Banned Agent:
                            <strong>{{ $challenge->banned_agent }}</strong>
                        </p>
                        <div class="flex space-x-2">
                            <button
                                class="px-4 py-2 font-semibold text-white bg-green-600 rounded hover:bg-green-700 transition"
                                wire:click="acceptChallenge({{ $challenge->id }})"> Accept </button>
                            <button
                                class="px-4 py-2 font-semibold text-white bg-red-600 rounded hover:bg-red-700 transition"
                                wire:click="declineChallenge({{ $challenge->id }})"> Decline </button>
                        </div>
                    </li>
                @endforeach
            </ul>

        @endif

        <div x-show="showSuccess" x-transition.duration.300ms
            class="alert alert-success mt-3 bg-green-200 text-green-800 p-3 rounded">
            Challenge updated successfully!
        </div>
    </div>
</div>
