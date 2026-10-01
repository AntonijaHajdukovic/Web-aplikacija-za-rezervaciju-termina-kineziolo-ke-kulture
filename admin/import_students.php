<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$message = '';
$error = '';
$importedCount = 0;
$updatedCount = 0;
$enrollmentCount = 0;
$errorRows = [];


/*
|--------------------------------------------------------------------------
| Dohvat akademskih godina
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        id,
        name,
        is_active
     FROM academic_years
     ORDER BY id DESC"
);

$academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Dohvat aktivnih kolegija
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        id,
        name
     FROM courses
     WHERE is_active = 1
     ORDER BY name ASC"
);

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Obrada CSV uvoza
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $academicYearId = (int) ($_POST['academic_year_id'] ?? 0);


    /*
     * Provjera akademske godine
     */

    $selectedAcademicYear = null;

    foreach ($academicYears as $academicYear) {

        if (
            (int) $academicYear['id']
            === $academicYearId
        ) {

            $selectedAcademicYear = $academicYear;
            break;
        }
    }


    if (!$selectedAcademicYear) {

        $error = 'Odabrana akademska godina nije ispravna.';

    } elseif (
        !isset($_FILES['students_file'])
        || $_FILES['students_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $error = 'Molimo odaberite ispravnu CSV datoteku.';

    } elseif (
        strtolower(
            pathinfo(
                $_FILES['students_file']['name'],
                PATHINFO_EXTENSION
            )
        ) !== 'csv'
    ) {

        $error = 'Dozvoljene su samo CSV datoteke.';

    } else {

        $filePath = $_FILES['students_file']['tmp_name'];

        $handle = fopen($filePath, 'r');


        if ($handle === false) {

            $error = 'CSV datoteku nije moguće otvoriti.';

        } else {

            /*
             * Prvo čitanje zaglavlja.
             */

            $header = fgetcsv($handle);

            if ($header === false) {

                $error = 'CSV datoteka je prazna.';

            } else {

                /*
                 * Detekcija separatora.
                 */

                $headerLine = implode(',', $header);

                /*
                 * Ako je CSV zapravo učitan kao jedna kolona,
                 * pokušavamo ponovno sa semicolon separatorom.
                 */

                if (count($header) === 1) {

                    rewind($handle);

                    $header = fgetcsv(
                        $handle,
                        0,
                        ';'
                    );

                    $delimiter = ';';

                } else {

                    $delimiter = ',';
                }


                /*
                 * Uklanjanje BOM-a iz prvog zaglavlja.
                 */

                if (isset($header[0])) {

                    $header[0] = preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        $header[0]
                    );
                }


                /*
                 * Normalizacija zaglavlja.
                 */

                $header = array_map(
                    static function ($value) {

                        return strtolower(
                            trim($value)
                        );

                    },
                    $header
                );


                $requiredColumns = [
                    'aai_id',
                    'email',
                    'first_name',
                    'last_name',
                    'course_id',
                    'gender'
                ];


                $missingColumns = [];

                foreach (
                    $requiredColumns
                    as $requiredColumn
                ) {

                    if (
                        !in_array(
                            $requiredColumn,
                            $header,
                            true
                        )
                    ) {

                        $missingColumns[] =
                            $requiredColumn;
                    }
                }


                if (!empty($missingColumns)) {

                    $error =
                        'CSV nema potrebna zaglavlja: '
                        . implode(
                            ', ',
                            $missingColumns
                        );

                } else {

                    /*
                     * Indeksi stupaca.
                     */

                    $columnIndexes = [];

                    foreach (
                        $requiredColumns
                        as $column
                    ) {

                        $columnIndexes[$column] =
                            array_search(
                                $column,
                                $header,
                                true
                            );
                    }


                    /*
                     * Transakcija.
                     */

                    try {

                        $pdo->beginTransaction();


                        /*
                         * Pripremljeni upiti.
                         */

                        $findUserByAai = $pdo->prepare(
                            "SELECT
                                id,
                                email,
                                role
                             FROM users
                             WHERE aai_id = :aai_id
                             LIMIT 1"
                        );


                        $findUserByEmail = $pdo->prepare(
                            "SELECT
                                id,
                                aai_id,
                                role
                             FROM users
                             WHERE email = :email
                             LIMIT 1"
                        );


                        $findCourse = $pdo->prepare(
                            "SELECT
                                id,
                                name
                             FROM courses
                             WHERE id = :id
                               AND is_active = 1
                             LIMIT 1"
                        );


                        $insertUser = $pdo->prepare(
                            "INSERT INTO users (
                                email,
                                password_hash,
                                auth_provider,
                                aai_id,
                                first_name,
                                last_name,
                                gender,
                                role,
                                is_active
                            )
                            VALUES (
                                :email,
                                NULL,
                                'AAI',
                                :aai_id,
                                :first_name,
                                :last_name,
                                :gender,
                                'STUDENT',
                                1
                            )"
                        );


                        $updateUser = $pdo->prepare(
                            "UPDATE users
                            SET
                                email = :email,
                                auth_provider = 'AAI',
                                aai_id = :aai_id,
                                first_name = :first_name,
                                last_name = :last_name,
                                gender = :gender,
                                role = 'STUDENT',
                                is_active = 1
                            WHERE id = :id"
                        );


                        $findEnrollment = $pdo->prepare(
                            "SELECT
                                id,
                                status
                             FROM student_courses
                             WHERE student_id = :student_id
                               AND course_id = :course_id
                               AND academic_year_id = :academic_year_id
                             LIMIT 1"
                        );


                        $insertEnrollment = $pdo->prepare(
                            "INSERT INTO student_courses (
                                student_id,
                                course_id,
                                academic_year_id,
                                status
                             )
                             VALUES (
                                :student_id,
                                :course_id,
                                :academic_year_id,
                                'ENROLLED'
                             )"
                        );


                        $updateEnrollment = $pdo->prepare(
                            "UPDATE student_courses
                             SET status = 'ENROLLED'
                             WHERE id = :id"
                        );


                        /*
                         * Obrada redaka.
                         */

                        $rowNumber = 1;


                        while (
                            ($row = fgetcsv(
                                $handle,
                                0,
                                $delimiter
                            )) !== false
                        ) {

                            $rowNumber++;


                            /*
                             * Preskoči potpuno prazne retke.
                             */

                            $hasData = false;

                            foreach ($row as $value) {

                                if (
                                    trim((string) $value) !== ''
                                ) {

                                    $hasData = true;
                                    break;
                                }
                            }


                            if (!$hasData) {
                                continue;
                            }


                            /*
                             * Dohvat vrijednosti.
                             */

                            $aaiId = trim(
                                (string) (
                                    $row[
                                        $columnIndexes['aai_id']
                                    ] ?? ''
                                )
                            );


                            $email = trim(
                                (string) (
                                    $row[
                                        $columnIndexes['email']
                                    ] ?? ''
                                )
                            );


                            $firstName = trim(
                                (string) (
                                    $row[
                                        $columnIndexes['first_name']
                                    ] ?? ''
                                )
                            );


                            $lastName = trim(
                                (string) (
                                    $row[
                                        $columnIndexes['last_name']
                                    ] ?? ''
                                )
                            );


                            $courseId = (int) (
                                $row[
                                    $columnIndexes['course_id']
                                ] ?? 0
                            );

                            $gender = strtoupper(
                                trim(
                                    (string) (
                                        $row[
                                            $columnIndexes['gender']
                                        ] ?? ''
                                    )
                                )
                            );


                            /*
                             * Osnovna validacija.
                             */

                            if (
                                $aaiId === ''
                                || $email === ''
                                || $firstName === ''
                                || $lastName === ''
                                || $courseId <= 0
                                || !in_array(
                                    $gender,
                                    ['MALE', 'FEMALE'],
                                    true
                                )
                            ) {

                                $errorRows[] =
                                    "Redak {$rowNumber}: nedostaje obavezan podatak.";

                                continue;
                            }


                            if (
                                !filter_var(
                                    $email,
                                    FILTER_VALIDATE_EMAIL
                                )
                            ) {

                                $errorRows[] =
                                    "Redak {$rowNumber}: neispravan e-mail.";

                                continue;
                            }


                            /*
                             * Provjera kolegija.
                             */

                            $findCourse->execute([
                                'id' => $courseId
                            ]);

                            $course = $findCourse->fetch(
                                PDO::FETCH_ASSOC
                            );


                            if (!$course) {

                                $errorRows[] =
                                    "Redak {$rowNumber}: kolegij s ID-om {$courseId} ne postoji ili nije aktivan.";

                                continue;
                            }


                            /*
                             * Tražimo korisnika po AAI ID-u.
                             */

                            $findUserByAai->execute([
                                'aai_id' => $aaiId
                            ]);

                            $user = $findUserByAai->fetch(
                                PDO::FETCH_ASSOC
                            );


                            if ($user) {

                                /*
                                 * Ako AAI ID postoji, korisnik mora
                                 * biti STUDENT.
                                 */

                                if (
                                    $user['role'] !== 'STUDENT'
                                ) {

                                    $errorRows[] =
                                        "Redak {$rowNumber}: AAI identifikator već pripada korisniku koji nije student.";

                                    continue;
                                }


                                $updateUser->execute([
                                    'email' => $email,
                                    'aai_id' => $aaiId,
                                    'first_name' => $firstName,
                                    'last_name' => $lastName,
                                    'gender' => $gender,
                                    'id' => $user['id']
                                ]);

                                $studentId = (int) $user['id'];

                                $updatedCount++;


                            } else {

                                /*
                                 * AAI ID ne postoji.
                                 *
                                 * Prvo provjeravamo postoji li
                                 * već korisnik s istim e-mailom.
                                 */

                                $findUserByEmail->execute([
                                    'email' => $email
                                ]);

                                $emailUser =
                                    $findUserByEmail->fetch(
                                        PDO::FETCH_ASSOC
                                    );


                                if ($emailUser) {

                                    /*
                                     * Nećemo automatski pretvarati
                                     * postojeći LOCAL račun u AAI.
                                     * To bi moglo biti neispravno.
                                     */

                                    $errorRows[] =
                                        "Redak {$rowNumber}: korisnik s e-mailom {$email} već postoji, ali nema odgovarajući AAI identifikator.";

                                    continue;
                                }


                                /*
                                 * Novi AAI student.
                                 */

                                $insertUser->execute([
                                    'email' => $email,
                                    'aai_id' => $aaiId,
                                    'first_name' => $firstName,
                                    'last_name' => $lastName,
                                    'gender' => $gender
                                ]);


                                $studentId =
                                    (int) $pdo->lastInsertId();

                                $importedCount++;
                            }


                            /*
                             * Provjera upisa.
                             */

                            $findEnrollment->execute([
                                'student_id' =>
                                    $studentId,

                                'course_id' =>
                                    $courseId,

                                'academic_year_id' =>
                                    $academicYearId
                            ]);


                            $enrollment =
                                $findEnrollment->fetch(
                                    PDO::FETCH_ASSOC
                                );


                            if ($enrollment) {

                                $updateEnrollment->execute([
                                    'id' =>
                                        $enrollment['id']
                                ]);

                            } else {

                                $insertEnrollment->execute([
                                    'student_id' =>
                                        $studentId,

                                    'course_id' =>
                                        $courseId,

                                    'academic_year_id' =>
                                        $academicYearId
                                ]);

                            }

                            $enrollmentCount++;
                        }


                        fclose($handle);


                        /*
                         * Ako postoje greške u redovima,
                         * prekidamo cijeli uvoz.
                         */

                        if (!empty($errorRows)) {

                            $pdo->rollBack();

                            $importedCount = 0;
                            $updatedCount = 0;
                            $enrollmentCount = 0;

                            $error =
                                'Uvoz nije izvršen jer CSV sadrži neispravne podatke.';

                        } else {

                            $pdo->commit();

                            $message =
                                'Uvoz je uspješno završen. '
                                . 'Novi studenti: '
                                . $importedCount
                                . ', ažurirani studenti: '
                                . $updatedCount
                                . ', obrađeni upisi: '
                                . $enrollmentCount
                                . '.';
                        }


                    } catch (PDOException $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        if (is_resource($handle)) {
                            fclose($handle);
                        }

                        $importedCount = 0;
                        $updatedCount = 0;
                        $enrollmentCount = 0;

                        $error =
                            'Došlo je do pogreške prilikom uvoza podataka.';
                    }
                }
            }


            if (is_resource($handle)) {
                fclose($handle);
            }
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
        Uvoz studenata - Administracija
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

        .page-header h1 {
            margin-bottom: 10px;
        }

        .page-header p {
            color: #5b6470;
            font-size: 17px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #07558a;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .import-card {
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .import-card h2 {
            color: #07558a;
            margin-bottom: 10px;
            font-size: 22px;
        }

        .import-description {
            margin-bottom: 25px;
            color: #5b6470;
            line-height: 1.6;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 22px;
        }

        .form-group label {
            font-weight: bold;
            color: #172b4d;
        }

        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background-color: #ffffff;
            font-size: 15px;
        }

        .form-group select:focus {
            outline: none;
            border-color: #07558a;
        }

        .requirements {
            margin: 10px 0 25px;
            padding: 18px;
            background-color: #f5f9fc;
            border-radius: 8px;
            color: #475569;
            line-height: 1.6;
        }

        .requirements strong {
            color: #172b4d;
        }

        .csv-example {
            margin-top: 10px;
            padding: 15px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            overflow-x: auto;
        }

        .csv-example code {
            color: #334155;
            font-size: 14px;
            white-space: nowrap;
        }

        .message {
            max-width: 800px;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            line-height: 1.5;
        }

        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .message.error {
            background-color: #ffebee;
            color: #c62828;
        }

        .error-list {
            max-width: 800px;
            margin-bottom: 20px;
            padding: 18px 22px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            color: #9a3412;
        }

        .error-list h3 {
            margin-bottom: 10px;
        }

        .error-list ul {
            margin: 0;
            padding-left: 20px;
        }

        .submit-button {
            width: 100%;
            padding: 13px 20px;
            border: none;
            border-radius: 8px;
            background-color: #07558a;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-button:hover {
            background-color: #064a78;
        }

        @media (max-width: 700px) {

            .import-card {
                padding: 22px;
            }

        }

    </style>

</head>

<body>

<?php require_once '../includes/navbar.php'; ?>

<main class="container">


    <a
        href="users.php"
        class="back-link"
    >
        ← Povratak na korisnike
    </a>


    <section class="page-header">

        <h1>
            Uvoz studenata
        </h1>

        <p>
            Uvezite studente i njihove upise u odabranu
            akademsku godinu.
        </p>

    </section>


    <?php if ($message !== ''): ?>

        <div class="message success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($errorRows)): ?>

        <div class="error-list">

            <h3>
                Problemi u CSV datoteci
            </h3>

            <ul>

                <?php foreach ($errorRows as $errorRow): ?>

                    <li>
                        <?= htmlspecialchars($errorRow) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <section class="import-card">

        <h2>
            Podaci za uvoz
        </h2>

        <p class="import-description">

            Odaberite akademsku godinu i CSV datoteku
            s podacima o studentima.

        </p>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="form-group">

                <label for="academic_year_id">
                    Akademska godina
                </label>

                <select
                    id="academic_year_id"
                    name="academic_year_id"
                    required
                >

                    <option value="">
                        -- Odaberite akademsku godinu --
                    </option>

                    <?php foreach (
                        $academicYears
                        as $academicYear
                    ): ?>

                        <option
                            value="<?= (int) $academicYear['id'] ?>"
                            <?= (
                                (int) (
                                    $_POST['academic_year_id']
                                    ?? 0
                                )
                                === (int) $academicYear['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $academicYear['name']
                            ) ?>

                            <?php if (
                                (int) $academicYear['is_active'] === 1
                            ): ?>

                                (aktivna)

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="students_file">
                    CSV datoteka
                </label>

                <input
                    type="file"
                    id="students_file"
                    name="students_file"
                    accept=".csv,text/csv"
                    required
                >

            </div>


            <div class="requirements">

                <strong>CSV mora sadržavati sljedeća zaglavlja:</strong>

                <br>

                <code>
                    aai_id,email,first_name,last_name,course_id,gender
                </code>

                <br><br>

                Akademska godina ne nalazi se u CSV-u,
                nego se odabire iznad.

            </div>


            <div class="csv-example">

                <code>
                    aai_id,email,first_name,last_name,course_id,gender<br>

                    tvz-1001,marko.babic@tvz.hr,Marko,Babić,1,MALE<br>

                    tvz-1002,ana.horvat@tvz.hr,Ana,Horvat,1,FEMALE<br>

                    tvz-1003,ivan.novak@tvz.hr,Ivan,Novak,3,MALE
                </code>

            </div>


            <br>


            <button
                type="submit"
                class="submit-button"
            >
                Uvezi studente
            </button>


        </form>

    </section>


</main>

</body>

</html>