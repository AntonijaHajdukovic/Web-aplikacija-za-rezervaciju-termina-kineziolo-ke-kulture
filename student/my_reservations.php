<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');

$studentId = $_SESSION['user_id'];


/*
 * Dohvat rezervacija trenutno prijavljenog studenta.
 *
 * Prikazujemo samo aktivne rezervacije.
 * Otkazane rezervacije ostaju u bazi,
 * ali se više ne prikazuju studentu.
 *
 * Deaktivirani termini se i dalje prikazuju
 * ako ih je student prethodno rezervirao.
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

        a.name,
        a.description,
        a.points,

        s.day_of_week,
        s.start_time,
        s.end_time,
        s.location,
        s.gender,
        s.is_active,

        o.professor_id,

        sem.name AS semester_name,
        ay.name AS academic_year_name,

        u.first_name AS professor_first_name,
        u.last_name AS professor_last_name

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    LEFT JOIN semesters sem
        ON sem.id = o.semester_id

    LEFT JOIN academic_years ay
        ON ay.id = sem.academic_year_id

    LEFT JOIN users u
        ON u.id = o.professor_id

    WHERE r.student_id = ?
      AND r.status = 'RESERVED'

    ORDER BY
        r.reservation_date ASC,
        s.start_time ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $studentId
]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Pretvaranje broja dana u naziv.
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

    return $days[$day] ?? '';
}


/*
 * Poruka nakon akcije.
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

    <!-- Zajednički stilovi -->
    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <title>
        Moje rezervacije - TVZ Kineziološka kultura
    </title>

    <style>

        /*
        |--------------------------------------------------------------------------
        | Reservations
        |--------------------------------------------------------------------------
        */

        .reservations {
            display: grid;
            gap: 16px;
        }

        .reservation-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .reservation-name {
            font-size: 21px;
            font-weight: bold;
            color: #07558a;
            margin-bottom: 18px;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 30px;
        }

        .detail {
            font-size: 15px;
            line-height: 1.45;
            color: #4b5563;
        }

        .detail strong {
            color: #172b4d;
        }

        .status {
            font-weight: bold;
        }

        .status-reserved {
            color: #267a2e;
        }

        .status-inactive {
            color: #b91c1c;
        }


        /*
        |--------------------------------------------------------------------------
        | Actions
        |--------------------------------------------------------------------------
        */

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .cancel-button {
            border: none;
            background-color: #b91c1c;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .cancel-button:hover {
            opacity: 0.9;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .no-reservations {
            background-color: #ffffff;
            padding: 28px;
            border-radius: 12px;
            text-align: center;
            color: #5b6470;
        }


        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */

        .message {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .success {
            background-color: #e8f5e9;
            color: #267a2e;
        }

        .warning {
            background-color: #fff4e5;
            color: #a15c00;
        }

        .error {
            background-color: #fdecec;
            color: #b91c1c;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .details {
                grid-template-columns: 1fr;
            }

            .reservation-card {
                padding: 18px;
            }

            .reservation-name {
                font-size: 20px;
            }

            .actions {
                justify-content: stretch;
            }

            .cancel-button {
                width: 100%;
            }
        }

    </style>

</head>


<body>

<?php require_once '../includes/navbar.php'; ?>


<main class="container">

    <h1>
        Moje rezervacije
    </h1>

    <p class="subtitle">
        Pregled tvojih rezerviranih termina iz kolegija Kineziološka kultura.
    </p>


    <!-- PORUKE -->

    <?php if ($message === 'cancelled'): ?>

        <div class="message success">
            Rezervacija je uspješno otkazana.
        </div>


    <?php elseif ($message === 'late_cancel'): ?>

        <div class="message warning">
            Rezervacija je otkazana, ali zbog kasnog
            otkazivanja dodijeljen je penal od -1 boda.
        </div>


    <?php elseif ($message === 'not_available'): ?>

        <div class="message error">
            Ovu rezervaciju više nije moguće otkazati.
        </div>


    <?php elseif ($message === 'error'): ?>

        <div class="message error">
            Došlo je do pogreške prilikom otkazivanja rezervacije.
        </div>

    <?php endif; ?>


    <!-- REZERVACIJE -->

    <?php if (empty($reservations)): ?>

        <div class="no-reservations">
            Trenutno nemaš nijednu rezervaciju.
        </div>


    <?php else: ?>

        <div class="reservations">

            <?php foreach ($reservations as $reservation): ?>

                <?php
                /*
                 * Provjera je li termin još aktivan.
                 *
                 * 1 = aktivan
                 * 0 = deaktiviran od strane profesora
                 */
                $sessionActive = (int) $reservation['is_active'] === 1;
                ?>

                <div class="reservation-card">

                    <div class="reservation-name">

                        <?= htmlspecialchars(
                            $reservation['name']
                        ) ?>

                    </div>


                    <div class="details">

                        <div class="detail">

                            <strong>
                                Datum:
                            </strong>

                            <?= date(
                                'j. n. Y.',
                                strtotime(
                                    $reservation['reservation_date']
                                )
                            ) ?>

                        </div>


                        <div class="detail">

                            <strong>
                                Dan:
                            </strong>

                            <?= dayName(
                                $reservation['day_of_week']
                            ) ?>

                        </div>


                        <div class="detail">

                            <strong>
                                Vrijeme:
                            </strong>

                            <?= htmlspecialchars(
                                substr($reservation['start_time'], 0, 5)
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                substr($reservation['end_time'], 0, 5)
                            ) ?>

                        </div>


                        <div class="detail">

                            <strong>
                                Lokacija:
                            </strong>

                            <?= htmlspecialchars(
                                $reservation['location']
                            ) ?>

                        </div>

                        <div class="detail">

                            <strong>
                                Profesor:
                            </strong>

                            <?= htmlspecialchars(
                                trim(
                                    $reservation['professor_first_name']
                                    . ' '
                                    . $reservation['professor_last_name']
                                )
                            ) ?>

                        </div>


                        <div class="detail">

                            <strong>
                                Bodovi aktivnosti:
                            </strong>

                            <?= (int) $reservation['points'] ?>

                        </div>


                        <div class="detail">

                            <strong>
                                Status:
                            </strong>

                            <?php if ($sessionActive): ?>

                                <span class="status status-reserved">
                                    Rezervirano
                                </span>

                            <?php else: ?>

                                <span class="status status-inactive">
                                    Termin je deaktiviran
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <?php if ($sessionActive): ?>

                        <div class="actions">

                            <form
                                method="POST"
                                action="cancel_reservation.php"
                            >

                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int) $reservation['reservation_id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="cancel-button"
                                    onclick="return confirm(
                                        'Jeste li sigurni da želite otkazati ovu rezervaciju?'
                                    );"
                                >
                                    Otkaži rezervaciju
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


</main>

</body>

</html>