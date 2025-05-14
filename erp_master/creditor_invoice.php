<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

include("header.php");
require_once 'classes/Database.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Journal.php';
require_once 'classes/Language.php';

Database::initialize();
$pdo = Database::$conn;

error_reporting(E_ALL);
ini_set('display_errors', 'on'); 

$fehler = '';
$meldung = '';
$userId = $_SESSION['user_id'] ?? 1;

// USt-Sätze laden
$steuerSaetze = [];
$stmt = $pdo->query("SELECT ust_code AS kuerzel, satz AS prozentsatz, konto FROM ust_saetze WHERE gueltig_bis IS NULL OR gueltig_bis >= CURDATE()");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $steuerSaetze[$row['kuerzel']] = [
        'satz' => (float)$row['prozentsatz'],
        'konto' => (int)$row['konto']
    ];
}

$kreditoren = $pdo->query("SELECT kreditorennr, bezeichnung FROM kreditoren ORDER BY kreditorennr")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buchen'])) {
    try {
        $kreditor = $_POST['kreditor'] ?? null;
        $rechnung = trim($_POST['rechnungsnr'] ?? '');
        $belegdatum = $_POST['belegdatum'] ?? date('Y-m-d');
        $buchungsdatum = $_POST['buchungsdatum'] ?? date('Y-m-d');
        $zahlung = $_POST['zahlungsbedingungen'] ?? '30';
        $beschreibung = trim($_POST['beschreibung'] ?? '');

        if (!$kreditor || empty($_POST['konto']) || empty($_POST['betrag'])) {
            throw new Exception(Language::get('error_missing_fields'));
        }

        $zeilen = [];
        $steuerzeilen = [];
        $bruttobetrag = 0;
        $zeilennummer = 1;

for ($i = 0; $i < count($_POST['konto']); $i++) {
    $konto = (int)$_POST['konto'][$i];
    $betrag = (float)$_POST['betrag'][$i];
    $ust = trim($_POST['ust'][$i] ?? '');
    $gegenkonto = (int)($_POST['gegenkonto'][$i] ?? 0);

    if (!$konto || $betrag <= 0) continue;

    $steuerBetrag = 0;
    $steuerKonto = null;

    // 📌 1. Buchung – Nettoseite
    $nettoLineNumber = $zeilennummer;

    $zeilen[] = [
        'journalzeile' => $zeilennummer++,
        'konto' => $konto,
        'typ' => 'Soll',
        'betrag' => $betrag,
        'ust' => $ust,
        'steuer_betrag' => 0,
        'buchungstext' => $beschreibung
    ];

    // 📌 2. Steuer (wenn vorhanden)
    if ($ust && isset($steuerSaetze[$ust])) {
        $steuerSatz = $steuerSaetze[$ust]['satz'];
        $steuerKonto = $steuerSaetze[$ust]['konto'];
        $steuerBetrag = round($betrag * $steuerSatz / 100, 2);

        if ($steuerKonto <= 0) {
            throw new Exception("Steuerkonto fehlt oder ungültig für USt-Code: $ust");
        }

        $steuerzeilen[] = [
            'lineNumber' => $nettoLineNumber, // <-- 🔗 exakte Referenz
            'konto' => $steuerKonto,
            'typ' => 'Soll',
            'steuer_betrag' => $steuerBetrag,
            'ust' => $ust,
            'buchungstext' => $beschreibung
        ];
    }

    $bruttobetrag += $betrag + $steuerBetrag;
}

      // 📌 Kreditorenkonto als Gegenbuchung für den Bruttobetrag
      $zeilen[] = [
        'journalzeile' => $zeilennummer++,
        'konto' => $kreditor, // Kreditorennr = Kreditorenkonto
        'typ' => 'Haben',
        'betrag' => $bruttobetrag,
        'ust' => '',
        'steuer_betrag' => 0,
        'buchungstext' => $beschreibung
        ];

        $journalId = JournalEntry::save(
            $userId, 'K', $zahlung, $rechnung,
            $buchungsdatum, $belegdatum, $zeilen, $steuerzeilen 
        );

        $faelligkeit = (new DateTime($buchungsdatum))->modify("+{$zahlung} days")->format('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO offene_posten_kreditoren (kreditorennr, rechnungsnr, buchungsdatum, faelligkeit, betrag, status, zahlungsbedingung, bemerkung) VALUES (?, ?, ?, ?, ?, 'offen', ?, ?)");
        $stmt->execute([
            $kreditor,
            $rechnung,
            $buchungsdatum,
            $faelligkeit,
            $bruttobetrag,
            $zahlung,
            $beschreibung
        ]);

        // Update offener Betrag in kreditoren
        $pdo->prepare("UPDATE kreditoren SET offener_betrag = offener_betrag + ? WHERE kreditorennr = ?")
            ->execute([$bruttobetrag, $kreditor]);

        $meldung = Language::get('success_creditor_booking', ['id' => $journalId]);
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
  <title><?= Language::get('creditor_invoice_title') ?></title>
  <style>
    .button{  cursor: pointer; }
    form { max-width: 1000px; margin: auto; }
    label { display: block; margin-top: 1em; }
    input, select { width: 100%; padding: 8px; }
    table { width: 100%; margin-top: 1em; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ccc; vertical-align: middle; }
    .btn-row { text-align: right; margin-top: 1em; }
    .remove-btn {
      background: #e53935;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
      cursor: pointer;
    }
    title {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 20px;
      text-align: center;
    }
    input[type="text"], input[type="date"], input[type="number"], select {
    width: 100%;
    padding: 6px 10px;
    box-sizing: border-box;
    font-size: 14px;
    margin-bottom: 10px;
  }
  </style>
</head>
<body>

<?php if ($meldung): ?>
  <script>alert("<?= addslashes($meldung) ?>");</script>
<?php endif; ?>
<?php if ($fehler): ?>
  <script>alert("<?= addslashes($fehler) ?>");</script>
<?php endif; ?>

<h1 style="text-align:center;"><?= Language::get('creditor_invoice_heading') ?></h1>

<form method="post">
  <label><?= Language::get('creditor') ?>
    <select name="kreditor" required>
      <option value="">– <?= Language::get('select') ?> –</option>
      <?php foreach ($kreditoren as $k): ?>
        <option value="<?= $k['kreditorennr'] ?>"><?= $k['kreditorennr'] ?> – <?= htmlspecialchars($k['bezeichnung']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label><?= Language::get('invoice_number') ?><input type="text" name="rechnungsnr" required></label>
  <label><?= Language::get('document_date') ?><input type="date" name="belegdatum" value="<?= date('Y-m-d') ?>"></label>
  <label><?= Language::get('booking_date') ?><input type="date" name="buchungsdatum" value="<?= date('Y-m-d') ?>"></label>

  <label><?= Language::get('payment_terms') ?>
    <select name="zahlungsbedingungen"><?= KeyValueHelper::getOptions("zahlungsbedingungen", "tage", "beschreibung") ?></select>
  </label>

  <label><?= Language::get('description') ?><input type="text" name="beschreibung"></label>

  <h2><?= Language::get('booking_lines') ?></h2>
  <table id="buchungstabelle">
    <thead>
      <tr>
        <th><?= Language::get('account') ?></th>
        <th><?= Language::get('amount') ?> (€)</th>
        <th><?= Language::get('tax_code') ?></th>
        <th>🗑️</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><select name="konto[]"><?= KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung") ?></select></td>
        <td><input type="number" name="betrag[]" step="0.01" min="0.01" required></td>
        <td>
          <select name="ust[]">
            <option value="">–</option>
            <?php foreach ($steuerSaetze as $code => $satz): ?>
              <option value="<?= $code ?>"><?= $code ?> (<?= $satz['satz'] ?>%)</option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><button type="button" class="remove-btn" onclick="removeRow(this)">❌</button></td>
      </tr>
    </tbody>
  </table>

  <div class="btn-row">
    <button type="button" onclick="addRow()"> <?= Language::get('add_row') ?></button>
    <button type="submit" name="buchen"><?= Language::get('submit_booking') ?></button>
  </div>
</form>

<script>
function addRow() {
  const table = document.querySelector("#buchungstabelle tbody");
  const row = table.rows[0].cloneNode(true);
  row.querySelectorAll("input").forEach(input => input.value = '');
  row.querySelectorAll("select").forEach(sel => sel.selectedIndex = 0);
  table.appendChild(row);
}

function removeRow(btn) {
  const row = btn.closest("tr");
  const table = row.parentElement;
  if (table.rows.length > 1) {
    table.removeChild(row);
  } else {
    alert("<?= Language::get('at_least_one_row_required') ?>");
  }
}
</script>

</body>
</html>
