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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $steuerGesamt = 0;
        $zeilennummer = 1;

        for ($i = 0; $i < count($_POST['konto']); $i++) {
            $konto = (int)$_POST['konto'][$i];
            $betrag = (float)$_POST['betrag'][$i];
            $ust = trim($_POST['ust'][$i] ?? '');
            $gegenkonto = (int)($_POST['gegenkonto'][$i] ?? 0);

            if ($betrag <= 0) continue;

            $steuerBetrag = 0;
            $steuerKonto = null;

            if ($ust && isset($steuerSaetze[$ust])) {
                $steuerSatz = $steuerSaetze[$ust]['satz'];
                $steuerKonto = $steuerSaetze[$ust]['konto'];
                $steuerBetrag = round($betrag * $steuerSatz / 100, 2);
                $steuerGesamt += $steuerBetrag;
            }

            // Sollbuchung
            $zeilen[] = [
                'journalzeile' => $zeilennummer++,
                'konto' => $konto,
                'typ' => 'Soll',
                'betrag' => $betrag,
                'ust' => $ust,
                'steuer_betrag' => $steuerBetrag
            ];

            if ($gegenkonto) {
                // Habenbuchung
                $zeilen[] = [
                    'journalzeile' => $zeilennummer++,
                    'konto' => $gegenkonto,
                    'typ' => 'Haben',
                    'betrag' => $betrag + $steuerBetrag,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];
            }
        }

        // Habenbuchung Kreditor gesamt
        $gesamt = array_sum(array_column($zeilen, 'betrag'));
        $zeilen[] = [
            'journalzeile' => $zeilennummer++,
            'konto' => $kreditor,
            'typ' => 'Haben',
            'betrag' => $gesamt,
            'ust' => '',
            'steuer_betrag' => 0
        ];

        // Hauptbuchung
        $journalId = JournalEntry::save(
            $userId, 'K', $zahlung, $beschreibung,
            $buchungsdatum, $belegdatum, $zeilen
        );

        // Steuer separat buchen
        if ($steuerGesamt > 0) {
            $steuerZeilen = [];

            foreach ($_POST['konto'] as $i => $k) {
                $ust = trim($_POST['ust'][$i] ?? '');
                $betrag = (float)$_POST['betrag'][$i];
                if (!$ust || $betrag <= 0 || !isset($steuerSaetze[$ust])) continue;

                $steuerProzent = $steuerSaetze[$ust]['satz'];
                $steuerBetrag = round($betrag * $steuerProzent / 100, 2);
                $steuerKonto = $steuerSaetze[$ust]['konto'];

                $steuerZeilen[] = [
                    'journalzeile' => 1,
                    'konto' => $steuerKonto,
                    'typ' => 'Soll',
                    'betrag' => $steuerBetrag,
                    'ust' => $ust,
                    'steuer_betrag' => $steuerBetrag
                ];
                $steuerZeilen[] = [
                    'journalzeile' => 2,
                    'konto' => $kreditor,
                    'typ' => 'Haben',
                    'betrag' => $steuerBetrag,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];
            }

            if (!empty($steuerZeilen)) {
                $steuerText = Language::get('tax_booking_for_journal', ['id' => $journalId]);
                JournalEntry::save(
                    $userId, 'K', $zahlung, $steuerText,
                    $buchungsdatum, $belegdatum, $steuerZeilen
                );
            }
        }

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
  <title><?= Language::get('creditor_invoice_title') ?></title>
  <style>
    form { max-width: 800px; margin: auto; }
    label { display: block; margin-top: 1em; }
    input, select { width: 100%; padding: 8px; }
    .ok { color: green; }
    .fehler { color: red; }
    table { width: 100%; margin-top: 1em; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ccc; }
  </style>
</head>
<body>

<h1 style="text-align:center;"><?= Language::get('creditor_invoice_heading') ?></h1>
<?php if ($meldung): ?><p class="ok"><?= $meldung ?></p><?php endif; ?>
<?php if ($fehler): ?><p class="fehler"><?= $fehler ?></p><?php endif; ?>

<form method="post">
  <label><?= Language::get('creditor') ?>
    <select name="kreditor" required>
      <?= KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung") ?>
    </select>
  </label>

  <label><?= Language::get('invoice_number') ?><input type="text" name="rechnungsnr"></label>
  <label><?= Language::get('document_date') ?><input type="date" name="belegdatum" value="<?= date('Y-m-d') ?>"></label>
  <label><?= Language::get('booking_date') ?><input type="date" name="buchungsdatum" value="<?= date('Y-m-d') ?>"></label>

  <label><?= Language::get('payment_terms') ?>
    <select name="zahlungsbedingungen">
      <?= KeyValueHelper::getOptions("zahlungsbedingungen", "tage", "beschreibung") ?>
    </select>
  </label>

  <label><?= Language::get('description') ?><input type="text" name="beschreibung"></label>

  <h2><?= Language::get('booking_lines') ?></h2>
  <table id="buchungstabelle">
    <tr>
      <th><?= Language::get('account') ?></th>
      <th><?= Language::get('amount') ?> (€)</th>
      <th><?= Language::get('tax_code') ?></th>
      <th><?= Language::get('counter_account') ?></th>
    </tr>
    <tr>
      <td><select name="konto[]"><?= KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung") ?></select></td>
      <td><input type="number" name="betrag[]" step="0.01" required></td>
      <td>
        <select name="ust[]">
          <option value="">–</option>
          <?php foreach ($steuerSaetze as $code => $satz): ?>
            <option value="<?= $code ?>"><?= $code ?> (<?= $satz['satz'] ?>%)</option>
          <?php endforeach; ?>
        </select>
      </td>
      <td>
        <select name="gegenkonto[]">
          <option value="">–</option>
          <?= KeyValueHelper::getOptions("kontenstamm", "kontonr", "bezeichnung") ?>
        </select>
      </td>
    </tr>
  </table>

  <button type="button" onclick="addRow()">+ <?= Language::get('add_row') ?></button>
  <button type="submit">📥 <?= Language::get('submit_invoice') ?></button>
</form>

<script>
function addRow() {
  const table = document.getElementById("buchungstabelle");
  const row = table.rows[1].cloneNode(true);
  row.querySelectorAll("input, select").forEach(e => e.value = '');
  table.appendChild(row);
}
</script>
</body>
</html>
