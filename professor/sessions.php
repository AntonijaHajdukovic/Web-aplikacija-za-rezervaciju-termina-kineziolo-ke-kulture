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
| Pomoćne funkcije
|--------------------------------------------------------------------------
*/

function dayName($day)
{
    $days = [
        1 => 'Ponedjeljak',
        2 => 'Utorak',
        3 => 'Srijeda',
        4 => 'Četvrtak',
        5 => 'Petak',
        6 => 'Subota',
        7 => 'Nedjelja'
    ];

    return $days[(int) $day] ?? '';
}


function genderName($gender)
{
    $genders = [
        'ALL'    => 'Svi',
        'FEMALE' => 'Žene',
        'MALE'   => 'Muškarci'
    ];

    return $genders[$gender] ?? $gender;
}


/*
|--------------------------------------------------------------------------
| Deaktivacija termina
|--------------------------------------------------------------------------
|
| Termin se ne briše iz baze.
| Samo se postavlja is_active = 0.
|
| Profesor može deaktivirati samo termin
| koji pripada njegovoj vlastitoj izvedbi aktivnosti.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'deactivate'
) {

    $sessionId = filter_input(
        INPUT_POST,
        'session_id',
        FILTER_VALIDATE_INT
    );

    if (!$sessionId) {
        header('Location: sessions.php?message=error');
        exit;
    }

    try {

        $stmt = $pdo->prepare("
            UPDATE activity_sessions s

            INNER JOIN activity_offerings o
                ON o.id = s.offering_id

            SET s.is_active = 0

            WHERE s.id = :session_id
              AND o.professor_id = :professor_id
              AND o.is_active = 1
              AND s.is_active = 1
        ");

        $stmt->execute([
            'session_id'   => $sessionId,
            'professor_id' => $professorId
        ]);


        if ($stmt->rowCount() > 0) {

            header(
                'Location: sessions.php?message=deactivated'
            );

            exit;
        }


        header(
            'Location: sessions.php?message=not_allowed'
        );

        exit;

    } catch (PDOException $e) {

        header(
            'Location: sessions.php?message=error'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dohvat termina
|--------------------------------------------------------------------------
|
| Profesor vidi samo:
|
| - svoje activity_offerings
| - aktivne activity_offerings
| - aktivne aktivnosti
| - aktivne termine
|
| Za regularne aktivnosti prikazujemo i:
|
| - akademsku godinu
| - semestar
|
| BONUS aktivnosti mogu imati semester_id = NULL,
| zato koristimo LEFT JOIN na semesters i academic_years.
|
*/

$sql = "
    SELECT
        s.id AS session_id,
        s.offering_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,
        s.gender,
        s.capacity,
        s.is_active,

        a.name AS activity_name,
        a.description AS activity_description,
        a.points,
        a.activity_type,

        o.id AS offering_id,
        o.professor_id,

        sem.id AS semester_id,
        sem.name AS semester_name,
        sem.start_date AS semester_start_date,
        sem.end_date AS semester_end_date,

        ay.id AS academic_year_id,
        ay.name AS academic_year_name,

        u.first_name,
        u.last_name

    FROM activity_sessions s

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    INNER JOIN users u
        ON u.id = o.professor_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    WHERE s.is_active = 1
      AND o.is_active = 1
      AND a.is_active = 1
      AND o.professor_id = :professor_id

    ORDER BY
        a.name ASC,
        sem.start_date ASC,
        s.day_of_week ASC,
        s.start_time ASC
";


try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'professor_id' => $professorId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $rows = [];

    $error =
        'Došlo je do pogreške prilikom dohvaćanja termina.';
}


/*
|--------------------------------------------------------------------------
| Grupiranje termina po izvedbi aktivnosti
|--------------------------------------------------------------------------
|
| Ista aktivnost može postojati u više semestara.
|
| Zato grupiramo po offering_id, a ne samo po activity_id.
|
*/

$offerings = [];

foreach ($rows as $row) {

    $offeringId = (int) $row['offering_id'];

    if (!isset($offerings[$offeringId])) {

        $offerings[$offeringId] = [
            'id' =>
                $offeringId,

            'name' =>
                $row['activity_name'],

            'description' =>
                $row['activity_description'],

            'points' =>
                (int) $row['points'],

            'activity_type' =>
                $row['activity_type'],

            'professor_id' =>
                (int) $row['professor_id'],

            'professor_name' =>
                $row['first_name']
                . ' '
                . $row['last_name'],

            'semester_id' =>
                $row['semester_id'] !== null
                    ? (int) $row['semester_id']
                    : null,

            'semester_name' =>
                $row['semester_name'],

            'academic_year_name' =>
                $row['academic_year_name'],

            'sessions' => []
        ];
    }


    $offerings[$offeringId]['sessions'][] = $row;
}


$offerings = array_values($offerings);


/*
|--------------------------------------------------------------------------
| Poruka nakon akcije
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Termini - Profesor</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <link
        rel="stylesheet"
        href="../css/sessions.css"
    >

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <!--
    ==========================================================================
    Zaglavlje
    ==========================================================================
    -->

    <section class="page-header">

        <div>

            <h1>
                Termini
            </h1>

            <p>
                Pregled termina održavanja kinezioloških aktivnosti.
            </p>

        </div>


        <a
            href="add_session.php"
            class="add-button"
        >
            + Dodaj termin
        </a>

    </section>


    <!--
    ==========================================================================
    Poruke
    ==========================================================================
    -->

    <?php if ($message === 'deactivated'): ?>

        <div class="message success">
            Termin je uspješno deaktiviran.
        </div>


    <?php elseif ($message === 'not_allowed'): ?>

        <div class="message warning">
            Nemate pravo upravljati ovim terminom.
        </div>


    <?php elseif ($message === 'error'): ?>

        <div class="message error">
            Došlo je do pogreške. Pokušajte ponovno.
        </div>

    <?php endif; ?>


    <!--
    ==========================================================================
    Aktivnosti / izvedbe
    ==========================================================================
    -->

    <?php if (isset($error)): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>


    <?php elseif (empty($offerings)): ?>

        <div class="empty-state">
            Trenutno nema definiranih termina.
        </div>


    <?php else: ?>


        <section class="activities-list">


            <?php foreach ($offerings as $offering): ?>

                <?php

                /*
                 * Budući da SQL dohvaća samo vlastite aktivne
                 * termine, profesor je vlasnik ove izvedbe.
                 */

                $isMyOffering =
                    $offering['professor_id'] === $professorId;


                $activeSessionCount =
                    count($offering['sessions']);


                /*
                 * Ikona aktivnosti
                 */

                $activityIcon = '🏃';


                if (
                    stripos(
                        $offering['name'],
                        'badminton'
                    ) !== false
                ) {

                    $activityIcon = '🏸';

                } elseif (
                    stripos(
                        $offering['name'],
                        'futsal'
                    ) !== false
                ) {

                    $activityIcon = '⚽';

                } elseif (
                    stripos(
                        $offering['name'],
                        'košarka'
                    ) !== false
                ) {

                    $activityIcon = '🏀';

                } elseif (
                    stripos(
                        $offering['name'],
                        'odbojka'
                    ) !== false
                ) {

                    $activityIcon = '🏐';

                } elseif (
                    stripos(
                        $offering['name'],
                        'stolni tenis'
                    ) !== false
                ) {

                    $activityIcon = '🏓';

                } elseif (
                    stripos(
                        $offering['name'],
                        'planinars'
                    ) !== false
                ) {

                    $activityIcon = '🥾';
                }

                ?>


                <article class="activity-card">


                    <!--
                    ==========================================================
                    Zaglavlje aktivnosti
                    ==========================================================
                    -->

                    <div class="activity-header">


                        <div class="activity-main">


                            <div class="activity-title-row">

                                <span class="activity-icon">
                                    <?= $activityIcon ?>
                                </span>


                                <span class="activity-name">

                                    <?= htmlspecialchars(
                                        $offering['name']
                                    ) ?>

                                </span>


                                <span
                                    class="activity-type <?= strtolower(
                                        $offering['activity_type']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $offering['activity_type']
                                    ) ?>

                                </span>

                            </div>


                            <div class="activity-meta">

                                <span>

                                    <strong>

                                        <?= (int) $offering['points'] ?>

                                        bod.

                                    </strong>

                                </span>


                                <span class="meta-separator">
                                    ·
                                </span>


                                <span>

                                    <?= htmlspecialchars(
                                        $offering['professor_name']
                                    ) ?>

                                </span>


                                <?php if (
                                    !empty(
                                        $offering['academic_year_name']
                                    )
                                    &&
                                    !empty(
                                        $offering['semester_name']
                                    )
                                ): ?>

                                    <span class="meta-separator">
                                        ·
                                    </span>

                                    <span>

                                        <?= htmlspecialchars(
                                            $offering['academic_year_name']
                                        ) ?>

                                        –

                                        <?= htmlspecialchars(
                                            $offering['semester_name']
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="meta-separator">
                                        ·
                                    </span>

                                    <span>
                                        Opća aktivnost
                                    </span>

                                <?php endif; ?>


                                <?php if ($isMyOffering): ?>

                                    <span class="my-activity">
                                        Moja aktivnost
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!--
                        ======================================================
                        Sažetak termina
                        ======================================================
                        -->

                        <div class="activity-summary">


                            <div class="session-count">

                                <strong>
                                    <?= $activeSessionCount ?>
                                </strong>

                                <span>

                                    aktivnih

                                    <?= $activeSessionCount === 1
                                        ? 'termin'
                                        : 'termina'
                                    ?>

                                </span>

                            </div>


                            <button
                                type="button"
                                class="toggle-button"
                                onclick="toggleSessions(
                                    'offering-<?= (int) $offering['id'] ?>',
                                    this
                                )"
                            >
                                Prikaži termine ▾
                            </button>

                        </div>

                    </div>


                    <!--
                    ==========================================================
                    Termini aktivnosti
                    ==========================================================
                    -->

                    <div
                        id="offering-<?= (int) $offering['id'] ?>"
                        class="sessions-container"
                    >

                        <div class="sessions-list">


                            <?php foreach (
                                $offering['sessions']
                                as $session
                            ): ?>

                                <?php

                                $isMySession =
                                    (int) $offering['professor_id']
                                    === $professorId;

                                ?>


                                <div class="session-row">


                                    <!-- Dan -->

                                    <div class="session-detail">

                                        <strong>
                                            Dan
                                        </strong>

                                        <span class="session-day">

                                            <?= htmlspecialchars(
                                                dayName(
                                                    $session['day_of_week']
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <!-- Vrijeme -->

                                    <div class="session-detail">

                                        <strong>
                                            Vrijeme
                                        </strong>

                                        <?= htmlspecialchars(
                                            substr(
                                                $session['start_time'],
                                                0,
                                                5
                                            )
                                        ) ?>

                                        –

                                        <?= htmlspecialchars(
                                            substr(
                                                $session['end_time'],
                                                0,
                                                5
                                            )
                                        ) ?>

                                    </div>


                                    <!-- Lokacija -->

                                    <div class="session-detail">

                                        <strong>
                                            Lokacija
                                        </strong>

                                        <?= htmlspecialchars(
                                            $session['location']
                                        ) ?>

                                    </div>


                                    <!-- Spol -->

                                    <div class="session-detail">

                                        <strong>
                                            Spol
                                        </strong>

                                        <?= htmlspecialchars(
                                            genderName(
                                                $session['gender']
                                            )
                                        ) ?>

                                    </div>


                                    <!-- Kapacitet -->

                                    <div class="session-detail">

                                        <strong>
                                            Kapacitet
                                        </strong>

                                        <?php if (
                                            $session['capacity'] === null
                                        ): ?>

                                            Neograničeno

                                        <?php else: ?>

                                            <?= (int) $session['capacity'] ?>

                                        <?php endif; ?>

                                    </div>


                                    <!-- Status -->

                                    <div class="session-detail">

                                        <strong>
                                            Status
                                        </strong>

                                        <span class="status active">
                                            Aktivan
                                        </span>

                                    </div>


                                    <!-- Akcije -->

                                    <div class="session-actions">

                                        <?php if ($isMySession): ?>


                                            <a
                                                href="edit_session.php?id=<?= (int) $session['session_id'] ?>"
                                                class="edit-button"
                                            >
                                                Uredi
                                            </a>


                                            <a
                                                href="session_reservations.php?session_id=<?= (int) $session['session_id'] ?>"
                                                class="edit-button"
                                            >
                                                Rezervacije
                                            </a>


                                            <form
                                                method="POST"
                                                onsubmit="return confirm(
                                                    'Jeste li sigurni da želite deaktivirati ovaj termin?'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="deactivate"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="session_id"
                                                    value="<?= (int) $session['session_id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="deactivate-button"
                                                >
                                                    Deaktiviraj
                                                </button>

                                            </form>


                                        <?php else: ?>

                                            <span class="no-actions">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                </div>

                            <?php endforeach; ?>


                        </div>

                    </div>


                </article>


            <?php endforeach; ?>


        </section>

    <?php endif; ?>


</main>


<script>

function toggleSessions(id, button)
{
    const container =
        document.getElementById(id);

    if (!container) {
        return;
    }


    if (container.classList.contains('open')) {

        container.classList.remove('open');

        button.textContent =
            'Prikaži termine ▾';

    } else {

        container.classList.add('open');

        button.textContent =
            'Sakrij termine ▴';
    }
}

</script>


</body>

</html>