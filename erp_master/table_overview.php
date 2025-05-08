<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';

Database::initialize();

$pdo = Database::getConnection();
$tabellen = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tabellen[] = $row[0];
}

// Optional: Beschreibungen ergänzen
$beschreibungen = [
    'users' => '👤 ' . Language::get('table_users'),
    'kontenstamm' => '🧮 ' . Language::get('table_chart_of_accounts'),
    'kontorahmen' => '📚 ' . Language::get('table_account_frames'),
    'journal' => '📘 ' . Language::get('table_journal'),
    'journalzeile' => '📄 ' . Language::get('table_journal_lines'),
    'ust_saetze' => '💡 ' . Language::get('table_vat_rates'),
    'zahlungsbedingungen' => '⏱️ ' . Language::get('table_payment_terms'),
    'debitoren' => '🏢 ' . Language::get('table_debtors'),
    'kreditoren' => '🏦 ' . Language::get('table_creditors'),
    'offene_posten_debitoren' => '📂 ' . Language::get('table_open_items_debtors'),
    'offene_posten_kreditor' => '📁 ' . Language::get('table_open_items_creditors')
];
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('table_overview_title') ?></title>
  <link rel="icon" href="data:,"><!-- prevent 404 favicon -->
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #eee; }
    form { margin-bottom: 1em; display: flex; gap: 1em; flex-wrap: wrap; }
    label { display: flex; flex-direction: column; }
  </style>
  <script>
let currentLikeMode = false;

async function ladeFelder(tabelle) {
  const feldSelect = document.getElementById("feld");
  const wertInput = document.getElementById("wert");

  feldSelect.innerHTML = '<option value="">(alle)</option>';
  if (wertInput) wertInput.value = '';

  const res = await fetch('table_ajax.php?action=felder&tabelle=' + tabelle);
  const felder = await res.json();

  feldSelect.onchange = () => {
    const selected = feldSelect.value;
    const selectedField = felder.find(f => f.name === selected);
    const wertElement = document.getElementById("wert");

    if (!wertElement) return;

    if (selectedField && selectedField.enum.length > 0) {
      let dropdown = '<select name="wert" id="wert">';
      selectedField.enum.forEach(val => {
        dropdown += `<option value="${val}">${val.toUpperCase()}</option>`;
      });
      dropdown += '</select>';
      wertElement.outerHTML = dropdown;
    } else {
      let text = '<input type="text" name="wert" id="wert">';
      wertElement.outerHTML = text;
    }

    loadTable();
  };

  for (let feld of felder) {
    const opt = document.createElement("option");
    opt.value = feld.name;
    opt.text = feld.name;
    feldSelect.appendChild(opt);
  }
}

async function loadTable() {
  const tabelle = document.getElementById("tabelle").value;
  const feld = document.getElementById("feld").value;
  const wertElement = document.getElementById("wert");
  const wert = wertElement ? wertElement.value : '';
  const like = currentLikeMode ? '&like=true' : '';
  const res = await fetch(`table_ajax.php?action=daten&tabelle=${tabelle}&feld=${feld}&wert=${encodeURIComponent(wert)}${like}`);
  const html = await res.text();
  document.getElementById("ausgabe").innerHTML = html;
  const label = document.querySelector(`#tabelle option[value='${tabelle}']`).textContent;
  document.getElementById("tabellenname").innerText = '📘 ' + label;
}

function init() {
  document.getElementById("tabelle").addEventListener("change", () => {
    ladeFelder(document.getElementById("tabelle").value);
    loadTable();
  });

  document.getElementById("feld").addEventListener("change", loadTable);
  document.getElementById("wert").addEventListener("input", loadTable);

  document.getElementById("likeToggle").addEventListener("change", function() {
    currentLikeMode = this.checked;
    loadTable();
  });
}

window.onload = init;
  </script>
</head>
<body>
  <h2>📊 <?= Language::get('table_overview_title') ?></h2>

  <form>
    <label><?= Language::get('table') ?>:
      <select name="tabelle" id="tabelle">
        <option value=""><?= Language::get('please_select') ?></option>
        <?php foreach ($tabellen as $tab): ?>
          <?php $label = $beschreibungen[$tab] ?? '❓ ' . $tab; ?>
          <option value="<?= $tab ?>"><?= $tab ?> – <?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label><?= Language::get('field') ?>:
      <select name="feld" id="feld">
        <option value="">(alle)</option>
      </select>
    </label>

    <label><?= Language::get('value') ?>:
      <input type="text" name="wert" id="wert">
    </label>

    <label>
      <span><?= Language::get('search_mode_like') ?></span>
      <input type="checkbox" id="likeToggle">
    </label>
  </form>

  <h3 id="tabellenname">📘 <?= Language::get('table_label_not_selected') ?></h3>
  <div id="ausgabe"><?= Language::get('table_selection_prompt') ?></div>
</body>
</html>
