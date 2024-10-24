<?php

use Livewire\Volt\Component;
use App\Models\User;
use function Livewire\Volt\{state};

new class extends Component {
    public $rankedUsers;

    public function mount()
    {
        $this->loadRankedUsers();
    }

    public function loadRankedUsers()
    {
        // Fetch users ordered by rank in descending order
        $this->rankedUsers = User::orderBy('rank', 'asc')->get();
    }
};
?>

<div class="container mt-1 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h1 class="text-xl">Ranked List of Users</h1>
    <div class="mx-auto p-5">
        @if ($rankedUsers->isEmpty())
            <p class="text-gray-600 dark:text-gray-400">No users found.</p>
        @else
            <ul class="list-decimal">
                @foreach ($rankedUsers as $user)
                    <li class="mb-3">
                        <p class="text-gray-800 dark:text-white">
                            <strong>{{ $user->name }} (Alias: {{ $user->alias }})</strong> - Rank:
                            {{ $user->rank }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
