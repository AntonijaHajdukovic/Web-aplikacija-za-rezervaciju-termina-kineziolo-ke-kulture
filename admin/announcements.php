<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

/*
|--------------------------------------------------------------------------
| Brisanje obavijesti
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $announcementId = filter_input(
        INPUT_POST,
        'announcement_id',
        FILTER_VALIDATE_INT
    );

    if (!$announcementId) {
        header('Location: announcements.php?message=error');
        exit;
    }

    try {

        $stmt = $pdo->prepare(
            "
            DELETE FROM announcements
            WHERE id = ?
            "
        );

        $stmt->execute([
            $announcementId
        ]);

        if ($stmt->rowCount() > 0) {
            header('Location: announcements.php?message=deleted');
        } else {
            header('Location: announcements.php?message=not_found');
        }

        exit;

    } catch (PDOException $e) {

        header('Location: announcements.php?message=error');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dohvat svih obavijesti
|--------------------------------------------------------------------------
|
| Obavijest je povezana s korisnikom koji ju je objavio.
| Prikazujemo samo podatke potrebne administratoru.
|
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

    ORDER BY
        a.created_at DESC
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
        Obavijesti - Administracija
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
        | Kartica s obavijestima
        |--------------------------------------------------------------------------
        */

        .announcements-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 28px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .announcements-card h2 {
            color: #07558a;
            margin-bottom: 25px;
            font-size: 24px;
        }


        /*
        |--------------------------------------------------------------------------
        | Pojedinačna obavijest
        |--------------------------------------------------------------------------
        */

        .announcement {
            padding: 25px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .announcement:first-child {
            padding-top: 0;
        }

        .announcement:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .announcement-title {
            color: #07558a;
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .announcement-meta {
            color: #7a8491;
            font-size: 14px;
        }

        .announcement-content {
            margin-top: 20px;
            color: #1f2937;
            line-height: 1.6;
            font-size: 16px;

            white-space: pre-line;
        }


        /*
        |--------------------------------------------------------------------------
        | Autor
        |--------------------------------------------------------------------------
        */

        .announcement-author {
            margin-top: 20px;
            padding-top: 15px;

            border-top: 1px solid #eef0f2;

            color: #6b7280;
            font-size: 14px;
        }

        .announcement-author strong {
            color: #07558a;
        }


        /*
        |--------------------------------------------------------------------------
        | Gumb za brisanje
        |--------------------------------------------------------------------------
        */

        .delete-form {
            margin: 0;
        }

        .delete-button {
            border: none;

            padding: 9px 15px;

            border-radius: 7px;

            background-color: #fdeaea;
            color: #c62828;

            font-weight: bold;
            font-size: 14px;

            cursor: pointer;

            white-space: nowrap;
        }

        .delete-button:hover {
            background-color: #f9dada;
        }


        /*
        |--------------------------------------------------------------------------
        | Prazno stanje
        |--------------------------------------------------------------------------
        */

        .empty-state {
            padding: 40px 20px;

            text-align: center;

            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .page-header {
                flex-direction: column;
            }

            .announcement-header {
                flex-direction: column;
            }

            .delete-form {
                width: 100%;
            }

            .delete-button {
                width: 100%;
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

        <div>

            <h1>
                Obavijesti
            </h1>

            <p>
                Pregled i upravljanje obavijestima koje se prikazuju studentima.
            </p>

        </div>

    </section>


    <!-- ==========================================================
         Poruke
    =========================================================== -->

    <?php if ($message === 'deleted'): ?>

        <div class="message success">

            Obavijest je uspješno obrisana.

        </div>


    <?php elseif ($message === 'not_found'): ?>

        <div class="message warning">

            Obavijest nije pronađena.

        </div>


    <?php elseif ($message === 'error'): ?>

        <div class="message error">

            Došlo je do pogreške.
            Pokušajte ponovno.

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         Obavijesti
    =========================================================== -->

    <section class="announcements-card">

        <h2>
            Obavijesti (<?= count($announcements) ?>)
        </h2>


        <?php if (empty($announcements)): ?>

            <div class="empty-state">

                Trenutno nema objavljenih obavijesti.

            </div>


        <?php else: ?>


            <?php foreach ($announcements as $announcement): ?>

                <article class="announcement">


                    <!-- ==================================================
                         Naslov i brisanje
                    =================================================== -->

                    <div class="announcement-header">

                        <div>

                            <div class="announcement-title">

                                <?= htmlspecialchars(
                                    $announcement['title']
                                ) ?>

                            </div>


                            <div class="announcement-meta">

                                <?= date(
                                    'd. m. Y. H:i',
                                    strtotime(
                                        $announcement['created_at']
                                    )
                                ) ?>

                            </div>

                        </div>


                        <!-- ==================================================
                             Brisanje
                        =================================================== -->

                        <form
                            method="POST"
                            class="delete-form"
                            onsubmit="
                                return confirm(
                                    'Jeste li sigurni da želite obrisati ovu obavijest?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="announcement_id"
                                value="<?= (int) $announcement['id'] ?>"
                            >

                            <button
                                type="submit"
                                class="delete-button"
                            >
                                Obriši
                            </button>

                        </form>

                    </div>


                    <!-- ==================================================
                         Sadržaj
                    =================================================== -->

                    <div class="announcement-content">

                        <?= htmlspecialchars(
                            $announcement['content']
                        ) ?>

                    </div>


                    <!-- ==================================================
                         Autor
                    =================================================== -->

                    <div class="announcement-author">

                        Objavio/la:

                        <strong>

                            <?= htmlspecialchars(
                                $announcement['first_name']
                                . ' '
                                . $announcement['last_name']
                            ) ?>

                        </strong>

                    </div>


                </article>

            <?php endforeach; ?>


        <?php endif; ?>

    </section>


</main>

</body>

</html>