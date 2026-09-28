<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte scolaire — {{ config('app.name') }}</title>
    <style>
        /* Format CR80 (85,6 x 54 mm), une carte par page : chaque page est imprimee
           directement sur une carte PVC vierge par l'imprimante de badges, sans
           marge ni decoupe a faire. */
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #101828; }
        .card {
            width: 85.6mm; height: 53.98mm; box-sizing: border-box;
            position: relative; overflow: hidden; page-break-after: always;
        }
        .card:last-child { page-break-after: auto; }
        .band { background: #1e40af; color: #fff; padding: 3mm 4mm 2.2mm; }
        .band .school { margin: 0; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; }
        .body { padding: 3mm 4mm; }
        .name { margin: 0 0 1.5mm; font-size: 11pt; font-weight: bold; }
        .matricule { margin: 0 0 1mm; font-size: 8pt; color: #667085; }
        .class { margin: 0; font-size: 9pt; color: #344054; }
        .qr { position: absolute; right: 4mm; bottom: 3mm; }
        .qr img { width: 16mm; height: 16mm; }
    </style>
</head>
<body>
    @foreach ($cards as $card)
        <div class="card">
            <div class="band">
                <p class="school">{{ config('app.name') }} — Carte élève</p>
            </div>
            <div class="body">
                <p class="name">{{ $card['student']->fullName() }}</p>
                <p class="matricule">Matricule : {{ $card['student']->matricule }}</p>
                <p class="class">{{ $card['student']->schoolClass?->name ?? '—' }}</p>
            </div>
            <div class="qr">
                <img src="{{ $card['qrDataUri'] }}" alt="QR code de pointage">
            </div>
        </div>
    @endforeach
</body>
</html>
