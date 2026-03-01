<?php

namespace App\Http\Middleware;

use Closure;

class Superadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $administrator_list = config('constants.administrator_usernames') ?? '';
        $usernames = array_map('trim', explode(',', strtolower($administrator_list)));

        if (! empty($request->user()) && ! empty($usernames) && in_array(strtolower($request->user()->username), $usernames)) {
            return $next($request);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }
}
