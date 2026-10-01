<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$firstName = $_SESSION['first_name'] ?? 'Admin';
$lastName = $_SESSION['last_name'] ?? '';

?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin - TVZ Kineziološka kultura</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <style>

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .admin-card {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            min-height: 180px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            text-decoration: none;
            color: inherit;

            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .admin-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0, 0, 0, 0.12);
        }

        .card-icon {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .admin-card h2 {
            color: #07558a;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .admin-card p {
            color: #5b6470;
            line-height: 1.5;
            font-size: 15px;
        }

        .card-arrow {
            color: #07558a;
            font-weight: bold;
            margin-top: 20px;
        }

        @media (max-width: 900px) {
            .admin-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            header {
                height: auto;
                padding: 20px;
            }

            .logo {
                width: 200px;
            }

            .container {
                padding: 30px 20px;
            }

            .admin-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    

<?php require_once '../includes/navbar.php'; ?>

<main class="container">

    <h1 class="page-title">
        Administracija
    </h1>

    <p class="page-description">
        Upravljanje sustavom Kineziološke kulture.
    </p>

    <div class="admin-grid">

        <a href="users.php" class="admin-card">

            <div>
                <div class="card-icon">👥</div>

                <h2>Korisnici</h2>

                <p>
                    Pregled i upravljanje studentima, profesorima i administratorima.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>


        <a href="activities.php" class="admin-card">

            <div>
                <div class="card-icon">🏃</div>

                <h2>Aktivnosti</h2>

                <p>
                    Pregled i upravljanje aktivnostima kolegija Kineziološka kultura.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>


        <a href="sessions.php" class="admin-card">

            <div>
                <div class="card-icon">📅</div>

                <h2>Termini</h2>

                <p>
                    Pregled termina aktivnosti i njihovog statusa.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>

        <a href="courses.php" class="admin-card">

            <div>
                <div class="card-icon">📚</div>

                <h2>Kolegiji</h2>

                <p>
                    Pregled i upravljanje kolegijima i njihovim studentima.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>

        <a href="academic_years.php" class="admin-card">

            <div>
                <div class="card-icon">🎓</div>

                <h2>Akademske godine i semestri</h2>

                <p>
                    Upravljanje akademskim godinama i semestrima.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>

        <a href="announcements.php" class="admin-card">

            <div>
                <div class="card-icon">📢</div>

                <h2>Obavijesti</h2>

                <p>
                    Pregled i upravljanje obavijestima koje se prikazuju studentima.
                </p>
            </div>

            <div class="card-arrow">
                Otvori →
            </div>

        </a>

    </div>

</main>

</body>
</html>