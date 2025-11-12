<?php
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
    
    // Preberi vse naloge
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
    <title>Domov - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Todo Manager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Domov</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="urejanje.php">Upravljanje nalog</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">Vse naloge</h1>
        
        <?php if (empty($naloge)): ?>
            <div class="alert alert-info">Ni nalog. <a href="urejanje.php">Dodaj prvo nalogo</a></div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($naloge as $naloga): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($naloga['naslov']) ?></h5>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <strong>Rok:</strong> <?= $naloga['rok_izvedbe'] ? date('d.m.Y H:i', strtotime($naloga['rok_izvedbe'])) : 'Ni določen' ?>
                                    </small>
                                </p>
                                <p>
                                    <?php if ($naloga['status'] === 'opravljeno'): ?>
                                        <span class="badge bg-success">Opravljeno</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Neopravljeno</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="urejanje.php?edit=<?= $naloga['id'] ?>" class="btn btn-sm btn-primary">Uredi</a>
                                <a href="?delete=<?= $naloga['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Ste prepričani?')">Izbriši</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>