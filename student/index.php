<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnog kolegija studenta
|--------------------------------------------------------------------------
|
| Studentov kolegij određuje:
|
| - koji kolegij trenutno pohađa
| - kojem semestru studija pripada
| - koliko bodova mora ostvariti
|
| Konkretna akademska godina određuje se preko
| student_courses.academic_year_id.
|
*/

$stmt = $pdo->prepare(
    "
    SELECT
        c.id AS course_id,
        c.name AS course_name,
        c.semester_number,
        c.required_points,
        sc.status,

        ay.id AS academic_year_id,
        ay.name AS academic_year_name

    FROM student_courses sc

    INNER JOIN courses c
        ON sc.course_id = c.id

    INNER JOIN academic_years ay
        ON ay.id = sc.academic_year_id

    WHERE sc.student_id = :student_id

      AND sc.academic_year_id = (
            SELECT id
            FROM academic_years
            WHERE is_active = 1
            LIMIT 1
      )

      AND sc.status = 'ENROLLED'

      AND c.is_active = 1

    LIMIT 1
    "
);

$stmt->execute([
    'student_id' => $userId
]);

$course = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Ukupni ostvareni bodovi studenta
|--------------------------------------------------------------------------
|
| Bodovi se sada računaju za:
|
| 1. aktivnu akademsku godinu
| 2. trenutni semestar studenta
|
| Opće aktivnosti bez semester_id mogu sudjelovati u bodovima
| tijekom aktivne akademske godine.
|
*/

$totalPoints = 0;


if ($course) {

    $semesterNumber =
        (int) $course['semester_number'];


    $stmt = $pdo->prepare(
        "
        SELECT
            COALESCE(
                SUM(r.points_awarded),
                0
            )

        FROM reservations r

        INNER JOIN activity_sessions s
            ON s.id = r.session_id

        INNER JOIN activity_offerings o
            ON o.id = s.offering_id

        LEFT JOIN semesters sem
            ON sem.id = o.semester_id

        WHERE r.student_id = :student_id

          AND r.status IN (
                'ATTENDED',
                'NO_SHOW'
          )

          AND (
                /*
                | Opće aktivnosti bez konkretnog semestra.
                |
                | Kod njih koristimo datum rezervacije kako bismo
                | osigurali da pripadaju aktivnoj akademskoj godini.
                */

                (
                    o.semester_id IS NULL

                    AND r.reservation_date BETWEEN

                        (
                            SELECT
                                MIN(s1.start_date)

                            FROM semesters s1

                            WHERE s1.academic_year_id = (
                                SELECT id
                                FROM academic_years
                                WHERE is_active = 1
                                LIMIT 1
                            )
                        )

                    AND

                        (
                            SELECT
                                MAX(s2.end_date)

                            FROM semesters s2

                            WHERE s2.academic_year_id = (
                                SELECT id
                                FROM academic_years
                                WHERE is_active = 1
                                LIMIT 1
                            )
                        )
                )


                OR


                /*
                | Aktivnosti vezane uz konkretni semestar.
                |
                | Mora odgovarati:
                |
                | - aktivnoj akademskoj godini
                | - semestru studija trenutnog kolegija
                */

                (
                    o.semester_id IS NOT NULL

                    AND sem.academic_year_id = (
                        SELECT id
                        FROM academic_years
                        WHERE is_active = 1
                        LIMIT 1
                    )

                    AND sem.semester_number = (
                        CASE

                            WHEN :semester_number = 1
                                THEN 1

                            WHEN :semester_number = 2
                                THEN 2

                            WHEN :semester_number = 3
                                THEN 1

                            WHEN :semester_number = 4
                                THEN 2

                            ELSE 0

                        END
                    )
                )
          )
        "
    );


    $stmt->execute([
        'student_id' =>
            $userId,

        'semester_number' =>
            $semesterNumber
    ]);


    $totalPoints =
        (int) $stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| Dohvat nadolazećih rezervacija
|--------------------------------------------------------------------------
|
| Prikazuju se samo rezervacije:
|
| - trenutno prijavljenog studenta
| - statusa RESERVED
| - koje još nisu prošle
|
| Aktivnost se sada dohvaća preko:
|
| reservation
|     ↓
| activity_session
|     ↓
| activity_offering
|     ↓
| activity
|
*/

$stmt = $pdo->prepare(
    "
    SELECT

        r.reservation_date,

        a.name,
        a.points,

        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,

        u.first_name AS professor_first_name,
        u.last_name AS professor_last_name

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN users u
        ON u.id = o.professor_id

    WHERE r.student_id = :student_id

      AND r.status = 'RESERVED'

      AND o.is_active = 1

      AND a.is_active = 1

      AND s.is_active = 1

      AND (
            r.reservation_date > CURDATE()

            OR (

                r.reservation_date = CURDATE()

                AND s.start_time >= CURTIME()

            )
      )

    ORDER BY

        r.reservation_date ASC,
        s.start_time ASC

    LIMIT 3
    "
);

$stmt->execute([
    'student_id' => $userId
]);

$upcomingReservations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Izračun napretka u bodovima
|--------------------------------------------------------------------------
*/

$requiredPoints = $course
    ? (int) $course['required_points']
    : 0;


if ($requiredPoints > 0) {

    $pointsProgress = (
        $totalPoints / $requiredPoints
    ) * 100;


    $pointsProgress = max(
        0,
        min(
            100,
            $pointsProgress
        )
    );


    $pointsMissing = max(
        0,
        $requiredPoints - $totalPoints
    );

} else {

    $pointsProgress = 0;

    $pointsMissing = 0;
}


/*
|--------------------------------------------------------------------------
| Nazivi dana
|--------------------------------------------------------------------------
*/

$days = [

    1 => 'Ponedjeljak',

    2 => 'Utorak',

    3 => 'Srijeda',

    4 => 'Četvrtak',

    5 => 'Petak',

    6 => 'Subota',

    7 => 'Nedjelja'

];

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <title>
        Student - TVZ Kineziološka kultura
    </title>


    <style>

        /*
        |--------------------------------------------------------------------------
        | Page header
        |--------------------------------------------------------------------------
        */

        .student-welcome {
            margin-bottom: 32px;
        }

        .student-welcome h1 {
            margin-bottom: 8px;
            font-size: 30px;
            line-height: 1.25;
        }

        .student-welcome p {
            margin: 0;
            color: #5b6470;
            font-size: 17px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        .student-dashboard {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 28px;
            margin-bottom: 36px;
        }


        /*
        |--------------------------------------------------------------------------
        | Main cards
        |--------------------------------------------------------------------------
        */

        .dashboard-card {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }


        /*
        |--------------------------------------------------------------------------
        | Course card
        |--------------------------------------------------------------------------
        */

        .course-label {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 12px;
            background-color: #eaf4fa;
            color: #07558a;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .course-card h2 {
            margin: 0 0 12px;
            color: #07558a;
            font-size: 28px;
            line-height: 1.25;
        }

        .course-status {
            margin: 0 0 24px;
            color: #4b5563;
            font-size: 16px;
        }

        .course-status strong {
            color: #172b4d;
        }


        /*
        |--------------------------------------------------------------------------
        | Points progress
        |--------------------------------------------------------------------------
        */

        .points-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .points-title {
            color: #172b4d;
            font-size: 17px;
            font-weight: bold;
        }

        .points-value {
            color: #07558a;
            font-size: 22px;
            font-weight: bold;
            white-space: nowrap;
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background-color: #e5e7eb;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: #07558a;
            border-radius: 20px;
            transition: width 0.3s ease;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 9px;
            color: #6b7280;
            font-size: 14px;
        }

        .points-message {
            margin-top: 18px;
            color: #4b5563;
            font-size: 16px;
            line-height: 1.5;
        }

        .points-message strong {
            color: #172b4d;
        }


        /*
        |--------------------------------------------------------------------------
        | Points summary card
        |--------------------------------------------------------------------------
        */

        .points-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .points-card-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
        }

        .dashboard-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background-color: #eaf4fa;
            border-radius: 14px;
            font-size: 32px;
        }

        .points-card-title h2 {
            margin: 0 0 5px;
            color: #07558a;
            font-size: 23px;
        }

        .points-card-title p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
        }

        .big-points {
            margin-bottom: 25px;
            color: #172b4d;
            font-size: 42px;
            font-weight: bold;
            line-height: 1;
        }

        .big-points span {
            color: #6b7280;
            font-size: 21px;
            font-weight: normal;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .dashboard-button {
            display: inline-block;
            align-self: flex-start;
            padding: 12px 20px;
            background-color: #07558a;
            color: #ffffff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            transition: background-color 0.2s ease,
                        transform 0.2s ease;
        }

        .dashboard-button:hover {
            background-color: #064a77;
            transform: translateY(-1px);
        }


        /*
        |--------------------------------------------------------------------------
        | Quick access
        |--------------------------------------------------------------------------
        */

        .quick-access-section {
            margin-bottom: 36px;
        }

        .section-title {
            margin: 0 0 16px;
            color: #172b4d;
            font-size: 22px;
        }

        .quick-access {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .quick-card {
            display: flex;
            align-items: center;
            gap: 18px;
            min-height: 105px;
            padding: 20px 22px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            text-decoration: none;
            transition: transform 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .quick-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(0, 0, 0, 0.10);
        }

        .quick-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background-color: #eaf4fa;
            border-radius: 12px;
            font-size: 28px;
        }

        .quick-content h3 {
            margin: 0 0 5px;
            color: #07558a;
            font-size: 19px;
        }

        .quick-content p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | Upcoming reservations
        |--------------------------------------------------------------------------
        */

        .upcoming-section {
            margin-top: 5px;
        }

        .upcoming-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 16px;
        }

        .upcoming-count {
            color: #07558a;
            font-size: 15px;
            font-weight: bold;
        }

        .upcoming-card {
            background-color: #ffffff;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 14px;
        }

        .upcoming-card h3 {
            color: #07558a;
            font-size: 20px;
            line-height: 1.3;
            margin: 0 0 16px;
        }

        .upcoming-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
        }

        .upcoming-detail {
            color: #4b5563;
            font-size: 15px;
            line-height: 1.45;
        }

        .upcoming-detail strong {
            color: #172b4d;
            margin-right: 4px;
        }


        /*
        |--------------------------------------------------------------------------
        | Upcoming link
        |--------------------------------------------------------------------------
        */

        .upcoming-link {
            display: inline-block;
            margin-top: 4px;
            padding: 10px 17px;
            background-color: #07558a;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .upcoming-link:hover {
            background-color: #064a77;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty states
        |--------------------------------------------------------------------------
        */

        .no-upcoming,
        .no-course {
            background-color: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            color: #5b6470;
            font-size: 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 950px) {

            .student-dashboard {
                grid-template-columns: 1fr;
            }

            .quick-access {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 700px) {

            .student-welcome h1 {
                font-size: 26px;
            }

            .dashboard-card {
                padding: 22px;
            }

            .course-card h2 {
                font-size: 23px;
            }

            .points-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 5px;
            }

            .upcoming-details {
                grid-template-columns: 1fr;
            }

            .upcoming-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 5px;
            }

            .dashboard-button {
                width: 100%;
                text-align: center;
            }

        }

    </style>

</head>


<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">


        <!--
        |--------------------------------------------------------------------------
        | Welcome
        |--------------------------------------------------------------------------
        -->

        <section class="student-welcome">

            <h1>
                Dobrodošli u aplikaciju Kineziološke kulture TVZ-a!
            </h1>

            <p>
                Ovdje možete pregledati sve aktivnosti iz kolegija Kineziološke kulture, rezervirati termine, pratiti ostvarene bodove i nadolazeće rezervacije.
            </p>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Glavni pregled
        |--------------------------------------------------------------------------
        -->

        <section class="student-dashboard">


            <!--
            | MOJ KOLEGIJ
            -->

            <div class="dashboard-card course-card">

                <?php if ($course): ?>

                    <span class="course-label">
                        Moj kolegij
                    </span>

                    <h2>
                        <?= htmlspecialchars(
                            $course['course_name']
                        ) ?>
                    </h2>

                    <p class="course-status">

                        <strong>Status:</strong>

                        <?php
                        if ($course['status'] === 'ENROLLED') {
                            echo 'Upisan';
                        } elseif ($course['status'] === 'COMPLETED') {
                            echo 'Završen';
                        } elseif ($course['status'] === 'FAILED') {
                            echo 'Nije položen';
                        } else {
                            echo htmlspecialchars(
                                $course['status']
                            );
                        }
                        ?>

                    </p>


                    <div class="points-header">

                        <div class="points-title">
                            Napredak u bodovima
                        </div>

                        <div class="points-value">
                            <?= $totalPoints ?>
                            /
                            <?= $requiredPoints ?>
                        </div>

                    </div>


                    <div class="progress-bar">

                        <div
                            class="progress-fill"
                            style="
                                width: <?= $pointsProgress ?>%;
                            "
                        ></div>

                    </div>


                    <div class="progress-info">

                        <span>
                            <?= round($pointsProgress) ?>% ostvareno
                        </span>

                        <span>
                            <?= $requiredPoints ?> bodova potrebno
                        </span>

                    </div>


                    <p class="points-message">

                        <?php if ($pointsMissing > 0): ?>

                            Do potrebnog broja bodova
                            nedostaje ti

                            <strong>
                                <?= $pointsMissing ?>
                            </strong>

                            <?= $pointsMissing === 1
                                ? 'bod'
                                : 'bodova'
                            ?>.

                        <?php else: ?>

                            Ostvarila si potreban broj bodova
                            za kolegij.

                        <?php endif; ?>

                    </p>


                <?php else: ?>

                    <span class="course-label">
                        Moj kolegij
                    </span>

                    <div class="no-course">

                        Trenutno nemaš aktivan upis
                        na kolegij.

                    </div>

                <?php endif; ?>

            </div>


            <!--
            | MOJI BODOVI
            -->

            <div class="dashboard-card points-card">

                <div>

                    <div class="points-card-header">

                        <div class="dashboard-icon">
                            🏆
                        </div>

                        <div class="points-card-title">

                            <h2>
                                Moji bodovi
                            </h2>

                            <p>
                                Trenutno ostvareni bodovi
                            </p>

                        </div>

                    </div>


                    <div class="big-points">

                        <?= $totalPoints ?>

                        <span>
                            /
                            <?= $requiredPoints ?>
                            bodova
                        </span>

                    </div>

                </div>


                <a
                    href="points.php"
                    class="dashboard-button"
                >
                    Pregled bodova →
                </a>

            </div>


        </section>


        <!--
        |--------------------------------------------------------------------------
        | Brzi pristup
        |--------------------------------------------------------------------------
        -->

        <section class="quick-access-section">

            <h2 class="section-title">
                Brzi pristup
            </h2>


            <div class="quick-access">


                <!-- AKTIVNOSTI -->

                <a
                    href="activities.php"
                    class="quick-card"
                >

                    <div class="quick-icon">
                        🏃
                    </div>

                    <div class="quick-content">

                        <h3>
                            Aktivnosti
                        </h3>

                        <p>
                            Pregled dostupnih aktivnosti
                        </p>

                    </div>

                </a>


                <!-- REZERVIRAJ TERMIN -->

                <a
                    href="activities.php"
                    class="quick-card"
                >

                    <div class="quick-icon">
                        🗓️
                    </div>

                    <div class="quick-content">

                        <h3>
                            Rezerviraj termin
                        </h3>

                        <p>
                            Odaberi aktivnost i termin
                        </p>

                    </div>

                </a>


                <!-- OBAVIJESTI -->

                <a
                    href="announcements.php"
                    class="quick-card"
                >

                    <div class="quick-icon">
                        📢
                    </div>

                    <div class="quick-content">

                        <h3>
                            Obavijesti
                        </h3>

                        <p>
                            Pregled važnih obavijesti
                        </p>

                    </div>

                </a>

            </div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Nadolazeće rezervacije
        |--------------------------------------------------------------------------
        -->

        <section class="upcoming-section">


            <div class="upcoming-header">

                <h2 class="section-title">
                    Nadolazeće rezervacije
                </h2>

                <?php if (!empty($upcomingReservations)): ?>

                    <span class="upcoming-count">

                        <?= count($upcomingReservations) ?>

                        <?= count($upcomingReservations) === 1
                            ? 'rezervacija'
                            : 'rezervacije'
                        ?>

                    </span>

                <?php endif; ?>

            </div>


            <?php if (empty($upcomingReservations)): ?>

                <div class="no-upcoming">

                    Trenutno nemaš nadolazećih rezervacija.

                </div>


            <?php else: ?>


                <?php foreach ($upcomingReservations as $reservation): ?>

                    <div class="upcoming-card">


                        <h3>

                            <?= htmlspecialchars(
                                $reservation['name']
                            ) ?>

                        </h3>


                        <div class="upcoming-details">


                            <!-- Datum -->

                            <div class="upcoming-detail">

                                <strong>
                                    Datum:
                                </strong>

                                <?= date(
                                    'j. n. Y.',
                                    strtotime(
                                        $reservation['reservation_date']
                                    )
                                ) ?>

                            </div>


                            <!-- Dan -->

                            <div class="upcoming-detail">

                                <strong>
                                    Dan:
                                </strong>

                                <?= htmlspecialchars(
                                    $days[
                                        $reservation['day_of_week']
                                    ] ?? ''
                                ) ?>

                            </div>


                            <!-- Vrijeme -->

                            <div class="upcoming-detail">

                                <strong>
                                    Vrijeme:
                                </strong>

                                <?= htmlspecialchars(
                                    substr(
                                        $reservation['start_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    substr(
                                        $reservation['end_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                            </div>


                            <!-- Lokacija -->

                            <div class="upcoming-detail">

                                <strong>
                                    Lokacija:
                                </strong>

                                <?= htmlspecialchars(
                                    $reservation['location']
                                ) ?>

                            </div>

                            <!-- Profesor -->

                            <div class="upcoming-detail">

                                <strong>
                                    Profesor:
                                </strong>

                                <?= htmlspecialchars(
                                    trim(
                                        $reservation['professor_first_name']
                                        . ' '
                                        . $reservation['professor_last_name']
                                    )
                                ) ?>

                            </div>


                            <!-- Bodovi -->

                            <div class="upcoming-detail">

                                <strong>
                                    Bodovi:
                                </strong>

                                <?= htmlspecialchars(
                                    $reservation['points']
                                ) ?>

                            </div>


                        </div>

                    </div>

                <?php endforeach; ?>


                <a
                    href="my_reservations.php"
                    class="upcoming-link"
                >
                    Prikaži sve moje rezervacije
                </a>


            <?php endif; ?>


        </section>


    </main>

</body>

</html>