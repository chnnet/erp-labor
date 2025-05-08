<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
Database::initialize();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('dashboard_title') ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f9f9f9; }
    .container { padding: 2em; max-width: 1200px; margin: auto; }
    .kpi-boxes {
      display: flex;
      justify-content: space-around;
      margin-bottom: 2em;
      gap: 1em;
      flex-wrap: wrap;
    }
    .kpi {
      background: #fff;
      padding: 1em 2em;
      border-radius: 8px;
      box-shadow: 0 0 6px rgba(0,0,0,0.1);
      flex: 1 1 200px;
      text-align: center;
    }
    .kpi h3 {
      margin: 0;
      font-size: 1.2em;
      color: #666;
    }
    .kpi p {
      font-size: 1.8em;
      font-weight: bold;
      color: #2c3e50;
    }
    .chart-container {
      display: flex;
      gap: 2em;
      flex-wrap: wrap;
      justify-content: center;
    }
    .chart {
      width: 100%;
      max-width: 550px;
      background: white;
      padding: 1em;
      border-radius: 8px;
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
    h2 {
      text-align: center;
      margin-bottom: 1em;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <h2>📊 <?= Language::get('dashboard_heading', ['date' => date("d.m.Y")]) ?></h2>

    <div class="kpi-boxes" id="kpiBoxen">
      <div class="kpi">
        <h3><?= Language::get('kpi_creditors') ?></h3>
        <p id="kpiKreditoren">– €</p>
      </div>
      <div class="kpi">
        <h3><?= Language::get('kpi_debtors') ?></h3>
        <p id="kpiDebitoren">– €</p>
      </div>
    </div>

    <div class="chart-container">
      <div class="chart">
        <canvas id="kreditorChart"></canvas>
      </div>
      <div class="chart">
        <canvas id="debitorChart"></canvas>
      </div>
    </div>
  </div>

  <script>
    async function fetchChartData(endpoint) {
      try {
        const res = await fetch(endpoint);
        if (!res.ok) throw new Error("Ladefehler");
        return await res.json();
      } catch (err) {
        console.error("API Fehler:", err);
        return { labels: [], data: [] };
      }
    }

    async function renderCharts() {
      const kreditoren = await fetchChartData('api/creditors.php');
      const debitoren = await fetchChartData('api/debtors.php');

      // KPI anzeigen
      const sumK = kreditoren.data.reduce((a, b) => a + b, 0);
      const sumD = debitoren.data.reduce((a, b) => a + b, 0);
      document.getElementById('kpiKreditoren').innerText = sumK.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
      document.getElementById('kpiDebitoren').innerText = sumD.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });

      // Kreditoren-Chart
      new Chart(document.getElementById('kreditorChart'), {
        type: 'bar',
        data: {
          labels: kreditoren.labels,
          datasets: [{
            label: '<?= Language::get('kpi_creditors') ?> (€)',
            data: kreditoren.data,
            backgroundColor: 'rgba(231, 76, 60, 0.6)'
          }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
      });

      // Debitoren-Chart
      new Chart(document.getElementById('debitorChart'), {
        type: 'bar',
        data: {
          labels: debitoren.labels,
          datasets: [{
            label: '<?= Language::get('kpi_debtors') ?> (€)',
            data: debitoren.data,
            backgroundColor: 'rgba(52, 152, 219, 0.6)'
          }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
      });
    }

    renderCharts();
  </script>
</body>
</html>
