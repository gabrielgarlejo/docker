<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$loggedInUserId = $_SESSION['user_id'] ?? null;
$userName = null;

$dbName = 'bidabid';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    if ($loggedInUserId) {
        $userStmt = $pdo->prepare("SELECT username FROM users WHERE user_id = :user_id LIMIT 1");
        $userStmt->execute([':user_id' => $loggedInUserId]);
        $userResult = $userStmt->fetch();
        if ($userResult) {
            $userName = $userResult['username'];
        }
    }

    $categoriesStmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $allCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT item_id, item_name, description, category_id, image_url, user_id FROM items WHERE listing_type = 'trade'";
    if ($loggedInUserId) {
        $sql .= " AND user_id != :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $loggedInUserId]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsByCategory = [];
    foreach ($allItems as $item) {
        $itemsByCategory[$item['category_id']][] = $item;
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Trade Items - BidABid</title>
    <link rel="stylesheet" href="../bidabid-css/style.css" />
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
            <a href="trade-items.php" class="active">Trade</a>
            <?php if ($loggedInUserId): ?>
                <a href="my-items.php">My Items</a>
                <a href="post-item.php">Post Item</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="registration.php">Register</a>
            <?php endif; ?>
        </nav>
        <div class="search-container">
            <input type="text" placeholder="Search items..." id="trade-search-bar" />
            <button id="trade-search-btn">Search</button>
        </div>
    </header>

    <section class="categories">
        <button class="category active" data-cat="All">All</button>
        <?php foreach ($allCategories as $category): ?>
            <button class="category"
                data-cat="<?= htmlspecialchars($category['category_name']) ?>"><?= htmlspecialchars($category['category_name']) ?></button>
        <?php endforeach; ?>
    </section>

    <?php foreach ($allCategories as $category):
        $categoryId = $category['category_id'];
        $categoryName = $category['category_name'];
        $items_in_category = $itemsByCategory[$categoryId] ?? [];
        ?>
        <section class="section" data-cat-section="<?= htmlspecialchars($categoryName) ?>">
            <div class="section-header">
                <h2><?= htmlspecialchars($categoryName) ?></h2>
                <div class="arrows">
                    <button class="arrow left" data-target="trade-list-<?= $categoryId ?>">&lt;</button>
                    <button class="arrow right" data-target="trade-list-<?= $categoryId ?>">&gt;</button>
                </div>
            </div>
            <div class="card-container" id="trade-list-<?= $categoryId ?>">
                <?php if (empty($items_in_category)): ?>
                    <p>No items available for trading in this category.</p>
                <?php else: ?>
                    <?php foreach ($items_in_category as $item): ?>
                        <div class="card" data-cat="<?= htmlspecialchars($categoryName) ?>" data-item-id="<?= (int) $item['item_id'] ?>"
                            style="cursor:pointer;" onclick="window.location.href='trade-details.php?id=<?= $item['item_id'] ?>'">
                            <?php
                            $imgSrc = (!empty($item['image_url'])) ? 'data:image/jpeg;base64,' . base64_encode($item['image_url']) : 'https://via.placeholder.com/200x150';
                            ?>
                            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['item_name']) ?>"
                                style="width:100%;height:150px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;display:block;" />
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                            <p><?= htmlspecialchars(substr($item['description'], 0, 100)) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <footer class="footer">
        <p>&copy; 2025 BidABid. All rights reserved.</p>
    </footer>
    <script src="../bidabid-js/traded-items.js"></script>
</body>

</html>
