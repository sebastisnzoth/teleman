<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Check if notifications table exists
            if (!Schema::hasTable('notifications')) {
                Log::warning('Notifications table does not exist', [
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'notifications' => [],
                    'unread_count' => 0,
                ]);
            }
            
            // Get notifications from database
            $notifications = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($notification) {
                    $data = json_decode($notification->data, true);
                    
                    return [
                        'id' => $notification->id,
                        'title' => $data['title'] ?? 'Notification',
                        'message' => $data['message'] ?? '',
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at,
                        'url' => $data['url'] ?? null,
                        'type' => $data['type'] ?? 'info',
                    ];
                });

            $unreadCount = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty result instead of 500 error to prevent UI crash
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
            ]);
        }
    }

    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->update(['read_at' => now()]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'error' => $e->getMessage(),
                'notification_id' => $id,
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();
            
            DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark notifications as read'
            ], 500);
        }
    }
}
