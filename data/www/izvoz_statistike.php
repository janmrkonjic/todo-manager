<?php
session_start();
require_once 'preveri_prijavo.php';

// Onemogočimo prikazovanje napak, da ne pokvarijo CSV izhoda
ini_set('display_errors', 0);

// Preverimo, če je uporabnik prijavljen in je administrator
preveri_prijavo();
preveri_vlogo([1]);

try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Poizvedba za statistiko uporabnikov
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.uporabnisko_ime,
            u.email,
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
        ORDER BY u.uporabnisko_ime ASC
    ");
    
    $uporabniki = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Nastavimo glave za prenos CSV datoteke
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=statistika_uporabnikov_' . date('Y-m-d') . '.csv');

    // Odpremo izhodni tok
    $output = fopen('php://output', 'w');

    // Dodamo BOM za pravilno prikazovanje šumnikov v Excelu
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Zapišemo naslovno vrstico
    fputcsv($output, ['ID', 'Uporabniško ime', 'Email', 'Vloga', 'Št. komentarjev', 'Opravljene naloge', 'Aktivne naloge'], ';', '"', "\\");

    // Zapišemo podatke
    foreach ($uporabniki as $uporabnik) {
        fputcsv($output, [
            $uporabnik['id'],
            $uporabnik['uporabnisko_ime'],
            $uporabnik['email'],
            $uporabnik['vloga'],
            $uporabnik['stevilo_komentarjev'],
            $uporabnik['stevilo_opravljenih'],
            $uporabnik['stevilo_aktivnih']
        ], ';', '"', "\\");
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Napaka pri povezavi z bazo: " . $e->getMessage());
}
?>
