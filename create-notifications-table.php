<?php
include 'config.php';

$createSql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    related_item_id INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_id (user_id),
    INDEX idx_notifications_is_read (is_read),
    INDEX idx_notifications_created_at (created_at)
)";

if (!$conn->query($createSql)) {
    die("Error creating notifications table: " . $conn->error);
}

$columnChecks = [
    'type' => "ALTER TABLE notifications ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'system' AFTER message",
    'related_item_id' => "ALTER TABLE notifications ADD COLUMN related_item_id INT(11) NULL AFTER is_read",
];

foreach ($columnChecks as $columnName => $alterSql) {
    $checkSql = "SHOW COLUMNS FROM notifications LIKE '$columnName'";
    $result = $conn->query($checkSql);

    if ($result && $result->num_rows === 0) {
        if (!$conn->query($alterSql)) {
            die("Error updating notifications table for column {$columnName}: " . $conn->error);
        }
    }
}

$indexChecks = [
    'idx_notifications_user_id' => "ALTER TABLE notifications ADD INDEX idx_notifications_user_id (user_id)",
    'idx_notifications_is_read' => "ALTER TABLE notifications ADD INDEX idx_notifications_is_read (is_read)",
    'idx_notifications_created_at' => "ALTER TABLE notifications ADD INDEX idx_notifications_created_at (created_at)",
];

foreach ($indexChecks as $indexName => $alterSql) {
    $result = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = '$indexName'");
    if ($result && $result->num_rows === 0) {
        $conn->query($alterSql);
    }
}

echo "Notifications table is ready.";

$conn->close();
?>
