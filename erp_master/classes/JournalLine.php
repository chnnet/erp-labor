<?php
require_once 'Database.php';
require_once 'Language.php';

class JournalLine
{
    // Fetches all lines for a given journal ID
    public static function findByJournalId(int $journalId, ?string $type = null): array {
        $pdo = Database::getConnection();

        $sql = "SELECT je.*, j.buchungstext, j.buchungsdatum, j.belegdatum 
                FROM journalzeile je
                JOIN journal j ON je.journal_id = j.ID
                WHERE je.journal_id = ?";
        $params = [$journalId];

        if ($type) {
            $sql .= " AND j.vorgang = ?";
            $params[] = $type;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetches all entries for a specific account (can be customer/vendor)
    public static function findByAccount(int $accountId): array {
        $pdo = Database::getConnection();

        $sql = "SELECT je.*, j.buchungstext, j.buchungsdatum, j.belegdatum 
                FROM journalzeile je
                JOIN journal j ON je.journal_id = j.ID
                WHERE je.kontosoll = ? OR je.kontohaben = ?
                ORDER BY j.buchungsdatum DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$accountId, $accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Aggregates open balances per customer/vendor
    public static function sumOpenBalances(string $type = 'debitoren'): array {
        $pdo = Database::getConnection();

        $table = $type === 'kreditoren' ? 'kreditoren' : 'debitoren';
        $col = $type === 'kreditoren' ? 'kreditorennr' : 'debitorennr';

        $sql = "
            SELECT k.$col AS id, k.bezeichnung,
                   COALESCE(SUM(
                     CASE
                       WHEN je.kontosoll = k.$col THEN je.betrag
                       WHEN je.kontohaben = k.$col THEN -je.betrag
                       ELSE 0
                     END
                   ), 0) AS saldo
            FROM $table k
            LEFT JOIN journalzeile je
              ON je.kontosoll = k.$col OR je.kontohaben = k.$col
            GROUP BY k.$col, k.bezeichnung
            HAVING saldo <> 0
            ORDER BY k.$col
        ";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Returns current balance of one account
public static function calculateBalance(int $accountId): float {
    $pdo = Database::getConnection();

    $sql = "
        SELECT COALESCE(SUM(
            CASE
                WHEN kontosoll = :konto THEN betrag
                WHEN kontohaben = :konto THEN -betrag
                ELSE 0
            END
        ), 0) AS saldo
        FROM journalzeile
        WHERE kontosoll = :konto OR kontohaben = :konto
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['konto' => $accountId]);

    $result = $stmt->fetchColumn();
    return is_numeric($result) ? (float)$result : 0.0;
    }
}
