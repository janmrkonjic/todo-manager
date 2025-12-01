<?php
/**
 * API endpoint za preverjanje rokov nalog in pošiljanje opomnikov
 * 
 * Ta skripta naj se izvaja dnevno prek cron job-a:
 * 0 9 * * * php /pot/do/www/api/preveri_roke.php
 */

// Zahtevamo absolutno pot do config datotek
require_once __DIR__ . '/../config/email.php';

// Povezava z bazo
$dsn = 'mysql:host=mysql;port=3306;dbname=todo_manager;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'superVarnoGeslo', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

/**
 * Poišče vse neopravljene naloge, ki imajo rok čez 24 ur (±1 ura tolerance)
 * 
 * @param PDO $pdo Podatkovna povezava
 * @param int $hours Število ur vnaprej (privzeto 24)
 * @return array Seznam nalog
 */
function poisci_naloge_za_opomnik($pdo, $hours = 24) {
    // Za testiranje: če je nastavljen GET parameter 'test_hours', uporabi to vrednost
    if (isset($_GET['test_hours']) && is_numeric($_GET['test_hours'])) {
        $hours = (int)$_GET['test_hours'];
        echo "⚠️  TESTNI NAČIN: Preverjam naloge z rokom čez $hours ur\n\n";
    }
    
    $sql = "
        SELECT 
            n.id,
            n.naslov,
            n.opis,
            n.rok_izvedbe,
            n.status
        FROM Naloga n
        WHERE n.status = 'neopravljeno'
        AND n.rok_izvedbe >= DATE_ADD(NOW(), INTERVAL :hours_min MINUTE) 
        AND n.rok_izvedbe < DATE_ADD(NOW(), INTERVAL :hours_max MINUTE)
    ";
    
    // Privzeto iščemo naloge, ki imajo rok čez 24 ur +/- 30 minut (za urni cron job)
    // Pretvorimo ure v minute za bolj natančen izračun
    $target_minutes = $hours * 60;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'hours_min' => $target_minutes - 29, // 29 minut prej (skupaj z 0 je to 30 min)
        'hours_max' => $target_minutes + 31  // 31 minut kasneje (ekskluzivno, torej do 30 min)
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Najde vse uporabnike, ki so dodeljeni k določeni nalogi
 */
function poisci_uporabnike_naloge($pdo, $naloga_id) {
    // Preveri, če je naloga dodeljena posamezniku ali skupini
    $sql = "
        SELECT 
            d.uporabnik_id,
            d.skupina_id
        FROM DodelitevNaloge d
        WHERE d.naloga_id = ?
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$naloga_id]);
    $dodelitev = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dodelitev) {
        return [];
    }
    
    $uporabniki = [];
    
    // Če je naloga dodeljena posamezniku
    if ($dodelitev['uporabnik_id']) {
        $sql = "
            SELECT 
                u.id,
                u.uporabnisko_ime,
                u.email,
                NULL as skupina_ime
            FROM Uporabnik u
            WHERE u.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dodelitev['uporabnik_id']]);
        $uporabniki = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Če je naloga dodeljena skupini
    elseif ($dodelitev['skupina_id']) {
        $sql = "
            SELECT 
                u.id,
                u.uporabnisko_ime,
                u.email,
                s.ime as skupina_ime
            FROM ClaniSkupine cs
            JOIN Uporabnik u ON cs.uporabnik_id = u.id
            JOIN Skupina s ON cs.skupina_id = s.id
            WHERE cs.skupina_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dodelitev['skupina_id']]);
        $uporabniki = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $uporabniki;
}

/**
 * Generira HTML vsebino za email opomnik
 */
function generiraj_email_opomnik($naloga, $uporabnisko_ime) {
    $rok = date('d.m.Y H:i', strtotime($naloga['rok_izvedbe']));
    $skupina_info = $naloga['skupina_ime'] ? "<p><strong>Skupina:</strong> {$naloga['skupina_ime']}</p>" : "";
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .header { background-color: #f8f9fa; padding: 10px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
            .task-card { background-color: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 4px; margin: 15px 0; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>⏰ Opomnik za nalogo</h2>
            </div>
            
            <p>Pozdravljeni <strong>$uporabnisko_ime</strong>,</p>
            
            <p>Obveščamo vas, da se rok za naslednjo nalogo izteče čez <strong>24 ur</strong>:</p>
            
            <div class='task-card'>
                <h3>{$naloga['naslov']}</h3>
                <p>{$naloga['opis']}</p>
                <hr style='border: 0; border-top: 1px solid #e6dbb9;'>
                <p><strong>📅 Rok izvedbe:</strong> $rok</p>
                $skupina_info
            </div>
            
            <p>Prosimo, da nalogo opravite pravočasno in jo označite kot opravljeno v aplikaciji.</p>
            
            <p><a href='http://localhost:8000' class='btn'>Odpri aplikacijo</a></p>
            
            <div class='footer'>
                <p>To je avtomatsko sporočilo iz sistema Todo Manager.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Glavna funkcija za procesiranje opomnikov
 */
function procesiraj_opomnike() {
    global $pdo;
    
    $naloge = poisci_naloge_za_opomnik($pdo);
    
    $statistika = [
        'naloge_pregledane' => count($naloge),
        'emaili_poslani' => 0,
        'emaili_neuspeli' => 0,
        'napake' => []
    ];
    
    foreach ($naloge as $naloga) {
        $uporabniki = poisci_uporabnike_naloge($pdo, $naloga['id']);
        
        foreach ($uporabniki as $uporabnik) {
            // Združi podatke za email
            $naloga_podatki = array_merge($naloga, [
                'skupina_ime' => $uporabnik['skupina_ime']
            ]);
            
            // Generiraj HTML email
            $subject = "⏰ Opomnik: Rok naloge \"{$naloga['naslov']}\" se izteče čez 1 dan";
            $message = generiraj_email_opomnik($naloga_podatki, $uporabnik['uporabnisko_ime']);
            
            // Pošlji email
            $uspeh = poslji_email($uporabnik['email'], $subject, $message);
            
            if ($uspeh) {
                $statistika['emaili_poslani']++;
                echo "✓ Email poslan: {$uporabnik['email']} - Naloga: {$naloga['naslov']}\n";
            } else {
                $statistika['emaili_neuspeli']++;
                $statistika['napake'][] = "Neuspeh: {$uporabnik['email']} - Naloga: {$naloga['naslov']}";
                echo "✗ Napaka pri pošiljanju: {$uporabnik['email']}\n";
            }
        }
    }
    
    return $statistika;
}

// Izvedi samo, če se izvaja iz ukazne vrstice (cron) ali z ?manual=1 parametrom
if (php_sapi_name() === 'cli' || (isset($_GET['manual']) && $_GET['manual'] === '1')) {
    echo "=== Preverjanje rokov nalog ===\n";
    echo "Datum in čas: " . date('Y-m-d H:i:s') . "\n\n";
    
    try {
        $statistika = procesiraj_opomnike();
        
        echo "\n=== Poročilo ===\n";
        echo "Pregledanih nalog: {$statistika['naloge_pregledane']}\n";
        echo "Poslanih emailov: {$statistika['emaili_poslani']}\n";
        echo "Neuspelih emailov: {$statistika['emaili_neuspeli']}\n";
        
        if (!empty($statistika['napake'])) {
            echo "\nNapake:\n";
            foreach ($statistika['napake'] as $napaka) {
                echo "- $napaka\n";
            }
        }
        
        exit(0);
    } catch (Exception $e) {
        echo "NAPAKA: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Če se dostopa preko brskalnika brez parametra
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Ta endpoint se lahko izvaja samo prek cron job-a ali z ?manual=1 parametrom.'
    ]);
    exit;
}
