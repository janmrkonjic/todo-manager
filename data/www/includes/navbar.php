<?php
$activePage = $activePage ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-check2-circle"></i> Todo Manager
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (function_exists('je_administrator') && je_administrator()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'administracija.php' ? 'active' : '' ?>" href="administracija.php">
                        <i class="bi bi-gear"></i> Administracija
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'uporabniki.php' ? 'active' : '' ?>" href="uporabniki.php">
                        <i class="bi bi-people-fill"></i> Uporabniki
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'statistika.php' ? 'active' : '' ?>" href="statistika.php">
                        <i class="bi bi-graph-up"></i> Statistika
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Domov
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'opravljene.php' ? 'active' : '' ?>" href="opravljene.php">
                        <i class="bi bi-check-circle"></i> Opravljene naloge
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'skupine.php' ? 'active' : '' ?>" href="skupine.php">
                        <i class="bi bi-people"></i> Moje skupine
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?= $activePage === 'profil.php' ? 'active' : '' ?>" href="profil.php">
                        <?php if (isset($uporabnik_slika) && $uporabnik_slika && file_exists('uploads/profilne/' . $uporabnik_slika)): ?>
                            <img src="uploads/profilne/<?= htmlspecialchars($uporabnik_slika) ?>" 
                                 alt="Profil" 
                                 class="rounded-circle me-2" 
                                 style="width: 32px; height: 32px; object-fit: cover;"
                                 data-lazy="true"
                                 loading="lazy">
                        <?php else: ?>
                            <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($_SESSION['uporabnisko_ime'] ?? '') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="odjava.php">
                        <i class="bi bi-box-arrow-right"></i> Odjava
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
