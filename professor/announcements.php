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
 * Dodavanje nove obavijesti.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
     * DODAVANJE OBAVIJESTI
     */
    if ($action === 'add') {

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '' || $content === '') {

            header('Location: announcements.php?message=empty');
            exit;
        }

        $sql = "
            INSERT INTO announcements (
                professor_id,
                title,
                content
            )
            VALUES (
                ?,
                ?,
                ?
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $professorId,
            $title,
            $content
        ]);

        header('Location: announcements.php?message=added');
        exit;
    }


    /*
     * BRISANJE OBAVIJESTI
     */
    if ($action === 'delete') {

        $announcementId = isset($_POST['announcement_id'])
            ? (int) $_POST['announcement_id']
            : 0;

        if ($announcementId <= 0) {

            header('Location: announcements.php?message=error');
            exit;
        }

        /*
         * Brišemo samo obavijest koju je
         * objavio trenutno prijavljeni profesor.
         */
        $sql = "
            DELETE FROM announcements
            WHERE id = ?
              AND professor_id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $announcementId,
            $professorId
        ]);

        header('Location: announcements.php?message=deleted');
        exit;
    }
}


/*
 * Dohvat obavijesti trenutno prijavljenog profesora.
 */
$sql = "
    SELECT
        id,
        title,
        content,
        created_at
    FROM announcements
    WHERE professor_id = ?
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $professorId
]);

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
        Obavijesti - Profesor
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

        .announcement-form {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .announcement-form h2 {
            margin-bottom: 20px;
            color: #07558a;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-family: inherit;
            font-size: 15px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .add-button {
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            background-color: #07558a;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
        }

        .add-button:hover {
            background-color: #064a77;
        }

        .announcement-list {
            display: grid;
            gap: 18px;
        }

        .announcement-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 22px 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .announcement-card h2 {
            color: #07558a;
            margin-bottom: 8px;
        }

        .announcement-date {
            color: #7a828c;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .announcement-content {
            color: #333333;
            line-height: 1.6;
            white-space: pre-line;
        }

        .delete-form {
            margin-top: 18px;
        }

        .delete-button {
            border: none;
            padding: 9px 14px;
            border-radius: 7px;
            background-color: #b91c1c;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
        }

        .delete-button:hover {
            background-color: #991b1b;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .message.error {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .empty-state {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            color: #5b6470;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

    </style>

</head>

<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">

        <section class="page-header">

            <h1>
                Obavijesti
            </h1>

            <p>
                Objavite važne informacije za studente.
            </p>

        </section>


        <?php if (isset($_GET['message'])): ?>

            <?php if ($_GET['message'] === 'added'): ?>

                <div class="message success">
                    Obavijest je uspješno objavljena.
                </div>

            <?php elseif ($_GET['message'] === 'deleted'): ?>

                <div class="message success">
                    Obavijest je uspješno obrisana.
                </div>

            <?php elseif ($_GET['message'] === 'empty'): ?>

                <div class="message error">
                    Naslov i sadržaj obavijesti su obavezni.
                </div>

            <?php elseif ($_GET['message'] === 'error'): ?>

                <div class="message error">
                    Došlo je do pogreške.
                </div>

            <?php endif; ?>

        <?php endif; ?>


        <section class="announcement-form">

            <h2>
                Nova obavijest
            </h2>

            <form
                method="POST"
                action="announcements.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                >

                <div class="form-group">

                    <label for="title">
                        Naslov
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="content">
                        Sadržaj
                    </label>

                    <textarea
                        id="content"
                        name="content"
                        required
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="add-button"
                >
                    Objavi obavijest
                </button>

            </form>

        </section>


        <section>

            <h2>
                Moje obavijesti
            </h2>


            <?php if (empty($announcements)): ?>

                <div class="empty-state">

                    Još nema objavljenih obavijesti.

                </div>

            <?php else: ?>

                <div class="announcement-list">

                    <?php foreach ($announcements as $announcement): ?>

                        <article class="announcement-card">

                            <h2>
                                <?= htmlspecialchars(
                                    $announcement['title']
                                ) ?>
                            </h2>


                            <div class="announcement-date">

                                <?= date(
                                    'j. n. Y. H:i',
                                    strtotime(
                                        $announcement['created_at']
                                    )
                                ) ?>

                            </div>


                            <div class="announcement-content">

                                <?= htmlspecialchars(
                                    $announcement['content']
                                ) ?>

                            </div>


                            <form
                                method="POST"
                                action="announcements.php"
                                class="delete-form"
                                onsubmit="return confirm(
                                    'Jeste li sigurni da želite obrisati ovu obavijest?'
                                );"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
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

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>