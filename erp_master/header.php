<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
  header("Location: login.php");
  exit;
}
require_once 'classes/Language.php';

// Sprache setzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
$lang = $_SESSION['language'] ?? 'de';

function activeLink($file) {
    $current = strtok(basename($_SERVER['PHP_SELF']) . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), '?');
    return strpos($file, $current) !== false ? ' class="active"' : '';
}

$nav = [
    'journal' => [
        'title' => Language::get('nav_journal'),
        'entries' => [
            'journal_selection.php' => Language::get('nav_journal_selection'),
            'journal_entry.php?vorgang=H' => Language::get('nav_journal_entry_gl'),
            'journal_entry.php?vorgang=A' => Language::get('nav_journal_entry_asset'),
            'journal_entry.php?vorgang=L' => Language::get('nav_journal_entry_payroll'),
            'account_framework.php' => Language::get('nav_chart_of_accounts'),
            'account_overview.php' => Language::get('nav_account_overview'),
            'journal_create.php' => Language::get('nav_account_create')
        ]
    ],
    'debitor' => [
        'title' => Language::get('nav_debtor'),
        'entries' => [
            'debtor_create.php' => Language::get('nav_debtor_create'),
            'debtor_manage.php' => Language::get('nav_debtor_manage'),
            'payment_incoming_debtor.php' => Language::get('nav_debtor_payments'),
            'open_items_debtor.php' => Language::get('nav_debtor_open_items')
        ]
    ],
    'kreditor' => [
        'title' => Language::get('nav_creditor'),
        'entries' => [
            'creditor_create.php' => Language::get('nav_creditor_create'),
            'creditor_manage.php' => Language::get('nav_creditor_manage'),
            'payment_run_creditor.php' => Language::get('nav_creditor_payment_run'),
            'open_items_creditor.php' => Language::get('nav_creditor_open_items')
        ]
    ],
    'system' => [
    'title' => Language::get('nav_system'),
    'entries' => [
        'table_overview.php' => Language::get('nav_table_overview'),
        'user_manage.php' => Language::get('nav_user_manage')
    ]
]
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title>ERP System</title>
    <style>
        nav {
            background-color: #333;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            flex-wrap: wrap;
        }
        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
        }
        nav li {
            position: relative;
            margin-right: 20px;
        }
        nav li > a {
            color: white;
            text-decoration: none;
            padding: 6px 10px;
            display: block;
        }
        nav a:hover, nav li:hover > a {
            background-color: #555;
        }
        nav a.active {
            background-color: #0077cc;
            font-weight: bold;
        }
        nav ul ul {
            display: none;
            position: absolute;
            background-color: #444;
            top: 100%;
            left: 0;
            min-width: 200px;
            z-index: 1000;
            padding: 0;
            margin: 0;
        }
        nav li:hover > ul {
            display: block;
        }
        nav ul ul li {
            width: 100%;
        }
        nav ul ul a {
            padding: 8px 12px;
            display: block;
            white-space: nowrap;
        }
        nav ul ul a:hover {
            background-color: #666;
        }
        .right-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .lang-switch select {
            background: #444;
            color: white;
            border: 1px solid #ccc;
            padding: 4px 6px;
        }
        nav a.logout-link {
        color: white; /* oder z.B. #ff5555 für ein auffälliges Rot */
        font-weight: bold;
        }
      nav a.logout-link:hover {
    color:rgb(247, 247, 247);
}
    </style>
</head>
<body>
<nav>
    <ul>
        <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="dashboard.php"<?= activeLink("dashboard.php") ?>><?= Language::get('nav_dashboard') ?></a></li>

            <?php foreach ($nav as $key => $section): ?>
                <li>
                    <a href="#"><?= $section['title'] ?></a>
                    <ul>
                        <?php foreach ($section['entries'] as $file => $label): ?>
                            <li><a href="<?= $file ?>"<?= activeLink($file) ?>><?= $label ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li style="color: white; padding: 6px 10px;">🔐 ERP System</li>
        <?php endif; ?>
    </ul>

    <div class="right-section">
        <form method="post" class="lang-switch">
            <select name="language" onchange="this.form.submit()">
                <option value="de" <?= ($_SESSION['language'] ?? 'de') === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch</option>
                <option value="en" <?= ($_SESSION['language'] ?? 'de') === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
            </select>
        </form>

        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="logout.php" class="logout-link"<?= activeLink("logout.php") ?>><?= Language::get('nav_logout') ?></a>
        <?php endif; ?>
    </div>
</nav>
