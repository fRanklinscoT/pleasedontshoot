<?php

namespace App\Livewire;

use App\Models\Challenge;
use Livewire\Component;

class ChallengeList extends Component
{
    public $challenges;

    public function mount()
    {
        $this->challenges = Challenge::with(['challanger', 'opponent', 'witnes'])->get();
    }
    public function render()
    {
        return view('livewire.challenge-list', ['challenges' => $this->challenges]);
    }
}
