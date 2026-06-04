<?php
// app/models/Notification.php

require_once '../core/Database.php';

class NotificationModel {

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getLastError() {
        return $this->db->getLastError();
    }

    
/**
 * Get specific users for notification (e.g., warehouse_manager)
 */
public function getUsersForNotification(string $role = 'warehouse_manager'): array {
    $sql = "SELECT u.id, u.username, e.designation 
            FROM users u 
            JOIN employees e ON u.employee_id = e.id 
            WHERE u.is_active = 1 
            AND e.designation = ?";

    $this->db->query($sql);
    $this->db->bind(1, $role);
    $users = $this->db->resultSet();
    
    error_log("📋 Warehouse Manager Notification: Found " . count($users) . " users with designation: " . $role);
    return $users;
}



    /**
     * Get all FCM tokens for a specific user
     */
    public function getUserFCMTokens(int $user_id): array {
        $sql = "SELECT fcm_token FROM fcm_tokens 
                WHERE user_id = ? 
                ORDER BY id DESC";

        $this->db->query($sql);
        $this->db->bind(1, $user_id, PDO::PARAM_INT);
        return $this->db->resultSet();
    }

    /**
     * Save notification in database for in-app notification center
     */
    public function saveNotification(
        int $user_id, 
        string $title, 
        string $message, 
        string $type, 
        ?int $reference_id = null
    ): bool {
        $sql = "INSERT INTO notifications 
                (user_id, title, message, type, reference_id, created_at, is_read) 
                VALUES (?, ?, ?, ?, ?, NOW(), 0)";

        $this->db->query($sql);
        $this->db->bind(1, $user_id, PDO::PARAM_INT);
        $this->db->bind(2, $title);
        $this->db->bind(3, $message);
        $this->db->bind(4, $type);
        $this->db->bind(5, $reference_id, PDO::PARAM_INT);

        return $this->db->execute();
    }

    /**
     * Optional: Get unread notifications for a user
     */
    public function getUnreadNotifications(int $user_id): array {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                ORDER BY created_at DESC LIMIT 20";

        $this->db->query($sql);
        $this->db->bind(1, $user_id, PDO::PARAM_INT);
        return $this->db->resultSet();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notification_id, int $user_id): bool {
        $sql = "UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?";

        $this->db->query($sql);
        $this->db->bind(1, $notification_id, PDO::PARAM_INT);
        $this->db->bind(2, $user_id, PDO::PARAM_INT);
        return $this->db->execute();
    }
}