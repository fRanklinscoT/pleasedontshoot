<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\challenge_limit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChallengeController extends Controller
{
    public function issueChallenge(Request $request)
    {
        $challenger = Auth::user();
        $opponent = User::find($request->input('opponent_id'));

        // Check if within 1 rank difference
        $rankDifference = abs($challenger->rank - $opponent->rank);

        if ($rankDifference > 2 && !$this->canIssueChallenge($challenger)) {
            return response()->json(['error' => 'Challenge limit exceeded or rank difference too high.'], 403);
        }

        $challenge = Challenge::create([
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'witness_id' => $request->input('witness_id'),
            'banned_agent' => $request->input('banned_agent')
        ]);

        return redirect()->route('WebhookController.sendToDiscord');

        // return response()->json(['message' => 'Challenge issued!', 'challenge' => $challenge]);
    }

    public function acceptChallenge($challengeId)
    {
        $challenge = Challenge::find($challengeId);
        $challenge->update(['status' => 'accepted']);

        return response()->json(['message' => 'Challenge accepted!']);
    }

    public function declineChallenge($challengeId)
    {
        $challenge = Challenge::find($challengeId);
        $challenge->update(['status' => 'declined']);

        return response()->json(['message' => 'Challenge declined.']);
    }

    private function canIssueChallenge($challenger)
    {
        // Logic to check if user has exceeded the 2-challenge limit per week
        $limit = challenge_limit::where('user_id', $challenger->id)
            ->where('week_start', Carbon::now()->startOfWeek())
            ->first();

        return $limit && $limit->challenge_count < 2;
    }
}
