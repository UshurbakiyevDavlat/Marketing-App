<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     * @throws Exception
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }

        if (!Auth::check() || ($role && ($user->role !== $role))) {
            return response()->json(['message' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
