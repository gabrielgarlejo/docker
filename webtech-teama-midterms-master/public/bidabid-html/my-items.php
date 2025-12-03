<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
$currentDate = date('Y-m-d H:i:s');

$userId = $_SESSION['user_id'] ?? null;
$loggedIn = !empty($userId);
$userName = null;
$liveAuctionItems = [];
$upcomingAuctionItems = [];
$liveBidItems = [];
$upcomingBidItems = [];
$userAuctionItems = [];
$userBidItems = [];
$userTradeItems = [];
$auctionsByItemSeller = [];
$bidsByAuctionSeller = [];
$auctionsByItemBid = [];
$bidsByAuctionBid = [];
$userBidsByAuction = [];

if (!$loggedIn) {
    header('Location: login.php');
    exit;
}

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

    $userStmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $userResult = $userStmt->fetch();
    $userName = $userResult['username'] ?? null;

    $sellerItemStmt = $pdo->prepare("
        SELECT i.*, c.category_name 
        FROM items i 
        INNER JOIN categories c ON i.category_id = c.category_id 
        WHERE i.user_id = ? AND i.listing_type = 'Auction' 
        ORDER BY i.item_id DESC
    ");
    $sellerItemStmt->execute([$userId]);
    $userAuctionItems = $sellerItemStmt->fetchAll();

    $sellerItemIds = array_column($userAuctionItems, 'item_id');
    if (!empty($sellerItemIds)) {
        $placeholders = str_repeat('?,', count($sellerItemIds) - 1) . '?';
        $aucStmt = $pdo->prepare("SELECT * FROM auctions WHERE item_id IN ($placeholders)");
        $aucStmt->execute($sellerItemIds);
        $auctions = $aucStmt->fetchAll();
        foreach ($auctions as $auc) {
            $auctionsByItemSeller[$auc['item_id']] = $auc;
        }

        $sellerAuctionIds = array_column($auctions, 'auction_id');
        if (!empty($sellerAuctionIds)) {
            $placeholders = str_repeat('?,', count($sellerAuctionIds) - 1) . '?';
            $bidsStmt = $pdo->prepare("
                SELECT auction_id, MAX(amount) as highest_bid, COUNT(*) as bid_count 
                FROM bids 
                WHERE auction_id IN ($placeholders) 
                GROUP BY auction_id
            ");
            $bidsStmt->execute($sellerAuctionIds);
            $bids = $bidsStmt->fetchAll();
            foreach ($bids as $bid) {
                $bidsByAuctionSeller[$bid['auction_id']] = $bid;
            }
        }
    }

    foreach ($userAuctionItems as $item) {
        $status = getAuctionStatus($item['item_id'], $auctionsByItemSeller, $bidsByAuctionSeller, $currentDate);
        if (!$status)
            continue;

        $status['item'] = $item;
        if ($status['type'] === 'live') {
            $liveAuctionItems[] = $status;
        } elseif ($status['type'] === 'upcoming') {
            $upcomingAuctionItems[] = $status;
        }
    }
    if (!empty($liveAuctionItems)) {
        usort($liveAuctionItems, function ($a, $b) {
            return strtotime($a['auction']['end_time'] ?? '') <=> strtotime($b['auction']['end_time'] ?? '');
        });
    }
    if (!empty($upcomingAuctionItems)) {
        usort($upcomingAuctionItems, function ($a, $b) {
            return strtotime($a['auction']['start_time'] ?? '') <=> strtotime($b['auction']['start_time'] ?? '');
        });
    }

    $userBidsStmt = $pdo->prepare("
        SELECT b.auction_id, b.amount as user_bid_amount, b.bid_time,
               a.item_id, a.start_time, a.end_time, a.status as auction_status, a.auction_id as full_auction_id
        FROM bids b 
        INNER JOIN auctions a ON b.auction_id = a.auction_id 
        WHERE b.user_id = ? 
        ORDER BY b.auction_id, b.amount DESC
    ");
    $userBidsStmt->execute([$userId]);
    $userBidsRaw = $userBidsStmt->fetchAll();

    $processedUserBids = [];
    foreach ($userBidsRaw as $bid) {
        $auctionId = $bid['auction_id'];
        if (!isset($processedUserBids[$auctionId])) {
            $processedUserBids[$auctionId] = $bid;
        } else {
            if (($bid['user_bid_amount'] ?? 0) > ($processedUserBids[$auctionId]['user_bid_amount'] ?? 0)) {
                $processedUserBids[$auctionId] = $bid;
            }
        }
    }
    $userBids = array_values($processedUserBids);

    $userBidsByAuction = [];
    $itemToUserBid = [];
    foreach ($userBids as $bid) {
        $userBidsByAuction[$bid['auction_id']] = $bid;
        $itemToUserBid[$bid['item_id']] = $bid;
    }

    $bidItemIds = array_unique(array_filter(array_column($userBids, 'item_id')));
    if (!empty($bidItemIds)) {
        $placeholders = str_repeat('?,', count($bidItemIds) - 1) . '?';
        $bidItemsStmt = $pdo->prepare("
            SELECT i.*, c.category_name 
            FROM items i 
            INNER JOIN categories c ON i.category_id = c.category_id 
            WHERE i.item_id IN ($placeholders)
            ORDER BY i.item_id DESC
        ");
        $bidItemsStmt->execute($bidItemIds);
        $userBidItems = $bidItemsStmt->fetchAll();

        $bidAuctionStmt = $pdo->prepare("SELECT * FROM auctions WHERE item_id IN ($placeholders)");
        $bidAuctionStmt->execute($bidItemIds);
        $bidAuctions = $bidAuctionStmt->fetchAll();
        foreach ($bidAuctions as $auc) {
            $auctionsByItemBid[$auc['item_id']] = $auc;
        }

        $bidAuctionIds = array_column($bidAuctions, 'auction_id');
        if (!empty($bidAuctionIds)) {
            $placeholders = str_repeat('?,', count($bidAuctionIds) - 1) . '?';
            $bidBidsStmt = $pdo->prepare("
                SELECT auction_id, MAX(amount) as highest_bid, COUNT(*) as bid_count 
                FROM bids 
                WHERE auction_id IN ($placeholders) 
                GROUP BY auction_id
            ");
            $bidBidsStmt->execute($bidAuctionIds);
            $bidBids = $bidBidsStmt->fetchAll();
            foreach ($bidBids as $bid) {
                $bidsByAuctionBid[$bid['auction_id']] = $bid;
            }
        }
    }

    foreach ($userBidItems as $item) {
        $userBid = $itemToUserBid[$item['item_id']] ?? null;
        if (!$userBid)
            continue;
        $auctionId = $userBid['auction_id'];

        $status = getBidStatus($auctionId, $userBidsByAuction, $auctionsByItemBid, $bidsByAuctionBid, $currentDate);
        if (!$status)
            continue;

        $status['item'] = $item;
        if ($status['type'] === 'live') {
            $liveBidItems[] = $status;
        } elseif ($status['type'] === 'upcoming') {
            $upcomingBidItems[] = $status;
        }
    }
    if (!empty($liveBidItems)) {
        usort($liveBidItems, function ($a, $b) {
            return strtotime($a['auction']['end_time'] ?? '') <=> strtotime($b['auction']['end_time'] ?? '');
        });
    }
    if (!empty($upcomingBidItems)) {
        usort($upcomingBidItems, function ($a, $b) {
            return strtotime($a['auction']['start_time'] ?? '') <=> strtotime($b['auction']['start_time'] ?? '');
        });
    }

    $tradeItemStmt = $pdo->prepare("
        SELECT i.*, c.category_name 
        FROM items i 
        INNER JOIN categories c ON i.category_id = c.category_id 
        WHERE i.user_id = ? AND i.listing_type = 'Trade' 
        ORDER BY i.item_id DESC
    ");
    $tradeItemStmt->execute([$userId]);
    $userTradeItems = $tradeItemStmt->fetchAll();

} catch (Throwable $e) {
    error_log("Database error: " . $e->getMessage());
}

function truncateDescription($text, $wordCount = 7) {
    $words = explode(' ', $text);
    if (count($words) > $wordCount) {
        return implode(' ', array_slice($words, 0, $wordCount)) . '...';
    }
    return $text;
}

function getAuctionStatus($itemId, $auctionsByItem, $bidsByAuction, $now)
{
    $auc = $auctionsByItem[$itemId] ?? null;
    if (empty($auc))
        return null;
    $auctionId = $auc['auction_id'] ?? null;
    $bids = $bidsByAuction[$auctionId] ?? ['highest_bid' => 0, 'bid_count' => 0];

    $startTime = $auc['start_time'] ?? '';
    $endTime = $auc['end_time'] ?? '';
    $status = $auc['status'] ?? '';

    if ($startTime <= $now && $now <= $endTime && $status === 'Active') {
        return ['type' => 'live', 'auction' => $auc, 'bids' => $bids];
    }
    if ($startTime > $now) {
        return ['type' => 'upcoming', 'auction' => $auc, 'bids' => $bids];
    }
    return null;
}

function getBidStatus($auctionId, $userBidsByAuction, $auctionsByItem, $bidsByAuction, $now)
{
    $bid = $userBidsByAuction[$auctionId] ?? null;
    if (empty($bid))
        return null;

    $itemId = $bid['item_id'] ?? null;
    $auc = $auctionsByItem[$itemId] ?? null;
    if (empty($auc))
        return null;
    $auctionIdReal = $auc['auction_id'] ?? null;
    $bids = $bidsByAuction[$auctionIdReal] ?? ['highest_bid' => 0, 'bid_count' => 0];

    $startTime = $auc['start_time'] ?? '';
    $endTime = $auc['end_time'] ?? '';
    $status = $auc['status'] ?? '';

    $userBidAmount = $bid['user_bid_amount'] ?? 0;
    $highestBid = $bids['highest_bid'] ?? 0;
    $isWinning = $userBidAmount >= $highestBid;

    if ($startTime <= $now && $now <= $endTime && $status === 'Active') {
        return ['type' => 'live', 'auction' => $auc, 'bid' => $bid, 'bids' => $bids, 'is_winning' => $isWinning];
    }
    if ($startTime > $now) {
        return ['type' => 'upcoming', 'auction' => $auc, 'bid' => $bid, 'bids' => $bids];
    }
    return null;
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
    <title>My Items - BidABid</title>
    <link rel="stylesheet" href="../bidabid-css/style.css" />
    <link rel="stylesheet" href="../bidabid-css/auction-style.css" />
    <link rel="stylesheet" href="../bidabid-css/my-items.css" />
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
            <a href="my-items.php" class="active">My Items</a>
            <a href="post-item.php">Post Item</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>My Items</h1>
            <p>Manage your auctions, trades, and track your bids</p>
        </div>
        <div class="view-toggle">
            <button class="toggle-btn active" data-view="auction">Auctions & Bids</button>
            <button class="toggle-btn" data-view="trade">Trades</button>
        </div>

        <div id="auction-content" class="content-section active">
            <section class="section auctions-section">
                <div class="section-header">
                    <h2>My Auctioned Items</h2>
                </div>

                <?php if (!empty($liveAuctionItems)): ?>
                    <div class="sub-section">
                        <h3 class="auctions-row-title">Live Auctions</h3>
                        <div class="card-container" id="auction-live">
                            <?php foreach ($liveAuctionItems as $it):
                                $item = $it['item'] ?? [];
                                $auc = $it['auction'] ?? [];
                                $bids = $it['bids'] ?? ['highest_bid' => 0, 'bid_count' => 0];
                                $imgSrc = getImageSrc($item['image_url'] ?? '');
                                $endDate = date('M d, Y h:i A', strtotime($auc['end_time'] ?? ''));
                                $desc = truncateDescription($item['description'] ?? '', 7);
                            ?>
                                <div class="card" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>" data-end="<?= htmlspecialchars($auc['end_time'] ?? '') ?>" style="cursor:pointer;">
                                    <span class="auction-phase-label live">LIVE</span>
                                    <div class="timer" data-end="<?= htmlspecialchars($auc['end_time'] ?? '') ?>" data-upcoming="0"></div>
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name'] ?? '') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=Image+Error';">
                                    <h4><?= htmlspecialchars($item['item_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                    <div class="item-bid-info">
                                        <div><strong>Current Bid:</strong> ₱<?= number_format((float)($bids['highest_bid'] ?? ($item['starting_price'] ?? 0)), 2) ?></div>
                                        <div><strong>Bids:</strong> <?= (int)($bids['bid_count'] ?? 0) ?></div>
                                        <div><strong>Ends:</strong> <?= $endDate ?></div>
                                    </div>
                                    <span class="view-details-btn"><strong>View Details</strong></span>
                                    <div class="card-actions">
                                        <button class="delete-btn blue-btn" data-auction-id="<?= (int)($auc['auction_id'] ?? 0) ?>" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($upcomingAuctionItems)): ?>
                    <div class="sub-section">
                        <h3 class="auctions-row-title">Upcoming Auctions</h3>
                        <div class="card-container" id="auction-upcoming">
                            <?php foreach ($upcomingAuctionItems as $it):
                                $item = $it['item'] ?? [];
                                $auc = $it['auction'] ?? [];
                                $bids = $it['bids'] ?? ['bid_count' => 0];
                                $imgSrc = getImageSrc($item['image_url'] ?? '');
                                $startDate = date('M d, Y h:i A', strtotime($auc['start_time'] ?? ''));
                                $endDate = date('M d, Y h:i A', strtotime($auc['end_time'] ?? ''));
                                $desc = truncateDescription($item['description'] ?? '', 7);
                            ?>
                                <div class="card" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>" style="cursor:pointer;">
                                    <span class="auction-phase-label upcoming">UPCOMING</span>
                                    <div class="timer" data-end="<?= htmlspecialchars($auc['start_time'] ?? '') ?>" data-upcoming="1"></div>
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name'] ?? '') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=Image+Error';">
                                    <h4><?= htmlspecialchars($item['item_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                    <div class="item-bid-info">
                                        <div><strong>Starting Bid:</strong> ₱<?= number_format((float)($item['starting_price'] ?? 0), 2) ?></div>
                                        <div><strong>Bids:</strong> <?= (int)($bids['bid_count'] ?? 0) ?></div>
                                        <div><strong>Starts:</strong> <?= $startDate ?></div>
                                        <div><strong>Ends:</strong> <?= $endDate ?></div>
                                    </div>
                                    <span class="view-details-btn">View Details</span>
                                    <div class="card-actions">
                                        <button class="delete-btn blue-btn" data-auction-id="<?= (int)($auc['auction_id'] ?? 0) ?>" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($liveAuctionItems) && empty($upcomingAuctionItems)): ?>
                    <div class="empty-subsection">
                        No auctions posted yet. <a href="post-item.php?type=auction">Post your first auction!</a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="section bids-section">
                <div class="section-header">
                    <h2>My Bidded Items</h2>
                </div>

                <?php if (!empty($liveBidItems)): ?>
                    <div class="sub-section">
                        <h3 class="auctions-row-title">Live Bids</h3>
                        <div class="card-container" id="bid-live">
                            <?php foreach ($liveBidItems as $it):
                                $item = $it['item'] ?? [];
                                $bid = $it['bid'] ?? [];
                                $bids = $it['bids'] ?? ['highest_bid' => 0, 'bid_count' => 0];
                                $auc = $it['auction'] ?? [];
                                $imgSrc = getImageSrc($item['image_url'] ?? '');
                                $winningClass = ($it['is_winning'] ?? false) ? 'winning-bid' : '';
                                $isWinning = $it['is_winning'] ?? false;
                                $endDate = date('M d, Y h:i A', strtotime($auc['end_time'] ?? ''));
                                $desc = truncateDescription($item['description'] ?? '', 7);
                            ?>
                                <div class="card" data-item-id="<?= (int) ($item['item_id'] ?? 0) ?>" data-end="<?= htmlspecialchars($auc['end_time'] ?? '') ?>" style="cursor:pointer;">
                                    <span class="auction-phase-label live">LIVE</span>
                                    <div class="timer" data-end="<?= htmlspecialchars($auc['end_time'] ?? '') ?>" data-upcoming="0"></div>
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name'] ?? '') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=Image+Error';">
                                    <h4><?= htmlspecialchars($item['item_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                    <div class="item-bid-info">
                                        <div><strong>Your Bid:</strong> ₱<?= number_format((float) ($bid['user_bid_amount'] ?? 0), 2) ?> <span class="<?= $winningClass ?>"> (<?= $isWinning ? 'Winning!' : 'Outbid' ?>)</span></div>
                                        <div><strong>Current High:</strong> ₱<?= number_format((float) ($bids['highest_bid'] ?? 0), 2) ?></div>
                                        <div><strong>Total Bids:</strong> <?= (int) ($bids['bid_count'] ?? 0) ?></div>
                                        <div><strong>Ends:</strong> <?= $endDate ?></div>
                                    </div>
                                    <span class="view-details-btn">View Details</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($upcomingBidItems)): ?>
                    <div class="sub-section">
                        <h3 class="auctions-row-title">Upcoming Bids</h3>
                        <div class="card-container" id="bid-upcoming">
                            <?php foreach ($upcomingBidItems as $it):
                                $item = $it['item'] ?? [];
                                $bid = $it['bid'] ?? [];
                                $bids = $it['bids'] ?? ['bid_count' => 0];
                                $auc = $it['auction'] ?? [];
                                $imgSrc = getImageSrc($item['image_url'] ?? '');
                                $startDate = date('M d, Y h:i A', strtotime($auc['start_time'] ?? ''));
                                $endDate = date('M d, Y h:i A', strtotime($auc['end_time'] ?? ''));
                                $desc = truncateDescription($item['description'] ?? '', 7);
                            ?>
                                <div class="card" data-item-id="<?= (int) ($item['item_id'] ?? 0) ?>" style="cursor:pointer;">
                                    <span class="auction-phase-label upcoming">UPCOMING</span>
                                    <div class="timer" data-end="<?= htmlspecialchars($auc['start_time'] ?? '') ?>" data-upcoming="1"></div>
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name'] ?? '') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=Image+Error';">
                                    <h4><?= htmlspecialchars($item['item_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                    <div class="item-bid-info">
                                        <div><strong>Your Bid:</strong> ₱<?= number_format((float) ($bid['user_bid_amount'] ?? 0), 2) ?></div>
                                        <div><strong>Starting Bid:</strong> ₱<?= number_format((float) ($item['starting_price'] ?? 0), 2) ?></div>
                                        <div><strong>Total Bids:</strong> <?= (int) ($bids['bid_count'] ?? 0) ?></div>
                                        <div><strong>Starts:</strong> <?= $startDate ?></div>
                                        <div><strong>Ends:</strong> <?= $endDate ?></div>
                                    </div>
                                    <span class="view-details-btn">View Details</span>
                                    <div class="card-actions">
                                        <button class="delete-btn blue-btn" data-auction-id="<?= (int)($auc['auction_id'] ?? 0) ?>" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($liveBidItems) && empty($upcomingBidItems)): ?>
                    <div class="empty-subsection">
                        No bids placed yet. <a href="index.php">Browse auctions and start bidding!</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div id="trade-content" class="content-section">
            <section class="section trades-section">
                <div class="section-header">
                    <h2>My Trades</h2>
                </div>

                <?php if (!empty($userTradeItems)): ?>
                    <div class="sub-section">
                        <h3 class="auctions-row-title">Active Trade Listings</h3>
                        <div class="card-container" id="trade-listings">
                            <?php foreach ($userTradeItems as $item):
                                $imgSrc = getImageSrc($item['image_url'] ?? '');
                                $desc = truncateDescription($item['description'] ?? '', 7);
                            ?>
                                <div class="card trade-card" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>" style="cursor:pointer;">
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name'] ?? '') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=Image+Error';">
                                    <h4><?= htmlspecialchars($item['item_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                    <div class="item-info">
                                        <div><strong>Category:</strong> <?= htmlspecialchars($item['category_name'] ?? '') ?></div>
                                    </div>
                                    <span class="view-details-btn"><strong>View Proposed Trades</strong></span>
                                    <div class="card-actions">
                                        <button class="delete-btn blue-btn" data-item-id="<?= (int)($item['item_id'] ?? 0) ?>" data-listing-type="trade">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($userTradeItems)): ?>
                    <div class="empty-subsection">
                        No trade items posted yet. <a href="post-item.php?type=trade">Post your first trade item!</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2025 BidABid. All rights reserved.</p>
    </footer>
    <script src="../bidabid-js/my-items.js"></script>

</body>

</html>
