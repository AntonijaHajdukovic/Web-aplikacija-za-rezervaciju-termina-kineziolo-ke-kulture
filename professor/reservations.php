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
 * Dohvat filtera
 */

$activityFilter = isset($_GET['activity_id'])
    ? (int) $_GET['activity_id']
    : 0;

$statusFilter = $_GET['status'] ?? '';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';


/*
 * Dozvoljeni statusi rezervacija
 */

$allowedStatuses = [
    'RESERVED',
    'CANCELLED',
    'ATTENDED',
    'NO_SHOW'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnosti / izvedbi profesora za filter
|--------------------------------------------------------------------------
|
| Profesor vidi samo svoje aktivne activity_offerings.
|
*/

$activitySql = "
    SELECT
        o.id AS offering_id,
        a.name AS activity_name,

        sem.name AS semester_name,
        ay.name AS academic_year_name

    FROM activity_offerings o

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    WHERE o.professor_id = ?
      AND o.is_active = 1
      AND a.is_active = 1

    ORDER BY
        a.name ASC,
        sem.start_date ASC
";

$activityStmt = $pdo->prepare($activitySql);

$activityStmt->execute([
    $professorId
]);

$professorActivities =
    $activityStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Dohvat rezervacija
|--------------------------------------------------------------------------
|
| Rezervacije dohvaćamo samo za activity_offerings
| trenutno prijavljenog profesora.
|
*/

$sql = "
    SELECT
        r.id AS reservation_id,
        r.reservation_date,
        r.status,
        r.points_awarded,

        s.id AS session_id,
        s.start_time,
        s.end_time,

        o.id AS offering_id,
        o.semester_id,

        a.id AS activity_id,
        a.name AS activity_name,
        a.points,

        sem.name AS semester_name,
        ay.name AS academic_year_name,

        u.first_name,
        u.last_name,
        u.email

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

    INNER JOIN users u
        ON u.id = r.student_id

    WHERE o.professor_id = ?
      AND o.is_active = 1
      AND a.is_active = 1
";

$params = [
    $professorId
];


/*
|--------------------------------------------------------------------------
| Filter po aktivnosti / izvedbi
|--------------------------------------------------------------------------
*/

if ($activityFilter > 0) {

    $sql .= "
        AND o.id = ?
    ";

    $params[] = $activityFilter;
}


/*
|--------------------------------------------------------------------------
| Filter po statusu
|--------------------------------------------------------------------------
*/

if ($statusFilter !== '') {

    $sql .= "
        AND r.status = ?
    ";

    $params[] = $statusFilter;
}


/*
|--------------------------------------------------------------------------
| Filter po početnom datumu
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND r.reservation_date >= ?
    ";

    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| Filter po završnom datumu
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $sql .= "
        AND r.reservation_date <= ?
    ";

    $params[] = $dateTo;
}


/*
|--------------------------------------------------------------------------
| Sortiranje
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        r.reservation_date ASC,
        s.start_time ASC,
        a.name ASC,
        u.last_name ASC,
        u.first_name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$reservations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Rezervacije - Profesor</title>

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

        .page-header p {
            color: #5b6470;
            font-size: 17px;
        }


        /* FILTERI */

        .filter-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            padding: 24px;
            margin-bottom: 30px;
        }

        .filter-title {
            margin: 0 0 20px;
            color: #07558a;
            font-size: 21px;
        }

        .filter-form {
            display: grid;
            grid-template-columns:
                minmax(180px, 1fr)
                minmax(180px, 1fr)
                minmax(160px, 1fr)
                minmax(160px, 1fr);
            gap: 18px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
            color: #303841;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            box-sizing: border-box;
            min-height: 44px;
            padding: 9px 12px;
            border: 1px solid #ccd4dc;
            border-radius: 7px;
            background-color: #ffffff;
            color: #303841;
            font-size: 15px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #07558a;
            box-shadow: 0 0 0 2px rgba(7, 85, 138, 0.1);
        }

        .filter-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 2px;
        }

        .filter-button,
        .reset-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-sizing: border-box;
        }

        .filter-button {
            border: none;
            background-color: #07558a;
            color: #ffffff;
        }

        .filter-button:hover {
            background-color: #064a78;
        }

        .reset-button {
            border: 1px solid #d7dee5;
            background-color: #f4f7fa;
            color: #07558a;
        }

        .reset-button:hover {
            background-color: #e9eef3;
        }


        /* TABLICA */

        .reservations-wrapper {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            overflow-x: auto;
        }

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .reservations-table th {
            background-color: #f4f7fa;
            color: #07558a;
            text-align: left;
            padding: 15px 18px;
            font-size: 15px;
            font-weight: 600;
            border-bottom: 1px solid #e1e6eb;
        }

        .reservations-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #edf0f2;
            color: #303841;
            font-size: 15px;
        }

        .reservations-table tbody tr:last-child td {
            border-bottom: none;
        }

        .reservations-table tbody tr:hover {
            background-color: #f9fbfc;
        }


        /* SADRŽAJ */

        .student-name {
            font-weight: 600;
            color: #172b4d;
        }

        .activity-name {
            font-weight: 500;
        }

        .activity-context {
            margin-top: 4px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.35;
        }

        .date {
            white-space: nowrap;
        }

        .time {
            white-space: nowrap;
        }

        .points {
            white-space: nowrap;
            font-weight: 600;
        }


        /* STATUSI */

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-reserved {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .status-cancelled {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .status-attended {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .status-no-show {
            background-color: #fdecec;
            color: #b91c1c;
        }


        /* PRAZNO STANJE */

        .empty-state {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: #5b6470;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }


        /* BROJ REZULTATA */

        .results-info {
            margin-bottom: 15px;
            color: #5b6470;
            font-size: 15px;
        }


        /* RESPONSIVE */

        @media (max-width: 900px) {

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 600px) {

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                grid-column: auto;
                flex-direction: column;
            }

            .filter-button,
            .reset-button {
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">

        <section class="page-header">

            <h1>
                Rezervacije
            </h1>

            <p>
                Pregled svih rezervacija studenata
                za vaše aktivnosti.
            </p>

        </section>


        <!-- FILTERI -->

        <section class="filter-card">

            <h2 class="filter-title">
                Filter rezervacija
            </h2>


            <form
                method="GET"
                class="filter-form"
            >

                <!-- Aktivnost -->

                <div class="filter-group">

                    <label for="activity_id">
                        Aktivnost
                    </label>

                    <select
                        id="activity_id"
                        name="activity_id"
                    >

                        <option value="0">
                            Sve aktivnosti
                        </option>


                        <?php foreach ($professorActivities as $activity): ?>

                            <?php

                            $activityLabel =
                                $activity['activity_name'];

                            if (
                                !empty(
                                    $activity['academic_year_name']
                                )
                                &&
                                !empty(
                                    $activity['semester_name']
                                )
                            ) {

                                $activityLabel .=
                                    ' - '
                                    . $activity['academic_year_name']
                                    . ' - '
                                    . $activity['semester_name'];

                            } else {

                                $activityLabel .=
                                    ' - Opća aktivnost';
                            }

                            ?>

                            <option
                                value="<?= (int) $activity['offering_id'] ?>"
                                <?= $activityFilter ===
                                    (int) $activity['offering_id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $activityLabel
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Status -->

                <div class="filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            Svi statusi
                        </option>

                        <option
                            value="RESERVED"
                            <?= $statusFilter === 'RESERVED'
                                ? 'selected'
                                : '' ?>
                        >
                            Rezervirano
                        </option>

                        <option
                            value="CANCELLED"
                            <?= $statusFilter === 'CANCELLED'
                                ? 'selected'
                                : '' ?>
                        >
                            Otkazano
                        </option>

                        <option
                            value="ATTENDED"
                            <?= $statusFilter === 'ATTENDED'
                                ? 'selected'
                                : '' ?>
                        >
                            Dolazak
                        </option>

                        <option
                            value="NO_SHOW"
                            <?= $statusFilter === 'NO_SHOW'
                                ? 'selected'
                                : '' ?>
                        >
                            Nedolazak
                        </option>

                    </select>

                </div>


                <!-- Datum od -->

                <div class="filter-group">

                    <label for="date_from">
                        Datum od
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= htmlspecialchars(
                            $dateFrom
                        ) ?>"
                    >

                </div>


                <!-- Datum do -->

                <div class="filter-group">

                    <label for="date_to">
                        Datum do
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= htmlspecialchars(
                            $dateTo
                        ) ?>"
                    >

                </div>


                <!-- Gumbi -->

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-button"
                    >
                        Primijeni filtere
                    </button>

                    <a
                        href="reservations.php"
                        class="reset-button"
                    >
                        Poništi filtere
                    </a>

                </div>

            </form>

        </section>


        <?php if (empty($reservations)): ?>

            <div class="empty-state">

                <?php if (
                    $activityFilter > 0
                    || $statusFilter !== ''
                    || $dateFrom !== ''
                    || $dateTo !== ''
                ): ?>

                    Trenutno nema rezervacija koje odgovaraju
                    odabranim filterima.

                <?php else: ?>

                    Trenutno nema rezervacija studenata.

                <?php endif; ?>

            </div>


        <?php else: ?>

            <div class="results-info">

                Prikazano rezervacija:
                <strong>
                    <?= count($reservations) ?>
                </strong>

            </div>


            <div class="reservations-wrapper">

                <table class="reservations-table">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Aktivnost
                            </th>

                            <th>
                                Datum
                            </th>

                            <th>
                                Vrijeme
                            </th>

                            <th>
                                Bodovi aktivnosti
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Ostvareni bodovi
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $reservations
                        as $reservation
                    ): ?>

                        <?php

                        $statusClass = '';
                        $statusText = '';

                        switch ($reservation['status']) {

                            case 'RESERVED':

                                $statusClass =
                                    'status-reserved';

                                $statusText =
                                    'Rezervirano';

                                break;


                            case 'CANCELLED':

                                $statusClass =
                                    'status-cancelled';

                                $statusText =
                                    'Otkazano';

                                break;


                            case 'ATTENDED':

                                $statusClass =
                                    'status-attended';

                                $statusText =
                                    'Dolazak';

                                break;


                            case 'NO_SHOW':

                                $statusClass =
                                    'status-no-show';

                                $statusText =
                                    'Nedolazak';

                                break;


                            default:

                                $statusText =
                                    $reservation['status'];

                        }

                        ?>

                        <tr>

                            <td>

                                <span class="student-name">

                                    <?= htmlspecialchars(
                                        $reservation['first_name']
                                        . ' '
                                        . $reservation['last_name']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="activity-name">

                                    <?= htmlspecialchars(
                                        $reservation['activity_name']
                                    ) ?>

                                </span>


                                <?php if (
                                    !empty(
                                        $reservation['academic_year_name']
                                    )
                                    &&
                                    !empty(
                                        $reservation['semester_name']
                                    )
                                ): ?>

                                    <div class="activity-context">

                                        <?= htmlspecialchars(
                                            $reservation['academic_year_name']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $reservation['semester_name']
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <div class="activity-context">
                                        Opća aktivnost
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td class="date">

                                <?= date(
                                    'j. n. Y.',
                                    strtotime(
                                        $reservation['reservation_date']
                                    )
                                ) ?>

                            </td>


                            <td class="time">

                                <?= htmlspecialchars(
                                    substr(
                                        $reservation['start_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                                –

                                <?= htmlspecialchars(
                                    substr(
                                        $reservation['end_time'],
                                        0,
                                        5
                                    )
                                ) ?>

                            </td>


                            <td class="points">

                                <?= (int) $reservation['points'] ?>

                            </td>


                            <td>

                                <span
                                    class="status <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $statusText
                                    ) ?>

                                </span>

                            </td>


                            <td class="points">

                                <?= (int) $reservation['points_awarded'] ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </main>

</body>

</html>