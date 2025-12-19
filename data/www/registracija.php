<?php
require_once 'config/email.php';
$napaka = '';
$uspeh = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'config/db.php';
        
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
                
                // Pošlji email dobrodošlice
                $zadeva = "Dobrodošli v Todo Manager!";
                $sporocilo = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                        h1 { color: #2c3e50; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h1>👋 Dobrodošli, $uporabnisko_ime!</h1>
                        <p>Hvala za registracijo v aplikacijo <strong>Todo Manager</strong>.</p>
                        <p>Zdaj lahko začnete z ustvarjanjem nalog in organizacijo svojega dela.</p>
                        <p>Srečno!</p>
                    </div>
                </body>
                </html>
                ";
                poslji_email($email, $zadeva, $sporocilo);

                $uspeh = "Registracija uspešna! Sedaj se lahko prijavite.";
            }
        }
        
    } catch (PDOException $e) {
        $napaka = "Napaka pri registraciji: " . $e->getMessage();
    }
}
$pageTitle = 'Registracija - Todo Manager';
$bodyClass = 'bg-light';
include 'includes/header.php';
?>
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
    
<?php include 'includes/footer.php'; ?>
