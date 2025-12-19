<?php

function preveri_prijavo() {
    if (!isset($_SESSION['uporabnik_id'])) {
        header("Location: prijava.php");
        exit;
    }
    
    // Če je administrator, preusmeri na administracijo (razen če je že tam ali na uporabniki.php ali statistika.php)
    if (isset($_SESSION['vloga_id']) && $_SESSION['vloga_id'] == 1) {
        $current_page = basename($_SERVER['PHP_SELF']);
        $current_dir = basename(dirname($_SERVER['PHP_SELF']));
        
        // Ne preusmerjaj, če je uporabnik na dovoljenih straneh ali v API direktoriju
        $allowed_pages = ['administracija.php', 'uporabniki.php', 'statistika.php', 'profil.php', 'odjava.php', 'izvoz_statistike.php'];
        
        if (!in_array($current_page, $allowed_pages) && $current_dir !== 'api') {
            header("Location: administracija.php");
            exit;
        }
    }
}

function preveri_vlogo($dovoljene_vloge) {
    if (!isset($_SESSION['vloga_id']) || !in_array($_SESSION['vloga_id'], $dovoljene_vloge)) {
        http_response_code(403);
        die("Nimate dostopa do te strani.");
    }
}

function je_administrator() {
    return isset($_SESSION['vloga_id']) && $_SESSION['vloga_id'] == 1;
}

function je_vodja_skupine() {
    return isset($_SESSION['vloga_id']) && $_SESSION['vloga_id'] == 2;
}

function je_clan_skupine() {
    return isset($_SESSION['vloga_id']) && $_SESSION['vloga_id'] == 3;
}

function je_samostojni_uporabnik() {
    return isset($_SESSION['vloga_id']) && $_SESSION['vloga_id'] == 4;
}

function get_user_profile_image($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare('SELECT profilna_slika FROM Uporabnik WHERE id = :id');
        $stmt->execute(['id' => $user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return null;
    }
}

function api_check_auth() {
    if (!isset($_SESSION['uporabnik_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Niste prijavljeni.']);
        exit;
    }
}


// Preveri, če je uporabnik član določene skupine 
function je_clan_skupine_db($pdo, $skupina_id, $uporabnik_id) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM ClaniSkupine 
        WHERE skupina_id = ? AND uporabnik_id = ?
    ");
    $stmt->execute([$skupina_id, $uporabnik_id]);
    return $stmt->fetchColumn() !== false;
}

// Preveri, če je uporabnik vodja določene skupine
function je_vodja_skupine_db($pdo, $skupina_id, $uporabnik_id) {
    $stmt = $pdo->prepare("SELECT vodja_id FROM Skupina WHERE id = ?");
    $stmt->execute([$skupina_id]);
    $skupina = $stmt->fetch(PDO::FETCH_ASSOC);
    return $skupina && $skupina['vodja_id'] == $uporabnik_id;
}

// Preveri, če ima uporabnik dostop do naloge (je dodeljen ali član skupine)
function ima_dostop_do_naloge($pdo, $naloga_id, $uporabnik_id) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM Naloga n
        INNER JOIN DodelitevNaloge dn ON n.id = dn.naloga_id
        LEFT JOIN ClaniSkupine cs ON dn.skupina_id = cs.skupina_id
        WHERE n.id = ? AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
    ");
    $stmt->execute([$naloga_id, $uporabnik_id, $uporabnik_id]);
    return $stmt->fetchColumn() !== false;
}
