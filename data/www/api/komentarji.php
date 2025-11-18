<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../preveri_prijavo.php';

// Preveri prijavo - če ni prijavljen, vrne 401
if (!isset($_SESSION['uporabnik_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Niste prijavljeni.']);
    exit;
}

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $uporabnik_id = $_SESSION['uporabnik_id'];
    
    // POST - dodaj nov komentar
    if ($method === 'POST') {
        $naloga_id = (int)($_POST['naloga_id'] ?? 0);
        $besedilo = trim($_POST['besedilo'] ?? '');
        
        if ($naloga_id === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID naloge je obvezen.']);
            exit;
        }
        
        if (empty($besedilo)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Besedilo komentarja ne sme biti prazno.']);
            exit;
        }
        
        // Preveri, če ima uporabnik dostop do naloge
        $stmt = $pdo->prepare("
            SELECT n.* FROM Naloga n
            INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
            LEFT JOIN ClaniSkupine cs ON dn.skupina_id = cs.skupina_id
            WHERE n.id = ? AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
        ");
        $stmt->execute([$naloga_id, $uporabnik_id, $uporabnik_id]);
        $naloga = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$naloga) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nimate dostopa do te naloge.']);
            exit;
        }
        
        // Dodaj komentar
        $stmt = $pdo->prepare("
            INSERT INTO Komentar (naloga_id, uporabnik_id, besedilo, datum_vnosa) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$naloga_id, $uporabnik_id, $besedilo]);
        
        $komentar_id = $pdo->lastInsertId();
        
        // Pridobi podatke o novem komentarju
        $stmt = $pdo->prepare("
            SELECT k.*, u.uporabnisko_ime 
            FROM Komentar k 
            JOIN Uporabnik u ON k.uporabnik_id = u.id 
            WHERE k.id = ?
        ");
        $stmt->execute([$komentar_id]);
        $komentar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Komentar je bil uspešno dodan!',
            'komentar' => $komentar
        ]);
        exit;
    }
    
    // DELETE - izbriši komentar
    if ($method === 'DELETE') {
        parse_str(file_get_contents("php://input"), $_DELETE);
        $komentar_id = (int)($_DELETE['komentar_id'] ?? 0);
        
        if ($komentar_id === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID komentarja je obvezen.']);
            exit;
        }
        
        // Preveri, če je uporabnik avtor komentarja
        $stmt = $pdo->prepare("SELECT * FROM Komentar WHERE id = ?");
        $stmt->execute([$komentar_id]);
        $komentar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$komentar) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Komentar ne obstaja.']);
            exit;
        }
        
        if ($komentar['uporabnik_id'] != $uporabnik_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Lahko brišete samo svoje komentarje.']);
            exit;
        }
        
        // Izbriši komentar
        $stmt = $pdo->prepare("DELETE FROM Komentar WHERE id = ?");
        $stmt->execute([$komentar_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Komentar je bil izbrisan!',
            'komentar_id' => $komentar_id
        ]);
        exit;
    }
    
    // Nepodprta metoda
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda ni podprta.']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka v bazi: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka: ' . $e->getMessage()]);
}
