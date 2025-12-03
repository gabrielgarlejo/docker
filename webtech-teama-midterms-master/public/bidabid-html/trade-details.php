<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $redirectUrl = 'trade-details.php?id=' . ($_GET['id'] ?? '');
    header('Location: login.php?redirect=' . urlencode($redirectUrl));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_feedback') {
    header('Content-Type: application/json');

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
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            break;
        } catch (Throwable $ignored) {
        }
    }

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_connect_failed']);
        exit;
    }

    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                f.feedback_id,
                f.user1_id   AS to_user_id,
                f.user2_id   AS from_user_id,
                f.rating,
                f.comment,
                u.username   AS from_username
            FROM feedback f
            JOIN users u ON u.user_id = f.user2_id
            WHERE f.user1_id = :uid
            ORDER BY f.feedback_id DESC
            LIMIT 50
        ");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        $avgStmt = $pdo->prepare("
            SELECT ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS total
            FROM feedback
            WHERE user1_id = :uid
        ");
        $avgStmt->execute([':uid' => $userId]);
        $avgRow = $avgStmt->fetch() ?: ['avg_rating' => null, 'total' => 0];

        echo json_encode([
            'ok' => true,
            'feedbacks' => $rows,
            'avg' => is_null($avgRow['avg_rating']) ? null : (float) $avgRow['avg_rating'],
            'count' => (int) $avgRow['total']
        ]);
    } catch (Throwable $t) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'query_failed']);
    }
    exit;
}

$itemId = $_GET['id'] ?? null;
if (!$itemId || !is_numeric($itemId)) {
    die("A valid Item ID is required.");
}

$loggedInUserId = $_SESSION['user_id'];
$dbName = 'bidabid';
$dbUser = 'root';
$dbPass = '';

$targetItem = null;
$userOwnedItems = [];
$ownerAvgRating = null;
$ownerFeedbackCount = 0;

try {
    $candidates = [
        ['host' => '127.0.0.1', 'port' => '3306'],
        ['host' => 'localhost', 'port' => '3306'],
    ];
    $pdo = null;
    foreach ($candidates as $c) {
        try {
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            break;
        } catch (Throwable $ignored) {
        }
    }
    if (!$pdo) {
        throw new RuntimeException('Database Error: could not connect');
    }

    $sql = "SELECT i.item_id, i.item_name, i.description, i.item_condition, i.image_url, i.user_id, u.username 
            FROM items i 
            JOIN users u ON i.user_id = u.user_id 
            WHERE i.item_id = :item_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':item_id' => $itemId]);
    $targetItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetItem) {
        die("Item not found.");
    }
    $avgStmt = $pdo->prepare("
        SELECT ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS total
        FROM feedback
        WHERE user1_id = :uid
    ");
    $avgStmt->execute([':uid' => $targetItem['user_id']]);
    $avgRow = $avgStmt->fetch(PDO::FETCH_ASSOC);
    if ($avgRow && (int) $avgRow['total'] > 0) {
        $ownerAvgRating = (float) $avgRow['avg_rating'];
        $ownerFeedbackCount = (int) $avgRow['total'];
    }

    $userItemsStmt = $pdo->prepare("SELECT item_id, item_name FROM items WHERE user_id = :user_id AND listing_type = 'Trade'");
    $userItemsStmt->execute([':user_id' => $loggedInUserId]);
    $userOwnedItems = $userItemsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function stars_from_avg(?float $avg): string
{
    if ($avg === null)
        return '☆☆☆☆☆';
    $full = (int) floor($avg);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Trade Details</title>
    <link rel="stylesheet" href="../bidabid-css/style.css" />
    <link rel="stylesheet" href="../bidabid-css/trade-details.css" />
</head>

<body>
    <header class="navbar">
        <div class="logo">
            <span class="red">B</span><span class="red">i</span><span class="red">d</span><span
                class="yellow">A</span><span class="blue">B</span><span class="blue">i</span><span class="blue">d</span>
        </div>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade-items.php">Trade</a>
            <?php if ($loggedInUserId): ?>
                <a href="my-items.php">My Items</a>
                <a href="post-item.php">Post Item</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="registration.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="container">
        <div class="item-display-panel">
            <h1><?= htmlspecialchars($targetItem['item_name']) ?></h1>
            <?php
            $ownerStars = stars_from_avg($ownerAvgRating);
            $ownerAvgTxt = is_null($ownerAvgRating) ? '—' : number_format($ownerAvgRating, 2);
            $ownerCount = (int) $ownerFeedbackCount;
            ?>
            <p class="owner">
                Owned by:
                <a href="#" class="seller-link" id="owner-feedback-link"
                    data-user-id="<?= (int) $targetItem['user_id'] ?>">
                    <strong><?= htmlspecialchars($targetItem['username']) ?></strong>
                </a>
                <span class="seller-rating" title="<?= $ownerCount ?> rating(s)">
                    <span class="stars"><?= $ownerStars ?></span>
                    <span class="num"><?= $ownerAvgTxt ?></span>
                </span>
            </p>
            <?php
            $imgSrc = (!empty($targetItem['image_url'])) ? 'data:image/jpeg;base64,' . base64_encode($targetItem['image_url']) : 'https://via.placeholder.com/600x400';
            ?>
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($targetItem['item_name']) ?>" class="main-item-image" />
            <div class="item-info">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($targetItem['description'])) ?></p>
                <h3>Condition</h3>
                <p><?= htmlspecialchars($targetItem['item_condition']) ?></p>
            </div>
        </div>
        <div class="offer-panel">
            <h2>Propose a Trade</h2>

            <?php if ($targetItem['user_id'] == $loggedInUserId): ?>
                <p class="notice">This is your own item. You cannot make an offer.</p>
            <?php elseif (empty($userOwnedItems)): ?>
                <p class="notice">You have no items listed for trade. <a href="post-item.php">Post an item</a> to make
                    offers.</p>
            <?php else: ?>
                <form id="demo-offer-form" action="#" onsubmit="return false;">
                    <input type="hidden" name="target_item_id" value="<?= $targetItem['item_id'] ?>">
                    <input type="hidden" name="responder_id" value="<?= $targetItem['user_id'] ?>">
                    <div class="form-group">
                        <label for="user_item_id">Select Your Item to Offer:</label>
                        <select name="user_item_id" id="user_item_id" required>
                            <option value="">-- Choose one of your items --</option>
                            <?php foreach ($userOwnedItems as $ownedItem): ?>
                                <option value="<?= $ownedItem['item_id'] ?>"><?= htmlspecialchars($ownedItem['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="submit-offer-btn">Submit Trade Proposal</button>
                    <p id="demo-note" class="notice" style="display:none;margin-top:10px;">Proposal Sent!</p>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <div id="feedback-pane" class="feedback-pane" aria-hidden="true">
        <div class="fb-header">
            <span id="fb-title">Feedback</span>
            <button id="fb-close" type="button" aria-label="Close">×</button>
        </div>
        <div id="fb-body" class="fb-body">
            <div class="fb-loading">Loading…</div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2025 BidABid. All rights reserved.</p>
    </footer>
    <script src="../bidabid-js/trade-details"></script>
</body>

</html>