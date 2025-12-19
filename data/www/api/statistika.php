<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../preveri_prijavo.php';

header('Content-Type: application/json');

// Preverjanje, da je uporabnik prijavljen in ima administratorske pravice
try {
    preveri_prijavo();
    preveri_vlogo([1]);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dostop zavrnjen', 'error' => $e->getMessage()]);
    exit;
}

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $statistika = [];

    // 1. Število nalog po statusu (opravljeno/neopravljeno)
    $stmt = $pdo->query("
        SELECT 
            status AS status,
            COUNT(*) AS stevilo
        FROM Naloga
        GROUP BY status
    ");
    $statistika['nalog_po_statusu'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Število nalog po tipu (osebne/skupinske)
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN dn.skupina_id IS NOT NULL THEN 'Skupinska'
                WHEN dn.uporabnik_id IS NOT NULL THEN 'Osebna'
                ELSE 'Nedoločeno'
            END AS tip,
            COUNT(*) AS stevilo
        FROM Naloga n
        LEFT JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
        GROUP BY tip
        HAVING tip != 'Nedoločeno'
    ");
    $statistika['nalog_po_tipu'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Naloge po rokih (tedenski histogram - zadnjih 8 tednov in prihodnjih 4 tedne)
    $stmt = $pdo->query("
        SELECT 
            CONCAT(
                YEAR(rok_izvedbe), '-W', 
                LPAD(WEEK(rok_izvedbe, 3), 2, '0')
            ) AS teden,
            DATE_FORMAT(DATE_ADD(rok_izvedbe, INTERVAL(1-DAYOFWEEK(rok_izvedbe)) DAY), '%Y-%m-%d') AS teden_zacetek,
            COUNT(*) AS stevilo,
            SUM(CASE WHEN status = 'opravljeno' THEN 1 ELSE 0 END) AS opravljenih
        FROM Naloga
        WHERE rok_izvedbe BETWEEN DATE_SUB(CURDATE(), INTERVAL 8 WEEK) 
            AND DATE_ADD(CURDATE(), INTERVAL 4 WEEK)
        GROUP BY teden, teden_zacetek
        ORDER BY teden_zacetek
    ");
    $statistika['nalog_po_rokih'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Aktivnost uporabnikov (št. komentarjev, opravljenih nalog)
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.uporabnisko_ime,
            v.naziv AS vloga,
            COALESCE(kom.stevilo_komentarjev, 0) AS stevilo_komentarjev,
            COALESCE(nal.stevilo_opravljenih, 0) AS stevilo_opravljenih,
            COALESCE(nal.stevilo_aktivnih, 0) AS stevilo_aktivnih
        FROM Uporabnik u
        LEFT JOIN Vloga v ON u.vloga_id = v.id
        LEFT JOIN (
            SELECT uporabnik_id, COUNT(*) AS stevilo_komentarjev
            FROM Komentar
            GROUP BY uporabnik_id
        ) kom ON u.id = kom.uporabnik_id
        LEFT JOIN (
            SELECT 
                dn.uporabnik_id,
                SUM(CASE WHEN n.status = 'opravljeno' THEN 1 ELSE 0 END) AS stevilo_opravljenih,
                SUM(CASE WHEN n.status = 'neopravljeno' THEN 1 ELSE 0 END) AS stevilo_aktivnih
            FROM DodelitevNaloge dn
            JOIN Naloga n ON dn.naloga_id = n.id
            WHERE dn.uporabnik_id IS NOT NULL
            GROUP BY dn.uporabnik_id
        ) nal ON u.id = nal.uporabnik_id
        ORDER BY (COALESCE(kom.stevilo_komentarjev, 0) + COALESCE(nal.stevilo_opravljenih, 0)) DESC
        LIMIT 10
    ");
    $statistika['aktivnost_uporabnikov'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Splošne statistike
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM Naloga) AS skupaj_nalog,
            (SELECT COUNT(*) FROM Naloga WHERE status = 'opravljeno') AS opravljenih_nalog,
            (SELECT COUNT(*) FROM Naloga WHERE status = 'neopravljeno') AS aktivnih_nalog,
            (SELECT COUNT(*) FROM Uporabnik) AS skupaj_uporabnikov,
            (SELECT COUNT(*) FROM Skupina) AS skupaj_skupin,
            (SELECT COUNT(*) FROM Komentar) AS skupaj_komentarjev
    ");
    $statistika['splosne'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Naloge po skupinah (top 10)
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.ime AS naziv,
            s.barva,
            COUNT(n.id) AS stevilo_nalog,
            SUM(CASE WHEN n.status = 'opravljeno' THEN 1 ELSE 0 END) AS opravljenih,
            SUM(CASE WHEN n.status = 'neopravljeno' THEN 1 ELSE 0 END) AS aktivnih
        FROM Skupina s
        LEFT JOIN DodelitevNaloge dn ON s.id = dn.skupina_id
        LEFT JOIN Naloga n ON dn.naloga_id = n.id
        GROUP BY s.id, s.ime, s.barva
        ORDER BY stevilo_nalog DESC
        LIMIT 10
    ");
    $statistika['nalog_po_skupinah'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Dnevna aktivnost (zadnjih 30 dni)
    $stmt = $pdo->query("
        SELECT 
            DATE(datum_ustvarjenja) AS datum,
            COUNT(*) AS stevilo_ustvarjenih
        FROM Naloga
        WHERE datum_ustvarjenja >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(datum_ustvarjenja)
        ORDER BY datum
    ");
    $statistika['dnevna_aktivnost'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $statistika
    ], JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Napaka pri pridobivanju statistike',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Napaka strežnika',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
