<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de notes — {{ $bulletin['student'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #101828; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #1e40af; }
        .subtitle { margin: 0 0 18px; color: #667085; }
        .identity { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .identity td { padding: 4px 0; }
        .identity .label { width: 90px; color: #667085; }
        table.grades { width: 100%; border-collapse: collapse; }
        table.grades th { background: #1e40af; color: #fff; padding: 8px; text-align: left; }
        table.grades td { padding: 7px 8px; border-bottom: 1px solid #e4e7ec; }
        table.grades tr:nth-child(even) td { background: #f6f9ff; }
        .num { text-align: center; }
        table.grades tfoot td { background: #eaf2ff; font-weight: bold; border-top: 2px solid #1e40af; }
        .empty { padding: 18px; text-align: center; color: #667085; }
        .footer { margin-top: 24px; font-size: 10px; color: #667085; }
    </style>
</head>
<body>
    <h1>Bulletin de notes</h1>
    <p class="subtitle">{{ $bulletin['term'] }} — année scolaire {{ $bulletin['academic_year'] }}</p>

    <table class="identity">
        <tr><td class="label">Élève</td><td><strong>{{ $bulletin['student'] }}</strong></td></tr>
        <tr><td class="label">Matricule</td><td>{{ $bulletin['matricule'] }}</td></tr>
        <tr><td class="label">Classe</td><td>{{ $bulletin['class'] ?? '—' }}</td></tr>
    </table>

    @if (count($bulletin['subjects']) === 0)
        <p class="empty">Aucune note enregistrée pour cette période.</p>
    @else
        <table class="grades">
            <thead>
                <tr>
                    <th>Matière</th>
                    <th class="num">Coefficient</th>
                    <th class="num">Notes</th>
                    <th class="num">Moyenne /20</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bulletin['subjects'] as $subject)
                    <tr>
                        <td>{{ $subject['subject'] }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($subject['coefficient'], 2, ',', ''), '0'), ',') }}</td>
                        <td class="num">{{ $subject['grades_count'] }}</td>
                        <td class="num">{{ number_format($subject['average'], 2, ',', '') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Moyenne générale{{ $bulletin['mention'] ? ' — '.$bulletin['mention'] : '' }}</td>
                    <td class="num">{{ $bulletin['overall_average'] !== null ? number_format($bulletin['overall_average'], 2, ',', '') : '—' }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <p class="footer">Document édité le {{ $bulletin['issued_on'] }}.</p>
</body>
</html>
