<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$error = '';
$success = '';

function db_connect()
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

$categories = [];
$pdo = null;
try {
  $pdo = db_connect();
  $catStmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');
  $categories = $catStmt->fetchAll();
} catch (Throwable $t) {
  if (empty($categories)) {
    $error = 'Could not load categories.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $itemName = trim($_POST['item_name'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $condition = trim($_POST['item_condition'] ?? '');
  $listingType = trim($_POST['listing_type'] ?? 'Trade');
  $categoryName = trim($_POST['category_name'] ?? '');
  $startingPrice = isset($_POST['starting_price']) ? (float) $_POST['starting_price'] : 0.0;


  $auctionStart = $_POST['start_time'] ?? '';
  $auctionEnd = $_POST['end_time'] ?? '';
  $startingBid = isset($_POST['starting_bid']) ? (float) $_POST['starting_bid'] : 0.0;

  if ($itemName === '' || $description === '' || $condition === '' || $categoryName === '') {
    $error = 'Please fill all required fields.';
  } else {
    try {

      if (!$pdo) {
        $pdo = db_connect();
      }

      $catStmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = :name LIMIT 1');
      $catStmt->execute([':name' => $categoryName]);
      $catRow = $catStmt->fetch();
      if (!$catRow) {
        $error = 'Invalid category selected.';
      } else {
        $categoryId = (int) $catRow['category_id'];

        if (strcasecmp($listingType, 'Auction') === 0) {
          $startingPrice = $startingBid > 0 ? $startingBid : $startingPrice;
        }

        $imageBlob = null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
          $imageBlob = file_get_contents($_FILES['image']['tmp_name']);
        }


        $stmt = $pdo->prepare('INSERT INTO items (item_name, description, item_condition, image_url, listing_type, user_id, category_id, starting_price)
                               VALUES (:item_name, :description, :item_condition, :image_url, :listing_type, :user_id, :category_id, :starting_price)');
        $stmt->bindValue(':item_name', $itemName);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':item_condition', $condition);
        $stmt->bindValue(':image_url', $imageBlob, $imageBlob === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $stmt->bindValue(':listing_type', $listingType);
        $stmt->bindValue(':user_id', (int) $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':starting_price', $startingPrice);
        $stmt->execute();
        $itemId = (int) $pdo->lastInsertId();


        if (strcasecmp($listingType, 'Auction') === 0) {
          if ($auctionStart === '') {
            $auctionStart = date('Y-m-d H:i:s');
          }
          if ($auctionEnd === '') {
            $auctionEnd = date('Y-m-d H:i:s', time() + 3 * 24 * 60 * 60);
          }
          $a = $pdo->prepare('INSERT INTO auctions (item_id, start_time, end_time, status)
                              VALUES (:item_id, :start_time, :end_time, :status)');
          $a->execute([
            ':item_id' => $itemId,
            ':start_time' => $auctionStart,
            ':end_time' => $auctionEnd,
            ':status' => 'Active',
          ]);
        }

        header('Location: item-details.php?id=' . $itemId);
        exit;
      }
    } catch (Throwable $t) {
      $error = 'Could not save item. Error: ' . htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8');
      error_log('Post item error: ' . $t->getMessage() . ' | Trace: ' . $t->getTraceAsString());
      
      if (empty($categories) && $pdo) {
        try {
          $catStmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');
          $categories = $catStmt->fetchAll();
        } catch (Throwable $ignored) {
        }
      }
    }
  }
}


if (empty($categories)) {
  try {
    if (!$pdo) {
      $pdo = db_connect();
    }
    $catStmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');
    $categories = $catStmt->fetchAll();
  } catch (Throwable $ignored) {
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Post Item</title>
  <link rel="stylesheet" href="../bidabid-css/style.css" />
  <link rel="stylesheet" href="../bidabid-css/post-item.css" />
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

  <main class="post-item">
    <h2>Post New Item</h2>
    <?php if ($error !== ''): ?>
      <div class="error-banner"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="post-form">
      <div class="row">
        <div class="col">
          <label>Item Name</label>
          <input type="text" name="item_name" required />
        </div>
        <div class="col">
          <label>Condition</label>
          <select name="item_condition" required>
            <option value="New">New</option>
            <option value="Like New">Like New</option>
            <option value="Good">Good</option>
            <option value="Fair">Fair</option>
            <option value="Poor">Poor</option>
          </select>
        </div>
        <div class="col">
          <label>Listing Type</label>
          <select name="listing_type" required>
            <option value="Auction">Auction</option>
            <option value="Trade">Trade</option>
          </select>
        </div>
        <div class="col">
          <label>Category</label>
          <select name="category_name" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label>Starting Price (for display)</label>
          <input type="number" step="0.01" name="starting_price" value="0" />
        </div>
        <div class="col" style="flex-basis:100%">
          <label>Description</label>
          <textarea name="description" rows="4" required></textarea>
        </div>
        <div class="col">
          <label>Image</label>
          <input type="file" name="image" accept="image/*" />
        </div>
      </div>

      <fieldset>
        <legend>Auction Details (only if Listing Type = Auction)</legend>
        <div class="row">
          <div class="col">
            <label>Start Time</label>
            <input type="datetime-local" name="start_time" />
          </div>
          <div class="col">
            <label>End Time</label>
            <input type="datetime-local" name="end_time" />
          </div>
          <div class="col">
            <label>Starting Bid</label>
            <input type="number" step="0.01" name="starting_bid" value="0" />
          </div>
        </div>
      </fieldset>

      <div class="actions">
        <button type="submit" class="btn">Post Item</button>
        <a href="index.php" class="btn">Cancel</a>
      </div>
    </form>
  </main>
</body>

</html>