<?php
// Middleware za preverjanje prijave uporabnika
// Vključi to datoteko na začetku vsake zaščitene strani

session_start();

function preveri_prijavo() {
    if (!isset($_SESSION['uporabnik_id'])) {
        header("Location: prijava.php");
        exit;
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
