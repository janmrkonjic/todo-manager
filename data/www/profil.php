<?php
session_start();
require_once 'includes/functions.php';
preveri_prijavo();

// Pridobitev podatkov uporabnika iz baze
try {
    require_once 'config/db.php';

    $stmt = $pdo->prepare('
        SELECT u.id, u.uporabnisko_ime, u.email, u.datum_registracije, u.profilna_slika, v.naziv as vloga
        FROM Uporabnik u
        LEFT JOIN Vloga v ON u.vloga_id = v.id
        WHERE u.id = :id
    ');
    $stmt->execute(['id' => $_SESSION['uporabnik_id']]);
    $uporabnik = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$uporabnik) {
        header('Location: odjava.php');
        exit;
    }
    
    // Slika uporabnika v navbaru
    $uporabnik_slika = $uporabnik['profilna_slika'];
} catch (PDOException $e) {
    die('Napaka pri povezavi z bazo: ' . htmlspecialchars($e->getMessage()));
}

$pageTitle = 'Moj profil';
$activePage = 'profil.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .profile-image-container {
            width: 200px;
            height: 200px;
            margin: 0 auto 1.5rem;
            position: relative;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            color: white;
        }
        .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem;
            text-align: center;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        .profile-image-container:hover .upload-overlay {
            opacity: 1;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        .info-value {
            color: #212529;
        }
        .badge-role {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
    </style>
    <div class="profile-container">
        <h1 class="text-center mb-4"><i class="bi bi-person-circle"></i> Moj profil</h1>

        <!-- Profilna slika -->
        <div class="profile-image-container" id="profileImageContainer">
            <?php if ($uporabnik['profilna_slika'] && file_exists('uploads/profilne/' . $uporabnik['profilna_slika'])): ?>
                <img src="uploads/profilne/<?= htmlspecialchars($uporabnik['profilna_slika']) ?>" 
                     alt="Profilna slika" 
                     class="profile-image" 
                     id="profileImage"
                     data-lazy="true"
                     loading="lazy">
            <?php else: ?>
                <div class="profile-image-placeholder">
                    <i class="bi bi-person-circle"></i>
                </div>
            <?php endif; ?>
            <div class="upload-overlay" onclick="document.getElementById('imageUpload').click()">
                <i class="bi bi-camera"></i> Spremeni sliko
            </div>
        </div>

        <!-- Obrazec za nalaganje slike -->
        <form id="uploadForm" enctype="multipart/form-data" style="display: none;">
            <input type="file" 
                   id="imageUpload" 
                   name="slika" 
                   accept="image/jpeg,image/jpg,image/png"
                   onchange="handleImageUpload(event)">
        </form>

        <!-- Osnovni podatki -->
        <div class="info-card">
            <h5 class="mb-3"><i class="bi bi-info-circle"></i> Osnovni podatki</h5>
            <div class="info-row">
                <span class="info-label">Uporabniško ime:</span>
                <span class="info-value"><?= htmlspecialchars($uporabnik['uporabnisko_ime']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">E-pošta:</span>
                <span class="info-value"><?= htmlspecialchars($uporabnik['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Vloga:</span>
                <span class="info-value">
                    <span class="badge badge-role bg-primary">
                        <?= htmlspecialchars($uporabnik['vloga'] ?? 'Neznana') ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Registracija:</span>
                <span class="info-value">
                    <?= date('d.m.Y', strtotime($uporabnik['datum_registracije'])) ?>
                </span>
            </div>
        </div>

        <!-- Akcije -->
        <?php if ($uporabnik['profilna_slika']): ?>
        <div class="text-center">
            <button type="button" class="btn btn-danger" onclick="deleteProfileImage()">
                <i class="bi bi-trash"></i> Odstrani profilno sliko
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="api.js"></script>
    <script>
        async function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validacija tipa datoteke
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showAlert('Dovoljeni so samo JPEG in PNG formati.', 'error');
                return;
            }

            // Validacija velikosti (max 2MB)
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                showAlert('Slika je prevelika. Največja dovoljena velikost je 2MB.', 'error');
                return;
            }

            // Prikaz loading stanja
            const container = document.getElementById('profileImageContainer');
            const originalContent = container.innerHTML;
            container.innerHTML = `
                <div class="profile-image-placeholder">
                    <div class="spinner-border text-light" role="status">
                        <span class="visually-hidden">Nalaganje...</span>
                    </div>
                </div>
            `;

            try {
                const formData = new FormData();
                formData.append('slika', file);

                const response = await fetch('api/profil.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showAlert(data.message || 'Slika je bila uspešno naložena!', 'success');
                    
                    // Osveži stran po kratkem zamiku
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Napaka pri nalaganju slike');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showAlert(error.message || 'Napaka pri nalaganju slike. Prosim poskusite znova.', 'error');
                container.innerHTML = originalContent;
            }

            // Resetiraj input
            event.target.value = '';
        }

        async function deleteProfileImage() {
            if (!confirm('Ali ste prepričani, da želite odstraniti profilno sliko?')) {
                return;
            }

            try {
                const response = await fetch('api/profil.php?action=delete', {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showAlert(data.message || 'Slika je bila uspešno odstranjena!', 'success');
                    
                    // Osveži stran po kratkem zamiku
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Napaka pri brisanju slike');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showAlert(error.message || 'Napaka pri brisanju slike. Prosim poskusite znova.', 'error');
            }
        }
    </script>

<?php include 'includes/footer.php'; ?>
