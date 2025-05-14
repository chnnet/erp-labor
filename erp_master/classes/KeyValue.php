<?php
require_once 'Database.php';
require_once 'JournalLine.php';
require_once 'Language.php';

class KeyValueHelper
{
    public static function buildGroupedArray($table, $key, $value, $groupField): array
    {
        Database::initialize();

        $sql = "SELECT $key, $value, $groupField FROM $table ORDER BY $groupField, $key";
        $stmt = Database::$conn->prepare($sql);
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $group = $row[$groupField];
            $result[$group][] = [
                'key' => $row[$key],
                'value' => $row[$value]
            ];
        }
        return $result;
    }

    public static function showBookingsForAccount($accountId): void
    {
        $entries = JournalLine::findByAccount($accountId);
        echo "<h3>" . Language::get('entries_for_account', ['id' => $accountId]) . "</h3>";
        echo "<table><tr><th>" . Language::get('date') . "</th><th>" . Language::get('type') . "</th><th>" . Language::get('amount') . "</th><th>" . Language::get('text') . "</th></tr>";
        foreach ($entries as $entry) {
            echo "<tr>";
            echo "<td>{$entry['transaction_date']}</td>";
            echo "<td>{$entry['entry_type']}</td>";
            echo "<td>" . number_format($entry['amount'], 2) . " €</td>";
            echo "<td>{$entry['description']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    public static function getEnumOptions($table, $field): string
    {
        Database::initialize();
        $stmt = Database::$conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$field]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
        $enum = str_getcsv($matches[1], ',', "'");

        $html = '';
        foreach ($enum as $value) {
            $html .= "<option value=\"$value\">" . strtoupper($value) . "</option>";
        }
        return $html;
    }

    public static function getOptions($table, $idField, $nameField): string
    {
        Database::initialize();

        $stmt = Database::$conn->query("SELECT $idField, $nameField FROM $table ORDER BY $idField");
        $html = '';
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $html .= "<option value=\"" . $row[0] . "\">" . $row[0] . " – " . htmlspecialchars($row[1]) . "</option>";
        }
        return $html;
    }

    public static function showAccountBalance($accountId): void
    {
        $balance = JournalLine::calculateBalance($accountId);
        echo "<p><strong>" . Language::get('account_balance', ['id' => $accountId]) . ":</strong> " . number_format($balance, 2) . " €</p>";
    }

    public static function showJournalLines($journalId): void
    {
        $lines = JournalLine::findByJournalId($journalId);
        echo "<h3>" . Language::get('journal_lines', ['id' => $journalId]) . "</h3>";
        echo "<table><tr><th>#</th><th>" . Language::get('account') . "</th><th>" . Language::get('type') . "</th><th>" . Language::get('amount') . "</th><th>" . Language::get('date') . "</th><th>" . Language::get('status') . "</th></tr>";
        foreach ($lines as $line) {
            echo "<tr>";
            echo "<td>{$line['zeilennr']}</td>";
            echo "<td>{$line['account_id']}</td>";
            echo "<td>{$line['entry_type']}</td>";
            echo "<td>" . number_format($line['amount'], 2) . " €</td>";
            echo "<td>{$line['transaction_date']}</td>";
            echo "<td>{$line['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    public static function getAccountTypeLabel(string $code): string
    {
        $labels = [
            'B' => Language::get('account_type_balance'),
            'E' => Language::get('account_type_income'),
            'K' => Language::get('account_type_expense'),
            'Z' => Language::get('account_type_intermediate')
        ];
        return $labels[$code] ?? $code;
    }
}
