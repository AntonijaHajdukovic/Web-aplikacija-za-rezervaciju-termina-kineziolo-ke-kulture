<?php

session_start();

require_once '../includes/auth.php';

requireRole('STUDENT');

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
        Lokacije - TVZ Kineziološka kultura
    </title>

    <link
        rel="stylesheet"
        href="../css/common.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar.css"
    >

    <!-- Leaflet CSS -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | Page header
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header p {
            color: #5b6470;
            font-size: 16px;
            margin-top: 6px;
        }


        /*
        |--------------------------------------------------------------------------
        | Map
        |--------------------------------------------------------------------------
        */

        .map-container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 28px;
        }

        #map {
            width: 100%;
            height: 450px;
            border-radius: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Locations
        |--------------------------------------------------------------------------
        */

        .locations-title {
            margin-bottom: 16px;
            font-size: 20px;
            color: #172b4d;
        }

        .locations-list {
            display: grid;
            gap: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Location card
        |--------------------------------------------------------------------------
        */

        .location-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .location-card h2 {
            color: #07558a;
            font-size: 20px;
            line-height: 1.3;
            margin: 0 0 7px;
        }

        .location-name {
            font-weight: bold;
            font-size: 16px;
            color: #172b4d;
            margin-bottom: 8px;
        }

        .location-address {
            color: #5b6470;
            line-height: 1.5;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .location-description {
            color: #172b4d;
            font-size: 15px;
            line-height: 1.5;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
        }


        /*
        |--------------------------------------------------------------------------
        | Links
        |--------------------------------------------------------------------------
        */

        .location-link {
            display: inline-block;
            margin-top: 8px;
            color: #07558a;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .location-link:hover {
            text-decoration: underline;
        }

        .map-button {
            display: inline-block;
            margin-top: 10px;
            padding: 9px 14px;
            border-radius: 8px;
            background-color: #07558a;
            color: #ffffff;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .map-button:hover {
            background-color: #064a77;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            #map {
                height: 380px;
            }

            .map-container {
                padding: 14px;
            }

            .location-card {
                padding: 18px;
            }

            .location-card h2 {
                font-size: 19px;
            }

        }


        @media (max-width: 500px) {

            #map {
                height: 340px;
            }

            .location-address,
            .location-description {
                font-size: 14px;
            }

            .map-button {
                width: 100%;
                text-align: center;
            }

        }

    </style>

</head>


<body>

    <?php require_once '../includes/navbar.php'; ?>


    <main class="container">


        <!--
        |--------------------------------------------------------------------------
        | Page header
        |--------------------------------------------------------------------------
        -->

        <section class="page-header">

            <h1>
                Lokacije
            </h1>

            <p>
                Pregled lokacija na kojima se održavaju
                aktivnosti kolegija Kineziološka kultura.
            </p>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Karta
        |--------------------------------------------------------------------------
        -->

        <section class="map-container">

            <div id="map"></div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Popis lokacija
        |--------------------------------------------------------------------------
        -->

        <section>

            <h2 class="locations-title">
                Lokacije aktivnosti
            </h2>


            <div class="locations-list">


                <!-- BADMINTON -->

                <article class="location-card">

                    <h2>
                        Badminton
                    </h2>

                    <div class="location-name">
                        Badminton centar MAX
                    </div>

                    <div class="location-address">

                        Zagrebački velesajam,
                        paviljon 20<br>

                        Avenija Dubrovnik 15

                    </div>

                    <a
                        href="https://badminton.hr/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="location-link"
                    >
                        Web stranica lokacije
                    </a>

                    <br>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Badminton+centar+Max+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- FITNESS -->

                <article class="location-card">

                    <h2>
                        Fitnes / teretana
                    </h2>

                    <div class="location-name">
                        XXL1
                    </div>

                    <div class="location-address">

                        Zagrebački velesajam,
                        paviljon 25<br>

                        Avenija Dubrovnik 15

                    </div>

                    <a
                        href="https://fc-xxl.hr/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="location-link"
                    >
                        Web stranica lokacije
                    </a>

                    <br>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=XXL1+Zagreb+Velesajam"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- FUTSAL -->

                <article class="location-card">

                    <h2>
                        Futsal
                    </h2>

                    <div class="location-name">
                        Dvorana ŽKK Medveščak
                    </div>

                    <div class="location-address">

                        Zagrebački velesajam,
                        paviljon 25, prizemlje<br>

                        Avenija Dubrovnik 15

                    </div>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Zagrebački+velesajam+paviljon+25+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- KOŠARKA I ODBOJKA -->

                <article class="location-card">

                    <h2>
                        Košarka & odbojka
                    </h2>

                    <div class="location-name">
                        Studentska dvorana Kačićeva 23
                    </div>

                    <div class="location-address">

                        Kačićeva 23<br>

                        Preko puta AGG fakulteta
                        (Arhitektura, Građevina, Geodezija)

                    </div>

                    <div class="location-description">

                        Ulaz u dvoranu je iza kafića,
                        na sjevernoj ili južnoj strani.
                        Zvonite!

                    </div>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Kačićeva+23+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- PLANINARENJE -->

                <article class="location-card">

                    <h2>
                        Planinarenje
                    </h2>

                    <div class="location-name">
                        Park prirode Medvednica
                    </div>

                    <div class="location-address">

                        Polazišna točka:
                        Šestinski lagvić

                    </div>

                    <div class="location-description">

                        Autobusnom linijom
                        Britanski trg - Mihaljevac
                        siđete kod Šestinske crkve.

                        Hodate pored groblja prema sjeveru
                        oko 500 metara.

                        <br><br>

                        Sastanak je oko 100 m iznad
                        restorana Šestinski lagvić
                        pod malom drvenom nadstrešnicom.

                    </div>

                    <a
                        href="http://www.pp-medvednica.hr/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="location-link"
                    >
                        Park prirode Medvednica
                    </a>

                    <br>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Šestinski+lagvić+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- PJEŠAČKA TURA JARUN -->

                <article class="location-card">

                    <h2>
                        Pješačke ture
                    </h2>

                    <div class="location-name">
                        SRC Jarun
                    </div>

                    <div class="location-address">

                        Aleja Matije Ljubeka,
                        kod restorana Jarunski dvori

                    </div>

                    <div class="location-description">

                        U blizini je okretište
                        autobusne linije
                        Ljubljanica - Jarun.

                    </div>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Jarunski+dvori+Aleja+Matije+Ljubeka+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- PJEŠAČKA TURA MOST SLOBODE -->

                <article class="location-card">

                    <h2>
                        Pješačke ture
                    </h2>

                    <div class="location-name">
                        Most Slobode
                    </div>

                    <div class="location-address">

                        Av. Većeslava Holjevca

                    </div>

                    <div class="location-description">

                        Sastanak je u dogovoreno vrijeme
                        ispod Mosta Slobode,
                        dolje kod nasipa na strani Bundeka.

                    </div>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Most+Slobode+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- STOLNI TENIS -->

                <article class="location-card">

                    <h2>
                        Stolni tenis
                    </h2>

                    <div class="location-name">
                        HASTK Mladost
                    </div>

                    <div class="location-address">

                        Jarunska ulica 5

                        <br>

                        U blizini Studentskog doma
                        Stjepan Radić

                    </div>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=HASTK+Mladost+Jarunska+5+Zagreb"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="map-button"
                    >
                        Otvori u Google kartama
                    </a>

                </article>


                <!-- NATJECANJA -->

                <article class="location-card">

                    <h2>
                        Natjecanja
                    </h2>

                    <div class="location-description">

                        Natjecanja se održavaju prema
                        dodatnim obavijestima.

                        Voditelj/ica natjecanja obavještava
                        studente/ice koji se prijave
                        za pojedina natjecanja.

                    </div>

                </article>


            </div>

        </section>

    </main>


    <!-- Leaflet JS -->

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ></script>


    <script>

        /*
         * Početna karta Zagreba.
         */

        const map = L.map('map').setView(
            [45.8150, 15.9819],
            12
        );


        /*
         * OpenStreetMap podloga.
         */

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);


        /*
         * Lokacije aktivnosti.
         *
         * Koordinate predstavljaju lokaciju
         * koju student treba posjetiti.
         */

        const locations = [

            {
                name: 'Badminton centar MAX',
                activity: 'Badminton',
                address: 'Zagrebački velesajam, paviljon 20',
                lat: 45.7788,
                lng: 15.9687
            },

            {
                name: 'XXL1',
                activity: 'Fitnes / teretana',
                address: 'Zagrebački velesajam, paviljon 25',
                lat: 45.7794,
                lng: 15.9698
            },

            {
                name: 'Dvorana ŽKK Medveščak',
                activity: 'Futsal',
                address: 'Zagrebački velesajam, paviljon 25',
                lat: 45.7794,
                lng: 15.9698
            },

            {
                name: 'Studentska dvorana Kačićeva 23',
                activity: 'Košarka & odbojka',
                address: 'Kačićeva 23, Zagreb',
                lat: 45.8109,
                lng: 15.9575
            },

            {
                name: 'Šestinski lagvić',
                activity: 'Planinarenje',
                address: 'Šestinski lagvić, Zagreb',
                lat: 45.8368,
                lng: 15.9405
            },

            {
                name: 'SRC Jarun',
                activity: 'Pješačke ture',
                address: 'Aleja Matije Ljubeka, Jarun',
                lat: 45.7797,
                lng: 15.9234
            },

            {
                name: 'Most Slobode',
                activity: 'Pješačke ture',
                address: 'Av. Većeslava Holjevca, Zagreb',
                lat: 45.7935,
                lng: 15.9820
            },

            {
                name: 'HASTK Mladost',
                activity: 'Stolni tenis',
                address: 'Jarunska ulica 5, Zagreb',
                lat: 45.7828,
                lng: 15.9339
            }

        ];


        /*
         * Dodavanje markera na kartu.
         */

        locations.forEach(function(location) {

            const marker = L.marker([
                location.lat,
                location.lng
            ]).addTo(map);


            marker.bindPopup(
                '<strong>'
                + location.activity
                + '</strong>'
                + '<br><br>'
                + location.name
                + '<br>'
                + location.address
            );

        });

    </script>

</body>

</html>