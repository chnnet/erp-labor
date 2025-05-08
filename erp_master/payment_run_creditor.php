<?php
include "header.php";
require_once 'classes/database.php';
require_once 'classes/journal.php';
require_once 'classes/language.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

Database::initialize();
$pdo = Database::$conn;

$meldung = '';
$fehler = '';
$offene = [];

$bankkonten = $pdo->query("SELECT kontonr, bezeichnung FROM bankkonten")->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankkonto = (int)($_POST['bankkonto'] ?? 0);

    try {
        if (!$bankkonto) {
            throw new Exception(Language::get('select_bank_account'));
        }

        // Offene Posten immer abrufen für Anzeige + spätere Buchung
        $offene = $pdo->query("SELECT * FROM offene_posten_kreditoren WHERE status = 'offen'")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($offene)) {
            throw new Exception(Language::get('no_open_items'));
        }

        // Nur buchen, wenn explizit bestätigt
        if (isset($_POST['confirm']) && $_POST['confirm'] === '1') {
            $zeilen = [];
            $beschreibung = Language::get('creditor_payment_run_description');
            $belegdatum = date('Y-m-d');
            $buchungsdatum = date('Y-m-d');
            $zahlung = 30;
            $userId = $_SESSION['user_id'] ?? 1;

            foreach ($offene as $posten) {
                $kreditor = $posten['kreditorennr'];
                $betrag = $posten['betrag'];

                $zeilen[] = [
                    'konto' => $kreditor,
                    'typ' => 'Soll',
                    'betrag' => $betrag,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];
                $zeilen[] = [
                    'konto' => $bankkonto,
                    'typ' => 'Haben',
                    'betrag' => $betrag,
                    'ust' => '',
                    'steuer_betrag' => 0
                ];

                $stmt = $pdo->prepare("UPDATE offene_posten_kreditoren SET bezahlt = ?, status = 'bezahlt' WHERE id = ?");
                $stmt->execute([$betrag, $posten['id']]);

                $insert = $pdo->prepare("
                    INSERT INTO zahllauefe_kreditor (posten_id, kreditorennr, betrag, zahlungsdatum, user_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $posten['id'],
                    $kreditor,
                    $betrag,
                    $buchungsdatum,
                    $userId
                ]);
            }

            $journalId = JournalEntry::save(
                $userId,
                'K',
                $zahlung,
                $beschreibung,
                $buchungsdatum,
                $belegdatum,
                $zeilen
            );

            $meldung = Language::get('creditor_payment_run_success', ['id' => $journalId]);
            $offene = []; // Zurücksetzen, Anzeige leer nach Buchung
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
  <title><?= Language::get('creditor_payment_run') ?></title>
  <style>
    body { font-family: Arial; padding: 2em; }
    .ok { color: green; }
    .fehler { color: red; }
    select, button { padding: 8px; margin-top: 10px; width: 100%; max-width: 400px; }
    table { border-collapse: collapse; width: 100%; max-width: 800px; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f0f0f0; }
  </style>
</head>
<body>
  <h1>🏦 <?= Language::get('creditor_payment_run') ?></h1>

  <?php if ($meldung): ?><p class="ok"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
  <?php if ($fehler): ?><p class="fehler"><?= htmlspecialchars($fehler) ?></p><?php endif; ?>

  <form method="post">
    <label><?= Language::get('select_bank_account') ?>:</label>
    <select name="bankkonto" required>
      <option value=""><?= Language::get('please_select') ?></option>
      <?php foreach ($bankkonten as $nr => $bez): ?>
        <option value="<?= $nr ?>" <?= (isset($bankkonto) && $bankkonto == $nr) ? 'selected' : '' ?>>
          <?= $nr ?> – <?= htmlspecialchars($bez) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if (!empty($offene)): ?>
      <h2>📋 <?= Language::get('open_items') ?>:</h2>
      <table>
        <thead>
          <tr>
            <th><?= Language::get('creditor_number') ?></th>
            <th><?= Language::get('amount') ?></th>
            <th><?= Language::get('due_date') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($offene as $posten): ?>
            <tr>
              <td><?= htmlspecialchars($posten['kreditorennr']) ?></td>
              <td><?= number_format($posten['betrag'], 2, ',', '.') ?> EUR</td>
              <td><?= htmlspecialchars($posten['faelligkeit'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <input type="hidden" name="confirm" value="1">
      <button type="submit">✅ <?= Language::get('run_payment') ?></button>
    <?php else: ?>
      <button type="submit">🔍 <?= Language::get('preview_payment_run') ?? 'Zahllauf anzeigen' ?></button>
    <?php endif; ?>
  </form>
</body>
</html>
