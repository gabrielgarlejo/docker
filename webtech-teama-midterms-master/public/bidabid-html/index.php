<?php
if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();
date_default_timezone_set('Asia/Manila');
$currentDate = date('Y-m-d H:i:s');
$loggedIn = isset($_SESSION['user_id']);
$loggedInUserId = $_SESSION['user_id'] ?? null;
$userName = null;
$allCategories = [];
$itemsByCategory = [];
$auctionsByItem = [];
try {
    $dbName = 'bidabid';
    $dbUser = 'root';
    $dbPass = '';
    $candidates = [
        ['host' => '127.0.0.1', 'port' => '3306'],
        ['host' => 'localhost', 'port' => '3306'],
    ];
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
    if ($loggedInUserId) {
        $userStmt = $pdo->prepare("SELECT username FROM users WHERE user_id = :user_id LIMIT 1");
        $userStmt->execute([':user_id' => $loggedInUserId]);
        $userResult = $userStmt->fetch();
        if ($userResult) {
            $userName = $userResult['username'];
        }
    }
    $allCategories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();

    // Fetch auctions (only active)
    foreach ($pdo->query("SELECT * FROM auctions WHERE status = 'Active'") as $auc) {
        $auctionsByItem[$auc['item_id']] = $auc;
    }

    // Fetch only items that have auction records with active status
    if ($loggedInUserId) {
        $stmt = $pdo->prepare("
            SELECT i.*
            FROM items i
            JOIN auctions a ON i.item_id = a.item_id
            WHERE i.listing_type = 'Auction' AND i.user_id != :user_id AND a.status = 'Active'
            ORDER BY i.item_id DESC
        ");
        $stmt->execute([':user_id' => $loggedInUserId]);
    } else {
        $stmt = $pdo->query("
            SELECT i.*
            FROM items i
            JOIN auctions a ON i.item_id = a.item_id
            WHERE i.listing_type = 'Auction' AND a.status = 'Active'
            ORDER BY i.item_id DESC
        ");
    }
    $allItems = $stmt->fetchAll();
    foreach ($allItems as $item) {
        $itemsByCategory[$item['category_id']][] = $item;
    }
} catch (Throwable $e) {
}

function getAuctionStatus($item, $auctionsByItem, $now)
{
    if (empty($auctionsByItem[$item['item_id']]))
        return null;
    $auc = $auctionsByItem[$item['item_id']];
    if ($auc['start_time'] <= $now && $now <= $auc['end_time'])
        return ['type' => 'live', 'info' => $auc];
    if ($auc['start_time'] > $now)
        return ['type' => 'upcoming', 'info' => $auc];
    return ['type' => 'ended', 'info' => $auc];
}

function sortByTimeRemaining(&$items, $auctionsByItem, $now, $isLive = true)
{
    usort($items, function($a, $b) use ($auctionsByItem, $now, $isLive) {
        $aucA = $auctionsByItem[$a['item']['item_id']] ?? null;
        $aucB = $auctionsByItem[$b['item']['item_id']] ?? null;
        if (!$aucA || !$aucB) return 0;
        
        if ($isLive) {
            $timeA = strtotime($aucA['end_time']) - strtotime($now);
            $timeB = strtotime($aucB['end_time']) - strtotime($now);
        } else {
            $timeA = strtotime($aucA['start_time']) - strtotime($now);
            $timeB = strtotime($aucB['start_time']) - strtotime($now);
        }
        return $timeA <=> $timeB;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BidABid - Online Auction & Trading</title>
    <link rel="stylesheet" href="../bidabid-css/style.css" />
    <link rel="stylesheet" href="../bidabid-css/auction-style.css" />
</head>
<body>
    <?php if ($userName): ?>
        <div class="user-greeting">Welcome, <strong><?= htmlspecialchars($userName) ?></strong></div>
    <?php endif; ?>
    <header class="navbar">
        <div class="logo">
            <span class="red">B</span><span class="red">i</span><span class="red">d</span><span
                class="yellow">A</span><span class="blue">B</span><span class="blue">i</span><span class="blue">d</span>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade-items.php">Trade</a>
            <?php if ($loggedIn): ?>
                <a href="my-items.php">My Items</a>
                <a href="post-item.php">Post Item</a>
                <a href="signout.php">Sign Out</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="registration.php">Register</a>
            <?php endif; ?>
        </nav>
        <div class="search-container">
            <input type="text" placeholder="Search items..." id="search-bar" />
            <button id="search-btn">Search</button>
        </div>
    </header>
    <section class="categories">
        <button class="category active" data-cat="All">All</button>
        <?php foreach ($allCategories as $category): ?>
            <button class="category" data-cat="<?= htmlspecialchars($category['category_name']) ?>">
                <?= htmlspecialchars($category['category_name']) ?>
            </button>
        <?php endforeach; ?>
    </section>
    <?php foreach ($allCategories as $cat):
        $catId = $cat['category_id'];
        $catName = $cat['category_name'];
        $live = [];
        $future = [];
        foreach (($itemsByCategory[$catId] ?? []) as $item) {
            $status = getAuctionStatus($item, $auctionsByItem, $currentDate);
            if (!$status)
                continue;
            if ($status['type'] === 'live')
                $live[] = ['item' => $item, 'auction' => $status['info']];
            elseif ($status['type'] === 'upcoming')
                $future[] = ['item' => $item, 'auction' => $status['info']];
        }
        sortByTimeRemaining($live, $auctionsByItem, $currentDate, true);
        sortByTimeRemaining($future, $auctionsByItem, $currentDate, false);
        ?>
        <section class="section" data-cat-section="<?= htmlspecialchars($catName) ?>">
            <div class="auctions-row-title"><?= htmlspecialchars($catName) ?> - Live Auctions</div>
            <div class="section-header" style="margin-bottom:.3em;">
                <div class="arrows">
                    <button class="arrow left" data-target="live-auctions-<?= $catId ?>">&lt;</button>
                    <button class="arrow right" data-target="live-auctions-<?= $catId ?>">&gt;</button>
                </div>
            </div>
            <div class="card-container" id="live-auctions-<?= $catId ?>">
                <?php if (empty($live)): ?>
                    <p style="color:#777;">No live auctions in this category.</p>
                <?php else: ?>
                    <?php foreach ($live as $it):
                        $item = $it['item'];
                        $auc = $it['auction']; ?>
                        <div class="card" data-cat="<?= htmlspecialchars($catName) ?>" data-item-id="<?= (int) $item['item_id'] ?>"
                            style="cursor:pointer;">
                            <span class="auction-phase-label">LIVE</span>
                            <div class="timer" data-end="<?= htmlspecialchars($auc['end_time']) ?>"></div>
                            <?php $imgSrc = (!empty($item['image_url'])) ? 'data:image/jpeg;base64,' . base64_encode($item['image_url']) : 'https://via.placeholder.com/200x150'; ?>
                            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                            <p><?= htmlspecialchars($item['description']) ?></p>
                            <span class="view-details-btn"><strong>Bid Now</strong></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="auctions-row-title"><?= htmlspecialchars($catName) ?> - Upcoming Auctions</div>
            <div class="section-header" style="margin-bottom:.3em;">
                <div class="arrows">
                    <button class="arrow left" data-target="upcoming-auctions-<?= $catId ?>">&lt;</button>
                    <button class="arrow right" data-target="upcoming-auctions-<?= $catId ?>">&gt;</button>
                </div>
            </div>
            <div class="card-container" id="upcoming-auctions-<?= $catId ?>">
                <?php if (empty($future)): ?>
                    <p style="color:#777;">No upcoming auctions in this category.</p>
                <?php else: ?>
                    <?php foreach ($future as $it):
                        $item = $it['item'];
                        $auc = $it['auction']; ?>
                        <?php $imgSrc = (!empty($item['image_url'])) ? 'data:image/jpeg;base64,' . base64_encode($item['image_url']) : 'https://via.placeholder.com/200x150'; ?>
                        <div class="card card-upcoming" data-cat="<?= htmlspecialchars($catName) ?>"
                            data-item-id="<?= (int) $item['item_id'] ?>" data-title="<?= htmlspecialchars($item['item_name']) ?>"
                            data-desc="<?= htmlspecialchars($item['description']) ?>" data-img="<?= $imgSrc ?>"
                            data-auctionstart="<?= htmlspecialchars($auc['start_time']) ?>" style="opacity:.78; cursor:pointer;">
                            <span class="auction-phase-label upcoming">UPCOMING</span>
                            <div class="timer" data-end="<?= htmlspecialchars($auc['start_time']) ?>" data-upcoming="1"></div>
                            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                            <p><?= htmlspecialchars($item['description']) ?></p>
                            <span class="view-details-btn" style="opacity:0.8;">Coming Soon</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <footer class="footer">
        <p>&copy; 2025 BidABid. All rights reserved.</p>
    </footer>
    <div id="quick-view-modal">
        <div id="quick-view-content">
            <button id="quick-view-close" type="button">&times;</button>
            <div id="quick-view-body"></div>
        </div>
    </div>
    <script src="../bidabid-js/script.js"></script>
</body>
</html>
