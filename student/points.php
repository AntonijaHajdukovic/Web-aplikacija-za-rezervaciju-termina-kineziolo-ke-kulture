<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');

$studentId = (int) ($_SESSION['user_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Odabrani filter statusa
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? 'ALL';

$allowedStatuses = [
    'ALL',
    'RESERVED',
    'ATTENDED',
    'NO_SHOW',
    'CANCELLED'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'ALL';
}


if ($studentId <= 0) {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat svih rezervacija studenta
|--------------------------------------------------------------------------
|
| Bodovi:
| +1 = dolazak
| -1 = nedolazak / penal
|  0 = bez promjene bodova
|
*/

$sql = "
    SELECT
        r.id AS reservation_id,
        r.reservation_date,
        r.status,
        r.points_awarded,

        a.name AS activity_name,
        a.points AS activity_points,

        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,

        sem.name AS semester_name,
        ay.name AS academic_year_name

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    INNER JOIN student_courses sc
        ON sc.student_id = r.student_id

    INNER JOIN courses c
        ON c.id = sc.course_id

    INNER JOIN academic_years current_ay
        ON current_ay.id = sc.academic_year_id
       AND current_ay.is_active = 1

    INNER JOIN semesters current_sem
        ON current_sem.academic_year_id = current_ay.id
       AND current_sem.semester_number = CASE
            WHEN c.semester_number IN (1, 3)
                THEN 1
            WHEN c.semester_number IN (2, 4)
                THEN 2
          END

    WHERE r.student_id = :student_id

      AND sc.status = 'ENROLLED'

      AND c.is_active = 1

      AND (
            /*
            |--------------------------------------------------------------------------
            | Aktivnost vezana uz trenutni semestar
            |--------------------------------------------------------------------------
            */

            o.semester_id = current_sem.id


            OR


            /*
            |--------------------------------------------------------------------------
            | Opća aktivnost
            |--------------------------------------------------------------------------
            |
            | Nije vezana uz semestar pa je uključujemo ako se
            | održala unutar datuma trenutnog semestra.
            |
            */

            (
                o.semester_id IS NULL

                AND r.reservation_date
                    BETWEEN current_sem.start_date
                    AND current_sem.end_date
            )
      )

    ORDER BY
        r.reservation_date DESC,
        s.start_time DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'student_id' => $studentId
]);

$allPointRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Ukupan broj ostvarenih bodova
|--------------------------------------------------------------------------
|
| Računamo ga iz SVIH zapisa,
| neovisno o trenutno odabranom filteru.
|
*/

$totalPoints = 0;

foreach ($allPointRecords as $record) {

    $totalPoints += (int) $record['points_awarded'];

}


/*
|--------------------------------------------------------------------------
| Filtriranje zapisa za prikaz
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'ALL') {

    $pointRecords = $allPointRecords;

} else {

    $pointRecords = array_filter(
        $allPointRecords,
        function ($record) use ($statusFilter) {

            return $record['status'] === $statusFilter;

        }
    );

}


/*
|--------------------------------------------------------------------------
| Dohvat potrebnog broja bodova za aktivni kolegij
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT
        c.required_points

    FROM student_courses sc

    INNER JOIN courses c
        ON c.id = sc.course_id

    WHERE sc.student_id = :student_id

      AND sc.academic_year_id = (
          SELECT id
          FROM academic_years
          WHERE is_active = 1
          LIMIT 1
      )

      AND sc.status = 'ENROLLED'

    LIMIT 1
    "
);

$stmt->execute([
    'student_id' => $studentId
]);

$requiredPoints = (int) ($stmt->fetchColumn() ?? 18);


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


/*
|--------------------------------------------------------------------------
| Nazivi statusa za filter
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'ALL' => 'Svi',
    'RESERVED' => 'Rezervirano',
    'ATTENDED' => 'Dolazak',
    'NO_SHOW' => 'Nedolazak',
    'CANCELLED' => 'Otkazano'
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

    <title>
        Moji bodovi - TVZ Kineziološka kultura
    </title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | Page header
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header p {
            color: #5b6470;
            font-size: 16px;
            margin-top: 6px;
        }


        /*
        |--------------------------------------------------------------------------
        | Total points
        |--------------------------------------------------------------------------
        */

        .total-points-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 25px;
        }

        .total-points-card h2 {
            color: #07558a;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .total-points {
            font-size: 26px;
            font-weight: bold;
            color: #172b4d;
        }


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        .filter-section {
            margin-bottom: 22px;
        }

        .filter-section h2 {
            font-size: 20px;
            margin-bottom: 12px;
            color: #172b4d;
        }

        .status-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .status-filter {
            display: inline-block;
            padding: 9px 15px;
            border-radius: 8px;
            background-color: #ffffff;
            border: 1px solid #d5dce5;
            color: #07558a;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;

            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                border-color 0.2s ease;
        }

        .status-filter:hover {
            background-color: #eef5fa;
            border-color: #07558a;
        }

        .status-filter.active {
            background-color: #07558a;
            border-color: #07558a;
            color: #ffffff;
        }


        /*
        |--------------------------------------------------------------------------
        | Points list
        |--------------------------------------------------------------------------
        */

        .points-list {
            display: grid;
            gap: 12px;
        }

        .point-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);

            display: grid;

            grid-template-columns:
                1.2fr
                1fr
                1fr
                1fr
                auto;

            gap: 16px;
            align-items: center;
        }


        /*
        |--------------------------------------------------------------------------
        | Activity
        |--------------------------------------------------------------------------
        */

        .activity-name {
            font-size: 17px;
            font-weight: bold;
            color: #172b4d;
        }


        /*
        |--------------------------------------------------------------------------
        | Details
        |--------------------------------------------------------------------------
        */

        .detail {
            font-size: 14px;
            line-height: 1.4;
            color: #4b5563;
        }

        .detail strong {
            display: block;
            color: #07558a;
            margin-bottom: 3px;
            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Points value
        |--------------------------------------------------------------------------
        */

        .point-value {
            font-size: 19px;
            font-weight: bold;
            text-align: right;
        }

        .positive {
            color: #267a2e;
        }

        .negative {
            color: #b91c1c;
        }

        .zero {
            color: #5b6470;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            font-weight: bold;
        }

        .status-reserved {
            color: #267a2e;
        }

        .status-cancelled {
            color: #b91c1c;
        }

        .status-attended {
            color: #267a2e;
        }

        .status-no-show {
            color: #b91c1c;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .empty-state {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            color: #5b6470;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .point-card {
                grid-template-columns: 1fr 1fr;
            }

            .point-value {
                text-align: left;
            }

        }


        @media (max-width: 600px) {

            .status-filters {
                flex-direction: column;
            }

            .status-filter {
                text-align: center;
            }

            .point-card {
                grid-template-columns: 1fr;
                padding: 18px;
            }

            .point-value {
                text-align: left;
            }

        }

    </style>

</head>


<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">


        <!--
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        -->

        <section class="page-header">

            <h1>
                Moji bodovi
            </h1>

            <p>
                Pregled termina i bodova koje si ostvarila
                tijekom kolegija Kineziološka kultura.
            </p>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Ukupni bodovi
        |--------------------------------------------------------------------------
        -->

        <section class="total-points-card">

            <h2>
                Ukupno ostvareni bodovi
            </h2>

            <div class="total-points">

                <?= $totalPoints ?>

                /

                <?= $requiredPoints ?>

            </div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Filteri
        |--------------------------------------------------------------------------
        -->

        <section class="filter-section">

            <h2>
                Evidencija bodova
            </h2>


            <div class="status-filters">

                <?php foreach ($statusLabels as $status => $label): ?>

                    <a
                        href="points.php?status=<?= urlencode($status) ?>"
                        class="status-filter <?= $statusFilter === $status ? 'active' : '' ?>"
                    >

                        <?= htmlspecialchars($label) ?>

                    </a>

                <?php endforeach; ?>

            </div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Popis bodova
        |--------------------------------------------------------------------------
        -->

        <section>

            <?php if (empty($pointRecords)): ?>

                <div class="empty-state">

                    <?php if ($statusFilter === 'ALL'): ?>

                        Još nemaš evidentiranih bodova.

                    <?php else: ?>

                        Nema zapisa za odabrani status.

                    <?php endif; ?>

                </div>


            <?php else: ?>


                <div class="points-list">


                    <?php foreach ($pointRecords as $record): ?>

                        <?php

                        $points = (int) $record['points_awarded'];


                        /*
                        |--------------------------------------------------------------------------
                        | Tekst statusa
                        |--------------------------------------------------------------------------
                        */

                        switch ($record['status']) {

                            case 'RESERVED':

                                $statusText = 'Rezervirano';
                                $statusClass = 'status-reserved';

                                break;


                            case 'CANCELLED':

                                $statusText = 'Otkazano';
                                $statusClass = 'status-cancelled';

                                break;


                            case 'ATTENDED':

                                $statusText = 'Dolazak';
                                $statusClass = 'status-attended';

                                break;


                            case 'NO_SHOW':

                                $statusText = 'Nedolazak';
                                $statusClass = 'status-no-show';

                                break;


                            default:

                                $statusText = $record['status'];
                                $statusClass = '';

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Klasa i tekst za prikaz bodova
                        |--------------------------------------------------------------------------
                        */

                        if ($points > 0) {

                            $pointClass = 'positive';
                            $pointText = '+' . $points;

                        } elseif ($points < 0) {

                            $pointClass = 'negative';
                            $pointText = (string) $points;

                        } else {

                            $pointClass = 'zero';
                            $pointText = '0';

                        }

                        ?>


                        <div class="point-card">


                            <!-- Aktivnost -->

                            <div class="activity-name">

                                <?= htmlspecialchars(
                                    $record['activity_name']
                                ) ?>

                            </div>


                            <!-- Datum -->

                            <div class="detail">

                                <strong>
                                    Datum
                                </strong>

                                <?= date(
                                    'j. n. Y.',
                                    strtotime(
                                        $record['reservation_date']
                                    )
                                ) ?>

                            </div>


                            <!-- Termin -->

                            <div class="detail">

                                <strong>
                                    Termin
                                </strong>

                                <?= htmlspecialchars(
                                    $days[
                                        (int) $record['day_of_week']
                                    ] ?? ''
                                ) ?>

                                <br>

                                <?= htmlspecialchars(
                                    substr(
                                        $record['start_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    substr(
                                        $record['end_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                            </div>


                            <!-- Status -->

                            <div class="detail">

                                <strong>
                                    Status
                                </strong>

                                <span
                                    class="status <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $statusText
                                    ) ?>

                                </span>

                            </div>


                            <!-- Bodovi -->

                            <div
                                class="point-value <?= $pointClass ?>"
                            >

                                <?= $pointText ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>


    </main>

</body>

</html>