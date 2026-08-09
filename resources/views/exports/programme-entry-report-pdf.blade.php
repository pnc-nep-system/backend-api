<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Programme Report - {{ $entry->name ?? 'Untitled Programme' }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0F5A4D;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .logo-text {
            font-size: 18px;
            font-weight: bold;
            color: #0F5A4D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sub-logo {
            font-size: 10px;
            color: #64748b;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .badge-verified {
            background-color: #dcfce7;
            color: #15803d;
        }
        .badge-unverified {
            background-color: #fef3c7;
            color: #b45309;
        }
        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0F5A4D;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            vertical-align: top;
            padding: 4px 6px;
        }
        .label {
            font-weight: bold;
            color: #475569;
            width: 30%;
        }
        .value {
            color: #0f172a;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .table th, .table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-size: 10.5px;
        }
        .table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        .chip {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9.5px;
            margin-right: 4px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="grid">
            <tr>
                <td>
                    <div class="logo-text">NEP CAMBODIA</div>
                    <div class="sub-logo">NGO Education Partnership — Self-Service Programme Report</div>
                </td>
                <td style="text-align: right;">
                    <span class="badge {{ $entry->is_unverified ? 'badge-unverified' : 'badge-verified' }}">
                        {{ $entry->is_unverified ? 'Unverified' : 'Verified' }}
                    </span>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 4px;">
                        Report Date: {{ now()->format('d M Y, H:i') }}
                    </div>
                </td>
            </tr>
        </table>
        <div class="report-title">{{ $entry->name ?? 'Untitled Programme' }}</div>
        <div style="color: #64748b; font-size: 11px;">
            Organisation: <strong>{{ $entry->organisation->name ?? 'N/A' }}</strong>
        </div>
    </div>

    <!-- Section 1: Basic Information -->
    <div class="section">
        <div class="section-title">1. Programme Information & Budget</div>
        <div class="card">
            <table class="grid">
                <tr>
                    <td class="label">Implementation Period:</td>
                    <td class="value">{{ $entry->start_year ?? 'N/A' }} – {{ $entry->end_year ?? 'Ongoing' }}</td>
                </tr>
                <tr>
                    <td class="label">Implementation Status:</td>
                    <td class="value">{{ ucfirst($entry->status ?? 'Active') }}</td>
                </tr>
                <tr>
                    <td class="label">Total Annual Budget:</td>
                    <td class="value">
                        @if($entry->annual_budget_usd)
                            ${{ number_format($entry->annual_budget_usd) }} USD
                        @elseif($entry->budget_band)
                            {{ $entry->budget_band }}
                        @else
                            Not specified
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Description / Summary:</td>
                    <td class="value">{{ $entry->description ?? 'No description provided.' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Section 2: Programme Activities -->
    <div class="section">
        <div class="section-title">2. Programme Activities & Taxonomy</div>
        @if($entry->activities && count($entry->activities) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Role</th>
                        <th style="width: 25%;">Category</th>
                        <th style="width: 35%;">Sub-Category / Activity</th>
                        <th style="width: 25%;">Education Levels</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->activities as $act)
                        <tr>
                            <td>
                                <strong>{{ $act->importance === 'primary' || $act->importance === 'core' ? 'Core' : 'Supporting' }}</strong>
                            </td>
                            <td>{{ $act->category->name ?? $act->activity_code }}</td>
                            <td>
                                {{ $act->subCategory->name ?? $act->activity_code }}
                                @if($act->other_text)
                                    <div style="font-size: 9px; color: #475569;">Note: {{ $act->other_text }}</div>
                                @endif
                            </td>
                            <td>
                                @if(is_array($act->education_levels))
                                    {{ implode(', ', $act->education_levels) }}
                                @else
                                    {{ $act->education_levels ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="card" style="color: #64748b;">No activities registered for this programme entry.</div>
        @endif
    </div>

    <!-- Section 3: Geographic Coverage -->
    <div class="section">
        <div class="section-title">3. Geographic Coverage</div>
        <div class="card">
            @if($entry->locations && count($entry->locations) > 0)
                <div style="margin-bottom: 6px;">
                    <strong>Covered Provinces:</strong>
                    @php
                        $provinces = $entry->locations->map(fn($loc) => $loc->province_name ?? $loc->name)->unique()->filter()->values();
                    @endphp
                    {{ $provinces->join(', ') }}
                </div>
            @else
                <div style="color: #64748b;">Geographic coverage not specified.</div>
            @endif
        </div>
    </div>

    <!-- Section 4: Government Agreements -->
    <div class="section">
        <div class="section-title">4. Government Agreements & Counterparts</div>
        @if($entry->governmentAgreements && count($entry->governmentAgreements) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Agreement Name / MoU</th>
                        <th>Counterpart Level</th>
                        <th>Signatory Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->governmentAgreements as $agr)
                        <tr>
                            <td>{{ $agr->name ?? $agr->agreement_name ?? '—' }}</td>
                            <td>{{ $agr->counterpartStatus->name ?? $agr->counterpart_level ?? '—' }}</td>
                            <td>{{ $agr->signatory_entity ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="card" style="color: #64748b;">No formal government agreements recorded.</div>
        @endif
    </div>

    <!-- Section 5: Keywords -->
    @if($entry->keywords && count($entry->keywords) > 0)
    <div class="section">
        <div class="section-title">5. Keywords & Key Focus Areas</div>
        <div class="card">
            @foreach($entry->keywords as $kw)
                <span class="chip">{{ $kw->keyword ?? $kw->name ?? $kw }}</span>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        Confidential — NGO Education Partnership (NEP) System • Generated for {{ $entry->organisation->name ?? 'Organisation Member' }}
    </div>
</body>
</html>
