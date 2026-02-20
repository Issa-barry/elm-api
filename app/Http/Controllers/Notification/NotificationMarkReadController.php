<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;

class NotificationMarkReadController extends Controller
{
    use ApiResponse;

    /**
     * Marquer une notification spécifique comme lue.
     */
    public function markOne(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification introuvable');
        }

        $notification->markAsRead();

        return $this->successResponse([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Notification marquée comme lue');
    }

    /**
     * Marquer toutes les notifications comme lues.
     */
    public function markAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse([
            'unread_count' => 0,
        ], 'Toutes les notifications ont été lues');
    }
}
