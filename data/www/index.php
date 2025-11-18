<?php
session_start();
require_once 'preveri_prijavo.php';
preveri_prijavo();

$searchTerm = trim($_GET['search'] ?? '');
$filterTip = $_GET['tip'] ?? 'vse';
$rokOd = $_GET['rok_od'] ?? '';
$rokDo = $_GET['rok_do'] ?? '';
$allowedTips = ['vse', 'osebna', 'skupinska'];

if (!in_array($filterTip, $allowedTips, true)) {
    $filterTip = 'vse';
}

$rokOd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rokOd) ? $rokOd : '';
$rokDo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rokDo) ? $rokDo : '';

$filterQuery = [];

if ($searchTerm !== '') {
    $filterQuery['search'] = $searchTerm;
}

if ($filterTip !== 'vse') {
    $filterQuery['tip'] = $filterTip;
}

if ($rokOd !== '') {
    $filterQuery['rok_od'] = $rokOd;
}

if ($rokDo !== '') {
    $filterQuery['rok_do'] = $rokDo;
}

$filterQueryString = http_build_query($filterQuery);
$filterQueryOnly = $filterQueryString ? '?' . $filterQueryString : '';
$filterQueryAppend = $filterQueryString ? '&' . $filterQueryString : '';
$advancedFiltersOpen = $filterTip !== 'vse' || $rokOd !== '' || $rokDo !== '';

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
        $filterSql = '';
        $filterParams = [];

        if ($searchTerm !== '') {
            $filterSql .= " AND n.naslov LIKE ?";
            $filterParams[] = '%' . $searchTerm . '%';
        }

        if ($filterTip === 'osebna') {
            $filterSql .= " AND dn.skupina_id IS NULL";
        } elseif ($filterTip === 'skupinska') {
            $filterSql .= " AND dn.skupina_id IS NOT NULL";
        }

        if ($rokOd !== '') {
            $filterSql .= " AND DATE(n.rok_izvedbe) >= ?";
            $filterParams[] = $rokOd;
        }

        if ($rokDo !== '') {
            $filterSql .= " AND DATE(n.rok_izvedbe) <= ?";
            $filterParams[] = $rokDo;
        }
    
    // Dodajanje nove naloge
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nalogo'])) {
        $naslov = trim($_POST['naslov']);
        $opis = trim($_POST['opis']);
        $rok = $_POST['rok_izvedbe'];
        $status = $_POST['status'] ?? 'neopravljeno';
        
        if (!empty($naslov)) {
            // Vstavi nalogo
            $stmt = $pdo->prepare("INSERT INTO Naloga (naslov, opis, rok_izvedbe, datum_ustvarjenja, status) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$naslov, $opis, $rok, $status]);
            
            // Pridobi ID novo ustvarjene naloge
            $naloga_id = $pdo->lastInsertId();
            
            // Dodeli nalogo trenutnemu uporabniku v DodelitevNaloge
            $stmt = $pdo->prepare("INSERT INTO DodelitevNaloge (datum_dodelitve, naloga_id, uporabnik_id, skupina_id) VALUES (NOW(), ?, ?, NULL)");
            $stmt->execute([$naloga_id, $_SESSION['uporabnik_id']]);
            
            $_SESSION['success_message'] = "Naloga je bila uspešno dodana!";
            header("Location: index.php");
            exit;
        }
    }
    
    // Brisanje naloge
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM Naloga WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php" . $filterQueryOnly);
        exit;
    }
    
    // Označevanje naloge kot opravljeno
    if (isset($_GET['complete'])) {
        $id = (int)$_GET['complete'];
        $stmt = $pdo->prepare("UPDATE Naloga SET status = 'opravljeno', datum_zakljucka = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php" . $filterQueryOnly);
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
        
        header("Location: index.php?view=" . $naloga_id . $filterQueryAppend);
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
    $sqlDanes = "
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
        {$filterSql}
        ORDER BY n.rok_izvedbe ASC
    ";
    $stmt = $pdo->prepare($sqlDanes);
    $stmt->execute(array_merge([$uporabnik_id, $danes, $uporabnik_id, $uporabnik_id], $filterParams));
    $naloge_danes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ostale naloge
    $sqlOstalo = "
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
        {$filterSql}
        ORDER BY n.rok_izvedbe ASC
    ";
    $stmt = $pdo->prepare($sqlOstalo);
    $stmt->execute(array_merge([$uporabnik_id, $danes, $uporabnik_id, $uporabnik_id], $filterParams));
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
        <form method="GET" class="task-filter-form" id="taskFilterForm">
            <div class="input-group task-search-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Išči naloge po nazivu">
                <button class="btn btn-outline-secondary filter-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="<?= $advancedFiltersOpen ? 'true' : 'false' ?>" aria-controls="advancedFilters" title="Napredni filtri">
                    <i class="bi bi-funnel"></i>
                </button>
                <button class="btn btn-primary" type="submit">
                    <span class="d-none d-sm-inline">Išči</span>
                    <i class="bi bi-search d-sm-none"></i>
                </button>
            </div>

            <div class="collapse <?= $advancedFiltersOpen ? 'show' : '' ?> mt-3" id="advancedFilters">
                <div class="advanced-filter-card">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="tipNaloge" class="form-label small text-uppercase text-muted">Tip naloge</label>
                            <select class="form-select" id="tipNaloge" name="tip">
                                <option value="vse" <?= $filterTip === 'vse' ? 'selected' : '' ?>>Vse naloge</option>
                                <option value="osebna" <?= $filterTip === 'osebna' ? 'selected' : '' ?>>Samostojne</option>
                                <option value="skupinska" <?= $filterTip === 'skupinska' ? 'selected' : '' ?>>Skupinske</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="rokOd" class="form-label small text-uppercase text-muted">Datum poteka od</label>
                            <input type="date" class="form-control" id="rokOd" name="rok_od" value="<?= htmlspecialchars($rokOd) ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="rokDo" class="form-label small text-uppercase text-muted">Datum poteka do</label>
                            <input type="date" class="form-control" id="rokDo" name="rok_do" value="<?= htmlspecialchars($rokDo) ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Uporabi filtre
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Ponastavi
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <!-- Naloge za danes -->
        <?php if (!empty($naloge_danes)): ?>
            <div class="task-section-title">
                Danes <span class="task-count"><?= count($naloge_danes) ?> <?= count($naloge_danes) == 1 ? 'naloga' : 'naloge' ?></span>
            </div>
            <?php foreach ($naloge_danes as $naloga): ?>
                <div class="task-item" onclick="window.location.href='?view=<?= $naloga['id'] ?><?= htmlspecialchars($filterQueryAppend, ENT_QUOTES) ?>'" style="cursor: pointer;" id="task-<?= $naloga['id'] ?>">
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
                        <button onclick="deleteTask(<?= $naloga['id'] ?>)" 
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
                <div class="task-item" onclick="window.location.href='?view=<?= $naloga['id'] ?><?= htmlspecialchars($filterQueryAppend, ENT_QUOTES) ?>'" style="cursor: pointer;" id="task-<?= $naloga['id'] ?>">
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
                        <button onclick="deleteTask(<?= $naloga['id'] ?>)" 
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
                <a href="#" data-bs-toggle="modal" data-bs-target="#addTaskModal" style="color: #dc4c3e; text-decoration: none;">Dodaj novo nalogo</a>
            </div>
        <?php endif; ?>

        <!-- Gumb za dodajanje naloge -->
        <button class="add-task-btn" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <span class="add-task-icon">
                <i class="bi bi-plus-lg"></i>
            </span>
            <span>Dodaj nalogo</span>
        </button>
    </div>

    <!-- Modal za dodajanje naloge -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Dodaj novo nalogo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php" id="addTaskForm">
                        <input type="hidden" name="dodaj_nalogo" value="1">
                        
                        <div class="mb-3">
                            <label for="naslov" class="form-label">Naslov naloge *</label>
                            <input type="text" class="form-control" id="naslov" name="naslov" required 
                                   placeholder="Vnesi naslov naloge">
                        </div>
                        
                        <div class="mb-3">
                            <label for="opis" class="form-label">Opis</label>
                            <textarea class="form-control" id="opis" name="opis" rows="3" 
                                      placeholder="Vnesi opis naloge (opcijsko)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rok_izvedbe" class="form-label">Rok izvedbe</label>
                            <input type="datetime-local" class="form-control" id="rok_izvedbe" name="rok_izvedbe">
                        </div>
                        
                        <input type="hidden" name="status" value="neopravljeno">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Prekliči
                    </button>
                    <button type="submit" form="addTaskForm" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Dodaj nalogo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal za prikaz naloge -->
    <?php if ($naloga_detail): ?>
    <div class="task-modal active" id="taskModal" onclick="if(event.target === this) window.location.href='index.php<?= htmlspecialchars($filterQueryOnly, ENT_QUOTES) ?>'">
        <div class="modal-content">
            <button class="modal-close" onclick="window.location.href='index.php<?= htmlspecialchars($filterQueryOnly, ENT_QUOTES) ?>'">&times;</button>
            
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
                    <div class="comments-list">
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
                        <p class="no-comments-msg" style="color: #808080; font-size: 14px; text-align: center; padding: 20px;">
                            Ni komentarjev. Bodite prvi, ki bo komentiral!
                        </p>
                    <?php endif; ?>
                    </div>                    
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

    <script src="api.js"></script>
    <script>
        const filterQueryAppend = <?= json_encode($filterQueryAppend) ?>;

        // AJAX funkcija za označevanje naloge kot opravljene
        async function completeTask(taskId) {
            const taskElement = document.getElementById('task-' + taskId);
            taskElement.classList.add('task-completing');
            
            try {
                const response = await apiPost('/naloge.php', {
                    action: 'opravi',
                    naloga_id: taskId
                });
                
                if (response.success) {
                    // Počakaj na animacijo in odstrani element
                    setTimeout(() => {
                        taskElement.remove();
                        showAlert(response.message, 'success');
                        
                        // Posodobi števce
                        updateTaskCounts();
                    }, 400);
                } else {
                    taskElement.classList.remove('task-completing');
                    showAlert(response.message, 'error');
                }
            } catch (error) {
                taskElement.classList.remove('task-completing');
                showAlert('Napaka: ' + error.message, 'error');
            }
        }
        
        // Funkcija za posodabljanje števcev nalog
        function updateTaskCounts() {
            const allTasks = document.querySelectorAll('.task-item');
            if (allTasks.length === 0) {
                // Če ni več nalog, prikaži prazen state
                const container = document.querySelector('.task-container');
                const addButton = document.querySelector('.add-task-btn');
                const sections = container.querySelectorAll('.task-section-title, .task-item');
                sections.forEach(s => s.remove());
                
                const emptyState = document.createElement('div');
                emptyState.style.cssText = 'text-align: center; padding: 60px 20px; color: #808080;';
                emptyState.innerHTML = `
                    <i class="bi bi-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
                    <p>Nimate nalog za danes!</p>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#addTaskModal" style="color: #dc4c3e; text-decoration: none;">Dodaj novo nalogo</a>
                `;
                container.insertBefore(emptyState, addButton);
            }
        }
        
        // AJAX funkcija za dodajanje komentarja
        const commentForm = document.querySelector('.comment-form form');
        if (commentForm) {
            commentForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const textarea = this.querySelector('textarea[name="besedilo"]');
                const submitButton = this.querySelector('button[type="submit"]');
                const nalogoId = formData.get('naloga_id');
                
                if (!textarea.value.trim()) {
                    showAlert('Komentar ne sme biti prazen.', 'warning');
                    return;
                }
                
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Objavljam...';
                
                try {
                    const response = await apiPost('/komentarji.php', formData);
                    
                    if (response.success) {
                        showAlert(response.message, 'success');
                        
                        // Dodaj komentar v DOM
                        addCommentToDOM(response.komentar);
                        
                        // Počisti obrazec
                        textarea.value = '';
                        
                        // Posodobi števec komentarjev
                        updateCommentCount();
                    } else {
                        showAlert(response.message, 'error');
                    }
                } catch (error) {
                    showAlert('Napaka: ' + error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            });
        }
        
        // Funkcija za dodajanje komentarja v DOM
        function addCommentToDOM(komentar) {
            const commentsList = document.querySelector('.comments-list');
            if (!commentsList) return;
            
            // Odstrani "Ni komentarjev" sporočilo, če obstaja
            const noCommentsMsg = commentsList.querySelector('.no-comments-msg');
            if (noCommentsMsg) {
                noCommentsMsg.remove();
            }
            
            // Ustvari nov komentar element
            const commentHTML = `
                <div class="comment-item">
                    <div class="comment-header">
                        <div class="comment-avatar">
                            ${komentar.uporabnisko_ime.charAt(0).toUpperCase()}
                        </div>
                        <span class="comment-author">${escapeHtml(komentar.uporabnisko_ime)}</span>
                        <span class="comment-date">${formatDate(komentar.datum_vnosa)}</span>
                    </div>
                    <div class="comment-text">${escapeHtml(komentar.besedilo).replace(/\n/g, '<br>')}</div>
                </div>
            `;
            
            // Dodaj komentar na konec seznama
            commentsList.insertAdjacentHTML('beforeend', commentHTML);
        }
        
        // Funkcija za posodabljanje števca komentarjev
        function updateCommentCount() {
            const countSpan = document.querySelector('.comments-title .task-count');
            if (countSpan) {
                const currentCount = parseInt(countSpan.textContent) || 0;
                countSpan.textContent = currentCount + 1;
            }
        }
        
        // AJAX funkcija za dodajanje nove naloge
        document.getElementById('addTaskForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.set('action', 'dodaj');
            
            // Gumb je izven forme, zato uporabimo form atribut
            const submitButton = document.querySelector('button[form="addTaskForm"][type="submit"]');
            if (!submitButton) {
                console.error('Submit gumb ni najden');
                return;
            }
            
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Dodajam...';
            
            try {
                const response = await apiPost('/naloge.php', formData);
                
                if (response.success) {
                    showAlert(response.message, 'success');
                    
                    // Zapri modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Resetiraj obrazec
                    this.reset();
                    
                    // Dodaj novo nalogo v DOM
                    addTaskToDOM(response.naloga);
                } else {
                    showAlert(response.message, 'error');
                }
            } catch (error) {
                showAlert('Napaka: ' + error.message, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
        
        // Funkcija za dodajanje naloge v DOM
        function addTaskToDOM(naloga) {
            // Preveri, če je datum za danes ali prihajajoč
            const rokDate = naloga.rok_izvedbe ? new Date(naloga.rok_izvedbe) : null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let isToday = false;
            if (rokDate) {
                const nalDateOnly = new Date(rokDate);
                nalDateOnly.setHours(0, 0, 0, 0);
                isToday = nalDateOnly.getTime() === today.getTime();
            }
            
            // Odstrani prazen state, če obstaja
            const emptyState = document.querySelector('.task-container > div[style*="text-align: center"]');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Ustvari HTML element naloge
            const taskHTML = `
                <div class="task-item" onclick="window.location.href='?view=${naloga.id}${filterQueryAppend}'" style="cursor: pointer;" id="task-${naloga.id}">
                    <div class="task-checkbox" onclick="event.stopPropagation(); completeTask(${naloga.id});"></div>
                    <div class="task-content">
                        <div class="task-title">
                            ${escapeHtml(naloga.naslov)}
                            ${naloga.tip_naloge !== 'osebna' && naloga.ime_skupine ? `
                                <span class="badge ms-2" style="font-size: 0.7em; background-color: ${naloga.barva_skupine || '#17a2b8'}; color: white;">
                                    <i class="bi bi-people"></i> ${escapeHtml(naloga.ime_skupine)}
                                </span>
                            ` : ''}
                        </div>
                        <div class="task-description">
                            ${escapeHtml(naloga.opis)}
                            ${naloga.rok_izvedbe ? '· ' + formatDateOnly(naloga.rok_izvedbe) : ''}
                        </div>
                    </div>
                    <div class="task-actions" onclick="event.stopPropagation();">
                        <button onclick="deleteTask(${naloga.id})" class="task-action-btn delete" title="Izbriši">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Najdi pravilno sekcijo
            const container = document.querySelector('.task-container');
            let targetSection;
            
            if (isToday) {
                targetSection = Array.from(container.querySelectorAll('.task-section-title')).find(t => t.textContent.includes('Danes'));
                if (!targetSection) {
                    // Ustvari sekcijo "Danes"
                    const addButton = document.querySelector('.add-task-btn');
                    const sectionTitle = document.createElement('div');
                    sectionTitle.className = 'task-section-title';
                    sectionTitle.innerHTML = 'Danes <span class="task-count">1 naloga</span>';
                    container.insertBefore(sectionTitle, addButton);
                    targetSection = sectionTitle;
                }
            } else {
                targetSection = Array.from(container.querySelectorAll('.task-section-title')).find(t => t.textContent.includes('Prihajajoče'));
                if (!targetSection) {
                    // Ustvari sekcijo "Prihajajoče"
                    const addButton = document.querySelector('.add-task-btn');
                    const sectionTitle = document.createElement('div');
                    sectionTitle.className = 'task-section-title';
                    sectionTitle.innerHTML = 'Prihajajoče <span class="task-count">1 naloga</span>';
                    container.insertBefore(sectionTitle, addButton);
                    targetSection = sectionTitle;
                }
            }
            
            // Dodaj nalogo za naslovom sekcije
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = taskHTML.trim();
            const taskElement = tempDiv.firstElementChild;
            
            if (taskElement) {
                targetSection.insertAdjacentElement('afterend', taskElement);
                // Posodobi števec
                updateSectionCount(targetSection);
            } else {
                console.error('Napaka pri ustvarjanju elementa naloge');
            }
        }
        
        // AJAX funkcija za brisanje naloge
        async function deleteTask(taskId) {
            if (!confirm('Ste prepričani, da želite izbrisati to nalogo?')) {
                return;
            }
            
            const taskElement = document.getElementById('task-' + taskId);
            taskElement.style.opacity = '0.5';
            
            try {
                const response = await apiDelete('/naloge.php', {
                    naloga_id: taskId
                });
                
                if (response.success) {
                    taskElement.remove();
                    showAlert(response.message, 'success');
                    updateTaskCounts();
                } else {
                    taskElement.style.opacity = '1';
                    showAlert(response.message, 'error');
                }
            } catch (error) {
                taskElement.style.opacity = '1';
                showAlert('Napaka: ' + error.message, 'error');
            }
        }
        
        // Helper funkcija za posodabljanje števca v sekciji
        function updateSectionCount(sectionTitle) {
            if (!sectionTitle) {
                console.error('updateSectionCount: sectionTitle je null');
                return;
            }
            
            let count = 0;
            let nextEl = sectionTitle.nextElementSibling;
            while (nextEl && nextEl.classList && nextEl.classList.contains('task-item')) {
                count++;
                nextEl = nextEl.nextElementSibling;
            }
            const countSpan = sectionTitle.querySelector('.task-count');
            if (countSpan) {
                countSpan.textContent = `${count} ${count === 1 ? 'naloga' : 'naloge'}`;
            }
        }
        
        // Helper funkcija za escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>