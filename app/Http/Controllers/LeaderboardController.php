<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function showLeaderboard()
    {
        $leaderboard = User::withSum('receivedTacos', 'number_of_given_tacos')
            ->having('received_tacos_sum_number_of_given_tacos', '>', 0)
            ->orderBy('received_tacos_sum_number_of_given_tacos', 'desc')
            ->get();

        return view('leaderboard', compact('leaderboard'));
    }
}
