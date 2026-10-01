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
| Dohvat ID-a izvedbe aktivnosti
|--------------------------------------------------------------------------
*/

$offeringId = filter_input(
    INPUT_GET,
    'offering_id',
    FILTER_VALIDATE_INT
);

if (!$offeringId) {
    header('Location: activities.php?message=error');
    exit;
}


/*
|--------------------------------------------------------------------------
| Deaktivacija izvedbe
|--------------------------------------------------------------------------
|
| Profesor može deaktivirati samo vlastitu aktivnu izvedbu.
|
| NE deaktiviramo activities zapis.
| Samo activity_offerings zapis.
|
*/

try {

    $stmt = $pdo->prepare("
        UPDATE activity_offerings
        SET
            is_active = 0,
            deactivated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND professor_id = :professor_id
          AND is_active = 1
    ");

    $stmt->execute([
        'id' =>
            $offeringId,

        'professor_id' =>
            $professorId
    ]);


    if ($stmt->rowCount() === 0) {

        header(
            'Location: activities.php?message=error'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Uspješna deaktivacija
    |--------------------------------------------------------------------------
    |
    | Termini i reservations ne brišu se.
    | Svi povijesni podaci ostaju u bazi.
    |
    | Budući prikazi filtriraju activity_offerings.is_active = 1,
    | pa se ova izvedba više neće nuditi studentima.
    |
    */

    header(
        'Location: activities.php?message=deactivated'
    );

    exit;


} catch (PDOException $e) {

    header(
        'Location: activities.php?message=error'
    );

    exit;
}