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
| Filteri
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? 'ALL';

$offeringFilter = isset($_GET['offering_id'])
    ? (int) $_GET['offering_id']
    : 0;


$allowedStatuses = [
    'ALL',
    'ACTIVE',
    'INACTIVE'
];


if (!in_array(
    $statusFilter,
    $allowedStatuses,
    true
)) {

    $statusFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| Dohvat profesorovih izvedbi za filter
|--------------------------------------------------------------------------
|
| Profesor može filtrirati samo po svojim izvedbama.
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
      AND a.is_active = 1

    ORDER BY
        a.name ASC,
        sem.start_date ASC
";


$activityStmt =
    $pdo->prepare($activitySql);


$activityStmt->execute([
    $professorId
]);


$activities =
    $activityStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Dohvat termina i statistike prisutnosti
|--------------------------------------------------------------------------
|
| Termin pripada profesoru isključivo ako njegov
| activity_offering pripada tom profesoru.
|
*/

$where = [
    'o.professor_id = ?'
];

$params = [
    $professorId
];


/*
|--------------------------------------------------------------------------
| Filter po statusu termina
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'ACTIVE') {

    $where[] =
        's.is_active = 1';

}


if ($statusFilter === 'INACTIVE') {

    $where[] =
        's.is_active = 0';

}


/*
|--------------------------------------------------------------------------
| Filter po izvedbi aktivnosti
|--------------------------------------------------------------------------
*/

if ($offeringFilter > 0) {

    $where[] =
        'o.id = ?';

    $params[] =
        $offeringFilter;
}


$whereSql =
    implode(
        ' AND ',
        $where
    );


/*
|--------------------------------------------------------------------------
| SQL za termine
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        s.id AS session_id,
        s.offering_id,

        s.day_of_week,
        s.session_type,

        (
            SELECT
                MIN(occ.occurrence_date)

            FROM activity_occurrences occ

            WHERE occ.session_id = s.id

        ) AS occurrence_date,

        s.start_time,
        s.end_time,
        s.location,
        s.capacity,
        s.is_active,


        a.id AS activity_id,
        a.name AS activity_name,
        a.points,


        sem.name AS semester_name,
        ay.name AS academic_year_name,


        COUNT(r.id)
            AS total_reservations,


        SUM(
            CASE

                WHEN r.status = 'ATTENDED'

                THEN 1

                ELSE 0

            END

        ) AS attended_count,


        SUM(
            CASE

                WHEN r.status = 'NO_SHOW'

                THEN 1

                ELSE 0

            END

        ) AS no_show_count,


        SUM(
            CASE

                WHEN r.status = 'RESERVED'

                THEN 1

                ELSE 0

            END

        ) AS reserved_count,


        COALESCE(
            SUM(r.points_awarded),
            0
        ) AS total_points


    FROM activity_sessions s


    INNER JOIN activity_offerings o
        ON o.id = s.offering_id


    INNER JOIN activities a
        ON a.id = o.activity_id


    LEFT JOIN semesters sem
        ON sem.id = o.semester_id


    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id


    LEFT JOIN reservations r
        ON r.session_id = s.id


    WHERE {$whereSql}


    GROUP BY

        s.id,
        s.offering_id,

        s.day_of_week,
        s.session_type,
        s.start_time,
        s.end_time,
        s.location,
        s.capacity,
        s.is_active,

        a.id,
        a.name,
        a.points,

        sem.name,
        ay.name


    ORDER BY

        a.name ASC,
        sem.start_date ASC,
        s.day_of_week ASC,
        s.start_time ASC
";


$stmt =
    $pdo->prepare($sql);


$stmt->execute(
    $params
);


$sessions =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Pomoćna funkcija za dan u tjednu
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


    return
        $days[(int) $day]
        ?? '';
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

    <title>
        Prisustvo i bodovi - Profesor
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

        .page-header {
            margin-bottom: 25px;
        }


        .page-header p {
            color: #5b6470;
            font-size: 17px;
        }


        /*
        |--------------------------------------------------------------------------
        | Filteri
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background-color: #ffffff;

            border-radius: 12px;

            padding: 22px 25px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
        }


        .filter-title {
            margin: 0 0 18px;

            font-size: 20px;

            color: #07558a;
        }


        .filter-form {
            display: flex;

            align-items: flex-end;

            gap: 20px;

            flex-wrap: wrap;
        }


        .filter-group {
            display: flex;

            flex-direction: column;

            gap: 7px;

            min-width: 210px;
        }


        .filter-group label {
            font-weight: bold;

            color: #172b4d;
        }


        .filter-group select {
            padding: 10px 12px;

            border: 1px solid #d0d7de;

            border-radius: 7px;

            background-color: #ffffff;

            color: #172b4d;

            font-size: 15px;

            min-width: 210px;
        }


        .filter-group select:focus {
            outline: none;

            border-color: #07558a;
        }


        .filter-button {
            padding: 10px 18px;

            border: none;

            border-radius: 7px;

            background-color: #07558a;

            color: #ffffff;

            font-weight: bold;

            font-size: 15px;

            cursor: pointer;
        }


        .filter-button:hover {
            background-color: #064a77;
        }


        /*
        |--------------------------------------------------------------------------
        | Lista prisutnosti
        |--------------------------------------------------------------------------
        */

        .attendance-list {
            display: grid;

            gap: 15px;
        }


        .attendance-card {
            background-color: #ffffff;

            border-radius: 12px;

            padding: 22px 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);

            display: grid;

            grid-template-columns:
                1.4fr
                1.3fr
                1.2fr
                1.2fr
                auto;

            gap: 20px;

            align-items: center;
        }


        .activity-name {
            font-size: 18px;

            font-weight: bold;

            color: #172b4d;
        }


        .activity-name span {
            display: block;

            margin-top: 5px;

            font-size: 14px;

            font-weight: normal;

            color: #5b6470;
        }


        .activity-semester {
            margin-top: 6px;

            font-size: 13px;

            color: #6b7280;
        }


        .attendance-detail {
            font-size: 15px;
        }


        .attendance-detail strong {
            display: block;

            color: #07558a;

            margin-bottom: 4px;
        }


        .statistics {
            display: flex;

            flex-direction: column;

            gap: 3px;
        }


        .attended {
            color: #267a2e;

            font-weight: bold;
        }


        .no-show {
            color: #b91c1c;

            font-weight: bold;
        }


        .reserved {
            color: #07558a;

            font-weight: bold;
        }


        .points {
            font-weight: bold;

            color: #172b4d;
        }


        .attendance-link {
            display: inline-block;

            padding: 9px 14px;

            border-radius: 7px;

            background-color: #07558a;

            color: #ffffff;

            text-decoration: none;

            font-weight: bold;

            text-align: center;

            white-space: nowrap;
        }


        .attendance-link:hover {
            background-color: #064a77;
        }


        .attendance-disabled {
            display: inline-block;

            padding: 9px 14px;

            border-radius: 7px;

            background-color: #eeeeee;

            color: #777777;

            font-weight: bold;

            text-align: center;

            white-space: nowrap;
        }


        .inactive {
            display: inline-block;

            margin-top: 5px;

            font-size: 13px;

            color: #b91c1c;

            font-weight: bold;
        }


        .empty-state {
            background-color: #ffffff;

            border-radius: 12px;

            padding: 35px;

            text-align: center;

            color: #5b6470;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .attendance-card {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .filter-form {
                flex-direction: column;

                align-items: stretch;
            }


            .filter-group,
            .filter-group select {
                width: 100%;
            }


            .filter-button {
                width: 100%;
            }


            .attendance-card {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <section class="page-header">

        <h1>
            Prisustvo i bodovi
        </h1>

        <p>
            Pregled prisutnosti studenata i ostvarenih bodova
            po terminima.
        </p>

    </section>


    <!-- FILTERI -->

    <section class="filter-card">

        <h2 class="filter-title">
            Filteri
        </h2>


        <form
            method="GET"
            class="filter-form"
        >


            <!-- Status -->

            <div class="filter-group">

                <label for="status">
                    Status termina
                </label>


                <select
                    name="status"
                    id="status"
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


            <!-- Izvedba aktivnosti -->

            <div class="filter-group">

                <label for="offering_id">
                    Aktivnost
                </label>


                <select
                    name="offering_id"
                    id="offering_id"
                >

                    <option value="0">
                        Sve aktivnosti
                    </option>


                    <?php foreach (
                        $activities
                        as $activity
                    ): ?>

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
                                . $activity[
                                    'academic_year_name'
                                ]
                                . ' - '
                                . $activity[
                                    'semester_name'
                                ];

                        } else {

                            $activityLabel .=
                                ' - Opća aktivnost';
                        }

                        ?>


                        <option
                            value="<?= (int) $activity['offering_id'] ?>"
                            <?= $offeringFilter ===
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


            <button
                type="submit"
                class="filter-button"
            >
                Primijeni filter
            </button>


        </form>

    </section>


    <?php if (empty($sessions)): ?>


        <div class="empty-state">

            Trenutno nema dostupnih termina
            prema odabranim filterima.

        </div>


    <?php else: ?>


        <section class="attendance-list">


            <?php foreach (
                $sessions
                as $session
            ): ?>


                <div class="attendance-card">


                    <!-- Aktivnost -->

                    <div class="activity-name">

                        <?= htmlspecialchars(
                            $session['activity_name']
                        ) ?>


                        <span>

                            <?= (int) $session['points'] ?>

                            <?= (
                                (int) $session['points'] === 1
                            )
                                ? 'bod'
                                : 'boda'
                            ?>

                        </span>


                        <?php if (
                            !empty(
                                $session[
                                    'academic_year_name'
                                ]
                            )
                            &&
                            !empty(
                                $session[
                                    'semester_name'
                                ]
                            )
                        ): ?>

                            <div class="activity-semester">

                                <?= htmlspecialchars(
                                    $session[
                                        'academic_year_name'
                                    ]
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $session[
                                        'semester_name'
                                    ]
                                ) ?>

                            </div>

                        <?php else: ?>

                            <div class="activity-semester">
                                Opća aktivnost
                            </div>

                        <?php endif; ?>


                        <?php if (
                            (int) $session[
                                'is_active'
                            ] !== 1
                        ): ?>

                            <span class="inactive">
                                Neaktivan termin
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- Termin -->

                    <div class="attendance-detail">

                        <strong>
                            Termin
                        </strong>


                        <?php if (
                            $session[
                                'session_type'
                            ] === 'ONE_TIME'
                        ): ?>

                            <?= !empty(
                                $session[
                                    'occurrence_date'
                                ]
                            )
                                ? htmlspecialchars(
                                    date(
                                        'd.m.Y.',
                                        strtotime(
                                            $session[
                                                'occurrence_date'
                                            ]
                                        )
                                    )
                                )
                                : 'Jednokratni termin'
                            ?>


                        <?php else: ?>

                            <?= htmlspecialchars(
                                dayName(
                                    $session[
                                        'day_of_week'
                                    ]
                                )
                            ) ?>

                        <?php endif; ?>


                        <br>


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


                        <?php if (
                            !empty(
                                $session['location']
                            )
                        ): ?>

                            <br>

                            <?= htmlspecialchars(
                                $session[
                                    'location'
                                ]
                            ) ?>

                        <?php endif; ?>

                    </div>


                    <!-- Statistika -->

                    <div class="attendance-detail">

                        <strong>
                            Evidencija
                        </strong>


                        <div class="statistics">

                            <span class="attended">

                                Dolasci:
                                <?= (int) $session[
                                    'attended_count'
                                ] ?>

                            </span>


                            <span class="no-show">

                                Nedolasci:
                                <?= (int) $session[
                                    'no_show_count'
                                ] ?>

                            </span>


                            <span class="reserved">

                                Čekaju:
                                <?= (int) $session[
                                    'reserved_count'
                                ] ?>

                            </span>

                        </div>

                    </div>


                    <!-- Bodovi -->

                    <div class="attendance-detail">

                        <strong>
                            Bodovi
                        </strong>


                        <span class="points">

                            <?= (int) $session[
                                'total_points'
                            ] ?>

                        </span>

                    </div>


                    <!-- Otvaranje evidencije -->

                    <div>

                        <?php if (
                            (int) $session[
                                'is_active'
                            ] === 1
                        ): ?>

                            <a
                                href="session_reservations.php?session_id=<?= (int) $session['session_id'] ?>"
                                class="attendance-link"
                            >
                                Evidencija
                            </a>

                        <?php else: ?>

                            <span class="attendance-disabled">
                                Neaktivan termin
                            </span>

                        <?php endif; ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </section>


    <?php endif; ?>


</main>

</body>

</html>