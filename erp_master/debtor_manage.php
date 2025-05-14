<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
Database::initialize();

$meldung = '';
$fehler = '';

// Aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $nr = (int)$_POST['debitorennr'];
    $bez = trim($_POST['bezeichnung']);
    $adresse = trim($_POST['adresse']);
    $email = trim($_POST['email']);

    $stmt = Database::$conn->prepare("UPDATE debitoren SET bezeichnung = ?, adresse = ?, email = ? WHERE debitorennr = ?");
    $stmt->execute([$bez, $adresse, $email, $nr]);
    $meldung = Language::get('debtor_updated', ['id' => $nr]);
}

// Löschen
if (isset($_GET['delete'])) {
    $nr = (int)$_GET['delete'];
    $check = Database::$conn->prepare("SELECT COUNT(*) FROM journalzeile WHERE kontosoll = ? OR kontohaben = ?");
    $check->execute([$nr, $nr]);

    if ($check->fetchColumn() > 0) {
        $fehler = Language::get('debtor_delete_failed', ['id' => $nr]);
    } else {
        $del = Database::$conn->prepare("DELETE FROM debitoren WHERE debitorennr = ?");
        $del->execute([$nr]);
        $meldung = Language::get('debtor_deleted', ['id' => $nr]);
    }
}

// Debitoren laden
$debitoren = Database::$conn->query("SELECT * FROM debitoren ORDER BY debitorennr")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('debtor_list') ?></title>
</head>
<body>
<h1><?= Language::get('debtor_list') ?></h1>
<?php if ($meldung): ?><p style="color:green"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
<?php if ($fehler): ?><p style="color:red"><?= htmlspecialchars($fehler) ?></p><?php endif; ?>

<table border="1" cellpadding="5">
  <tr>
    <th><?= Language::get('debtor_number') ?></th>
    <th><?= Language::get('name') ?></th>
    <th><?= Language::get('address') ?></th>
    <th><?= Language::get('email') ?></th>
    <th><?= Language::get('open_amount') ?></th>
    <th colspan="2"><?= Language::get('actions') ?></th>
  </tr>
  <?php foreach ($debitoren as $d): ?>
  <tr>
    <form method="post">
      <td>
        <input type="hidden" name="debitorennr" value="<?= $d['debitorennr'] ?>">
        <?= $d['debitorennr'] ?>
      </td>
      <td><input type="text" name="bezeichnung" value="<?= htmlspecialchars($d['bezeichnung']) ?>"></td>
      <td><input type="text" name="adresse" value="<?= htmlspecialchars($d['adresse'] ?? '') ?>"></td>
      <td><input type="email" name="email" value="<?= htmlspecialchars($d['email'] ?? '') ?>"></td>
      <td><?= number_format($d['offener_betrag'] ?? 0, 2) ?> €</td>
      <td>
        <button type="submit" name="update">💾 <?= Language::get('save') ?></button>
      </td>
      <td>|
        <a href="?delete=<?= $d['debitorennr'] ?>" onclick="return confirm('<?= Language::get('delete_confirm') ?>')">🗑️ <?= Language::get('delete') ?></a>
      </td>
    </form>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
