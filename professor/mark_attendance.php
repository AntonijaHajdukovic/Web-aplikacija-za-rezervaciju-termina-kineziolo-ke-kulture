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
| Samo POST zahtjevi
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sessions.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dohvat podataka iz forme
|--------------------------------------------------------------------------
*/

$reservationId = isset($_POST['reservation_id'])
    ? (int) $_POST['reservation_id']
    : 0;

$attendance = $_POST['attendance'] ?? '';

$returnSessionId = isset($_POST['session_id'])
    ? (int) $_POST['session_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Osnovna validacija
|--------------------------------------------------------------------------
*/

if (
    $reservationId <= 0 ||
    $returnSessionId <= 0 ||
    !in_array($attendance, ['ATTENDED', 'NO_SHOW'], true)
) {
    header(
        'Location: session_reservations.php?session_id='
        . $returnSessionId
        . '&message=error'
    );
    exit;
}


try {

    $pdo->beginTransaction();


    /*
     * Dohvaćamo rezervaciju.
     *
     * Istovremeno provjeravamo:
     *
     * 1. rezervacija postoji
     * 2. pripada traženom terminu
     * 3. termin pripada activity_offeringu ovog profesora
     * 4. izvedba aktivnosti je aktivna
     * 5. aktivnost je aktivna
     * 6. termin je aktivan
     *
     * FOR UPDATE zaključava rezervaciju tijekom obrade.
     */

    $sql = "
        SELECT
            r.id AS reservation_id,
            r.session_id,
            r.status,
            r.reservation_date,

            s.start_time,
            s.end_time,

            o.professor_id,
            o.is_active AS offering_active,

            a.is_active AS activity_active,
            a.points

        FROM reservations r

        INNER JOIN activity_sessions s
            ON s.id = r.session_id

        INNER JOIN activity_offerings o
            ON o.id = s.offering_id

        INNER JOIN activities a
            ON a.id = o.activity_id

        WHERE r.id = ?
          AND r.session_id = ?
          AND o.professor_id = ?
          AND o.is_active = 1
          AND a.is_active = 1
          AND s.is_active = 1

        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $reservationId,
        $returnSessionId,
        $professorId
    ]);

    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
     * Rezervacija ne postoji ili ne pripada ovom profesoru.
     */

    if (!$reservation) {

        $pdo->rollBack();

        header(
            'Location: session_reservations.php?session_id='
            . $returnSessionId
            . '&message=not_allowed'
        );

        exit;
    }


    /*
     * Evidencija dolaska moguća je samo
     * za aktivnu rezervaciju.
     */

    if ($reservation['status'] !== 'RESERVED') {

        $pdo->rollBack();

        header(
            'Location: session_reservations.php?session_id='
            . $returnSessionId
            . '&message=not_available'
        );

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | Određivanje bodova
    |--------------------------------------------------------------------------
    *
    * ATTENDED:
    * student dobiva pune bodove aktivnosti.
    *
    * NO_SHOW:
    * student dobiva penal -1 bod.
    */

    if ($attendance === 'ATTENDED') {

        $status = 'ATTENDED';
        $pointsAwarded = (int) $reservation['points'];

    } else {

        $status = 'NO_SHOW';
        $pointsAwarded = -1;
    }


    /*
    |----------------------------------------------------------------------
    | Ažuriranje rezervacije
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE reservations
        SET
            status = ?,
            attended_at = CURRENT_TIMESTAMP,
            points_awarded = ?

        WHERE id = ?
          AND session_id = ?
          AND status = 'RESERVED'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $status,
        $pointsAwarded,
        $reservationId,
        $returnSessionId
    ]);


    /*
     * Ako se ništa nije promijenilo,
     * rezervacija više nije aktivna.
     */

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        header(
            'Location: session_reservations.php?session_id='
            . $returnSessionId
            . '&message=not_available'
        );

        exit;
    }


    $pdo->commit();


    /*
    |----------------------------------------------------------------------
    | Poruka nakon uspješne evidencije
    |--------------------------------------------------------------------------
    */

    if ($attendance === 'ATTENDED') {

        header(
            'Location: session_reservations.php?session_id='
            . $returnSessionId
            . '&message=attended'
        );

    } else {

        header(
            'Location: session_reservations.php?session_id='
            . $returnSessionId
            . '&message=no_show'
        );
    }

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header(
        'Location: session_reservations.php?session_id='
        . $returnSessionId
        . '&message=error'
    );

    exit;
}