<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

  if ($email === '' || $password === '') {
    $error = 'Please enter email and password.';
  } else {
    $dbName = 'bidabid';
    $dbUser = 'root';
    $dbPass = '';

    try {
      if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('pdo_mysql extension is not enabled. Enable it in WAMP PHP extensions.');
      }

      $candidates = [
        ['host' => '127.0.0.1', 'port' => '3306'],
        ['host' => 'localhost', 'port' => '3306'],
        ['host' => '127.0.0.1', 'port' => '3307'],
        ['host' => 'localhost', 'port' => '3307'],
      ];

      $pdo = null;
      $lastError = '';
      foreach ($candidates as $c) {
        try {
          $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$dbName};charset=utf8mb4";
          $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          ]);
          break; 
        } catch (Throwable $connErr) {
          $lastError = $connErr->getMessage();
        }
      }

      if ($pdo === null) {
        throw new RuntimeException('Could not connect to database. ' . $lastError);
      }

      $stmt = $pdo->prepare('SELECT user_id, email, password_hash FROM users WHERE email = :email LIMIT 1');
      $stmt->execute([':email' => $email]);
      $user = $stmt->fetch();

      $isValid = false;
      if ($user) {
        $stored = (string) $user['password_hash'];
        if (strpos($stored, '$2') === 0 || strpos($stored, '$argon2') === 0) { 
          $isValid = password_verify($password, $stored);
        } else {
          $isValid = hash_equals($stored, $password);
        }
      }

      if ($isValid) {
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: index.php');
        exit;
      } else {
        $error = 'Invalid email or password.';
      }
    } catch (Throwable $t) {
      $error = 'Unable to connect. Please try again later.';
      if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        $error .= ' ' . htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8');
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BidABid Login</title>
  <link rel="stylesheet" href="../bidabid-css/login.css">
</head>

<body>
  <div class="container">
    <div class="left-side">
      <h1 class="logo">
        <span class="red">Bid</span><span class="yellow">A</span><span class="blue">Bid</span>
      </h1>
    </div>

    <div class="right-side">
      <div class="login-box">
        <h1 class="logo-text">
          <span class="red">Bid</span><span class="yellow">A</span><span class="blue">Bid</span>
        </h1>
        <?php if ($error !== ''): ?>
          <div style="color:#b00020;margin-bottom:12px;font-weight:bold;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <div class="input-group">
            <span class="icon">👤</span>
            <input type="email" name="email" placeholder="Email"
              value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>" required>
          </div>
          <div class="input-group">
            <span class="icon">🔑</span>
            <input type="password" name="password" placeholder="Password" required>
          </div>

          <div class="options">
            <label><input type="checkbox"> Remember me</label>
            <a href="#">Forgot Password</a>
          </div>

          <button type="submit" class="login-btn">LOGIN</button>
        </form>

        <p class="register-link">
          Don’t have an account?
          <a href="registration.php">Register</a>
        </p>
      </div>
    </div>
  </div>
</body>

</html>