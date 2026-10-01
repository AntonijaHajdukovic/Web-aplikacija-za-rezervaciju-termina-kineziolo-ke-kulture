<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TVZ Kineziološka kultura</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            color: #1f2937;
            min-height: 100vh;
        }

        /* HEADER */

        header {
            background-color: #ffffff;
            height: 96px;
            padding: 10px 60px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .logo {
            width: 250px;
            height: auto;
            display: block;
        }

        /* HERO */

        .hero {
            min-height: calc(100vh - 96px);
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 50px 20px;
        }

        .hero-content {
            width: 100%;
            max-width: 760px;
            background-color: #ffffff;
            padding: 55px 60px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
        }

        .hero h1 {
            font-size: 36px;
            line-height: 1.25;
            font-weight: 700;
            color: #172b4d;
            margin-bottom: 18px;
        }

        .hero p {
            max-width: 620px;
            margin: 0 auto 32px;
            font-size: 17px;
            line-height: 1.6;
            color: #5b6470;
        }

        /* BUTTON */

        .buttons {
            display: flex;
            justify-content: center;
        }

        .button {
            display: inline-block;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .login-button {
            background-color: #07558a;
            color: #ffffff;
            box-shadow: 0 3px 8px rgba(7, 85, 138, 0.18);
        }

        .login-button:hover {
            background-color: #064a78;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(7, 85, 138, 0.22);
        }

        /* RESPONSIVE */

        @media (max-width: 700px) {

            header {
                height: 82px;
                padding: 10px 25px;
            }

            .logo {
                width: 210px;
            }

            .hero {
                min-height: calc(100vh - 82px);
                padding: 30px 18px;
            }

            .hero-content {
                padding: 40px 25px;
            }

            .hero h1 {
                font-size: 30px;
            }

            .hero p {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

<header>
    <img
        src="images/tvz-logo2.png"
        alt="TVZ - Tehničko veleučilište u Zagrebu"
        class="logo"
    >
</header>

<main class="hero">
    <div class="hero-content">

        <h1>TVZ Kineziološka kultura</h1>

        <p>
            Sustav za prijavu na aktivnosti, praćenje prisutnosti
            i ostvarivanje bodova iz kolegija Kineziološka kultura.
        </p>

        <div class="buttons">
            <a href="login.php" class="button login-button">
                Prijava
            </a>
        </div>

    </div>
</main>

</body>
</html>