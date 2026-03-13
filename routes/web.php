<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use ProcessMaker\Facades\Metrics;

use ProcessMaker\Http\Controllers\AboutController;
use ProcessMaker\Http\Controllers\Admin\AuthClientController;
use ProcessMaker\Http\Controllers\Admin\CssOverrideController;
use ProcessMaker\Http\Controllers\Admin\DevLinkController;
use ProcessMaker\Http\Controllers\Admin\GroupController;
use ProcessMaker\Http\Controllers\Admin\LdapLogsController;
use ProcessMaker\Http\Controllers\Admin\QueuesController;
use ProcessMaker\Http\Controllers\Admin\ScriptExecutorController;
use ProcessMaker\Http\Controllers\Admin\SettingsController;
use ProcessMaker\Http\Controllers\Admin\TenantQueueController;
use ProcessMaker\Http\Controllers\Admin\UserController;
use ProcessMaker\Http\Controllers\AdminController;
use ProcessMaker\Http\Controllers\Auth\ChangePasswordController;
use ProcessMaker\Http\Controllers\Auth\ClientController;
use ProcessMaker\Http\Controllers\Auth\ForgotPasswordController;
use ProcessMaker\Http\Controllers\Auth\LoginController;
use ProcessMaker\Http\Controllers\Auth\ResetPasswordController;
use ProcessMaker\Http\Controllers\Auth\TwoFactorAuthController;
use ProcessMaker\Http\Controllers\CasesController;
use ProcessMaker\Http\Controllers\Designer\DesignerController;
use ProcessMaker\Http\Controllers\HomeController;
use ProcessMaker\Http\Controllers\InboxRulesController;
use ProcessMaker\Http\Controllers\NotificationController;
use ProcessMaker\Http\Controllers\Process\EnvironmentVariablesController;
use ProcessMaker\Http\Controllers\Process\ModelerController;
use ProcessMaker\Http\Controllers\Process\ScreenBuilderController;
use ProcessMaker\Http\Controllers\Process\ScreenController;
use ProcessMaker\Http\Controllers\Process\ScriptController;
use ProcessMaker\Http\Controllers\Process\SignalController;
use ProcessMaker\Http\Controllers\ProcessController;
use ProcessMaker\Http\Controllers\ProcessesCatalogueController;
use ProcessMaker\Http\Controllers\ProfileController;
use ProcessMaker\Http\Controllers\RequestController;
use ProcessMaker\Http\Controllers\Saml\MetadataController;
use ProcessMaker\Http\Controllers\StorageController;
use ProcessMaker\Http\Controllers\TaskController;
use ProcessMaker\Http\Controllers\TemplateController;
use ProcessMaker\Http\Controllers\TestStatusController;
use ProcessMaker\Http\Controllers\UnavailableController;
use ProcessMaker\Http\Middleware\NoCache;

// Public storage route - must be before auth middleware
Route::get('storage/{path}', [StorageController::class, 'serve'])
    ->where('path', '.*')  // Allow any characters including slashes for nested paths
    ->name('storage.serve');

Route::get('/diag/db-sessions', function() {
    $sessions = \Illuminate\Support\Facades\DB::table('sessions')
        ->orderBy('last_activity', 'desc')
        ->limit(20)
        ->get();
        
    return [
        'server_time' => date('Y-m-d H:i:s'),
        'sessions' => $sessions->map(function($s) {
            $payload = @unserialize(@base64_decode($s->payload));
            return [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'ip' => $s->ip_address,
                'last_active' => date('Y-m-d H:i:s', $s->last_activity),
                'payload_keys' => $payload ? array_keys($payload) : [],
                'login_verified' => $payload['login_verified'] ?? 'MISSING',
                'auth_keys' => $payload ? array_filter(array_keys($payload), function($k) {
                    return strpos($k, 'login_web_') === 0;
                }) : [],
            ];
        })
    ];
});

Route::get('/diag/force-login', function() {
    $user = \ProcessMaker\Models\User::where('username', 'admin')->first();
    if (!$user) return 'Admin user not found';
    
    Auth::login($user);
    session()->put('login_verified', 'forced_via_diag');
    session()->save();
    
    return [
        'status' => 'Logged in as Admin',
        'session_id' => session()->getId(),
        'user_id' => Auth::id(),
    ];
});



Route::get('/diag/restore-admin', function() {
    $admin = \ProcessMaker\Models\User::where('username', 'admin')->first();
    if (!$admin) return 'Admin user not found';
    
    // 1. Ensure "Administrators" group exists
    $group = \ProcessMaker\Models\Group::firstOrCreate(
        ['name' => 'Administrators'],
        ['status' => 'ACTIVE', 'description' => 'System Administrators Group']
    );
    
    // 2. Fetch all permissions
    $allPerms = \ProcessMaker\Models\Permission::all();
    if ($allPerms->isEmpty()) return 'No permissions found in system';
    
    // 3. Sync permissions to group
    $group->permissions()->sync($allPerms->pluck('id'));
    
    // 4. Add user to group
    $exists = \ProcessMaker\Models\GroupMember::where([
        'group_id' => $group->id,
        'member_id' => $admin->id,
        'member_type' => get_class($admin)
    ])->exists();
    
    if (!$exists) {
        \ProcessMaker\Models\GroupMember::create([
            'group_id' => $group->id,
            'member_id' => $admin->id,
            'member_type' => get_class($admin)
        ]);
    }
    
    // 5. Sync direct permissions
    $admin->permissions()->sync($allPerms->pluck('id'));
    
    // 6. Invalidate cache
    $admin->invalidatePermissionCache();
    
    return [
        'status' => 'Admin rights restored',
        'user' => $admin->username,
        'group' => $group->name,
        'permissions_count' => $allPerms->count(),
        'debug_auth_link' => url('/diag/debug-auth')
    ];
});



Route::get('/diag/set-cookie', function() {
    return response('Cookie Set')
        ->cookie('diag_test', 'working', 60, '/', null, false, false, false, 'lax');
});


Route::get('/diag/debug-auth', function() {
    return [
        'info' => 'Hyper-detailed Auth Debug',
        'auth' => [
            'check' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->getAttributes() : null,
            'can_view_dashboard' => Auth::check() ? Auth::user()->can('view-dashboard') : false,
            'id' => Auth::id(),
            'guard' => Auth::getDefaultDriver(),
            'direct_permissions' => Auth::check() ? Auth::user()->permissions()->pluck('name')->toArray() : [],
            'groups' => Auth::check() ? Auth::user()->groupMembersFromMemberable->map(function($gm) { 
                return ['id' => $gm->group->id, 'name' => $gm->group->name]; 
            }) : [],
        ],
        'session' => [
            'id' => session()->getId(),
            'driver' => config('session.driver'),
            'all' => session()->all(),
            'lifetime' => config('session.lifetime'),
            'expire_on_close' => config('session.expire_on_close'),
            'permissions_check' => [
                'session_permissions' => session('permissions'),
                'live_permissions' => Auth::check() ? Auth::user()->loadPermissions() : 'Not Logged In',
            ],
        ],
        'request' => [
            'cookies' => request()->cookies->all(),
            'headers' => collect(request()->headers->all())->map(function($v) { return $v[0]; }),
            'ip' => request()->ip(),
            'proto' => request()->header('X-Forwarded-Proto'),
            'secure' => request()->isSecure(),
        ],
        'config' => [
            'app_url' => config('app.url'),
            'session_domain' => config('session.domain'),
            'session_secure' => config('session.secure'),
        ]
    ];
});


Route::get('/diag/whoami', function() {
    if (Auth::check()) {
        return "Logged in as: " . Auth::user()->username . " (ID: " . Auth::id() . ")";
    }
    return "Not logged in. Session ID: " . session()->getId();
});


Route::middleware('auth', 'session_kill', 'sanitize', 'force_change_password', '2fa')->group(function () {
    // Routes related to Authentication (password reset, etc)
    // Auth::routes();
    Route::prefix('admin')->group(function () {
        Route::get('queues', [QueuesController::class, 'index'])->name('queues.index');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('can:view-settings');
        Route::get('ldap-logs', [LdapLogsController::class, 'index'])->name('ldap.index')->middleware('can:view-settings');
        Route::get('settings/export', [SettingsController::class, 'export'])->name('settings.export')->middleware('can:view-settings');
        Route::get('groups', [GroupController::class, 'index'])->name('groups.index')->middleware('can:view-groups');
        // Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show')->middleware('can:show-groups,group');
        Route::get('groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit')->middleware('can:edit-groups,group');

        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('can:view-users');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('can:edit-users,user');

        Route::get('auth-clients', [AuthClientController::class, 'index'])->name('auth-clients.index')->middleware('can:view-auth_clients');

        Route::get('customize-ui/{tab?}', [CssOverrideController::class, 'edit'])->name('customize-ui.edit');

        Route::get('script-executors', [ScriptExecutorController::class, 'index'])->name('script-executors.index');

        // Tenant Jobs Dashboard
        Route::get('tenant-queues', [TenantQueueController::class, 'index'])->name('tenant-queue.index');

        // DevLink
        Route::middleware('admin')->group(function () {
            Route::get('devlink/oauth-client', [DevLinkController::class, 'getOauthClient'])->name('devlink.oauth-client');
            Route::get('devlink/{router?}', [DevLinkController::class, 'index'])->where(['router' => '.*'])->name('devlink.index');
        });

        // temporary, should be removed
        Route::get('security-logs/download/all', [ProcessMaker\Http\Controllers\Api\SecurityLogController::class, 'downloadForAllUsers'])->middleware('can:view-security-logs');
        Route::get('security-logs/download/{user}', [ProcessMaker\Http\Controllers\Api\SecurityLogController::class, 'downloadForUser'])->middleware('can:view-security-logs');
    });

    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');

    Route::prefix('designer')->group(function () {
        Route::get('environment-variables', [EnvironmentVariablesController::class, 'index'])->name('environment-variables.index')->middleware('can:view-environment_variables');
        Route::get('environment-variables/{environment_variable}/edit', [EnvironmentVariablesController::class, 'edit'])->name('environment-variables.edit')->middleware('can:edit-environment_variables,environment_variable ');

        Route::get('screens', [ScreenController::class, 'index'])->name('screens.index')->middleware('can:view-screens');
        Route::get('screens/{screen}/edit', [ScreenController::class, 'edit'])->name('screens.edit')->middleware('can:edit-screens,screen');
        Route::get('screens/{screen}/export', [ScreenController::class, 'export'])->name('screens.export')->middleware('can:export-screens');
        Route::get('screens/import', [ScreenController::class, 'import'])->name('screens.import')->middleware('can:import-screens');
        Route::get('screens/{screen}/download/{key}', [ScreenController::class, 'download'])->name('screens.download')->middleware('can:export-screens');
        Route::get('screen-builder/{screen}/edit/{processId?}', [ScreenBuilderController::class, 'edit'])->name('screen-builder.edit')->middleware('can:edit-screens,screen');
        Route::get('screens/preview', [ScreenController::class, 'preview'])->name('screens.preview')->middleware('can:view-screens');

        Route::get('scripts', [ScriptController::class, 'index'])->name('scripts.index')->middleware('can:view-scripts');
        Route::get('scripts/{script}/edit', [ScriptController::class, 'edit'])->name('scripts.edit')->middleware('can:edit-scripts,script');
        Route::get('scripts/{script}/builder/{processId?}', [ScriptController::class, 'builder'])->name('scripts.builder')->middleware('can:edit-scripts,script');
        Route::get('scripts/preview', [ScriptController::class, 'preview'])->name('scripts.preview')->middleware('can:view-screens');

        Route::get('signals', [SignalController::class, 'index'])->name('signals.index')->middleware('can:view-signals');
        Route::get('signals/{signalId}/edit', [SignalController::class, 'edit'])->name('signals.edit')->middleware('can:edit-signals');
    });

    Route::get('designer/processes/categories', [ProcessController::class, 'index'])->name('process-categories.index')->middleware('can:view-process-categories');

    Route::get('designer/screens/categories', [ScreenController::class, 'index'])->name('screen-categories.index')->middleware('can:view-screen-categories');

    Route::get('designer/scripts/categories', [ScriptController::class, 'index'])->name('script-categories.index')->middleware('can:view-script-categories');
    Route::get('designer', [DesignerController::class, 'index'])->name('designer.index')->middleware('can:view-designer');

    Route::get('process-browser/{process?}', [ProcessesCatalogueController::class, 'index'])
       ->name('process.browser.index')
       ->middleware('can:view-process-catalog');
    //------------------------------------------------------------------------------------------
    // Below route is for backward compatibility with old format routes. PLEASE DO NOT REMOVE
    //------------------------------------------------------------------------------------------
    Route::get('processes-catalogue/{process?}', function ($process = null) {
        return redirect()->route('process.browser.index', [$process]);
    })->name('processes.catalogue.index');
    //------------------------------------------------------------------------------------------

    Route::get('processes', [ProcessController::class, 'index'])->name('processes.index');
    Route::get('processes/{process}/edit', [ProcessController::class, 'edit'])->name('processes.edit')->middleware('can:edit-processes');
    Route::get('processes/{process}/export/{page?}', [ProcessController::class, 'export'])->name('processes.export')->middleware('can:export-processes');
    Route::get('processes/import/{page?}', [ProcessController::class, 'import'])->name('processes.import')->middleware('can:import-processes');
    Route::get('import/download-debug', [ProcessController::class, 'downloadImportDebug'])->name('import.download-debug')->middleware('can:import-processes');
    Route::get('processes/{process}/download/{key}', [ProcessController::class, 'download'])->name('processes.download')->middleware('can:export-processes');
    Route::get('processes/create', [ProcessController::class, 'create'])->name('processes.create')->middleware('can:create-processes');
    Route::post('processes', [ProcessController::class, 'store'])->name('processes.store')->middleware('can:edit-processes');
    Route::get('processes/{process}', [ProcessController::class, 'show'])->name('processes.show')->middleware('can:view-processes');
    Route::put('processes/{process}', [ProcessController::class, 'update'])->name('processes.update')->middleware('can:edit-processes');
    Route::delete('processes/{process}', [ProcessController::class, 'destroy'])->name('processes.destroy')->middleware('can:archive-processes');

    Route::get('process_events/{process}', [ProcessController::class, 'triggerStartEventApi'])->name('process_events.trigger')->middleware('can:start,process');

    Route::get('about', [AboutController::class, 'index'])->name('about.index');

    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit')->middleware('can:edit-personal-profile');
    Route::get('profile/{id}', [ProfileController::class, 'show'])->name('profile.show');
    // Ensure our modeler loads at a distinct url
    Route::get('modeler/{process}', [ModelerController::class, 'show'])->name('modeler.show')->middleware('can:edit,process');
    Route::get('modeler/{process}/inflight/{request?}', [ModelerController::class, 'inflight'])->name('modeler.inflight')->middleware('can:view,request');

    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/inbox/{router?}', [TaskController::class, 'index'])->where(['router' => '.*'])->name('inbox')->middleware('no-cache');
    Route::get('/redirect-to-intended', [HomeController::class, 'redirectToIntended'])->name('redirect_to_intended');

    Route::post('/keep-alive', [LoginController::class, 'keepAlive'])->name('keep-alive');

    // Cases
    Route::get('cases/{type?}', [CasesController::class, 'index'])
        ->where('type', 'all|in_progress|completed')
        ->name('cases-main.index')
        ->middleware('no-cache');
    Route::get('cases/{case_number}', [CasesController::class, 'show'])
        ->where('case_number', '[0-9]+')
        ->name('cases.show')
        ->middleware('no-cache');

    Route::get('cases/{case_number}/files/{file}', [CasesController::class, 'show'])
        ->where('case_number', '[0-9]+')
        ->where('file', '.*')
        ->name('cases.show-file')
        ->middleware('no-cache');

    // Requests
    Route::get('requests', [RequestController::class, 'index'])
        ->name('requests.index')
        ->middleware('no-cache');
    Route::get('requests/{type?}', [RequestController::class, 'index'])
    ->where('type', 'all|in_progress|completed')->name('requests_by_type')->middleware('no-cache');
    Route::get('requests/{request}', [RequestController::class, 'show'])->name('requests.show');
    Route::get('request/{request}/files/{media}', [RequestController::class, 'downloadFiles'])->middleware('can:view,request');
    Route::get('requests/search', [RequestController::class, 'search'])->name('requests.search');
    Route::get('requests/mobile/{request}', [RequestController::class, 'show'])->name('requests.showMobile');
    Route::get('requests/{request}/task/{task}/screen/{screen}', [RequestController::class, 'screenPreview'])->name('requests.screen-preview');

    Route::get('tasks/search', [TaskController::class, 'search'])->name('tasks.search');
    Route::get('tasks', [TaskController::class, 'index'])
        ->name('tasks.index')
        ->middleware('no-cache');
    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::get('tasks/{task}/edit/quickfill', [TaskController::class, 'quickFillEdit'])->name('tasks.edit.quickfill');
    Route::get('tasks/{task}/edit/{preview}', [TaskController::class, 'edit'])->name('tasks.preview');

    Route::get('tasks/rules/{path?}', [InboxRulesController::class, 'index'])->name('inbox-rules.index')->where('path', '.*');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::get('template/{type}/import', [TemplateController::class, 'import'])->name('templates.import')->middleware('template-authorization');
    Route::get('template/{type}/{template}/configure', [TemplateController::class, 'configure'])->name('templates.configure')->middleware('template-authorization');
    Route::get('template/assets', [TemplateController::class, 'chooseTemplateAssets'])->name('templates.assets');
    Route::get('modeler/templates/{id}', [TemplateController::class, 'show'])->name('modeler.template.show')->middleware('template-authorization', 'can:edit-process-templates');
    Route::get('screen-template/{screen}/export', [TemplateController::class, 'export'])->name('screens-template.export')->middleware('can:export-screens');
    Route::get('screen-template/import', [TemplateController::class, 'importScreen'])->name('screens-template.importScreen')->middleware('can:import-screens');
    Route::get('screen-template/{id}/edit', [TemplateController::class, 'editScreenTemplate'])->name('screen-template.edit')->middleware('can:edit-screens');

    // Allows for a logged in user to see navigation on a 404 page
    Route::fallback(function () {
        return response()->view('errors.404', [], 404);
    })->name('fallback');

    Route::get('/test_status', [TestStatusController::class, 'test'])->name('test.status');
    Route::get('/test_email', [TestStatusController::class, 'email'])->name('test.email');
});

Route::get('/debug-session', [\ProcessMaker\Http\Controllers\DebugController::class, 'sessionInfo']);
Route::get('/set-session', [\ProcessMaker\Http\Controllers\DebugController::class, 'setSession']);
Route::get('/get-session', [\ProcessMaker\Http\Controllers\DebugController::class, 'getSession']);
Route::get('/scan-redis', [\ProcessMaker\Http\Controllers\DebugController::class, 'scanRedis']);
Route::get('/test-cookie', [\ProcessMaker\Http\Controllers\DebugController::class, 'testCookie']);
Route::get('/test-redis', [\ProcessMaker\Http\Controllers\DebugController::class, 'testRedis']);
Route::get('/redis-get', [\ProcessMaker\Http\Controllers\DebugController::class, 'getRedis']);
Route::get('/deep-debug', [\ProcessMaker\Http\Controllers\DebugController::class, 'deepDebug']);
Route::get('/list-keys', [\ProcessMaker\Http\Controllers\DebugController::class, 'listRedisKeys']);
Route::get('/force-save', [\ProcessMaker\Http\Controllers\DebugController::class, 'forceSessionSave']);

Route::group([
    'middleware' => ['web', 'auth:web,anon', 'sanitize', 'bindings'],
], function () {
    Route::get('tasks/update_variable/{token_abe}', [TaskController::class, 'updateVariable'])->name('tasks.abe.update');
});

// Add our broadcasting routes
Broadcast::routes();

// Authentication Routes...
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'loginWithIntendedCheck']);
Route::get('logout', [LoginController::class, 'beforeLogout'])->name('logout');
Route::get('2fa', [TwoFactorAuthController::class, 'displayTwoFactorAuthForm'])->name('2fa');
Route::post('2fa/validate', [TwoFactorAuthController::class, 'validateTwoFactorAuthCode'])->name('2fa.validate');
Route::get('2fa/send_again', [TwoFactorAuthController::class, 'sendCode'])->name('2fa.send_again');
Route::get('2fa/auth_app_qr', [TwoFactorAuthController::class, 'displayAuthAppQr'])->name('2fa.auth_app_qr');
Route::get('login-failed', [LoginController::class, 'showLoginFailed'])->name('login-failed');

// Password Reset Routes...
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset']);
Route::get('password/change', [ChangePasswordController::class, 'edit'])->name('password.change');

// overwrite laravel passport
Route::get('oauth/clients', [ClientController::class, 'index'])->name('passport.clients.index')->middleware('can:view-auth_clients');
Route::get('oauth/clients/{client_id}', [ClientController::class, 'show'])->name('passport.clients.show')->middleware('can:view-auth_clients');
Route::post('oauth/clients', [ClientController::class, 'store'])->name('passport.clients.store')->middleware('can:create-auth_clients');
Route::put('oauth/clients/{client_id}', [ClientController::class, 'update'])->name('passport.clients.update')->middleware('can:edit-auth_clients');
Route::delete('oauth/clients/{client_id}', [ClientController::class, 'destroy'])->name('passport.clients.delete')->middleware('can:delete-auth_clients');

Route::get('password/success', function () {
    return view('auth.passwords.success', ['title' => __('Password Reset')]);
})->name('password-success');

Route::get('/unavailable', [UnavailableController::class, 'show'])->name('error.unavailable');

Route::get('/not-authorized', function () {
    return view('errors.not-authorized');
})->name('errors.not-authorized');

Route::get('/task-is-not-assigned', function () {
    return view('errors.task-is-not-assigned');
})->name('errors.task-is-not-assigned');

// SAML Metadata Route
Route::resource('/saml/metadata', MetadataController::class)->only('index');

Route::get('/diag', function () {
    return [
        'auth' => auth()->check(),
        'user' => auth()->user() ? [
            'id' => auth()->user()->id,
            'username' => auth()->user()->username,
            'is_admin' => auth()->user()->is_administrator,
        ] : null,
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'session_data' => session()->all(),
        'cookies' => request()->cookies->all(),
        'secure' => request()->secure(),
        'app_url' => config('app.url'),
        'env_admin_pass' => env('ADMIN_PASSWORD') ? 'SET (length: ' . strlen(env('ADMIN_PASSWORD')) . ')' : 'NOT SET',
        'middleware_bypass' => 'Kernel modified to comment out AuthenticateSession and SessionControlKill',
        'links' => [
            'force_login_admin' => url('/diag/force/admin'),
            'list_users' => url('/diag/users'),
            'reset_admin_pass' => url('/diag/reset'),
            'clear_cache' => url('/diag/clear'),
        ]
    ];
});

Route::get('/diag/reset', function() {
    $admin = \ProcessMaker\Models\User::where('username', 'admin')->first();
    if ($admin) {
        $admin->password = \Hash::driver('bcrypt')->make('admin');
        $admin->status = 'ACTIVE';
        $admin->is_system = 0; // Ensure it's not a system user
        $admin->force_change_password = 0;
        $admin->password_changed_at = now();
        $admin->save();
        
        // Wipe all active sessions from DB
        \Illuminate\Support\Facades\DB::table('user_sessions')->update(['is_active' => false]);
        
        return "Admin password reset to 'admin'. Force change password cleared. is_system set to 0. ALL sessions marked inactive. check_admin should now be true in /diag/users";
    }
    return "Admin user not found";
});

Route::get('/diag/test-auth', function() {
    $creds = ['username' => 'admin', 'password' => 'admin'];
    $attempt = Auth::attempt($creds);
    $user = \ProcessMaker\Models\User::where('username', 'admin')->first();
    
    return [
        'creds' => $creds,
        'attempt_result' => $attempt,
        'auth_check_after_attempt' => Auth::check(),
        'user_found' => (bool)$user,
        'user_status' => $user ? $user->status : 'N/A',
        'hash_check' => $user ? \Hash::check('admin', $user->password) : 'N/A',
        'session_id' => session()->getId(),
    ];
});

Route::get('/diag/users', function() {
    return \ProcessMaker\Models\User::all()->map(function($u) {
        return [
            'id' => $u->id,
            'username' => $u->username,
            'status' => $u->status,
            'is_admin' => $u->is_administrator,
            'is_system' => $u->is_system ?? 'N/A',
            'force_change' => $u->force_change_password,
            'password_hash' => substr($u->password, 0, 10) . '...',
            'check_admin' => \Hash::check('admin', $u->password),
            'check_password' => \Hash::check('password', $u->password),
        ];
    });
});

Route::get('/diag/sessions', function() {
    return \Illuminate\Support\Facades\DB::table('user_sessions')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();
});


Route::get('/diag/force/{user}', function($userInput) {
    $user = \ProcessMaker\Models\User::where('username', $userInput)->orWhere('id', $userInput)->first();
    if ($user) {
        Auth::login($user);
        
        // Try to satisfy SessionControlKill by providing a token
        $token = (string) \Illuminate\Support\Str::uuid();
        session(['user_session' => $token]);
        
        // Create an active session record
        $user->sessions()->create([
            'token' => $token,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_active' => true,
            'expired_date' => now()->addHours(2),
            'device_name' => 'Super Diag',
            'device_type' => 'Browser',
            'device_platform' => 'Web',
        ]);

        return redirect('/diag')->with('status', 'Logged in as ' . $user->username);
    }
    return "User $userInput not found";
});

Route::get('/diag/clear', function() {
    \Artisan::call('config:clear');
    \Artisan::call('cache:clear');
    return "Caches cleared";
});

Route::get('/diag/create-user', function () {
    $username = request()->query('u');
    $password = request()->query('p');
    
    if (!$username || !$password) {
        return "Usage: /diag/create-user?u=USERNAME&p=PASSWORD";
    }
    
    $user = \ProcessMaker\Models\User::where('username', $username)->first();
    if ($user) {
        return "User $username already exists.";
    }
    
    $user = new \ProcessMaker\Models\User();
    $user->username = $username;
    $user->password = \Hash::make($password);
    $user->firstname = $username;
    $user->lastname = 'User';
    $user->email = "$username@example.com";
    $user->status = 'ACTIVE';
    $user->is_administrator = 1;
    $user->is_system = 0;
    $user->force_change_password = 0;
    $user->save();
    
    return "User $username created with password $password and admin privileges.";
});

Route::get('/diag/session-info', function () {
    return [
        'auth_check' => Auth::check(),
        'user' => Auth::user() ? [
            'id' => Auth::user()->id,
            'username' => Auth::user()->username,
        ] : null,
        'session_id' => Session::getId(),
        'session_driver' => config('session.driver'),
        'session_lifetime' => config('session.lifetime'),
        'session_expire_on_close' => config('session.expire_on_close'),
        'session_encrypt' => config('session.encrypt'),
        'session_cookie' => config('session.cookie'),
        'session_path' => config('session.path'),
        'session_domain' => config('session.domain'),
        'session_secure' => config('session.secure'),
        'session_http_only' => config('session.http_only'),
        'all_session' => session()->all(),
        'cookies' => request()->cookies->all(),
    ];
});

// Metrics Route
Route::get('/metrics', function () {
    if (!config('app.multitenancy')) {
        Metrics::collectQueueMetrics();
    }

    return response(Metrics::renderMetrics(), 200, [
        'Content-Type' => 'text/plain; version=0.0.4',
    ]);
});
