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
    
    // Ustvarjanje nove skupine
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ustvari_skupino'])) {
        $ime_skupine = trim($_POST['ime_skupine']);
        $barva = $_POST['barva'] ?? '#17a2b8';
        
        if (!empty($ime_skupine)) {
            // Ustvari skupino
            $stmt = $pdo->prepare("INSERT INTO Skupina (ime, vodja_id, barva, datum_ustvarjenja) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$ime_skupine, $uporabnik_id, $barva]);
            
            // Dodaj vodjo kot člana skupine
            $skupina_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO ClaniSkupine (uporabnik_id, skupina_id, datum_prikljucitve) VALUES (?, ?, NOW())");
            $stmt->execute([$uporabnik_id, $skupina_id]);
            
            $_SESSION['success_message'] = "Skupina '$ime_skupine' je bila uspešno ustvarjena!";
            header("Location: skupine.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Ime skupine ne sme biti prazno!";
        }
    }
    
    // Brisanje skupine (samo vodja)
    if (isset($_GET['izbrisi_skupino'])) {
        $skupina_id = (int)$_GET['izbrisi_skupino'];
        
        // Preveri, če je uporabnik vodja te skupine
        $stmt = $pdo->prepare("SELECT * FROM Skupina WHERE id = ? AND vodja_id = ?");
        $stmt->execute([$skupina_id, $uporabnik_id]);
        $skupina = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($skupina) {
            // Izbriši skupino (cascade bo izbrisal tudi člane in dodelitve nalog)
            $stmt = $pdo->prepare("DELETE FROM Skupina WHERE id = ?");
            $stmt->execute([$skupina_id]);
            
            $_SESSION['success_message'] = "Skupina je bila uspešno izbrisana!";
        } else {
            $_SESSION['error_message'] = "Nimate pravice za brisanje te skupine!";
        }
        
        header("Location: skupine.php");
        exit;
    }
    
    // Pridobi vse skupine, kjer je uporabnik vodja
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COUNT(DISTINCT cs.uporabnik_id) as stevilo_clanov,
               COUNT(DISTINCT dn.naloga_id) as stevilo_nalog
        FROM Skupina s
        LEFT JOIN ClaniSkupine cs ON s.id = cs.skupina_id
        LEFT JOIN DodelitevNaloge dn ON s.id = dn.skupina_id
        WHERE s.vodja_id = ?
        GROUP BY s.id
        ORDER BY s.datum_ustvarjenja DESC
    ");
    $stmt->execute([$uporabnik_id]);
    $skupine_vodja = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pridobi vse skupine, kjer je uporabnik član (ampak ne vodja)
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.uporabnisko_ime as vodja_ime,
               COUNT(DISTINCT cs2.uporabnik_id) as stevilo_clanov,
               COUNT(DISTINCT dn.naloga_id) as stevilo_nalog
        FROM Skupina s
        INNER JOIN ClaniSkupine cs ON s.id = cs.skupina_id
        LEFT JOIN Uporabnik u ON s.vodja_id = u.id
        LEFT JOIN ClaniSkupine cs2 ON s.id = cs2.skupina_id
        LEFT JOIN DodelitevNaloge dn ON s.id = dn.skupina_id
        WHERE cs.uporabnik_id = ? AND s.vodja_id != ?
        GROUP BY s.id
        ORDER BY s.datum_ustvarjenja DESC
    ");
    $stmt->execute([$uporabnik_id, $uporabnik_id]);
    $skupine_clan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Moje skupine - Todo Manager</title>
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
                    <?php if ($_SESSION['vloga_id'] != 1): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="skupine.php">
                            <i class="bi bi-people"></i> Moje skupine
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                        <li class="nav-item">
                            <span class="navbar-text text-white me-3">
                                <i class="bi bi-person-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($_SESSION['uporabnisko_ime']); ?>
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

    <div class="container mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-people"></i> Moje skupine</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ustvariSkupinoModal">
                <i class="bi bi-plus-circle"></i> Ustvari novo skupino
            </button>
        </div>

        <!-- Skupine kjer je uporabnik vodja -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-star-fill"></i> Skupine, kjer sem vodja</h5>
            </div>
            <div class="card-body">
                <?php if (empty($skupine_vodja)): ?>
                    <p class="text-muted">Še niste vodja nobene skupine. Ustvarite novo skupino!</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($skupine_vodja as $skupina): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-success" style="border-left: 5px solid <?php echo htmlspecialchars($skupina['barva'] ?? '#17a2b8'); ?> !important;">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                                                                    <i class="bi bi-people-fill"></i> <?php echo htmlspecialchars($skupina['ime']); ?>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> Ustvarjena: <?php echo date('d.m.Y', strtotime($skupina['datum_ustvarjenja'])); ?>
                                            </small>
                                        </p>
                                        <div class="mb-2">
                                            <span class="badge bg-info">
                                                <i class="bi bi-person"></i> <?php echo $skupina['stevilo_clanov']; ?> članov
                                            </span>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-list-task"></i> <?php echo $skupina['stevilo_nalog']; ?> nalog
                                            </span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="skupina_detail.php?id=<?php echo $skupina['id']; ?>" class="btn btn-sm btn-success flex-grow-1">
                                                <i class="bi bi-arrow-right-circle"></i> Odpri skupino
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="if(confirm('Ste prepričani, da želite izbrisati to skupino? To bo izbrisalo vse naloge in člane skupine!')) window.location.href='?izbrisi_skupino=<?php echo $skupina['id']; ?>'">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skupine kjer je uporabnik član -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-person"></i> Skupine, kjer sem član</h5>
            </div>
            <div class="card-body">
                <?php if (empty($skupine_clan)): ?>
                    <p class="text-muted">Niste član nobene skupine.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($skupine_clan as $skupina): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-info" style="border-left: 5px solid <?php echo htmlspecialchars($skupina['barva'] ?? '#17a2b8'); ?> !important;">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <span class="badge me-2" style="background-color: <?php echo htmlspecialchars($skupina['barva'] ?? '#17a2b8'); ?>;">
                                                <i class="bi bi-circle-fill"></i>
                                            </span>
                                            <i class="bi bi-people-fill"></i> <?php echo htmlspecialchars($skupina['ime']); ?>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="bi bi-person-badge"></i> Vodja: <?php echo htmlspecialchars($skupina['vodja_ime']); ?>
                                            </small><br>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> Ustvarjena: <?php echo date('d.m.Y', strtotime($skupina['datum_ustvarjenja'])); ?>
                                            </small>
                                        </p>
                                        <div class="mb-2">
                                            <span class="badge bg-info">
                                                <i class="bi bi-person"></i> <?php echo $skupina['stevilo_clanov']; ?> članov
                                            </span>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-list-task"></i> <?php echo $skupina['stevilo_nalog']; ?> nalog
                                            </span>
                                        </div>
                                        <a href="skupina_detail.php?id=<?php echo $skupina['id']; ?>" class="btn btn-sm btn-info w-100">
                                            <i class="bi bi-arrow-right-circle"></i> Odpri skupino
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal za ustvarjanje nove skupine -->
    <div class="modal fade" id="ustvariSkupinoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ustvari novo skupino</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="ime_skupine" class="form-label">Ime skupine *</label>
                            <input type="text" class="form-control" id="ime_skupine" name="ime_skupine" required maxlength="100">
                            <div class="form-text">Vnesite ime za vašo novo skupino (npr. "Projektna ekipa", "Razvojalci").</div>
                        </div>
                        <div class="mb-3">
                            <label for="barva" class="form-label">Barva skupine *</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="color" class="form-control form-control-color" id="barva" name="barva" value="#17a2b8" required>
                                <span class="text-muted">Izberite barvo, ki se bo prikazala pri nalogah te skupine</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Prekliči</button>
                        <button type="submit" name="ustvari_skupino" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Ustvari skupino
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
