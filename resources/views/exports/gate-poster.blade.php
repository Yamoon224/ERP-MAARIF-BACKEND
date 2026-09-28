<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Pointage au portail - {{ config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #101828; text-align: center; padding-top: 60px; }
        h1 { font-size: 26px; margin: 0 0 6px; color: #1e40af; }
        .subtitle { margin: 0 0 40px; color: #667085; font-size: 14px; }
        img.qr { width: 320px; height: 320px; }
        .instructions { max-width: 420px; margin: 40px auto 0; font-size: 13px; color: #344054; text-align: left; }
        .instructions li { margin-bottom: 10px; }
        .footer { margin-top: 50px; font-size: 10px; color: #98a2b3; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="subtitle">Scannez ce QR code depuis votre portail à votre arrivée</p>

    <img class="qr" src="{{ $qrDataUri }}" alt="QR code de pointage">

    <ol class="instructions">
        <li>Connectez-vous au portail parent ou élève.</li>
        <li>Scannez ce QR code avec l'appareil photo de votre téléphone, ou depuis le bouton « Pointer mon arrivée » du portail.</li>
        <li>Autorisez la localisation lorsque votre navigateur le demande : vous devez être à proximité immédiate du portail.</li>
    </ol>

    <p class="footer">Document édité le {{ now()->format('d/m/Y') }}.</p>
</body>
</html>
