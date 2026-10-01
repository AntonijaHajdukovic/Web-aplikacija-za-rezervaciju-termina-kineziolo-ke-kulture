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
| Filter aktivnosti
|--------------------------------------------------------------------------
|
| active   = aktivne izvedbe
| inactive = neaktivne izvedbe
| all      = sve izvedbe
|
*/

$statusFilter = $_GET['status'] ?? 'active';

$allowedStatuses = [
    'active',
    'inactive',
    'all'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'active';
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnosti i njihovih izvedbi
|--------------------------------------------------------------------------
|
| Profesor smije vidjeti sve aktivnosti.
|
| Vlasništvo se više ne određuje preko:
|
|     activities.professor_id
|
| nego preko:
|
|     activity_offerings.professor_id
|
| Aktivnost može imati više izvedbi kroz različite semestre.
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

        o.id AS offering_id,
        o.semester_id,
        o.professor_id AS offering_professor_id,
        o.is_active AS offering_is_active,

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
";


/*
|--------------------------------------------------------------------------
| Statusni filter
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'active') {

    $sql .= "
        WHERE
            a.is_active = 1
            AND o.is_active = 1
    ";

} elseif ($statusFilter === 'inactive') {

    $sql .= "
        WHERE
            (
                o.id IS NOT NULL
                AND o.is_active = 0
            )
            OR
            (
                o.id IS NULL
                AND a.is_active = 0
            )
    ";
}


$sql .= "
    ORDER BY
        a.name ASC,
        sem.start_date ASC,
        o.id ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute();

$activities =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


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

    <title>Aktivnosti - Profesor</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >


    <style>

        .activities-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .activities-header .subtitle {
            margin-bottom: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Filter
        |--------------------------------------------------------------------------
        */

        .activities-filter {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .filter-group select {
            min-width: 180px;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background-color: #ffffff;
            color: #374151;
            font-size: 14px;
            cursor: pointer;
        }

        .filter-button {
            padding: 9px 16px;
            border: none;
            border-radius: 7px;
            background-color: #07558a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button:hover {
            background-color: #064a76;
        }


        /*
        |--------------------------------------------------------------------------
        | Tablica
        |--------------------------------------------------------------------------
        */

        .activities-table-wrapper {
            overflow-x: auto;
        }

        .activities-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activities-table th,
        .activities-table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .activities-table th {
            color: #172b4d;
            font-size: 14px;
            background-color: #f8fafc;
        }

        .activities-table td {
            color: #374151;
        }


        /*
        |--------------------------------------------------------------------------
        | Aktivnost
        |--------------------------------------------------------------------------
        */

        .activity-name {
            font-weight: bold;
            color: #07558a;
        }

        .activity-description {
            max-width: 280px;
            margin-top: 4px;
            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | Kontekst izvedbe
        |--------------------------------------------------------------------------
        */

        .offering-context {
            line-height: 1.4;
        }

        .offering-context strong {
            color: #172b4d;
        }

        .offering-context small {
            display: block;
            margin-top: 3px;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Badgeovi
        |--------------------------------------------------------------------------
        */

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .badge-regular {
            background-color: #e8f1f8;
            color: #07558a;
        }

        .badge-bonus {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-active {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .badge-inactive {
            background-color: #fdecec;
            color: #b91c1c;
        }


        /*
        |--------------------------------------------------------------------------
        | Bodovi
        |--------------------------------------------------------------------------
        */

        .points {
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Profesor
        |--------------------------------------------------------------------------
        */

        .owner {
            white-space: nowrap;
        }

        .owner small {
            display: block;
            margin-top: 4px;
            color: #07558a;
            font-weight: bold;
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
            flex-wrap: wrap;
        }

        .action-link {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .edit-link {
            background-color: #e8f1f8;
            color: #07558a;
        }

        .edit-link:hover {
            background-color: #dbeaf4;
        }

        .deactivate-link {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .deactivate-link:hover {
            background-color: #fbdada;
        }


        /*
        |--------------------------------------------------------------------------
        | Neaktivni redak
        |--------------------------------------------------------------------------
        */

        .inactive-row {
            opacity: 0.65;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .activities-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .activities-filter {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-group select {
                width: 100%;
            }

            .filter-button {
                width: fit-content;
            }

        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <section class="activities-header">

        <div>

            <h1>
                Aktivnosti
            </h1>

            <p class="subtitle">
                Pregled aktivnosti i njihovih semestralnih izvedbi.
            </p>

        </div>


        <a
            href="add_activity.php"
            class="button"
        >
            + Dodaj aktivnost
        </a>

    </section>


    <?php if ($message === 'created'): ?>

        <div class="message success">
            Aktivnost je uspješno dodana.
        </div>


    <?php elseif ($message === 'updated'): ?>

        <div class="message success">
            Aktivnost je uspješno ažurirana.
        </div>


    <?php elseif ($message === 'deactivated'): ?>

        <div class="message success">
            Izvedba aktivnosti je uspješno deaktivirana.
        </div>


    <?php elseif ($message === 'not_allowed'): ?>

        <div class="message error">
            Nemate pravo upravljati ovom izvedbom aktivnosti.
        </div>


    <?php elseif ($message === 'error'): ?>

        <div class="message error">
            Došlo je do pogreške. Pokušajte ponovno.
        </div>

    <?php endif; ?>


    <!-- FILTER -->

    <form
        method="GET"
        class="activities-filter"
    >

        <div class="filter-group">

            <label for="status">
                Status izvedbe
            </label>


            <select
                id="status"
                name="status"
            >

                <option
                    value="active"
                    <?= $statusFilter === 'active'
                        ? 'selected'
                        : '' ?>
                >
                    Aktivne
                </option>


                <option
                    value="inactive"
                    <?= $statusFilter === 'inactive'
                        ? 'selected'
                        : '' ?>
                >
                    Neaktivne
                </option>


                <option
                    value="all"
                    <?= $statusFilter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    Sve
                </option>

            </select>

        </div>


        <button
            type="submit"
            class="filter-button"
        >
            Primijeni filter
        </button>

    </form>


    <section class="card">

        <div class="activities-table-wrapper">


            <?php if (empty($activities)): ?>

                <p>
                    Nema aktivnosti koje odgovaraju odabranom filteru.
                </p>


            <?php else: ?>


                <table class="activities-table">

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
                                Bodovi
                            </th>

                            <th>
                                Profesor
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Akcije
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
                        | Status izvedbe
                        |--------------------------------------------------------------------------
                        */

                        $hasOffering =
                            $activity['offering_id'] !== null;


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


                        $isActive =
                            $isActivityActive
                            &&
                            (
                                !$hasOffering
                                ||
                                $isOfferingActive
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Je li ovo profesorova izvedba?
                        |--------------------------------------------------------------------------
                        */

                        $isOwner =
                            $hasOffering
                            &&
                            (int) $activity[
                                'offering_professor_id'
                            ] === $professorId;


                        ?>

                        <tr
                            class="<?= !$isActive
                                ? 'inactive-row'
                                : '' ?>"
                        >


                            <!-- Aktivnost -->

                            <td>

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

                                    <div class="activity-description">

                                        <?= htmlspecialchars(
                                            $activity['description']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- Tip -->

                            <td>

                                <?php if (
                                    $activity['activity_type']
                                    === 'BONUS'
                                ): ?>

                                    <span
                                        class="badge badge-bonus"
                                    >
                                        BONUS
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge badge-regular"
                                    >
                                        REGULAR
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Semestar -->

                            <td>

                                <?php if (
                                    $hasOffering
                                    &&
                                    $activity[
                                        'semester_name'
                                    ] !== null
                                ): ?>

                                    <div class="offering-context">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $activity[
                                                    'academic_year_name'
                                                ]
                                            ) ?>

                                        </strong>

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


                            <!-- Bodovi -->

                            <td>

                                <span class="points">

                                    <?= (int) $activity['points'] ?>

                                </span>

                                bod.

                            </td>


                            <!-- Profesor -->

                            <td class="owner">

                                <?php if (
                                    $activity[
                                        'offering_professor_id'
                                    ] !== null
                                ): ?>

                                    <?= htmlspecialchars(
                                        $activity[
                                            'professor_first_name'
                                        ]
                                        . ' '
                                        . $activity[
                                            'professor_last_name'
                                        ]
                                    ) ?>


                                    <?php if (
                                        $isOwner
                                    ): ?>

                                        <small>
                                            Moja izvedba
                                        </small>

                                    <?php endif; ?>


                                <?php else: ?>

                                    <span>
                                        Nije dodijeljeno
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php if (
                                    $isActive
                                ): ?>

                                    <span
                                        class="badge badge-active"
                                    >
                                        Aktivna
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge badge-inactive"
                                    >
                                        Neaktivna
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Akcije -->

                            <td>


                                <?php if (
                                    $isOwner
                                    &&
                                    $isOfferingActive
                                    &&
                                    $isActivityActive
                                ): ?>

                                    <div class="actions">


                                        <a
                                            href="edit_activity.php?offering_id=<?= (int) $activity['offering_id'] ?>"
                                            class="action-link edit-link"
                                        >
                                            Uredi
                                        </a>


                                        <a
                                            href="deactivate_activity.php?offering_id=<?= (int) $activity['offering_id'] ?>"
                                            class="action-link deactivate-link"
                                            onclick="return confirm(
                                                'Jeste li sigurni da želite deaktivirati ovu izvedbu aktivnosti?'
                                            );"
                                        >
                                            Deaktiviraj
                                        </a>


                                    </div>


                                <?php else: ?>

                                    <span>
                                        —
                                    </span>

                                <?php endif; ?>


                            </td>


                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>


            <?php endif; ?>


        </div>

    </section>


</main>

</body>

</html>