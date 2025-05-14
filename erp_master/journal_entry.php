<?php

header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Journal.php';
require_once 'classes/ChartOfAccounts.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';

Database::initialize();
$pdo = Database::$conn;

$accounts = ChartOfAccounts::all();
$kontenOptions = '';
foreach ($accounts as $acc) {
    $kontenOptions .= '<option value="' . $acc['kontonr'] . '">' .
        htmlspecialchars($acc['kontonr'] . ' – ' . $acc['bezeichnung']) . '</option>';
}

$meldung = '';
$vorgang = $_GET['vorgang'] ?? $_POST['vorgang'] ?? 'H';

$validTypes = ['H', 'A', 'L'];
if (!in_array($vorgang, $validTypes)) {
    die(Language::get('invalid_transaction_type', ['type' => $vorgang]));
}

// VAT settings
$taxRates = [];
$stmt = $pdo->query("SELECT ust_code, satz, konto FROM ust_saetze WHERE gueltig_bis IS NULL OR gueltig_bis >= CURDATE()");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $taxRates[$row['ust_code']] = [
        'rate' => (float)$row['satz'],
        'account' => (int)$row['konto']
    ];
}

$typeLabels = [
    'H' => Language::get('vorgang_h'),
    'A' => Language::get('vorgang_a'),
    'L' => Language::get('vorgang_l')
];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buchung_absenden'])) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception(Language::get('user_not_logged_in'));
        }

        $reference = $_POST['referenz'] ?? '';
        $documentDate = $_POST['belegdatum'] ?? date('Y-m-d');
        $bookingDate = $_POST['buchungsdatum'] ?? date('Y-m-d');
        $paymentTerms = $_POST['zahlungsbedingungen'] ?? '30';
        $bookingText = $_POST['buchungstext'] ?? '';
        $taxSeparate = true;

        $lines = [];
        $taxLines = [];
        $soll = 0;
        $haben = 0;
        $lineNr = 1;

        $stmt = $pdo->query("
        SELECT ust_code, satz, konto 
        FROM ust_saetze 
        WHERE gueltig_bis IS NULL OR gueltig_bis >= CURDATE()
        ");
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $taxRates[$row['ust_code']] = [
        'rate' => (float)$row['satz'],
        'account' => (int)$row['konto']
      ];
      }

        for ($i = 0; $i < count($_POST['konto']); $i++) {
            $account = (int)$_POST['konto'][$i];
            $amount = (float)$_POST['betrag'][$i];
            $type = $_POST['typ'][$i];
            $vat = $_POST['ust'][$i] ?? '';
            $offset = $_POST['gegenkonto'][$i] ?? '';

            $taxAmount = 0;
            $taxAccount = null;

            if ($vat && isset($taxRates[$vat])) {

                $rate = $taxRates[$vat]['rate'];
                $taxAccount = $taxRates[$vat]['account'];
                $taxAmount = round($amount * $rate / 100, 2);
            }

            $netAmount = $amount;

            if ($type === 'Soll') $soll += $netAmount;
            else $haben += $netAmount;

            $currentLineNumber = $lineNr;            
            $lines[] = [
                'journalzeile' => $lineNr++,
                'konto' => $account,
                'typ' => $type,
                'betrag' => $netAmount,
                'ust' => $vat,
                'steuer_betrag' => $taxAmount ?? 0,
                'buchungstext' => $bookingText,
            ];

                if ($taxAmount > 0 && $taxAccount) {
                    $taxLines[] = [
                    'lineNumber' => $currentLineNumber,
                    'konto' => $taxAccount,
                    'typ' => $type,
                    'betrag' => $taxAmount,
                    'ust' => $vat,
                    'steuer_betrag' => $taxAmount ?? 0,
                    'buchungstext' => $bookingText,
    ];
}

            if (!empty($offset)) {
                $oppositeType = $type === 'Soll' ? 'Haben' : 'Soll';
                $lines[] = [
                    'journalzeile' => $lineNr++,
                    'konto' => (int)$offset,
                    'typ' => $oppositeType,
                    'betrag' => $netAmount,
                    'ust' => $vat,
                    'steuer_betrag' => $taxAmount ?? 0,
                    'buchungstext' => $bookingText,
                ];
                if ($oppositeType === 'Soll') $soll += $netAmount;
                else $haben += $netAmount;
            }
        }

        if (abs($soll - $haben) > 0.01) {
            throw new Exception(Language::get('booking_not_balanced', ['diff' => number_format($soll - $haben, 2)]));
        }

        $journalId = JournalEntry::save(
            $userId,
            $vorgang,
            $paymentTerms,
            $reference,
            $bookingDate,
            $documentDate,
            $lines,
            $taxLines
        );

        $meldung = Language::get('booking_saved', ['id' => $journalId]);
    } catch (Exception $e) {
        $meldung = $e->getMessage();
    }
}
?>

<!-- HTML PART -->
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('journal_entry_title') ?></title>
  <style>
body {
  font-family: sans-serif;
  margin: 2rem;
}

form {
  max-width: 1000px;
  margin: 0 auto;
}

label {
  display: block;
  margin: 1rem 0 0.5rem;
}

input[type="text"],
input[type="date"],
input[type="number"],
select {
  width: 100%;
  padding: 6px 10px;
  box-sizing: border-box;
  font-size: 14px;
}

table {
  width: 100%;
  margin-top: 1rem;
  border-collapse: collapse;
}

th, td {
  border: 1px solid #ddd;
  padding: 6px 8px;
  vertical-align: middle;
  text-align: left;
}

th {
  background-color: #f5f5f5;
  font-weight: 600;
  font-size: 14px;
}

tr td:last-child {
  text-align: center;
}

button[type="button"],
button[type="submit"] {
  margin-top: 1rem;
  padding: 8px 16px;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid #888;
  background-color: #fafafa;
  border-radius: 4px;
}

button[type="submit"] {
  background-color: #4CAF50;
  color: white;
}

button[type="submit"]:hover {
  background-color: #45a049;
}

.summenanzeige {
  text-align: right;
  margin-top: 0.5rem;
  font-weight: bold;
  font-size: 14px;
}

#bilanzWarnung {
  color: red;
  font-weight: bold;
  text-align: right;
  margin-top: 0.5rem;
}

@media (max-width: 768px) {
  table, thead, tbody, th, td, tr {
    display: block;
  }

  tr {
    margin-bottom: 1rem;
  }

  td {
    border: none;
    border-bottom: 1px solid #eee;
  }

  td::before {
    content: attr(data-label);
    font-weight: bold;
    display: block;
  }

.remove-btn {
      background-color: #f44336;
      color: white;
      border: none;
      padding: 4px 10px;
      font-size: 14px;
      cursor: pointer;
      border-radius: 4px;
}

  .remove-btn:hover {
    background: #d32f2f;
  }
}

  </style>
</head>
<body>
<h1 style="text-align:center;"><?= Language::get('journal_entry_heading') ?></h1>
<?php if ($meldung): ?>
  <script>
    alert("<?= addslashes($meldung) ?>");
  </script>
<?php endif; ?>

<form method="post">
  <label><?= Language::get('reference') ?><input type="text" name="referenz" required></label>
  <label><?= Language::get('document_date') ?><input type="date" name="belegdatum" value="<?= date('Y-m-d') ?>"></label>
  <label><?= Language::get('booking_date') ?><input type="date" name="buchungsdatum" value="<?= date('Y-m-d') ?>"></label>
  <label><?= Language::get('payment_terms') ?>
    <select name="zahlungsbedingungen"><?= KeyValueHelper::getOptions("zahlungsbedingungen", "tage", "beschreibung") ?></select>
  </label>
  <label><?= Language::get('booking_text') ?><input type="text" name="buchungstext"></label>
  <label><?= Language::get('transaction_type') ?>
    <select name="vorgang">
      <?php foreach ($typeLabels as $code => $label): ?>
        <option value="<?= $code ?>" <?= $code === $vorgang ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <h2><?= Language::get('booking_lines') ?></h2>
<table id="buchungstabelle">
  <tr>
    <th><?= Language::get('account') ?></th>
    <th><?= Language::get('type') ?></th>
    <th><?= Language::get('amount') ?></th>
    <th>USt</th>
    <th><?= Language::get('offset_account') ?></th>
    <th>🗑️</th> <!-- Spalte für Entfernen-Button -->
  </tr>
  <tr>
    <td><select name="konto[]"><?= $kontenOptions ?></select></td>
    <td>
      <select name="typ[]">
        <option value="Soll">Soll</option>
        <option value="Haben">Haben</option>
      </select>
    </td>
    <td><input type="number" name="betrag[]" step="0.01" required></td>
    <td>
      <select name="ust[]">
        <option value="">–</option>
        <?= KeyValueHelper::getOptions('ust_saetze', 'ust_code', 'satz') ?>
      </select>
    </td>
    <td>
      <select name="gegenkonto[]">
        <option value="">–</option>
        <?= $kontenOptions ?>
      </select>
    </td>
    <td><button type="button" class="remove-btn" onclick="removeRow(this)">❌</button></td>
  </tr>
</table>

<button type="button" onclick="addRow()"> <?= Language::get('add_row') ?></button>
<button type="submit" name="buchung_absenden" value="1"><?= Language::get('submit_booking') ?></button>


<script>
function addRow() {
  const table = document.getElementById("buchungstabelle");
  const row = table.rows[1];
  const clone = row.cloneNode(true);
  clone.querySelectorAll("input, select").forEach(e => e.value = '');
  table.appendChild(clone);
  attachChangeListeners(clone); // Summe bei neuer Zeile berücksichtigen
}

function removeRow(button) {
  const table = document.getElementById("buchungstabelle");
  const row = button.closest("tr");

  if (table.rows.length > 2) {
    row.remove();
    updateSums(); // Summe nach Entfernen aktualisieren
  } else {
    alert("<?= Language::get('at_least_one_row_required') ?>");
  }
}

function updateSums() {
  let soll = 0;
  let haben = 0;

  const rows = document.querySelectorAll("#buchungstabelle tr:not(:first-child)");
  rows.forEach(row => {
    const typ = row.querySelector('select[name="typ[]"]')?.value;
    const betrag = parseFloat(row.querySelector('input[name="betrag[]"]')?.value) || 0;

    if (typ === 'Soll') soll += betrag;
    if (typ === 'Haben') haben += betrag;
  });

  document.getElementById("sollSumme").textContent = soll.toFixed(2);
  document.getElementById("habenSumme").textContent = haben.toFixed(2);

  const differenz = Math.abs(soll - haben);
  const warnung = document.getElementById("bilanzWarnung");
  warnung.style.display = differenz > 0.01 ? "block" : "none";
}

function attachChangeListeners(row) {
  row.querySelectorAll('input[name="betrag[]"], select[name="typ[]"]').forEach(el => {
    el.addEventListener('change', updateSums);
  });
}

// Initiale Listener setzen
document.querySelectorAll("#buchungstabelle tr:not(:first-child)").forEach(row => {
  attachChangeListeners(row);
});

</script>


</body>
</html>
