<?php
$napaka = '';
$uspeh = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Pridobi podatke iz formularja
        $uporabnisko_ime = trim($_POST['uporabnisko_ime']);
        $email = trim($_POST['email']);
        $geslo = $_POST['geslo'];
        $geslo_ponovitev = $_POST['geslo_ponovitev'];
        
        // Validacija
        if (empty($uporabnisko_ime) || empty($email) || empty($geslo)) {
            $napaka = "Vsa polja so obvezna.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $napaka = "Neveljaven email naslov.";
        } elseif (strlen($geslo) < 6) {
            $napaka = "Geslo mora imeti vsaj 6 znakov.";
        } elseif ($geslo !== $geslo_ponovitev) {
            $napaka = "Gesli se ne ujemata.";
        } else {
            // Preveri ali uporabniško ime ali email že obstaja
            $stmt = $pdo->prepare("SELECT id FROM Uporabnik WHERE uporabnisko_ime = ? OR email = ?");
            $stmt->execute([$uporabnisko_ime, $email]);
            
            if ($stmt->fetch()) {
                $napaka = "Uporabniško ime ali email že obstaja.";
            } else {
                // Ustvari hash gesla
                $geslo_hash = password_hash($geslo, PASSWORD_DEFAULT);
                
                // Vstavi novega uporabnika (vloga_id = 4 za samostojnega uporabnika)
                $stmt = $pdo->prepare("
                    INSERT INTO Uporabnik (uporabnisko_ime, email, geslo, datum_registracije, vloga_id) 
                    VALUES (?, ?, ?, NOW(), 4)
                ");
                $stmt->execute([$uporabnisko_ime, $email, $geslo_hash]);
                
                $uspeh = "Registracija uspešna! Sedaj se lahko prijavite.";
            }
        }
        
    } catch (PDOException $e) {
        $napaka = "Napaka pri registraciji: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija - Todo Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Registracija</h2>
                        
                        <?php if ($napaka): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($napaka) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($uspeh): ?>
                            <div class="alert alert-success">
                                <?= htmlspecialchars($uspeh) ?>
                                <br>
                                <a href="prijava.php" class="btn btn-primary mt-2">Pojdi na prijavo</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="uporabnisko_ime" class="form-label">Uporabniško ime</label>
                                    <input type="text" class="form-control" id="uporabnisko_ime" 
                                           name="uporabnisko_ime" required
                                           value="<?= htmlspecialchars($_POST['uporabnisko_ime'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                           name="email" required
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="geslo" class="form-label">Geslo</label>
                                    <input type="password" class="form-control" id="geslo" 
                                           name="geslo" required minlength="6">
                                    <div class="form-text">Geslo mora imeti vsaj 6 znakov.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="geslo_ponovitev" class="form-label">Ponovite geslo</label>
                                    <input type="password" class="form-control" id="geslo_ponovitev" 
                                           name="geslo_ponovitev" required minlength="6">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Registriraj se</button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <p>Že imate račun? <a href="prijava.php">Prijavite se</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
