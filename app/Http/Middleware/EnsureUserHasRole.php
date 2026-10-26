<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Acceso no autorizado.');
        }

        if (! in_array($user->rol, $roles, true)) {
            abort(403, 'Acceso denegado: su rol institucional no tiene privilegios para este recurso.');
        }

        return $next($request);
    }
}
