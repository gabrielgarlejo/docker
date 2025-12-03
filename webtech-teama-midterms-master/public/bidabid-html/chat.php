<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

header('Content-Type: application/json');

function db_connect() {
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
    } catch (Throwable $ignored) {}
  }
  throw new RuntimeException('DB connection failed');
}

try {
  $pdo = db_connect();
  $pdo->exec('CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(item_id), INDEX(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo json_encode(['ok' => false, 'error' => 'unauthorized']);
      exit;
    }
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    if ($itemId <= 0 || $message === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'bad_request']);
      exit;
    }
    if (mb_strlen($message) > 1000) {
      $message = mb_substr($message, 0, 1000);
    }
    $stmt = $pdo->prepare('INSERT INTO chat_messages (item_id, user_id, message) VALUES (:item_id, :user_id, :message)');
    $stmt->execute([
      ':item_id' => $itemId,
      ':user_id' => (int)$_SESSION['user_id'],
      ':message' => $message,
    ]);
    echo json_encode(['ok' => true]);
    exit;
  }

  $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
  $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
  if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
  }
  $stmt = $pdo->prepare('SELECT m.id, m.message, m.created_at, u.username, u.email
                          FROM chat_messages m
                          JOIN users u ON u.user_id = m.user_id
                          WHERE m.item_id = :item_id AND m.id > :last_id
                          ORDER BY m.id ASC
                          LIMIT 100');
  $stmt->execute([':item_id' => $itemId, ':last_id' => $lastId]);
  $rows = $stmt->fetchAll();
  echo json_encode(['ok' => true, 'messages' => $rows]);
} catch (Throwable $t) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error']);
}
?>

