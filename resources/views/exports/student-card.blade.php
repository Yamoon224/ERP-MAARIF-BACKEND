<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte scolaire — {{ config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #101828; margin: 0; padding: 12px; }
        .card {
            width: 320px; height: 200px; border: 1px solid #d0d5dd; border-radius: 10px;
            padding: 14px; margin: 0 10px 16px 0; float: left; box-sizing: border-box;
            page-break-inside: avoid;
        }
        .school { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #1e40af; font-weight: bold; margin: 0 0 8px; }
        .layout { width: 100%; }
        .info { display: inline-block; width: 170px; vertical-align: top; }
        .name { font-size: 15px; font-weight: bold; margin: 0 0 4px; }
        .matricule { font-size: 11px; color: #667085; margin: 0 0 4px; }
        .class { font-size: 12px; color: #344054; }
        .qr { display: inline-block; width: 90px; vertical-align: top; text-align: right; }
        .qr img { width: 90px; height: 90px; }
    </style>
</head>
<body>
    @foreach ($cards as $card)
        <div class="card">
            <p class="school">{{ config('app.name') }} — Carte élève</p>
            <div class="layout">
                <div class="info">
                    <p class="name">{{ $card['student']->fullName() }}</p>
                    <p class="matricule">Matricule : {{ $card['student']->matricule }}</p>
                    <p class="class">{{ $card['student']->schoolClass?->name ?? '—' }}</p>
                </div>
                <div class="qr">
                    <img src="{{ $card['qrDataUri'] }}" alt="QR code de pointage">
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
