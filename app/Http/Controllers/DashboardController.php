<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function apiIndex()
    {
        return [
            'greeting' => "What's up! 😎 Here are some fresh finds:",
            'featuredChannels' => Channel::orderBy('last_scanned_at', 'desc')->get(),
        ];
    }
}
