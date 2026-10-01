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
| Početne vrijednosti forme
|--------------------------------------------------------------------------
*/

$error = '';

$offeringId = '';
$sessionType = 'WEEKLY';

$dayOfWeek = '';
$occurrenceDate = '';

$startTime = '';
$endTime = '';

$location = '';
$gender = 'ALL';
$capacity = '';


/*
|--------------------------------------------------------------------------
| Dohvat aktivnih izvedbi profesora
|--------------------------------------------------------------------------
|
| Profesor može dodavati termine samo za svoje aktivne izvedbe.
|
| Regularna aktivnost:
|   activity_offerings.semester_id nije NULL
|
| Bonus / opća aktivnost:
|   activity_offerings.semester_id može biti NULL
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            o.id AS offering_id,
            o.activity_id,
            o.semester_id,

            a.name,
            a.points,
            a.activity_type,

            sem.name AS semester_name,
            sem.start_date AS semester_start_date,
            sem.end_date AS semester_end_date,

            ay.name AS academic_year_name

        FROM activity_offerings o

        INNER JOIN activities a
            ON a.id = o.activity_id

        LEFT JOIN semesters sem
            ON sem.id = o.semester_id

        LEFT JOIN academic_years ay
            ON ay.id = sem.academic_year_id

        WHERE o.professor_id = :professor_id
          AND o.is_active = 1
          AND a.is_active = 1

        ORDER BY
            CASE
                WHEN o.semester_id IS NULL THEN 2
                ELSE 1
            END,
            sem.start_date ASC,
            a.name ASC
    ");

    $stmt->execute([
        'professor_id' => $professorId
    ]);

    $offerings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $offerings = [];

    $error =
        'Došlo je do pogreške prilikom dohvaćanja aktivnosti.';
}


/*
|--------------------------------------------------------------------------
| Obrada forme
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $error === ''
) {

    $offeringId = filter_input(
        INPUT_POST,
        'offering_id',
        FILTER_VALIDATE_INT
    );

    $sessionType = $_POST['session_type'] ?? 'WEEKLY';

    $dayOfWeek = filter_input(
        INPUT_POST,
        'day_of_week',
        FILTER_VALIDATE_INT
    );

    $occurrenceDate = trim(
        $_POST['occurrence_date'] ?? ''
    );

    $startTime = trim(
        $_POST['start_time'] ?? ''
    );

    $endTime = trim(
        $_POST['end_time'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    $gender = $_POST['gender'] ?? 'ALL';

    $capacity = trim(
        $_POST['capacity'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Tip termina
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $sessionType,
            ['WEEKLY', 'ONE_TIME'],
            true
        )
    ) {

        $error =
            'Odabrani tip termina nije ispravan.';
    }


    /*
    |--------------------------------------------------------------------------
    | Izvedba aktivnosti
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && !$offeringId
    ) {

        $error =
            'Izvedba aktivnosti je obavezna.';
    }


    /*
    |--------------------------------------------------------------------------
    | Dohvat i provjera izvedbe
    |--------------------------------------------------------------------------
    */

    $offering = null;

    if (
        $error === ''
    ) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    o.id AS offering_id,
                    o.activity_id,
                    o.semester_id,

                    a.name,
                    a.points,
                    a.activity_type,

                    sem.start_date AS semester_start_date,
                    sem.end_date AS semester_end_date,

                    sem.name AS semester_name,
                    ay.name AS academic_year_name

                FROM activity_offerings o

                INNER JOIN activities a
                    ON a.id = o.activity_id

                LEFT JOIN semesters sem
                    ON sem.id = o.semester_id

                LEFT JOIN academic_years ay
                    ON ay.id = sem.academic_year_id

                WHERE o.id = :offering_id
                  AND o.professor_id = :professor_id
                  AND o.is_active = 1
                  AND a.is_active = 1

                LIMIT 1
            ");

            $stmt->execute([
                'offering_id' => $offeringId,
                'professor_id' => $professorId
            ]);

            $offering =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offering) {

                $error =
                    'Nemate pravo dodavati termin za odabranu izvedbu aktivnosti.';
            }

        } catch (PDOException $e) {

            $error =
                'Došlo je do pogreške prilikom provjere aktivnosti.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Tjedni ili jednokratni termin
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        if ($sessionType === 'WEEKLY') {

            if (
                $dayOfWeek === false
                || $dayOfWeek < 1
                || $dayOfWeek > 7
            ) {

                $error =
                    'Odabrani dan nije ispravan.';
            }


            /*
             * Tjedni termin može postojati samo ako izvedba
             * pripada konkretnom semestru.
             */

            if (
                $error === ''
                && (
                    empty($offering['semester_id'])
                    || empty($offering['semester_start_date'])
                    || empty($offering['semester_end_date'])
                )
            ) {

                $error =
                    'Tjedni termin nije moguće dodati za opću aktivnost '
                    . 'koja nije vezana uz konkretan semestar.';
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Jednokratni termin
            |--------------------------------------------------------------------------
            */

            if ($occurrenceDate === '') {

                $error =
                    'Datum održavanja je obavezan.';

            } else {

                $date = DateTime::createFromFormat(
                    '!Y-m-d',
                    $occurrenceDate
                );

                $dateErrors = DateTime::getLastErrors();

                if (
                    !$date
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
                    $date->format('Y-m-d') !== $occurrenceDate
                ) {

                    $error =
                        'Datum održavanja nije ispravan.';

                } else {

                    /*
                     * day_of_week je obavezan u bazi.
                     * Za ONE_TIME ga računamo iz datuma.
                     */

                    $dayOfWeek =
                        (int) $date->format('N');
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera datuma za konkretnu izvedbu semestra
    |--------------------------------------------------------------------------
    |
    | Ako aktivnost pripada semestru, datum mora biti unutar tog semestra.
    |
    | BONUS / opća aktivnost bez semestra može imati jednokratni termin
    | izvan semestralnog raspona.
    |
    */

    if (
        $error === ''
        && $sessionType === 'ONE_TIME'
        && !empty($offering['semester_id'])
    ) {

        $semesterStart =
            new DateTime(
                $offering['semester_start_date']
            );

        $semesterEnd =
            new DateTime(
                $offering['semester_end_date']
            );

        $selectedDate =
            new DateTime($occurrenceDate);

        if (
            $selectedDate < $semesterStart
            ||
            $selectedDate > $semesterEnd
        ) {

            $error =
                'Datum termina mora biti unutar semestra '
                . '('
                . date(
                    'd.m.Y.',
                    strtotime(
                        $offering['semester_start_date']
                    )
                )
                . ' - '
                . date(
                    'd.m.Y.',
                    strtotime(
                        $offering['semester_end_date']
                    )
                )
                . ').';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Vrijeme
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $validStart =
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $startTime
            );

        $validEnd =
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $endTime
            );

        if (!$validStart || !$validEnd) {

            $error =
                'Vrijeme početka i završetka nije ispravno.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Početak prije završetka
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $startTime >= $endTime
    ) {

        $error =
            'Vrijeme završetka mora biti nakon vremena početka.';
    }


    /*
    |--------------------------------------------------------------------------
    | Lokacija
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $location === ''
    ) {

        $error =
            'Lokacija je obavezna.';
    }


    if (
        $error === ''
        && mb_strlen($location) > 150
    ) {

        $error =
            'Lokacija može sadržavati najviše 150 znakova.';
    }


    /*
    |--------------------------------------------------------------------------
    | Spol
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        !in_array(
            $gender,
            ['ALL', 'FEMALE', 'MALE'],
            true
        )
    ) {

        $error =
            'Odabrani spol nije ispravan.';
    }


    /*
    |--------------------------------------------------------------------------
    | Kapacitet
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $capacity !== ''
    ) {

        if (!ctype_digit($capacity)) {

            $error =
                'Kapacitet mora biti cijeli pozitivan broj.';

        } elseif ((int) $capacity <= 0) {

            $error =
                'Kapacitet mora biti veći od 0.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera preklapanja
    |--------------------------------------------------------------------------
    |
    | Provjeravamo unutar ISTE izvedbe aktivnosti.
    |
    */

    if ($error === '') {

        try {

            if ($sessionType === 'WEEKLY') {

                /*
                |--------------------------------------------------------------------------
                | WEEKLY
                |--------------------------------------------------------------------------
                |
                | Provjeravamo:
                |
                | - postojeći WEEKLY termin istog dana
                | - postojeći ONE_TIME termin koji pada tog dana
                |
                */

                $stmt = $pdo->prepare("
                    SELECT s.id

                    FROM activity_sessions s

                    LEFT JOIN activity_occurrences o
                        ON o.session_id = s.id

                    WHERE s.offering_id = :offering_id
                      AND s.is_active = 1

                      AND (
                            (
                                s.session_type = 'WEEKLY'
                                AND s.day_of_week = :day_of_week
                            )

                            OR

                            (
                                s.session_type = 'ONE_TIME'
                                AND WEEKDAY(o.occurrence_date) + 1 = :day_of_week
                            )
                      )

                      AND s.start_time < :end_time
                      AND s.end_time > :start_time

                    LIMIT 1
                ");

                $stmt->execute([
                    'offering_id' => $offeringId,
                    'day_of_week' => $dayOfWeek,
                    'start_time'  => $startTime,
                    'end_time'    => $endTime
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | ONE_TIME
                |--------------------------------------------------------------------------
                |
                | Provjeravamo postoji li na baš taj datum
                | već neki termin iste izvedbe.
                |
                */

                $stmt = $pdo->prepare("
                    SELECT s.id

                    FROM activity_sessions s

                    INNER JOIN activity_occurrences o
                        ON o.session_id = s.id

                    WHERE s.offering_id = :offering_id
                      AND s.is_active = 1
                      AND o.occurrence_date = :occurrence_date

                      AND s.start_time < :end_time
                      AND s.end_time > :start_time

                    LIMIT 1
                ");

                $stmt->execute([
                    'offering_id'    => $offeringId,
                    'occurrence_date' => $occurrenceDate,
                    'start_time'     => $startTime,
                    'end_time'       => $endTime
                ]);
            }


            if ($stmt->fetch()) {

                $error =
                    'Za ovu izvedbu aktivnosti već postoji termin '
                    . 'koji se preklapa s odabranim vremenom.';
            }

        } catch (PDOException $e) {

            $error =
                'Došlo je do pogreške prilikom provjere termina.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Spremanje + occurrences
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | 1. Kreiranje termina
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO activity_sessions (
                    activity_id,
                    offering_id,
                    session_type,
                    day_of_week,
                    start_time,
                    end_time,
                    location,
                    gender,
                    capacity,
                    is_active
                )
                VALUES (
                    :activity_id,
                    :offering_id,
                    :session_type,
                    :day_of_week,
                    :start_time,
                    :end_time,
                    :location,
                    :gender,
                    :capacity,
                    1
                )
            ");

            $stmt->execute([
                'activity_id' =>
                    (int) $offering['activity_id'],

                'offering_id' =>
                    (int) $offeringId,

                'session_type' =>
                    $sessionType,

                'day_of_week' =>
                    $dayOfWeek,

                'start_time' =>
                    $startTime,

                'end_time' =>
                    $endTime,

                'location' =>
                    $location,

                'gender' =>
                    $gender,

                'capacity' =>
                    $capacity === ''
                        ? null
                        : (int) $capacity
            ]);


            $sessionId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | 2. Priprema INSERT-a za occurrence
            |--------------------------------------------------------------------------
            */

            $occurrenceStmt = $pdo->prepare("
                INSERT INTO activity_occurrences (
                    session_id,
                    occurrence_date
                )
                VALUES (
                    :session_id,
                    :occurrence_date
                )
            ");


            /*
            |--------------------------------------------------------------------------
            | 3. WEEKLY
            |--------------------------------------------------------------------------
            |
            | Datumi se generiraju automatski prema početku i kraju
            | semestra kojem pripada izvedba.
            |
            */

            if ($sessionType === 'WEEKLY') {

                $currentDate =
                    new DateTime(
                        $offering['semester_start_date']
                    );

                $semesterEnd =
                    new DateTime(
                        $offering['semester_end_date']
                    );

                while ($currentDate <= $semesterEnd) {

                    if (
                        (int) $currentDate->format('N')
                        ===
                        (int) $dayOfWeek
                    ) {

                        $occurrenceStmt->execute([
                            'session_id' =>
                                $sessionId,

                            'occurrence_date' =>
                                $currentDate->format('Y-m-d')
                        ]);
                    }

                    $currentDate->modify('+1 day');
                }


            } else {

                /*
                |--------------------------------------------------------------------------
                | 4. ONE_TIME
                |--------------------------------------------------------------------------
                |
                | Točno jedan occurrence.
                |
                */

                $occurrenceStmt->execute([
                    'session_id' =>
                        $sessionId,

                    'occurrence_date' =>
                        $occurrenceDate
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | 5. Sve je uspješno
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            header(
                'Location: sessions.php?message=created'
            );

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                'Došlo je do pogreške prilikom '
                . 'spremanja termina i njegovih datuma.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Pomoćne funkcije
|--------------------------------------------------------------------------
*/

function dayName($day)
{
    $days = [
        1 => 'Ponedjeljak',
        2 => 'Utorak',
        3 => 'Srijeda',
        4 => 'Četvrtak',
        5 => 'Petak',
        6 => 'Subota',
        7 => 'Nedjelja'
    ];

    return $days[$day] ?? '';
}

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dodaj termin - Profesor</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <style>

        .form-card {
            max-width: 700px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #172b4d;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #07558a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .activity-info {
            background-color: #f1f7fb;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 25px;
            color: #07558a;
            line-height: 1.5;
        }

        .form-help {
            margin-top: 6px;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.45;
        }

        .error-message {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            background-color: #fdecec;
            color: #b91c1c;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .cancel-link {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            color: #374151;
            background-color: #e5e7eb;
        }

        .cancel-link:hover {
            background-color: #d1d5db;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 700px) {

            .form-row {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">

    <div class="form-card">

        <h1>
            Dodaj termin
        </h1>

        <p class="subtitle">
            Kreiraj novi termin za svoju izvedbu aktivnosti.
        </p>


        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <section class="card">

            <form method="POST">


                <!--
                =================================================================
                Izvedba aktivnosti
                =================================================================
                -->

                <div class="form-group">

                    <label for="offering_id">
                        Aktivnost
                    </label>

                    <select
                        id="offering_id"
                        name="offering_id"
                        required
                    >

                        <option value="">
                            Odaberite aktivnost
                        </option>


                        <?php foreach ($offerings as $offering): ?>

                            <?php

                            $offeringLabel =
                                $offering['name'];

                            if (
                                !empty(
                                    $offering['academic_year_name']
                                )
                                &&
                                !empty(
                                    $offering['semester_name']
                                )
                            ) {

                                $offeringLabel .=
                                    ' - '
                                    . $offering['academic_year_name']
                                    . ' - '
                                    . $offering['semester_name'];

                            } else {

                                $offeringLabel .=
                                    ' - Opća aktivnost';
                            }

                            ?>

                            <option
                                value="<?= (int) $offering['offering_id'] ?>"
                                <?= (string) $offeringId ===
                                    (string) $offering['offering_id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $offeringLabel
                                ) ?>

                                -
                                <?= (int) $offering['points'] ?>
                                bod.

                                (
                                <?= htmlspecialchars(
                                    $offering['activity_type']
                                ) ?>
                                )

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <div class="form-help">
                        Možete odabrati samo aktivne izvedbe aktivnosti
                        koje su vam dodijeljene.
                    </div>

                </div>


                <!--
                =================================================================
                Tip termina
                =================================================================
                -->

                <div class="form-group">

                    <label for="session_type">
                        Tip termina
                    </label>

                    <select
                        id="session_type"
                        name="session_type"
                        onchange="toggleSessionType()"
                        required
                    >

                        <option
                            value="WEEKLY"
                            <?= $sessionType === 'WEEKLY'
                                ? 'selected'
                                : '' ?>
                        >
                            Tjedni termin
                        </option>

                        <option
                            value="ONE_TIME"
                            <?= $sessionType === 'ONE_TIME'
                                ? 'selected'
                                : '' ?>
                        >
                            Jednokratni termin
                        </option>

                    </select>


                    <div class="form-help">

                        <span id="weekly-help">
                            Tjedni termin ponavljat će se kroz cijeli
                            semestar od početka do kraja semestra.
                        </span>


                        <span
                            id="one-time-help"
                            class="hidden"
                        >
                            Termin će se održati samo na odabranom datumu.
                        </span>

                    </div>

                </div>


                <!--
                =================================================================
                Dan
                =================================================================
                -->

                <div
                    class="form-group"
                    id="weekly-day-group"
                >

                    <label for="day_of_week">
                        Dan
                    </label>

                    <select
                        id="day_of_week"
                        name="day_of_week"
                    >

                        <option value="">
                            Odaberite dan
                        </option>


                        <?php for ($day = 1; $day <= 7; $day++): ?>

                            <option
                                value="<?= $day ?>"
                                <?= (string) $dayOfWeek ===
                                    (string) $day
                                    && $sessionType === 'WEEKLY'
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    dayName($day)
                                ) ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <!--
                =================================================================
                Datum
                =================================================================
                -->

                <div
                    class="form-group hidden"
                    id="one-time-date-group"
                >

                    <label for="occurrence_date">
                        Datum održavanja
                    </label>

                    <input
                        type="date"
                        id="occurrence_date"
                        name="occurrence_date"
                        value="<?= htmlspecialchars(
                            $occurrenceDate
                        ) ?>"
                    >


                    <div
                        class="form-help"
                        id="semester-date-help"
                    >
                        Za aktivnosti povezane sa semestrom datum mora biti
                        unutar trajanja tog semestra.
                    </div>

                </div>


                <!--
                =================================================================
                Vrijeme
                =================================================================
                -->

                <div class="form-row">

                    <div class="form-group">

                        <label for="start_time">
                            Vrijeme početka
                        </label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= htmlspecialchars(
                                $startTime
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="end_time">
                            Vrijeme završetka
                        </label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= htmlspecialchars(
                                $endTime
                            ) ?>"
                            required
                        >

                    </div>

                </div>


                <!--
                =================================================================
                Lokacija
                =================================================================
                -->

                <div class="form-group">

                    <label for="location">
                        Lokacija
                    </label>

                    <input
                        type="text"
                        id="location"
                        name="location"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $location
                        ) ?>"
                        placeholder="npr. Velesajam, paviljon 25 - ŽKK Medveščak"
                        required
                    >

                </div>


                <!--
                =================================================================
                Spol
                =================================================================
                -->

                <div class="form-group">

                    <label for="gender">
                        Spol
                    </label>

                    <select
                        id="gender"
                        name="gender"
                    >

                        <option
                            value="ALL"
                            <?= $gender === 'ALL'
                                ? 'selected'
                                : '' ?>
                        >
                            Svi
                        </option>

                        <option
                            value="FEMALE"
                            <?= $gender === 'FEMALE'
                                ? 'selected'
                                : '' ?>
                        >
                            Žene
                        </option>

                        <option
                            value="MALE"
                            <?= $gender === 'MALE'
                                ? 'selected'
                                : '' ?>
                        >
                            Muškarci
                        </option>

                    </select>

                    <div class="form-help">
                        Termin mogu rezervirati svi studenti,
                        samo studentice ili samo studenti.
                    </div>

                </div>


                <!--
                =================================================================
                Kapacitet
                =================================================================
                -->

                <div class="form-group">

                    <label for="capacity">
                        Kapacitet
                    </label>

                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        step="1"
                        value="<?= htmlspecialchars(
                            $capacity
                        ) ?>"
                        placeholder="npr. 20"
                    >

                    <div class="form-help">
                        Ostavite prazno ako termin nema
                        ograničenje broja mjesta.
                    </div>

                </div>


                <!--
                =================================================================
                Gumbi
                =================================================================
                -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="button"
                    >
                        Spremi termin
                    </button>


                    <a
                        href="sessions.php"
                        class="cancel-link"
                    >
                        Odustani
                    </a>

                </div>

            </form>

        </section>

    </div>

</main>


<script>

function toggleSessionType()
{
    const type =
        document.getElementById('session_type').value;

    const weeklyGroup =
        document.getElementById('weekly-day-group');

    const oneTimeGroup =
        document.getElementById('one-time-date-group');

    const weeklyHelp =
        document.getElementById('weekly-help');

    const oneTimeHelp =
        document.getElementById('one-time-help');

    const day =
        document.getElementById('day_of_week');

    const date =
        document.getElementById('occurrence_date');


    if (type === 'WEEKLY') {

        weeklyGroup.classList.remove('hidden');

        oneTimeGroup.classList.add('hidden');

        weeklyHelp.classList.remove('hidden');

        oneTimeHelp.classList.add('hidden');

        day.required = true;

        date.required = false;

    } else {

        weeklyGroup.classList.add('hidden');

        oneTimeGroup.classList.remove('hidden');

        weeklyHelp.classList.add('hidden');

        oneTimeHelp.classList.remove('hidden');

        day.required = false;

        date.required = true;
    }
}


document.addEventListener(
    'DOMContentLoaded',
    toggleSessionType
);

</script>


</body>

</html>