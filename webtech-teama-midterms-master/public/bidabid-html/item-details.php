<?php
if (session_status() !== PHP_SESSION_ACTIVE)
  session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
  header('Content-Type: application/json');

  $db = 'bidabid';
  $user = 'root';
  $pass = '';
  $candidates = [
    ['host' => '127.0.0.1', 'port' => '3306'],
    ['host' => 'localhost', 'port' => '3306'],
    ['host' => '127.0.0.1', 'port' => '3307'],
    ['host' => 'localhost', 'port' => '3307'],
  ];
  $pdo = null;
  foreach ($candidates as $h) {
    try {
      $dsn = "mysql:host={$h['host']};port={$h['port']};dbname=$db;charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);
      break;
    } catch (Throwable $e) {
    }
  }

  $itemId = (int) ($_POST['item_id'] ?? 0);
  $message = trim($_POST['message'] ?? '');
  $userId = (int) $_SESSION['user_id'];

  if (!$itemId || !$message) {
    echo json_encode(['ok' => false, 'error' => 'invalid_data']);
    exit;
  }

  try {
    $s = $pdo->prepare('INSERT INTO chat_messages (item_id, user_id, message, created_at) VALUES (:i, :u, :m, NOW())');
    $s->execute([':i' => $itemId, ':u' => $userId, ':m' => $message]);
    echo json_encode(['ok' => true]);
  } catch (Throwable $t) {
    echo json_encode(['ok' => false, 'error' => $t->getMessage()]);
  }
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_chat') {
  header('Content-Type: application/json');

  $db = 'bidabid';
  $user = 'root';
  $pass = '';
  $candidates = [
    ['host' => '127.0.0.1', 'port' => '3306'],
    ['host' => 'localhost', 'port' => '3306'],
    ['host' => '127.0.0.1', 'port' => '3307'],
    ['host' => 'localhost', 'port' => '3307'],
  ];
  $pdo = null;
  foreach ($candidates as $h) {
    try {
      $dsn = "mysql:host={$h['host']};port={$h['port']};dbname=$db;charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);
      break;
    } catch (Throwable $e) {
    }
  }

  $itemId = (int) ($_GET['item_id'] ?? 0);
  if (!$itemId) {
    echo json_encode(['ok' => false, 'messages' => []]);
    exit;
  }

  try {
    $s = $pdo->prepare('SELECT cm.message, u.username FROM chat_messages cm JOIN users u ON u.user_id=cm.user_id WHERE cm.item_id=:i ORDER BY cm.created_at ASC LIMIT 100');
    $s->execute([':i' => $itemId]);
    $messages = $s->fetchAll();
    echo json_encode(['ok' => true, 'messages' => $messages]);
  } catch (Throwable $t) {
    echo json_encode(['ok' => false, 'messages' => []]);
  }
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_feedback') {
  header('Content-Type: application/json');

  $db = 'bidabid';
  $user = 'root';
  $pass = '';
  $candidates = [
    ['host' => '127.0.0.1', 'port' => '3306'],
    ['host' => 'localhost', 'port' => '3306'],
    ['host' => '127.0.0.1', 'port' => '3307'],
    ['host' => 'localhost', 'port' => '3307'],
  ];
  $pdo = null;
  foreach ($candidates as $h) {
    try {
      $dsn = "mysql:host={$h['host']};port={$h['port']};dbname=$db;charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);
      break;
    } catch (Throwable $e) {
    }
  }
  if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connect_failed']);
    exit;
  }

  $uid = (int) ($_GET['user_id'] ?? 0);
  if ($uid <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
  }

  try {
    $s = $pdo->prepare("SELECT f.feedback_id,f.user1_id AS to_user_id,f.user2_id AS from_user_id,f.rating,f.comment,u.username AS from_username
                      FROM feedback f JOIN users u ON u.user_id=f.user2_id
                      WHERE f.user1_id=:u ORDER BY f.feedback_id DESC LIMIT 50");
    $s->execute([':u' => $uid]);
    $rows = $s->fetchAll();

    $a = $pdo->prepare("SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM feedback WHERE user1_id=:u");
    $a->execute([':u' => $uid]);
    $agg = $a->fetch() ?: ['avg_rating' => null, 'total' => 0];

    echo json_encode(['ok' => true, 'feedbacks' => $rows, 'avg' => is_null($agg['avg_rating']) ? null : (float) $agg['avg_rating'], 'count' => (int) $agg['total']]);
  } catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'query_failed']);
  }
  exit;
}

date_default_timezone_set('Asia/Manila');

$sessionUserId = (int) $_SESSION['user_id'];
$id = (int) ($_GET['id'] ?? 0);

$db = 'bidabid';
$user = 'root';
$pass = '';
$candidates = [
  ['host' => '127.0.0.1', 'port' => '3306'],
  ['host' => 'localhost', 'port' => '3306'],
  ['host' => '127.0.0.1', 'port' => '3307'],
  ['host' => 'localhost', 'port' => '3307'],
];
$pdo = null;
foreach ($candidates as $h) {
  try {
    $dsn = "mysql:host={$h['host']};port={$h['port']};dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]);
    break;
  } catch (Throwable $e) {
  }
}
if (!$pdo) {
  die('Database connection failed');
}

$walletBalance = 0.0;
$item = null;
$auction = null;
$bids = [];
$startingPrice = 0.0;
$itemOwnerId = null;
$sellerUsername = null;
$sellerAvg = null;
$sellerCount = 0;

$s = $pdo->prepare('SELECT balance FROM users WHERE user_id=:u LIMIT 1');
$s->execute([':u' => $sessionUserId]);
if ($r = $s->fetch())
  $walletBalance = (float) $r['balance'];

$it = $pdo->prepare('SELECT item_id,item_name,description,item_condition,image_url,listing_type,user_id,category_id,starting_price FROM items WHERE item_id=:i LIMIT 1');
$it->execute([':i' => $id]);
$item = $it->fetch();

$sa = $pdo->prepare('SELECT auction_id,item_id,start_time,end_time,status FROM auctions WHERE item_id=:i ORDER BY auction_id DESC LIMIT 1');
$sa->execute([':i' => $id]);
$auction = $sa->fetch();

if ($item) {
  $startingPrice = (float) $item['starting_price'];
  $itemOwnerId = (int) $item['user_id'];

  $us = $pdo->prepare('SELECT username FROM users WHERE user_id=:u LIMIT 1');
  $us->execute([':u' => $itemOwnerId]);
  if ($ud = $us->fetch())
    $sellerUsername = $ud['username'];

  $rs = $pdo->prepare('SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM feedback WHERE user1_id=:u');
  $rs->execute([':u' => $itemOwnerId]);
  $ar = $rs->fetch();
  if ($ar && (int) $ar['total'] > 0) {
    $sellerAvg = (float) $ar['avg_rating'];
    $sellerCount = (int) $ar['total'];
  }
}

if ($auction) {
  $bidsStmt = $pdo->prepare('
    SELECT b.bid_id,b.auction_id,b.user_id AS bidder_user_id,b.amount,b.bid_time,u.username
    FROM bids b JOIN users u ON u.user_id=b.user_id
    WHERE b.auction_id=:a ORDER BY b.bid_time ASC
  ');
  $bidsStmt->execute([':a' => $auction['auction_id']]);
  $bids = $bidsStmt->fetchAll();
}

$img = 'https://via.placeholder.com/400x300';
if ($item && !empty($item['image_url']))
  $img = 'data:image/jpeg;base64,' . base64_encode($item['image_url']);

$currentBidDisplay = '—';
$highest = 0.0;
if ($auction) {
  $h = $pdo->prepare('SELECT COALESCE(MAX(amount),0) AS max_amount FROM bids WHERE auction_id=:a');
  $h->execute([':a' => $auction['auction_id']]);
  $highest = (float) $h->fetch()['max_amount'];
  $currentBidDisplay = $highest > 0 ? '₱' . number_format($highest, 2) : '₱' . number_format($startingPrice, 2);
}

$minReq = max($startingPrice, $highest);

function stars($avg)
{
  if ($avg === null)
    return '☆☆☆☆☆';
  $f = (int) floor($avg);
  return str_repeat('★', $f) . str_repeat('☆', 5 - $f);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BidABid | Item Details</title>
  <link rel="stylesheet" href="../bidabid-css/style.css" />
  <link rel="stylesheet" href="../bidabid-css/style-item.css" />
  <script>
    window.bidConfig = {
      itemId: <?php echo json_encode((int) $id); ?>,
      minBid: <?php echo json_encode(number_format($minReq + 0.01, 2, '.', '')); ?>
    };
  </script>
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
      <a href="my-items.php">My Items</a>
      <a href="post-item.php">Post Item</a>
      <a href="signout.php">Sign Out</a>
    </nav>
  </header>

  <main class="item-page">
    <section class="item-info">
      <?php if ($item): ?>
        <h2 id="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h2>
        <?php
        $sellerStars = stars($sellerAvg);
        $sellerAvgTxt = is_null($sellerAvg) ? '—' : number_format($sellerAvg, 2);
        ?>
        <p>
          <strong>Auctioneer:</strong>
          <a href="#" id="seller-feedback-link" class="seller-link" data-user-id="<?php echo (int) $item['user_id']; ?>">
            <?php echo htmlspecialchars($sellerUsername ?? 'Seller'); ?>
          </a>
          <span class="seller-rating" title="<?php echo (int) $sellerCount; ?> rating(s)">
            <span class="stars"><?php echo $sellerStars; ?></span>
            <span class="num"><?php echo $sellerAvgTxt; ?></span>
          </span>
        </p>
        <p><strong>Condition:</strong> <?php echo htmlspecialchars($item['item_condition']); ?></p>
        <p><strong>Listing:</strong> <?php echo htmlspecialchars($item['listing_type']); ?></p>
        <?php if ($auction): ?>
          <p><strong>Starting Price:</strong> ₱<?php echo number_format((float) $startingPrice, 2); ?></p>
          <p><strong>Auction:</strong> #<?php echo (int) $auction['auction_id']; ?> |
            <?php echo htmlspecialchars($auction['status']); ?></p>
        <?php endif; ?>
        <p><strong>Description:</strong></p>
        <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
      <?php else: ?>
        <h2>Item not found</h2>
        <p>Details are unavailable.</p>
      <?php endif; ?>

      <?php if ($auction): ?>
        <div class="bids-section">
          <h3>📊 Live Bids</h3>
          <?php if ($startingPrice > 0): ?>
            <div class="bid-item starting-bid">
              <div><span class="bid-username">Starting Price</span></div>
              <span class="bid-amount">₱<?php echo number_format($startingPrice, 2); ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($bids)):
            foreach ($bids as $b): ?>
              <div class="bid-item">
                <div>
                  <span class="bid-username"><?php echo htmlspecialchars($b['username']); ?>
                    <span class="bid-id"> #U<?php echo (int) $b['bidder_user_id']; ?></span>
                  </span>
                  <span class="bid-time"><?php echo htmlspecialchars($b['bid_time']); ?></span>
                </div>
                <span class="bid-amount">₱<?php echo number_format((float) $b['amount'], 2); ?></span>
              </div>
            <?php endforeach; else: ?>
            <p style="color:#777; font-style:italic;">No bids placed yet.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="item-display">
      <div class="item-card">
        <img src="<?php echo $img; ?>" alt="Item Image" />
        <?php if ($auction): ?>
          <div class="bid-section">
            <p>Current Bid: <span id="current-bid"><?php echo $currentBidDisplay; ?></span></p>
            <div class="wallet">Wallet: <strong
                id="wallet-display">₱<?php echo number_format($walletBalance, 2); ?></strong></div>

            <div class="bid-controls">
              <input type="text" id="new-bid" placeholder="Enter bid amount" />
              <button id="place-bid" type="button">Place Bid</button>
            </div>
            <small>Minimum: ₱<?php echo number_format($minReq + 0.01, 2); ?></small>

            <div id="bid-message" style="margin-top:10px;"></div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="chat-section">
      <h3>Live Chat</h3>
      <div id="chat-box" class="chat-box"></div>
      <div class="chat-input">
        <input type="text" id="chat-message" placeholder="Type your message..." />
        <button id="send-message">Send</button>
      </div>
    </section>
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

  <script src="../bidabid-js/item-details.js"></script>
</body>

</html>