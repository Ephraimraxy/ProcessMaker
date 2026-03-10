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
            'session_config' => [
                'driver' => config('session.driver'),
                'domain' => config('session.domain'),
                'secure' => config('session.secure'),
                'path' => config('session.path'),
                'connection' => config('session.connection'),
            ],
            'redis_config' => [
                'prefix' => config('database.redis.options.prefix'),
            ],
            'handler' => get_class(Session::getHandler()),
        ]);
    }

    public function listRedisKeys(Request $request)
    {
        $keys = \Illuminate\Support\Facades\Redis::keys('*');
        return response()->json([
            'keys' => $keys,
            'prefix' => config('database.redis.options.prefix'),
        ]);
    }

    public function setRedis(Request $request)
    {
        \Illuminate\Support\Facades\Redis::set('cross_request_test', $request->input('val', 'sticky'));
        return 'Redis sticky value set';
    }

    public function getRedis()
    {
        return 'Redis sticky value: ' . \Illuminate\Support\Facades\Redis::get('cross_request_test');
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
