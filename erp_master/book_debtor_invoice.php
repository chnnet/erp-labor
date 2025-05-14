<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

include "header.php";
require_once 'classes/Database.php';
require_once 'classes/KeyValue.php';
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

$ustSaetze = [];
$stmt = $pdo->query("SELECT ust_code, satz, konto FROM ust_saetze WHERE gueltig_bis IS NULL OR gueltig_bis >= CURDATE()");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ustSaetze[$row['ust_code']] = [
        'satz' => (float)$row['satz'],
        'konto' => (int)$row['konto']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buchen'])) {
    try {
        $debitor = (int)$_POST['debitor'];
        $referenz = trim($_POST['referenz'] ?? '');
        $belegdatum = $_POST['belegdatum'] ?? date('Y-m-d');
        $buchungsdatum = $_POST['buchungsdatum'] ?? date('Y-m-d');
        $zahlung = $_POST['zahlungsbedingungen'] ?? '30';
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        $userId = $_SESSION['user_id'] ?? 1;

        $zeilen = [];
        $steuerzeilen = [];
        $bruttobetrag = 0;
        $lineNr = 1;

        for ($i = 0; $i < count($_POST['betrag']); $i++) {
            $betrag = (float)$_POST['betrag'][$i];
            $erloeskonto = (int)$_POST['konto'][$i];
            $ust = trim($_POST['ust'][$i] ?? '');

            if (!$erloeskonto || $betrag <= 0) 
                continue;
            if ($ust && isset($ustSaetze[$ust])) {
                $satz = $ustSaetze[$ust]['satz'];
                $steuerkonto = (int) $ustSaetze[$ust]['konto'];

                if ($steuerkonto <= 0) {
                throw new Exception("Steuerkonto fehlt oder ungültig für USt-Code: $ust");
            }     

              $steuerbetrag = round($betrag * $satz / 100, 2);
              // 1. Erlöskonto (Haben)
              $erloesLineNumber = $lineNr;

            // 1. Erlöskonto (Haben)
            $zeilen[] = [
                'journalzeile' => $lineNr++,
                'konto' => $erloeskonto,
                'typ' => 'Haben',
                'betrag' => $betrag,
                'ust' => $ust,
                'steuer_betrag' => $steuerbetrag,
                'buchungstext' => $beschreibung
            ];

            $bruttobetrag += $betrag;

            if ($ust && isset($ustSaetze[$ust])) {
                $steuerbetrag = round($betrag * $ustSaetze[$ust]['satz'] / 100, 2);

                if ($steuerbetrag > 0) {
                  $steuerzeilen[] = [
                    'lineNumber' => $erloesLineNumber,
                    'konto' => $steuerkonto,
                    'typ' => 'Soll',
                    'betrag' => $steuerbetrag,
                    'ust' => $ust,
                    'steuer_betrag' => $steuerbetrag,
                    'buchungstext' => $beschreibung,
                ];

                }
            }
                $bruttobetrag += $steuerbetrag;
          }
        }

        // Buchung des Bruttobetrags auf das Debitorenkonto (Haben)
        $zeilen[] = [
        'journalzeile' => $lineNr++,
        'konto' => $debitor,
        'typ' => 'Soll',
        'betrag' => $bruttobetrag,
        'ust' => '',
        'steuer_betrag' => 0,
        'buchungstext' => $beschreibung
        ];

        $hauptJournalId = JournalEntry::save(
            $userId,
            'D',
            $zahlung,
            $referenz,
            $buchungsdatum,
            $belegdatum,
            $zeilen,
            $steuerzeilen
        );

        $faelligkeit = (new DateTime($buchungsdatum))->modify("+{$zahlung} days")->format('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO offene_posten_debitoren (debitorennr, rechnungsnr, buchungsdatum, faelligkeit, betrag, status, zahlungsbedingung, bemerkung) VALUES (?, ?, ?, ?, ?, 'offen', ?, ?)");
        $stmt->execute([
            $debitor,
            $referenz,
            $buchungsdatum,
            $faelligkeit,
            $bruttobetrag,
            $zahlung,
            $beschreibung
        ]);

        $stmt = $pdo->prepare("UPDATE debitoren SET offener_betrag = (SELECT COALESCE(SUM(betrag - bezahlt), 0) FROM offene_posten_debitoren WHERE debitorennr = ?) WHERE debitorennr = ?");
        $stmt->execute([$debitor, $debitor]);

        $meldung = "✅ " . Language::get('debitor_booking_saved', ['id' => $journalId]);

    } catch (Exception $e) {
        $fehler = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    form { max-width: 1000px; margin: auto; }
    label { display: block; margin-top: 1em; }
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    input, select { width: 100%; }
    .ok { color: green; }
    .fehler { color: red; }
    
    button.remove-btn {
      background-color: #f44336;
      color: white;
      border: none;
      padding: 4px 10px;
      font-size: 14px;
      cursor: pointer;
      border-radius: 4px;
    }
    button.remove-btn:hover {
      background-color: #c62828;
    }
    h1 {
        text-align: center;
    }

    input[type="text"], input[type="date"], input[type="number"], select {
    width: 100%;
    padding: 4px 10px;
    box-sizing: border-box;
    font-size: 14px;
    margin-bottom: 10px;
    border-radius: 4px;
  }
  </style>
</head>
<body>
<h1><?= Language::get('debitor_booking_title') ?></h1>
<?php if ($meldung): ?>
  <script>alert("<?= addslashes($meldung) ?>");</script>
<?php endif; ?>
<?php if ($fehler): ?>
  <script>alert("⚠️ <?= addslashes($fehler) ?>");</script>
<?php endif; ?>

<form method="post">
  <label><?= Language::get('debtor') ?>:</label>
  <select name="debitor" required>
    <option value="">– <?= Language::get('select') ?> –</option>
    <?php foreach ($debitoren as $d): ?>
      <option value="<?= $d['debitorennr'] ?>"><?= $d['debitorennr'] ?> – <?= htmlspecialchars($d['bezeichnung']) ?></option>
    <?php endforeach; ?>
  </select>

  <label><?= Language::get('reference') ?>:</label>
  <input type="text" name="referenz" required>

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
      <th>🗑️</th>
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
      <td><button type="button" class="remove-btn" onclick="removeRow(this)">❌</button></td>
    </tr>
  </table>

  <button type="button" onclick="addRow()"><?= Language::get('add_row') ?></button>
  <br><br>
  <button type="submit" name="buchen" value="1"><?= Language::get('submit_booking') ?></button>
</form>

<script>
function addRow() {
  const table = document.getElementById("buchungstabelle");
  const row = table.rows[1].cloneNode(true);
  row.querySelectorAll("input").forEach(input => input.value = '');
  row.querySelectorAll("select").forEach(sel => sel.selectedIndex = 0);
  table.appendChild(row);
}

function removeRow(button) {
  const table = document.getElementById("buchungstabelle");
  if (table.rows.length > 2) {
    button.closest("tr").remove();
  } else {
    alert("Mindestens eine Buchungszeile ist erforderlich.");
  }
}
</script>
</body>
</html>
