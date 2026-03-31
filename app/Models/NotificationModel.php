<?php
/**
 * app/Models/NotificationModel.php — Tim: Database
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';
class NotificationModel extends BaseModel {
    protected static string $table = 'notifications';
    public static function countUnread(int $userId): int {
        return static::count("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$userId]);
    }
    public static function getForUser(int $userId, int $limit=20): array {
        return static::query("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?",[$userId,$limit]);
    }
    public static function markRead(int $notifId, int $userId): void {
        static::execute("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?",[$notifId,$userId]);
    }
    public static function markAllRead(int $userId): void {
        static::execute("UPDATE notifications SET is_read=1 WHERE user_id=?",[$userId]);
    }
}
