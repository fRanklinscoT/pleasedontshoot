<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state};
use Illuminate\Support\Facades\Http;

new class extends Component {
    public $challenger_id;
    public $opponent_id = null;
    public $witness_id = null;
    public $banned_agent = '';
    public $status = 'pending';
    public $step = 1;
    public $challengeCreated = false;
    public $loading = false;

    #[state]
    public bool $opponentSelected = false;

    #[state]
    public bool $witnessSelected = false;

    public function mount()
    {
        $this->challenger_id = Auth::id();
    }

    #[Computed]
    public function availableOpponents()
    {
        $challenger = Auth::user();
        return User::where('rank', '>=', $challenger->rank - 1)
            ->where('rank', '<=', $challenger->rank + 1)
            ->where('id', '!=', $challenger->id)
            ->get();
    }

    #[Computed]
    public function availableWitnesses()
    {
        return User::where('id', '!=', $this->challenger_id)
            ->where('id', '!=', $this->opponent_id)
            ->get();
    }

    public function confirmChallenge()
    {
        $this->step = 2;
    }

    public function backToSelection()
    {
        $this->step = 1;
    }

    public function createChallenge()
    {
        $this->validate([
            'opponent_id' => 'required|exists:users,id',
            'witness_id' => 'required|exists:users,id',
            'banned_agent' => 'nullable|string|max:255',
        ]);

        // Check for existing challenges
        $existingChallenge = Challenge::where('challenger_id', $this->challenger_id)
            ->where('opponent_id', $this->opponent_id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingChallenge) {
            $this->dispatch('challenge-exists', [
                'status' => $existingChallenge->status,
                'message' => 'A challenge has already been sent to ' . optional(User::find($this->opponent_id))->name . ' with status: **' . $existingChallenge->status . '**.',
            ]);
            $this->resetState(); // Reset steps on error
            return;
        }

        $this->loading = true;

        // Create the challenge
        $challenge = Challenge::create([
            'challenger_id' => $this->challenger_id,
            'opponent_id' => $this->opponent_id,
            'witness_id' => $this->witness_id,
            'banned_agent' => $this->banned_agent,
            'status' => $this->status,
        ]);

        // Send notification to Discord
        $this->sendToDiscord($challenge);

        $this->challengeCreated = true;
        $this->dispatch('challenge-created');

        // Resetting state after challenge creation
        $this->resetState();
    }

    protected function resetState()
    {
        $this->reset('opponent_id', 'witness_id', 'banned_agent');
        $this->step = 1; // Reset to the first step
    }

    protected function sendToDiscord($challenge)
    {
        $webhookUrl = env('DISCORD_WEBHOOK');

        $message = [
            'content' => "A new challenge has been issued!\n" . 'Challenger: **' . Auth::user()->name . "**\n" . 'Opponent: **' . optional(User::find($challenge->opponent_id))->name . "**\n" . 'Witness: **' . optional(User::find($challenge->witness_id))->name . "**\n" . 'Banned Agent: **' . ($challenge->banned_agent ?: 'None') . '**',
        ];

        Http::post($webhookUrl, $message);
    }

    public function updatedOpponentId()
    {
        $this->opponentSelected = $this->opponent_id !== null;
    }

    public function updatedWitnessId()
    {
        $this->witnessSelected = $this->witness_id !== null;
    }
};
?>

<div x-data="{ showSuccess: false, showError: false, loading: false, errorMessage: '' }" x-init="$wire.on('challenge-created', () => {
    showSuccess = true;
    setTimeout(() => showSuccess = false, 3000);
});
$wire.on('challenge-exists', (data) => {
    showError = true;
    errorMessage = data.message;
    setTimeout(() => showError = false, 3000);
})" class="container mt-1 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h1 class="text-xl">New Challenge</h1>
    <div class="mx-auto p-5">
        <button class="px-4 py-2 font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 transition"
            wire:click="$set('step', 1)">
            New Challenge
        </button>

        @if ($step == 1)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Select an Opponent</h4>
                @if ($this->availableOpponents->isEmpty())
                    <p class="text-red-500">No opponents available to challenge.</p>
                @else
                    <div class="mb-3">
                        <label for="opponent_id" class="block text-gray-700 dark:text-gray-300">Available
                            Opponents</label>
                        <select
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                            wire:model.live="opponent_id" id="opponent_id">
                            <option value="">Choose an opponent</option>
                            @foreach ($this->availableOpponents as $player)
                                <option value="{{ $player->id }}">{{ $player->name }} (Rank: {{ $player->rank }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($opponentSelected)
                        <button
                            class="px-4 py-2 font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 transition"
                            wire:click.prevent="confirmChallenge">
                            Next: Confirm Challenge
                        </button>
                    @endif
                @endif
            </div>
        @endif

        @if ($step == 2)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Confirm Challenge</h4>
                <p class="text-gray-600 dark:text-gray-400">Challenger: <strong>{{ Auth::user()->name }}</strong></p>
                <p class="text-gray-600 dark:text-gray-400">Opponent:
                    <strong>{{ optional(User::find($opponent_id))->name }}</strong>
                </p>

                <div class="mb-3">
                    <label for="witness_id" class="block text-gray-700 dark:text-gray-300">Witness</label>
                    <select
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                        wire:model.live="witness_id">
                        <option value="">Select Witness</option>
                        @foreach ($this->availableWitnesses as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="banned_agent" class="block text-gray-700 dark:text-gray-300">Banned Agent
                        (Optional)</label>
                    <input type="text"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                        wire:model="banned_agent" placeholder="Enter banned agent (optional)">
                </div>

                <div class="flex justify-between mt-4">
                    <button
                        class="px-4 py-2 font-semibold text-gray-700 bg-gray-300 rounded hover:bg-gray-400 transition"
                        wire:click="backToSelection">
                        Back
                    </button>

                    @if ($witnessSelected)
                        <button
                            class="px-4 py-2 font-semibold text-white bg-green-600 rounded hover:bg-green-700 transition"
                            wire:click="createChallenge" wire:loading.attr="disabled">
                            Confirm & Create Challenge
                        </button>
                        <div wire:loading class="ml-2 text-gray-500">Creating...</div>
                    @endif
                </div>
            </div>
        @endif

        <div x-show="showSuccess" x-transition.duration.300ms
            class="alert alert-success mt-3 bg-green-200 text-green-800 p-2 rounded">
            Challenge created successfully!
        </div>


        <div x-show="showError" x-transition.duration.300ms
            class="alert alert-success mt-3 bg-rose-700 text-white p-3 rounded">
            A Challange has already been made...
        </div>
    </div>
</div>
