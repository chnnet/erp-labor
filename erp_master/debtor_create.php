<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
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
        $check = Database::$conn->prepare("SELECT COUNT(*) FROM debitoren WHERE bezeichnung = ? AND adresse = ?");
        $check->execute([$bez, $adresse]);

        if ($check->fetchColumn() > 0) {
            $fehler = Language::get('debtor_exists');
        } else {
            $stmt = Database::$conn->query("SELECT MAX(debitorennr) FROM debitoren WHERE debitorennr BETWEEN 400000 AND 499999");
            $max = (int)$stmt->fetchColumn();
            $neueNr = $max > 0 ? $max + 1 : 400000;

            $insert = Database::$conn->prepare("INSERT INTO debitoren (debitorennr, bezeichnung, adresse, email) VALUES (?, ?, ?, ?)");
            if ($insert->execute([$neueNr, $bez, $adresse, $email])) {
                $kontoInsert = Database::$conn->prepare("
                INSERT INTO kontenstamm (kontonr, bezeichnung, typ, klasse, sammelkonto, ktorahmen_id, open_amount)
                VALUES (?, ?, 'D', 1, 0, 1, 0.00)
                ");
                $kontoInsert->execute([$neueNr, $bez]);
                $meldung = Language::get('debtor_created_successfully', ['id' => $neueNr]);
            } else {
                $fehler = Language::get('insert_error');
                $neueNr = null;
            }
        }
    }
}
?>
<style>
    label { display: block; margin-top: 10px; }
    input { width: 100%; padding: 6px; }
    .fehler { color: red; }
    .ok { color: green; }
    form { max-width: 800px; margin: auto; }
    .button{  cursor: pointer; }
    h1 {
      text-align: center;
      font-size: 2em;
    }
</style>    

<h1><?= Language::get('create_debtor') ?></h1>
<?php if ($meldung): ?><p style="color:green"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
<?php if ($fehler): ?><p style="color:red"><?= htmlspecialchars($fehler) ?></p><?php endif; ?>

<form method="post">
  <label><?= Language::get('debtor_number') ?>:</label>
  <input type="text" value="<?= $neueNr ?? '' ?>" readonly placeholder="<?= Language::get('auto_assigned') ?>"><br>

  <label><?= Language::get('name') ?>: <input type="text" name="bezeichnung" required></label><br>
  <label><?= Language::get('address') ?>: <input type="text" name="adresse" required></label><br>
  <label><?= Language::get('email') ?>: <input type="email" name="email" required></label><br>
  <button type="submit"><?= Language::get('create') ?></button>
</form>
