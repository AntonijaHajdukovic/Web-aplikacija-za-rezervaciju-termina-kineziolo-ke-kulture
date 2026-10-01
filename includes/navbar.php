<?php

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

?>

<header>

    <img
        src="../images/tvz-logo2.png"
        alt="TVZ - Tehničko veleučilište u Zagrebu"
        class="logo"
    >

    <nav class="main-nav">

        <?php if ($role === 'ADMIN'): ?>

            <a
                href="index.php"
                class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
            >
                Početna
            </a>

            <a
                href="users.php"
                class="<?= $currentPage === 'users.php' ? 'active' : '' ?>"
            >
                Korisnici
            </a>

            <a
                href="activities.php"
                class="<?= $currentPage === 'activities.php' ? 'active' : '' ?>"
            >
                Aktivnosti
            </a>

            <a
                href="sessions.php"
                class="<?= $currentPage === 'sessions.php' ? 'active' : '' ?>"
            >
                Termini
            </a>

            <a
                href="courses.php"
                class="<?= $currentPage === 'courses.php' ? 'active' : '' ?>"
            >
                Kolegiji
            </a>

            <a
                href="academic_years.php"
                class="<?= $currentPage === 'academic_years.php' ? 'active' : '' ?>"
            >
                Akademske godine
            </a>

            <a
                href="announcements.php"
                class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>"
            >
                Obavijesti
            </a>
            

        <?php elseif ($role === 'STUDENT'): ?>

            <a
                href="index.php"
                class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
            >
                Početna
            </a>

            <a
                href="activities.php"
                class="<?= $currentPage === 'activities.php' ? 'active' : '' ?>"
            >
                Aktivnosti
            </a>

            <a
                href="my_reservations.php"
                class="<?= $currentPage === 'my_reservations.php' ? 'active' : '' ?>"
            >
                Moje rezervacije
            </a>

            <a
                href="points.php"
                class="<?= $currentPage === 'points.php' ? 'active' : '' ?>"
            >
                Moji bodovi
            </a>

            <a
                href="announcements.php"
                class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>"
            >
                Obavijesti
            </a>

            <a
                href="locations.php"
                class="<?= $currentPage === 'locations.php' ? 'active' : '' ?>"
            >
                Lokacije
            </a>

        <?php elseif ($role === 'PROFESSOR'): ?>

            <a
                href="index.php"
                class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
            >
                Početna
            </a>

            <a
                href="activities.php"
                class="<?= $currentPage === 'activities.php' ? 'active' : '' ?>"
            >
                Aktivnosti
            </a>

            <a
                href="sessions.php"
                class="<?= $currentPage === 'sessions.php' ? 'active' : '' ?>"
            >
                Termini
            </a>

            <a
                href="reservations.php"
                class="<?= $currentPage === 'reservations.php' ? 'active' : '' ?>"
            >
                Rezervacije
            </a>

            <a
                href="attendance.php"
                class="<?= $currentPage === 'attendance.php' ? 'active' : '' ?>"
            >
                Prisustvo i bodovi
            </a>

            <a
                href="announcements.php"
                class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>"
            >
                Obavijesti
            </a>

        <?php endif; ?>

    </nav>

    <div class="user-info">

        <span class="user-name">
            <?= htmlspecialchars(
                ($_SESSION['first_name'] ?? '') . ' ' .
                ($_SESSION['last_name'] ?? '')
            ) ?>
        </span>

        <a href="../logout.php" class="logout">
            Odjava
        </a>

    </div>

</header>