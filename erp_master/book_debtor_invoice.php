<?php
include("header.php");
require_once 'classes/Database.php';
require_once 'classes/Keyvalue.php';
require_once 'classes/Journal.php';
require_once 'classes/Language.php';

Database::initialize();
$pdo = Database::$conn;

$fehler = '';
$meldung = '';
$hauptJournalId = null;
$steuerJournalId = null;

$debitoren = $pdo->query("SELECT debitorennr, bezeichnung FROM debitoren ORDER BY debitorennr")->fetchAll(PDO::FETCH_ASSOC);
$kontenOptions = KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung");

// USt-Sätze laden
$ustSaetze = [];
$stmt = $pdo->query("SELECT ust_code, satz, konto FROM ust_saetze WHERE gueltig_bis IS NULL OR gueltig_bis >= CURDATE()");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ustSaetze[$row['ust_code']] = [
        'satz' => (float)$row['satz'],
        'konto' => (int)$row['konto']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $debitor = (int)$_POST['debitor'];
        $belegdatum = $_POST['belegdatum'] ?? date('Y-m-d');
        $buchungsdatum = $_POST['buchungsdatum'] ?? date('Y-m-d');
        $zahlung = $_POST['zahlungsbedingungen'] ?? '30';
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        $userId = $_SESSION['user_id'] ?? 1;

        $zeilen = [];
        $steuerzeilen = [];

        for ($i = 0; $i < count($_POST['betrag']); $i++) {
            $betrag = (float)$_POST['betrag'][$i];
            $erloeskonto = (int)$_POST['konto'][$i];
            $ust = trim($_POST['ust'][$i] ?? '');
            $gegenkonto = (int)$_POST['gegenkonto'][$i];

            if (!$erloeskonto || $betrag <= 0 || !$gegenkonto) continue;

            $zeilen[] = [
                'journalzeile' => $i + 1,
                'konto' => $debitor,
                'typ' => 'Soll',
                'betrag' => $betrag,
                'ust' => '',
                'steuer_betrag' => 0
            ];

            $zeilen[] = [
                'journalzeile' => $i + 100,
                'konto' => $erloeskonto,
                'typ' => 'Haben',
                'betrag' => $betrag,
                'ust' => $ust,
                'steuer_betrag' => 0
            ];

            if ($ust && isset($ustSaetze[$ust])) {
                $steuerbetrag = round($betrag * $ustSaetze[$ust]['satz'] / 100, 2);
                $steuerkonto = $ustSaetze[$ust]['konto'];

                $steuerzeilen[] = [
                    'journalzeile' => $i + 200,
                    'konto' => $debitor,
                    'typ' => 'Soll',
                    'betrag' => $steuerbetrag,
                    'ust' => $ust,
                    'steuer_betrag' => $steuerbetrag
                ];

                $steuerzeilen[] = [
                    'journalzeile' => $i + 300,
                    'konto' => $steuerkonto,
                    'typ' => 'Haben',
                    'betrag' => $steuerbetrag,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];
            }
        }

        $hauptJournalId = JournalEntry::save(
            $userId,
            'D',
            $zahlung,
            $beschreibung,
            $buchungsdatum,
            $belegdatum,
            $zeilen
        );

        if (!empty($steuerzeilen)) {
            $steuerJournalId = JournalEntry::save(
                $userId,
                'D',
                $zahlung,
                Language::get('tax_booking_for_journal') . " #$hauptJournalId",
                $buchungsdatum,
                $belegdatum,
                $steuerzeilen
            );
        }

        $meldung = "✅ " . Language::get('debitor_booking_saved') . " <strong>#{$hauptJournalId}</strong>";
        if ($steuerJournalId) {
            $meldung .= "<br>📄 " . Language::get('tax_journal_id') . " <strong>#{$steuerJournalId}</strong>";
        }

    } catch (Exception $e) {
        $fehler = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('debitor_booking_title') ?></title>
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    input, select { width: 100%; }
    .ok { color: green; }
    .fehler { color: red; }
  </style>
</head>
<body>
<h1>📤 <?= Language::get('debitor_booking_title') ?></h1>
<?php if ($meldung): ?><p class="ok"><?= $meldung ?></p><?php endif; ?>
<?php if ($fehler): ?><p class="fehler"><?= $fehler ?></p><?php endif; ?>

<form method="post">
  <label><?= Language::get('debtor') ?>:</label>
  <select name="debitor" required>
    <option value="">– <?= Language::get('select') ?> –</option>
    <?php foreach ($debitoren as $d): ?>
      <option value="<?= $d['debitorennr'] ?>"><?= $d['debitorennr'] ?> – <?= htmlspecialchars($d['bezeichnung']) ?></option>
    <?php endforeach; ?>
  </select>

  <label><?= Language::get('document_date') ?>:</label>
  <input type="date" name="belegdatum" value="<?= date('Y-m-d') ?>">

  <label><?= Language::get('booking_date') ?>:</label>
  <input type="date" name="buchungsdatum" value="<?= date('Y-m-d') ?>">

  <label><?= Language::get('payment_terms') ?>:</label>
  <select name="zahlungsbedingungen">
    <?= KeyValueHelper::getOptions("zahlungsbedingungen", "tage", "beschreibung") ?>
  </select>

  <label><?= Language::get('booking_text') ?>:</label>
  <input type="text" name="beschreibung">

  <h3><?= Language::get('booking_lines') ?></h3>
  <table id="buchungstabelle">
    <tr>
      <th><?= Language::get('revenue_account') ?></th>
      <th><?= Language::get('amount') ?></th>
      <th><?= Language::get('vat') ?></th>
      <th><?= Language::get('offset_account') ?></th>
    </tr>
    <tr>
      <td><select name="konto[]"><?= $kontenOptions ?></select></td>
      <td><input type="number" name="betrag[]" step="0.01" required></td>
      <td>
        <select name="ust[]">
          <option value="">–</option>
          <?php foreach ($ustSaetze as $code => $satz): ?>
            <option value="<?= $code ?>"><?= $code ?> (<?= $satz['satz'] ?>%)</option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><select name="gegenkonto[]"><?= $kontenOptions ?></select></td>
    </tr>
  </table>

  <button type="button" onclick="addRow()"><?= Language::get('add_row') ?></button>
  <br><br>
  <button type="submit"><?= Language::get('submit_booking') ?></button>
</form>

<script>
function addRow() {
  const table = document.getElementById("buchungstabelle");
  const row = table.rows[1].cloneNode(true);
  row.querySelectorAll("input").forEach(input => input.value = '');
  row.querySelectorAll("select").forEach(sel => sel.selectedIndex = 0);
  table.appendChild(row);
}
</script>
</body>
</html>
