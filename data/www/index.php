<?php
session_start();
require_once 'includes/functions.php';
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
    require_once 'config/db.php';
    
    $uporabnik_slika = get_user_profile_image($pdo, $_SESSION['uporabnik_id']);
    
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

$pageTitle = 'Domov - Todo Manager';
$activePage = 'index.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>

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
                        <button type="button" class="btn btn-outline-danger" id="resetPreferencesBtn" title="Počisti vse shranjene nastavitve">
                            <i class="bi bi-trash"></i> Ponastavi pogled
                        </button>
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
                <div class="weather-section" style="padding: 12px 0; text-align: center;">
                    <div class="weather-compact" id="weatherCompact" onclick="toggleWeatherDetails()">
                        <span class="weather-icon">☁️</span>
                        <span class="weather-temp">--°C</span>
                        <small class="weather-city">Maribor</small>
                    </div>
                </div>
                <hr style="color: #bbbbbbff">
                
                <div class="weather-details" id="weatherDetails" style="display: none;"></div>

                <?php if (!empty($naloga_detail['opis'])): ?>
                    <p class="modal-task-description" style="margin-bottom: 24px;"><?= nl2br(htmlspecialchars($naloga_detail['opis'])) ?></p>
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

        // === LocalStorage za shranjevanje filtrov ===
        
        // Pri nalaganju strani preveri, če imamo shranjene filtre
        document.addEventListener('DOMContentLoaded', function() {
            // Samo če ni URL parametrov, naloži shranjene preference
            const urlParams = new URLSearchParams(window.location.search);
            const hasUrlFilters = urlParams.has('search') || urlParams.has('tip') || 
                                  urlParams.has('rok_od') || urlParams.has('rok_do');
            
            if (!hasUrlFilters) {
                const savedFilters = getTaskFilters();
                if (savedFilters) {
                    // Naloži shranjene filtre v obrazec
                    if (savedFilters.search) {
                        document.querySelector('input[name="search"]').value = savedFilters.search;
                    }
                    if (savedFilters.tip) {
                        document.querySelector('select[name="tip"]').value = savedFilters.tip;
                    }
                    if (savedFilters.rok_od) {
                        document.querySelector('input[name="rok_od"]').value = savedFilters.rok_od;
                    }
                    if (savedFilters.rok_do) {
                        document.querySelector('input[name="rok_do"]').value = savedFilters.rok_do;
                    }
                    
                    // Odpri napredne filtre, če so bili prej odprti
                    if (savedFilters.advancedOpen) {
                        const advancedFilters = document.getElementById('advancedFilters');
                        const filterToggle = document.querySelector('.filter-toggle');
                        if (advancedFilters && filterToggle) {
                            advancedFilters.classList.add('show');
                            filterToggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                }
            }
            
            // Preveri shranjene preference sortiranja (za prihodnjo implementacijo točke 4)
            const savedSorting = getSortPreferences();
            if (savedSorting) {
                // Označimo izbran stolpec za sortiranje, ko bo implementiran UI
                console.log('Shranjene preference sortiranja:', savedSorting);
            }
            
            // Shrani trenutne filtre pri oddaji obrazca
            const filterForm = document.getElementById('taskFilterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function() {
                    const filters = {
                        search: document.querySelector('input[name="search"]').value,
                        tip: document.querySelector('select[name="tip"]').value,
                        rok_od: document.querySelector('input[name="rok_od"]').value,
                        rok_do: document.querySelector('input[name="rok_do"]').value,
                        advancedOpen: document.getElementById('advancedFilters').classList.contains('show')
                    };
                    saveTaskFilters(filters);
                });
            }
            
            // Gumb za ponastavitev vseh preferenc
            const resetBtn = document.getElementById('resetPreferencesBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', async function() {
                    const confirmed = await showConfirm(
                        'Ali ste prepričani, da želite počistiti vse shranjene nastavitve?',
                        'Ponastavitev nastavitev'
                    );
                    
                    if (confirmed) {
                        clearUserPreferences();
                        showAlert('Nastavitve so bile ponastavljene.', 'success');
                        // Osvežitev na čisto stran
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 1000);
                    }
                });
            }
            
            // Sledenje spremembam stanja naprednih filtrov
            const advancedFilters = document.getElementById('advancedFilters');
            if (advancedFilters) {
                advancedFilters.addEventListener('shown.bs.collapse', function() {
                    const savedFilters = getTaskFilters() || {};
                    savedFilters.advancedOpen = true;
                    saveTaskFilters(savedFilters);
                });
                
                advancedFilters.addEventListener('hidden.bs.collapse', function() {
                    const savedFilters = getTaskFilters() || {};
                    savedFilters.advancedOpen = false;
                    saveTaskFilters(savedFilters);
                });
            }
        });

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
            const confirmed = await showConfirm(
                'Ste prepričani, da želite izbrisati to nalogo?',
                'Brisanje naloge'
            );
            
            if (!confirmed) {
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
        
        // === Vreme funkcionalnost ===
        
        // Funkcija za prikazovanje/skrivanje podrobnosti o vremenu
        function toggleWeatherDetails() {
            const detailsElement = document.getElementById('weatherDetails');
            const compactElement = document.getElementById('weatherCompact');
            if (!detailsElement || !compactElement) return;
            if (detailsElement.style.display === 'none') {
                detailsElement.style.display = 'block';
                compactElement.classList.add('active');
            } else {
                detailsElement.style.display = 'none';
                compactElement.classList.remove('active');
            }
        }

        // Funkcija za pridobivanje vremena
        async function loadWeather(city = 'Maribor') {
            const compactElement = document.getElementById('weatherCompact');
            const detailsElement = document.getElementById('weatherDetails');
            if (!compactElement || !detailsElement) return;
            try {
                const response = await fetch(`api/weather.php?city=${encodeURIComponent(city)}`);
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Napaka pri pridobivanju podatkov');
                }
                // Ikona za vreme
                const weatherIcons = {
                    '01d': '☀️', '01n': '🌙', '02d': '⛅', '02n': '☁️',
                    '03d': '☁️', '03n': '☁️', '04d': '☁️', '04n': '☁️',
                    '09d': '🌧️', '09n': '🌧️', '10d': '🌦️', '10n': '🌧️',
                    '11d': '⛈️', '11n': '⛈️', '13d': '❄️', '13n': '❄️',
                    '50d': '🌫️', '50n': '🌫️'
                };
                const weatherIcon = weatherIcons[data.icon] || '☁️';
                // Prikaz kompaktnega vremena
                compactElement.innerHTML = `
                    <span class="weather-icon">${weatherIcon}</span>
                    <span class="weather-temp">${data.temperature}°C</span>
                    <small class="weather-city">${data.city}</small>
                `;
                // Smer vetra
                const directions = ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'];
                const windDirection = directions[Math.round(data.wind_deg / 45) % 8];
                // Sončni vzhod in zahod
                const sunrise = data.sunrise ? new Date(data.sunrise * 1000).toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                const sunset = data.sunset ? new Date(data.sunset * 1000).toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                // Prikaz podrobnosti
                detailsElement.innerHTML = `
                    <div class="weather-detailed-content">
                        <div class="text-center mb-3">
                            <h4 class="mb-1">${data.city}${data.country ? ', ' + data.country : ''}</h4>
                            <div class="weather-main-icon">${weatherIcon}</div>
                            <h2 class="mb-0">${data.temperature}°C</h2>
                            <p class="text-muted mb-0">${data.description}</p>
                            <small class="text-muted">Občutek: ${data.feels_like}°C</small>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-thermometer-half text-danger"></i>
                                    <small class="d-block">Min/Max</small>
                                    <strong>${data.temp_min}°C / ${data.temp_max}°C</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-droplet-half text-primary"></i>
                                    <small class="d-block">Vlažnost</small>
                                    <strong>${data.humidity}%</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-wind text-info"></i>
                                    <small class="d-block">Veter</small>
                                    <strong>${data.wind_speed} m/s ${windDirection}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-speedometer text-secondary"></i>
                                    <small class="d-block">Pritisk</small>
                                    <strong>${data.pressure} hPa</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-sunrise text-warning"></i>
                                    <small class="d-block">Sončni vzhod</small>
                                    <strong>${sunrise}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <i class="bi bi-sunset text-warning"></i>
                                    <small class="d-block">Sončni zahod</small>
                                    <strong>${sunset}</strong>
                                </div>
                            </div>
                        </div>
                        ${data.from_cache ? '<small class="text-muted d-block text-center mt-2"><i class="bi bi-clock-history"></i> Shranjeno</small>' : ''}
                    </div>
                `;
            } catch (error) {
                console.error('Napaka pri nalaganju vremena:', error);
                compactElement.innerHTML = `
                    <span class="weather-icon">❌</span>
                    <span class="weather-temp">--°C</span>
                    <small class="weather-city">Napaka</small>
                `;
            }
        }
        // Naloži vreme če je modal odprt
        <?php if ($naloga_detail): ?>
        document.addEventListener('DOMContentLoaded', function() {
            loadWeather();
        });
        <?php endif; ?>
    </script>

<?php include 'includes/footer.php'; ?>