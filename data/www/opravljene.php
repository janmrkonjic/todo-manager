<?php
session_start();
require_once 'preveri_prijavo.php';
preveri_prijavo();

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $uporabnik_id = $_SESSION['uporabnik_id'];
    
    // Pridobi opravljene naloge uporabnika (osebne in skupinske)
    $stmt = $pdo->prepare("
        SELECT DISTINCT n.*, 
               CASE 
                   WHEN dn.uporabnik_id IS NOT NULL THEN 'osebna'
                   WHEN dn.skupina_id IS NOT NULL THEN s.ime
                   ELSE 'ostalo'
               END as tip_naloge,
               s.ime as ime_skupine,
               s.barva as barva_skupine
        FROM Naloga n
        INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
        LEFT JOIN Skupina s ON dn.skupina_id = s.id
        LEFT JOIN ClaniSkupine cs ON s.id = cs.skupina_id AND cs.uporabnik_id = ?
        WHERE n.status = 'opravljeno'
        AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
        ORDER BY n.datum_zakljucka DESC
    ");
    $stmt->execute([$uporabnik_id, $uporabnik_id, $uporabnik_id]);
    $opravljene_naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    http_response_code(500);
    die("DB napaka: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opravljene naloge - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check2-circle"></i> Todo Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house-door"></i> Domov
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="urejanje.php">
                            <i class="bi bi-pencil-square"></i> Upravljanje nalog
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="opravljene.php">
                            <i class="bi bi-check-circle"></i> Opravljene naloge
                        </a>
                    </li>
                    <?php if ($_SESSION['vloga_id'] != 1): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="skupine.php">
                            <i class="bi bi-people"></i> Moje skupine
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <i class="bi bi-person-circle" style="margin-right: 8px;"></i><?= htmlspecialchars($_SESSION['uporabnisko_ime']) ?>
                        </span>
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

    <div class="task-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-check-circle-fill text-success"></i> Opravljene naloge</h1>
        </div>

        <?php if (empty($opravljene_naloge)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <p class="text-muted mt-3" style="font-size: 1.2rem;">Še nimate opravljenih nalog.</p>
            </div>
        <?php else: ?>
            <div class="task-section-title">
                Vse opravljene <span class="task-count"><?= count($opravljene_naloge) ?> <?= count($opravljene_naloge) == 1 ? 'naloga' : 'naloge' ?></span>
            </div>
            
            <?php foreach ($opravljene_naloge as $naloga): ?>
                <div class="task-item completed-task">
                    <div class="task-checkbox completed">
                        <i class="bi bi-check text-success" style="font-size: 1rem; font-weight: bold;"></i>
                    </div>
                    <div class="task-content">
                        <div class="task-title" style="text-decoration: line-through; color: #808080;">
                            <?= htmlspecialchars($naloga['naslov']) ?>
                            <?php if ($naloga['tip_naloge'] != 'osebna' && $naloga['ime_skupine']): ?>
                                <span class="badge ms-2" style="font-size: 0.7em; background-color: <?= htmlspecialchars($naloga['barva_skupine'] ?? '#17a2b8') ?>; color: white;">
                                    <i class="bi bi-people"></i> <?= htmlspecialchars($naloga['ime_skupine']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-description" style="color: #a0a0a0;">
                            <?= htmlspecialchars($naloga['opis']) ?>
                        </div>
                        <div class="task-meta" style="font-size: 0.85rem; color: #999; margin-top: 8px;">
                            <i class="bi bi-check-circle"></i> Opravljeno: <?= date('d.m.Y H:i', strtotime($naloga['datum_zakljucka'])) ?>
                            <?php if ($naloga['rok_izvedbe']): ?>
                                | <i class="bi bi-calendar"></i> Rok: <?= date('d.m.Y H:i', strtotime($naloga['rok_izvedbe'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <style>
        .completed-task {
            opacity: 0.8;
            background-color: #f8f9fa;
        }
        
        .task-checkbox.completed {
            background-color: #d4edda;
            border-color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
