<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$message = '';
$error = '';

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$role = $_POST['role'] ?? 'STUDENT';
$gender = $_POST['gender'] ?? '';
$password = $_POST['password'] ?? '';
$courseId = (int) ($_POST['course_id'] ?? 0);
$academicYearId = (int) ($_POST['academic_year_id'] ?? 0);

$allowedRoles = [
    'STUDENT',
    'PROFESSOR',
    'ADMIN'
];

$allowedGenders = [
    'MALE',
    'FEMALE'
];

/*
|--------------------------------------------------------------------------
| Dohvat podataka za studentski dio forme
|--------------------------------------------------------------------------
*/

$coursesStmt = $pdo->query(
    "SELECT
        id,
        code,
        name
     FROM courses
     WHERE is_active = 1
     ORDER BY name ASC"
);

$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$academicYearsStmt = $pdo->query(
    "SELECT
        id,
        name,
        is_active
     FROM academic_years
     ORDER BY is_active DESC, id DESC"
);

$academicYears = $academicYearsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Obrada forme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($role, $allowedRoles, true)) {
        $error = 'Odabrana uloga nije ispravna.';
    } elseif ($firstName === '' || $lastName === '') {
        $error = 'Ime i prezime su obavezni.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Unesite ispravnu adresu e-pošte.';
    } elseif (
        in_array($role, ['STUDENT', 'PROFESSOR'], true)
        && $aaiId === ''
    ) {
        $error = 'Za studenta ili profesora morate unijeti AAI identifikator.';
    } elseif (
        $role === 'ADMIN'
        && strlen($password) < 8
    ) {
        $error = 'Lozinka administratora mora sadržavati najmanje 8 znakova.';
    } elseif (
        $role === 'STUDENT'
        && !in_array($gender, $allowedGenders, true)
    ) {
        $error = 'Za studenta morate odabrati spol.';
    } elseif (
        $role === 'STUDENT'
        && $courseId <= 0
    ) {
        $error = 'Za studenta morate odabrati kolegij.';
    } elseif (
        $role === 'STUDENT'
        && $academicYearId <= 0
    ) {
        $error = 'Za studenta morate odabrati akademsku godinu.';
    }

    if ($error === '') {

        try {

            $pdo->beginTransaction();

            /*
            | Provjera postoji li već korisnik s istim e-mailom.
            */
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                'email' => $email
            ]);

            if ($stmt->fetch()) {
                throw new RuntimeException(
                    'Korisnik s tom e-mail adresom već postoji.'
                );
            }

            /*
            | Dodatna provjera studentskih podataka.
            */
            if ($role === 'STUDENT') {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM courses
                     WHERE id = :id
                       AND is_active = 1
                     LIMIT 1"
                );

                $stmt->execute([
                    'id' => $courseId
                ]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException(
                        'Odabrani kolegij ne postoji ili nije aktivan.'
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM academic_years
                     WHERE id = :id
                     LIMIT 1"
                );

                $stmt->execute([
                    'id' => $academicYearId
                ]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException(
                        'Odabrana akademska godina ne postoji.'
                    );
                }
            }

            /*
            | Kreiranje LOCAL korisnika.
            */
            $stmt = $pdo->prepare(
                "INSERT INTO users (
                    email,
                    password_hash,
                    auth_provider,
                    aai_id,
                    first_name,
                    last_name,
                    role,
                    gender,
                    is_active
                )
                VALUES (
                    :email,
                    :password_hash,
                    'LOCAL',
                    NULL,
                    :first_name,
                    :last_name,
                    :role,
                    :gender,
                    1
                )"
            );

            $stmt->execute([
                'email' => $email,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $role,
                'gender' => $role === 'STUDENT'
                    ? $gender
                    : null
            ]);

            $userId = (int) $pdo->lastInsertId();

            /*
            | Ako je korisnik student, odmah stvaramo upis.
            */
            if ($role === 'STUDENT') {

                $stmt = $pdo->prepare(
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

                $stmt->execute([
                    'student_id' => $userId,
                    'course_id' => $courseId,
                    'academic_year_id' => $academicYearId
                ]);
            }

            $pdo->commit();

            header('Location: users.php?message=user_added');
            exit;

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = 'Korisnik s tim podacima već postoji.';
            } else {
                $error = 'Došlo je do pogreške pri dodavanju korisnika.';
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

    <title>Dodaj korisnika - Administracija</title>

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
            margin: 0;
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

        .form-card {
            max-width: 850px;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: bold;
            color: #172b4d;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background-color: #ffffff;
            font-size: 15px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #07558a;
        }

        .student-fields {
            display: none;
            margin-top: 5px;
            padding: 20px;
            background-color: #f5f9fc;
            border-radius: 10px;
        }

        .student-fields.visible {
            display: block;
        }

        .student-fields h2 {
            margin: 0 0 18px;
            color: #07558a;
            font-size: 20px;
        }

        .info-box {
            margin-bottom: 22px;
            padding: 14px 16px;
            border-radius: 8px;
            background-color: #f1f7fb;
            color: #475569;
            line-height: 1.5;
        }

        .error-message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            background-color: #fdecec;
            color: #b91c1c;
            font-weight: bold;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .submit-button,
        .cancel-link {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .submit-button {
            border: none;
            background-color: #07558a;
            color: #ffffff;
        }

        .submit-button:hover {
            background-color: #064a78;
        }

        .cancel-link {
            color: #374151;
            background-color: #e5e7eb;
        }

        .cancel-link:hover {
            background-color: #d1d5db;
        }

        @media (max-width: 700px) {

            .form-card {
                padding: 22px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column;
            }

            .submit-button,
            .cancel-link {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
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
            Dodaj korisnika
        </h1>

        <p>
            Kreiraj korisnički račun i odredi način prijave.
        </p>

    </section>

    <?php if ($error !== ''): ?>

        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <section class="form-card">

        <div class="info-box">
            Administratori koriste lokalni račun, a studenti i profesori prijavljuju se putem AAI sustava.
        </div>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label for="first_name">
                        Ime
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="<?= htmlspecialchars($firstName) ?>"
                        maxlength="100"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="last_name">
                        Prezime
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="<?= htmlspecialchars($lastName) ?>"
                        maxlength="100"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">
                        E-mail
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        maxlength="150"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="role">
                        Uloga
                    </label>

                    <select
                        id="role"
                        name="role"
                        required
                    >

                        <option
                            value="STUDENT"
                            <?= $role === 'STUDENT' ? 'selected' : '' ?>
                        >
                            Student
                        </option>

                        <option
                            value="PROFESSOR"
                            <?= $role === 'PROFESSOR' ? 'selected' : '' ?>
                        >
                            Profesor
                        </option>

                        <option
                            value="ADMIN"
                            <?= $role === 'ADMIN' ? 'selected' : '' ?>
                        >
                            Administrator
                        </option>

                    </select>

                </div>

                <div
                    id="aai-field"
                    class="form-group"
                >

                    <label for="aai_id">
                        AAI identifikator
                    </label>

                    <input
                        type="text"
                        id="aai_id"
                        name="aai_id"
                        value="<?= htmlspecialchars($aaiId) ?>"
                        maxlength="150"
                    >

                </div>


                <div
                    id="password-field"
                    class="form-group full-width"
                >

                    <label for="password">
                        Lozinka
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required
                    >

                    <small>
                        Lozinka mora sadržavati najmanje 8 znakova.
                    </small>

                </div>

            </div>

            <section
                id="student-fields"
                class="student-fields<?= $role === 'STUDENT' ? ' visible' : '' ?>"
            >

                <h2>
                    Podaci o studentu
                </h2>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="gender">
                            Spol
                        </label>

                        <select
                            id="gender"
                            name="gender"
                        >

                            <option value="">
                                Odaberi spol
                            </option>

                            <option
                                value="MALE"
                                <?= $gender === 'MALE' ? 'selected' : '' ?>
                            >
                                Muško
                            </option>

                            <option
                                value="FEMALE"
                                <?= $gender === 'FEMALE' ? 'selected' : '' ?>
                            >
                                Žensko
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="course_id">
                            Kolegij
                        </label>

                        <select
                            id="course_id"
                            name="course_id"
                        >

                            <option value="">
                                Odaberi kolegij
                            </option>

                            <?php foreach ($courses as $course): ?>

                                <option
                                    value="<?= (int) $course['id'] ?>"
                                    <?= $courseId === (int) $course['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $course['code']
                                        . ' - '
                                        . $course['name']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group full-width">

                        <label for="academic_year_id">
                            Akademska godina
                        </label>

                        <select
                            id="academic_year_id"
                            name="academic_year_id"
                        >

                            <option value="">
                                Odaberi akademsku godinu
                            </option>

                            <?php foreach ($academicYears as $academicYear): ?>

                                <option
                                    value="<?= (int) $academicYear['id'] ?>"
                                    <?= $academicYearId === (int) $academicYear['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $academicYear['name']
                                    ) ?>
                                    <?= (int) $academicYear['is_active'] === 1
                                        ? ' (aktivna)'
                                        : '' ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

            </section>

            <div class="form-actions">

                <button
                    type="submit"
                    class="submit-button"
                >
                    Spremi korisnika
                </button>

                <a
                    href="users.php"
                    class="cancel-link"
                >
                    Odustani
                </a>

            </div>

        </form>

    </section>

</main>

<script>

    const roleSelect = document.getElementById('role');
    const studentFields = document.getElementById('student-fields');
    const aaiField = document.getElementById('aai-field');
    const aaiIdInput = document.getElementById('aai_id');
    const passwordField = document.getElementById('password-field');
    const passwordInput = document.getElementById('password');
    const genderSelect = document.getElementById('gender');
    const courseSelect = document.getElementById('course_id');
    const academicYearSelect = document.getElementById('academic_year_id');

    function updateFields() {

        const role = roleSelect.value;
        const isStudent = role === 'STUDENT';
        const isAaiUser =
            role === 'STUDENT' || role === 'PROFESSOR';
        const isAdmin = role === 'ADMIN';

        studentFields.classList.toggle(
            'visible',
            isStudent
        );

        aaiField.style.display =
            isAaiUser ? 'flex' : 'none';

        passwordField.style.display =
            isAdmin ? 'flex' : 'none';

        aaiIdInput.required = isAaiUser;
        passwordInput.required = isAdmin;

        genderSelect.required = isStudent;
        courseSelect.required = isStudent;
        academicYearSelect.required = isStudent;
    }

    roleSelect.addEventListener('change', updateFields);

    updateFields();

</script>

</body>

</html>