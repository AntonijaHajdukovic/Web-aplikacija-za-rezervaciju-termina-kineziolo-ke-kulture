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
| Dohvat session_id
|--------------------------------------------------------------------------
*/

$sessionId = filter_input(
    INPUT_GET,
    'session_id',
    FILTER_VALIDATE_INT
);

if (!$sessionId) {
    header('Location: sessions.php?message=error');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat termina
|--------------------------------------------------------------------------
|
| Provjeravamo da termin pripada activity_offeringu
| čiji je profesor trenutno prijavljen profesor.
|
*/

$sql = "
    SELECT
        s.id AS session_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,
        s.capacity,
        s.gender,
        s.is_active,

        o.id AS offering_id,
        o.professor_id,

        a.id AS activity_id,
        a.name AS activity_name,
        a.points,

        sem.name AS semester_name,
        ay.name AS academic_year_name

    FROM activity_sessions s

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    WHERE s.id = ?
      AND o.professor_id = ?
      AND o.is_active = 1
      AND a.is_active = 1
      AND s.is_active = 1

    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $sessionId,
    $professorId
]);

$session = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Termin ne postoji ili ne pripada profesoru
|--------------------------------------------------------------------------
*/

if (!$session) {
    header('Location: sessions.php?message=not_allowed');
    exit;
}


/*
|--------------------------------------------------------------------------
| Pomoćna funkcija za dan u tjednu
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


/*
|--------------------------------------------------------------------------
| Dohvat rezervacija
|--------------------------------------------------------------------------
|
| Dohvaćamo sve rezervacije za konkretan termin.
|
| Rezervacija je vezana uz konkretan datum
| preko reservation_date.
|
*/

$sql = "
    SELECT
        r.id AS reservation_id,
        r.reservation_date,
        r.status,
        r.reserved_at,
        r.cancelled_at,
        r.attended_at,
        r.points_awarded,

        u.id AS student_id,
        u.first_name,
        u.last_name,
        u.email

    FROM reservations r

    INNER JOIN users u
        ON u.id = r.student_id

    WHERE r.session_id = ?

    ORDER BY
        r.reservation_date ASC,
        u.last_name ASC,
        u.first_name ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $sessionId
]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <title>
        Rezervacije termina - Profesor
    </title>

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

    <style>

        .page-header {
            margin-bottom: 30px;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            color: #07558a;
            font-weight: bold;
            text-decoration: none;
        }

        .session-info {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .session-info h2 {
            margin-top: 0;
            color: #07558a;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 40px;
        }

        .reservation-list {
            display: grid;
            gap: 15px;
        }

        .reservation-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);

            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .student-name {
            font-size: 18px;
            font-weight: bold;
            color: #172b4d;
        }

        .reservation-detail {
            font-size: 15px;
        }

        .reservation-detail strong {
            display: block;
            color: #07558a;
            margin-bottom: 4px;
        }

        .status {
            font-weight: bold;
        }

        .status-reserved {
            color: #267a2e;
        }

        .status-cancelled {
            color: #b91c1c;
        }

        .status-attended {
            color: #267a2e;
        }

        .status-no-show {
            color: #b91c1c;
        }

        .attendance-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .attendance-button {
            border: none;
            padding: 9px 14px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .attended-button {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .attended-button:hover {
            background-color: #d7efd9;
        }

        .no-show-button {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .no-show-button:hover {
            background-color: #f9d7d7;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .message-success {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .message-warning {
            background-color: #fff4e5;
            color: #a15c00;
        }

        .message-error {
            background-color: #fdecec;
            color: #b91c1c;
        }

        .empty-state {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 35px;
            text-align: center;
            color: #5b6470;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .semester-info {
            margin-top: 12px;
            color: #5b6470;
            font-size: 14px;
        }

        @media (max-width: 900px) {

            .reservation-card {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 600px) {

            .session-details {
                grid-template-columns: 1fr;
            }

            .reservation-card {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">


    <a
        href="sessions.php"
        class="back-button"
    >
        ← Natrag na termine
    </a>


    <section class="page-header">

        <div>

            <h1>
                Rezervacije termina
            </h1>

            <p>
                Pregled studenata koji su rezervirali ovaj termin.
            </p>

        </div>

    </section>


    <?php if ($message === 'attended'): ?>

        <div class="message message-success">
            Dolazak studenta je uspješno evidentiran.
        </div>

    <?php elseif ($message === 'no_show'): ?>

        <div class="message message-warning">
            Student je obilježen da nije došao i dodijeljen mu je penal od -1 boda.
        </div>

    <?php elseif ($message === 'not_available'): ?>

        <div class="message message-error">
            Ovu rezervaciju više nije moguće evidentirati.
        </div>

    <?php elseif ($message === 'not_allowed'): ?>

        <div class="message message-error">
            Nemate pravo mijenjati ovu rezervaciju.
        </div>

    <?php elseif ($message === 'error'): ?>

        <div class="message message-error">
            Došlo je do pogreške prilikom evidentiranja dolaska.
        </div>

    <?php endif; ?>


    <!-- INFORMACIJE O TERMINU -->

    <section class="session-info">

        <h2>
            <?= htmlspecialchars(
                $session['activity_name']
            ) ?>
        </h2>


        <?php if (
            !empty($session['academic_year_name'])
            &&
            !empty($session['semester_name'])
        ): ?>

            <div class="semester-info">

                <strong>
                    Semestar:
                </strong>

                <?= htmlspecialchars(
                    $session['academic_year_name']
                ) ?>

                -

                <?= htmlspecialchars(
                    $session['semester_name']
                ) ?>

            </div>

        <?php else: ?>

            <div class="semester-info">
                Opća aktivnost
            </div>

        <?php endif; ?>


        <div class="session-details">

            <div>

                <strong>
                    Dan:
                </strong>

                <?= dayName(
                    $session['day_of_week']
                ) ?>

            </div>


            <div>

                <strong>
                    Vrijeme:
                </strong>

                <?= htmlspecialchars(
                    substr($session['start_time'], 0, 5)
                ) ?>

                -

                <?= htmlspecialchars(
                    substr($session['end_time'], 0, 5)
                ) ?>

            </div>


            <div>

                <strong>
                    Lokacija:
                </strong>

                <?= htmlspecialchars(
                    $session['location']
                ) ?>

            </div>


            <div>

                <strong>
                    Kapacitet:
                </strong>

                <?php if ($session['capacity'] === null): ?>

                    Neograničeno

                <?php else: ?>

                    <?= (int) $session['capacity'] ?>

                    mjesta

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- REZERVACIJE -->

    <?php if (empty($reservations)): ?>

        <div class="empty-state">

            Za ovaj termin trenutno nema rezervacija.

        </div>


    <?php else: ?>

        <section class="reservation-list">


            <?php foreach ($reservations as $reservation): ?>

                <?php

                $statusClass = '';

                $statusText = '';

                switch ($reservation['status']) {

                    case 'RESERVED':

                        $statusClass =
                            'status-reserved';

                        $statusText =
                            'Rezervirano';

                        break;

                    case 'CANCELLED':

                        $statusClass =
                            'status-cancelled';

                        $statusText =
                            'Otkazano';

                        break;

                    case 'ATTENDED':

                        $statusClass =
                            'status-attended';

                        $statusText =
                            'Dolazak';

                        break;

                    case 'NO_SHOW':

                        $statusClass =
                            'status-no-show';

                        $statusText =
                            'Nedolazak';

                        break;

                    default:

                        $statusText =
                            $reservation['status'];
                }

                ?>


                <div class="reservation-card">


                    <div class="student-name">

                        <?= htmlspecialchars(
                            $reservation['first_name']
                            . ' '
                            . $reservation['last_name']
                        ) ?>

                    </div>


                    <div class="reservation-detail">

                        <strong>
                            Datum
                        </strong>

                        <?= date(
                            'j. n. Y.',
                            strtotime(
                                $reservation[
                                    'reservation_date'
                                ]
                            )
                        ) ?>

                    </div>


                    <div class="reservation-detail">

                        <strong>
                            Status
                        </strong>

                        <span
                            class="status <?= $statusClass ?>"
                        >

                            <?= htmlspecialchars(
                                $statusText
                            ) ?>

                        </span>

                    </div>


                    <div class="reservation-detail">

                        <strong>
                            Bodovi
                        </strong>

                        <?= (int) $reservation[
                            'points_awarded'
                        ] ?>

                    </div>


                    <?php if (
                        $reservation['status']
                        === 'RESERVED'
                    ): ?>

                        <div class="attendance-actions">


                            <form
                                method="POST"
                                action="mark_attendance.php"
                            >

                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int) $reservation['reservation_id'] ?>"
                                >


                                <input
                                    type="hidden"
                                    name="session_id"
                                    value="<?= (int) $sessionId ?>"
                                >


                                <input
                                    type="hidden"
                                    name="attendance"
                                    value="ATTENDED"
                                >


                                <button
                                    type="submit"
                                    class="attendance-button attended-button"
                                >
                                    Dolazak
                                </button>

                            </form>


                            <form
                                method="POST"
                                action="mark_attendance.php"
                            >

                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int) $reservation['reservation_id'] ?>"
                                >


                                <input
                                    type="hidden"
                                    name="session_id"
                                    value="<?= (int) $sessionId ?>"
                                >


                                <input
                                    type="hidden"
                                    name="attendance"
                                    value="NO_SHOW"
                                >


                                <button
                                    type="submit"
                                    class="attendance-button no-show-button"
                                    onclick="return confirm(
                                        'Jeste li sigurni da želite označiti da student nije došao?'
                                    );"
                                >
                                    Nedolazak
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>


                </div>

            <?php endforeach; ?>


        </section>

    <?php endif; ?>


</main>

</body>

</html>