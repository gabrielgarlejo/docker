<?php
// Simple registration handler matching `users` table
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
    $contact = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    if ($username === '' || $email === '' || $password === '' || $confirm === '' || $contact === '' || $address === '') {
        $error = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $dbName = 'bidabid';
        $dbUser = 'root';
        $dbPass = '';

        try {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('pdo_mysql extension is not enabled.');
            }

            $candidates = [
                ['host' => '127.0.0.1', 'port' => '3306'],
                ['host' => 'localhost', 'port' => '3306'],
                ['host' => '127.0.0.1', 'port' => '3307'],
                ['host' => 'localhost', 'port' => '3307'],
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
                } catch (Throwable $ignored) {}
            }

            if ($pdo === null) {
                throw new RuntimeException('Unable to connect to the database.');
            }

            // Check duplicates
            $dup = $pdo->prepare('SELECT 1 FROM users WHERE email = :email OR username = :username LIMIT 1');
            $dup->execute([':email' => $email, ':username' => $username]);
            if ($dup->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, balance, contact_number, address) VALUES (:username, :email, :password_hash, :balance, :contact_number, :address)');
                $ins->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password_hash' => $passwordHash,
                    ':balance' => 0.00,
                    ':contact_number' => $contact,
                    ':address' => $address,
                ]);
                $success = 'Registration successful. You can now log in.';
                // Clear values except email to help login next
                $username = '';
                $contact = '';
                $address = '';
            }
        } catch (Throwable $t) {
            $error = 'Registration failed. Please try again later.';
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
    <title>BidABid Registration</title>
    <link rel="stylesheet" href="../bidabid-css/registration.css">
</head>
<body>
    <div class="container">
        <div class="info-section">
            <h1 class="title">Account Creation</h1>
            <p>
                Create your BidABid account to start exploring, bidding, and connecting with sellers and buyers. Join our 
                growing community and experience smarter online trading today!
            </p>
        </div>

        <div class="form-section">
            <h1 class="title">REGISTRATION</h1>
            <?php if ($error !== ''): ?>
                <div style="color:#b00020;margin-bottom:12px;font-weight:bold;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($success !== ''): ?>
                <div style="color:#0a7a0a;margin-bottom:12px;font-weight:bold;"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="input-row">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : '' ?>" required>
                    </div>
                    <div class="input-group">
                        <label>G-mail / E-mail</label>
                        <input type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>" required>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" value="<?= isset($contact) ? htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') : '' ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : '' ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn">REGISTER</button>
                <p class="login-link">Already have an account? <a href="login.php">Log In</a></p>
            </form>
        </div>
    </div>
</body>
</html>
