<?php
session_start();
header('Content-Type: application/json');

api_check_auth();

// Povezava z bazo
try {
    require_once '../config/db.php';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri povezavi z bazo.']);
    exit;
}

$uporabnik_id = $_SESSION['uporabnik_id'];

// Obdelava POST zahteve - nalaganje slike
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preveri, ali je bila datoteka naložena
    if (!isset($_FILES['slika']) || $_FILES['slika']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Napaka pri nalaganju datoteke.']);
        exit;
    }

    $file = $_FILES['slika'];
    
    // Validacija tipa datoteke
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Napačen tip datoteke. Dovoljeni so samo JPEG in PNG.']);
        exit;
    }

    // Validacija velikosti (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datoteka je prevelika. Največja dovoljena velikost je 2MB.']);
        exit;
    }

    // Pridobi obstoječo sliko uporabnika
    $stmt = $pdo->prepare('SELECT profilna_slika FROM Uporabnik WHERE id = :id');
    $stmt->execute(['id' => $uporabnik_id]);
    $stara_slika = $stmt->fetchColumn();

    // Generiraj varno ime datoteke
    $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
    $filename = 'user_' . $uporabnik_id . '_' . time() . '.' . $extension;
    $uploadPath = __DIR__ . '/../uploads/profilne/' . $filename;

    // Premakni naloženo datoteko
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Napaka pri shranjevanju datoteke.']);
        exit;
    }

    // Posodobi bazo
    try {
        $stmt = $pdo->prepare('UPDATE Uporabnik SET profilna_slika = :slika WHERE id = :id');
        $stmt->execute([
            'slika' => $filename,
            'id' => $uporabnik_id
        ]);

        // Izbriši staro sliko, če obstaja
        if ($stara_slika) {
            $staraPath = __DIR__ . '/../uploads/profilne/' . $stara_slika;
            if (file_exists($staraPath)) {
                unlink($staraPath);
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Slika je bila uspešno naložena!',
            'filename' => $filename
        ]);
    } catch (PDOException $e) {
        // Če je napaka pri shranjevanju v bazo, izbriši naloženo datoteko
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Napaka pri posodabljanju profila.']);
    }
    exit;
}

// Obdelava DELETE zahteve - brisanje slike
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        // Pridobi trenutno sliko
        $stmt = $pdo->prepare('SELECT profilna_slika FROM Uporabnik WHERE id = :id');
        $stmt->execute(['id' => $uporabnik_id]);
        $slika = $stmt->fetchColumn();

        if (!$slika) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Profilna slika ne obstaja.']);
            exit;
        }

        // Posodobi bazo
        $stmt = $pdo->prepare('UPDATE Uporabnik SET profilna_slika = NULL WHERE id = :id');
        $stmt->execute(['id' => $uporabnik_id]);

        // Izbriši datoteko
        $imagePath = __DIR__ . '/../uploads/profilne/' . $slika;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profilna slika je bila uspešno odstranjena!'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Napaka pri brisanju slike.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Metoda ni podprta.']);
