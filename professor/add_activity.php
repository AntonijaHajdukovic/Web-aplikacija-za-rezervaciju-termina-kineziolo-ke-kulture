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

$error = '';

$name = '';
$description = '';
$activityType = 'REGULAR';
$points = '';

$activityPoints = 1;

$semester = null;

/*
|--------------------------------------------------------------------------
| Obrada forme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $activityType = $_POST['activity_type'] ?? 'REGULAR';
    $points = trim($_POST['points'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validacija naziva
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error = 'Naziv aktivnosti je obavezan.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validacija tipa aktivnosti
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && !in_array(
            $activityType,
            ['REGULAR', 'BONUS'],
            true
        )
    ) {

        $error =
            'Odabrani tip aktivnosti nije ispravan.';
    }


    /*
    |--------------------------------------------------------------------------
    | REGULAR
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $activityType === 'REGULAR'
    ) {

        $activityPoints = 1;
    }


    /*
    |--------------------------------------------------------------------------
    | BONUS
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $activityType === 'BONUS'
    ) {

        if ($points === '') {

            $error =
                'Za BONUS aktivnost potrebno je unijeti broj bodova.';

        } elseif (!ctype_digit($points)) {

            $error =
                'Broj bodova mora biti cijeli pozitivan broj.';

        } elseif ((int) $points <= 0) {

            $error =
                'Broj bodova mora biti veći od 0.';

        } else {

            $activityPoints = (int) $points;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera postoji li aktivnost s istim nazivom
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $checkStmt = $pdo->prepare("
                SELECT
                    id

                FROM activities

                WHERE name = :name

                LIMIT 1
            ");

            $checkStmt->execute([
                'name' => $name
            ]);


            if ($checkStmt->fetch()) {

                $error =
                    'Aktivnost s tim nazivom već postoji.';
            }

        } catch (PDOException $e) {

            $error =
                'Došlo je do pogreške prilikom provjere aktivnosti.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Dohvat relevantnog semestra
    |--------------------------------------------------------------------------
    |
    | Za REGULAR aktivnost:
    |
    | 1. uzimamo semestar koji je trenutno u tijeku
    | 2. ako nema aktivnog semestra, uzimamo prvi nadolazeći
    |
    | Sve se odnosi na trenutno aktivnu akademsku godinu.
    |
    */


    if (
        $error === ''
        && $activityType === 'REGULAR'
    ) {

        try {

            $semesterStmt = $pdo->query("
                SELECT
                    s.id,
                    s.name,
                    s.semester_number,
                    s.start_date,
                    s.end_date,

                    ay.id AS academic_year_id,
                    ay.name AS academic_year_name

                FROM semesters s

                INNER JOIN academic_years ay
                    ON ay.id = s.academic_year_id

                WHERE ay.is_active = 1

                ORDER BY

                    CASE

                        WHEN CURDATE()
                             BETWEEN s.start_date
                             AND s.end_date

                            THEN 0

                        WHEN s.start_date > CURDATE()

                            THEN 1

                        ELSE 2

                    END,

                    CASE

                        WHEN s.start_date > CURDATE()

                            THEN s.start_date

                        ELSE NULL

                    END ASC,

                    s.start_date ASC

                LIMIT 1
            ");


            $semester =
                $semesterStmt->fetch(PDO::FETCH_ASSOC);


            if (!$semester) {

                $error =
                    'Nije pronađen aktivni ili nadolazeći semestar '
                    . 'u aktivnoj akademskoj godini.';
            }

        } catch (PDOException $e) {

            $error =
                'Došlo je do pogreške prilikom dohvaćanja semestra.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Spremanje aktivnosti + offeringa
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | 1. Kreiranje aktivnosti
            |--------------------------------------------------------------------------
            |
            | Aktivnost sadrži samo podatke koji opisuju samu aktivnost.
            |
            | Profesor nije direktno vezan uz activities.
            | Profesor i semestar definiraju se kroz
            | activity_offerings zapis koji se kreira odmah nakon aktivnosti.
            |
            */

            $stmt = $pdo->prepare("
                INSERT INTO activities (
                    name,
                    description,
                    points,
                    activity_type,
                    is_active,
                    created_at,
                    deactivated_at
                )

                VALUES (
                    :name,
                    :description,
                    :points,
                    :activity_type,
                    1,
                    CURRENT_TIMESTAMP,
                    NULL
                )
            ");


            $stmt->execute([

                'name' =>
                    $name,

                'description' =>
                    $description,

                'points' =>
                    $activityPoints,

                'activity_type' =>
                    $activityType
            ]);


            $activityId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | 2. Kreiranje activity_offering
            |--------------------------------------------------------------------------
            */

            $offeringStmt = $pdo->prepare("
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


            $offeringStmt->execute([

                'activity_id' =>
                    $activityId,

                'semester_id' =>
                    $activityType === 'REGULAR'
                        ? (int) $semester['id']
                        : null,

                'professor_id' =>
                    $professorId

            ]);


            /*
            |--------------------------------------------------------------------------
            | 3. Potvrda transakcije
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: activities.php?message=created'
            );

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            $error =
                'Došlo je do pogreške prilikom spremanja aktivnosti.';
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
        Dodaj aktivnost - Profesor
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 16px;
            box-sizing: border-box;
        }


        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }


        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #07558a;
        }


        .form-help {
            margin-top: 6px;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.45;
        }


        .points-info {
            margin-bottom: 22px;
            padding: 12px 14px;
            background-color: #f1f7fb;
            border-radius: 8px;
            color: #07558a;
            font-size: 15px;
            line-height: 1.5;
        }


        .semester-info {
            margin-bottom: 22px;
            padding: 14px 16px;
            background-color: #eef7ee;
            border-radius: 8px;
            color: #286329;
            font-size: 15px;
            line-height: 1.5;
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


        .error-message {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            background-color: #fdecec;
            color: #b91c1c;
        }


        #bonus-points {
            display: none;
        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">

    <div class="form-card">

        <h1>
            Dodaj aktivnost
        </h1>


        <p class="subtitle">
            Kreiraj novu aktivnost za studente.
        </p>


        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <section class="card">

            <form method="POST">


                <!--
                ================================================================
                Naziv
                ================================================================
                -->

                <div class="form-group">

                    <label for="name">
                        Naziv aktivnosti
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!--
                ================================================================
                Opis
                ================================================================
                -->

                <div class="form-group">

                    <label for="description">
                        Opis
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Unesite opis aktivnosti..."
                    ><?= htmlspecialchars($description) ?></textarea>

                </div>


                <!--
                ================================================================
                Tip aktivnosti
                ================================================================
                -->

                <div class="form-group">

                    <label for="activity_type">
                        Tip aktivnosti
                    </label>

                    <select
                        id="activity_type"
                        name="activity_type"
                    >

                        <option
                            value="REGULAR"
                            <?= $activityType === 'REGULAR'
                                ? 'selected'
                                : '' ?>
                        >
                            REGULAR
                        </option>


                        <option
                            value="BONUS"
                            <?= $activityType === 'BONUS'
                                ? 'selected'
                                : '' ?>
                        >
                            BONUS
                        </option>

                    </select>


                    <div class="form-help">

                        REGULAR aktivnost automatski će biti
                        ponuđena u odgovarajućem semestru.

                        BONUS aktivnost nije vezana uz konkretan
                        semestar i može biti dostupna svim studentima.

                    </div>

                </div>


                <!--
                ================================================================
                Informacija za REGULAR
                ================================================================
                -->

                <div
                    id="regular-points"
                    class="points-info"
                >

                    REGULAR aktivnosti nose

                    <strong>
                        1 bod.
                    </strong>

                    Broj bodova nije moguće mijenjati.

                </div>


                <!--
                ================================================================
                Bodovi za BONUS
                ================================================================
                -->

                <div
                    id="bonus-points"
                    class="form-group"
                >

                    <label for="points">
                        Broj bodova
                    </label>

                    <input
                        type="number"
                        id="points"
                        name="points"
                        value="<?= htmlspecialchars($points) ?>"
                        min="1"
                        step="1"
                    >


                    <div class="form-help">
                        Kod BONUS aktivnosti profesor sam određuje
                        broj bodova.
                    </div>

                </div>

                <!--
                ================================================================
                Gumbi
                ================================================================
                -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="button"
                    >
                        Spremi aktivnost
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

    </div>

</main>


<script>

const activityType =
    document.getElementById(
        'activity_type'
    );


const regularPoints =
    document.getElementById(
        'regular-points'
    );


const bonusPoints =
    document.getElementById(
        'bonus-points'
    );


const pointsInput =
    document.getElementById(
        'points'
    );


function updatePointsField()
{
    if (
        activityType.value ===
        'BONUS'
    ) {

        bonusPoints.style.display =
            'block';

        regularPoints.style.display =
            'none';

        pointsInput.required =
            true;

    } else {

        bonusPoints.style.display =
            'none';

        regularPoints.style.display =
            'block';

        pointsInput.required =
            false;

        pointsInput.value =
            '';
    }
}


activityType.addEventListener(
    'change',
    updatePointsField
);


updatePointsField();

</script>


</body>

</html>