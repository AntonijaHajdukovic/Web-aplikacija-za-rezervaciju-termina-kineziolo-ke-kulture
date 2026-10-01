<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/aai.php';

$error = '';
$loginMethod = $_POST['login_method'] ?? 'AAI';

if (!in_array($loginMethod, ['ADMIN', 'AAI'], true)) {
    $loginMethod = 'AAI';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Molimo unesite e-mail i lozinku.';
    } elseif ($loginMethod === 'ADMIN') {

        /*
         * Administratori se prijavljuju lokalnim korisničkim računom.
         */
        $stmt = $pdo->prepare(
            "SELECT
                id,
                email,
                password_hash,
                first_name,
                last_name,
                role,
                auth_provider,
                aai_id,
                is_active
             FROM users
             WHERE email = :email
               AND auth_provider = 'LOCAL'
               AND role = 'ADMIN'
             LIMIT 1"
        );

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Neispravan e-mail ili lozinka.';
        } elseif ((int) $user['is_active'] !== 1) {
            $error = 'Vaš administratorski račun je deaktiviran.';
        } elseif (
            empty($user['password_hash'])
            || !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $error = 'Neispravan e-mail ili lozinka.';
        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = 'ADMIN';
            $_SESSION['auth_provider'] = 'LOCAL';
            $_SESSION['aai_id'] = $user['aai_id'];

            header('Location: admin/index.php');
            exit;
        }

    } else {

        /*
         * Studenti i profesori koriste AAI autentifikaciju.
         */
        $aaiUser = aaiAuthenticate(
            $pdo,
            $email,
            $password
        );

        if ($aaiUser === null) {
            $error = 'Neispravan AAI e-mail ili lozinka.';
        } else {

            /*
             * Studenti moraju imati aktivan upis.
             * Profesori nemaju student_courses upis.
             */
            if ($aaiUser['role'] === 'STUDENT') {

                $enrollmentStmt = $pdo->prepare(
                    "SELECT
                        sc.id
                     FROM student_courses sc
                     INNER JOIN academic_years ay
                         ON ay.id = sc.academic_year_id
                     WHERE sc.student_id = :student_id
                       AND sc.status = 'ENROLLED'
                       AND ay.is_active = 1
                     LIMIT 1"
                );

                $enrollmentStmt->execute([
                    'student_id' => (int) $aaiUser['id']
                ]);

                $enrollment =
                    $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$enrollment) {
                    $error =
                        'Nemate aktivan upis na kolegij Kineziološka kultura.';
                }
            }

            if ($error === '') {

                session_regenerate_id(true);

                $_SESSION['user_id'] =
                    (int) $aaiUser['id'];
                $_SESSION['email'] =
                    $aaiUser['email'];
                $_SESSION['first_name'] =
                    $aaiUser['first_name'];
                $_SESSION['last_name'] =
                    $aaiUser['last_name'];
                $_SESSION['role'] =
                    $aaiUser['role'];
                $_SESSION['auth_provider'] =
                    'AAI';
                $_SESSION['aai_id'] =
                    $aaiUser['aai_id'];

                if ($aaiUser['role'] === 'PROFESSOR') {
                    header('Location: professor/index.php');
                    exit;
                }

                header('Location: student/index.php');
                exit;
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
        Prijava - TVZ Kineziološka kultura
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            color: #1f2937;
            min-height: 100vh;
        }

        header {
            background-color: #ffffff;
            height: 120px;
            padding: 10px 60px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .logo {
            width: 300px;
            height: auto;
            display: block;
        }

        .login-container {
            min-height: calc(100vh - 120px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .login-box {
            width: 100%;
            max-width: 500px;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .login-box h1 {
            text-align: center;
            color: #07558a;
            margin-bottom: 10px;
        }

        .login-box > p {
            text-align: center;
            color: #5b6470;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .login-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 25px;
            padding: 5px;
            background-color: #eef2f6;
            border-radius: 9px;
        }

        .login-tab {
            border: none;
            padding: 12px 14px;
            border-radius: 7px;
            background-color: transparent;
            color: #475569;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .login-tab.active {
            background-color: #07558a;
            color: #ffffff;
        }

        .login-tab:not(.active):hover {
            background-color: #e2e8f0;
        }

        .login-mode-title {
            color: #172b4d;
            font-size: 21px;
            margin-bottom: 6px;
            text-align: center;
        }

        .login-mode-description {
            color: #64748b;
            font-size: 14px;
            text-align: center;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 16px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #07558a;
        }

        .login-button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background-color: #07558a;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .login-button:hover {
            opacity: 0.9;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #07558a;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.4;
        }

        @media (max-width: 600px) {

            header {
                height: 90px;
                padding: 10px 25px;
            }

            .logo {
                width: 220px;
            }

            .login-container {
                min-height: calc(100vh - 90px);
                padding: 25px 15px;
            }

            .login-box {
                padding: 30px 25px;
            }

            .login-tabs {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<header>

    <img
        src="images/tvz-logo2.png"
        alt="TVZ - Tehničko veleučilište u Zagrebu"
        class="logo"
    >

</header>

<main class="login-container">

    <div class="login-box">

        <h1>
            Prijava
        </h1>

        <p>
            Odaberite način prijave.
        </p>

        <div class="login-tabs">

            <button
                type="button"
                class="login-tab <?= $loginMethod === 'AAI' ? 'active' : '' ?>"
                data-mode="AAI"
            >
                Studenti i profesori · AAI
            </button>

            <button
                type="button"
                class="login-tab <?= $loginMethod === 'ADMIN' ? 'active' : '' ?>"
                data-mode="ADMIN"
            >
                Administrator
            </button>

        </div>

        <?php if ($error): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <div
            id="mode-title"
            class="login-mode-title"
        ></div>

        <div
            id="mode-description"
            class="login-mode-description"
        ></div>

        <form
            method="POST"
            action="login.php"
        >

            <input
                type="hidden"
                name="login_method"
                id="login_method"
                value="<?= htmlspecialchars($loginMethod) ?>"
            >

            <div class="form-group">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Unesite e-mail"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? ''
                    ) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Lozinka
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Unesite lozinku"
                    required
                >

            </div>

            <button
                type="submit"
                class="login-button"
                id="submit-button"
            ></button>

        </form>

        <a
            href="index.php"
            class="back-link"
        >
            ← Povratak na početnu
        </a>

    </div>

</main>

<script>

    const tabs = document.querySelectorAll('.login-tab');
    const methodInput = document.getElementById('login_method');
    const title = document.getElementById('mode-title');
    const description = document.getElementById('mode-description');
    const submitButton = document.getElementById('submit-button');

    const modes = {
        ADMIN: {
            title: 'Prijava administratora',
            description:
                'Administratori se prijavljuju svojim lokalnim korisničkim računom.',
            button: 'Prijava administratora'
        },
        AAI: {
            title: 'AAI@EduHr prijava',
            description:
                'Studenti i profesori prijavljuju se svojim AAI@EduHr korisničkim računom.',
            button: 'Prijava putem AAI sustava'
        }
    };

    function setLoginMode(mode) {

        const selectedMode = modes[mode] || modes.ADMIN;

        methodInput.value = mode;
        title.textContent = selectedMode.title;
        description.textContent = selectedMode.description;
        submitButton.textContent = selectedMode.button;

        tabs.forEach((tab) => {
            tab.classList.toggle(
                'active',
                tab.dataset.mode === mode
            );
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            setLoginMode(tab.dataset.mode);
        });
    });

    setLoginMode(methodInput.value);

</script>

</body>
</html>