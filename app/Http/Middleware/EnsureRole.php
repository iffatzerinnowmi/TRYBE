<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a route say "only this role may enter".
 *
 *   Route::get('/admin', ...)->middleware('role:admin');
 *   Route::get('/x', ...)->middleware('role:researcher,organization');
 *
 * Register the 'role' alias in bootstrap/app.php — see the snippet that
 * came with this section.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Not logged in at all — let the 'auth' middleware deal with it.
        if (! $user) {
            return redirect()->route('login');
        }

        // role is cast to the UserRole enum on the User model, so ->value
        // gives us the plain string to compare against the route's list.
        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'This page is not available for your account type.');
        }

        return $next($request);
    }
}
