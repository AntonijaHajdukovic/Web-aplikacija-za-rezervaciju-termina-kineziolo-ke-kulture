<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('PROFESSOR');

$professorId = (int) ($_SESSION['user_id'] ?? 0);

if ($professorId <= 0) {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Brzi pregled profesora
|--------------------------------------------------------------------------
|
| Sve statistike odnose se samo na activity_offerings
| trenutno prijavljenog profesora.
|
*/


/*
|--------------------------------------------------------------------------
| Broj aktivnih aktivnosti
|--------------------------------------------------------------------------
|
| Jedna aktivnost može imati više izvedbi kroz različite semestre.
| Zato brojimo aktivne offerings, a ne globalne activities zapise.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM activity_offerings o

    INNER JOIN activities a
        ON a.id = o.activity_id

    WHERE o.professor_id = :professor_id
      AND o.is_active = 1
      AND a.is_active = 1
");

$stmt->execute([
    'professor_id' => $professorId
]);

$activeActivities = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Broj aktivnih termina
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM activity_sessions s

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    WHERE o.professor_id = :professor_id
      AND o.is_active = 1
      AND a.is_active = 1
      AND s.is_active = 1
");

$stmt->execute([
    'professor_id' => $professorId
]);

$activeSessions = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Broj aktivnih rezervacija
|--------------------------------------------------------------------------
|
| Uzimamo samo rezervacije sa statusom RESERVED
| za aktivne termine profesorovih aktivnih izvedbi.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    WHERE o.professor_id = :professor_id
      AND o.is_active = 1
      AND a.is_active = 1
      AND s.is_active = 1
      AND r.status = 'RESERVED'
");

$stmt->execute([
    'professor_id' => $professorId
]);

$activeReservations = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Broj različitih studenata
|--------------------------------------------------------------------------
|
| Brojimo samo studente koji trenutno imaju aktivnu rezervaciju
| za profesorove aktivne izvedbe.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT r.student_id)

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    WHERE o.professor_id = :professor_id
      AND o.is_active = 1
      AND a.is_active = 1
      AND s.is_active = 1
      AND r.status = 'RESERVED'
");

$stmt->execute([
    'professor_id' => $professorId
]);

$activeStudents = (int) $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Profesor - TVZ Kineziološka kultura</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <style>

        .professor-welcome {
            margin-bottom: 32px;
        }

        .professor-welcome h1 {
            margin-bottom: 8px;
            font-size: 30px;
            line-height: 1.25;
        }

        .professor-welcome p {
            margin: 0;
            color: #5b6470;
            font-size: 17px;
            line-height: 1.5;
        }

        .professor-subtitle {
            margin-top: 6px !important;
            color: #6b7280 !important;
            font-size: 16px !important;
        }


        /*
         * KARTICE
         */

        .professor-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .professor-card {
            display: flex;
            flex-direction: column;
            min-height: 185px;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 28px 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            text-decoration: none;
            color: inherit;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .professor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.11);
        }

        .professor-card-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 30px;
        }

        .professor-card h2 {
            margin: 0 0 10px;
            color: #07558a;
            font-size: 22px;
            line-height: 1.25;
        }

        .professor-card p {
            margin: 0;
            color: #5b6470;
            line-height: 1.5;
            font-size: 16px;
        }

        .professor-card-link {
            margin-top: auto;
            padding-top: 20px;
            color: #07558a;
            font-weight: 700;
            font-size: 15px;
        }


        /*
         * BRZI PREGLED
         */

        .quick-overview {
            margin-top: 34px;
        }

        .quick-overview h2 {
            margin: 0 0 16px;
            color: #172b4d;
            font-size: 22px;
        }

        .overview-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .overview-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .overview-icon {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .overview-number {
            display: block;
            margin-bottom: 4px;
            color: #07558a;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .overview-label {
            color: #5b6470;
            font-size: 15px;
            line-height: 1.4;
        }


        /*
         * RESPONSIVE
         */

        @media (max-width: 1000px) {

            .overview-cards {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 800px) {

            .professor-cards {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 500px) {

            .professor-welcome h1 {
                font-size: 26px;
            }

            .professor-card {
                min-height: auto;
                padding: 24px;
            }

            .overview-cards {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">


        <!-- UVOD -->

        <section class="professor-welcome">

            <h1>
                Dobrodošli,
                <?= htmlspecialchars($_SESSION['first_name']) ?>!
            </h1>

            <p class="professor-subtitle">
                Na jednom mjestu možete upravljati aktivnostima,
                terminima, rezervacijama, prisustvom i obavijestima
                za studente.
            </p>

        </section>


        <!-- GLAVNE FUNKCIONALNOSTI -->

        <section class="professor-cards">


            <!-- AKTIVNOSTI -->

            <a
                href="activities.php"
                class="professor-card"
            >

                <div class="professor-card-icon">
                    🏃
                </div>

                <h2>
                    Aktivnosti
                </h2>

                <p>
                    Pregled i upravljanje aktivnostima
                    koje su dostupne studentima.
                </p>

                <div class="professor-card-link">
                    Otvori →
                </div>

            </a>


            <!-- TERMINI -->

            <a
                href="sessions.php"
                class="professor-card"
            >

                <div class="professor-card-icon">
                    🗓️
                </div>

                <h2>
                    Termini
                </h2>

                <p>
                    Upravljanje terminima održavanja
                    kinezioloških aktivnosti.
                </p>

                <div class="professor-card-link">
                    Otvori →
                </div>

            </a>


            <!-- REZERVACIJE -->

            <a
                href="reservations.php"
                class="professor-card"
            >

                <div class="professor-card-icon">
                    📋
                </div>

                <h2>
                    Rezervacije
                </h2>

                <p>
                    Pregled rezervacija studenata
                    za pojedine termine.
                </p>

                <div class="professor-card-link">
                    Otvori →
                </div>

            </a>


            <!-- PRISUSTVO I BODOVI -->

            <a
                href="attendance.php"
                class="professor-card"
            >

                <div class="professor-card-icon">
                    ✅
                </div>

                <h2>
                    Prisustvo i bodovi
                </h2>

                <p>
                    Evidencija prisutnosti studenata
                    i ostvarenih bodova.
                </p>

                <div class="professor-card-link">
                    Otvori →
                </div>

            </a>


            <!-- OBAVIJESTI -->

            <a
                href="announcements.php"
                class="professor-card"
            >

                <div class="professor-card-icon">
                    📢
                </div>

                <h2>
                    Obavijesti
                </h2>

                <p>
                    Kreiranje i pregled obavijesti
                    za studente.
                </p>

                <div class="professor-card-link">
                    Otvori →
                </div>

            </a>


        </section>


        <!-- BRZI PREGLED -->

        <section class="quick-overview">

            <h2>
                Brzi pregled
            </h2>


            <div class="overview-cards">


                <!-- AKTIVNE AKTIVNOSTI -->

                <div class="overview-card">

                    <div class="overview-icon">
                        🏃
                    </div>

                    <span class="overview-number">
                        <?= $activeActivities ?>
                    </span>

                    <span class="overview-label">
                        aktivnih aktivnosti
                    </span>

                </div>


                <!-- AKTIVNI TERMINI -->

                <div class="overview-card">

                    <div class="overview-icon">
                        🗓️
                    </div>

                    <span class="overview-number">
                        <?= $activeSessions ?>
                    </span>

                    <span class="overview-label">
                        aktivnih termina
                    </span>

                </div>


                <!-- REZERVACIJE -->

                <div class="overview-card">

                    <div class="overview-icon">
                        📋
                    </div>

                    <span class="overview-number">
                        <?= $activeReservations ?>
                    </span>

                    <span class="overview-label">
                        aktivnih rezervacija
                    </span>

                </div>


                <!-- STUDENTI -->

                <div class="overview-card">

                    <div class="overview-icon">
                        👥
                    </div>

                    <span class="overview-number">
                        <?= $activeStudents ?>
                    </span>

                    <span class="overview-label">
                        studenata s rezervacijom
                    </span>

                </div>


            </div>

        </section>


    </main>

</body>

</html>