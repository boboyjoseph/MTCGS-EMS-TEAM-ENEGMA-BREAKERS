<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function markAsRead(string $notification, Request $request)
    {
        $record = Auth::user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $redirectUrl = $record->data['url'] ?? route('dashboard');

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'url' => $redirectUrl,
                'unread_count' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        return redirect($redirectUrl);
    }

    public function markAllAsRead(Request $request)
    {
        $count = Auth::user()->unreadNotifications()->count();
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'count' => $count,
                'unread_count' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        return back();
    }

    public function destroy(string $notification, Request $request)
    {
        $record = Auth::user()->notifications()->findOrFail($notification);
        $record->delete();

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully.',
                'unread_count' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        return back();
    }
}
