<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApprovedShop
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->canOrder()) {
            $status = $user?->shop?->status ?? 'none';
            $msg = match ($status) {
                'pending' => 'Your account is awaiting approval. We will notify you once it is verified.',
                'rejected' => 'Your registration was not approved. Please contact support.',
                'suspended' => 'Your account is suspended. Please contact support.',
                default => 'Ordering is not available for this account.',
            };
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $msg, 'code' => 'shop_' . $status], 403);
            }
            abort(403, $msg);
        }

        return $next($request);
    }
}
