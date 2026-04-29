<?php

/**
 * Notifications and matching helper functions.
 */

function notificationsColumnExists($conn, $columnName) {
    static $cache = [];

    if (array_key_exists($columnName, $cache)) {
        return $cache[$columnName];
    }

    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE '{$safeColumn}'");
    $cache[$columnName] = $result && $result->num_rows > 0;

    return $cache[$columnName];
}

function createNotification($conn, $user_id, $message, $type, $related_item_id = null) {
    $user_id = (int) $user_id;
    $message = (string) $message;
    $type = (string) $type;

    $hasTypeColumn = notificationsColumnExists($conn, 'type');
    $hasRelatedItemColumn = notificationsColumnExists($conn, 'related_item_id');

    if (!$hasTypeColumn && !$hasRelatedItemColumn) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read)
                                VALUES (?, ?, 0)");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('is', $user_id, $message);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if ($hasTypeColumn && !$hasRelatedItemColumn) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read)
                                VALUES (?, ?, ?, 0)");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iss', $user_id, $message, $type);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if (!$hasTypeColumn && $hasRelatedItemColumn) {
        if ($related_item_id === null) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, related_item_id)
                                    VALUES (?, ?, 0, NULL)");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('is', $user_id, $message);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, related_item_id)
                                VALUES (?, ?, 0, ?)");

        if (!$stmt) {
            return false;
        }

        $relatedItemId = (int) $related_item_id;
        $stmt->bind_param('isi', $user_id, $message, $relatedItemId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if ($related_item_id === null) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, related_item_id)
                                VALUES (?, ?, ?, 0, NULL)");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iss', $user_id, $message, $type);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, related_item_id)
                            VALUES (?, ?, ?, 0, ?)");

    if (!$stmt) {
        return false;
    }

    $relatedItemId = (int) $related_item_id;
    $stmt->bind_param('issi', $user_id, $message, $type, $relatedItemId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function getUnreadCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $user_id = (int) $user_id;
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    $stmt->close();

    return (int) ($row['count'] ?? 0);
}

function getRecentNotifications($conn, $user_id, $limit = 10) {
    $selectFields = ['id', 'user_id', 'message', 'is_read', 'created_at'];

    if (notificationsColumnExists($conn, 'type')) {
        $selectFields[] = 'type';
    }

    if (notificationsColumnExists($conn, 'related_item_id')) {
        $selectFields[] = 'related_item_id';
    }

    $sql = "SELECT " . implode(', ', $selectFields) . "
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $user_id = (int) $user_id;
    $limit = (int) $limit;
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['is_read'] = (int) $row['is_read'];
            $row['type'] = $row['type'] ?? 'system';
            $row['related_item_id'] = isset($row['related_item_id']) && $row['related_item_id'] !== null ? (int) $row['related_item_id'] : null;
            $notifications[] = $row;
        }
    }

    $stmt->close();

    return $notifications;
}

function markAsRead($conn, $notification_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $notification_id = (int) $notification_id;
    $user_id = (int) $user_id;
    $stmt->bind_param('ii', $notification_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function markAllAsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $user_id = (int) $user_id;
    $stmt->bind_param('i', $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function formatNotificationTime($created_at) {
    $time = strtotime($created_at);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }

    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    return date('M d, Y', $time);
}

function buildOwnerMatchMessage($itemTitle, $location) {
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'your item';
    $safeLocation = trim((string) $location) !== '' ? trim((string) $location) : 'the reported location';
    return "Possible match found for your item '{$safeTitle}' at {$safeLocation}. Click to view details.";
}

function buildFinderMatchMessage($itemTitle) {
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'your found item';
    return "Someone might be the owner of your found item '{$safeTitle}'. Check now.";
}

function buildOwnerClaimMessage($itemTitle) {
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'your item';
    return "Your item '{$safeTitle}' has been successfully recovered!";
}

function buildFinderClaimMessage($itemTitle) {
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'the item';
    return "The item '{$safeTitle}' you reported has been claimed by its owner.";
}

function buildItemRequestMessage($requesterName, $requesterEmail, $itemTitle) {
    $safeName = trim((string) $requesterName) !== '' ? trim((string) $requesterName) : 'A user';
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'your posted item';
    $safeEmail = trim((string) $requesterEmail);

    if ($safeEmail !== '') {
        return "{$safeName} thinks '{$safeTitle}' may belong to them and asked you to get in touch. You can reply to {$safeEmail}.";
    }

    return "{$safeName} thinks '{$safeTitle}' may belong to them and asked you to get in touch.";
}

function hasRecentMatchingNotification($conn, $user_id, $message, $type, $related_item_id = null, $minutes = 30) {
    $user_id = (int) $user_id;
    $message = (string) $message;
    $type = (string) $type;
    $minutes = max(1, (int) $minutes);

    $hasTypeColumn = notificationsColumnExists($conn, 'type');
    $hasRelatedItemColumn = notificationsColumnExists($conn, 'related_item_id');

    $sql = "SELECT id FROM notifications WHERE user_id = ? AND message = ?";
    $types = 'is';
    $params = [$user_id, $message];

    if ($hasTypeColumn) {
        $sql .= " AND type = ?";
        $types .= 's';
        $params[] = $type;
    }

    if ($hasRelatedItemColumn) {
        if ($related_item_id === null) {
            $sql .= " AND related_item_id IS NULL";
        } else {
            $sql .= " AND related_item_id = ?";
            $types .= 'i';
            $params[] = (int) $related_item_id;
        }
    }

    $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1";
    $types .= 'i';
    $params[] = $minutes;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function buildNewItemPostedMessage($itemTitle, $status, $location) {
    $safeTitle = trim((string) $itemTitle) !== '' ? trim((string) $itemTitle) : 'an item';
    $safeStatus = strtolower(trim((string) $status)) === 'lost' ? 'lost' : 'found';
    $safeLocation = trim((string) $location);

    if ($safeLocation !== '') {
        return "A new {$safeStatus} item was posted: '{$safeTitle}' near {$safeLocation}. Tap to view it.";
    }

    return "A new {$safeStatus} item was posted: '{$safeTitle}'. Tap to view it.";
}

function notifyUsersAboutNewItem($conn, $item, $excludeUserId = null) {
    $itemId = (int) ($item['id'] ?? 0);
    if ($itemId <= 0) {
        return;
    }

    $message = buildNewItemPostedMessage(
        $item['title'] ?? '',
        $item['status'] ?? '',
        $item['specific_location'] ?? ''
    );

    $excludeUserId = $excludeUserId !== null ? (int) $excludeUserId : null;
    $sql = "SELECT id FROM users";

    if ($excludeUserId !== null && $excludeUserId > 0) {
        $sql .= " WHERE id <> ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    if ($excludeUserId !== null && $excludeUserId > 0) {
        $stmt->bind_param('i', $excludeUserId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $targetUserId = (int) ($row['id'] ?? 0);
            if ($targetUserId <= 0) {
                continue;
            }

            createNotification($conn, $targetUserId, $message, 'system', $itemId);
        }
    }

    $stmt->close();
}

function normalizeMatchText($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

function extractMatchKeywords($value) {
    $normalized = normalizeMatchText($value);

    if ($normalized === '') {
        return [];
    }

    $words = explode(' ', $normalized);
    $stopWords = [
        'the', 'and', 'for', 'with', 'from', 'that', 'this', 'have', 'has',
        'was', 'were', 'are', 'you', 'your', 'into', 'near', 'seen', 'item',
        'lost', 'found', 'black', 'white', 'gray', 'grey'
    ];

    $keywords = [];
    foreach ($words as $word) {
        if (strlen($word) < 3 || in_array($word, $stopWords, true)) {
            continue;
        }

        $keywords[] = $word;
    }

    return array_values(array_unique($keywords));
}

function calculateMatchScore($sourceItem, $candidateItem) {
    similar_text(
        normalizeMatchText($sourceItem['title'] ?? ''),
        normalizeMatchText($candidateItem['title'] ?? ''),
        $titlePercent
    );

    similar_text(
        normalizeMatchText($sourceItem['description'] ?? ''),
        normalizeMatchText($candidateItem['description'] ?? ''),
        $descriptionPercent
    );

    $sourceKeywords = extractMatchKeywords(($sourceItem['title'] ?? '') . ' ' . ($sourceItem['description'] ?? '') . ' ' . ($sourceItem['keywords'] ?? ''));
    $candidateKeywords = extractMatchKeywords(($candidateItem['title'] ?? '') . ' ' . ($candidateItem['description'] ?? '') . ' ' . ($candidateItem['keywords'] ?? ''));
    $sharedKeywords = array_intersect($sourceKeywords, $candidateKeywords);

    $keywordPercent = 0;
    if (count($sourceKeywords) > 0 || count($candidateKeywords) > 0) {
        $keywordPercent = (count($sharedKeywords) / max(count($sourceKeywords), count($candidateKeywords), 1)) * 100;
    }

    $categoryBonus = 0;
    if (
        normalizeMatchText($sourceItem['category'] ?? '') !== '' &&
        normalizeMatchText($sourceItem['category'] ?? '') === normalizeMatchText($candidateItem['category'] ?? '')
    ) {
        $categoryBonus = 10;
    }

    $score = ($titlePercent * 0.5) + ($descriptionPercent * 0.25) + ($keywordPercent * 0.25) + $categoryBonus;

    return [
        'score' => min(100, round($score)),
        'shared_keywords' => array_slice(array_values($sharedKeywords), 0, 4),
    ];
}

function findTopMatches($conn, $newItem, $limit = 3, $minimumScore = 35, $onlyUnclaimed = true) {
    $oppositeStatus = ($newItem['status'] ?? '') === 'lost' ? 'found' : 'lost';
    $sql = "SELECT id, title, description, category, status, image, user_id, claim_status, keywords, specific_location
            FROM items
            WHERE status = ?";

    if (!empty($newItem['id'])) {
        $sql .= " AND id <> ?";
    }

    if ($onlyUnclaimed) {
        $sql .= " AND (claim_status IS NULL OR claim_status = 'unclaimed')";
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if (!empty($newItem['id'])) {
        $itemId = (int) $newItem['id'];
        $stmt->bind_param('si', $oppositeStatus, $itemId);
    } else {
        $stmt->bind_param('s', $oppositeStatus);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $matches = [];

    if ($result) {
        while ($candidate = $result->fetch_assoc()) {
            $matchData = calculateMatchScore($newItem, $candidate);

            if ($matchData['score'] < $minimumScore) {
                continue;
            }

            $candidate['match_score'] = $matchData['score'];
            $candidate['shared_keywords'] = $matchData['shared_keywords'];
            $matches[] = $candidate;
        }
    }

    $stmt->close();

    usort($matches, static function ($left, $right) {
        return $right['match_score'] <=> $left['match_score'];
    });

    return array_slice($matches, 0, (int) $limit);
}

function findBestMatch($conn, $item, $minimumScore = 35) {
    $matches = findTopMatches($conn, $item, 1, $minimumScore, false);
    return $matches[0] ?? null;
}

function notifyMatchParticipants($conn, $newItem, $matches) {
    $newItemId = (int) ($newItem['id'] ?? 0);
    $newItemTitle = trim((string) ($newItem['title'] ?? 'item'));
    $newItemTitle = $newItemTitle !== '' ? $newItemTitle : 'item';
    $newItemUserId = (int) ($newItem['user_id'] ?? 0);
    $newItemStatus = (string) ($newItem['status'] ?? '');
    $newItemLocation = trim((string) ($newItem['specific_location'] ?? ''));

    foreach ($matches as $match) {
        $matchUserId = (int) ($match['user_id'] ?? 0);
        $matchTitle = trim((string) ($match['title'] ?? 'item'));
        $matchLocation = trim((string) ($match['specific_location'] ?? ''));

        if ($newItemStatus === 'lost') {
            if ($newItemUserId > 0) {
                createNotification(
                    $conn,
                    $newItemUserId,
                    buildOwnerMatchMessage($newItemTitle, $matchLocation),
                    'match',
                    (int) $match['id']
                );
            }

            if ($matchUserId > 0) {
                createNotification(
                    $conn,
                    $matchUserId,
                    buildFinderMatchMessage($matchTitle),
                    'match',
                    $newItemId
                );
            }
        } elseif ($newItemStatus === 'found') {
            if ($matchUserId > 0) {
                createNotification(
                    $conn,
                    $matchUserId,
                    buildOwnerMatchMessage($matchTitle, $newItemLocation),
                    'match',
                    $newItemId
                );
            }

            if ($newItemUserId > 0) {
                createNotification(
                    $conn,
                    $newItemUserId,
                    buildFinderMatchMessage($newItemTitle),
                    'match',
                    (int) $match['id']
                );
            }
        }
    }
}

function notifyClaimParticipants($conn, $item, $matchedItem = null, $claimingUserId = null) {
    $itemId = (int) ($item['id'] ?? 0);
    $itemUserId = (int) ($item['user_id'] ?? 0);
    $itemStatus = (string) ($item['status'] ?? '');
    $itemTitle = trim((string) ($item['title'] ?? 'item'));
    $claimingUserId = $claimingUserId !== null ? (int) $claimingUserId : null;

    if ($itemStatus === 'found') {
        if ($claimingUserId !== null && $claimingUserId > 0) {
            createNotification(
                $conn,
                $claimingUserId,
                buildOwnerClaimMessage($itemTitle),
                'claim',
                $itemId
            );
        } elseif ($matchedItem && !empty($matchedItem['user_id'])) {
            createNotification(
                $conn,
                (int) $matchedItem['user_id'],
                buildOwnerClaimMessage($itemTitle),
                'claim',
                $itemId
            );
        }

        if ($itemUserId > 0) {
            createNotification(
                $conn,
                $itemUserId,
                buildFinderClaimMessage($itemTitle),
                'claim',
                $itemId
            );
        }

        return;
    }

    if ($itemUserId > 0) {
        createNotification(
            $conn,
            $itemUserId,
            buildOwnerClaimMessage($itemTitle),
            'claim',
            $itemId
        );
    }

    if ($matchedItem && !empty($matchedItem['user_id'])) {
        createNotification(
            $conn,
            (int) $matchedItem['user_id'],
            buildFinderClaimMessage($matchedItem['title'] ?? $itemTitle),
            'claim',
            $itemId
        );
    }
}

?>
