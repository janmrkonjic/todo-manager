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
    
    // Posodabljanje naloge
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uredi_nalogo'])) {
        $id = (int)$_POST['id'];
        $naslov = trim($_POST['naslov']);
        $opis = trim($_POST['opis']);
        $rok_izvedbe = $_POST['rok_izvedbe'] ?: null;
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE Naloga SET naslov = ?, opis = ?, rok_izvedbe = ?, status = ? WHERE id = ?");
        $stmt->execute([$naslov, $opis, $rok_izvedbe, $status, $id]);
        
        header("Location: administracija.php");
        exit;
    }
    
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
                    <li class="nav-item">
                        <a class="nav-link" href="statistika.php">
                            <i class="bi bi-bar-chart-line"></i> Statistika
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <?= htmlspecialchars($_SESSION['uporabnisko_ime']) ?>
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
                                            <button class="btn btn-sm btn-primary me-2" 
                                                    onclick="odpriModalUredi(<?= htmlspecialchars(json_encode($naloga)) ?>)" 
                                                    title="Uredi">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button onclick="deleteTask(<?= $naloga['id'] ?>)" class="btn btn-sm btn-danger" 
                                               title="Izbriši">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

    <!-- Modal za urejanje naloge -->
    <div class="modal fade" id="urediNalogoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="administracija.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Urejanje naloge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="uredi_nalogo" value="1">
                        <input type="hidden" name="id" id="uredi_id">
                        
                        <div class="mb-3">
                            <label for="uredi_naslov" class="form-label">Naslov</label>
                            <input type="text" class="form-control" id="uredi_naslov" name="naslov" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="uredi_opis" class="form-label">Opis</label>
                            <textarea class="form-control" id="uredi_opis" name="opis" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="uredi_rok_izvedbe" class="form-label">Rok izvedbe</label>
                            <input type="datetime-local" class="form-control" id="uredi_rok_izvedbe" name="rok_izvedbe">
                        </div>
                        
                        <div class="mb-3">
                            <label for="uredi_status" class="form-label">Status</label>
                            <select class="form-select" id="uredi_status" name="status" required>
                                <option value="neopravljeno">Neopravljeno</option>
                                <option value="opravljeno">Opravljeno</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Prekliči</button>
                        <button type="submit" class="btn btn-primary">Shrani spremembe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="api.js"></script>
    <script>
        function odpriModalUredi(naloga) {
            // Napolni modal s podatki naloge
            document.getElementById('uredi_id').value = naloga.id;
            document.getElementById('uredi_naslov').value = naloga.naslov;
            document.getElementById('uredi_opis').value = naloga.opis;
            document.getElementById('uredi_status').value = naloga.status;
            
            // Konvertiraj rok izvedbe v format za datetime-local input
            if (naloga.rok_izvedbe) {
                // Odstrani sekundni del in nadomesti presledek z 'T'
                const rokFormatiran = naloga.rok_izvedbe.slice(0, 16).replace(' ', 'T');
                document.getElementById('uredi_rok_izvedbe').value = rokFormatiran;
            } else {
                document.getElementById('uredi_rok_izvedbe').value = '';
            }
            
            // Odpri modal
            const modal = new bootstrap.Modal(document.getElementById('urediNalogoModal'));
            modal.show();
        }
        
        // Funkcija za brisanje naloge
        async function deleteTask(taskId) {
            const confirmed = await showConfirm(
                'Ste prepričani, da želite izbrisati to nalogo?',
                'Brisanje naloge'
            );
            
            if (confirmed) {
                window.location.href = '?delete=' + taskId;
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
    </script>
</body>
</html>
