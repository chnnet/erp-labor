<?php
include("header.php");
require_once 'classes/Database.php';
require_once 'classes/Language.php';
Database::initialize();

$meldung = '';
$fehler = '';
$neueNr = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bez = trim($_POST['bezeichnung']);
    $adresse = trim($_POST['adresse']);
    $email = trim($_POST['email']);

    if (empty($bez) || empty($adresse) || empty($email)) {
        $fehler = Language::get('fill_all_fields');
    } else {
        $check = Database::$conn->prepare("SELECT COUNT(*) FROM kreditoren WHERE bezeichnung = ? AND adresse = ?");
        $check->execute([$bez, $adresse]);

        if ($check->fetchColumn() > 0) {
            $fehler = Language::get('creditor_exists');
        } else {
            $stmt = Database::$conn->query("SELECT MAX(kreditorennr) FROM kreditoren WHERE kreditorennr BETWEEN 300000 AND 399999");
            $max = (int)$stmt->fetchColumn();
            $neueNr = $max > 0 ? $max + 1 : 300000;

            $insert = Database::$conn->prepare("INSERT INTO kreditoren (kreditorennr, bezeichnung, adresse, email) VALUES (?, ?, ?, ?)");
            if ($insert->execute([$neueNr, $bez, $adresse, $email])) {
                $meldung = Language::get('creditor_created_successfully') . " <strong>$neueNr</strong>.";
            } else {
                $fehler = Language::get('insert_error');
                $neueNr = null;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('create_creditor') ?></title>
  <style>
    label { display: block; margin-top: 10px; }
    input { width: 100%; padding: 6px; }
    .fehler { color: red; }
    .ok { color: green; }
    form { max-width: 400px; margin: auto; }
  </style>
</head>
<body>
  <h2><?= Language::get('create_creditor') ?></h2>

  <?php if ($meldung): ?><p class="ok"><?= $meldung ?></p><?php endif; ?>
  <?php if ($fehler): ?><p class="fehler"><?= $fehler ?></p><?php endif; ?>

  <form method="post">
    <label><?= Language::get('creditor_number') ?>:</label>
    <input type="text" value="<?= $neueNr ?? '' ?>" readonly placeholder="<?= Language::get('auto_assigned') ?>">

    <label><?= Language::get('name') ?>:
      <input type="text" name="bezeichnung" required>
    </label>

    <label><?= Language::get('address') ?>:
      <input type="text" name="adresse" required>
    </label>

    <label><?= Language::get('email') ?>:
      <input type="email" name="email" required>
    </label>

    <button type="submit"><?= Language::get('create') ?></button>
  </form>
</body>
</html>
