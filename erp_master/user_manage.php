<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';

session_start();
Database::initialize();
$pdo = Database::$conn;

$meldung = '';
$fehler = '';

// 🔒 Benutzer löschen
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId === $_SESSION['user_id']) {
        $fehler = Language::get('cannot_delete_self');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$deleteId])) {
            $meldung = Language::get('user_deleted_success');
        } else {
            $fehler = Language::get('user_delete_error');
        }
    }
}

// ✏️ Benutzer bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $userId = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $passwort = $_POST['passwort'];

    if (empty($username)) {
        $fehler = Language::get('username_required');
    } else {
        $updateSql = "UPDATE users SET username = ?" . (!empty($passwort) ? ", password_hash = ?" : "") . " WHERE id = ?";
        $params = [$username];
        if (!empty($passwort)) {
            $params[] = password_hash($passwort, PASSWORD_DEFAULT);
        }
        $params[] = $userId;

        $stmt = $pdo->prepare($updateSql);
        if ($stmt->execute($params)) {
            if ($userId === $_SESSION['user_id']) {
                $_SESSION['username'] = $username;
            }
            $meldung = Language::get('user_updated_success');
        } else {
            $fehler = Language::get('user_update_error');
        }
    }
}

// ➕ Neuer Benutzer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $newUser = trim($_POST['new_username']);
    $newPass = $_POST['new_passwort'];

    if (empty($newUser) || empty($newPass)) {
        $fehler = Language::get('username_and_password_required');
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $ok = $stmt->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT)]);
        $meldung = $ok ? Language::get('user_created_success') : Language::get('user_create_error');
    }
}

// 📋 Benutzerliste laden
$stmt = $pdo->prepare("SELECT id, username FROM users ORDER BY id");
$stmt->execute();
$benutzer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('user_management') ?></title>
  <style>
    table { width: 100%; border-collapse: collapse; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    .fehler { color: #a00; font-weight: bold; background: #fee; padding: 10px; border: 1px solid #a00; }
    .ok { color: #060; font-weight: bold; background: #e6ffe6; padding: 10px; border: 1px solid #060; }
    input[type="text"], input[type="password"] { padding: 5px; width: 160px; }
    form.inline-buttons { display: inline; }
    button, .action-link { padding: 4px 8px; font-size: 0.9em; margin-right: 6px; }
    .action-link { text-decoration: none; }
  </style>
</head>
<body>
  <h2>👥 <?= Language::get('user_management') ?></h2>

  <?php if ($meldung): ?><div class="ok"><?= htmlspecialchars($meldung) ?></div><?php endif; ?>
  <?php if ($fehler): ?><div class="fehler"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

  <?php if (empty($benutzer)): ?>
    <p><?= Language::get('no_users_found') ?></p>
  <?php else: ?>
    <table>
      <tr>
        <th>ID</th>
        <th><?= Language::get('username') ?></th>
        <th><?= Language::get('new_username') ?></th>
        <th><?= Language::get('new_password') ?></th>
        <th><?= Language::get('actions') ?></th>
      </tr>
      <?php foreach ($benutzer as $user): ?>
        <tr>
          <td><?= $user['id'] ?></td>
          <td><?= htmlspecialchars($user['username']) ?><?= $user['id'] === $_SESSION['user_id'] ? " (" . Language::get('you') . ")" : "" ?></td>
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <td><input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required></td>
            <td><input type="password" name="passwort" placeholder="<?= Language::get('new_password_optional') ?>"></td>
            <td>
              <button type="submit" title="<?= Language::get('save') ?>">💾 <?= Language::get('save') ?></button>
              <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                <a href="?delete=<?= $user['id'] ?>" class="action-link" onclick="return confirm('<?= Language::get('confirm_user_delete') ?>')">🗑️ <?= Language::get('delete') ?></a>
              <?php endif; ?>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
      <!-- Neue Benutzerzeile -->
      <tr>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <td>➕</td>
          <td colspan="1"><?= Language::get('new_user') ?></td>
          <td><input type="text" name="new_username" required></td>
          <td><input type="password" name="new_passwort" required></td>
          <td><button type="submit">➕ <?= Language::get('add_user') ?></button></td>
        </form>
      </tr>
    </table>
  <?php endif; ?>
</body>
</html>
