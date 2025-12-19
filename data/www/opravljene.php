<?php
session_start();
require_once 'includes/functions.php';
preveri_prijavo();

try {
    require_once 'config/db.php';
    
    $uporabnik_id = $_SESSION['uporabnik_id'];
    
    $uporabnik_slika = get_user_profile_image($pdo, $uporabnik_id);
    
    // Pridobi opravljene naloge uporabnika (osebne in skupinske)
    $stmt = $pdo->prepare("
        SELECT DISTINCT n.*, 
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
        LEFT JOIN ClaniSkupine cs ON s.id = cs.skupina_id AND cs.uporabnik_id = ?
        WHERE n.status = 'opravljeno'
        AND (dn.uporabnik_id = ? OR cs.uporabnik_id = ?)
        ORDER BY n.datum_zakljucka DESC
    ");
    $stmt->execute([$uporabnik_id, $uporabnik_id, $uporabnik_id]);
    $opravljene_naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    http_response_code(500);
    die("DB napaka: " . htmlspecialchars($e->getMessage()));
}

$pageTitle = 'Opravljene naloge - Todo Manager';
$activePage = 'opravljene.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>

    <div class="task-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-check-circle-fill text-success"></i> Opravljene naloge</h1>
        </div>

        <?php if (empty($opravljene_naloge)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <p class="text-muted mt-3" style="font-size: 1.2rem;">Še nimate opravljenih nalog.</p>
            </div>
        <?php else: ?>
            <div class="task-section-title">
                Vse opravljene <span class="task-count"><?= count($opravljene_naloge) ?> <?= count($opravljene_naloge) == 1 ? 'naloga' : 'naloge' ?></span>
            </div>
            
            <?php foreach ($opravljene_naloge as $naloga): ?>
                <div class="task-item completed-task">
                    <div class="task-checkbox completed">
                        <i class="bi bi-check text-success" style="font-size: 1rem; font-weight: bold;"></i>
                    </div>
                    <div class="task-content">
                        <div class="task-title" style="text-decoration: line-through; color: #808080;">
                            <?= htmlspecialchars($naloga['naslov']) ?>
                            <?php if ($naloga['tip_naloge'] != 'osebna' && $naloga['ime_skupine']): ?>
                                <span class="badge ms-2" style="font-size: 0.7em; background-color: <?= htmlspecialchars($naloga['barva_skupine'] ?? '#17a2b8') ?>; color: white;">
                                    <i class="bi bi-people"></i> <?= htmlspecialchars($naloga['ime_skupine']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-description" style="color: #a0a0a0;">
                            <?= htmlspecialchars($naloga['opis']) ?>
                        </div>
                        <div class="task-meta" style="font-size: 0.85rem; color: #999; margin-top: 8px;">
                            <i class="bi bi-check-circle"></i> Opravljeno: <?= date('d.m.Y H:i', strtotime($naloga['datum_zakljucka'])) ?>
                            <?php if ($naloga['rok_izvedbe']): ?>
                                | <i class="bi bi-calendar"></i> Rok: <?= date('d.m.Y H:i', strtotime($naloga['rok_izvedbe'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <style>
        .completed-task {
            opacity: 0.8;
            background-color: #f8f9fa;
        }
        
        .task-checkbox.completed {
            background-color: #d4edda;
            border-color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

<?php include 'includes/footer.php'; ?>
