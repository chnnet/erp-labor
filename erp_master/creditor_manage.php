<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
Database::initialize();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$meldung = '';
$fehler = '';

// ✅ Aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $nr = (int)$_POST['kreditorennr'];
    $bez = trim($_POST['bezeichnung']);
    $adresse = trim($_POST['adresse']);
    $email = trim($_POST['email']);

    $stmt = Database::$conn->prepare("UPDATE kreditoren SET bezeichnung = ?, adresse = ?, email = ? WHERE kreditorennr = ?");
    $stmt->execute([$bez, $adresse, $email, $nr]);
    $meldung = Language::get('creditor_updated', ['id' => $nr]);
}

// ❌ Löschen
if (isset($_GET['delete'])) {
    $nr = (int)$_GET['delete'];
    $check = Database::$conn->prepare("SELECT COUNT(*) FROM journalzeile WHERE kontosoll = ? OR kontohaben = ?");
    $check->execute([$nr, $nr]);

    if ($check->fetchColumn() > 0) {
        $fehler = Language::get('creditor_delete_denied', ['id' => $nr]);
    } else {
        $del = Database::$conn->prepare("DELETE FROM kreditoren WHERE kreditorennr = ?");
        $del->execute([$nr]);
        $meldung = Language::get('creditor_deleted', ['id' => $nr]);
    }
}

// Kreditoren laden
$kreditoren = Database::$conn->query("SELECT * FROM kreditoren ORDER BY kreditorennr")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('creditor_overview_title') ?></title>
</head>
<body>
  <h1><?= Language::get('creditor_overview_title') ?></h1>

  <?php if ($meldung): ?>
    <p style="color:green"><?= htmlspecialchars($meldung) ?></p>
  <?php endif; ?>

  <?php if ($fehler): ?>
    <p style="color:red"><?= htmlspecialchars($fehler) ?></p>
  <?php endif; ?>

  <table border="1" cellpadding="5">
    <tr>
      <th><?= Language::get('creditor_number') ?></th>
      <th><?= Language::get('name') ?></th>
      <th><?= Language::get('address') ?></th>
      <th><?= Language::get('email') ?></th>
      <th colspan="2"><?= Language::get('actions') ?></th>
    </tr>
    <?php foreach ($kreditoren as $k): ?>
      <tr>
        <form method="post">
          <td>
            <input type="hidden" name="kreditorennr" value="<?= $k['kreditorennr'] ?>">
            <?= $k['kreditorennr'] ?>
          </td>
          <td><input type="text" name="bezeichnung" value="<?= htmlspecialchars($k['bezeichnung']) ?>"></td>
          <td><input type="text" name="adresse" value="<?= htmlspecialchars($k['adresse'] ?? '') ?>"></td>
          <td><input type="email" name="email" value="<?= htmlspecialchars($k['email'] ?? '') ?>"></td>
          <td>
            <button type="submit" name="update">💾 <?= Language::get('save') ?></button>
          </td>
          <td>
            <a href="creditor_details.php?kreditorennr=<?= $k['kreditorennr'] ?>">🔍 <?= Language::get('details') ?></a> |
            <a href="?delete=<?= $k['kreditorennr'] ?>" onclick="return confirm('<?= Language::get('confirm_delete_creditor') ?>')">🗑️ <?= Language::get('delete') ?></a>
          </td>
        </form>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
