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
| Dohvat spola prijavljenog studenta
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        gender
    FROM users
    WHERE id = ?
      AND is_active = 1
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$studentGender = $stmt->fetchColumn();

if (!$studentGender) {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat relevantnog semestra
|--------------------------------------------------------------------------
|
| U aktivnoj akademskoj godini tražimo:
|
| 1. trenutno aktivni semestar, ako današnji datum pripada njemu
| 2. ako trenutno nije nijedan semestar aktivan, prvi nadolazeći
|    semestar
|
| Na taj način nema hardkodiranih datuma.
|
*/

$semesterStmt = $pdo->query("
    SELECT
        s.id,
        s.name,
        s.semester_number,
        s.start_date,
        s.end_date,
        ay.id AS academic_year_id,
        ay.name AS academic_year_name

    FROM semesters s

    INNER JOIN academic_years ay
        ON ay.id = s.academic_year_id

    WHERE ay.is_active = 1

    ORDER BY
        CASE
            WHEN CURDATE()
                 BETWEEN s.start_date AND s.end_date
                THEN 0

            WHEN s.start_date > CURDATE()
                THEN 1

            ELSE 2
        END,

        CASE
            WHEN s.start_date > CURDATE()
                THEN s.start_date
            ELSE NULL
        END ASC,

        s.start_date ASC

    LIMIT 1
");

$currentSemester = $semesterStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Ako nema dostupnog semestra
|--------------------------------------------------------------------------
*/

if (!$currentSemester) {

    $activities = [];

} else {

    $sql = "
        SELECT

            o.id AS offering_id,
            o.semester_id,

            a.id AS activity_id,
            a.name,
            a.description,
            a.points,
            a.activity_type,

            p.first_name AS professor_first_name,
            p.last_name AS professor_last_name,

            s.id AS session_id,
            s.day_of_week,
            s.start_time,
            s.end_time,
            s.location,
            s.gender,
            s.capacity,

            occ.id AS occurrence_id,
            occ.occurrence_date,

            sem.name AS semester_name,
            sem.start_date AS semester_start_date,
            sem.end_date AS semester_end_date,

            ay.name AS academic_year_name,

            (
                SELECT COUNT(*)

                FROM reservations r

                WHERE r.session_id = s.id
                  AND r.reservation_date = occ.occurrence_date
                  AND r.status = 'RESERVED'

            ) AS reserved_count,

            EXISTS (

                SELECT 1

                FROM reservations ur

                WHERE ur.session_id = s.id
                  AND ur.reservation_date = occ.occurrence_date
                  AND ur.status = 'RESERVED'
                  AND ur.student_id = :user_id_reserved

            ) AS user_reserved

        FROM activity_offerings o

        INNER JOIN activities a
            ON a.id = o.activity_id

        INNER JOIN activity_sessions s
            ON s.offering_id = o.id

        INNER JOIN users p
            ON p.id = o.professor_id

        INNER JOIN activity_occurrences occ
            ON occ.session_id = s.id

        LEFT JOIN semesters sem
            ON sem.id = o.semester_id

        LEFT JOIN academic_years ay
            ON ay.id = sem.academic_year_id

        WHERE
            o.is_active = 1
            AND a.is_active = 1
            AND s.is_active = 1

            AND
            (
                /*
                | Redovna aktivnost u aktualnom / nadolazećem semestru
                */

                (
                    a.activity_type = 'REGULAR'

                    AND o.semester_id = :semester_id

                    AND occ.occurrence_date
                        BETWEEN :semester_start
                        AND :semester_end
                )

                OR

                /*
                | BONUS / opća aktivnost
                |
                | Nije vezana uz konkretan semestar.
                */

                (
                    a.activity_type = 'BONUS'
                    AND o.semester_id IS NULL
                )
            )


        ORDER BY
            a.name ASC,
            occ.occurrence_date ASC,
            s.start_time ASC
    ";


    $stmt = $pdo->prepare($sql);


    $stmt->execute([

        ':user_id_reserved' =>
            $userId,

        ':semester_id' =>
            (int) $currentSemester['id'],

        ':semester_start' =>
            $currentSemester['start_date'],

        ':semester_end' =>
            $currentSemester['end_date'],


    ]);


    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Grupiranje po activity_offering
    |--------------------------------------------------------------------------
    |
    | Isto ime aktivnosti može postojati u više različitih
    | semestara, zato više ne grupiramo samo po activity_id.
    |
    */

    $activities = [];


    foreach ($rows as $row) {

        $offeringId =
            (int) $row['offering_id'];


        if (!isset($activities[$offeringId])) {

            $activities[$offeringId] = [

                'id' =>
                    $offeringId,

                'activity_id' =>
                    (int) $row['activity_id'],

                'name' =>
                    $row['name'],

                'description' =>
                    $row['description'],

                'points' =>
                    (int) $row['points'],

                'activity_type' =>
                    $row['activity_type'],

                'professor_name' =>
                    $row['professor_first_name']
                    . ' '
                    . $row['professor_last_name'],

                'academic_year_name' =>
                    $row['academic_year_name'],

                'semester_name' =>
                    $row['semester_name'],

                'occurrences' =>
                    [],

                'months' =>
                    []
            ];
        }


        $activities[$offeringId]['occurrences'][] = [

            'occurrence_id' =>
                (int) $row['occurrence_id'],

            'session_id' =>
                (int) $row['session_id'],

            'date' =>
                $row['occurrence_date'],

            'day_of_week' =>
                (int) $row['day_of_week'],

            'start_time' =>
                substr(
                    $row['start_time'],
                    0,
                    5
                ),

            'end_time' =>
                substr(
                    $row['end_time'],
                    0,
                    5
                ),

            'location' =>
                $row['location'],

            'gender' =>
                $row['gender'],

            'capacity' =>
                $row['capacity'] !== null
                    ? (int) $row['capacity']
                    : null,

            'reserved_count' =>
                (int) $row['reserved_count'],

            'user_reserved' =>
                (bool) $row['user_reserved']
        ];


        /*
        |--------------------------------------------------------------------------
        | Mjeseci koji stvarno postoje za ovu izvedbu
        |--------------------------------------------------------------------------
        */

        $monthKey =
            date(
                'Y-m',
                strtotime(
                    $row['occurrence_date']
                )
            );


        $activities[$offeringId]['months'][$monthKey] = [

            'year' =>
                (int) date(
                    'Y',
                    strtotime(
                        $row['occurrence_date']
                    )
                ),

            'month' =>
                (int) date(
                    'n',
                    strtotime(
                        $row['occurrence_date']
                    )
                )
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Pretvaranje mjeseci u numerički niz
    |--------------------------------------------------------------------------
    */

    foreach ($activities as &$activity) {

        ksort($activity['months']);

        $activity['months'] =
            array_values(
                $activity['months']
            );
    }

    unset($activity);


    $activities =
        array_values($activities);


}


/*
|--------------------------------------------------------------------------
| Poruka
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';


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
        Dostupne aktivnosti - TVZ Kineziološka kultura
    </title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <link
        rel="stylesheet"
        href="css/activities.css"
    >

</head>

<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <?php if (isset($_GET['message'])): ?>

        <?php if ($_GET['message'] === 'points_completed'): ?>

            <div class="message warning">
                Ostvareni bodovi i bodovi tvojih budućih rezervacija
                već dosežu potrebnih 18 bodova.
                Nove rezervacije trenutno nisu moguće.
            </div>


        <?php elseif ($_GET['message'] === 'one_per_day'): ?>

            <div class="message error">
                Za ovaj dan već imaš rezerviranu aktivnost.
                Možeš rezervirati samo jednu aktivnost dnevno.
            </div>


        <?php elseif ($_GET['message'] === 'reserved'): ?>

            <div class="message success">
                Rezervacija je uspješno napravljena.
            </div>


        <?php elseif ($_GET['message'] === 'cancelled'): ?>

            <div class="message success">
                Rezervacija je uspješno otkazana.
            </div>


        <?php elseif ($_GET['message'] === 'late_cancel'): ?>

            <div class="message warning">
                Rezervacija je otkazana, ali zbog kasnog otkazivanja
                dodijeljen je penal od -1 boda.
            </div>


        <?php elseif ($_GET['message'] === 'already_reserved'): ?>

            <div class="message warning">
                Već imate rezervaciju za ovaj termin.
            </div>


        <?php elseif ($_GET['message'] === 'full'): ?>

            <div class="message error">
                Nažalost, ovaj termin je popunjen.
            </div>


        <?php elseif ($_GET['message'] === 'not_available'): ?>

            <div class="message error">
                Ovaj termin više nije moguće rezervirati.
            </div>


        <?php elseif ($_GET['message'] === 'gender_not_allowed'): ?>

            <div class="message error">
                Ne možeš rezervirati ovaj termin jer je namijenjen
                drugoj spolnoj skupini.
            </div>


        <?php elseif ($_GET['message'] === 'error'): ?>

            <div class="message error">
                Došlo je do pogreške prilikom rezervacije.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <h1>
        Dostupne aktivnosti
    </h1>


    <?php if ($currentSemester): ?>

        <p class="subtitle">

            Dostupne aktivnosti za:

            <strong>
                <?= htmlspecialchars(
                    $currentSemester['academic_year_name']
                ) ?>

                -
                <?= htmlspecialchars(
                    $currentSemester['name']
                ) ?>
            </strong>

        </p>

    <?php else: ?>

        <p class="subtitle">
            Trenutno nije definirana aktivna akademska godina.
        </p>

    <?php endif; ?>


    <?php if (empty($activities)): ?>

        <div class="no-activities">
            Trenutno nema dostupnih aktivnosti.
        </div>


    <?php else: ?>


        <div class="activities">


            <?php foreach ($activities as $activity): ?>

                <div class="activity-card">

                    <div>


                        <div class="activity-name">

                            <?= htmlspecialchars(
                                $activity['name']
                            ) ?>

                        </div>


                        <?php if (
                            !empty(
                                $activity['description']
                            )
                        ): ?>

                            <div class="description">

                                <?= htmlspecialchars(
                                    $activity['description']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty($activity['semester_name'])
                        ): ?>

                            <div class="description">

                                <?= htmlspecialchars(
                                    $activity['academic_year_name']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $activity['semester_name']
                                ) ?>

                            </div>

                        <?php else: ?>

                            <div class="description">
                                Opća / BONUS aktivnost
                            </div>

                        <?php endif; ?>


                        <div class="description">

                            Profesor:
                            <strong>
                                <?= htmlspecialchars(
                                    $activity['professor_name']
                                ) ?>
                            </strong>

                        </div>


                    </div>


                    <div class="activity-bottom">

                        <div class="points">

                            <?= (int) $activity['points'] ?>

                            <?= (
                                (int) $activity['points'] === 1
                            )
                                ? 'bod'
                                : 'boda'
                            ?>

                        </div>


                        <button
                            type="button"
                            class="view-button"
                            onclick="openActivityModal(
                                <?= (int) $activity['id'] ?>
                            )"
                        >
                            Pregledaj termine
                        </button>

                    </div>

                </div>

            <?php endforeach; ?>


        </div>

    <?php endif; ?>


</main>


<!--
|--------------------------------------------------------------------------
| MODALI ZA AKTIVNOSTI
|--------------------------------------------------------------------------
-->

<?php foreach ($activities as $activity): ?>

    <div
        class="modal"
        id="activity-modal-<?= (int) $activity['id'] ?>"
        data-activity-id="<?= (int) $activity['id'] ?>"
    >

        <div class="modal-content">


            <div class="modal-header">

                <div>

                    <div class="modal-title">

                        <?= htmlspecialchars(
                            $activity['name']
                        ) ?>

                    </div>


                    <div class="modal-subtitle">

                        <?php if (
                            !empty(
                                $activity['semester_name']
                            )
                        ): ?>

                            <?= htmlspecialchars(
                                $activity['academic_year_name']
                            ) ?>

                            -
                            <?= htmlspecialchars(
                                $activity['semester_name']
                            ) ?>

                            · Odaberite datum za rezervaciju.

                        <?php else: ?>

                            Opća / BONUS aktivnost
                            · Odaberite datum za rezervaciju.

                        <?php endif; ?>

                    </div>

                </div>


                <button
                    type="button"
                    class="close-modal"
                    onclick="closeActivityModal(
                        <?= (int) $activity['id'] ?>
                    )"
                >
                    &times;
                </button>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Navigacija kroz mjesece
            |--------------------------------------------------------------------------
            -->

            <div class="month-navigation">

                <button
                    type="button"
                    class="month-arrow"
                    id="prev-month-<?= (int) $activity['id'] ?>"
                    onclick="changeMonth(
                        <?= (int) $activity['id'] ?>,
                        -1
                    )"
                >
                    ←
                </button>


                <div
                    class="month-title"
                    id="month-title-<?= (int) $activity['id'] ?>"
                ></div>


                <button
                    type="button"
                    class="month-arrow"
                    id="next-month-<?= (int) $activity['id'] ?>"
                    onclick="changeMonth(
                        <?= (int) $activity['id'] ?>,
                        1
                    )"
                >
                    →
                </button>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Kalendar
            |--------------------------------------------------------------------------
            -->

            <div
                class="calendar"
                id="calendar-<?= (int) $activity['id'] ?>"
            ></div>


            <!--
            |--------------------------------------------------------------------------
            | Odabrani datum i termini
            |--------------------------------------------------------------------------
            -->

            <div class="selected-date">

                <div
                    class="selected-date-title"
                    id="selected-date-title-<?= (int) $activity['id'] ?>"
                >
                    Odaberite datum
                </div>


                <div
                    class="session-list"
                    id="session-list-<?= (int) $activity['id'] ?>"
                >

                    <div class="selected-date-empty">
                        Kliknite na jedan od označenih datuma
                        u kalendaru.
                    </div>

                </div>

            </div>


        </div>

    </div>

<?php endforeach; ?>


<script>
const activityData = <?= json_encode(
    array_reduce(
        $activities,
        function ($result, $activity) {
            $result[$activity['id']] = [
                'name' => $activity['name'],
                'occurrences' => $activity['occurrences'],
                'months' => $activity['months']
            ];

            return $result;
        },
        []
    ),
    JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;

const studentGender = <?= json_encode(
    $studentGender,
    JSON_UNESCAPED_UNICODE
) ?>;
</script>
<script src="js/activities.js"></script>

</body>
</html>
