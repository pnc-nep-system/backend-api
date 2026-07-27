<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advisory Note - {{ $note->document_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #333; padding: 24px; }

        .header { border-bottom: 3px solid #2c3e50; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 16pt; color: #2c3e50; }
        .header .meta { font-size: 9pt; color: #666; margin-top: 4px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; background: #e8f4fd; color: #2980b9; border: 1px solid #aed6f1; }

        .info-grid { width: 100%; margin-bottom: 20px; }
        .info-grid td { padding: 4px 8px 4px 0; font-size: 9pt; vertical-align: top; width: 50%; }
        .info-grid .label { font-weight: bold; color: #555; }

        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section-title { font-size: 11pt; font-weight: bold; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 4px; margin-bottom: 10px; }
        .section-body { font-size: 9.5pt; white-space: pre-wrap; }

        .rec-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 6px; }
        .rec-table th { background: #2c3e50; color: white; padding: 6px 8px; text-align: left; }
        .rec-table td { padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        .rec-table tr:nth-child(even) td { background: #f8f9fa; }

        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; font-size: 8pt; color: #777; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Advisory Note &mdash; {{ $note->document_name }}</h1>
        <div class="meta">
            Generated: {{ $generatedAt }}
            &nbsp;&bull;&nbsp;
            Status: <span class="badge">{{ $note->status }}</span>
            @if($note->analysis_scope)
                &nbsp;&bull;&nbsp; Scope: {{ $note->analysis_scope }}
            @endif
        </div>
    </div>

    <table class="info-grid">
        <tr>
            <td><span class="label">Submitting Party:</span> {{ $note->submitting_party }}</td>
            <td><span class="label">Submitted At:</span> {{ $note->submitted_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>
                <span class="label">Source Programme:</span>
                @if($note->programmeEntry)
                    #{{ $note->programmeEntry->id }} &mdash; {{ $note->programmeEntry->programme_name }}
                    ({{ $note->programmeEntry->organisation->name ?? 'Unknown Organisation' }})
                @else
                    N/A
                @endif
            </td>
            <td><span class="label">Coordinator:</span> {{ $note->coordinator->name ?? 'Unassigned' }}</td>
        </tr>
        @if($note->analysis_scope_detail)
        <tr>
            <td colspan="2"><span class="label">Scope Detail:</span> {{ $note->analysis_scope_detail }}</td>
        </tr>
        @endif
        @if($note->delivered_at)
        <tr>
            <td colspan="2"><span class="label">Delivered At:</span> {{ $note->delivered_at->format('Y-m-d H:i') }}</td>
        </tr>
        @endif
    </table>

    @if($note->section_profile)
    <div class="section">
        <div class="section-title">A &middot; Programme Profile as Interpreted</div>
        <div class="section-body">{{ $note->section_profile }}</div>
    </div>
    @endif

    @if($note->recommendations->isNotEmpty())
    <div class="section">
        <div class="section-title">B &middot; Similar or Overlapping Programmes</div>
        <table class="rec-table">
            <thead>
                <tr>
                    <th style="width:30%">Programme</th>
                    <th style="width:25%">Organisation</th>
                    <th style="width:20%">Overlap Type</th>
                    <th style="width:25%">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($note->recommendations as $rec)
                <tr>
                    <td>
                        @if($rec->programmeEntry)
                            #{{ $rec->programmeEntry->id }} &mdash; {{ $rec->programmeEntry->programme_name }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $rec->organisation_name ?? $rec->programmeEntry?->organisation?->name ?? 'N/A' }}</td>
                    <td>{{ $rec->type }}</td>
                    <td>{{ $rec->relational }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($note->section_gaps)
    <div class="section">
        <div class="section-title">C &middot; Gaps &amp; Coverage Analysis</div>
        <div class="section-body">{{ $note->section_gaps }}</div>
    </div>
    @endif

    @if($note->section_coordinators_notes)
    <div class="section">
        <div class="section-title">D &middot; Coordinator Notes</div>
        <div class="section-body">{{ $note->section_coordinators_notes }}</div>
    </div>
    @endif

    <div class="footer">
        <p>NEP Programme System &mdash; Confidential Advisory Note</p>
        <p>Generated on {{ $generatedAt }}</p>
    </div>

</body>
</html>
