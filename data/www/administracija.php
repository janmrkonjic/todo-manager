<?php
session_start();
require_once 'preveri_prijavo.php';
preveri_prijavo();
preveri_vlogo([1]); // Samo administrator

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
        header("Location: administracija.php");
        exit;
    }
    
    // Branje vseh nalog
    $stmt = $pdo->query("SELECT * FROM Naloga ORDER BY datum_ustvarjenja DESC");
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Administracija - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
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
                        <a class="nav-link active" href="administracija.php">
                            <i class="bi bi-gear"></i> Administracija
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uporabniki.php">
                            <i class="bi bi-people"></i> Uporabniki
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['uporabnisko_ime']) ?>
                            <span class="badge bg-light text-primary ms-2"><?= htmlspecialchars($_SESSION['vloga_naziv']) ?></span>
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
        <h1 class="mb-4"><i class="bi bi-shield-check"></i> Administracija - Vse naloge</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Seznam vseh nalog (<?= count($naloge) ?>)</h5>
                <?php if (empty($naloge)): ?>
                    <p class="text-muted">Ni nalog.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Naslov</th>
                                    <th>Opis</th>
                                    <th>Rok</th>
                                    <th>Status</th>
                                    <th>Ustvarjeno</th>
                                    <th>Akcije</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($naloge as $naloga): ?>
                                    <tr>
                                        <td><?= $naloga['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($naloga['naslov']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($naloga['opis'], 0, 50)) ?><?= strlen($naloga['opis']) > 50 ? '...' : '' ?></td>
                                        <td><?= $naloga['rok_izvedbe'] ? date('d.m.Y H:i', strtotime($naloga['rok_izvedbe'])) : '-' ?></td>
                                        <td>
                                            <?php if ($naloga['status'] === 'opravljeno'): ?>
                                                <span class="badge bg-success">Opravljeno</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Neopravljeno</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($naloga['datum_ustvarjenja'])) ?></td>
                                        <td>
                                            <a href="?delete=<?= $naloga['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Ste prepričani?')" title="Izbriši">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
