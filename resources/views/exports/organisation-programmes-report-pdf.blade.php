<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $organisation->name }} — All Programmes Report</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2.5px solid #0F5A4D;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-text {
            font-size: 18px;
            font-weight: bold;
            color: #0F5A4D;
            letter-spacing: 0.5px;
        }
        .sub-logo {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 10px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            vertical-align: top;
        }
        .kpi-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 16px;
        }
        .kpi-title {
            font-size: 11px;
            font-weight: bold;
            color: #0F5A4D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        .table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        .table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9.5px;
        }
        .table tr:nth-child(even) {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .badge-verified {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-unverified {
            background: #fef3c7;
            color: #b45309;
        }
        .chip {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            margin-right: 3px;
            margin-bottom: 3px;
        }
        .page-break {
            page-break-after: always;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="grid">
            <tr>
                <td>
                    <div class="logo-text">NEP CAMBODIA</div>
                    <div class="sub-logo">NGO Education Partnership — Organisation Programmes Summary Report</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 11px; font-weight: bold; color: #0F5A4D;">{{ $organisation->name }}</div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                        Report Date: {{ now()->format('d M Y, H:i') }}
                    </div>
                </td>
            </tr>
        </table>
        <div class="report-title">Consolidated Organisation Programmes Report</div>
    </div>

    <!-- Organisation Summary KPI Box -->
    <div class="kpi-box">
        <div class="kpi-title">Organisation Summary</div>
        <table class="grid">
            <tr>
                <td style="width: 33%;">
                    <div style="color: #64748b; font-size: 9px;">Organisation Name</div>
                    <div style="font-weight: bold; font-size: 11px;">{{ $organisation->name }}</div>
                </td>
                <td style="width: 33%;">
                    <div style="color: #64748b; font-size: 9px;">Total Submitted Programmes</div>
                    <div style="font-weight: bold; font-size: 11px; color: #0F5A4D;">{{ count($entries) }} Programmes</div>
                </td>
                <td style="width: 34%;">
                    <div style="color: #64748b; font-size: 9px;">Total Staffing (FTE)</div>
                    <div style="font-weight: bold; font-size: 11px;">{{ number_format($entries->sum('fte_staff'), 1) }} FTE</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Overview Table of All Programmes -->
    <div style="font-size: 11px; font-weight: bold; color: #0F5A4D; margin-bottom: 6px;">Programmes Overview</div>
    <table class="table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 26%;">Programme Name</th>
                <th style="width: 14%;">Period</th>
                <th style="width: 16%;">Budget Band</th>
                <th style="width: 25%;">Covered Locations</th>
                <th style="width: 15%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $index => $entry)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $entry->programme_name }}</strong>
                    </td>
                    <td>{{ $entry->start_year ?? 'N/A' }} – {{ $entry->ongoing ? 'Ongoing' : ($entry->end_year ?? 'N/A') }}</td>
                    <td>{{ $entry->budgetBand->band_name ?? 'Not specified' }}</td>
                    <td>
                        @php
                            $locs = $entry->locations->map(function($loc) {
                                $prov = $loc->province->province_name ?? $loc->province_name ?? $loc->country;
                                $dist = $loc->district->name ?? $loc->district->district_name ?? null;
                                if ($prov && $dist) {
                                    return "$prov ($dist)";
                                }
                                return $prov;
                            })->unique()->filter()->values();
                        @endphp
                        {{ $locs->count() ? $locs->join(', ') : 'Not specified' }}
                    </td>
                    <td>
                        <span class="badge {{ $entry->is_unverified ? 'badge-unverified' : 'badge-verified' }}">
                            {{ $entry->is_unverified ? 'Unverified' : 'Verified' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 12px;">No submitted programmes found for this organisation.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Detailed Programme Entries Section -->
    @foreach($entries as $index => $entry)
        <div style="margin-top: 20px;">
            <div style="background: #0F5A4D; color: #ffffff; padding: 6px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                Programme #{{ $index + 1 }}: {{ $entry->programme_name }}
            </div>
            
            <table class="grid" style="margin-top: 8px; margin-bottom: 8px;">
                <tr>
                    <td style="width: 50%; padding-right: 8px;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px;">
                            <div><strong>Implementation Period:</strong> {{ $entry->start_year ?? 'N/A' }} – {{ $entry->ongoing ? 'Ongoing' : ($entry->end_year ?? 'N/A') }}</div>
                            <div><strong>Annual Budget:</strong> {{ $entry->budgetBand->band_name ?? 'Not specified' }}</div>
                            <div><strong>Staff FTE:</strong> {{ $entry->fte_staff ? $entry->fte_staff . ' FTE' : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding-left: 8px;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px;">
                            <div><strong>Verification Status:</strong> {{ $entry->is_unverified ? 'Unverified' : 'Verified' }}</div>
                            <div><strong>Beneficiaries:</strong> Direct: {{ $entry->direct_beneficiaries ?? 0 }} · Indirect: {{ $entry->indirect_beneficiaries ?? 0 }}</div>
                            <div><strong>Agreements Count:</strong> {{ count($entry->governmentAgreements) }} formal agreements</div>
                        </div>
                    </td>
                </tr>
            </table>

            @if($entry->activities && count($entry->activities) > 0)
                <div style="font-weight: bold; font-size: 9.5px; color: #334155; margin-top: 4px; margin-bottom: 4px;">Registered Activities:</div>
                <table class="table" style="margin-top: 0; margin-bottom: 8px;">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Role</th>
                            <th style="width: 30%;">Category</th>
                            <th style="width: 55%;">Activity Item Title</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry->activities as $act)
                            <tr>
                                <td><strong>{{ $act->is_primary ? 'Core' : 'Supporting' }}</strong></td>
                                <td>{{ $act->activityItem->subcategory->category->label ?? $act->activityItem->subcategory->category->code ?? 'N/A' }}</td>
                                <td>
                                    <strong>{{ $act->activityItem->code ? '[' . $act->activityItem->code . '] ' : '' }}{{ $act->activityItem->label ?? 'N/A' }}</strong>
                                    @if($act->other_text)
                                        <span style="color: #64748b; font-size: 8.5px;">(Note: {{ $act->other_text }})</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="footer">
        Confidential — NGO Education Partnership (NEP) System • All Programmes Export for {{ $organisation->name }}
    </div>
</body>
</html>
