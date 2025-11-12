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
        header("Location: urejanje.php");
        exit;
    }
    
    // Dodajanje nove naloge
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
        $naslov = trim($_POST['naslov']);
        $opis = trim($_POST['opis']);
        $rok = $_POST['rok_izvedbe'];
        $status = $_POST['status'];
        
        if (!empty($naslov)) {
            // Vstavi nalogo
            $stmt = $pdo->prepare("INSERT INTO Naloga (naslov, opis, rok_izvedbe, datum_ustvarjenja, status) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$naslov, $opis, $rok, $status]);
            
            // Pridobi ID novo ustvarjene naloge
            $naloga_id = $pdo->lastInsertId();
            
            // Dodeli nalogo trenutnemu uporabniku v DodelitevNaloge
            $stmt = $pdo->prepare("INSERT INTO DodelitevNaloge (datum_dodelitve, naloga_id, uporabnik_id, skupina_id) VALUES (NOW(), ?, ?, NULL)");
            $stmt->execute([$naloga_id, $_SESSION['uporabnik_id']]);
            
            header("Location: urejanje.php");
            exit;
        }
    }
    
    // Urejanje obstoječe naloge
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $naslov = trim($_POST['naslov']);
        $opis = trim($_POST['opis']);
        $rok = $_POST['rok_izvedbe'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE Naloga SET naslov = ?, opis = ?, rok_izvedbe = ?, status = ? WHERE id = ?");
        $stmt->execute([$naslov, $opis, $rok, $status, $id]);
        header("Location: urejanje.php");
        exit;
    }
    
    // Preberi vse naloge
    $stmt = $pdo->query("SELECT * FROM Naloga ORDER BY datum_ustvarjenja DESC");
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pridobi nalogo za urejanje
    $editNaloga = null;
    if (isset($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM Naloga WHERE id = ?");
        $stmt->execute([$id]);
        $editNaloga = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Upravljanje nalog - Todo Manager</title>
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
                        <a class="nav-link active" href="urejanje.php">
                            <i class="bi bi-pencil-square"></i> Upravljanje nalog
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

    <div class="container mt-5">
        <h1 class="mb-4">Upravljanje nalog</h1>
        
        <!-- Form za dodajanje/urejanje naloge -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= $editNaloga ? 'Uredi nalogo' : 'Dodaj novo nalogo' ?></h5>
                <form method="POST">
                    <?php if ($editNaloga): ?>
                        <input type="hidden" name="edit" value="1">
                        <input type="hidden" name="id" value="<?= $editNaloga['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="add" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Naslov</label>
                        <input type="text" class="form-control" name="naslov" 
                               value="<?= $editNaloga ? htmlspecialchars($editNaloga['naslov']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Opis</label>
                        <textarea class="form-control" name="opis" rows="3"><?= $editNaloga ? htmlspecialchars($editNaloga['opis']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rok izvedbe</label>
                        <input type="datetime-local" class="form-control" name="rok_izvedbe" 
                               value="<?= $editNaloga && $editNaloga['rok_izvedbe'] ? date('Y-m-d\TH:i', strtotime($editNaloga['rok_izvedbe'])) : '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="neopravljeno" <?= ($editNaloga && $editNaloga['status'] === 'neopravljeno') ? 'selected' : '' ?>>Neopravljeno</option>
                            <option value="opravljeno" <?= ($editNaloga && $editNaloga['status'] === 'opravljeno') ? 'selected' : '' ?>>Opravljeno</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><?= $editNaloga ? 'Posodobi' : 'Dodaj' ?></button>
                    <?php if ($editNaloga): ?>
                        <a href="urejanje.php" class="btn btn-secondary">Prekliči</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
