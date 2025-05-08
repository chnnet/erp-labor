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
        string $description,
        string $bookingDate,
        string $documentDate,
        array $lines
    ): int {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $journalId = self::nextId();

            // 1. JOURNAL HEADER
            $stmt = $pdo->prepare("
                INSERT INTO journal (ID, vorgang, datum, benutzer_id, zahlungsbedingungen)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $journalId,
                $type,
                $userId,
                $paymentTerms
            ]);

            // 2. JOURNAL LINES + GENERAL LEDGER
            $lineNumber = 1;
            $stmtLine = $pdo->prepare("
                INSERT INTO journalzeile (
                    journal_id, journalzeile, kontosoll, kontohaben,
                    belegnr, referenz, betrag, status, vorgang, buchungstext,
                    buchungsdatum, belegdatum
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'A', ?, ?, ?, ?)
            ");

            $stmtLedger = $pdo->prepare("
                INSERT INTO hauptbuch (
                    datum, buchungstext, betrag,
                    soll_konto_id, haben_konto_id, journalzeile_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($lines as $line) {
                $soll = $line['typ'] === 'Soll' ? $line['konto'] : null;
                $haben = $line['typ'] === 'Haben' ? $line['konto'] : null;
                $amount = (float)$line['betrag'];
                $vatLabel = $line['ust'] ?? '';
                $text = $vatLabel ? "inkl. {$vatLabel}%" : $description;

                // 2.1 Add to journalzeile
                $stmtLine->execute([
                    $journalId,
                    $lineNumber,
                    $soll ?? 0,
                    $haben ?? 0,
                    $journalId,
                    '',
                    $amount,
                    $type,
                    $text,
                    $bookingDate,
                    $documentDate
                ]);

                $lineId = $pdo->lastInsertId();

                // 2.2 Add to hauptbuch
                $stmtLedger->execute([
                    $bookingDate,
                    $text,
                    $amount,
                    $soll,
                    $haben,
                    $lineId
                ]);

                $lineNumber++;
            }

            $pdo->commit();
            return $journalId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception(Language::get('journal_save_error') . ': ' . $e->getMessage());
        }
    }
}
