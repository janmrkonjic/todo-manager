<?php
session_start();
require_once 'config/email.php'; // Za vsak slučaj, če bomo rabili

// Konfiguracija baze
require_once 'config/db.php';

// Preveri parametre
if (!isset($_GET['id']) || !isset($_GET['hash'])) {
    die("Neveljavna povezava.");
}

$skupina_id = (int)$_GET['id'];
$hash = $_GET['hash'];

// Preveri hash (mora se ujemati z logiko v skupina_detail.php)
$secret_salt = 'skrivni_kljuc_za_povabila_' . $skupina_id;
$expected_hash = md5($skupina_id . $secret_salt);

if ($hash !== $expected_hash) {
    die("Neveljavna ali potekla povezava.");
}

// Preveri, če je uporabnik prijavljen
if (!isset($_SESSION['uporabnik_id'])) {
    // Shrani namen pridružitve v sejo
    $_SESSION['pending_join_group'] = [
        'id' => $skupina_id,
        'hash' => $hash
    ];
    $_SESSION['error_message'] = "Za pridružitev skupini se morate najprej prijaviti ali registrirati.";
    header("Location: prijava.php");
    exit;
}

$uporabnik_id = $_SESSION['uporabnik_id'];

// Počisti morebitno napako o prijavi, če smo prišli nazaj po uspešni prijavi
if (isset($_SESSION['error_message']) && $_SESSION['error_message'] === "Za pridružitev skupini se morate najprej prijaviti ali registrirati.") {
    unset($_SESSION['error_message']);
}

// Preveri, če skupina obstaja
$stmt = $pdo->prepare("SELECT * FROM Skupina WHERE id = ?");
$stmt->execute([$skupina_id]);
$skupina = $stmt->fetch();

if (!$skupina) {
    die("Skupina ne obstaja.");
}

// Preveri, če je uporabnik že član
$stmt = $pdo->prepare("SELECT * FROM ClaniSkupine WHERE skupina_id = ? AND uporabnik_id = ?");
$stmt->execute([$skupina_id, $uporabnik_id]);

if ($stmt->fetch()) {
    // Že je član
    $_SESSION['success_message'] = "Ste že član skupine {$skupina['ime']}.";
    header("Location: skupina_detail.php?id=" . $skupina_id);
    exit;
}

// Dodaj uporabnika v skupino
$stmt = $pdo->prepare("INSERT INTO ClaniSkupine (uporabnik_id, skupina_id, datum_prikljucitve) VALUES (?, ?, NOW())");
$stmt->execute([$uporabnik_id, $skupina_id]);

$_SESSION['success_message'] = "Uspešno ste se pridružili skupini {$skupina['ime']}!";
header("Location: skupina_detail.php?id=" . $skupina_id);
exit;
