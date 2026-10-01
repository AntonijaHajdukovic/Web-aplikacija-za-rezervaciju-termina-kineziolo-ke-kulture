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
| Dodavanje kolegija
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_course'
) {

    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');

    $semesterNumber = filter_input(
        INPUT_POST,
        'semester_number',
        FILTER_VALIDATE_INT
    );

    $requiredPoints = filter_input(
        INPUT_POST,
        'required_points',
        FILTER_VALIDATE_INT
    );

    $ectsRaw = trim($_POST['ects'] ?? '');
    $ectsRaw = str_replace(',', '.', $ectsRaw);

    $ects = filter_var(
        $ectsRaw,
        FILTER_VALIDATE_FLOAT
    );


    if (
        $code === ''
        || $name === ''
        || !in_array($semesterNumber, [1, 2, 3, 4], true)
        || $requiredPoints === false
        || $requiredPoints < 0
        || $ects === false
        || $ects < 0
    ) {

        header('Location: courses.php?message=empty_course');
        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | Dodavanje kolegija
        |--------------------------------------------------------------------------
        |
        | Kolegij nije vezan uz konkretnu akademsku godinu.
        | semester_number određuje pripada li zimskom ili ljetnom semestru.
        |
        */

        $stmt = $pdo->prepare(
            "
            INSERT INTO courses
                (
                    code,
                    name,
                    semester_number,
                    required_points,
                    ects,
                    is_active
                )
            VALUES
                (?, ?, ?, ?, ?, 1)
            "
        );

        $stmt->execute([
            $code,
            $name,
            $semesterNumber,
            $requiredPoints,
            $ects
        ]);


        header('Location: courses.php?message=course_added');
        exit;


    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | code je UNIQUE
        |--------------------------------------------------------------------------
        */

        if ($e->getCode() === '23000') {

            header(
                'Location: courses.php?message=course_exists'
            );

        } else {

            header(
                'Location: courses.php?message=error'
            );
        }

        exit;
    }
}



/*
|--------------------------------------------------------------------------
| Aktiviranje / deaktiviranje kolegija
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'toggle_course'
) {

    $courseId = filter_input(
        INPUT_POST,
        'course_id',
        FILTER_VALIDATE_INT
    );


    if (!$courseId) {

        header('Location: courses.php?message=error');
        exit;
    }


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE courses

            SET is_active =
                CASE
                    WHEN is_active = 1 THEN 0
                    ELSE 1
                END

            WHERE id = ?
            "
        );

        $stmt->execute([
            $courseId
        ]);


        header(
            'Location: courses.php?message=course_updated'
        );

        exit;


    } catch (PDOException $e) {

        header(
            'Location: courses.php?message=error'
        );

        exit;
    }
}



/*
|--------------------------------------------------------------------------
| Filteri
|--------------------------------------------------------------------------
*/

$status = $_GET['status'] ?? '';

$semesterNumber = filter_input(
    INPUT_GET,
    'semester_number',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Dohvat kolegija
|--------------------------------------------------------------------------
|
| Kolegij je trajni katalog.
|
| Ne vežemo ga uz academic_years ili semesters.id.
| Kolegij samo zna kojem semestru pripada:
|
| 1 = zimski
| 2 = ljetni
| 3 = zimski
| 4 = ljetni
|
*/

$sql = "
    SELECT
        c.id,
        c.code,
        c.name,
        c.required_points,
        c.ects,
        c.is_active,
        c.semester_number

    FROM courses c

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Filter status
|--------------------------------------------------------------------------
*/

if ($status === 'active') {

    $sql .= " AND c.is_active = 1";

} elseif ($status === 'inactive') {

    $sql .= " AND c.is_active = 0";
}


/*
|--------------------------------------------------------------------------
| Filter semestar
|--------------------------------------------------------------------------
*/

if (
    $semesterNumber !== false
    && in_array($semesterNumber, [1, 2, 3, 4], true)
) {

    $sql .= " AND c.semester_number = ?";

    $params[] = $semesterNumber;
}


/*
|--------------------------------------------------------------------------
| Sortiranje
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        c.semester_number ASC,
        c.name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);



/*
|--------------------------------------------------------------------------
| Poruke
|--------------------------------------------------------------------------
*/

$messageText = '';
$messageClass = '';

switch ($message) {

    case 'course_added':

        $messageText =
            'Kolegij je uspješno dodan.';

        $messageClass =
            'success';

        break;


    case 'course_updated':

        $messageText =
            'Status kolegija je uspješno promijenjen.';

        $messageClass =
            'success';

        break;


    case 'course_exists':

        $messageText =
            'Kolegij s tom šifrom već postoji.';

        $messageClass =
            'error';

        break;


    case 'empty_course':

        $messageText =
            'Molimo ispunite sva polja ispravno.';

        $messageClass =
            'error';

        break;


    case 'error':

        $messageText =
            'Došlo je do pogreške. Pokušajte ponovno.';

        $messageClass =
            'error';

        break;
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
        Kolegiji - Administracija
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
        | Kartice
        |--------------------------------------------------------------------------
        */

        .card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.08);
        }


        .card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #005b96;
            font-size: 26px;
        }


        /*
        |--------------------------------------------------------------------------
        | Forma
        |--------------------------------------------------------------------------
        */

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        label {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 16px;
        }


        input,
        select {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            font-size: 16px;
            color: #102a43;
            box-sizing: border-box;
        }


        input:focus,
        select:focus {
            outline: none;
            border-color: #005b96;
        }


        /*
        |--------------------------------------------------------------------------
        | Gumbi
        |--------------------------------------------------------------------------
        */

        button {
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            padding: 13px 22px;
        }


        .btn-primary {
            background: #005b96;
            color: white;
        }


        .btn-primary:hover {
            background: #004a7c;
        }


        .btn-danger {
            background: #fde7e9;
            color: #d62828;
        }


        .btn-danger:hover {
            background: #fbd0d4;
        }


        .btn-success {
            background: #e2f4e8;
            color: #18794e;
        }


        .btn-success:hover {
            background: #cdebd8;
        }


        /*
        |--------------------------------------------------------------------------
        | Filter
        |--------------------------------------------------------------------------
        */

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }


        /*
        |--------------------------------------------------------------------------
        | Poruke
        |--------------------------------------------------------------------------
        */

        .message {
            padding: 15px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: bold;
        }


        .message.success {
            background: #e2f4e8;
            color: #18794e;
        }


        .message.error {
            background: #fde7e9;
            color: #b42318;
        }


        /*
        |--------------------------------------------------------------------------
        | Tablica
        |--------------------------------------------------------------------------
        */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        th {
            text-align: left;
            padding: 17px 14px;
            background: #f4f7fa;
            color: #005b96;
            font-size: 16px;
        }


        td {
            padding: 18px 14px;
            border-top: 1px solid #e2e8f0;
            vertical-align: middle;
        }


        tr:hover td {
            background: #fafcff;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }


        .badge-active {
            background: #e2f4e8;
            color: #18794e;
        }


        .badge-inactive {
            background: #fde7e9;
            color: #b42318;
        }


        .badge-semester {
            background: #e7f2fa;
            color: #005b96;
        }


        /*
        |--------------------------------------------------------------------------
        | Naziv kolegija
        |--------------------------------------------------------------------------
        */

        .course-name {
            font-weight: bold;
            color: #102a43;
        }


        .course-code {
            color: #005b96;
            font-weight: bold;
        }


        .academic-year {
            color: #486581;
            font-size: 14px;
            margin-top: 4px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .form-group.full {
                grid-column: auto;
            }

        }


        @media (max-width: 600px) {

            header {
                height: auto;
                padding: 20px;
                gap: 20px;
            }


            .logo {
                width: 180px;
            }


            .header-right {
                gap: 15px;
                font-size: 15px;
            }


            .container {
                margin: 35px auto;
            }


            h1 {
                font-size: 28px;
            }


            .filter-grid {
                grid-template-columns: 1fr;
            }


            .card {
                padding: 20px;
            }

        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">

    <h1>
        Kolegiji
    </h1>


    <p class="subtitle">
        Pregled i upravljanje kolegijima Kineziološke kulture.
    </p>



    <?php if ($messageText !== ''): ?>

        <div class="message <?= $messageClass ?>">

            <?= htmlspecialchars(
                $messageText
            ) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | Dodavanje kolegija
    |--------------------------------------------------------------------------
    -->

    <section class="card">

        <h2>
            Dodaj kolegij
        </h2>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="add_course"
            >


            <div class="form-grid">


                <!-- Šifra -->

                <div class="form-group">

                    <label for="code">
                        Šifra kolegija
                    </label>


                    <input
                        type="text"
                        id="code"
                        name="code"
                        maxlength="100"
                        placeholder="npr. KIN101"
                        required
                    >

                </div>



                <!-- Naziv -->

                <div class="form-group">

                    <label for="name">
                        Naziv kolegija
                    </label>


                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        placeholder="npr. Kineziološka kultura I"
                        required
                    >

                </div>



                <!-- Semestar -->

                <div class="form-group">

                    <label for="semester_number">
                        Semestar
                    </label>


                    <select
                        id="semester_number"
                        name="semester_number"
                        required
                    >

                        <option value="">
                            Odaberi semestar
                        </option>


                        <option value="1">Zimski semestar - 1. semestar</option>
                        <option value="2">Ljetni semestar - 2. semestar</option>
                        <option value="3">Zimski semestar - 3. semestar</option>
                        <option value="4">Ljetni semestar - 4. semestar</option>


                    </select>

                </div>



                <!-- Obavezni bodovi -->

                <div class="form-group">

                    <label for="required_points">
                        Obavezni bodovi
                    </label>


                    <input
                        type="number"
                        id="required_points"
                        name="required_points"
                        min="0"
                        value="18"
                        required
                    >

                </div>



                <!-- ECTS -->

                <div class="form-group">

                    <label for="ects">
                        ECTS bodovi
                    </label>


                    <input
                        type="number"
                        id="ects"
                        name="ects"
                        min="0"
                        step="0.1"
                        value="1.0"
                        required
                    >

                </div>



                <!-- Gumb -->

                <div class="form-group">

                    <label>
                        &nbsp;
                    </label>


                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Dodaj kolegij
                    </button>

                </div>


            </div>

        </form>

    </section>



    <!--
    |--------------------------------------------------------------------------
    | Filteri
    |--------------------------------------------------------------------------
    -->

    <section class="card">

        <h2>
            Filter kolegija
        </h2>


        <form method="GET">

            <div class="filter-grid">


                <!-- Status -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>


                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value=""
                            <?= $status === ''
                                ? 'selected'
                                : '' ?>
                        >
                            Svi
                        </option>


                        <option
                            value="active"
                            <?= $status === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            Aktivni
                        </option>


                        <option
                            value="inactive"
                            <?= $status === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Neaktivni
                        </option>

                    </select>

                </div>



                <!-- Semestar -->

                <div class="form-group">

                    <label for="semester_filter">
                        Semestar
                    </label>


                    <select
                        id="semester_filter"
                        name="semester_number"
                    >

                        <option value="">
                            Svi
                        </option>


                        <option
                            value="1"
                            <?= $semesterNumber === 1
                                ? 'selected'
                                : '' ?>
                        >
                            1. semestar - Zimski
                        </option>


                        <option
                            value="2"
                            <?= $semesterNumber === 2
                                ? 'selected'
                                : '' ?>
                        >
                            2. semestar - Ljetni
                        </option>

                        <option
                            value="3"
                            <?= $semesterNumber === 3
                                ? 'selected'
                                : '' ?>
                        >
                            3. semestar - Zimski
                        </option>

                        <option
                            value="4"
                            <?= $semesterNumber === 4
                                ? 'selected'
                                : '' ?>
                        >
                            4. semestar - Ljetni
                        </option>

                    </select>

                </div>



                <!-- Gumb -->

                <div>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Primijeni filter
                    </button>

                </div>


            </div>

        </form>

    </section>



    <!--
    |--------------------------------------------------------------------------
    | Popis kolegija
    |--------------------------------------------------------------------------
    -->

    <section class="card">

        <h2>

            Kolegiji
            (<?= count($courses) ?>)

        </h2>


        <div class="table-wrapper">


            <table>

                <thead>

                    <tr>

                        <th>
                            Šifra
                        </th>


                        <th>
                            Kolegij
                        </th>


                        <th>
                            Semestar
                        </th>


                        <th>
                            Bodovi
                        </th>


                        <th>
                            ECTS
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


                    <?php if (
                        empty($courses)
                    ): ?>

                        <tr>

                            <td
                                colspan="7"
                                style="
                                    text-align: center;
                                    padding: 35px;
                                "
                            >

                                Nema kolegija koji odgovaraju
                                odabranim filterima.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $courses
                            as $course
                        ): ?>

                            <tr>


                                <!-- Šifra -->

                                <td>

                                    <span class="course-code">

                                        <?= htmlspecialchars(
                                            $course['code']
                                        ) ?>

                                    </span>

                                </td>



                                <!-- Kolegij -->

                                <td>

                                    <div class="course-name">

                                        <?= htmlspecialchars(
                                            $course['name']
                                        ) ?>

                                    </div>

                                </td>



                                <!-- Semestar -->

                                <td>

                                    <span class="badge badge-semester">

                                        <?php if (
                                            in_array(
                                                (int) $course['semester_number'],
                                                [1, 3],
                                                true
                                            )
                                        ): ?>

                                            Zimski semestar

                                        <?php else: ?>

                                            Ljetni semestar

                                        <?php endif; ?>

                                    </span>

                                </td>



                                <!-- Bodovi -->

                                <td>

                                    <?= (int) $course[
                                        'required_points'
                                    ] ?>

                                </td>



                                <!-- ECTS -->

                                <td>

                                    <?= htmlspecialchars(
                                        $course['ects']
                                    ) ?>

                                </td>



                                <!-- Status -->

                                <td>

                                    <?php if (
                                        (int) $course[
                                            'is_active'
                                        ] === 1
                                    ): ?>

                                        <span
                                            class="
                                                badge
                                                badge-active
                                            "
                                        >

                                            Aktivan

                                        </span>


                                    <?php else: ?>

                                        <span
                                            class="
                                                badge
                                                badge-inactive
                                            "
                                        >

                                            Neaktivan

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- Radnja -->

                                <td>

                                    <form
                                        method="POST"
                                        style="margin: 0;"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle_course"
                                        >


                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?= (int) $course['id'] ?>"
                                        >


                                        <?php if (
                                            (int) $course[
                                                'is_active'
                                            ] === 1
                                        ): ?>

                                            <button
                                                type="submit"
                                                class="btn-danger"
                                            >
                                                Deaktiviraj
                                            </button>


                                        <?php else: ?>

                                            <button
                                                type="submit"
                                                class="btn-success"
                                            >
                                                Aktiviraj
                                            </button>

                                        <?php endif; ?>


                                    </form>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>


        </div>

    </section>


</main>

</body>

</html>