<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Poruke
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';


/*
|--------------------------------------------------------------------------
| Dodavanje akademske godine
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_year'
) {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        header('Location: academic_years.php?message=empty_year');
        exit;
    }

    try {

        $stmt = $pdo->prepare(
            "
            INSERT INTO academic_years
                (name, is_active)
            VALUES
                (?, 1)
            "
        );

        $stmt->execute([
            $name
        ]);

        header('Location: academic_years.php?message=year_added');
        exit;

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {

            header(
                'Location: academic_years.php?message=year_exists'
            );

        } else {

            header(
                'Location: academic_years.php?message=error'
            );
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Aktiviranje / deaktiviranje akademske godine
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'toggle_year'
) {

    $yearId = filter_input(
        INPUT_POST,
        'academic_year_id',
        FILTER_VALIDATE_INT
    );

    if (!$yearId) {

        header('Location: academic_years.php?message=error');
        exit;
    }

    try {

        $stmt = $pdo->prepare(
            "
            UPDATE academic_years
            SET is_active =
                CASE
                    WHEN is_active = 1 THEN 0
                    ELSE 1
                END
            WHERE id = ?
            "
        );

        $stmt->execute([
            $yearId
        ]);

        header(
            'Location: academic_years.php?message=year_updated'
        );

        exit;

    } catch (PDOException $e) {

        header(
            'Location: academic_years.php?message=error'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dodavanje semestra
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_semester'
) {

    $academicYearId = filter_input(
        INPUT_POST,
        'academic_year_id',
        FILTER_VALIDATE_INT
    );

    $semesterNumber = filter_input(
        INPUT_POST,
        'semester_number',
        FILTER_VALIDATE_INT
    );

    $name = trim($_POST['name'] ?? '');

    if (
        !$academicYearId
        || !$semesterNumber
        || $name === ''
        || !in_array($semesterNumber, [1, 2], true)
    ) {

        header(
            'Location: academic_years.php?message=invalid_semester'
        );

        exit;
    }

    try {

        /*
         * Provjera postoji li akademska godina.
         */

        $yearStmt = $pdo->prepare(
            "
            SELECT id
            FROM academic_years
            WHERE id = ?
            "
        );

        $yearStmt->execute([
            $academicYearId
        ]);

        $yearExists = $yearStmt->fetchColumn();

        if (!$yearExists) {

            header(
                'Location: academic_years.php?message=year_not_found'
            );

            exit;
        }


        /*
         * Dodavanje semestra.
         */

        $stmt = $pdo->prepare(
            "
            INSERT INTO semesters
                (
                    academic_year_id,
                    name,
                    semester_number
                )
            VALUES
                (?, ?, ?)
            "
        );

        $stmt->execute([
            $academicYearId,
            $name,
            $semesterNumber
        ]);

        header(
            'Location: academic_years.php?message=semester_added'
        );

        exit;

    } catch (PDOException $e) {

        /*
         * 23000 = UNIQUE constraint.
         * To znači da taj semestar već postoji
         * za odabranu akademsku godinu.
         */

        if ($e->getCode() === '23000') {

            header(
                'Location: academic_years.php?message=semester_exists'
            );

        } else {

            header(
                'Location: academic_years.php?message=error'
            );
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dohvat akademskih godina i semestara
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        ay.id AS academic_year_id,
        ay.name AS academic_year_name,
        ay.is_active,

        s.id AS semester_id,
        s.name AS semester_name,
        s.semester_number

    FROM academic_years ay

    LEFT JOIN semesters s
        ON s.academic_year_id = ay.id

    ORDER BY
        ay.name DESC,
        s.semester_number ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Grupiranje semestara po akademskoj godini
|--------------------------------------------------------------------------
*/

$academicYears = [];

foreach ($rows as $row) {

    $yearId = (int) $row['academic_year_id'];

    if (!isset($academicYears[$yearId])) {

        $academicYears[$yearId] = [
            'id' => $yearId,
            'name' => $row['academic_year_name'],
            'is_active' => (int) $row['is_active'],
            'semesters' => []
        ];
    }

    if ($row['semester_id'] !== null) {

        $academicYears[$yearId]['semesters'][] = [
            'id' => (int) $row['semester_id'],
            'name' => $row['semester_name'],
            'number' => (int) $row['semester_number']
        ];
    }
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
        Akademske godine i semestri - Administracija
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
        | Zaglavlje stranice
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin-bottom: 8px;
        }

        .page-header p {
            color: #5b6470;
            font-size: 17px;
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
        | Kartice
        |--------------------------------------------------------------------------
        */

        .admin-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 28px;

            margin-bottom: 30px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .admin-card h2 {
            color: #07558a;
            font-size: 24px;
            margin-bottom: 22px;
        }


        /*
        |--------------------------------------------------------------------------
        | Forma
        |--------------------------------------------------------------------------
        */

        .form-row {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-weight: bold;
            color: #1f2937;
        }

        .form-group input,
        .form-group select {
            min-width: 230px;

            padding: 11px 13px;

            border: 1px solid #cfd6df;
            border-radius: 7px;

            font-size: 15px;
            background-color: #ffffff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #075f96;
        }

        .primary-button {
            border: none;

            padding: 12px 20px;

            border-radius: 7px;

            background-color: #075f96;
            color: #ffffff;

            font-size: 15px;
            font-weight: bold;

            cursor: pointer;
        }

        .primary-button:hover {
            background-color: #064e7c;
        }


        /*
        |--------------------------------------------------------------------------
        | Akademijska godina
        |--------------------------------------------------------------------------
        */

        .year-card {
            background-color: #ffffff;

            border-radius: 12px;

            margin-bottom: 22px;

            padding: 25px 28px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .year-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            padding-bottom: 20px;

            border-bottom: 1px solid #e5e7eb;
        }

        .year-title-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .year-title {
            color: #07558a;
            font-size: 23px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 13px;
            font-weight: bold;
        }

        .status.active {
            background-color: #e4f4e8;
            color: #187a35;
        }

        .status.inactive {
            background-color: #f1f1f1;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Gumb za aktivaciju
        |--------------------------------------------------------------------------
        */

        .toggle-button {
            border: none;

            padding: 9px 14px;

            border-radius: 7px;

            font-weight: bold;

            cursor: pointer;
        }

        .toggle-button.deactivate {
            background-color: #fdeaea;
            color: #c62828;
        }

        .toggle-button.deactivate:hover {
            background-color: #f9dada;
        }

        .toggle-button.activate {
            background-color: #e4f4e8;
            color: #187a35;
        }

        .toggle-button.activate:hover {
            background-color: #d5eedb;
        }


        /*
        |--------------------------------------------------------------------------
        | Semestri
        |--------------------------------------------------------------------------
        */

        .semester-section {
            padding-top: 22px;
        }

        .semester-section h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .semester-list {
            display: flex;
            flex-direction: column;
            gap: 10px;

            margin-bottom: 22px;
        }

        .semester-item {
            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 13px 15px;

            background-color: #f7f9fb;

            border-radius: 7px;
        }

        .semester-name {
            color: #1f2937;
            font-weight: 500;
        }

        .semester-number {
            color: #075f96;
            font-weight: bold;
        }

        .empty-semesters {
            color: #7a8491;
            font-size: 14px;

            padding: 10px 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Dodavanje semestra
        |--------------------------------------------------------------------------
        */

        .add-semester {
            padding-top: 20px;

            border-top: 1px solid #eef0f2;
        }

        .add-semester-title {
            color: #07558a;
            font-size: 16px;
            font-weight: bold;

            margin-bottom: 15px;
        }

        .small-form input,
        .small-form select {
            min-width: 180px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .year-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-row {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                min-width: 0;
            }

            .primary-button {
                width: 100%;
            }

            .semester-item {
                gap: 10px;
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

    <section class="page-header">

        <h1>
            Akademske godine i semestri
        </h1>

        <p>
            Upravljanje akademskim godinama i pripadajućim semestrima.
        </p>

    </section>


    <!-- ==========================================================
         Poruke
    =========================================================== -->

    <?php if ($message === 'year_added'): ?>

        <div class="message success">
            Akademska godina je uspješno dodana.
        </div>


    <?php elseif ($message === 'year_updated'): ?>

        <div class="message success">
            Status akademske godine je uspješno promijenjen.
        </div>


    <?php elseif ($message === 'semester_added'): ?>

        <div class="message success">
            Semestar je uspješno dodan.
        </div>


    <?php elseif ($message === 'year_exists'): ?>

        <div class="message warning">
            Akademska godina s tim nazivom već postoji.
        </div>


    <?php elseif ($message === 'semester_exists'): ?>

        <div class="message warning">
            Odabrani semestar već postoji za tu akademsku godinu.
        </div>


    <?php elseif ($message === 'empty_year'): ?>

        <div class="message warning">
            Unesite naziv akademske godine.
        </div>


    <?php elseif ($message === 'invalid_semester'): ?>

        <div class="message warning">
            Unesite ispravne podatke za semestar.
        </div>


    <?php elseif ($message === 'year_not_found'): ?>

        <div class="message warning">
            Odabrana akademska godina ne postoji.
        </div>


    <?php elseif ($message === 'error'): ?>

        <div class="message error">
            Došlo je do pogreške. Pokušajte ponovno.
        </div>

    <?php endif; ?>


    <!-- ==========================================================
         Dodavanje akademske godine
    =========================================================== -->

    <section class="admin-card">

        <h2>
            Dodaj akademsku godinu
        </h2>


        <form
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="add_year"
            >


            <div class="form-row">

                <div class="form-group">

                    <label for="year_name">
                        Naziv akademske godine
                    </label>

                    <input
                        type="text"
                        id="year_name"
                        name="name"
                        placeholder="npr. 2026./2027."
                        maxlength="20"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="primary-button"
                >
                    Dodaj akademsku godinu
                </button>

            </div>

        </form>

    </section>


    <!-- ==========================================================
         Popis akademskih godina
    =========================================================== -->

    <section>

        <div class="admin-card">

            <h2>
                Akademske godine (<?= count($academicYears) ?>)
            </h2>


            <?php if (empty($academicYears)): ?>

                <div class="empty-semesters">

                    Trenutno nema akademskih godina.

                </div>


            <?php endif; ?>

        </div>


        <?php foreach ($academicYears as $year): ?>

            <article class="year-card">


                <!-- ==================================================
                     Zaglavlje godine
                =================================================== -->

                <div class="year-header">

                    <div class="year-title-wrapper">

                        <div class="year-title">

                            <?= htmlspecialchars(
                                $year['name']
                            ) ?>

                        </div>


                        <?php if ($year['is_active'] === 1): ?>

                            <span class="status active">
                                Aktivna
                            </span>

                        <?php else: ?>

                            <span class="status inactive">
                                Neaktivna
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- ==================================================
                         Aktivacija / deaktivacija
                    =================================================== -->

                    <form
                        method="POST"
                        onsubmit="
                            return confirm(
                                'Jeste li sigurni da želite promijeniti status ove akademske godine?'
                            );
                        "
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="toggle_year"
                        >

                        <input
                            type="hidden"
                            name="academic_year_id"
                            value="<?= $year['id'] ?>"
                        >


                        <?php if ($year['is_active'] === 1): ?>

                            <button
                                type="submit"
                                class="toggle-button deactivate"
                            >
                                Deaktiviraj
                            </button>

                        <?php else: ?>

                            <button
                                type="submit"
                                class="toggle-button activate"
                            >
                                Aktiviraj
                            </button>

                        <?php endif; ?>

                    </form>

                </div>


                <!-- ==================================================
                     Semestri
                =================================================== -->

                <div class="semester-section">

                    <h3>
                        Semestri
                    </h3>


                    <?php if (empty($year['semesters'])): ?>

                        <div class="empty-semesters">

                            Za ovu akademsku godinu još nema dodanih
                            semestara.

                        </div>

                    <?php else: ?>

                        <div class="semester-list">

                            <?php foreach ($year['semesters'] as $semester): ?>

                                <div class="semester-item">

                                    <span class="semester-name">

                                        <?= htmlspecialchars(
                                            $semester['name']
                                        ) ?>

                                    </span>


                                    <span class="semester-number">

                                        <?= $semester['number'] ?>.
                                        semestar

                                    </span>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>


                    <!-- ==================================================
                         Dodavanje semestra
                    =================================================== -->

                    <div class="add-semester">

                        <div class="add-semester-title">

                            Dodaj semestar

                        </div>


                        <form
                            method="POST"
                            class="small-form"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="add_semester"
                            >

                            <input
                                type="hidden"
                                name="academic_year_id"
                                value="<?= $year['id'] ?>"
                            >


                            <div class="form-row">


                                <div class="form-group">

                                    <label>
                                        Semestar
                                    </label>

                                    <select
                                        name="semester_number"
                                        required
                                    >

                                        <option value="">
                                            Odaberi
                                        </option>

                                        <option value="1">
                                            1. semestar
                                        </option>

                                        <option value="2">
                                            2. semestar
                                        </option>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Naziv
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        placeholder="npr. Zimski semestar"
                                        maxlength="30"
                                        required
                                    >

                                </div>


                                <button
                                    type="submit"
                                    class="primary-button"
                                >
                                    Dodaj semestar
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </article>

        <?php endforeach; ?>

    </section>


</main>

</body>

</html>