<?php
session_start();
require_once 'preveri_prijavo.php';
preveri_prijavo();
preveri_vlogo([1]);

// Pridobi profilno sliko uporabnika
try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->prepare('SELECT profilna_slika FROM Uporabnik WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['uporabnik_id']]);
    $uporabnik_slika = $stmt->fetchColumn();
} catch (PDOException $e) {
    $uporabnik_slika = null;
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistika - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="lazy-loader.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 2rem;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
        }
        .error-message {
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="administracija.php">
                <i class="bi bi-check2-circle"></i> Todo Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="administracija.php">
                            <i class="bi bi-gear"></i> Administracija
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uporabniki.php">
                            <i class="bi bi-people"></i> Uporabniki
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="statistika.php">
                            <i class="bi bi-bar-chart-line"></i> Statistika
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center" href="profil.php">
                            <?php if ($uporabnik_slika && file_exists('uploads/profilne/' . $uporabnik_slika)): ?>
                                <img src="uploads/profilne/<?= htmlspecialchars($uporabnik_slika) ?>" 
                                     alt="Profil" 
                                     class="rounded-circle me-2" 
                                     style="width: 32px; height: 32px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($_SESSION['uporabnisko_ime']) ?>
                            <span class="badge bg-light text-primary ms-2"><?= htmlspecialchars($_SESSION['vloga_naziv']) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="odjava.php">
                            <i class="bi bi-box-arrow-right"></i> Odjava
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-bar-chart-line"></i> Statistika in analitika</h1>
            <a href="izvoz_statistike.php" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Izvozi v Excel
            </a>
        </div>

        <div id="loadingSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Nalaganje...</span>
            </div>
        </div>

        <div id="errorMessage" class="error-message d-none">
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Napaka!</strong> Statistike ni bilo mogoče naložiti.
            </div>
        </div>

        <div id="statisticsContent" class="d-none">
            <!-- Splošne statistike -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="bi bi-list-check text-primary" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-2">Skupaj nalog</h5>
                            <p class="stats-value text-primary" id="statSkupajNalog">0</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-2">Opravljenih</h5>
                            <p class="stats-value text-success" id="statOpravljenihNalog">0</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-2">Aktivnih</h5>
                            <p class="stats-value text-warning" id="statAktivnihNalog">0</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-2">Uporabniki</h5>
                            <p class="stats-value text-info" id="statSkupajUporabnikov">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs za različne grafe -->
            <ul class="nav nav-tabs mb-4" id="chartsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">
                        <i class="bi bi-pie-chart"></i> Status nalog
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tip-tab" data-bs-toggle="tab" data-bs-target="#tip" type="button" role="tab">
                        <i class="bi bi-diagram-3"></i> Tipi nalog
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="roki-tab" data-bs-toggle="tab" data-bs-target="#roki" type="button" role="tab">
                        <i class="bi bi-calendar-week"></i> Roki
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="aktivnost-tab" data-bs-toggle="tab" data-bs-target="#aktivnost" type="button" role="tab">
                        <i class="bi bi-person-check"></i> Aktivnost
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="skupine-tab" data-bs-toggle="tab" data-bs-target="#skupine" type="button" role="tab">
                        <i class="bi bi-people-fill"></i> Skupine
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="chartsTabContent">
                <!-- Status nalog -->
                <div class="tab-pane fade show active" id="status" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-pie-chart"></i> Razmerje opravljenih nalog</h5>
                                    <div class="chart-container">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-activity"></i> Dnevna aktivnost (zadnjih 30 dni)</h5>
                                    <div class="chart-container">
                                        <canvas id="dnevnaAktivnostChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipi nalog -->
                <div class="tab-pane fade" id="tip" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-diagram-3"></i> Razdelitev nalog po tipih</h5>
                            <div class="chart-container">
                                <canvas id="tipChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roki -->
                <div class="tab-pane fade" id="roki" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-calendar-week"></i> Naloge po rokih (tedenski pregled)</h5>
                            <div class="chart-container">
                                <canvas id="rokiChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aktivnost uporabnikov -->
                <div class="tab-pane fade" id="aktivnost" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-person-check"></i> Top 10 najbolj aktivnih uporabnikov</h5>
                            <div class="chart-container">
                                <canvas id="aktivnostChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skupine -->
                <div class="tab-pane fade" id="skupine" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-people-fill"></i> Top 10 skupin po številu nalog</h5>
                            <div class="chart-container">
                                <canvas id="skupineChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let charts = {};

        async function loadStatistics() {
            try {
                const response = await fetch('api/statistika.php');
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Server error:', errorData);
                    throw new Error(errorData.error || errorData.message || 'Napaka pri pridobivanju podatkov');
                }

                const result = await response.json();
                
                if (!result.success) {
                    console.error('API error:', result);
                    throw new Error(result.error || result.message || 'Napaka pri pridobivanju podatkov');
                }

                displayStatistics(result.data);
                
            } catch (error) {
                console.error('Napaka:', error);
                document.getElementById('loadingSpinner').classList.add('d-none');
                document.getElementById('errorMessage').classList.remove('d-none');
                
                if (error.message) {
                    console.error('Error message:', error.message);
                }
            }
        }

        // Funkcija za prikaz statistike
        function displayStatistics(data) {
            // Skrij spinner, prikaži vsebino
            document.getElementById('loadingSpinner').classList.add('d-none');
            document.getElementById('statisticsContent').classList.remove('d-none');

            // Splošne statistike
            if (data.splosne) {
                document.getElementById('statSkupajNalog').textContent = data.splosne.skupaj_nalog || 0;
                document.getElementById('statOpravljenihNalog').textContent = data.splosne.opravljenih_nalog || 0;
                document.getElementById('statAktivnihNalog').textContent = data.splosne.aktivnih_nalog || 0;
                document.getElementById('statSkupajUporabnikov').textContent = data.splosne.skupaj_uporabnikov || 0;
            }

            // Graf: Status nalog
            createStatusChart(data.nalog_po_statusu);

            // Graf: Tipi nalog
            createTipChart(data.nalog_po_tipu);

            // Graf: Roki
            createRokiChart(data.nalog_po_rokih);

            // Graf: Aktivnost uporabnikov
            createAktivnostChart(data.aktivnost_uporabnikov);

            // Graf: Skupine
            createSkupineChart(data.nalog_po_skupinah);

            // Graf: Dnevna aktivnost
            createDnevnaAktivnostChart(data.dnevna_aktivnost);
        }

        // Graf: Status nalog (pie chart)
        function createStatusChart(data) {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            if (charts.statusChart) {
                charts.statusChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o statusu nalog');
                return;
            }

            charts.statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => item.status),
                    datasets: [{
                        data: data.map(item => item.stevilo),
                        backgroundColor: ['#28a745', '#ffc107'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graf: Tipi nalog (bar chart)
        function createTipChart(data) {
            const ctx = document.getElementById('tipChart').getContext('2d');
            
            if (charts.tipChart) {
                charts.tipChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o tipih nalog');
                return;
            }

            charts.tipChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.tip),
                    datasets: [{
                        label: 'Število nalog',
                        data: data.map(item => item.stevilo),
                        backgroundColor: ['#007bff', '#17a2b8', '#6c757d'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Graf: Roki (line chart)
        function createRokiChart(data) {
            const ctx = document.getElementById('rokiChart').getContext('2d');
            
            if (charts.rokiChart) {
                charts.rokiChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o rokih nalog');
                return;
            }

            const labels = data.map(item => {
                const datum = new Date(item.teden_zacetek);
                return datum.toLocaleDateString('sl-SI', { day: 'numeric', month: 'short' });
            });

            charts.rokiChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Skupaj nalog',
                            data: data.map(item => item.stevilo),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Opravljenih',
                            data: data.map(item => item.opravljenih),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Graf: Aktivnost uporabnikov (horizontal bar chart)
        function createAktivnostChart(data) {
            const ctx = document.getElementById('aktivnostChart').getContext('2d');
            
            if (charts.aktivnostChart) {
                charts.aktivnostChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o aktivnosti uporabnikov');
                return;
            }

            charts.aktivnostChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.uporabnisko_ime),
                    datasets: [
                        {
                            label: 'Opravljene naloge',
                            data: data.map(item => item.stevilo_opravljenih),
                            backgroundColor: '#28a745'
                        },
                        {
                            label: 'Komentarji',
                            data: data.map(item => item.stevilo_komentarjev),
                            backgroundColor: '#17a2b8'
                        },
                        {
                            label: 'Aktivne naloge',
                            data: data.map(item => item.stevilo_aktivnih),
                            backgroundColor: '#ffc107'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            stacked: false
                        },
                        y: {
                            stacked: false
                        }
                    }
                }
            });
        }

        // Graf: Skupine (bar chart)
        function createSkupineChart(data) {
            const ctx = document.getElementById('skupineChart').getContext('2d');
            
            if (charts.skupineChart) {
                charts.skupineChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o skupinah');
                return;
            }

            const backgroundColors = data.map(item => item.barva || '#6c757d');

            charts.skupineChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.naziv),
                    datasets: [
                        {
                            label: 'Opravljene naloge',
                            data: data.map(item => item.opravljenih),
                            backgroundColor: '#28a745'
                        },
                        {
                            label: 'Aktivne naloge',
                            data: data.map(item => item.aktivnih),
                            backgroundColor: '#ffc107'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Graf: Dnevna aktivnost (line chart)
        function createDnevnaAktivnostChart(data) {
            const ctx = document.getElementById('dnevnaAktivnostChart').getContext('2d');
            
            if (charts.dnevnaAktivnostChart) {
                charts.dnevnaAktivnostChart.destroy();
            }

            if (!data || data.length === 0) {
                displayNoDataMessage(ctx, 'Ni podatkov o dnevni aktivnosti');
                return;
            }

            // Polnimo manjkajoče datume z 0
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);

            const dateMap = {};
            data.forEach(item => {
                dateMap[item.datum] = parseInt(item.stevilo_ustvarjenih);
            });

            const labels = [];
            const values = [];
            
            for (let d = new Date(thirtyDaysAgo); d <= today; d.setDate(d.getDate() + 1)) {
                const dateStr = d.toISOString().split('T')[0];
                labels.push(new Date(d).toLocaleDateString('sl-SI', { day: 'numeric', month: 'short' }));
                values.push(dateMap[dateStr] || 0);
            }

            charts.dnevnaAktivnostChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Ustvarjene naloge',
                        data: values,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Funkcija za prikaz sporočila "Ni podatkov"
        function displayNoDataMessage(ctx, message) {
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '16px Arial';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(message, ctx.canvas.width / 2, ctx.canvas.height / 2);
            ctx.restore();
        }

        // Nalaganje podatkov ob zagonu strani
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
        });
    </script>
</body>
</html>
