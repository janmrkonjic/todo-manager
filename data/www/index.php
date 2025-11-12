<?php
session_start();
require_once 'preveri_prijavo.php';
preveri_prijavo();

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Brisanje naloge
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM Naloga WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    }
    
    // Označevanje naloge kot opravljeno
    if (isset($_GET['complete'])) {
        $id = (int)$_GET['complete'];
        $stmt = $pdo->prepare("UPDATE Naloga SET status = 'opravljeno', datum_zakljucka = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    }
    
    // Dodajanje komentarja
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_komentar'])) {
        $naloga_id = (int)$_POST['naloga_id'];
        $besedilo = trim($_POST['besedilo']);
        
        if (!empty($besedilo)) {
            $stmt = $pdo->prepare("
                INSERT INTO Komentar (naloga_id, uporabnik_id, besedilo, datum_vnosa) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$naloga_id, $_SESSION['uporabnik_id'], $besedilo]);
        }
        
        header("Location: index.php?view=" . $naloga_id);
        exit;
    }
    
    // Pridobi podrobnosti naloge za modal
    $naloga_detail = null;
    $komentarji = [];
    if (isset($_GET['view'])) {
        $view_id = (int)$_GET['view'];
        $stmt = $pdo->prepare("SELECT * FROM Naloga WHERE id = ?");
        $stmt->execute([$view_id]);
        $naloga_detail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($naloga_detail) {
            // Pridobi komentarje za to nalogo
            $stmt = $pdo->prepare("
                SELECT k.*, u.uporabnisko_ime 
                FROM Komentar k 
                JOIN Uporabnik u ON k.uporabnik_id = u.id 
                WHERE k.naloga_id = ? 
                ORDER BY k.datum_vnosa ASC
            ");
            $stmt->execute([$view_id]);
            $komentarji = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Razdeli naloge po datumu roka
    $danes = date('Y-m-d');
    $uporabnik_id = $_SESSION['uporabnik_id'];
    
    // Uporabniki vidijo svoje osebne naloge in naloge iz skupin, kjer so člani
    // Naloge za danes
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
        WHERE DATE(n.rok_izvedbe) = ? 
        AND n.status = 'neopravljeno'
        AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
        ORDER BY n.rok_izvedbe ASC
    ");
    $stmt->execute([$uporabnik_id, $danes, $uporabnik_id, $uporabnik_id]);
    $naloge_danes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ostale naloge
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
        WHERE (DATE(n.rok_izvedbe) > ? OR n.rok_izvedbe IS NULL)
        AND n.status = 'neopravljeno'
        AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
        ORDER BY n.rok_izvedbe ASC
    ");
    $stmt->execute([$uporabnik_id, $danes, $uporabnik_id, $uporabnik_id]);
    $naloge_ostalo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Domov - Todo Manager</title>
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
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house-door"></i> Domov
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="urejanje.php">
                            <i class="bi bi-pencil-square"></i> Upravljanje nalog
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="opravljene.php">
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
        <!-- Naloge za danes -->
        <?php if (!empty($naloge_danes)): ?>
            <div class="task-section-title">
                Danes <span class="task-count"><?= count($naloge_danes) ?> <?= count($naloge_danes) == 1 ? 'naloga' : 'naloge' ?></span>
            </div>
            <?php foreach ($naloge_danes as $naloga): ?>
                <div class="task-item" onclick="window.location.href='?view=<?= $naloga['id'] ?>'" style="cursor: pointer;" id="task-<?= $naloga['id'] ?>">
                    <div class="task-checkbox" onclick="event.stopPropagation(); completeTask(<?= $naloga['id'] ?>);"></div>
                    <div class="task-content">
                        <div class="task-title">
                            <?= htmlspecialchars($naloga['naslov']) ?>
                            <?php if ($naloga['tip_naloge'] != 'osebna' && $naloga['ime_skupine']): ?>
                                <span class="badge ms-2" style="font-size: 0.7em; background-color: <?= htmlspecialchars($naloga['barva_skupine'] ?? '#17a2b8') ?>; color: white;">
                                    <i class="bi bi-people"></i> <?= htmlspecialchars($naloga['ime_skupine']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-description"><?= htmlspecialchars($naloga['opis']) ?></div>
                    </div>
                    <div class="task-actions" onclick="event.stopPropagation();">
                        <a href="urejanje.php?edit=<?= $naloga['id'] ?>" class="task-action-btn" title="Uredi">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button onclick="if(confirm('Ste prepričani?')) window.location.href='?delete=<?= $naloga['id'] ?>'" 
                                class="task-action-btn delete" title="Izbriši">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Vse ostale naloge -->
        <?php if (!empty($naloge_ostalo)): ?>
            <div class="task-section-title">
                Prihajajoče <span class="task-count"><?= count($naloge_ostalo) ?> <?= count($naloge_ostalo) == 1 ? 'naloga' : 'naloge' ?></span>
            </div>
            <?php foreach ($naloge_ostalo as $naloga): ?>
                <div class="task-item" onclick="window.location.href='?view=<?= $naloga['id'] ?>'" style="cursor: pointer;" id="task-<?= $naloga['id'] ?>">
                    <div class="task-checkbox" onclick="event.stopPropagation(); completeTask(<?= $naloga['id'] ?>);"></div>
                    <div class="task-content">
                        <div class="task-title">
                            <?= htmlspecialchars($naloga['naslov']) ?>
                            <?php if ($naloga['tip_naloge'] != 'osebna' && $naloga['ime_skupine']): ?>
                                <span class="badge ms-2" style="font-size: 0.7em; background-color: <?= htmlspecialchars($naloga['barva_skupine'] ?? '#17a2b8') ?>; color: white;">
                                    <i class="bi bi-people"></i> <?= htmlspecialchars($naloga['ime_skupine']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-description">
                            <?= htmlspecialchars($naloga['opis']) ?>
                            <?php if ($naloga['rok_izvedbe']): ?>
                                · <?= date('d. M', strtotime($naloga['rok_izvedbe'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="task-actions" onclick="event.stopPropagation();">
                        <a href="urejanje.php?edit=<?= $naloga['id'] ?>" class="task-action-btn" title="Uredi">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button onclick="if(confirm('Ste prepričani?')) window.location.href='?delete=<?= $naloga['id'] ?>'" 
                                class="task-action-btn delete" title="Izbriši">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($naloge_danes) && empty($naloge_ostalo)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #808080;">
                <i class="bi bi-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
                <p>Nimate nalog za danes!</p>
                <a href="urejanje.php" style="color: #dc4c3e; text-decoration: none;">Dodaj novo nalogo</a>
            </div>
        <?php endif; ?>

        <!-- Gumb za dodajanje naloge -->
        <a href="urejanje.php" style="text-decoration: none;">
            <button class="add-task-btn">
                <span class="add-task-icon">
                    <i class="bi bi-plus-lg"></i>
                </span>
                <span>Dodaj nalogo</span>
            </button>
        </a>
    </div>

    <!-- Modal za prikaz naloge -->
    <?php if ($naloga_detail): ?>
    <div class="task-modal active" id="taskModal" onclick="if(event.target === this) window.location.href='index.php'">
        <div class="modal-content">
            <button class="modal-close" onclick="window.location.href='index.php'">&times;</button>
            
            <div class="modal-header">
                <h2 class="modal-task-title"><?= htmlspecialchars($naloga_detail['naslov']) ?></h2>
                <div class="modal-task-meta">
                    <?php if ($naloga_detail['rok_izvedbe']): ?>
                        <span><i class="bi bi-calendar"></i> <?= date('d. M Y, H:i', strtotime($naloga_detail['rok_izvedbe'])) ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-circle-fill" style="font-size: 8px; color: <?= $naloga_detail['status'] === 'opravljeno' ? '#22c55e' : '#fbbf24' ?>;"></i> <?= ucfirst($naloga_detail['status']) ?></span>
                </div>
            </div>
            
            <div class="modal-body">
                <?php if (!empty($naloga_detail['opis'])): ?>
                    <p class="modal-task-description"><?= nl2br(htmlspecialchars($naloga_detail['opis'])) ?></p>
                <?php endif; ?>
                
                <div class="comments-section">
                    <h3 class="comments-title">
                        <i class="bi bi-chat-left-text"></i> Komentarji 
                        <span class="task-count"><?= count($komentarji) ?></span>
                    </h3>
                    
                    <!-- Obstoječi komentarji -->
                    <?php if (!empty($komentarji)): ?>
                        <?php foreach ($komentarji as $komentar): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <div class="comment-avatar">
                                        <?= strtoupper(substr($komentar['uporabnisko_ime'], 0, 1)) ?>
                                    </div>
                                    <span class="comment-author"><?= htmlspecialchars($komentar['uporabnisko_ime']) ?></span>
                                    <span class="comment-date">
                                        <?php 
                                        $datum = new DateTime($komentar['datum_vnosa']);
                                        echo $datum->format('d. M Y, H:i');
                                        ?>
                                    </span>
                                </div>
                                <div class="comment-text"><?= nl2br(htmlspecialchars($komentar['besedilo'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #808080; font-size: 14px; text-align: center; padding: 20px;">
                            Ni komentarjev. Bodite prvi, ki bo komentiral!
                        </p>
                    <?php endif; ?>
                    
                    <!-- Formular za dodajanje komentarja -->
                    <div class="comment-form">
                        <form method="POST">
                            <input type="hidden" name="naloga_id" value="<?= $naloga_detail['id'] ?>">
                            <input type="hidden" name="dodaj_komentar" value="1">
                            <textarea name="besedilo" class="comment-input" 
                                      placeholder="Dodaj komentar..." required></textarea>
                            <button type="submit" class="comment-submit">
                                <i class="bi bi-send"></i> Objavi komentar
                            </button>
                        </form>
                    </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <style>
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .task-completing {
            animation: slideOutRight 0.4s ease-out forwards;
        }
        
        .task-checkbox {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .task-checkbox:hover {
            background-color: #e7f1ff;
            border-color: #0d6efd;
        }
    </style>

    <script>
        function completeTask(taskId) {
            const taskElement = document.getElementById('task-' + taskId);
            taskElement.classList.add('task-completing');
            
            setTimeout(() => {
                window.location.href = '?complete=' + taskId;
            }, 400);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>