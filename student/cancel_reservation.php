<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('STUDENT');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_reservations.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];

$reservationId = isset($_POST['reservation_id'])
    ? (int) $_POST['reservation_id']
    : 0;

if ($reservationId <= 0) {
    header('Location: my_reservations.php?message=error');
    exit;
}

try {

    $pdo->beginTransaction();

    /*
     * Dohvaćamo rezervaciju i podatke o terminu.
     *
     * FOR UPDATE zaključava rezervaciju tijekom obrade
     * kako ne bi došlo do istovremenih promjena.
     *
     * Provjeravamo student_id kako student ne bi mogao
     * otkazati tuđu rezervaciju.
     */
$sql = "
    SELECT
        r.id,
        r.student_id,
        r.session_id,
        r.reservation_date,
        r.status,

        s.start_time,
        s.end_time,
        s.is_active,

        o.is_active AS offering_active,

        a.is_active AS activity_active

    FROM reservations r

    INNER JOIN activity_sessions s
        ON s.id = r.session_id

    INNER JOIN activity_offerings o
        ON o.id = s.offering_id

    INNER JOIN activities a
        ON a.id = o.activity_id

    WHERE r.id = ?
      AND r.student_id = ?

    FOR UPDATE
";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $reservationId,
        $studentId
    ]);

    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
     * Rezervacija ne postoji ili ne pripada studentu.
     */
    if (!$reservation) {

        $pdo->rollBack();

        header('Location: my_reservations.php?message=not_available');
        exit;
    }

    /*
     * Moguće je otkazati samo aktivnu rezervaciju.
     */
    if ($reservation['status'] !== 'RESERVED') {

        /*
        * Ako je profesor deaktivirao termin,
        * rezervaciju više nije moguće otkazati.
        */
        if (
            (int) $reservation['is_active'] !== 1
            ||
            (int) $reservation['offering_active'] !== 1
            ||
            (int) $reservation['activity_active'] !== 1
        ) {

            $pdo->rollBack();

            header('Location: my_reservations.php?message=not_available');
            exit;
        }
    }

    /*
     * Sastavljamo točan datum i vrijeme početka termina.
     *
     * Primjer:
     * reservation_date = 2026-10-16
     * start_time       = 18:00:00
     *
     * -> 2026-10-16 18:00:00
     */
    $sessionStart = new DateTime(
        $reservation['reservation_date'] . ' ' . $reservation['start_time']
    );

    $now = new DateTime();

    /*
     * Granica za pravovremeno otkazivanje:
     *
     * početak termina - 3 sata
     */
    $cancellationDeadline = clone $sessionStart;
    $cancellationDeadline->modify('-3 hours');

    /*
    * Provjera je li termin već počeo.
    *
    * Nakon početka termina rezervaciju više nije moguće otkazati.
    */
    if ($now >= $sessionStart) {

        $pdo->rollBack();

        header('Location: my_reservations.php?message=not_available');
        exit;
    }


    /*
    * Provjera pravovremenog / kasnog otkazivanja.
    *
    * Više od ili točno 3 sata prije početka:
    *     nema penala.
    *
    * Manje od 3 sata prije početka:
    *     penal -1 bod.
    */
    if ($now <= $cancellationDeadline) {

        $pointsAwarded = 0;

    } else {

        $pointsAwarded = -1;
    }

    /*
     * Otkazivanje rezervacije.
     */
    $sql = "
        UPDATE reservations
        SET
            status = 'CANCELLED',
            cancelled_at = CURRENT_TIMESTAMP,
            points_awarded = ?
        WHERE id = ?
          AND student_id = ?
          AND status = 'RESERVED'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $pointsAwarded,
        $reservationId,
        $studentId
    ]);

    /*
     * Ako se ništa nije promijenilo,
     * rezervacija više nije aktivna.
     */
    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        header('Location: my_reservations.php?message=not_available');
        exit;
    }

    $pdo->commit();

    /*
     * Različita poruka ovisno o tome
     * je li dodijeljen penal.
     */
    if ($pointsAwarded === -1) {

        header('Location: my_reservations.php?message=late_cancel');
        exit;
    }

    header('Location: my_reservations.php?message=cancelled');
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: my_reservations.php?message=error');
    exit;
}