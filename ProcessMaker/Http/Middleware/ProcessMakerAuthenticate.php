<?php

namespace ProcessMaker\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Str;

class ProcessMakerAuthenticate extends Authenticate
{
    protected function authenticate($request, array $guards)
    {
        $this->addAcceptJsonHeaderIfApiCall($request, $guards);

        // Load permissions into the session
        $isAuthed = auth()->check();
        $sessId = session()->getId();
        error_log("[AUTH_DIAG] URL: " . $request->fullUrl() . " - Authed: " . ($isAuthed ? 'YES' : 'NO') . " - Session: " . $sessId);

        if ($isAuthed && !$request->ajax()) {
            $permissions = $request->user()->loadPermissions();
            error_log("[AUTH_DIAG] User: " . auth()->id() . " - Perms Count: " . count($permissions));
            session(['permissions' => $permissions]);
        }

        return parent::authenticate($request, $guards);
    }

    /**
     * @param array $guards
     * @param \Illuminate\Http\Request $request
     */
    private function addAcceptJsonHeaderIfApiCall(\Illuminate\Http\Request $request, array $guards): void
    {
        if (in_array('api', $guards) && !$this->requestHasAcceptJsonHeader($request)) {
            $request->headers->set('accept', 'application/json,' . $request->header('accept'));
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    private function requestHasAcceptJsonHeader(\Illuminate\Http\Request $request): bool
    {
        return Str::contains($request->header('accept'), ['/json', '+json']);
    }
}
