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
| Dohvat ID-a izvedbe aktivnosti
|--------------------------------------------------------------------------
*/

$offeringId = filter_input(
    INPUT_GET,
    'offering_id',
    FILTER_VALIDATE_INT
);

if (!$offeringId) {
    header('Location: activities.php?message=error');
    exit;
}


/*
|--------------------------------------------------------------------------
| Početne vrijednosti
|--------------------------------------------------------------------------
*/

$error = '';

$name = '';
$description = '';
$activityType = '';
$points = '';

$activityId = 0;
$semesterName = null;
$academicYearName = null;


/*
|--------------------------------------------------------------------------
| Dohvat izvedbe aktivnosti
|--------------------------------------------------------------------------
|
| Profesor može uređivati samo vlastitu aktivnu izvedbu.
|
| Vlasništvo se određuje preko:
|
| activity_offerings.professor_id
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            o.id AS offering_id,
            o.activity_id,
            o.professor_id,
            o.is_active AS offering_is_active,

            a.name,
            a.description,
            a.points,
            a.activity_type,
            a.is_active AS activity_is_active,

            sem.name AS semester_name,
            ay.name AS academic_year_name

        FROM activity_offerings o

        INNER JOIN activities a
            ON a.id = o.activity_id

        LEFT JOIN semesters sem
            ON sem.id = o.semester_id

        LEFT JOIN academic_years ay
            ON ay.id = sem.academic_year_id

        WHERE o.id = :offering_id
          AND o.professor_id = :professor_id

        LIMIT 1
    ");

    $stmt->execute([
        'offering_id' =>
            $offeringId,

        'professor_id' =>
            $professorId
    ]);

    $offering =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$offering) {

        header(
            'Location: activities.php?message=not_allowed'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Izvedba ne smije biti neaktivna
    |--------------------------------------------------------------------------
    */

    if (
        (int) $offering['offering_is_active'] !== 1
    ) {

        header(
            'Location: activities.php?message=inactive'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Aktivnost mora biti aktivna
    |--------------------------------------------------------------------------
    */

    if (
        (int) $offering['activity_is_active'] !== 1
    ) {

        header(
            'Location: activities.php?message=inactive'
        );

        exit;
    }


    $activityId =
        (int) $offering['activity_id'];

    $name =
        $offering['name'];

    $description =
        $offering['description'];

    $activityType =
        $offering['activity_type'];

    $points =
        $offering['points'];

    $semesterName =
        $offering['semester_name'];

    $academicYearName =
        $offering['academic_year_name'];


} catch (PDOException $e) {

    $error =
        'Došlo je do pogreške prilikom '
        . 'dohvaćanja aktivnosti.';
}


/*
|--------------------------------------------------------------------------
| Obrada forme
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $error === ''
) {

    $name =
        trim(
            $_POST['name'] ?? ''
        );

    $description =
        trim(
            $_POST['description'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | Validacija naziva
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error =
            'Naziv aktivnosti je obavezan.';
    }


    /*
    |--------------------------------------------------------------------------
    | Provjera postoji li druga aktivnost s istim nazivom
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $checkStmt = $pdo->prepare("
                SELECT
                    id

                FROM activities

                WHERE name = :name
                  AND id <> :activity_id

                LIMIT 1
            ");

            $checkStmt->execute([

                'name' =>
                    $name,

                'activity_id' =>
                    $activityId

            ]);


            if ($checkStmt->fetch()) {

                $error =
                    'Aktivnost s tim nazivom već postoji.';
            }


        } catch (PDOException $e) {

            $error =
                'Došlo je do pogreške prilikom '
                . 'provjere naziva aktivnosti.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BONUS bodovi
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && $activityType === 'BONUS'
    ) {

        $pointsInput =
            trim(
                $_POST['points'] ?? ''
            );


        if ($pointsInput === '') {

            $error =
                'Broj bodova je obavezan '
                . 'za BONUS aktivnost.';

        } elseif (
            !ctype_digit($pointsInput)
        ) {

            $error =
                'Broj bodova mora biti '
                . 'cijeli pozitivan broj.';

        } elseif (
            (int) $pointsInput <= 0
        ) {

            $error =
                'Broj bodova mora biti veći od 0.';

        } else {

            $points =
                (int) $pointsInput;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Spremanje izmjena
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Dodatna sigurnosna provjera
            |--------------------------------------------------------------------------
            |
            | Ponovno provjeravamo da offering pripada profesoru
            | i da je još uvijek aktivan.
            |
            */

            $lockStmt = $pdo->prepare("
                SELECT
                    o.activity_id,
                    a.activity_type

                FROM activity_offerings o

                INNER JOIN activities a
                    ON a.id = o.activity_id

                WHERE o.id = :offering_id
                  AND o.professor_id = :professor_id
                  AND o.is_active = 1
                  AND a.is_active = 1

                FOR UPDATE
            ");

            $lockStmt->execute([
                'offering_id' =>
                    $offeringId,

                'professor_id' =>
                    $professorId
            ]);


            $lockedOffering =
                $lockStmt->fetch(PDO::FETCH_ASSOC);


            if (!$lockedOffering) {

                $pdo->rollBack();

                header(
                    'Location: activities.php?message=not_allowed'
                );

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | REGULAR
            |--------------------------------------------------------------------------
            |
            | Bodovi ostaju zaključani.
            |
            */

            if (
                $activityType === 'REGULAR'
            ) {

                $stmt = $pdo->prepare("
                    UPDATE activities

                    SET
                        name = :name,
                        description = :description

                    WHERE id = :activity_id
                      AND is_active = 1
                ");


                $stmt->execute([

                    'name' =>
                        $name,

                    'description' =>
                        $description,

                    'activity_id' =>
                        $activityId

                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | BONUS
            |--------------------------------------------------------------------------
            |
            | Profesor može promijeniti broj bodova.
            |
            */

            elseif (
                $activityType === 'BONUS'
            ) {

                $stmt = $pdo->prepare("
                    UPDATE activities

                    SET
                        name = :name,
                        description = :description,
                        points = :points

                    WHERE id = :activity_id
                      AND is_active = 1
                ");


                $stmt->execute([

                    'name' =>
                        $name,

                    'description' =>
                        $description,

                    'points' =>
                        $points,

                    'activity_id' =>
                        $activityId

                ]);
            }


            else {

                $pdo->rollBack();

                $error =
                    'Tip aktivnosti nije ispravan.';
            }


            /*
            |--------------------------------------------------------------------------
            | Završetak transakcije
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $pdo->commit();

                header(
                    'Location: activities.php?message=updated'
                );

                exit;
            }


            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


        } catch (PDOException $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                'Došlo je do pogreške prilikom '
                . 'spremanja izmjena.';
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
        Uredi aktivnost - Profesor
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


        .activity-info {
            margin-bottom: 24px;
            padding: 16px 18px;
            background-color: #f1f7fb;
            border-radius: 8px;
            color: #07558a;
            line-height: 1.5;
        }


        .activity-info strong {
            color: #172b4d;
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
        .form-group textarea {
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
        .form-group textarea:focus {
            outline: none;
            border-color: #07558a;
        }


        .readonly-field {
            background-color: #f3f4f6;
            color: #4b5563;
            cursor: not-allowed;
        }


        .info-box {
            margin-bottom: 22px;
            padding: 12px 14px;
            border-radius: 8px;
            background-color: #f1f7fb;
            color: #07558a;
            font-size: 15px;
            line-height: 1.5;
        }


        .error-message {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            background-color: #fdecec;
            color: #b91c1c;
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


    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <div class="form-card">


        <h1>
            Uredi aktivnost
        </h1>


        <p class="subtitle">
            Uredi podatke aktivnosti.
        </p>


        <?php if ($error !== ''): ?>

            <div class="error-message">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <section class="card">


            <!--
            ==================================================================
            Kontekst izvedbe
            ==================================================================
            -->

            <div class="activity-info">

                <strong>
                    Izvedba aktivnosti:
                </strong>

                <?= htmlspecialchars(
                    $name
                ) ?>


                <?php if (
                    $academicYearName
                    &&
                    $semesterName
                ): ?>

                    <br>

                    <strong>
                        Semestar:
                    </strong>

                    <?= htmlspecialchars(
                        $academicYearName
                    ) ?>

                    -

                    <?= htmlspecialchars(
                        $semesterName
                    ) ?>

                <?php else: ?>

                    <br>

                    <strong>
                        Vrsta:
                    </strong>

                    Opća / BONUS aktivnost

                <?php endif; ?>

            </div>


            <form method="POST">


                <!--
                ==================================================================
                Naziv
                ==================================================================
                -->

                <div class="form-group">

                    <label for="name">
                        Naziv aktivnosti
                    </label>


                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars(
                            $name
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!--
                ==================================================================
                Opis
                ==================================================================
                -->

                <div class="form-group">

                    <label for="description">
                        Opis
                    </label>


                    <textarea
                        id="description"
                        name="description"
                    ><?= htmlspecialchars(
                        $description
                    ) ?></textarea>

                </div>


                <!--
                ==================================================================
                Tip aktivnosti
                ==================================================================
                -->

                <div class="form-group">

                    <label>
                        Tip aktivnosti
                    </label>


                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $activityType
                        ) ?>"
                        class="readonly-field"
                        readonly
                    >

                </div>


                <?php if (
                    $activityType === 'REGULAR'
                ): ?>

                    <div class="info-box">

                        REGULAR aktivnost trenutno nosi

                        <strong>

                            <?= (int) $points ?>

                            <?= (
                                (int) $points === 1
                            )
                                ? 'bod'
                                : (
                                    (
                                        (int) $points >= 2
                                        &&
                                        (int) $points <= 4
                                    )
                                        ? 'boda'
                                        : 'bodova'
                                )
                            ?>

                        </strong>


                        <br>

                        Broj bodova nije moguće mijenjati.

                    </div>


                <?php elseif (
                    $activityType === 'BONUS'
                ): ?>

                    <div class="form-group">

                        <label for="points">
                            Broj bodova
                        </label>


                        <input
                            type="number"
                            id="points"
                            name="points"
                            value="<?= htmlspecialchars(
                                $points
                            ) ?>"
                            min="1"
                            step="1"
                            required
                        >


                        <div class="form-help">

                            Kod BONUS aktivnosti profesor može
                            promijeniti broj bodova.

                        </div>

                    </div>

                <?php endif; ?>


                <!--
                ==================================================================
                Gumbi
                ==================================================================
                -->

                <div class="form-actions">


                    <button
                        type="submit"
                        class="button"
                    >
                        Spremi izmjene
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

</body>

</html>