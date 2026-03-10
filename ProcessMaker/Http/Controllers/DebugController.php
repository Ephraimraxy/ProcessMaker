<?php

namespace ProcessMaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class DebugController extends Controller
{
    public function sessionInfo(Request $request)
    {
        $sessionId = Session::getId();
        $keys = \Illuminate\Support\Facades\Redis::keys('*' . $sessionId . '*');
        
        return response()->json([
            'auth_check' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->only(['id', 'username', 'email', 'status', 'is_administrator']) : null,
            'session_id' => $sessionId,
            'found_keys' => $keys,
            'session_data' => Session::all(),
            'cookies' => $request->cookies->all(),
            'ip' => $request->ip(),
            'cache_prefix' => \Illuminate\Support\Facades\Cache::getPrefix(),
            'session_config' => [
                'driver' => config('session.driver'),
                'domain' => config('session.domain'),
                'secure' => config('session.secure'),
                'path' => config('session.path'),
                'connection' => config('session.connection'),
                'store' => config('session.store'),
            ],
            'redis_config' => [
                'prefix' => config('database.redis.options.prefix'),
            ],
            'env_debug' => [
                'REDIS_PREFIX' => env('REDIS_PREFIX', 'NOT_SET'),
                'CACHE_PREFIX' => env('CACHE_PREFIX', 'NOT_SET'),
                'APP_NAME' => env('APP_NAME'),
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

    public function flushAll()
    {
        \Illuminate\Support\Facades\Redis::connection()->flushall();
        return 'Redis flushed successfully';
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

    public function scanRedis()
    {
        $results = [];
        $originalDb = config('database.redis.default.database', 0);
        
        for ($i = 0; $i < 4; $i++) { // Check first 4 DBs
            try {
                \Illuminate\Support\Facades\Redis::connection()->select($i);
                $keys = \Illuminate\Support\Facades\Redis::connection()->keys('*');
                $results["db_$i"] = $keys;
            } catch (\Exception $e) {
                $results["error_db_$i"] = $e->getMessage();
            }
        }
        
        // Reset to original
        try {
            \Illuminate\Support\Facades\Redis::connection()->select($originalDb);
        } catch (\Exception $e) {}

        return response()->json($results);
    }

    public function deepDebug(Request $request)
    {
        $id = Session::getId();
        $store = Session::getHandler();
        $cache = \Illuminate\Support\Facades\Cache::store(config('session.store'));
        
        $sessionWrite = session()->put('deep_test', 'works-' . time());
        $cacheWrite = $cache->put('cache_test', 'works-' . time(), 60);
        
        return response()->json([
            'session_id' => $id,
            'handler' => get_class($store),
            'cache_prefix' => $cache->getPrefix(),
            'session_data_before_save' => session()->all(),
            'session_save_result' => session()->save(),
            'cache_read_back' => $cache->get('cache_test'),
            'redis_keys_matching_id' => \Illuminate\Support\Facades\Redis::keys('*' . $id . '*'),
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
