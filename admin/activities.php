<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| Aktivacija / deaktivacija izvedbe aktivnosti
|--------------------------------------------------------------------------
|
| Admin aktivira ili deaktivira konkretnu izvedbu aktivnosti
| iz activity_offerings tablice.
|
| Globalna aktivnost u activities se NE deaktivira.
|
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $offeringId = (int) ($_POST['offering_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($offeringId <= 0) {

        $error = 'Neispravna izvedba aktivnosti.';

    } elseif (
        !in_array(
            $action,
            ['activate', 'deactivate'],
            true
        )
    ) {

        $error = 'Neispravna radnja.';

    } else {

        $newStatus =
            $action === 'activate'
                ? 1
                : 0;


        $stmt = $pdo->prepare("
            UPDATE activity_offerings

            SET
                is_active = :is_active,

                deactivated_at = CASE
                    WHEN :is_active = 1
                        THEN NULL
                    ELSE CURRENT_TIMESTAMP
                END

            WHERE id = :id
        ");


        $stmt->execute([
            'is_active' =>
                $newStatus,

            'id' =>
                $offeringId
        ]);


        if ($action === 'activate') {

            $message =
                'Izvedba aktivnosti je uspješno aktivirana.';

        } else {

            $message =
                'Izvedba aktivnosti je uspješno deaktivirana.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Filteri
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? 'ALL';
$typeFilter = $_GET['type'] ?? 'ALL';


$allowedStatuses = [
    'ALL',
    'ACTIVE',
    'INACTIVE'
];


$allowedTypes = [
    'ALL',
    'REGULAR',
    'BONUS'
];


if (!in_array(
    $statusFilter,
    $allowedStatuses,
    true
)) {

    $statusFilter = 'ALL';
}


if (!in_array(
    $typeFilter,
    $allowedTypes,
    true
)) {

    $typeFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnosti i njihovih izvedbi
|--------------------------------------------------------------------------
|
| activities = globalni katalog aktivnosti
|
| activity_offerings = konkretna izvedba aktivnosti
|                     u semestru i kod profesora
|
*/

$sql = "
    SELECT

        a.id AS activity_id,
        a.name,
        a.description,
        a.points,
        a.activity_type,
        a.is_active AS activity_is_active,
        a.created_at,
        a.deactivated_at,

        o.id AS offering_id,
        o.is_active AS offering_is_active,
        o.semester_id,
        o.professor_id AS offering_professor_id,

        sem.name AS semester_name,
        sem.start_date AS semester_start_date,
        sem.end_date AS semester_end_date,

        ay.name AS academic_year_name,

        u.first_name AS professor_first_name,
        u.last_name AS professor_last_name

    FROM activities a

    LEFT JOIN activity_offerings o
        ON o.activity_id = a.id

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
| Filter statusa
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'ACTIVE') {

    $sql .= "
        AND (
            (
                o.id IS NOT NULL
                AND o.is_active = 1
            )
            OR
            (
                o.id IS NULL
                AND a.is_active = 1
            )
        )
    ";
}


if ($statusFilter === 'INACTIVE') {

    $sql .= "
        AND (
            (
                o.id IS NOT NULL
                AND o.is_active = 0
            )
            OR
            (
                o.id IS NULL
                AND a.is_active = 0
            )
        )
    ";
}


/*
|--------------------------------------------------------------------------
| Filter tipa aktivnosti
|--------------------------------------------------------------------------
*/

if ($typeFilter !== 'ALL') {

    $sql .= "
        AND a.activity_type = :activity_type
    ";

    $params['activity_type'] =
        $typeFilter;
}


/*
|--------------------------------------------------------------------------
| Sortiranje
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        a.name ASC,
        ay.name ASC,
        sem.start_date ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$activities =
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

    <title>
        Aktivnosti - Administracija
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

        .admin-header {
            margin-bottom: 30px;
        }


        .admin-header h1 {
            margin-bottom: 10px;
        }


        .admin-header p {
            color: #5b6470;
            font-size: 17px;
        }


        /*
        |--------------------------------------------------------------------------
        | Poruke
        |--------------------------------------------------------------------------
        */

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: bold;
        }


        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }


        .message.error {
            background-color: #ffebee;
            color: #c62828;
        }


        /*
        |--------------------------------------------------------------------------
        | Filteri
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
        }


        .filter-card h2 {
            color: #07558a;
            margin-bottom: 20px;
            font-size: 22px;
        }


        .filters {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: end;
        }


        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }


        .filter-group label {
            font-weight: bold;
        }


        .filter-group select {
            min-width: 180px;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
            background-color: #ffffff;
        }


        .filter-button {
            background-color: #07558a;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }


        .filter-button:hover {
            background-color: #064a78;
        }


        /*
        |--------------------------------------------------------------------------
        | Aktivnosti
        |--------------------------------------------------------------------------
        */

        .activities-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
            overflow-x: auto;
        }


        .activities-card h2 {
            color: #07558a;
            margin-bottom: 20px;
            font-size: 22px;
        }


        .activities-table {
            width: 100%;
            border-collapse: collapse;
        }


        .activities-table th {
            text-align: left;
            color: #07558a;
            padding: 15px 12px;
            border-bottom: 2px solid #e5e7eb;
        }


        .activities-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }


        .activities-table tr:last-child td {
            border-bottom: none;
        }


        /*
        |--------------------------------------------------------------------------
        | Tip aktivnosti
        |--------------------------------------------------------------------------
        */

        .activity-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }


        .activity-type-regular {
            background-color: #e0f2fe;
            color: #0369a1;
        }


        .activity-type-bonus {
            background-color: #fef3c7;
            color: #92400e;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            font-weight: bold;
        }


        .status-active {
            color: #2e7d32;
        }


        .status-inactive {
            color: #c62828;
        }


        /*
        |--------------------------------------------------------------------------
        | Opis aktivnosti
        |--------------------------------------------------------------------------
        */

        .activity-description {
            color: #5b6470;
            font-size: 14px;
            line-height: 1.4;
            max-width: 300px;
        }


        /*
        |--------------------------------------------------------------------------
        | Bodovi
        |--------------------------------------------------------------------------
        */

        .points {
            font-weight: bold;
            color: #07558a;
        }


        /*
        |--------------------------------------------------------------------------
        | Profesor
        |--------------------------------------------------------------------------
        */

        .professor-name {
            font-weight: 600;
            white-space: nowrap;
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
        | Gumbi
        |--------------------------------------------------------------------------
        */

        .action-button {
            border: none;
            border-radius: 7px;
            padding: 8px 13px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }


        .activate-button {
            background-color: #e8f5e9;
            color: #2e7d32;
        }


        .activate-button:hover {
            background-color: #c8e6c9;
        }


        .deactivate-button {
            background-color: #ffebee;
            color: #c62828;
        }


        .deactivate-button:hover {
            background-color: #ffcdd2;
        }


        /*
        |--------------------------------------------------------------------------
        | Prazno
        |--------------------------------------------------------------------------
        */

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobilni prikaz
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .filters {
                flex-direction: column;
                align-items: stretch;
            }


            .filter-group select {
                width: 100%;
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


    <section class="admin-header">

        <h1>
            Aktivnosti
        </h1>


        <p>
            Pregled i upravljanje aktivnostima kolegija
            Kineziološka kultura.
        </p>

    </section>


    <?php if ($message !== ''): ?>

        <div class="message success">

            <?= htmlspecialchars(
                $message
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- FILTERI -->

    <section class="filter-card">

        <h2>
            Filter aktivnosti
        </h2>


        <form method="GET">

            <div class="filters">


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
                            Sve
                        </option>


                        <option
                            value="ACTIVE"
                            <?= $statusFilter === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Aktivne
                        </option>


                        <option
                            value="INACTIVE"
                            <?= $statusFilter === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Neaktivne
                        </option>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="type">
                        Tip aktivnosti
                    </label>


                    <select
                        id="type"
                        name="type"
                    >

                        <option
                            value="ALL"
                            <?= $typeFilter === 'ALL'
                                ? 'selected'
                                : '' ?>
                        >
                            Sve
                        </option>


                        <option
                            value="REGULAR"
                            <?= $typeFilter === 'REGULAR'
                                ? 'selected'
                                : '' ?>
                        >
                            Redovne
                        </option>


                        <option
                            value="BONUS"
                            <?= $typeFilter === 'BONUS'
                                ? 'selected'
                                : '' ?>
                        >
                            Bonus
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-button"
                >
                    Primijeni filter
                </button>

            </div>

        </form>

    </section>


    <!-- AKTIVNOSTI -->

    <section class="activities-card">


        <h2>

            Aktivnosti
            (<?= count($activities) ?>)

        </h2>


        <?php if (empty($activities)): ?>

            <div class="empty-state">

                Nema aktivnosti koje odgovaraju
                odabranom filteru.

            </div>


        <?php else: ?>


            <table class="activities-table">


                <thead>

                    <tr>

                        <th>
                            Aktivnost
                        </th>


                        <th>
                            Opis
                        </th>


                        <th>
                            Bodovi
                        </th>


                        <th>
                            Tip
                        </th>


                        <th>
                            Semestar
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
                    $activities
                    as $activity
                ): ?>

                    <?php


                    /*
                    |--------------------------------------------------------------------------
                    | Tip aktivnosti
                    |--------------------------------------------------------------------------
                    */

                    $typeClass =
                        match (
                            $activity[
                                'activity_type'
                            ]
                        ) {

                            'REGULAR' =>
                                'activity-type-regular',

                            'BONUS' =>
                                'activity-type-bonus',

                            default =>
                                ''

                        };


                    $typeText =
                        match (
                            $activity[
                                'activity_type'
                            ]
                        ) {

                            'REGULAR' =>
                                'Redovna',

                            'BONUS' =>
                                'Bonus',

                            default =>
                                $activity[
                                    'activity_type'
                                ]

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | Profesor
                    |--------------------------------------------------------------------------
                    */

                    $professorName =
                        trim(
                            (
                                $activity[
                                    'professor_first_name'
                                ]
                                ?? ''
                            )
                            . ' '
                            .
                            (
                                $activity[
                                    'professor_last_name'
                                ]
                                ?? ''
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Status izvedbe
                    |--------------------------------------------------------------------------
                    */

                    $hasOffering =
                        $activity[
                            'offering_id'
                        ] !== null;


                    $isOfferingActive =
                        $hasOffering
                        &&
                        (int) $activity[
                            'offering_is_active'
                        ] === 1;


                    $isActivityActive =
                        (int) $activity[
                            'activity_is_active'
                        ] === 1;


                    /*
                    | Globalna aktivnost je aktivna,
                    | a offering može biti aktivan ili neaktivan.
                    */

                    if ($hasOffering) {

                        $isActive =
                            $isOfferingActive
                            &&
                            $isActivityActive;

                    } else {

                        /*
                        | Aktivnost bez offeringa
                        | još nije dodijeljena.
                        */

                        $isActive =
                            $isActivityActive;
                    }

                    ?>


                    <tr>


                        <!-- Aktivnost -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $activity['name']
                                ) ?>

                            </strong>

                        </td>


                        <!-- Opis -->

                        <td>

                            <div class="activity-description">

                                <?= htmlspecialchars(
                                    $activity['description']
                                ) ?>

                            </div>

                        </td>


                        <!-- Bodovi -->

                        <td>

                            <span class="points">

                                <?= (int) $activity['points'] ?>

                            </span>

                        </td>


                        <!-- Tip -->

                        <td>

                            <span
                                class="
                                    activity-type
                                    <?= $typeClass ?>
                                "
                            >

                                <?= htmlspecialchars(
                                    $typeText
                                ) ?>

                            </span>

                        </td>


                        <!-- Semestar -->

                        <td>

                            <?php if (
                                $activity[
                                    'semester_name'
                                ] !== null
                            ): ?>

                                <div class="semester-name">

                                    <?= htmlspecialchars(
                                        $activity[
                                            'academic_year_name'
                                        ]
                                    ) ?>

                                    <small>

                                        <?= htmlspecialchars(
                                            $activity[
                                                'semester_name'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            <?php elseif (
                                $hasOffering
                            ): ?>

                                <span>
                                    Opća aktivnost
                                </span>

                            <?php else: ?>

                                <span>
                                    Nije dodijeljena
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Profesor -->

                        <td>

                            <span class="professor-name">

                                <?php if (
                                    $professorName !== ''
                                ): ?>

                                    <?= htmlspecialchars(
                                        $professorName
                                    ) ?>

                                <?php else: ?>

                                    Nije dodijeljen

                                <?php endif; ?>

                            </span>

                        </td>


                        <!-- Status -->

                        <td>

                            <?php if (
                                $isActive
                            ): ?>

                                <span
                                    class="
                                        status
                                        status-active
                                    "
                                >

                                    Aktivna

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        status
                                        status-inactive
                                    "
                                >

                                    Neaktivna

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Radnja -->

                        <td>

                            <?php if (
                                $hasOffering
                                &&
                                $isOfferingActive
                                &&
                                $isActivityActive
                            ): ?>

                            <a
                                href="assign_activity.php?activity_id=<?= (int) $activity['activity_id'] ?>"
                                class="action-button"
                                style="
                                    display: inline-block;
                                    text-decoration: none;
                                    background-color: #e8f1f8;
                                    color: #07558a;
                                "
                            >
                                Dodijeli
                            </a>

                                <!-- Deaktiviraj offering -->

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Jeste li sigurni da želite deaktivirati ovu izvedbu aktivnosti?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="offering_id"
                                        value="<?= (int) $activity['offering_id'] ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="deactivate"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            action-button
                                            deactivate-button
                                        "
                                    >

                                        Deaktiviraj

                                    </button>

                                </form>


                            <?php elseif (
                                $hasOffering
                                &&
                                !$isOfferingActive
                            ): ?>


                                <!-- Aktiviraj offering -->

                                <form
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="offering_id"
                                        value="<?= (int) $activity['offering_id'] ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="activate"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            action-button
                                            activate-button
                                        "
                                    >

                                        Aktiviraj

                                    </button>

                                </form>


                            <?php else: ?>


                                <span>
                                    Nije dodijeljena
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