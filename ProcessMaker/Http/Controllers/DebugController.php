<?php

namespace ProcessMaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class DebugController extends Controller
{
    public function sessionInfo(Request $request)
    {
        return response()->json([
            'auth_check' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->only(['id', 'username', 'email', 'status', 'is_administrator']) : null,
            'session_id' => Session::getId(),
            'session_data' => Session::all(),
            'cookies' => $request->cookies->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'secure' => $request->secure(),
            'url' => $request->fullUrl(),
        ]);
    }
}
