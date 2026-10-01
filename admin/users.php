<?php

session_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole('ADMIN');

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Aktivacija / deaktivacija korisnika
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Admin ne može deaktivirati sam sebe
    if ($userId === (int) $_SESSION['user_id']) {

        $error = 'Ne možete deaktivirati vlastiti administratorski račun.';

    } elseif ($userId <= 0) {

        $error = 'Neispravan korisnik.';

    } elseif (!in_array($action, ['activate', 'deactivate'], true)) {

        $error = 'Neispravna radnja.';

    } else {

        $newStatus = $action === 'activate' ? 1 : 0;

        $stmt = $pdo->prepare(
            "UPDATE users
             SET is_active = :is_active
             WHERE id = :id"
        );

        $stmt->execute([
            'is_active' => $newStatus,
            'id' => $userId
        ]);

        if ($action === 'activate') {
            $message = 'Korisnik je uspješno aktiviran.';
        } else {
            $message = 'Korisnik je uspješno deaktiviran.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Filteri
|--------------------------------------------------------------------------
*/

$roleFilter = $_GET['role'] ?? 'ALL';
$statusFilter = $_GET['status'] ?? 'ALL';

$allowedRoles = [
    'ALL',
    'STUDENT',
    'PROFESSOR',
    'ADMIN'
];

$allowedStatuses = [
    'ALL',
    'ACTIVE',
    'INACTIVE'
];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = 'ALL';
}

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| Dohvat korisnika
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        first_name,
        last_name,
        email,
        role,
        is_active
    FROM users
    WHERE 1 = 1
";

$params = [];

if ($roleFilter !== 'ALL') {
    $sql .= " AND role = :role";
    $params['role'] = $roleFilter;
}

if ($statusFilter === 'ACTIVE') {
    $sql .= " AND is_active = 1";
}

if ($statusFilter === 'INACTIVE') {
    $sql .= " AND is_active = 0";
}

$sql .= "
    ORDER BY
        CASE role
            WHEN 'ADMIN' THEN 1
            WHEN 'PROFESSOR' THEN 2
            WHEN 'STUDENT' THEN 3
            ELSE 4
        END,
        last_name,
        first_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="hr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Korisnici - Administracija</title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <style>

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 30px;
        }

        .admin-header-content h1 {
            margin-bottom: 10px;
        }

        .admin-header-content p {
            color: #5b6470;
            font-size: 17px;
            margin: 0;
        }

        .import-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            background-color: #07558a;
            color: #ffffff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            white-space: nowrap;
        }

        .import-button:hover {
            background-color: #064a78;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .add-user-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            background-color: #ffffff;
            color: #07558a;
            border: 1px solid #07558a;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            white-space: nowrap;
        }

        .add-user-button:hover {
            background-color: #eef6fb;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .message.error {
            background-color: #ffebee;
            color: #c62828;
        }

        .filter-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .filter-card h2 {
            color: #07558a;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .filters {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .filter-group label {
            font-weight: bold;
        }

        .filter-group select {
            min-width: 180px;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
            background-color: #ffffff;
        }

        .filter-button {
            background-color: #07558a;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .filter-button:hover {
            background-color: #064a78;
        }

        .users-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            overflow-x: auto;
        }

        .users-card h2 {
            color: #07558a;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            text-align: left;
            color: #07558a;
            padding: 15px 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        .users-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .role {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        .role-admin {
            background-color: #ede9fe;
            color: #6d28d9;
        }

        .role-professor {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .role-student {
            background-color: #f3f4f6;
            color: #374151;
        }

        .status {
            font-weight: bold;
        }

        .status-active {
            color: #2e7d32;
        }

        .status-inactive {
            color: #c62828;
        }

        .action-button {
            border: none;
            border-radius: 7px;
            padding: 8px 13px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        .activate-button {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .activate-button:hover {
            background-color: #c8e6c9;
        }

        .deactivate-button {
            background-color: #ffebee;
            color: #c62828;
        }

        .deactivate-button:hover {
            background-color: #ffcdd2;
        }

        .current-admin {
            color: #6b7280;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        @media (max-width: 800px) {

            .admin-header {
                align-items: stretch;
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .header-actions a {
                width: 100%;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select {
                width: 100%;
            }

            .filter-button {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<?php require_once '../includes/navbar.php'; ?>

<main class="container">

    <section class="admin-header">

        <div class="admin-header-content">

            <h1>
                Korisnici
            </h1>

            <p>
                Pregled i upravljanje korisnicima sustava.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="add_user.php"
                class="add-user-button"
            >
                + Dodaj korisnika
            </a>

            <a
                href="import_students.php"
                class="import-button"
            >
                + Uvezi studente
            </a>

        </div>

    </section>


    <?php if ($message !== ''): ?>

        <div class="message success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- FILTERI -->

    <section class="filter-card">

        <h2>
            Filter korisnika
        </h2>

        <form method="GET">

            <div class="filters">

                <div class="filter-group">

                    <label for="role">
                        Uloga
                    </label>

                    <select
                        id="role"
                        name="role"
                    >

                        <option
                            value="ALL"
                            <?= $roleFilter === 'ALL'
                                ? 'selected'
                                : '' ?>
                        >
                            Svi
                        </option>

                        <option
                            value="STUDENT"
                            <?= $roleFilter === 'STUDENT'
                                ? 'selected'
                                : '' ?>
                        >
                            Studenti
                        </option>

                        <option
                            value="PROFESSOR"
                            <?= $roleFilter === 'PROFESSOR'
                                ? 'selected'
                                : '' ?>
                        >
                            Profesori
                        </option>

                        <option
                            value="ADMIN"
                            <?= $roleFilter === 'ADMIN'
                                ? 'selected'
                                : '' ?>
                        >
                            Administratori
                        </option>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="ALL"
                            <?= $statusFilter === 'ALL'
                                ? 'selected'
                                : '' ?>
                        >
                            Svi
                        </option>

                        <option
                            value="ACTIVE"
                            <?= $statusFilter === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Aktivni
                        </option>

                        <option
                            value="INACTIVE"
                            <?= $statusFilter === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Neaktivni
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-button"
                >
                    Primijeni filter
                </button>

            </div>

        </form>

    </section>


    <!-- KORISNICI -->

    <section class="users-card">

        <h2>
            Korisnici
            (<?= count($users) ?>)
        </h2>


        <?php if (empty($users)): ?>

            <div class="empty-state">
                Nema korisnika koji odgovaraju odabranom filteru.
            </div>

        <?php else: ?>

            <table class="users-table">

                <thead>

                    <tr>

                        <th>
                            Ime i prezime
                        </th>

                        <th>
                            E-mail
                        </th>

                        <th>
                            Uloga
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Radnja
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($users as $user): ?>

                    <?php

                    $roleClass = match ($user['role']) {

                        'ADMIN' =>
                            'role-admin',

                        'PROFESSOR' =>
                            'role-professor',

                        'STUDENT' =>
                            'role-student',

                        default =>
                            ''

                    };


                    $roleText = match ($user['role']) {

                        'ADMIN' =>
                            'Administrator',

                        'PROFESSOR' =>
                            'Profesor',

                        'STUDENT' =>
                            'Student',

                        default =>
                            $user['role']

                    };

                    ?>


                    <tr>

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $user['first_name']
                                    . ' '
                                    . $user['last_name']
                                ) ?>

                            </strong>


                            <?php if (
                                (int) $user['id']
                                === (int) $_SESSION['user_id']
                            ): ?>

                                <div class="current-admin">
                                    Trenutno prijavljeni korisnik
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $user['email']
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="role <?= $roleClass ?>"
                            >

                                <?= htmlspecialchars(
                                    $roleText
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if (
                                (int) $user['is_active'] === 1
                            ): ?>

                                <span class="status status-active">
                                    Aktivan
                                </span>

                            <?php else: ?>

                                <span class="status status-inactive">
                                    Neaktivan
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                (int) $user['id']
                                === (int) $_SESSION['user_id']
                            ): ?>

                                <span class="current-admin">
                                    Nije moguće mijenjati
                                </span>

                            <?php elseif (
                                (int) $user['is_active'] === 1
                            ): ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Jeste li sigurni da želite deaktivirati ovog korisnika?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $user['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="deactivate"
                                    >

                                    <button
                                        type="submit"
                                        class="action-button deactivate-button"
                                    >
                                        Deaktiviraj
                                    </button>

                                </form>

                            <?php else: ?>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $user['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="activate"
                                    >

                                    <button
                                        type="submit"
                                        class="action-button activate-button"
                                    >
                                        Aktiviraj
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </section>

</main>

</body>

</html>