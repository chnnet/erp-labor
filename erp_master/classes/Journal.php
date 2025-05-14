<?php
require_once 'Database.php';
require_once 'Language.php';

class JournalEntry
{
    public static function nextId(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT MAX(ID) FROM journal");
        $max = $stmt->fetchColumn();
        return $max ? ((int)$max + 1) : 1;
    }

    public static function save(
    int $userId,
    string $type,
    string $paymentTerms,
    string $referenz,
    string $bookingDate,
    string $documentDate,
    array $lines,
    array $taxLines = []
): int {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    try {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $documentDate)) {
            throw new Exception("Ungültiges Datumsformat.");
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM journal WHERE referenz = ?");
        $stmt->execute([$referenz]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception(Language::get('reference_already_used'));
        }

        $journalId = self::nextId();

        $stmt = $pdo->prepare("INSERT INTO journal (ID, vorgang, datum, referenz, benutzer_id, zahlungsbedingungen)
                               VALUES (?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([$journalId, $type, $referenz, $userId, $paymentTerms]);

        $stmtLine = $pdo->prepare("INSERT INTO journalzeile (
            journal_id, journalzeile, kontosoll, kontohaben, betrag, status, vorgang, buchungstext, buchungsdatum, belegdatum, ust_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $taxStmt = $pdo->prepare("INSERT INTO steuerbuchungen (
            journal_id, journalzeile, steuer_code, steuer_betrag, konto, buchungsdatum, belegdatum
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmtLedger = $pdo->prepare("INSERT INTO hauptbuch (
            buchungsdatum, belegdatum, buchungstext, betrag, kontonr, soll_haben, journal_id, ust_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");


        $lineNumber = 1;
        $konten = [];

        // 📒 Journalzeilen und Hauptbuch
        foreach ($lines as $line) {
            $soll = $line['typ'] === 'Soll' ? $line['konto'] : null;
            $haben = $line['typ'] === 'Haben' ? $line['konto'] : null;
            $betrag = (float)$line['betrag'];
            $ustCode = $line['ust'] ?? '';
            $text = $line['buchungstext'] ?? '';

            $stmtLine->execute([
                $journalId,
                $lineNumber,
                $soll ?? 0,
                $haben ?? 0,
                $betrag,
                'A',
                $type,
                $text,
                $bookingDate,
                $documentDate,
                $ustCode ?: null
            ]);

            $stmtLedger->execute([
                $bookingDate,
                $documentDate,
                $text,
                $betrag,
                $line['konto'],
                $line['typ'] === 'Soll' ? 'S' : 'H',
                $journalId,
                $ustCode ?: null
            ]);


            $konten[$line['konto']] = true;
            $lineNumber++;
        }

        // 🧾 Steuerzeilen zuordnen
        foreach ($taxLines as $taxLine) {
            $steuerBetrag = (float)$taxLine['steuer_betrag'];
            $steuerCode = $taxLine['ust'];
            $steuerKonto = $taxLine['konto'];
            $steuerText = $taxLine['buchungstext'] ?? '';
            $steuerTyp = $taxLine['typ'] ?? 'Soll';

            // 🚫 Steuerbuchung überspringen, wenn der Betrag 0 ist
            if ($steuerBetrag == 0.0) {
                continue;
            }

            $zeilennummer = $taxLine['lineNumber'] ?? null;
            if (!$zeilennummer || !is_numeric($zeilennummer)) {
                throw new Exception("Für Steuerbuchung fehlt die gültige lineNumber.");
            }

            $taxStmt->execute([
                $journalId,
                $zeilennummer,
                $steuerCode,
                $steuerBetrag,
                $steuerKonto,
                $bookingDate,
                $documentDate
            ]);

            $stmtLedger->execute([
                $bookingDate,
                $documentDate,
                $steuerText,
                $steuerBetrag,
                $steuerKonto,
                $steuerTyp === 'Soll' ? 'S' : 'H',
                $journalId,
                $steuerCode ?: null
            ]);

            $konten[$steuerKonto] = true;
        }

        // 🧮 Salden aktualisieren
        $saldoStmt = $pdo->prepare("
            UPDATE kontenstamm SET open_amount = (
                SELECT COALESCE(SUM(
                    CASE WHEN soll_haben = 'S' THEN betrag ELSE -betrag END
                ), 0)
                FROM hauptbuch WHERE kontonr = ?
            ) WHERE kontonr = ?
        ");

        foreach (array_keys($konten) as $konto) {
            $saldoStmt->execute([$konto, $konto]);
        }

        $pdo->commit();
        return $journalId;

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception(Language::get('journal_save_error') . ': ' . $e->getMessage());
    }
}


}
