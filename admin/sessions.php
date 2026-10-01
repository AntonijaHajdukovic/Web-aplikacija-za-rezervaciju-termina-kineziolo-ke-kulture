<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');


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

    return $days[(int) $day] ?? '';
}


function genderName($gender)
{
    $genders = [
        'ALL' => 'Svi',
        'FEMALE' => 'Žene',
        'MALE' => 'Muškarci'
    ];

    return $genders[$gender] ?? $gender;
}


/*
|--------------------------------------------------------------------------
| Filteri
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? 'ALL';

$activityFilter = filter_input(
    INPUT_GET,
    'activity_id',
    FILTER_VALIDATE_INT
);

$professorFilter = filter_input(
    INPUT_GET,
    'professor_id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Deaktivacija termina
|--------------------------------------------------------------------------
|
| Termin se ne briše iz baze.
| Samo se postavlja is_active = 0.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'deactivate'
) {

    $sessionId = filter_input(
        INPUT_POST,
        'session_id',
        FILTER_VALIDATE_INT
    );


    if (!$sessionId) {

        header(
            'Location: sessions.php?message=error'
        );

        exit;
    }


    try {

        $stmt = $pdo->prepare("
            UPDATE activity_sessions

            SET
                is_active = 0

            WHERE id = ?
              AND is_active = 1
        ");


        $stmt->execute([
            $sessionId
        ]);


        if ($stmt->rowCount() > 0) {

            header(
                'Location: sessions.php?message=deactivated'
            );

            exit;
        }


        header(
            'Location: sessions.php?message=not_allowed'
        );

        exit;


    } catch (PDOException $e) {

        header(
            'Location: sessions.php?message=error'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnosti za filter
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name

        FROM activities

        WHERE is_active = 1

        ORDER BY name ASC
    ");


    $activities =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    $activities = [];
}


/*
|--------------------------------------------------------------------------
| Dohvat profesora za filter
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            first_name,
            last_name

        FROM users

        WHERE role = 'PROFESSOR'
          AND is_active = 1

        ORDER BY
            last_name ASC,
            first_name ASC
    ");


    $professors =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    $professors = [];
}


/*
|--------------------------------------------------------------------------
| Dohvat termina
|--------------------------------------------------------------------------
|
| Termin:
|
| activity_session
|      ↓
| activity_offering
|      ↓
| activity + profesor + semestar
|
*/

$sql = "
    SELECT

        s.id AS session_id,
        s.offering_id,

        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,
        s.gender,
        s.capacity,
        s.is_active,

        a.id AS activity_id,
        a.name AS activity_name,
        a.activity_type,

        o.professor_id,

        sem.name AS semester_name,
        ay.name AS academic_year_name,

        u.first_name,
        u.last_name

    FROM activity_sessions s

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    LEFT JOIN users u
        ON u.id = o.professor_id

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Filter - status
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'ACTIVE') {

    $sql .= "
        AND s.is_active = 1
    ";


} elseif (
    $statusFilter === 'INACTIVE'
) {

    $sql .= "
        AND s.is_active = 0
    ";
}


/*
|--------------------------------------------------------------------------
| Filter - aktivnost
|--------------------------------------------------------------------------
*/

if ($activityFilter) {

    $sql .= "
        AND o.activity_id = :activity_id
    ";

    $params[':activity_id'] =
        $activityFilter;
}


/*
|--------------------------------------------------------------------------
| Filter - profesor
|--------------------------------------------------------------------------
*/

if ($professorFilter) {

    $sql .= "
        AND o.professor_id = :professor_id
    ";

    $params[':professor_id'] =
        $professorFilter;
}


/*
|--------------------------------------------------------------------------
| Sortiranje
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        a.name ASC,
        sem.start_date ASC,
        s.day_of_week ASC,
        s.start_time ASC
";


/*
|--------------------------------------------------------------------------
| Izvršavanje upita
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $sessions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $error = '';


} catch (PDOException $e) {

    $sessions = [];

    $error =
        'Došlo je do pogreške prilikom dohvaćanja termina.';
}


/*
|--------------------------------------------------------------------------
| Poruke
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
        Termini - Administracija
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
        | Zaglavlje
        |--------------------------------------------------------------------------
        */

        .admin-header {
            margin-bottom: 30px;
        }

        .admin-header h1 {
            margin-bottom: 8px;
        }

        .admin-header p {
            color: #5b6470;
            font-size: 17px;
            margin: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Filter
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 28px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .filter-card h2 {
            color: #07558a;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .filters {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: bold;
            color: #172b4d;
        }

        .filter-group select {
            padding: 11px 13px;
            border: 1px solid #d1d9e0;
            border-radius: 7px;
            background-color: #ffffff;
            font-size: 15px;
            min-width: 200px;
        }

        .filter-button {
            border: none;
            padding: 12px 20px;

            background-color: #07558a;
            color: #ffffff;

            border-radius: 8px;

            font-size: 15px;
            font-weight: bold;

            cursor: pointer;
        }

        .filter-button:hover {
            background-color: #064a78;
        }


        /*
        |--------------------------------------------------------------------------
        | Tablica
        |--------------------------------------------------------------------------
        */

        .table-card {
            background-color: #ffffff;

            border-radius: 12px;

            padding: 25px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);

            overflow-x: auto;
        }

        .table-title {
            color: #07558a;
            font-size: 22px;

            margin-top: 0;
            margin-bottom: 20px;
        }

        .sessions-table {
            width: 100%;
            border-collapse: collapse;

            min-width: 1150px;
        }

        .sessions-table th {
            background-color: #f6f8fa;

            color: #172b4d;

            text-align: left;

            padding: 15px 14px;

            font-size: 15px;

            border-bottom:
                1px solid #e1e5e9;
        }

        .sessions-table td {
            padding: 15px 14px;

            border-bottom:
                1px solid #e1e5e9;

            color: #374151;

            vertical-align: middle;
        }

        .sessions-table tr:last-child td {
            border-bottom: none;
        }


        /*
        |--------------------------------------------------------------------------
        | Aktivnost
        |--------------------------------------------------------------------------
        */

        .activity-name {
            color: #07558a;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Tip aktivnosti
        |--------------------------------------------------------------------------
        */

        .activity-type {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 13px;
            font-weight: bold;
        }

        .activity-type.regular {
            background-color: #e8f2f8;
            color: #07558a;
        }

        .activity-type.bonus {
            background-color: #fff1c7;
            color: #9a6700;
        }


        /*
        |--------------------------------------------------------------------------
        | Profesor
        |--------------------------------------------------------------------------
        */

        .professor-name {
            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | Semestar
        |--------------------------------------------------------------------------
        */

        .semester-name {
            line-height: 1.4;
        }

        .semester-name small {
            display: block;
            margin-top: 3px;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Spol
        |--------------------------------------------------------------------------
        */

        .gender {
            color: #5b6470;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 13px;
            font-weight: bold;
        }

        .status.active {
            background-color: #e5f4e7;
            color: #16803c;
        }

        .status.inactive {
            background-color: #f1f1f1;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Akcije
        |--------------------------------------------------------------------------
        */

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .deactivate-button {
            border: none;

            padding: 9px 13px;

            border-radius: 7px;

            background-color: #fdeaea;
            color: #c62828;

            font-weight: bold;
            font-size: 14px;

            cursor: pointer;
        }

        .deactivate-button:hover {
            background-color: #f9dada;
        }

        .no-actions {
            color: #9ca3af;
            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Poruke
        |--------------------------------------------------------------------------
        */

        .message {
            margin-bottom: 25px;

            padding: 14px 18px;

            border-radius: 8px;

            font-weight: 500;
        }

        .message.success {
            background-color: #e7f5e9;
            color: #187a35;
        }

        .message.error {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .message.warning {
            background-color: #fff4d6;
            color: #8a6100;
        }


        /*
        |--------------------------------------------------------------------------
        | Prazno stanje
        |--------------------------------------------------------------------------
        */

        .empty-state {
            padding: 40px 20px;

            text-align: center;

            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select {
                width: 100%;
                box-sizing: border-box;
            }

            .filter-button {
                width: 100%;
            }
        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <!-- ==========================================================
         Zaglavlje
    =========================================================== -->

    <section class="admin-header">

        <h1>
            Termini
        </h1>

        <p>
            Pregled i upravljanje terminima aktivnosti
            kolegija Kineziološka kultura.
        </p>

    </section>


    <!-- ==========================================================
         Poruke
    =========================================================== -->

    <?php if ($message === 'deactivated'): ?>

        <div class="message success">
            Termin je uspješno deaktiviran.
        </div>

    <?php elseif ($message === 'not_allowed'): ?>

        <div class="message warning">
            Termin nije moguće deaktivirati.
        </div>

    <?php elseif ($message === 'error'): ?>

        <div class="message error">
            Došlo je do pogreške. Pokušajte ponovno.
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ==========================================================
         Filteri
    =========================================================== -->

    <section class="filter-card">

        <h2>
            Filter termina
        </h2>


        <form method="GET">

            <div class="filters">


                <!-- STATUS -->

                <div class="filter-group">

                    <label for="status">
                        Status
                    </label>


                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="ALL"
                            <?= $statusFilter === 'ALL'
                                ? 'selected'
                                : '' ?>
                        >
                            Svi
                        </option>


                        <option
                            value="ACTIVE"
                            <?= $statusFilter === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Aktivni
                        </option>


                        <option
                            value="INACTIVE"
                            <?= $statusFilter === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Neaktivni
                        </option>

                    </select>

                </div>


                <!-- AKTIVNOST -->

                <div class="filter-group">

                    <label for="activity_id">
                        Aktivnost
                    </label>


                    <select
                        id="activity_id"
                        name="activity_id"
                    >

                        <option value="">
                            Sve
                        </option>


                        <?php foreach (
                            $activities
                            as $activity
                        ): ?>

                            <option
                                value="<?= (int) $activity['id'] ?>"
                                <?= $activityFilter
                                    === (int) $activity['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $activity['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PROFESOR -->

                <div class="filter-group">

                    <label for="professor_id">
                        Profesor
                    </label>


                    <select
                        id="professor_id"
                        name="professor_id"
                    >

                        <option value="">
                            Svi
                        </option>


                        <?php foreach (
                            $professors
                            as $professor
                        ): ?>

                            <?php
                            $professorId =
                                (int) $professor['id'];
                            ?>

                            <option
                                value="<?= $professorId ?>"
                                <?= $professorFilter === $professorId
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $professor['first_name']
                                    . ' '
                                    . $professor['last_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- GUMB -->

                <button
                    type="submit"
                    class="filter-button"
                >
                    Primijeni filter
                </button>

            </div>

        </form>

    </section>


    <!-- ==========================================================
         Termini
    =========================================================== -->

    <section class="table-card">

        <h2 class="table-title">
            Termini (<?= count($sessions) ?>)
        </h2>


        <?php if (empty($sessions)): ?>

            <div class="empty-state">

                Nema termina koji odgovaraju
                odabranom filteru.

            </div>


        <?php else: ?>


            <table class="sessions-table">

                <thead>

                    <tr>

                        <th>
                            Aktivnost
                        </th>

                        <th>
                            Tip
                        </th>

                        <th>
                            Semestar
                        </th>

                        <th>
                            Dan
                        </th>

                        <th>
                            Vrijeme
                        </th>

                        <th>
                            Lokacija
                        </th>

                        <th>
                            Spol
                        </th>

                        <th>
                            Kapacitet
                        </th>

                        <th>
                            Profesor
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Radnja
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $sessions
                    as $session
                ): ?>

                    <tr>


                        <!-- AKTIVNOST -->

                        <td>

                            <span class="activity-name">

                                <?= htmlspecialchars(
                                    $session['activity_name']
                                ) ?>

                            </span>

                        </td>


                        <!-- TIP -->

                        <td>

                            <?php if (
                                $session['activity_type']
                                === 'BONUS'
                            ): ?>

                                <span
                                    class="activity-type bonus"
                                >
                                    Bonus
                                </span>

                            <?php else: ?>

                                <span
                                    class="activity-type regular"
                                >
                                    Redovna
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- SEMESTAR -->

                        <td>

                            <?php if (
                                $session['semester_name']
                                !== null
                            ): ?>

                                <div class="semester-name">

                                    <?= htmlspecialchars(
                                        $session[
                                            'academic_year_name'
                                        ]
                                    ) ?>

                                    <small>

                                        <?= htmlspecialchars(
                                            $session[
                                                'semester_name'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            <?php else: ?>

                                Opća aktivnost

                            <?php endif; ?>

                        </td>


                        <!-- DAN -->

                        <td>

                            <?= htmlspecialchars(
                                dayName(
                                    $session['day_of_week']
                                )
                            ) ?>

                        </td>


                        <!-- VRIJEME -->

                        <td>

                            <?= htmlspecialchars(
                                substr(
                                    $session['start_time'],
                                    0,
                                    5
                                )
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                substr(
                                    $session['end_time'],
                                    0,
                                    5
                                )
                            ) ?>

                        </td>


                        <!-- LOKACIJA -->

                        <td>

                            <?= htmlspecialchars(
                                $session['location']
                            ) ?>

                        </td>


                        <!-- SPOL -->

                        <td>

                            <span class="gender">

                                <?= htmlspecialchars(
                                    genderName(
                                        $session['gender']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- KAPACITET -->

                        <td>

                            <?php if (
                                $session['capacity']
                                !== null
                            ): ?>

                                <?= (int) $session['capacity'] ?>

                                stud.

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <!-- PROFESOR -->

                        <td>

                            <span class="professor-name">

                                <?= htmlspecialchars(
                                    $session['first_name']
                                    . ' '
                                    . $session['last_name']
                                ) ?>

                            </span>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php if (
                                (int) $session['is_active']
                                === 1
                            ): ?>

                                <span
                                    class="status active"
                                >
                                    Aktivni
                                </span>

                            <?php else: ?>

                                <span
                                    class="status inactive"
                                >
                                    Neaktivni
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- RADNJA -->

                        <td>

                            <?php if (
                                (int) $session['is_active']
                                === 1
                            ): ?>

                                <form
                                    method="POST"
                                    onsubmit="
                                        return confirm(
                                            'Jeste li sigurni da želite deaktivirati ovaj termin?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="deactivate"
                                    >


                                    <input
                                        type="hidden"
                                        name="session_id"
                                        value="<?= (int) $session['session_id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="deactivate-button"
                                    >
                                        Deaktiviraj
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="no-actions">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                    </tr>

                <?php endforeach; ?>


                </tbody>

            </table>


        <?php endif; ?>


    </section>


</main>

</body>

</html>