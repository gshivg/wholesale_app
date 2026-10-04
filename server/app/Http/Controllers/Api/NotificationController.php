<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'data' => $u->notifications()->latest()->limit(50)->get()->map(fn ($n) => Presenter::notification($n)),
            'unread' => $u->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $q = $request->user()->unreadNotifications();
        if ($request->filled('id')) {
            $q->where('id', $request->string('id'));
        }
        $q->update(['read_at' => now()]);

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }
}
