<?php
session_start();

// Če je uporabnik že prijavljen, preusmeri na index
if (isset($_SESSION['uporabnik_id'])) {
    header("Location: index.php");
    exit;
}

$napaka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'config/db.php';
        
        $email = trim($_POST['email']);
        $geslo = $_POST['geslo'];
        
        if (empty($email) || empty($geslo)) {
            $napaka = "Email in geslo sta obvezna.";
        } else {
            // Poišči uporabnika po emailu
            $stmt = $pdo->prepare("
                SELECT u.*, v.naziv as vloga_naziv 
                FROM Uporabnik u 
                JOIN Vloga v ON u.vloga_id = v.id 
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $uporabnik = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Preveri geslo
            if ($uporabnik && password_verify($geslo, $uporabnik['geslo'])) {
                // Uspešna prijava - shrani v session
                $_SESSION['uporabnik_id'] = $uporabnik['id'];
                $_SESSION['uporabnisko_ime'] = $uporabnik['uporabnisko_ime'];
                $_SESSION['email'] = $uporabnik['email'];
                $_SESSION['vloga_id'] = $uporabnik['vloga_id'];
                $_SESSION['vloga_naziv'] = $uporabnik['vloga_naziv'];
                
                // Preveri za pending join
                if (isset($_SESSION['pending_join_group'])) {
                    $pending = $_SESSION['pending_join_group'];
                    unset($_SESSION['pending_join_group']);
                    header("Location: pridruzi_skupini.php?id=" . $pending['id'] . "&hash=" . $pending['hash']);
                    exit;
                }

                // Preusmeri glede na vlogo
                if ($uporabnik['vloga_id'] == 1) {
                    // Administrator gre na administracijo
                    header("Location: administracija.php");
                } else {
                    // Ostali uporabniki grejo na domačo stran
                    header("Location: index.php");
                }
                exit;
            } else {
                $napaka = "Napačen email ali geslo.";
            }
        }
        
    } catch (PDOException $e) {
        $napaka = "Napaka pri prijavi: " . $e->getMessage();
    }
}
$pageTitle = 'Prijava - Todo Manager';
$bodyClass = 'bg-light';
include 'includes/header.php';
?>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Prijava</h2>
                        
                        <?php if (isset($_GET['registracija']) && $_GET['registracija'] === 'uspesna'): ?>
                            <div class="alert alert-success">
                                Registracija uspešna! Sedaj se lahko prijavite.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($napaka): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($napaka) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       name="email" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="geslo" class="form-label">Geslo</label>
                                <input type="password" class="form-control" id="geslo" 
                                       name="geslo" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Prijavi se</button>
                        </form>
                        <hr>

                        <div class="text-center mt-3">
                            <p>Nimate računa? <a href="registracija.php">Registrirajte se</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php include 'includes/footer.php'; ?>
