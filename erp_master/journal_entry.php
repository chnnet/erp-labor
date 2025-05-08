<?php
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

$fehler = '';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $taxSeparate = isset($_POST['steuer_separat']) && $_POST['steuer_separat'] === '1';

        $lines = [];
        $taxLines = [];
        $soll = 0;
        $haben = 0;
        $lineNr = 1;

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

            $fullAmount = $amount + ($taxSeparate ? 0 : $taxAmount);

            if ($type === 'Soll') $soll += $fullAmount;
            else $haben += $fullAmount;

            $lines[] = [
                'journalzeile' => $lineNr++,
                'konto' => $account,
                'typ' => $type,
                'betrag' => $fullAmount,
                'ust' => $vat,
                'steuer_betrag' => $taxAmount
            ];

            if ($taxAmount > 0 && $taxAccount) {
                $direction = $type;
                if ($taxSeparate) {
                    $taxLines[] = [
                        'konto' => $taxAccount,
                        'typ' => $direction,
                        'betrag' => $taxAmount,
                        'ust' => $vat,
                        'steuer_betrag' => $taxAmount
                    ];
                } else {
                    $lines[] = [
                        'journalzeile' => $lineNr++,
                        'konto' => $taxAccount,
                        'typ' => $direction,
                        'betrag' => $taxAmount,
                        'ust' => $vat,
                        'steuer_betrag' => $taxAmount
                    ];
                    if ($direction === 'Soll') $soll += $taxAmount;
                    else $haben += $taxAmount;
                }
            }

            if (!empty($offset)) {
                $oppositeType = $type === 'Soll' ? 'Haben' : 'Soll';
                $lines[] = [
                    'journalzeile' => $lineNr++,
                    'konto' => (int)$offset,
                    'typ' => $oppositeType,
                    'betrag' => $fullAmount,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];
                if ($oppositeType === 'Soll') $soll += $fullAmount;
                else $haben += $fullAmount;
            }
        }

        if (abs($soll - $haben) > 0.01) {
            throw new Exception(Language::get('booking_not_balanced', ['diff' => number_format($soll - $haben, 2)]));
        }

        $journalId = JournalEntry::save(
            $userId,
            $vorgang,
            $paymentTerms,
            $bookingText,
            $bookingDate,
            $documentDate,
            $lines
        );

        $taxJournalId = null;
        if ($taxSeparate && !empty($taxLines)) {
            $taxJournalId = JournalEntry::save(
                $userId,
                $vorgang,
                $paymentTerms,
                Language::get('tax_booking_for', ['id' => $journalId]),
                $bookingDate,
                $documentDate,
                $taxLines
            );
        }

        $meldung = Language::get('booking_saved', ['id' => $journalId]);
        if ($taxJournalId) {
            $meldung .= ' ' . Language::get('tax_journal_id') . ' ' . $taxJournalId;
        }

    } catch (Exception $e) {
        $fehler = $e->getMessage();
    }
}
?>

<!-- HTML PART -->
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('journal_entry_title') ?></title>
  <style>
    form { max-width: 700px; margin: auto; }
    label { display: block; margin: 10px 0 5px; }
    input, select { width: 100%; padding: 8px; margin-bottom: 10px; }
    table { width: 100%; margin-top: 20px; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    .fehler { color: red; }
    .ok { color: green; }
    .hinweis { font-style: italic; color: #555; margin-bottom: 1em; }
  </style>
</head>
<body>
<h1 style="text-align:center;"><?= Language::get('journal_entry_heading') ?></h1>
<?php if ($fehler): ?><p class="fehler"><?= htmlspecialchars($fehler) ?></p><?php endif; ?>
<?php if ($meldung): ?><p class="ok"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>

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

  <label>
    <input type="checkbox" name="steuer_separat" id="steuer_separat" value="1" checked>
    <?= Language::get('separate_tax_hint') ?>
  </label>
  <div id="steuerHinweis" class="hinweis"><?= Language::get('separate_tax_description') ?></div>

  <h2><?= Language::get('booking_lines') ?></h2>
  <table id="buchungstabelle">
    <tr>
      <th><?= Language::get('account') ?></th><th><?= Language::get('type') ?></th><th><?= Language::get('amount') ?></th><th>USt</th><th><?= Language::get('offset_account') ?></th>
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
    </tr>
  </table>

  <button type="button" onclick="addRow()">+ <?= Language::get('add_row') ?></button>
  <button type="submit">📥 <?= Language::get('submit_booking') ?></button>
</form>

<script>
function addRow() {
  const table = document.getElementById("buchungstabelle");
  const row = table.rows[1];
  const clone = row.cloneNode(true);
  clone.querySelectorAll("input, select").forEach(e => e.value = '');
  table.appendChild(clone);
}

document.getElementById('steuer_separat').addEventListener('change', function () {
  const hint = document.getElementById('steuerHinweis');
  hint.textContent = this.checked
    ? '<?= Language::get("separate_tax_description") ?>'
    : '<?= Language::get("tax_inline_description") ?>';
});
</script>
</body>
</html>
