<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;

class NotificationIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $user          = $request->user();
        $unreadOnly    = $request->boolean('unread_only', true);

        $notifications = $unreadOnly
            ? $user->unreadNotifications()->latest()->get()
            : $user->notifications()->latest()->paginate(20);

        return $this->successResponse([
            'notifications'  => $notifications,
            'unread_count'   => $user->unreadNotifications()->count(),
        ]);
    }
}
