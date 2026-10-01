<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');


/*
|--------------------------------------------------------------------------
| Dohvat svih objavljenih obavijesti
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.title,
        a.content,
        a.created_at,
        u.first_name,
        u.last_name

    FROM announcements a

    INNER JOIN users u
        ON u.id = a.professor_id

    ORDER BY a.created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute();

$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        Obavijesti - Student
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
        | Page header
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header p {
            color: #5b6470;
            font-size: 16px;
            margin-top: 6px;
        }


        /*
        |--------------------------------------------------------------------------
        | Announcement list
        |--------------------------------------------------------------------------
        */

        .announcement-list {
            display: grid;
            gap: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Announcement card
        |--------------------------------------------------------------------------
        */

        .announcement-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .announcement-card h2 {
            color: #07558a;
            font-size: 20px;
            line-height: 1.3;
            margin: 0 0 7px;
        }


        /*
        |--------------------------------------------------------------------------
        | Date
        |--------------------------------------------------------------------------
        */

        .announcement-date {
            color: #7a828c;
            font-size: 13px;
            margin-bottom: 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | Content
        |--------------------------------------------------------------------------
        */

        .announcement-content {
            color: #333333;
            font-size: 15px;
            line-height: 1.55;
            white-space: pre-line;
        }


        /*
        |--------------------------------------------------------------------------
        | Author
        |--------------------------------------------------------------------------
        */

        .announcement-author {
            margin-top: 16px;
            padding-top: 13px;
            border-top: 1px solid #e5e7eb;
            color: #5b6470;
            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .empty-state {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            color: #5b6470;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            .announcement-card {
                padding: 18px;
            }

            .announcement-card h2 {
                font-size: 19px;
            }

            .announcement-content {
                font-size: 14px;
            }

        }

    </style>

</head>


<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">


        <!--
        |--------------------------------------------------------------------------
        | Page header
        |--------------------------------------------------------------------------
        -->

        <section class="page-header">

            <h1>
                Obavijesti
            </h1>

            <p>
                Važne informacije i obavijesti profesora.
            </p>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        -->

        <?php if (empty($announcements)): ?>

            <div class="empty-state">

                Trenutno nema objavljenih obavijesti.

            </div>


        <?php else: ?>

            <section class="announcement-list">

                <?php foreach ($announcements as $announcement): ?>

                    <article class="announcement-card">


                        <!-- Naslov -->

                        <h2>

                            <?= htmlspecialchars(
                                $announcement['title']
                            ) ?>

                        </h2>


                        <!-- Datum objave -->

                        <div class="announcement-date">

                            <?= date(
                                'j. n. Y. H:i',
                                strtotime(
                                    $announcement['created_at']
                                )
                            ) ?>

                        </div>


                        <!-- Sadržaj -->

                        <div class="announcement-content">

                            <?= htmlspecialchars(
                                $announcement['content']
                            ) ?>

                        </div>


                        <!-- Autor -->

                        <div class="announcement-author">

                            Objavio/la:

                            <?= htmlspecialchars(
                                $announcement['first_name']
                                . ' '
                                . $announcement['last_name']
                            ) ?>

                        </div>


                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>


    </main>

</body>

</html>