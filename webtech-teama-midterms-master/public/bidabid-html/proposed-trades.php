<?php
date_default_timezone_set('Asia/Manila');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$itemId = (int) ($_GET['id'] ?? 0);


$message = $_SESSION['message'] ?? null;
$messageType = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

$itemDetails = null;
$proposals = []; 
$itemOwned = false;

try {
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
        } catch (Throwable $e) {
        }
    }

    if (!$pdo) {
        throw new Exception("Could not connect to database");
    }


    $itemStmt = $pdo->prepare("
        SELECT i.*, c.category_name, i.user_id as owner_id
        FROM items i 
        INNER JOIN categories c ON i.category_id = c.category_id 
        WHERE i.item_id = ? AND i.user_id = ?
        LIMIT 1
    ");
    $itemStmt->execute([$itemId, $currentUserId]);
    $itemDetails = $itemStmt->fetch();
    $itemOwned = !empty($itemDetails);

    if (!$itemOwned) {
        $itemDetails = null;
    }

    $proposalsStmt = $pdo->prepare("
        SELECT t.*, 
               target_item.item_name as target_item_name, 
               target_item.image_url as target_item_image,
               opponent.username as opponent_username, 
               opponent.user_id as opponent_id
        FROM trades t 
        LEFT JOIN items target_item ON t.item2_id = target_item.item_id
        LEFT JOIN users opponent ON t.responder_id = opponent.user_id
        WHERE t.initiator_id = ? AND t.item1_id = ? AND t.status = 'proposed'
        ORDER BY t.proposed_at DESC
    ");
    $proposalsStmt->execute([$currentUserId, $itemId]);
    $proposals = $proposalsStmt->fetchAll();

} catch (Throwable $e) {
    error_log("Database error: " . $e->getMessage());
    $proposals = [];
    $itemDetails = null;
    $itemOwned = false;
}

function getImageSrc($imageUrl)
{
    if (empty($imageUrl)) {
        return 'https://via.placeholder.com/200x150?text=No+Image';
    }
    try {
        return 'data:image/jpeg;base64,' . base64_encode($imageUrl);
    } catch (Exception $e) {
        return 'https://via.placeholder.com/200x150?text=Image+Error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Your Proposed Trades - <?= htmlspecialchars($itemDetails['item_name'] ?? 'Item ID ' . $itemId) ?> - BidABid
    </title>
    <link rel="stylesheet" href="../bidabid-css/style.css" />
    <link rel="stylesheet" href="../bidabid-css/proposed-trades.css" />
</head>

<body>

    <header class="navbar">
        <div class="logo">
            <span class="red">B</span><span class="red">i</span><span class="red">d</span><span
                class="yellow">A</span><span class="blue">B</span><span class="blue">i</span><span class="blue">d</span>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade-items.php">Trade</a>
            <a href="my-items.php" class="active">My Items</a>
            <a href="post-item.php">Post Item</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Your Proposed Trades for Item ID <?= $itemId ?></h1>
            <p>Trades you proposed using your item (User ID: <?= $currentUserId ?>)</p>
        </div>

        <?php if ($message): ?>
            <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($itemDetails || !empty($proposals)): ?>
            
            <?php if ($itemDetails): ?>
                <section class="section">
                    <div class="item-details">
                        <img src="<?= getImageSrc($itemDetails['image_url'] ?? '') ?>"
                            alt="<?= htmlspecialchars($itemDetails['item_name'] ?? '') ?>">
                        <h2><?= htmlspecialchars($itemDetails['item_name'] ?? '') ?> (Your Offered Item)</h2>
                        <p><?= htmlspecialchars($itemDetails['description'] ?? '') ?></p>
                        <div class="item-info">
                            <strong>Category:</strong> <?= htmlspecialchars($itemDetails['category_name'] ?? '') ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            
            <section class="section">
                <div class="section-header">
                    <h2>Your Proposed Trades (<?= count($proposals) ?>)</h2>
                    <?php if (empty($proposals)): ?>
                        <p>No proposed trades found for your item ID <?= $itemId ?>.</p>
                    <?php endif; ?>
                </div>
                <div class="card-container">
                    <?php if (!empty($proposals)): ?>
                        <?php foreach ($proposals as $proposal): ?>
                            <div class="proposal-card">
                                <img src="<?= getImageSrc($proposal['target_item_image'] ?? '') ?>"
                                    alt="<?= htmlspecialchars($proposal['target_item_name'] ?? 'No Target Image') ?>"
                                    class="proposal-item-img">
                                <div class="proposal-info">
                                    <h4><strong>From:</strong>
                                        <?= htmlspecialchars($proposal['opponent_username'] ?? 'Unknown Opponent (ID: ' . $proposal['responder_id'] . ')') ?>
                                    </h4>
                                    <p><strong>Offered Item:</strong>
                                        <?= htmlspecialchars($proposal['target_item_name'] ?? 'No Target Specified (ID: ' . $proposal['item2_id'] . ')') ?>
                                    </p>
                                    <p><strong>Your item they want:</strong>
                                        <?= htmlspecialchars($itemDetails['item_name'] ?? 'Your Item ID ' . $itemId) ?></p>
                                    <p><strong>Status:</strong> <?= ucfirst($proposal['status']) ?> (Waiting for your response)
                                    </p>
                                    <small><strong>Proposed on:</strong>
                                        <?= date('Y-m-d H:i:s', strtotime($proposal['proposed_at'] ?? 'now')) ?></small>
                                </div>
                                <div class="proposal-actions">
                                    <a href="trade-details.php?id=<?= $proposal['item2_id'] ?>" class="view-btn">View Details</a>                                    
                                    <form method="POST" action="update-proposal.php" style="display: inline;">
                                        <input type="hidden" name="trade_id"
                                            value="<?= htmlspecialchars($proposal['trade_id'] ?? '') ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                        <button type="submit" class="cancel-btn"
                                            onclick="return confirm('Are you sure you want to cancel this proposal?')">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-proposals">
                            <p>No proposed trades yet for this item. Propose some!</p>
                            <a href="trade-items.php">Browse & Propose Trades</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="error-message">
                <p>Item ID <?= $itemId ?> not found or you do not own it (User ID: <?= $currentUserId ?>). Access denied.
                </p>
                <a href="my-items.php">Back to My Items</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <p>&copy; 2025 BidABid. All rights reserved.</p>
    </footer>

    <script src="../bidabid-js/script.js"></script>
</body>

</html>