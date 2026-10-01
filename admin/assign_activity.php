<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$error = '';
$success = '';

$activityId = isset($_GET['activity_id'])
    ? (int) $_GET['activity_id']
    : 0;

$selectedProfessorId = 0;
$selectedSemesterId = 0;


/*
|--------------------------------------------------------------------------
| Dohvat aktivnosti
|--------------------------------------------------------------------------
*/

if ($activityId <= 0) {

    header('Location: activities.php?message=error');
    exit;
}


$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        description,
        points,
        activity_type,
        is_active

    FROM activities

    WHERE id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $activityId
]);

$activity = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$activity) {

    header('Location: activities.php?message=error');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat aktivnih profesora
|--------------------------------------------------------------------------
*/

$professorStmt = $pdo->query("
    SELECT
        id,
        first_name,
        last_name,
        email

    FROM users

    WHERE role = 'PROFESSOR'
      AND is_active = 1

    ORDER BY
        last_name ASC,
        first_name ASC
");

$professors =
    $professorStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Dohvat akademske godine i semestara
|--------------------------------------------------------------------------
|
| Prikazujemo aktivnu akademsku godinu.
| To je trenutno relevantna godina za dodjeljivanje novih izvedbi.
|
*/

$semesterStmt = $pdo->query("
    SELECT
        s.id,
        s.name,
        s.semester_number,
        s.start_date,
        s.end_date,

        ay.id AS academic_year_id,
        ay.name AS academic_year_name,
        ay.is_active

    FROM semesters s

    INNER JOIN academic_years ay
        ON ay.id = s.academic_year_id

    WHERE ay.is_active = 1

    ORDER BY
        s.start_date ASC
");

$semesters =
    $semesterStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Obrada forme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedProfessorId =
        (int) ($_POST['professor_id'] ?? 0);

    $selectedSemesterId =
        (int) ($_POST['semester_id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Provjera profesora
    |--------------------------------------------------------------------------
    */

    if ($selectedProfessorId <= 0) {

        $error =
            'Morate odabrati profesora.';
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera aktivnosti
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        (int) $activity['is_active'] !== 1
    ) {

        $error =
            'Neaktivnu aktivnost nije moguće dodijeliti profesoru.';
    }


    /*
    |--------------------------------------------------------------------------
    | Dohvat odabranog profesora
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT
                id

            FROM users

            WHERE id = :id
              AND role = 'PROFESSOR'
              AND is_active = 1

            LIMIT 1
        ");

        $stmt->execute([
            'id' => $selectedProfessorId
        ]);


        if (!$stmt->fetch()) {

            $error =
                'Odabrani profesor nije valjan.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REGULAR mora imati semestar
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $activity['activity_type'] === 'REGULAR'
        &&
        $selectedSemesterId <= 0
    ) {

        $error =
            'Za REGULAR aktivnost morate odabrati semestar.';
    }


    /*
    |--------------------------------------------------------------------------
    | BONUS ne mora imati semestar
    |--------------------------------------------------------------------------
    |
    | Za BONUS aktivnost administrator može odabrati:
    |
    | 0 = opća aktivnost
    |
    | ili konkretan semestar, ako se jednog dana želi
    | ograničiti na određeni semestar.
    |
    */

    if (
        $error === ''
        &&
        $selectedSemesterId > 0
    ) {

        $stmt = $pdo->prepare("
            SELECT
                s.id

            FROM semesters s

            INNER JOIN academic_years ay
                ON ay.id = s.academic_year_id

            WHERE s.id = :semester_id
              AND ay.is_active = 1

            LIMIT 1
        ");

        $stmt->execute([
            'semester_id' => $selectedSemesterId
        ]);


        if (!$stmt->fetch()) {

            $error =
                'Odabrani semestar nije valjan.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera postojeće izvedbe
    |--------------------------------------------------------------------------
    |
    | Ne želimo napraviti duplikat:
    |
    | Futsal + Zimski 2026./2027. + Marko
    |
    */

    if ($error === '') {

        if ($selectedSemesterId > 0) {

            $duplicateStmt = $pdo->prepare("
                SELECT
                    id,
                    is_active

                FROM activity_offerings

                WHERE activity_id = :activity_id
                  AND semester_id = :semester_id
                  AND professor_id = :professor_id

                LIMIT 1
            ");

            $duplicateStmt->execute([

                'activity_id' =>
                    $activityId,

                'semester_id' =>
                    $selectedSemesterId,

                'professor_id' =>
                    $selectedProfessorId

            ]);

        } else {

            $duplicateStmt = $pdo->prepare("
                SELECT
                    id,
                    is_active

                FROM activity_offerings

                WHERE activity_id = :activity_id
                  AND semester_id IS NULL
                  AND professor_id = :professor_id

                LIMIT 1
            ");

            $duplicateStmt->execute([

                'activity_id' =>
                    $activityId,

                'professor_id' =>
                    $selectedProfessorId

            ]);
        }


        $existingOffering =
            $duplicateStmt->fetch(PDO::FETCH_ASSOC);


        if ($existingOffering) {

            /*
            |--------------------------------------------------------------------------
            | Ako postoji ali je deaktiviran,
            | ponovno ga aktiviramo.
            |--------------------------------------------------------------------------
            */

            if (
                (int) $existingOffering['is_active'] === 0
            ) {

                $reactivateStmt = $pdo->prepare("
                    UPDATE activity_offerings

                    SET
                        is_active = 1,
                        deactivated_at = NULL

                    WHERE id = :id
                ");


                $reactivateStmt->execute([
                    'id' =>
                        (int) $existingOffering['id']
                ]);


                $success =
                    'Postojeća izvedba aktivnosti je ponovno aktivirana.';

            } else {

                $error =
                    'Ova aktivnost već je dodijeljena odabranom profesoru '
                    . 'u odabranom semestru.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Kreiranje novog offeringa
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $success === ''
    ) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                INSERT INTO activity_offerings (
                    activity_id,
                    semester_id,
                    professor_id,
                    is_active,
                    created_at,
                    deactivated_at
                )

                VALUES (
                    :activity_id,
                    :semester_id,
                    :professor_id,
                    1,
                    CURRENT_TIMESTAMP,
                    NULL
                )
            ");


            $stmt->execute([

                'activity_id' =>
                    $activityId,

                'semester_id' =>
                    $selectedSemesterId > 0
                        ? $selectedSemesterId
                        : null,

                'professor_id' =>
                    $selectedProfessorId

            ]);


            $pdo->commit();


            $success =
                'Aktivnost je uspješno dodijeljena profesoru.';


            $selectedProfessorId = 0;
            $selectedSemesterId = 0;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            $error =
                'Došlo je do pogreške prilikom dodjeljivanja aktivnosti.';
        }
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
        Dodijeli aktivnost - Administracija
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
            margin-bottom: 30px;
        }


        .page-header p {
            color: #5b6470;
            font-size: 17px;
        }


        .form-card {
            max-width: 700px;
            margin: 0 auto;

            background-color: #ffffff;

            border-radius: 12px;

            padding: 30px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
        }


        .activity-info {
            margin-bottom: 25px;

            padding: 18px;

            background-color: #f1f7fb;

            border-radius: 9px;

            color: #07558a;

            line-height: 1.5;
        }


        .activity-info h2 {
            margin: 0 0 7px;

            color: #07558a;

            font-size: 23px;
        }


        .activity-info p {
            margin: 0;

            color: #5b6470;
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


        .form-group select {
            width: 100%;

            box-sizing: border-box;

            padding: 12px 14px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            background-color: #ffffff;

            font-family: inherit;

            font-size: 16px;
        }


        .form-group select:focus {
            outline: none;

            border-color: #07558a;
        }


        .form-help {
            margin-top: 6px;

            color: #6b7280;

            font-size: 14px;

            line-height: 1.45;
        }


        .message {
            margin-bottom: 20px;

            padding: 14px 18px;

            border-radius: 8px;

            font-weight: bold;
        }


        .message.success {
            background-color: #e8f5e9;

            color: #2e7d32;
        }


        .message.error {
            background-color: #fdecec;

            color: #b91c1c;
        }


        .button-row {
            display: flex;

            gap: 12px;

            margin-top: 28px;
        }


        .cancel-link {
            display: inline-block;

            padding: 12px 20px;

            border-radius: 8px;

            background-color: #e5e7eb;

            color: #374151;

            text-decoration: none;

            font-weight: bold;
        }


        .cancel-link:hover {
            background-color: #d1d5db;
        }


        @media (max-width: 600px) {

            .form-card {
                padding: 22px;
            }


            .button-row {
                flex-direction: column;
            }


            .button-row .button,
            .cancel-link {
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
            Dodijeli aktivnost profesoru
        </h1>


        <p>
            Dodijelite konkretnu izvedbu aktivnosti profesoru.
        </p>

    </section>


    <?php if ($success !== ''): ?>

        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <section class="form-card">


        <div class="activity-info">

            <h2>

                <?= htmlspecialchars(
                    $activity['name']
                ) ?>

            </h2>


            <p>

                <?php if (
                    !empty(
                        $activity['description']
                    )
                ): ?>

                    <?= htmlspecialchars(
                        $activity['description']
                    ) ?>

                    <br>

                <?php endif; ?>


                <strong>

                    <?= (int) $activity['points'] ?>

                    bod.

                </strong>

                ·

                <?= $activity['activity_type'] === 'BONUS'
                    ? 'BONUS aktivnost'
                    : 'REGULAR aktivnost'
                ?>

            </p>

        </div>


        <form
            method="POST"
        >


            <!-- Profesor -->

            <div class="form-group">

                <label for="professor_id">
                    Profesor
                </label>


                <select
                    id="professor_id"
                    name="professor_id"
                    required
                >

                    <option value="">
                        Odaberite profesora
                    </option>


                    <?php foreach (
                        $professors
                        as $professor
                    ): ?>

                        <option
                            value="<?= (int) $professor['id'] ?>"
                            <?= $selectedProfessorId ===
                                (int) $professor['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $professor['last_name']
                                . ' '
                                . $professor['first_name']
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                $professor['email']
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <!-- Semestar -->

            <div class="form-group">

                <label for="semester_id">
                    Semestar
                </label>


                <select
                    id="semester_id"
                    name="semester_id"
                    <?= $activity['activity_type'] === 'REGULAR'
                        ? 'required'
                        : '' ?>
                >

                    <?php if (
                        $activity['activity_type'] === 'BONUS'
                    ): ?>

                        <option
                            value="0"
                            <?= $selectedSemesterId === 0
                                ? 'selected'
                                : '' ?>
                        >
                            Opća aktivnost - svi studenti
                        </option>

                    <?php else: ?>

                        <option value="">
                            Odaberite semestar
                        </option>

                    <?php endif; ?>


                    <?php foreach (
                        $semesters
                        as $semester
                    ): ?>

                        <option
                            value="<?= (int) $semester['id'] ?>"
                            <?= $selectedSemesterId ===
                                (int) $semester['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $semester['academic_year_name']
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                $semester['name']
                            ) ?>

                            (
                            <?= date(
                                'd.m.Y.',
                                strtotime(
                                    $semester['start_date']
                                )
                            ) ?>

                            -

                            <?= date(
                                'd.m.Y.',
                                strtotime(
                                    $semester['end_date']
                                )
                            ) ?>

                            )

                        </option>

                    <?php endforeach; ?>


                </select>


                <div class="form-help">

                    <?php if (
                        $activity['activity_type'] === 'REGULAR'
                    ): ?>

                        REGULAR aktivnost mora pripadati
                        konkretnom semestru.

                    <?php else: ?>

                        BONUS aktivnost može biti opća i tada
                        nije vezana uz jedan konkretan semestar.

                    <?php endif; ?>

                </div>

            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="button"
                >
                    Dodijeli aktivnost
                </button>


                <a
                    href="activities.php"
                    class="cancel-link"
                >
                    Odustani
                </a>

            </div>


        </form>


    </section>


</main>

</body>

</html>