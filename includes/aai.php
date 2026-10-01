<?php

/*
|--------------------------------------------------------------------------
| Simulacija AAI@EduHr autentifikacije
|--------------------------------------------------------------------------
|
| Ova funkcija simulira vanjski AAI sustav tijekom razvoja aplikacije.
|
| U stvarnom sustavu ovdje bi se koristila stvarna AAI@EduHr
| autentifikacija. Lozinka se tada ne bi provjeravala u našoj aplikaciji.
|
| Razvojna testna lozinka:
|
| AAI2026!
|
| Ova lozinka služi ISKLJUČIVO za razvoj i demonstraciju.
|
|--------------------------------------------------------------------------
*/

function aaiAuthenticate(
    PDO $pdo,
    string $email,
    string $password
): ?array {

    /*
     * Razvojna lozinka za simulirani AAI.
     *
     * U stvarnom sustavu ovdje ne bi postojala.
     */

    $demoPassword = 'AAI2026!';


    /*
     * Normalizacija e-maila.
     */

    $email = strtolower(trim($email));


    /*
     * Simulirani AAI prihvaća samo testnu razvojnu lozinku.
     */

    if (!hash_equals($demoPassword, $password)) {

        return null;
    }


    /*
     * Tražimo korisnika u našoj bazi.
     *
     * Korisnik mora:
     *
     * - postojati
     * - biti AAI korisnik
     * - biti STUDENT ili PROFESSOR
     * - biti aktivan
     */

    $stmt = $pdo->prepare(
        "SELECT
            id,
            email,
            aai_id,
            first_name,
            last_name,
            gender,
            role,
            is_active
         FROM users
         WHERE email = :email
           AND auth_provider = 'AAI'
           AND role IN ('STUDENT', 'PROFESSOR')
           AND is_active = 1
         LIMIT 1"
    );

    $stmt->execute([
        'email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
     * Korisnik ne postoji ili nije AAI student/profesor.
     */

    if (!$user) {

        return null;
    }


    /*
     * Simuliramo podatke koje bi AAI vratio nakon
     * uspješne autentifikacije.
     */

    return [
        'id' => $user['id'],
        'aai_id' => $user['aai_id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'gender' => $user['gender'],
        'role' => $user['role']
    ];
}