<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\User;
use App\Models\RankHistory;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $challenges;
    public $selectedChallenge;
    public $winnerId = null; // Initialize winnerId

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        $userId = Auth::id();
        // Load accepted challenges where the current user is the witness
        $this->challenges = Challenge::where('witness_id', $userId)->where('status', 'accepted')->get();
    }

    public function submitOutcome($challengeId)
    {
        // dd("Submit outcome called for challenge ID: $challengeId");

        $this->validate([
            'winnerId' => 'required|exists:users,id', // Validate winnerId
        ]);

        $challenge = Challenge::find($challengeId);
        if ($challenge && $challenge->status === 'accepted') {
            $winner = User::find($this->winnerId);
            if (!$winner) {
                session()->flash('error', 'Invalid winner selected.');
                return;
            }

            $loser = $winner->id === $challenge->challenger_id ? User::find($challenge->opponent_id) : User::find($challenge->challenger_id);

            // Check if loser is found
            if ($loser) {
                $winnerRank = $winner->rank;
                $loserRank = $loser->rank;

                // Swap ranks only if the winner's rank is lower (numerically higher)
                if ($winnerRank > $loserRank) {
                    $this->swapRanks($winner, $loser, $challenge);
                }

                // Mark challenge as completed
                $challenge->update(['status' => 'completed']);

                $this->dispatch('challenge-completed'); // Dispatch event to notify success
                $this->loadChallenges(); // Reload challenges
            }
        }
    }
    protected function sendRankListToDiscord()
    {
        $webhookUrl = env('DISCORD_WEBHOOK');

        // Fetch the current rank list
        $users = User::orderBy('rank')->get(); // Assuming 'rank' is the column name for ranks
        $rankList = '';

        foreach ($users as $user) {
            $rankList .= '**Rank ' . $user->rank . '**: ' . $user->name . "\n";
        }

        $message = [
            'content' => "Updated Rank List:\n" . $rankList,
        ];

        Http::post($webhookUrl, $message);
    }

    private function swapRanks(User $winner, User $loser, Challenge $challenge)
    {
        $winnerPreviousRank = $winner->rank;
        $loserPreviousRank = $loser->rank;

        // Swap the ranks
        $winner->update(['rank' => $loserPreviousRank]);
        $loser->update(['rank' => $winnerPreviousRank]);

        // Log rank history for both users
        RankHistory::create([
            'user_id' => $winner->id,
            'previous_rank' => $winnerPreviousRank,
            'new_rank' => $loserPreviousRank,
            'challenge_id' => $challenge->id,
        ]);

        RankHistory::create([
            'user_id' => $loser->id,
            'previous_rank' => $loserPreviousRank,
            'new_rank' => $winnerPreviousRank,
            'challenge_id' => $challenge->id,
        ]);

        // Send the updated rank list to Discord
        $this->sendRankListToDiscord();
    }
};

?>

<div x-data="{ showSuccess: false }" x-init="$wire.on('challenge-completed', () => {
    showSuccess = true;
    setTimeout(() => showSuccess = false, 3000);
})" class="container mt-1 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h1 class="text-xl">Witness Challenges Outcomes</h1>
    <div class="mx-auto p-5">
        @if ($challenges->isEmpty())
            <p class="text-gray-600 dark:text-gray-400">No challenges for you to judge.</p>
        @else
            <ul class="list-disc">
                @foreach ($challenges as $challenge)
                    <li class="mb-3">
                        <p class="text-gray-800 dark:text-white">
                            Challenge between:
                            <strong>{{ optional($challenge->challenger)->name }}</strong>
                            and
                            <strong>{{ optional($challenge->opponent)->name }}</strong>
                        </p>
                        @if ($challenge->status === 'accepted')
                            <label for="winner-{{ $challenge->id }}"
                                class="block text-gray-700 dark:text-gray-300">Select Winner:</label>
                            <select id="winner-{{ $challenge->id }}" wire:model.live="winnerId"
                                class="block w-full px-4 py-2 border-gray-300 dark:bg-gray-700 rounded">
                                <option value="{{ $challenge->challenger_id }}">
                                    {{ optional($challenge->challenger)->name }}</option>
                                <option value="{{ $challenge->opponent_id }}">
                                    {{ optional($challenge->opponent)->name }}</option>
                            </select>
                            <button
                                class="px-4 py-2 font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 transition mt-2"
                                wire:click="submitOutcome({{ $challenge->id }})"> Submit Outcome </button>
                        @else
                            <p class="text-green-600 dark:text-green-400">Challenge has been completed.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div x-show="showSuccess" x-transition.duration.300ms
            class="alert alert-success mt-3 bg-green-200 text-green-800 p-3 rounded">
            Challenge outcome submitted successfully!
        </div>

        @if (session()->has('error'))
            <div class="alert alert-error mt-3 bg-red-200 text-red-800 p-3 rounded">
                {{ session('error') }}
            </div>
        @endif
    </div>
</div>
