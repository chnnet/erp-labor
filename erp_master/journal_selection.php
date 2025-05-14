<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once './classes/Database.php';
require_once './classes/Language.php';

Database::initialize();

?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('journal_selection_title') ?></title>
  <style>
    body { font-family: Arial; padding: 2em; background-color: #f4f4f4; }
    .container { max-width: 800px; margin: auto; background: white; padding: 2em; border-radius: 8px; }
    h1 { margin-bottom: 1em; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1em; }
    .card { border: 1px solid #ccc; padding: 1em; background: #fff; border-radius: 5px; box-shadow: 2px 2px 8px rgba(0,0,0,0.05); }
    .card form { margin: 0; }
    .card button { width: 100%; padding: 0.5em; font-size: 1em; }
    #openJournals { margin-top: 2em; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 0.5em; text-align: left; }
    select { margin-bottom: 1em; }
  </style>
  <script>
    async function ladeJournale() {
      const typ = document.getElementById("filterTyp").value;
      const limit = document.getElementById("filterLimit").value;
      const table = document.getElementById("journaltabelle");

      const res = await fetch(`api/open_journals.php?vorgang=${typ}&limit=${limit}`);
      const data = await res.json();

      if (Array.isArray(data)) {
        table.innerHTML = `<tr>
          <th><?= Language::get('journal_id') ?></th>
          <th><?= Language::get('type') ?></th>
          <th><?= Language::get('status') ?></th>
          <th><?= Language::get('details') ?></th>
        </tr>`;
        data.forEach(row => {
          table.innerHTML += `<tr>
            <td>${row.id}</td>
            <td>${row.typ}</td>
            <td>${row.status ?? '–'}</td>
            <td><a href="journal_show.php?id=${row.id}">🔍 <?= Language::get('show_journal_details') ?></a></td>
          </tr>`;
        });
      } else {
        table.innerHTML = `<tr><td colspan="4" style="color:red">⚠️ <?= Language::get('no_data_loaded') ?>: ${data.error ?? 'Unknown error'}</td></tr>`;
      }
    }

    window.addEventListener("DOMContentLoaded", () => {
      document.getElementById("filterTyp").addEventListener("change", ladeJournale);
      document.getElementById("filterLimit").addEventListener("change", ladeJournale);
      ladeJournale();
    });
  </script>
</head>
<body>
<div class="container">
  <h1><?= Language::get('journal_selection_title') ?></h1>

  <div class="grid">
    <div class="card">
      <form action="journal_entry.php?vorgang=H" method="post">
        <button type="submit">📘 <?= Language::get('new_main_journal') ?></button>
      </form>
    </div>
    <div class="card">
      <form action="journal_entry.php?vorgang=A" method="post">
        <button type="submit">🏗️ <?= Language::get('new_asset_journal') ?></button>
      </form>
    </div>
    <div class="card">
      <form action="journal_entry.php?vorgang=L" method="post">
        <button type="submit">💶 <?= Language::get('new_salary_journal') ?></button>
      </form>
    </div>
    <div class="card">
      <form action="book_debtor_invoice.php" method="post">
        <button type="submit">🏢 <?= Language::get('nav_debtor_invoice') ?></button>
      </form>
    </div>
    <div class="card">
      <form action="creditor_invoice.php" method="post">
        <button type="submit">🏦 <?= Language::get('new_creditor_invoice') ?></button>
      </form>
    </div>
  </div>

  <div id="openJournals">
    <h2><?= Language::get('open_journals') ?></h2>

    <label><?= Language::get('type') ?>:
      <select id="filterTyp">
        <option value=""><?= Language::get('all') ?></option>
        <option value="H"><?= Language::get('vorgang_h') ?></option>
        <option value="D"><?= Language::get('vorgang_d') ?></option>
        <option value="K"><?= Language::get('vorgang_k') ?></option>
        <option value="A"><?= Language::get('vorgang_a') ?></option>
        <option value="L"><?= Language::get('vorgang_l') ?></option>
      </select>
    </label>

    <label><?= Language::get('amount') ?>:
      <select id="filterLimit">
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </label>

    <table id="journaltabelle">
      <tr><td colspan="4"><?= Language::get('loading') ?>...</td></tr>
    </table>
  </div>
</div>
</body>
</html>
