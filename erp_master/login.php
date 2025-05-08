<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'classes/Database.php';
require_once 'classes/Language.php';

$error = '';

// Wenn Benutzer bereits eingeloggt ist → direkt zur Startseite
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Verarbeitet Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['loginname'] ?? '';
    $password = $_POST['password'] ?? '';
    $umgebung = $_POST['umgebung'] ?? 3;

    $_SESSION['umgebung'] = $umgebung;

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            unset($_SESSION['last_registered']); // Login erfolgreich, Benutzername nicht mehr anzeigen
            header("Location: dashboard.php");
            exit;
        } else {
            $error = Language::get('invalid_credentials');
        }
    } catch (PDOException $e) {
        $error = Language::get('db_connection_error') . ': ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('login_title') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: sans-serif;
      background: #f4f4f4;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-container {
      background: #fff;
      padding: 2em;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
    }

    h2 {
      text-align: center;
      margin-bottom: 1em;
    }

    label {
      display: block;
      margin: 0.5em 0 0.2em;
    }

    input, select {
      width: 100%;
      padding: 0.6em;
      margin-bottom: 1em;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    button {
      width: 100%;
      padding: 0.7em;
      background-color: #007bff;
      border: none;
      color: white;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
    }

    button:hover {
      background-color: #0056b3;
    }

    .error {
      color: red;
      text-align: center;
      margin-bottom: 1em;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2><?= Language::get('login_title') ?></h2>

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="loginname"><?= Language::get('username') ?></label>
      <input type="text" name="loginname" id="loginname"
             value="<?= htmlspecialchars($_SESSION['last_registered'] ?? '') ?>" required>

      <label for="password"><?= Language::get('password') ?></label>
      <input type="password" name="password" id="password" required>

      <label for="umgebung"><?= Language::get('environment') ?></label>
      <select name="umgebung" id="umgebung">
        <option value="3"><?= Language::get('test') ?></option>
        <option value="1"><?= Language::get('production') ?></option>
      </select>

      <button type="submit"><?= Language::get('login_button') ?></button>

      <div style="text-align:center; margin-top: 10px;">
        <a href="register.php"><?= Language::get('register_link') ?></a>
      </div>
    </form>
  </div>
</body>
</html>
