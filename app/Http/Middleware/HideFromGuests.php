<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin-only pages on the public site (finance) don't exist for anyone who isn't logged in. */
class HideFromGuests
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 404);

        return $next($request);
    }
}
