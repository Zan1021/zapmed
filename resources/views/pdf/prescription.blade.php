<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription {{ $prescription->reference }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.5;
            padding: 40px;
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-table {
            width: 100%;
        }
        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .brand-dot {
            color: #64cc0f;
        }
        .doc-type {
            font-size: 16px;
            font-weight: bold;
            color: #374151;
            text-align: right;
        }
        .reference {
            font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
            font-size: 10px;
            color: #6b7280;
            margin-top: 5px;
        }
        .info-section {
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .info-section h4 {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .info-grid {
            width: 100%;
        }
        .info-grid td {
            padding: 2px 10px 2px 0;
            font-size: 11px;
            vertical-align: top;
        }
        .info-label {
            color: #6b7280;
            font-size: 10px;
            width: 120px;
        }
        .info-value {
            color: #1a1a1a;
            font-weight: 500;
        }
        .diagnosis-section {
            margin-bottom: 15px;
            padding: 10px 12px;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
        }
        .diagnosis-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #3b82f6;
            margin-bottom: 3px;
        }
        .diagnosis-value {
            font-size: 12px;
            font-weight: 600;
            color: #1e40af;
        }
        .chronic-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 15px;
        }
        .med-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }
        .med-table thead th {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #374151;
            font-weight: 600;
        }
        .med-table tbody td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            vertical-align: top;
        }
        .med-name {
            font-weight: 600;
            color: #1a1a1a;
        }
        .med-detail {
            font-size: 9px;
            color: #6b7280;
        }
        .daw-badge {
            display: inline-block;
            padding: 1px 4px;
            background-color: #fee2e2;
            color: #dc2626;
            font-size: 8px;
            font-weight: bold;
            border-radius: 2px;
            margin-top: 2px;
        }
        .notes-section {
            margin-bottom: 20px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .notes-section h4 {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .notes-section p {
            font-size: 11px;
            color: #374151;
        }
        .signature-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        .signature-name {
            font-size: 12px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .signature-detail {
            font-size: 10px;
            color: #6b7280;
            margin-top: 2px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .footer-disclaimer {
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
            margin-bottom: 8px;
        }
        .footer-company {
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 50%;">
                    <div class="brand">zapmed<span class="brand-dot">.</span></div>
                    <div class="reference">{{ $prescription->reference }} | Issued: {{ $prescription->signed_at ? $prescription->signed_at->format('d M Y') : now()->format('d M Y') }}</div>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <div class="doc-type">PRESCRIPTION</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Doctor Info -->
    <div class="info-section">
        <h4>Prescribing Practitioner</h4>
        <table class="info-grid">
            <tr>
                <td class="info-label">Name:</td>
                <td class="info-value">Dr {{ $prescription->doctor->first_name }} {{ $prescription->doctor->last_name }}</td>
                <td class="info-label">HPCSA No:</td>
                <td class="info-value">{{ $prescription->doctor->doctorProfile->hpcsa_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-label">Qualification:</td>
                <td class="info-value" colspan="3">{{ $prescription->doctor->doctorProfile->qualification ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Patient Info -->
    <div class="info-section">
        <h4>Patient Details</h4>
        <table class="info-grid">
            <tr>
                <td class="info-label">Name:</td>
                <td class="info-value">{{ $prescription->patient->first_name }} {{ $prescription->patient->last_name }}</td>
                <td class="info-label">Date of Birth:</td>
                <td class="info-value">{{ $prescription->patient->date_of_birth ? $prescription->patient->date_of_birth->format('d M Y') : 'N/A' }}</td>
            </tr>
            @if($prescription->patient->id_number)
            <tr>
                <td class="info-label">ID Number:</td>
                <td class="info-value" colspan="3">{{ $prescription->patient->id_number }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Diagnosis -->
    @if($prescription->diagnosis)
    <div class="diagnosis-section">
        <div class="diagnosis-label">Diagnosis</div>
        <div class="diagnosis-value">{{ $prescription->diagnosis }}</div>
    </div>
    @endif

    <!-- Chronic Badge -->
    @if($prescription->is_chronic)
    <div class="chronic-badge">
        CHRONIC PRESCRIPTION &mdash; {{ $prescription->repeats }} {{ $prescription->repeats === 1 ? 'repeat' : 'repeats' }} authorized
    </div>
    @endif

    <!-- Medications Table -->
    <table class="med-table">
        <thead>
            <tr>
                <th style="width: 20px;">#</th>
                <th style="width: 30%;">Medication</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Route</th>
                <th>Duration</th>
                <th>Qty</th>
                <th style="width: 20%;">Instructions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescription->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <div class="med-name">{{ $item->medication_name }}</div>
                    <div class="med-detail">{{ $item->strength }} &middot; {{ $item->form }}</div>
                    @if(!$item->substitution_allowed)
                    <span class="daw-badge">DAW</span>
                    @endif
                </td>
                <td>{{ $item->dosage }}</td>
                <td>{{ $item->frequency }}</td>
                <td>{{ $item->route }}</td>
                <td>{{ $item->duration_days ? $item->duration_days . ' days' : '-' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->instructions ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pharmacist Notes -->
    @if($prescription->notes)
    <div class="notes-section">
        <h4>Notes for Pharmacist</h4>
        <p>{{ $prescription->notes }}</p>
    </div>
    @endif

    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-name">Dr {{ $prescription->doctor->first_name }} {{ $prescription->doctor->last_name }}</div>
        <div class="signature-detail">
            Digitally signed via Zapmed on {{ $prescription->signed_at ? $prescription->signed_at->format('d F Y \a\t H:i') : now()->format('d F Y \a\t H:i') }}
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-disclaimer">
            This prescription was generated electronically and is valid without a physical signature per HPCSA guidelines for telemedicine.
        </div>
        <div class="footer-company">
            Zapmed (Pty) Ltd | www.zapmed.co.za | support@zapmed.co.za
        </div>
    </div>
</body>
</html>
