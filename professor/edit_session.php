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
| Dohvat ID-a termina
|--------------------------------------------------------------------------
*/

$sessionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$sessionId) {
    header('Location: sessions.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat termina
|--------------------------------------------------------------------------
|
| Profesor može otvoriti samo termin koji pripada njegovoj
| aktivnoj activity_offering izvedbi.
|
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.offering_id,
        s.session_type,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,
        s.gender,
        s.capacity,

        (
            SELECT o2.occurrence_date
            FROM activity_occurrences o2
            WHERE o2.session_id = s.id
            ORDER BY o2.occurrence_date
            LIMIT 1
        ) AS occurrence_date,

        a.name AS activity_name,
        a.activity_type,
        a.points,

        o.professor_id,
        o.semester_id,

        sem.name AS semester_name,
        sem.start_date AS semester_start_date,
        sem.end_date AS semester_end_date,

        ay.name AS academic_year_name

    FROM activity_sessions s

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    WHERE s.id = :session_id
      AND o.professor_id = :professor_id
      AND o.is_active = 1
      AND a.is_active = 1
      AND s.is_active = 1

    LIMIT 1
");

$stmt->execute([
    'session_id' => $sessionId,
    'professor_id' => $professorId
]);

$session = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$session) {
    header('Location: sessions.php?message=not_allowed');
    exit;
}


/*
|--------------------------------------------------------------------------
| Početne vrijednosti forme
|--------------------------------------------------------------------------
*/

$dayOfWeek = (int) $session['day_of_week'];

$startTime = substr(
    $session['start_time'],
    0,
    5
);

$endTime = substr(
    $session['end_time'],
    0,
    5
);

$location = $session['location'];

$gender = $session['gender'];

$capacity = $session['capacity'];


/*
|--------------------------------------------------------------------------
| Greške
|--------------------------------------------------------------------------
*/

$errors = [];


/*
|--------------------------------------------------------------------------
| Spremanje izmjena
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dayOfWeek = filter_input(
        INPUT_POST,
        'day_of_week',
        FILTER_VALIDATE_INT
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

    $gender = $_POST['gender'] ?? '';

    $capacityInput = trim(
        $_POST['capacity'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validacija dana
    |--------------------------------------------------------------------------
    */

    if (
        $dayOfWeek === false
        || $dayOfWeek < 1
        || $dayOfWeek > 7
    ) {

        $errors[] =
            'Odaberite ispravan dan.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validacija vremena
    |--------------------------------------------------------------------------
    */

    if ($startTime === '') {

        $errors[] =
            'Unesite vrijeme početka.';
    }


    if ($endTime === '') {

        $errors[] =
            'Unesite vrijeme završetka.';
    }


    if (
        $startTime !== ''
        && $endTime !== ''
        && $startTime >= $endTime
    ) {

        $errors[] =
            'Vrijeme završetka mora biti nakon vremena početka.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validacija lokacije
    |--------------------------------------------------------------------------
    */

    if ($location === '') {

        $errors[] =
            'Unesite lokaciju.';
    }


    if (
        $location !== ''
        && mb_strlen($location) > 150
    ) {

        $errors[] =
            'Lokacija može sadržavati najviše 150 znakova.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validacija spola
    |--------------------------------------------------------------------------
    */

    $allowedGenders = [
        'ALL',
        'MALE',
        'FEMALE'
    ];

    if (!in_array($gender, $allowedGenders, true)) {

        $errors[] =
            'Odaberite ispravnu spolnu skupinu.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validacija kapaciteta
    |--------------------------------------------------------------------------
    */

    if ($capacityInput === '') {

        $capacity = null;

    } elseif (
        !ctype_digit($capacityInput)
        || (int) $capacityInput <= 0
    ) {

        $errors[] =
            'Kapacitet mora biti pozitivan cijeli broj.';

    } else {

        $capacity = (int) $capacityInput;
    }


    /*
    |--------------------------------------------------------------------------
    | WEEKLY termin mora pripadati semestru
    |--------------------------------------------------------------------------
    |
    | Kod tjednih termina moramo znati raspon semestra jer se raspored
    | odnosi na konkretan semestar.
    |
    */

    if (
        empty($errors)
        && $session['session_type'] === 'WEEKLY'
        && (
            empty($session['semester_id'])
            || empty($session['semester_start_date'])
            || empty($session['semester_end_date'])
        )
    ) {

        $errors[] =
            'Tjedni termin mora pripadati konkretnom semestru.';
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera preklapanja termina
    |--------------------------------------------------------------------------
    |
    | Preklapanje provjeravamo unutar ISTE activity_offering izvedbe.
    |
    */

    if (empty($errors)) {

        try {

            if ($session['session_type'] === 'WEEKLY') {

                /*
                |--------------------------------------------------------------------------
                | WEEKLY
                |--------------------------------------------------------------------------
                |
                | Provjeravamo:
                |
                | - drugi WEEKLY termin iste izvedbe na isti dan
                | - ONE_TIME termin iste izvedbe koji pada na taj dan
                |
                */

                $overlap = $pdo->prepare("
                    SELECT s.id

                    FROM activity_sessions s

                    LEFT JOIN activity_occurrences o2
                        ON o2.session_id = s.id

                    WHERE s.offering_id = :offering_id
                      AND s.is_active = 1
                      AND s.id <> :session_id

                      AND (
                            (
                                s.session_type = 'WEEKLY'
                                AND s.day_of_week = :day_of_week
                            )

                            OR

                            (
                                s.session_type = 'ONE_TIME'
                                AND WEEKDAY(o2.occurrence_date) + 1 = :day_of_week
                            )
                      )

                      AND s.start_time < :end_time
                      AND s.end_time > :start_time

                    LIMIT 1
                ");

                $overlap->execute([
                    'offering_id' =>
                        $session['offering_id'],

                    'session_id' =>
                        $sessionId,

                    'day_of_week' =>
                        $dayOfWeek,

                    'start_time' =>
                        $startTime,

                    'end_time' =>
                        $endTime
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | ONE_TIME
                |--------------------------------------------------------------------------
                |
                | Provjeravamo postoji li na tom datumu drugi termin
                | iste izvedbe.
                |
                */

                $overlap = $pdo->prepare("
                    SELECT s.id

                    FROM activity_sessions s

                    INNER JOIN activity_occurrences o2
                        ON o2.session_id = s.id

                    WHERE s.offering_id = :offering_id
                      AND s.is_active = 1
                      AND s.id <> :session_id
                      AND o2.occurrence_date = :occurrence_date

                      AND s.start_time < :end_time
                      AND s.end_time > :start_time

                    LIMIT 1
                ");

                $overlap->execute([
                    'offering_id' =>
                        $session['offering_id'],

                    'session_id' =>
                        $sessionId,

                    'occurrence_date' =>
                        $session['occurrence_date'],

                    'start_time' =>
                        $startTime,

                    'end_time' =>
                        $endTime
                ]);
            }


            if ($overlap->fetch()) {

                $errors[] =
                    'Za ovu izvedbu aktivnosti već postoji termin '
                    . 'koji se preklapa s odabranim vremenom.';
            }


        } catch (PDOException $e) {

            $errors[] =
                'Došlo je do pogreške prilikom provjere termina.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Spremanje
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            /*
            |--------------------------------------------------------------------------
            | Dodatna sigurnosna provjera vlasništva
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            $update = $pdo->prepare("
                UPDATE activity_sessions s

                INNER JOIN activity_offerings o
                    ON o.id = s.offering_id

                SET
                    s.day_of_week = :day_of_week,
                    s.start_time = :start_time,
                    s.end_time = :end_time,
                    s.location = :location,
                    s.gender = :gender,
                    s.capacity = :capacity

                WHERE s.id = :session_id
                  AND o.professor_id = :professor_id
                  AND o.is_active = 1
                  AND s.is_active = 1
            ");


            $update->execute([
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
                    $capacity,

                'session_id' =>
                    $sessionId,

                'professor_id' =>
                    $professorId
            ]);


            if ($update->rowCount() === 0) {

                $pdo->rollBack();

                $errors[] =
                    'Termin nije moguće urediti. '
                    . 'Možda više nije aktivan ili mu nemate pravo pristupiti.';

            } else {

                $pdo->commit();

                header(
                    'Location: sessions.php?message=updated'
                );

                exit;
            }


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Došlo je do pogreške prilikom spremanja izmjena.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Naziv dana
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

    <title>Uredi termin - Profesor</title>


    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >


    <style>

        .page-header {
            margin-bottom: 30px;
        }


        .page-header h1 {
            margin-bottom: 8px;
        }


        .page-header p {
            color: #5b6470;
            font-size: 17px;
            margin: 0;
        }


        .session-card {
            max-width: 700px;

            background-color: #ffffff;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }


        .activity-info {
            margin-bottom: 28px;

            padding: 18px;

            background-color: #f5f8fa;

            border-radius: 8px;
        }


        .activity-info h2 {
            margin: 0 0 8px;

            color: #07558a;

            font-size: 21px;
        }


        .activity-info p {
            margin: 0;

            color: #5b6470;
        }


        .activity-info strong {
            color: #172b4d;
        }


        .form-group {
            margin-bottom: 20px;
        }


        .form-group label {
            display: block;

            margin-bottom: 7px;

            color: #172b4d;

            font-weight: bold;
        }


        .form-group input,
        .form-group select {
            width: 100%;

            box-sizing: border-box;

            padding: 11px 13px;

            border: 1px solid #ccd3da;

            border-radius: 7px;

            font-size: 15px;

            font-family: inherit;

            background-color: #ffffff;
        }


        .form-group input:focus,
        .form-group select:focus {
            outline: none;

            border-color: #07558a;
        }


        .form-row {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 18px;
        }


        .errors {
            margin-bottom: 22px;

            padding: 15px 18px;

            border-radius: 8px;

            background-color: #fdecec;

            color: #b91c1c;
        }


        .errors ul {
            margin: 0;

            padding-left: 20px;
        }


        .form-actions {
            display: flex;

            gap: 12px;

            margin-top: 28px;
        }


        .save-button {
            border: none;

            padding: 12px 20px;

            border-radius: 8px;

            background-color: #07558a;

            color: #ffffff;

            font-weight: bold;

            font-size: 15px;

            cursor: pointer;
        }


        .save-button:hover {
            background-color: #064a78;
        }


        .cancel-button {
            display: inline-block;

            padding: 12px 20px;

            border-radius: 8px;

            background-color: #eef1f4;

            color: #374151;

            text-decoration: none;

            font-weight: bold;
        }


        .cancel-button:hover {
            background-color: #e1e5e9;
        }


        @media (max-width: 600px) {

            .session-card {
                padding: 22px;
            }


            .form-row {
                grid-template-columns: 1fr;
            }


            .form-actions {
                flex-direction: column;
            }


            .save-button,
            .cancel-button {
                width: 100%;

                box-sizing: border-box;

                text-align: center;
            }

        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <section class="page-header">

        <h1>
            Uredi termin
        </h1>

        <p>
            Uredite podatke o terminu aktivnosti.
        </p>

    </section>


    <section class="session-card">


        <!--
        ==========================================================================
        Aktivnost
        ==========================================================================
        -->

        <div class="activity-info">

            <h2>
                <?= htmlspecialchars(
                    $session['activity_name']
                ) ?>
            </h2>

            <p>

                <?= htmlspecialchars(
                    $session['activity_type']
                ) ?>

                ·

                <strong>
                    <?= (int) $session['points'] ?>
                    bod.
                </strong>

                <?php if (
                    !empty($session['academic_year_name'])
                    &&
                    !empty($session['semester_name'])
                ): ?>

                    ·

                    <?= htmlspecialchars(
                        $session['academic_year_name']
                    ) ?>

                    -

                    <?= htmlspecialchars(
                        $session['semester_name']
                    ) ?>

                <?php else: ?>

                    · Opća aktivnost

                <?php endif; ?>

            </p>

        </div>


        <!--
        ==========================================================================
        Greške
        ==========================================================================
        -->

        <?php if (!empty($errors)): ?>

            <div class="errors">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!--
        ==========================================================================
        Forma
        ==========================================================================
        -->

        <form
            method="POST"
            action=""
        >


            <!-- Dan -->

            <div class="form-group">

                <label for="day_of_week">
                    Dan
                </label>

                <select
                    id="day_of_week"
                    name="day_of_week"
                    required
                >

                    <option value="">
                        Odaberite dan
                    </option>


                    <?php foreach (
                        $days
                        as $dayNumber => $dayName
                    ): ?>

                        <option
                            value="<?= $dayNumber ?>"
                            <?= (int) $dayOfWeek === $dayNumber
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($dayName) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Vrijeme -->

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


            <!-- Lokacija -->

            <div class="form-group">

                <label for="location">
                    Lokacija
                </label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    value="<?= htmlspecialchars($location) ?>"
                    maxlength="150"
                    required
                >

            </div>


            <!-- Spol -->

            <div class="form-group">

                <label for="gender">
                    Spol
                </label>

                <select
                    id="gender"
                    name="gender"
                    required
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
                        value="MALE"
                        <?= $gender === 'MALE'
                            ? 'selected'
                            : '' ?>
                    >
                        Muškarci
                    </option>

                    <option
                        value="FEMALE"
                        <?= $gender === 'FEMALE'
                            ? 'selected'
                            : '' ?>
                    >
                        Žene
                    </option>

                </select>

            </div>


            <!-- Kapacitet -->

            <div class="form-group">

                <label for="capacity">
                    Kapacitet
                </label>

                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    value="<?= $capacity !== null
                        ? htmlspecialchars($capacity)
                        : '' ?>"
                    min="1"
                    placeholder="Npr. 20"
                >

            </div>


            <!-- Akcije -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="save-button"
                >
                    Spremi izmjene
                </button>


                <a
                    href="sessions.php"
                    class="cancel-button"
                >
                    Odustani
                </a>

            </div>


        </form>


    </section>


</main>

</body>

</html>