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
    
    // Preveri, če je ID skupine podan
    if (!isset($_GET['id'])) {
        header("Location: skupine.php");
        exit;
    }
    
    $skupina_id = (int)$_GET['id'];
    
    // Pridobi podatke o skupini
    $stmt = $pdo->prepare("
        SELECT s.*, u.uporabnisko_ime as vodja_ime
        FROM Skupina s
        LEFT JOIN Uporabnik u ON s.vodja_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$skupina_id]);
    $skupina = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$skupina) {
        $_SESSION['error_message'] = "Skupina ne obstaja!";
        header("Location: skupine.php");
        exit;
    }
    
    // Preveri, če je uporabnik član ali vodja skupine
    $stmt = $pdo->prepare("
        SELECT * FROM ClaniSkupine 
        WHERE skupina_id = ? AND uporabnik_id = ?
    ");
    $stmt->execute([$skupina_id, $uporabnik_id]);
    $je_clan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$je_clan) {
        $_SESSION['error_message'] = "Nimate dostopa do te skupine!";
        header("Location: skupine.php");
        exit;
    }
    
    $je_vodja = ($skupina['vodja_id'] == $uporabnik_id);
    
    // Dodajanje člana v skupino (samo vodja)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_clana']) && $je_vodja) {
        $novi_clan_id = (int)$_POST['uporabnik_id'];
        
        // Preveri, če uporabnik že je član
        $stmt = $pdo->prepare("SELECT * FROM ClaniSkupine WHERE skupina_id = ? AND uporabnik_id = ?");
        $stmt->execute([$skupina_id, $novi_clan_id]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO ClaniSkupine (uporabnik_id, skupina_id, datum_prikljucitve) VALUES (?, ?, NOW())");
            $stmt->execute([$novi_clan_id, $skupina_id]);
            $_SESSION['success_message'] = "Član je bil uspešno dodan v skupino!";
        } else {
            $_SESSION['error_message'] = "Ta uporabnik je že član skupine!";
        }
        
        header("Location: skupina_detail.php?id=" . $skupina_id);
        exit;
    }
    
    // Odstranjevanje člana iz skupine (samo vodja)
    if (isset($_GET['odstrani_clana']) && $je_vodja) {
        $clan_id = (int)$_GET['odstrani_clana'];
        
        // Ne dovoli odstranitve vodja
        if ($clan_id != $skupina['vodja_id']) {
            $stmt = $pdo->prepare("DELETE FROM ClaniSkupine WHERE skupina_id = ? AND uporabnik_id = ?");
            $stmt->execute([$skupina_id, $clan_id]);
            $_SESSION['success_message'] = "Član je bil odstranjen iz skupine!";
        } else {
            $_SESSION['error_message'] = "Vodja skupine ne more biti odstranjen!";
        }
        
        header("Location: skupina_detail.php?id=" . $skupina_id);
        exit;
    }
    
    // Dodajanje nove naloge za skupino (samo vodja)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nalogo']) && $je_vodja) {
        $naslov = trim($_POST['naslov']);
        $opis = trim($_POST['opis']);
        $rok = $_POST['rok_izvedbe'];
        $status = 'neopravljeno';
        
        if (!empty($naslov)) {
            // Ustvari nalogo
            $stmt = $pdo->prepare("INSERT INTO Naloga (naslov, opis, rok_izvedbe, datum_ustvarjenja, status) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$naslov, $opis, $rok, $status]);
            
            $naloga_id = $pdo->lastInsertId();
            
            // Dodeli nalogo skupini
            $stmt = $pdo->prepare("INSERT INTO DodelitevNaloge (datum_dodelitve, naloga_id, uporabnik_id, skupina_id) VALUES (NOW(), ?, NULL, ?)");
            $stmt->execute([$naloga_id, $skupina_id]);
            
            $_SESSION['success_message'] = "Naloga je bila uspešno dodana skupini!";
            header("Location: skupina_detail.php?id=" . $skupina_id);
            exit;
        }
    }
    
    // Označi nalogo kot opravljeno
    if (isset($_GET['opravi']) && isset($_GET['naloga_id'])) {
        $naloga_id = (int)$_GET['naloga_id'];
        
        $stmt = $pdo->prepare("UPDATE Naloga SET status = 'opravljeno', datum_zakljucka = NOW() WHERE id = ?");
        $stmt->execute([$naloga_id]);
        
        $_SESSION['success_message'] = "Naloga je bila označena kot opravljena!";
        header("Location: skupina_detail.php?id=" . $skupina_id);
        exit;
    }
    
    // Brisanje naloge (samo vodja)
    if (isset($_GET['izbrisi_nalogo']) && $je_vodja) {
        $naloga_id = (int)$_GET['izbrisi_nalogo'];
        
        $stmt = $pdo->prepare("DELETE FROM Naloga WHERE id = ?");
        $stmt->execute([$naloga_id]);
        
        $_SESSION['success_message'] = "Naloga je bila izbrisana!";
        header("Location: skupina_detail.php?id=" . $skupina_id);
        exit;
    }
    
    // Dodajanje komentarja na nalogo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_komentar'])) {
        $naloga_id = (int)$_POST['naloga_id'];
        $besedilo = trim($_POST['besedilo']);
        
        if (!empty($besedilo)) {
            $stmt = $pdo->prepare("INSERT INTO Komentar (naloga_id, uporabnik_id, besedilo, datum_vnosa) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$naloga_id, $uporabnik_id, $besedilo]);
            $_SESSION['success_message'] = "Komentar je bil dodan!";
        }
        
        header("Location: skupina_detail.php?id=" . $skupina_id . "#naloga-" . $naloga_id);
        exit;
    }
    
    // Pridobi člane skupine
    $stmt = $pdo->prepare("
        SELECT u.id, u.uporabnisko_ime, u.email, cs.datum_prikljucitve,
               CASE WHEN s.vodja_id = u.id THEN 1 ELSE 0 END as je_vodja
        FROM ClaniSkupine cs
        INNER JOIN Uporabnik u ON cs.uporabnik_id = u.id
        INNER JOIN Skupina s ON cs.skupina_id = s.id
        WHERE cs.skupina_id = ?
        ORDER BY je_vodja DESC, u.uporabnisko_ime ASC
    ");
    $stmt->execute([$skupina_id]);
    $clani = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pridobi uporabnike, ki še niso člani (za dodajanje)
    if ($je_vodja) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.uporabnisko_ime, u.email
            FROM Uporabnik u
            WHERE u.id NOT IN (SELECT uporabnik_id FROM ClaniSkupine WHERE skupina_id = ?)
            AND u.vloga_id != 1
            ORDER BY u.uporabnisko_ime ASC
        ");
        $stmt->execute([$skupina_id]);
        $razpolozljivi_uporabniki = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Pridobi naloge skupine
    $stmt = $pdo->prepare("
        SELECT n.*, 
               (SELECT COUNT(*) FROM Komentar WHERE naloga_id = n.id) as stevilo_komentarjev
        FROM Naloga n
        INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
        WHERE dn.skupina_id = ?
        ORDER BY 
            CASE WHEN n.status = 'neopravljeno' THEN 0 ELSE 1 END,
            n.rok_izvedbe ASC,
            n.datum_ustvarjenja DESC
    ");
    $stmt->execute([$skupina_id]);
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pridobi komentarje za prikaz naloge
    $naloga_komentarji = [];
    if (isset($_GET['view_naloga'])) {
        $view_naloga_id = (int)$_GET['view_naloga'];
        $stmt = $pdo->prepare("
            SELECT k.*, u.uporabnisko_ime
            FROM Komentar k
            INNER JOIN Uporabnik u ON k.uporabnik_id = u.id
            WHERE k.naloga_id = ?
            ORDER BY k.datum_vnosa ASC
        ");
        $stmt->execute([$view_naloga_id]);
        $naloga_komentarji = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM Naloga WHERE id = ?");
        $stmt->execute([$view_naloga_id]);
        $naloga_detail = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
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
    <title><?php echo htmlspecialchars($skupina['ime']); ?> - Todo Manager</title>
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
                    <?php if ($_SESSION['vloga_id'] != 1): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="skupine.php">
                            <i class="bi bi-people"></i> Moje skupine
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['uporabnisko_ime']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="odjava.php"><i class="bi bi-box-arrow-right"></i> Odjava</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="bi bi-people-fill"></i> <?php echo htmlspecialchars($skupina['ime']); ?></h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-person-badge"></i> Vodja: <strong><?php echo htmlspecialchars($skupina['vodja_ime']); ?></strong>
                    <?php if ($je_vodja): ?>
                        <span class="badge bg-success ms-2">Vi ste vodja</span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="skupine.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Nazaj na skupine
            </a>
        </div>

        <div class="row">
            <!-- Leva stran: Člani -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Člani skupine</h5>
                        <?php if ($je_vodja): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#dodajClanaModal">
                            <i class="bi bi-plus"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($clani as $clan): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-person-fill"></i> 
                                        <strong><?php echo htmlspecialchars($clan['uporabnisko_ime']); ?></strong>
                                        <?php if ($clan['je_vodja']): ?>
                                            <span class="badge bg-warning text-dark ms-2">Vodja</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($clan['email']); ?></small>
                                    </div>
                                    <?php if ($je_vodja && !$clan['je_vodja']): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="removeMember(<?php echo $clan['id']; ?>, <?php echo $skupina_id; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Vreme -->
                <div class="card mt-3">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-cloud-sun"></i> Vreme</h5>
                        <button class="btn btn-sm btn-dark" onclick="refreshWeather()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="card-body" id="weatherWidget">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Nalaganje...</span>
                            </div>
                            <p class="mt-2 text-muted">Nalaganje vremenske napovedi...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desna stran: Naloge -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-task"></i> Naloge skupine</h5>
                        <?php if ($je_vodja): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#dodajNalogoModal">
                            <i class="bi bi-plus-circle"></i> Dodaj nalogo
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($naloge)): ?>
                            <p class="text-muted text-center py-4">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i><br>
                                Skupina še nima nalog.
                                <?php if ($je_vodja): ?>
                                    <br>Dodajte prvo nalogo s klikom na gumb zgoraj!
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($naloge as $naloga): ?>
                                    <div class="list-group-item" id="naloga-<?php echo $naloga['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php if ($naloga['status'] == 'opravljeno'): ?>
                                                        <i class="bi bi-check-circle-fill text-success"></i>
                                                        <del><?php echo htmlspecialchars($naloga['naslov']); ?></del>
                                                        <span class="badge bg-success ms-2">Opravljeno</span>
                                                    <?php else: ?>
                                                        <i class="bi bi-circle"></i>
                                                        <?php echo htmlspecialchars($naloga['naslov']); ?>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($naloga['opis']); ?></p>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> Rok: <?php echo date('d.m.Y H:i', strtotime($naloga['rok_izvedbe'])); ?>
                                                    | <i class="bi bi-chat"></i> <?php echo $naloga['stevilo_komentarjev']; ?> komentarjev
                                                </small>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <?php if ($naloga['status'] == 'neopravljeno'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="completeTask(<?php echo $naloga['id']; ?>)">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#komentarModal<?php echo $naloga['id']; ?>">
                                                    <i class="bi bi-chat"></i>
                                                </button>
                                                <?php if ($je_vodja): ?>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="deleteTask(<?php echo $naloga['id']; ?>, <?php echo $skupina_id; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal za komentarje naloge -->
                                    <div class="modal fade" id="komentarModal<?php echo $naloga['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Komentarji: <?php echo htmlspecialchars($naloga['naslov']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php
                                                    // Pridobi komentarje za to nalogo
                                                    $stmt = $pdo->prepare("
                                                        SELECT k.*, u.uporabnisko_ime
                                                        FROM Komentar k
                                                        INNER JOIN Uporabnik u ON k.uporabnik_id = u.id
                                                        WHERE k.naloga_id = ?
                                                        ORDER BY k.datum_vnosa ASC
                                                    ");
                                                    $stmt->execute([$naloga['id']]);
                                                    $komentarji = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                    
                                                    <?php if (empty($komentarji)): ?>
                                                        <div class="komentarji-list">
                                                            <p class="text-muted text-center">Še ni komentarjev.</p>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="komentarji-list mb-3" style="max-height: 300px; overflow-y: auto;">
                                                            <?php foreach ($komentarji as $komentar): ?>
                                                                <div class="border-bottom pb-2 mb-2">
                                                                    <div class="d-flex justify-content-between">
                                                                        <strong><?php echo htmlspecialchars($komentar['uporabnisko_ime']); ?></strong>
                                                                        <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($komentar['datum_vnosa'])); ?></small>
                                                                    </div>
                                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($komentar['besedilo'])); ?></p>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <hr>
                                                    <form method="POST" class="komentar-form">
                                                        <input type="hidden" name="naloga_id" value="<?php echo $naloga['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Dodaj komentar:</label>
                                                            <textarea class="form-control" name="besedilo" rows="3" required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-send"></i> Dodaj komentar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal za dodajanje člana -->
    <?php if ($je_vodja): ?>
    <div class="modal fade" id="dodajClanaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Dodaj člana</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($razpolozljivi_uporabniki)): ?>
                            <p class="text-muted">Vsi uporabniki so že člani te skupine.</p>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Izberi uporabnika:</label>
                                <select class="form-select" name="uporabnik_id" required>
                                    <option value="">-- Izberi uporabnika --</option>
                                    <?php foreach ($razpolozljivi_uporabniki as $uporabnik): ?>
                                        <option value="<?php echo $uporabnik['id']; ?>">
                                            <?php echo htmlspecialchars($uporabnik['uporabnisko_ime']); ?> 
                                            (<?php echo htmlspecialchars($uporabnik['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Prekliči</button>
                        <?php if (!empty($razpolozljivi_uporabniki)): ?>
                            <button type="submit" name="dodaj_clana" class="btn btn-primary">
                                <i class="bi bi-check"></i> Dodaj
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal za dodajanje naloge -->
    <div class="modal fade" id="dodajNalogoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Dodaj nalogo skupini</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Naslov naloge *</label>
                            <input type="text" class="form-control" name="naslov" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Opis</label>
                            <textarea class="form-control" name="opis" rows="3" maxlength="255"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rok izvedbe *</label>
                            <input type="datetime-local" class="form-control" name="rok_izvedbe" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Prekliči</button>
                        <button type="submit" name="dodaj_nalogo" class="btn btn-primary">
                            <i class="bi bi-check"></i> Dodaj nalogo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="api.js"></script>
    <script>
        const skupinaId = <?= $skupina_id ?>;
        
        // AJAX funkcija za označevanje naloge kot opravljene
        async function completeTask(nalogoId) {
            const nalogoElement = document.getElementById('naloga-' + nalogoId);
            nalogoElement.style.opacity = '0.5';
            
            try {
                const response = await apiPost('/naloge.php', {
                    action: 'opravi',
                    naloga_id: nalogoId
                });
                
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Osveži stran po kratki zakasnitvi
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    nalogoElement.style.opacity = '1';
                    showAlert(response.message, 'error');
                }
            } catch (error) {
                nalogoElement.style.opacity = '1';
                showAlert('Napaka: ' + error.message, 'error');
            }
        }
        
        // AJAX funkcija za dodajanje komentarja
        document.querySelectorAll('.komentar-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const nalogoId = formData.get('naloga_id');
                const textarea = this.querySelector('textarea[name="besedilo"]');
                const submitButton = this.querySelector('button[type="submit"]');
                
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Dodajam...';
                
                try {
                    const response = await apiPost('/komentarji.php', formData);
                    
                    if (response.success) {
                        showAlert(response.message, 'success');
                        
                        // Dodaj komentar v DOM
                        addCommentToDOM(nalogoId, response.komentar);
                        
                        // Počisti obrazec
                        textarea.value = '';
                        
                        // Posodobi števec komentarjev
                        updateCommentCount(nalogoId);
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
        });
        
        // Funkcija za dodajanje komentarja v DOM
        function addCommentToDOM(nalogoId, komentar) {
            const komentarjiContainer = document.querySelector(`#komentarModal${nalogoId} .komentarji-list`);
            if (!komentarjiContainer) return;
            
            // Odstrani "Ni komentarjev" sporočilo, če obstaja
            const noComments = komentarjiContainer.querySelector('.text-muted.text-center');
            if (noComments) {
                noComments.remove();
            }
            
            const komentarElement = document.createElement('div');
            komentarElement.className = 'border-bottom pb-2 mb-2';
            komentarElement.innerHTML = `
                <div class="d-flex justify-content-between">
                    <strong>${escapeHtml(komentar.uporabnisko_ime)}</strong>
                    <small class="text-muted">${formatDate(komentar.datum_vnosa)}</small>
                </div>
                <p class="mb-0">${escapeHtml(komentar.besedilo)}</p>
            `;
            
            komentarjiContainer.appendChild(komentarElement);
        }
        
        // Funkcija za posodabljanje števca komentarjev
        function updateCommentCount(nalogoId) {
            const nalogoElement = document.getElementById('naloga-' + nalogoId);
            if (!nalogoElement) return;
            
            const commentCountElement = nalogoElement.querySelector('small.text-muted');
            if (commentCountElement) {
                const currentText = commentCountElement.textContent;
                const match = currentText.match(/(\d+) komentarjev/);
                if (match) {
                    const newCount = parseInt(match[1]) + 1;
                    commentCountElement.innerHTML = commentCountElement.innerHTML.replace(
                        /\d+ komentarjev/,
                        newCount + ' komentarjev'
                    );
                }
            }
        }
        
        // Helper funkcija za escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        
        // Funkcija za odstranitev člana
        async function removeMember(memberId, groupId) {
            const confirmed = await showConfirm(
                'Ste prepričani, da želite odstraniti tega člana?',
                'Odstranitev člana'
            );
            
            if (confirmed) {
                window.location.href = '?id=' + groupId + '&odstrani_clana=' + memberId;
            }
        }
        
        // Funkcija za brisanje naloge
        async function deleteTask(taskId, groupId) {
            const confirmed = await showConfirm(
                'Ste prepričani, da želite izbrisati to nalogo?',
                'Brisanje naloge'
            );
            
            if (confirmed) {
                window.location.href = '?id=' + groupId + '&izbrisi_nalogo=' + taskId;
            }
        }
        
        // Prikaži obvestila iz PHP sessiona
        <?php if (isset($_SESSION['success_message'])): ?>
            showAlert(<?php echo json_encode($_SESSION['success_message']); ?>, 'success');
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            showAlert(<?php echo json_encode($_SESSION['error_message']); ?>, 'error');
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        // Funkcija za pridobivanje vremena
        async function loadWeather(city = 'Maribor') {
            const widget = document.getElementById('weatherWidget');
            
            try {
                const response = await fetch(`api/weather.php?city=${encodeURIComponent(city)}`);
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Napaka pri pridobivanju podatkov');
                }
                
                // Ikona za vreme
                const iconUrl = `https://openweathermap.org/img/wn/${data.icon}@2x.png`;
                
                // Smer vetra
                const windDirection = getWindDirection(data.wind_deg);
                
                // Sončni vzhod in zahod
                const sunrise = data.sunrise ? new Date(data.sunrise * 1000).toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                const sunset = data.sunset ? new Date(data.sunset * 1000).toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                
                widget.innerHTML = `
                    <div class="text-center mb-3">
                        <h4 class="mb-1">${data.city}${data.country ? ', ' + data.country : ''}</h4>
                        <img src="${iconUrl}" alt="${data.description}" style="width: 80px; height: 80px;">
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
                `;
                
            } catch (error) {
                console.error('Napaka pri nalaganju vremena:', error);
                widget.innerHTML = `
                    <div class="alert alert-warning mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Napaka pri nalaganju vremena</strong>
                        <p class="mb-0 small">${error.message}</p>
                        <small class="text-muted d-block mt-2">
                            Preverite, ali je API ključ pravilno nastavljen v <code>api/weather.php</code>.
                            <br>Navodila najdete v <code>CONFIG_API.md</code>.
                        </small>
                    </div>
                `;
            }
        }
        
        // Funkcija za določitev smeri vetra
        function getWindDirection(degrees) {
            const directions = ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'];
            const index = Math.round(degrees / 45) % 8;
            return directions[index];
        }
        
        // Funkcija za osvežitev vremena
        function refreshWeather() {
            const widget = document.getElementById('weatherWidget');
            widget.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Nalaganje...</span>
                    </div>
                    <p class="mt-2 text-muted">Osveževanje vremenske napovedi...</p>
                </div>
            `;
            loadWeather();
        }
        
        // Naloži vreme ob nalaganju strani
        loadWeather();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
