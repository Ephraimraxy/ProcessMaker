<?php

namespace ProcessMaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class DebugController extends Controller
{
    public function sessionInfo(Request $request)
    {
        $sessionId = Session::getId();
        $keys = Redis::keys('*' . $sessionId . '*');
        
        return response()->json([
            'auth_check' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->only(['id', 'username', 'email', 'status', 'is_administrator']) : null,
            'session_id' => $sessionId,
            'found_keys' => $keys,
            'session_data' => Session::all(),
            'cookies' => $request->cookies->all(),
            'ip' => $request->ip(),
            'cache_driver_runtime' => Cache::getDefaultDriver(),
            'cache_prefix_runtime' => Cache::getPrefix(),
            'config_vs_env' => [
                'session_driver' => ['config' => config('session.driver'), 'env' => env('SESSION_DRIVER')],
                'session_store' => ['config' => config('session.store'), 'env' => env('SESSION_STORE')],
                'cache_driver' => ['config' => config('cache.default'), 'env' => env('CACHE_DRIVER')],
                'cache_prefix' => ['config' => config('cache.prefix'), 'env' => env('CACHE_PREFIX')],
                'redis_prefix' => ['config' => config('database.redis.options.prefix'), 'env' => env('REDIS_PREFIX')],
                'redis_db' => ['config' => config('database.redis.default.database'), 'env' => env('REDIS_DB')],
                'multitenancy' => ['config' => config('app.multitenancy'), 'env' => env('MULTITENANCY')],
            ],
            'handler' => get_class(Session::getHandler()),
        ]);
    }

    public function testCookie()
    {
        return response('Cookie should be set')->cookie('debug_cookie', 'works_'.time(), 60, '/', null, true, true, false, 'lax');
    }

    public function testRedis()
    {
        $val = 'persistent_'.time();
        Redis::set('cross_request_test', $val);
        return response()->json([
            'message' => 'Redis sticky value set',
            'value' => $val,
            'check' => Redis::get('cross_request_test')
        ]);
    }

    public function getRedis()
    {
        return response('Redis sticky value: ' . Redis::get('cross_request_test'));
    }

    public function scanRedis()
    {
        $results = [];
        $connections = ['default', 'cache', 'cache_settings'];
        
        foreach ($connections as $conn) {
            try {
                $redis = Redis::connection($conn);
                for ($i = 0; $i < 4; $i++) {
                    $redis->select($i);
                    $keys = $redis->keys('*');
                    if (!empty($keys)) {
                        $results["{$conn}_db_{$i}"] = $keys;
                    }
                }
            } catch (\Exception $e) {
                $results["error_{$conn}"] = $e->getMessage();
            }
        }

        return response()->json($results);
    }

    public function deepDebug(Request $request)
    {
        $id = Session::getId();
        $store = Session::getHandler();
        $cache = Cache::store(config('session.store'));
        
        session()->put('deep_test', 'works-' . time());
        $cache->put('cache_test', 'works-' . time(), 60);
        
        return response()->json([
            'session_id' => $id,
            'handler' => get_class($store),
            'cache_prefix' => $cache->getPrefix(),
            'session_data_before_save' => session()->all(),
            'session_save_result' => session()->save(),
            'cache_read_back' => $cache->get('cache_test'),
            'redis_keys_matching_id' => Redis::keys('*' . $id . '*'),
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

    public function flushAll()
    {
        Redis::connection()->flushall();
        return 'Redis flushed successfully';
    }

    public function listRedisKeys(Request $request)
    {
        $keys = Redis::keys('*');
        return response()->json([
            'keys' => $keys,
            'prefix' => config('database.redis.options.prefix'),
        ]);
    }
}
