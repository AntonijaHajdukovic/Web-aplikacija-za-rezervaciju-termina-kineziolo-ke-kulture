<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');


/*
|--------------------------------------------------------------------------
| Osnovni podaci
|--------------------------------------------------------------------------
*/

$studentId = (int) ($_SESSION['user_id'] ?? 0);

if ($studentId <= 0) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Očekujemo POST zahtjev
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: activities.php');

    exit;
}


$sessionId = isset($_POST['session_id'])
    ? (int) $_POST['session_id']
    : 0;

$reservationDate = isset($_POST['reservation_date'])
    ? trim($_POST['reservation_date'])
    : '';


if (
    $sessionId <= 0
    || $reservationDate === ''
) {

    header('Location: activities.php?message=error');

    exit;
}


/*
|--------------------------------------------------------------------------
| Provjera ispravnosti datuma
|--------------------------------------------------------------------------
*/

$dateObject = DateTime::createFromFormat(
    '!Y-m-d',
    $reservationDate
);

$dateErrors = DateTime::getLastErrors();


if (
    !$dateObject
    ||
    (
        $dateErrors !== false
        &&
        (
            $dateErrors['warning_count'] > 0
            ||
            $dateErrors['error_count'] > 0
        )
    )
    ||
    $dateObject->format('Y-m-d') !== $reservationDate
) {

    header('Location: activities.php?message=error');

    exit;
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Dohvat termina i izvedbe aktivnosti
    |--------------------------------------------------------------------------
    |
    | Termin je sada povezan preko:
    |
    | activity_sessions.offering_id
    |         ↓
    | activity_offerings
    |
    | Tako znamo:
    |
    | - kojoj aktivnosti termin pripada
    | - kojem semestru pripada
    | - kojem profesoru pripada
    | - je li izvedba aktivna
    |
    */

    $sql = "
        SELECT
            s.id,
            s.day_of_week,
            s.start_time,
            s.end_time,
            s.capacity,
            s.gender,
            s.is_active,

            a.points,
            a.activity_type,
            a.is_active AS activity_active,

            o.is_active AS offering_active,
            o.semester_id,

            sem.name AS semester_name,
            sem.semester_number,
            sem.start_date AS semester_start_date,
            sem.end_date AS semester_end_date,

            ay.id AS academic_year_id,
            ay.name AS academic_year_name,
            ay.is_active AS academic_year_active,

            u.gender AS student_gender,
            u.is_active AS student_active

        FROM activity_sessions s

        INNER JOIN activity_offerings o
            ON o.id = s.offering_id

        INNER JOIN activities a
            ON a.id = o.activity_id

        LEFT JOIN semesters sem
            ON sem.id = o.semester_id

        LEFT JOIN academic_years ay
            ON ay.id = sem.academic_year_id

        INNER JOIN users u
            ON u.id = :student_id

        WHERE s.id = :session_id

        FOR UPDATE
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'student_id' => $studentId,
        'session_id' => $sessionId
    ]);


    $session = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Osnovna provjera dostupnosti
    |--------------------------------------------------------------------------
    */

    if (
        !$session
        ||
        !(int) $session['student_active']
        ||
        !(int) $session['is_active']
        ||
        !(int) $session['activity_active']
        ||
        !(int) $session['offering_active']
    ) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=not_available'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera vrste aktivnosti i semestra
    |--------------------------------------------------------------------------
    |
    | REGULAR aktivnosti moraju imati konkretan semestar.
    |
    | BONUS aktivnosti mogu biti globalne i imati semester_id = NULL.
    |
    */

    $isBonus =
        $session['activity_type'] === 'BONUS';


    if (
        !$isBonus
        &&
        empty($session['semester_id'])
    ) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=not_available'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Provjera semestra za REGULAR aktivnost
    |--------------------------------------------------------------------------
    |
    | REGULAR aktivnost mora pripadati konkretnom semestru.
    |
    | Student ne mora imati poseban zapis u student_courses
    | kako bi mogao rezervirati ponuđenu aktivnost.
    |
    */

    $requiredPoints = 18;


    if (!$isBonus) {

        /*
        |--------------------------------------------------------------------------
        | Aktivna akademska godina i semestar moraju biti valjani
        |--------------------------------------------------------------------------
        */

        if (
            empty($session['academic_year_id'])
            ||
            !(int) $session['academic_year_active']
            ||
            empty($session['semester_number'])
            ||
            empty($session['semester_start_date'])
            ||
            empty($session['semester_end_date'])
        ) {

            $pdo->rollBack();

            header(
                'Location: activities.php?message=not_available'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Datum rezervacije mora pripadati semestru
        |--------------------------------------------------------------------------
        */

        if (
            $reservationDate < $session['semester_start_date']
            ||
            $reservationDate > $session['semester_end_date']
        ) {

            $pdo->rollBack();

            header(
                'Location: activities.php?message=not_available'
            );

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Termin nije smio već početi
    |--------------------------------------------------------------------------
    */

    $now = new DateTime(
        'now',
        new DateTimeZone('Europe/Zagreb')
    );


    $sessionStart = new DateTime(
        $reservationDate
        . ' '
        . $session['start_time'],
        new DateTimeZone('Europe/Zagreb')
    );


    if ($sessionStart <= $now) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=not_available'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Ukupno planirani bodovi
    |--------------------------------------------------------------------------
    |
    | Student može rezervirati nove termine dok:
    |
    | ostvareni bodovi
    | +
    | bodovi budućih aktivnih rezervacija
    |
    | nisu jednaki ili veći od potrebnog broja bodova.
    |
    */

    $sql = "
    SELECT
        (
            SELECT COALESCE(
                SUM(r.points_awarded),
                0
            )

            FROM reservations r

            INNER JOIN activity_sessions s
                ON s.id = r.session_id

            INNER JOIN activity_offerings o
                ON o.id = s.offering_id

            WHERE r.student_id = :student_id_earned

              AND (
                    o.semester_id = :current_semester_id_earned

                    OR
                    (
                        o.semester_id IS NULL
                        AND r.reservation_date
                            BETWEEN :current_semester_start_earned
                            AND :current_semester_end_earned
                    )
              )
        )

        +

        (
            SELECT COALESCE(
                SUM(a.points),
                0
            )

            FROM reservations r

            INNER JOIN activity_sessions s
                ON s.id = r.session_id

            INNER JOIN activity_offerings o
                ON o.id = s.offering_id

            INNER JOIN activities a
                ON a.id = o.activity_id

            WHERE r.student_id = :student_id_reserved

              AND r.status = 'RESERVED'

              AND r.reservation_date >= CURRENT_DATE

              AND s.is_active = 1

              AND o.is_active = 1

              AND a.is_active = 1

              AND (
                    o.semester_id = :current_semester_id_reserved

                    OR
                    (
                        o.semester_id IS NULL
                        AND r.reservation_date
                            BETWEEN :current_semester_start_reserved
                            AND :current_semester_end_reserved
                    )
              )
        )

        AS total_planned_points
";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'student_id_earned' =>
            $studentId,

        'current_semester_id_earned' =>
            !empty($session['semester_id'])
                ? (int) $session['semester_id']
                : 0,

        'current_semester_start_earned' =>
            $session['semester_start_date']
            ?? '1000-01-01',

        'current_semester_end_earned' =>
            $session['semester_end_date']
            ?? '9999-12-31',

        'student_id_reserved' =>
            $studentId,

        'current_semester_id_reserved' =>
            !empty($session['semester_id'])
                ? (int) $session['semester_id']
                : 0,

        'current_semester_start_reserved' =>
            $session['semester_start_date']
            ?? '1000-01-01',

        'current_semester_end_reserved' =>
            $session['semester_end_date']
            ?? '9999-12-31'
    ]);


    $totalPlannedPoints =
        (int) $stmt->fetchColumn();


    if ($totalPlannedPoints >= $requiredPoints) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=points_completed'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera spola
    |--------------------------------------------------------------------------
    */

    if (
        $session['gender'] !== 'ALL'
        &&
        $session['gender'] !== $session['student_gender']
    ) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=gender_not_allowed'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera konkretnog occurrence zapisa
    |--------------------------------------------------------------------------
    |
    | Student ne može sam poslati proizvoljan datum.
    |
    | Datum mora postojati u:
    |
    | activity_occurrences
    |
    */

    $sql = "
        SELECT
            id

        FROM activity_occurrences

        WHERE session_id = ?
          AND occurrence_date = ?

        LIMIT 1
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $sessionId,
        $reservationDate
    ]);


    $occurrence =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$occurrence) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=not_available'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Jedna aktivna rezervacija dnevno
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id

        FROM reservations

        WHERE student_id = ?
          AND reservation_date = ?
          AND status = 'RESERVED'

        LIMIT 1

        FOR UPDATE
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $studentId,
        $reservationDate
    ]);


    $dailyReservation =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if ($dailyReservation) {

        $pdo->rollBack();

        header(
            'Location: activities.php?message=one_per_day'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Postojeća rezervacija za isti termin i datum
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            *

        FROM reservations

        WHERE student_id = ?
          AND session_id = ?
          AND reservation_date = ?

        FOR UPDATE
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $studentId,
        $sessionId,
        $reservationDate
    ]);


    $existingReservation =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Ako rezervacija već postoji
    |--------------------------------------------------------------------------
    */

    if ($existingReservation) {

        /*
        |--------------------------------------------------------------------------
        | Već aktivna rezervacija
        |--------------------------------------------------------------------------
        */

        if (
            $existingReservation['status'] === 'RESERVED'
        ) {

            $pdo->rollBack();

            header(
                'Location: activities.php?message=already_reserved'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Prethodno otkazana rezervacija
        |--------------------------------------------------------------------------
        */

        if (
            $existingReservation['status'] === 'CANCELLED'
        ) {

            /*
            | Ako je dodijeljen penal (-1),
            | ponovno rezerviranje nije dopušteno.
            */

            if (
                (int) $existingReservation['points_awarded']
                === -1
            ) {

                $pdo->rollBack();

                header(
                    'Location: activities.php?message=late_cancel'
                );

                exit;
            }


            /*
            | Inače ponovno aktiviramo rezervaciju.
            */

            $sql = "
                UPDATE reservations

                SET
                    status = 'RESERVED',
                    reserved_at = CURRENT_TIMESTAMP,
                    cancelled_at = NULL,
                    attended_at = NULL,
                    points_awarded = 0

                WHERE id = ?
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $existingReservation['id']
            ]);


            $pdo->commit();


            header(
                'Location: activities.php?message=reserved'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | ATTENDED / NO_SHOW
        |--------------------------------------------------------------------------
        */

        $pdo->rollBack();

        header(
            'Location: activities.php?message=not_available'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera kapaciteta
    |--------------------------------------------------------------------------
    |
    | Provjeravamo samo aktivne rezervacije
    | za konkretan datum.
    |
    */

    if ($session['capacity'] !== null) {

        $sql = "
            SELECT
                COUNT(*)

            FROM reservations

            WHERE session_id = ?
              AND reservation_date = ?
              AND status = 'RESERVED'
        ";


        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $sessionId,
            $reservationDate
        ]);


        $reservedCount =
            (int) $stmt->fetchColumn();


        if (
            $reservedCount
            >= (int) $session['capacity']
        ) {

            $pdo->rollBack();

            header(
                'Location: activities.php?message=full'
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Kreiranje rezervacije
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO reservations (
            student_id,
            session_id,
            reservation_date,
            status,
            reserved_at,
            points_awarded
        )

        VALUES (
            ?,
            ?,
            ?,
            'RESERVED',
            CURRENT_TIMESTAMP,
            0
        )
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $studentId,
        $sessionId,
        $reservationDate
    ]);


    $pdo->commit();


    header(
        'Location: activities.php?message=reserved'
    );

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header(
        'Location: activities.php?message=error'
    );

    exit;
}