<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Journal.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';

Database::initialize();
$pdo = Database::$conn;

error_reporting(E_ALL);
ini_set('display_errors', 'on');

$fehler = '';
$meldung = '';
$buchungenErfolgreich = [];

$debitoren = $pdo->query("SELECT debitorennr, bezeichnung FROM debitoren ORDER BY debitorennr")->fetchAll(PDO::FETCH_ASSOC);
$kontenOptions = KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung");

// Vorbelegung über GET-Parameter
$rechnungsnr = $_GET['rechnungsnr'] ?? null;
$selectedBank = $_GET['bank'] ?? '';

$params = [];
$sql = "
  SELECT id, debitorennr, rechnungsnr, buchungsdatum, faelligkeit, betrag, bezahlt, status, bemerkung
  FROM offene_posten_debitoren
  WHERE status != 'bezahlt'
";

if ($rechnungsnr !== null) {
    $sql .= " AND rechnungsnr = ?";
    $params[] = $rechnungsnr;
}

$sql .= " ORDER BY faelligkeit ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offenePosten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bank accounts
$bankkonten = $pdo->query("SELECT kontonr, bezeichnung FROM bankkonten ORDER BY kontonr")->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zahlung_senden'])) {
    try {
        $konto = (int)$_POST['bankkonto'];
        $zahlungen = $_POST['zahlung'] ?? [];
        $userId = $_SESSION['user_id'] ?? 1;

        if (!$konto || empty($zahlungen)) {
            throw new Exception(Language::get('select_bank_and_amounts'));
        }

        foreach ($zahlungen as $postenId => $eingang) {
            $eingang = (float)str_replace(',', '.', $eingang);
            if ($eingang <= 0) continue;

            $stmt = $pdo->prepare("SELECT * FROM offene_posten_debitoren WHERE id = ?");
            $stmt->execute([$postenId]);
            $posten = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$posten) continue;

            $nochOffen = $posten['betrag'] - $posten['bezahlt'];
            $neuerStand = $posten['bezahlt'] + $eingang;

            $status = 'teilbezahlt';
            if (abs($neuerStand - $posten['betrag']) < 0.01) {
                $status = 'bezahlt';
            }

            // Update open item
            $update = $pdo->prepare("UPDATE offene_posten_debitoren SET bezahlt = ?, status = ? WHERE id = ?");
            $update->execute([$neuerStand, $status, $postenId]);

            // Journal
            $zeilen = [
                [
                    'konto' => $konto,
                    'typ' => 'Soll',
                    'betrag' => $eingang,
                    'ust' => '',
                    'steuer_betrag' => 0
                ],
                [
                    'konto' => $posten['debitorennr'],
                    'typ' => 'Haben',
                    'betrag' => $eingang,
                    'ust' => '',
                    'steuer_betrag' => 0
                ]
            ];

            $journalId = JournalEntry::save(
                $userId,
                'Z',
                '0',
                Language::get('payment_for_invoice', ['invoice' => $posten['rechnungsnr']]),
                date('Y-m-d'),
                date('Y-m-d'),
                $zeilen
            );

            $pdo->prepare("UPDATE debitoren SET offener_betrag = GREATEST(offener_betrag - ?, 0) WHERE debitorennr = ?")
                ->execute([$eingang, $posten['debitorennr']]);

            $buchungenErfolgreich[] = Language::get('posted_payment_entry', [
                'invoice' => $posten['rechnungsnr'],
                'amount' => number_format($eingang, 2),
                'journal' => $journalId
            ]);

            $insert = $pdo->prepare("
                INSERT INTO zahlungseingaenge_debitor (offener_posten_id, debitorennr, betrag, zahlungsdatum, user_id)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $insert->execute([$postenId, $posten['debitorennr'], $eingang, $userId]);
        }

        if (empty($buchungenErfolgreich)) {
            $fehler = Language::get('no_payments_processed');
        } else {
            $meldung = implode("<br>", $buchungenErfolgreich);
        }

    } catch (Exception $e) {
        $fehler = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('debtor_incoming_payments') ?></title>
  <style>
    table { width: 100%; border-collapse: collapse; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    .ok { color: green; }
    .fehler { color: red; }
  </style>
</head>
<body>
<h1>📥 <?= Language::get('debtor_incoming_payments') ?></h1>

<?php if ($meldung): ?>
  <script>alert("<?= addslashes($meldung) ?>");</script>
<?php endif; ?>
<?php if ($fehler): ?>
  <script>alert("⚠️ <?= addslashes($fehler) ?>");</script>
<?php endif; ?>

<form method="post">
  <label><?= Language::get('bank_account') ?>:
    <select name="bankkonto" required>
      <option value=""><?= Language::get('please_select') ?></option>
      <?php foreach ($bankkonten as $k => $bez): ?>
        <option value="<?= $k ?>" <?= $selectedBank == $k ? 'selected' : '' ?>>
          <?= $k ?> – <?= htmlspecialchars($bez) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <h3><?= Language::get('open_items') ?></h3>
  <table>
    <tr>
      <th><?= Language::get('debtor') ?></th>
      <th><?= Language::get('invoice') ?></th>
      <th><?= Language::get('booking_date') ?></th>
      <th><?= Language::get('due_date') ?></th>
      <th><?= Language::get('amount') ?></th>
      <th><?= Language::get('paid') ?></th>
      <th><?= Language::get('outstanding') ?></th>
      <th><?= Language::get('pay_now') ?></th>
    </tr>
    <?php foreach ($offenePosten as $op): ?>
      <?php
        $rest = $op['betrag'] - $op['bezahlt'];
        if ($rest < 0.01) continue;
      ?>
      <tr>
        <td><?= $op['debitorennr'] ?></td>
        <td><?= htmlspecialchars($op['rechnungsnr']) ?></td>
        <td><?= $op['buchungsdatum'] ?></td>
        <td><?= $op['faelligkeit'] ?></td>
        <td><?= number_format($op['betrag'], 2, ',', '.') ?> €</td>
        <td><?= number_format($op['bezahlt'], 2, ',', '.') ?> €</td>
        <td><?= number_format($rest, 2, ',', '.') ?> €</td>
        <td>
          <input type="number" step="0.01" name="zahlung[<?= $op['id'] ?>]" 
                 value="<?= number_format($rest, 2, '.', '') ?>" 
                 <?= $rechnungsnr ? 'readonly' : '' ?>>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <button type="submit" name="zahlung_senden" value="1">✅ <?= Language::get('post_payments') ?></button>
</form>
</body>
</html>
