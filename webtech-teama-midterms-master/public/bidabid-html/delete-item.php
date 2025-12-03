<?php
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$dbName = 'bidabid';
$dbUser = 'root';
$dbPass = '';
$candidates = [
    ['host' => '127.0.0.1', 'port' => '3306'],
    ['host' => 'localhost', 'port' => '3306'],
];

$pdo = null;
foreach ($candidates as $c) {
    try {
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        break;
    } catch (Throwable $e) {}
}
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$auctionId = $_POST['auction_id'] ?? null;
$itemId = $_POST['item_id'] ?? null;
$listingType = $_POST['listing_type'] ?? 'auction';

if (!$auctionId && !$itemId) {
    echo json_encode(['success' => false, 'message' => 'Missing auction_id or item_id']);
    exit;
}

try {
    if ($listingType === 'auction' && $auctionId) {
        $stmt = $pdo->prepare("SELECT i.user_id FROM auctions a INNER JOIN items i ON a.item_id = i.item_id WHERE a.auction_id = ? LIMIT 1");
        $stmt->execute([$auctionId]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Auction not found']);
            exit;
        }
        if ($row['user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $upd = $pdo->prepare("UPDATE auctions SET status = 'Cancelled' WHERE auction_id = ?");
        $upd->execute([$auctionId]);
        if ($upd->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Auction update failed']);
        }
        exit;
    } elseif ($listingType === 'trade' && $itemId) {
        $stmt = $pdo->prepare("SELECT user_id FROM items WHERE item_id = ? AND listing_type = 'Trade' LIMIT 1");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Trade item not found']);
            exit;
        }
        if ($row['user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $del = $pdo->prepare("DELETE FROM items WHERE item_id = ? AND listing_type = 'Trade'");
        $del->execute([$itemId]);
        if ($del->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Trade item delete failed']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
