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
            'ip' => $request->ip(),
            'secure' => $request->secure(),
        ]);
    }

    public function setSession(Request $request)
    {
        $val = $request->input('val', 'persistent-value');
        $request->session()->put('debug_key', $val);
        $request->session()->save();
        return response()->json([
            'message' => 'Session value set',
            'value' => $val,
            'session_id' => Session::getId()
        ]);
    }

    public function getSession(Request $request)
    {
        return response()->json([
            'debug_key' => $request->session()->get('debug_key'),
            'session_id' => Session::getId(),
            'session_data' => $request->session()->all()
        ]);
    }
}
