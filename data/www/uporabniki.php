<?php
session_start();
require_once 'preveri_prijavo.php';

// Preveri, če je uporabnik prijavljen in ima vlogo administratorja
preveri_prijavo();
preveri_vlogo([1]); // Samo administrator

// Poveži se z bazo
try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch(PDOException $e) {
    die("Napaka pri povezavi z bazo: " . $e->getMessage());
}

// Obdelava izbrisa uporabnika
if (isset($_GET['izbrisi']) && is_numeric($_GET['izbrisi'])) {
    $uporabnik_id = $_GET['izbrisi'];
    
    try {
        // Ne dovoli izbrisa samega sebe
        if ($uporabnik_id == $_SESSION['uporabnik_id']) {
            $napaka = "Ne morete izbrisati samega sebe!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Uporabnik WHERE id = ?");
            $stmt->execute([$uporabnik_id]);
            $uspeh = "Uporabnik je bil uspešno izbrisan.";
        }
    } catch(PDOException $e) {
        $napaka = "Napaka pri brisanju uporabnika: " . $e->getMessage();
    }
}

// Obdelava spremembe vloge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uporabnik_id']) && isset($_POST['vloga_id'])) {
    $uporabnik_id = $_POST['uporabnik_id'];
    $vloga_id = $_POST['vloga_id'];
    
    try {
        // Ne dovoli spremembe svoje vloge
        if ($uporabnik_id == $_SESSION['uporabnik_id']) {
            $napaka = "Ne morete spremeniti svoje vloge!";
        } else {
            $stmt = $pdo->prepare("UPDATE Uporabnik SET vloga_id = ? WHERE id = ?");
            $stmt->execute([$vloga_id, $uporabnik_id]);
            $uspeh = "Vloga uporabnika je bila uspešno spremenjena.";
        }
    } catch(PDOException $e) {
        $napaka = "Napaka pri spremembi vloge: " . $e->getMessage();
    }
}

// Pridobi vse uporabnike z vlogami
$stmt = $pdo->query("
    SELECT u.id, u.uporabnisko_ime, u.email, u.datum_registracije, v.id as vloga_id, v.naziv as vloga_naziv
    FROM Uporabnik u
    LEFT JOIN Vloga v ON u.vloga_id = v.id
    ORDER BY u.datum_registracije DESC
");
$uporabniki = $stmt->fetchAll();

// Pridobi vse vloge za dropdown
$stmt = $pdo->query("SELECT id, naziv FROM Vloga ORDER BY id");
$vloge = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravljanje uporabnikov - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar -->
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
                        <a class="nav-link" href="administracija.php">
                            <i class="bi bi-gear"></i> Administracija
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="uporabniki.php">
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
                            <?php echo htmlspecialchars($_SESSION['uporabnisko_ime']); ?>
                            <span class="badge bg-light text-primary ms-2"><?php echo htmlspecialchars($_SESSION['vloga_naziv']); ?></span>
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
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-people-fill"></i> Upravljanje uporabnikov
                </h1>
                
                <?php if (isset($uspeh)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $uspeh; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($napaka)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $napaka; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-table"></i> Seznam uporabnikov
                            <span class="badge bg-light text-primary float-end"><?php echo count($uporabniki); ?> uporabnikov</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Uporabniško ime</th>
                                        <th>Email</th>
                                        <th>Vloga</th>
                                        <th>Datum registracije</th>
                                        <th class="text-center">Akcije</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($uporabniki)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                                <p class="mb-0 mt-2">Ni uporabnikov</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($uporabniki as $uporabnik): ?>
                                            <tr <?php echo $uporabnik['id'] == $_SESSION['uporabnik_id'] ? 'class="table-info"' : ''; ?>>
                                                <td><strong><?php echo htmlspecialchars($uporabnik['id']); ?></strong></td>
                                                <td>
                                                    <i class="bi bi-person-fill"></i>
                                                    <?php echo htmlspecialchars($uporabnik['uporabnisko_ime']); ?>
                                                    <?php if ($uporabnik['id'] == $_SESSION['uporabnik_id']): ?>
                                                        <span class="badge bg-info">Vi</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="bi bi-envelope"></i>
                                                    <?php echo htmlspecialchars($uporabnik['email']); ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onchange="if(confirm('Ali ste prepričani, da želite spremeniti vlogo tega uporabnika?')) this.submit(); else return false;">
                                                        <input type="hidden" name="uporabnik_id" value="<?php echo $uporabnik['id']; ?>">
                                                        <select name="vloga_id" class="form-select form-select-sm" style="width: auto; display: inline-block;" 
                                                                <?php echo $uporabnik['id'] == $_SESSION['uporabnik_id'] ? 'disabled' : ''; ?>>
                                                            <?php foreach ($vloge as $vloga): ?>
                                                                <option value="<?php echo $vloga['id']; ?>" 
                                                                        <?php echo $uporabnik['vloga_id'] == $vloga['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($vloga['naziv']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <i class="bi bi-calendar"></i>
                                                    <?php echo date('d.m.Y H:i', strtotime($uporabnik['datum_registracije'])); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($uporabnik['id'] != $_SESSION['uporabnik_id']): ?>
                                                        <a href="?izbrisi=<?php echo $uporabnik['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Ali ste prepričani, da želite izbrisati tega uporabnika?');">
                                                            <i class="bi bi-trash"></i> Izbriši
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Statistika vlog -->
                <div class="row mt-4">
                    <?php
                    // Preštej uporabnike po vlogah
                    $vloge_statistika = [];
                    foreach ($uporabniki as $uporabnik) {
                        $vloga = $uporabnik['vloga_naziv'] ?? 'Neznana';
                        if (!isset($vloge_statistika[$vloga])) {
                            $vloge_statistika[$vloga] = 0;
                        }
                        $vloge_statistika[$vloga]++;
                    }
                    ?>
                    
                    <?php foreach ($vloge_statistika as $vloga_naziv => $stevilo): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary"><?php echo $stevilo; ?></h3>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($vloga_naziv); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
