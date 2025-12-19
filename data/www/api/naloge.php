<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/functions.php';

api_check_auth();

try {
    require_once '../config/db.php';
    
    $method = $_SERVER['REQUEST_METHOD'];
    $uporabnik_id = $_SESSION['uporabnik_id'];
    
    // GET - pridobi naloge (lahko bi bilo za lazy loading)
    if ($method === 'GET') {
        http_response_code(501);
        echo json_encode(['success' => false, 'message' => 'GET metoda še ni implementirana.']);
        exit;
    }
    
    // POST - dodaj novo nalogo
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'dodaj') {
            $naslov = trim($_POST['naslov'] ?? '');
            $opis = trim($_POST['opis'] ?? '');
            $rok = $_POST['rok_izvedbe'] ?? '';
            $status = $_POST['status'] ?? 'neopravljeno';
            $skupina_id = isset($_POST['skupina_id']) && $_POST['skupina_id'] !== '' ? (int)$_POST['skupina_id'] : null;
            
            if (empty($naslov)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Naslov naloge je obvezen.']);
                exit;
            }
            
            // Če je skupinska naloga, preveri, če je uporabnik vodja
            if ($skupina_id !== null) {
                if (!je_vodja_skupine_db($pdo, $skupina_id, $uporabnik_id)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Nimate pravice dodajati naloge v to skupino.']);
                    exit;
                }
            }
            
            // Vstavi nalogo
            $stmt = $pdo->prepare("INSERT INTO Naloga (naslov, opis, rok_izvedbe, datum_ustvarjenja, status) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$naslov, $opis, $rok, $status]);
            
            $naloga_id = $pdo->lastInsertId();
            
            // Dodeli nalogo uporabniku ali skupini
            if ($skupina_id !== null) {
                $stmt = $pdo->prepare("INSERT INTO DodelitevNaloge (datum_dodelitve, naloga_id, uporabnik_id, skupina_id) VALUES (NOW(), ?, NULL, ?)");
                $stmt->execute([$naloga_id, $skupina_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO DodelitevNaloge (datum_dodelitve, naloga_id, uporabnik_id, skupina_id) VALUES (NOW(), ?, ?, NULL)");
                $stmt->execute([$naloga_id, $uporabnik_id]);
            }
            
            // Pridobi podatke o novi nalogi za prikaz
            $stmt = $pdo->prepare("
                SELECT n.*, 
                       CASE 
                           WHEN dn.uporabnik_id IS NOT NULL THEN 'osebna'
                           WHEN dn.skupina_id IS NOT NULL THEN s.ime
                           ELSE 'ostalo'
                       END as tip_naloge,
                       s.ime as ime_skupine,
                       s.barva as barva_skupine
                FROM Naloga n
                INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
                LEFT JOIN Skupina s ON dn.skupina_id = s.id
                WHERE n.id = ?
            ");
            $stmt->execute([$naloga_id]);
            $nova_naloga = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Naloga je bila uspešno dodana!',
                'naloga' => $nova_naloga
            ]);
            exit;
        }
        
        if ($action === 'opravi') {
            $naloga_id = (int)($_POST['naloga_id'] ?? 0);
            
            if ($naloga_id === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID naloge je obvezen.']);
                exit;
            }
            
            // Preveri, če ima uporabnik dostop do naloge
            if (!ima_dostop_do_naloge($pdo, $naloga_id, $uporabnik_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nimate dostopa do te naloge.']);
                exit;
            }
            
            // Označi nalogo kot opravljeno
            $stmt = $pdo->prepare("UPDATE Naloga SET status = 'opravljeno', datum_zakljucka = NOW() WHERE id = ?");
            $stmt->execute([$naloga_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Naloga je bila označena kot opravljena!',
                'naloga_id' => $naloga_id
            ]);
            exit;
        }
    }
    
    // DELETE - izbriši nalogo
    if ($method === 'DELETE') {
        parse_str(file_get_contents("php://input"), $_DELETE);
        $naloga_id = (int)($_DELETE['naloga_id'] ?? 0);
        
        if ($naloga_id === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID naloge je obvezen.']);
            exit;
        }
        
        // Preveri, če ima uporabnik dostop do brisanja (mora biti lastnik ali vodja skupine)
        $stmt = $pdo->prepare("
            SELECT n.*, dn.uporabnik_id, dn.skupina_id, s.vodja_id 
            FROM Naloga n
            INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
            LEFT JOIN Skupina s ON dn.skupina_id = s.id
            WHERE n.id = ?
        ");
        $stmt->execute([$naloga_id]);
        $naloga = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$naloga) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Naloga ne obstaja.']);
            exit;
        }
        
        // Preveri pravice
        $ima_pravico = false;
        if ($naloga['uporabnik_id'] == $uporabnik_id) {
            // Lastnik osebne naloge
            $ima_pravico = true;
        } elseif ($naloga['vodja_id'] == $uporabnik_id) {
            // Vodja skupine
            $ima_pravico = true;
        }
        
        if (!$ima_pravico) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nimate pravice brisati te naloge.']);
            exit;
        }
        
        // Izbriši nalogo
        $stmt = $pdo->prepare("DELETE FROM Naloga WHERE id = ?");
        $stmt->execute([$naloga_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Naloga je bila izbrisana!',
            'naloga_id' => $naloga_id
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
