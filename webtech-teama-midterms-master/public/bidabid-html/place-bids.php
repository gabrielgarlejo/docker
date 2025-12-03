<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();

header('Content-Type: application/json');

function db_connect(): PDO
{
    $dbName = 'bidabid';
    $dbUser = 'root';
    $dbPass = '';
    $candidates = [
        ['host' => '127.0.0.1', 'port' => '3306'],
        ['host' => 'localhost', 'port' => '3306'],
        ['host' => '127.0.0.1', 'port' => '3307'],
        ['host' => 'localhost', 'port' => '3307'],
    ];
    foreach ($candidates as $c) {
        try {
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$dbName};charset=utf8mb4";
            return new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $ignored) {
        }
    }
    throw new RuntimeException('DB connection failed');
}

try {
    $pdo = db_connect();

    // Must be POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized', 'message' => 'Please log in to place a bid']);
        exit;
    }

    $sessionUserId = (int) $_SESSION['user_id'];

    // Get POST data
    $itemIdRaw = $_POST['item_id'] ?? null;
    $newBidRaw = $_POST['new_bid'] ?? null;

    $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
    $newBid = is_numeric($newBidRaw) ? (float) $newBidRaw : 0.0;

    // Validate item_id
    if ($itemId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_item_id', 'message' => 'Invalid item ID']);
        exit;
    }

    // Validate new_bid
    if ($newBid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_bid_amount', 'message' => 'Invalid bid amount']);
        exit;
    }

    // Fetch item
    $it = $pdo->prepare('SELECT item_id, item_name, starting_price, listing_type, user_id AS owner_id FROM items WHERE item_id = :i LIMIT 1');
    $it->execute([':i' => $itemId]);
    $itemRow = $it->fetch();

    if (!$itemRow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found', 'message' => 'Item not found']);
        exit;
    }

    // Check listing type
    if (($itemRow['listing_type'] ?? '') !== 'Auction') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_listing', 'message' => 'This item is not an auction']);
        exit;
    }

    // Find auction
    $sa = $pdo->prepare('SELECT auction_id, item_id, start_time, end_time, status FROM auctions WHERE item_id = :i ORDER BY auction_id DESC LIMIT 1');
    $sa->execute([':i' => $itemId]);
    $auction = $sa->fetch();

    if (!$auction) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found', 'message' => 'No auction found for this item']);
        exit;
    }

    // Check auction active status
    $now = date('Y-m-d H:i:s');
    $activeByTime = ($auction['start_time'] <= $now && $now <= $auction['end_time']);
    $activeByFlag = (isset($auction['status']) && strtolower((string) $auction['status']) === 'active');
    $isActive = ($activeByTime || $activeByFlag);

    if (!$isActive) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'inactive_auction', 'message' => 'This auction is not active']);
        exit;
    }

    // Check if owner
    $ownerId = (int) ($itemRow['owner_id'] ?? 0);
    if ($ownerId === $sessionUserId) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden', 'message' => 'You cannot bid on your own item']);
        exit;
    }

    // Get current highest bid and starting price
    $startingPrice = (float) ($itemRow['starting_price'] ?? 0);
    $mx = $pdo->prepare('SELECT COALESCE(MAX(amount),0) AS max_amount FROM bids WHERE auction_id = :a');
    $mx->execute([':a' => (int) $auction['auction_id']]);
    $highest = (float) ($mx->fetch()['max_amount'] ?? 0);
    $minRequired = max($startingPrice, $highest);

    // Check if bid is high enough
    if ($newBid <= $minRequired) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'bid_too_low',
            'message' => 'Your bid must be higher than ₱' . number_format($minRequired, 2)
        ]);
        exit;
    }

    // Check wallet balance
    $wal = $pdo->prepare('SELECT balance FROM users WHERE user_id = :u LIMIT 1');
    $wal->execute([':u' => $sessionUserId]);
    $walletBalance = (float) ($wal->fetch()['balance'] ?? 0);

    if ($walletBalance < $newBid) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'insufficient_balance',
            'message' => 'Insufficient balance'
        ]);
        exit;
    }

    // Transaction: Insert bid and deduct balance
    $pdo->beginTransaction();

    try {
        // Lock user balance
        $lu = $pdo->prepare('SELECT balance FROM users WHERE user_id = :u FOR UPDATE');
        $lu->execute([':u' => $sessionUserId]);
        $balanceNow = (float) $lu->fetch()['balance'];

        if ($balanceNow < $newBid) {
            throw new RuntimeException('Balance changed during transaction');
        }

        // Lock highest bid
        $mxLock = $pdo->prepare('SELECT amount FROM bids WHERE auction_id = :a ORDER BY amount DESC LIMIT 1 FOR UPDATE');
        $mxLock->execute([':a' => (int) $auction['auction_id']]);
        $rowMx = $mxLock->fetch();
        $highestNow = $rowMx ? (float) $rowMx['amount'] : 0.0;
        $minNow = max($startingPrice, $highestNow);

        if ($newBid <= $minNow) {
            throw new RuntimeException('Another bid was placed before yours');
        }

        // Insert bid
        $ins = $pdo->prepare('INSERT INTO bids (auction_id, user_id, amount, bid_time) VALUES (:a, :u, :amt, NOW())');
        $ins->execute([
            ':a' => (int) $auction['auction_id'],
            ':u' => $sessionUserId,
            ':amt' => $newBid,
        ]);
        $newBidId = (int) $pdo->lastInsertId();

        // Deduct wallet
        $upd = $pdo->prepare('UPDATE users SET balance = balance - :amt WHERE user_id = :u');
        $upd->execute([
            ':amt' => $newBid,
            ':u' => $sessionUserId,
        ]);

        $pdo->commit();

        // Get updated balance
        $walNew = $pdo->prepare('SELECT balance FROM users WHERE user_id = :u LIMIT 1');
        $walNew->execute([':u' => $sessionUserId]);
        $newBalance = (float) ($walNew->fetch()['balance'] ?? 0);

        echo json_encode([
            'ok' => true,
            'message' => 'Bid placed successfully!',
            'bid_id' => $newBidId,
            'amount' => $newBid,
            'new_balance' => $newBalance
        ]);

    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'transaction_failed', 'message' => $ex->getMessage()]);
    }

} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => $t->getMessage()]);
}
?>