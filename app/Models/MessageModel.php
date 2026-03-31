<?php
/**
 * app/Models/MessageModel.php — Tim: Database
 * Model untuk tabel messages (chat per lamaran).
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class MessageModel extends BaseModel {
    protected static string $table = 'messages';

    public static function sendMessage(int $applicationId, int $senderId, string $message): array {
        if (empty(trim($message))) {
            return ['success' => false, 'message' => 'Pesan tidak boleh kosong.'];
        }

        // Verify sender has access to this application
        if (!self::hasAccess($applicationId, $senderId)) {
            return ['success' => false, 'message' => 'Tidak memiliki akses.'];
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO messages (application_id, sender_id, message) VALUES (?, ?, ?)"
        );
        $stmt->execute([$applicationId, $senderId, $message]);
        $msgId = $pdo->lastInsertId();

        logActivity($senderId, 'send_message', 'chat', "Message sent for application_id={$applicationId}");

        return ['success' => true, 'id' => $msgId];
    }

    public static function getMessages(int $applicationId, int $requesterId): array {
        if (!self::hasAccess($applicationId, $requesterId)) return [];

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT m.*, u.username, u.role
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.application_id = ?
             ORDER BY m.sent_at ASC"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    public static function markRead(int $applicationId, int $userId): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "UPDATE messages SET is_read = 1
             WHERE application_id = ? AND sender_id != ? AND is_read = 0"
        );
        $stmt->execute([$applicationId, $userId]);
    }

    public static function countUnread(int $userId): int {
        $pdo  = getDB();

        // Get all application IDs this user is party to
        if (isAlumni()) {
            $stmt = $pdo->prepare(
                "SELECT a.id FROM applications a
                 JOIN alumni_profiles ap ON ap.id = a.alumni_id
                 WHERE ap.user_id = ?"
            );
        } elseif (isCompany()) {
            $stmt = $pdo->prepare(
                "SELECT a.id FROM applications a
                 JOIN job_vacancies jv ON jv.id = a.vacancy_id
                 JOIN companies c ON c.id = jv.company_id
                 WHERE c.user_id = ?"
            );
        } else {
            // Admin sees all
            return 0;
        }

        $stmt->execute([$userId]);
        $appIds = array_column($stmt->fetchAll(), 'id');
        if (empty($appIds)) return 0;

        $placeholders = implode(',', array_fill(0, count($appIds), '?'));
        $stmt2        = $pdo->prepare(
            "SELECT COUNT(*) FROM messages
             WHERE application_id IN ({$placeholders}) AND sender_id != ? AND is_read = 0"
        );
        $stmt2->execute(array_merge($appIds, [$userId]));
        return (int)$stmt2->fetchColumn();
    }

    private static function hasAccess(int $applicationId, int $userId): bool {
        if (isAdmin()) return true;

        $pdo  = getDB();

        if (isAlumni()) {
            $stmt = $pdo->prepare(
                "SELECT a.id FROM applications a
                 JOIN alumni_profiles ap ON ap.id = a.alumni_id
                 WHERE a.id = ? AND ap.user_id = ? LIMIT 1"
            );
            $stmt->execute([$applicationId, $userId]);
            return (bool)$stmt->fetch();
        }

        if (isCompany()) {
            $stmt = $pdo->prepare(
                "SELECT a.id FROM applications a
                 JOIN job_vacancies jv ON jv.id = a.vacancy_id
                 JOIN companies c ON c.id = jv.company_id
                 WHERE a.id = ? AND c.user_id = ? LIMIT 1"
            );
            $stmt->execute([$applicationId, $userId]);
            return (bool)$stmt->fetch();
        }

        return false;
    }

    // Aliases for backward compatibility with controllers
    public static function send(int $applicationId, int $senderId, string $message): array {
        return self::sendMessage($applicationId, $senderId, $message);
    }

    public static function getByApp(int $applicationId, int $requesterId): array {
        return self::getMessages($applicationId, $requesterId);
    }

}
