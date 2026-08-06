<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sick Note - {{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            line-height: 1.6;
            padding: 50px;
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 30px;
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
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            text-align: right;
        }
        .doctor-section {
            margin-bottom: 25px;
            padding: 12px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .doctor-section h4 {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .doctor-name {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .doctor-detail {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }
        .body-text {
            font-size: 12px;
            line-height: 1.8;
            margin-bottom: 20px;
            color: #1a1a1a;
        }
        .body-text strong {
            color: #000;
        }
        .diagnosis-section {
            margin: 20px 0;
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
        .signature-section {
            margin-top: 50px;
            padding-top: 15px;
        }
        .signature-line {
            width: 250px;
            border-bottom: 1px solid #1a1a1a;
            margin-bottom: 5px;
            height: 40px;
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
        .date-line {
            margin-top: 20px;
            font-size: 11px;
            color: #6b7280;
        }
        .footer {
            margin-top: 50px;
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
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <div class="doc-type">MEDICAL CERTIFICATE (SICK NOTE)</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Doctor Info -->
    <div class="doctor-section">
        <h4>Practitioner Details</h4>
        <div class="doctor-name">Dr {{ $consultation->doctor->first_name }} {{ $consultation->doctor->last_name }}</div>
        <div class="doctor-detail">HPCSA No: {{ $consultation->doctor->doctorProfile->hpcsa_number ?? 'N/A' }}</div>
        <div class="doctor-detail">Qualification: {{ $consultation->doctor->doctorProfile->qualification ?? 'N/A' }}</div>
    </div>

    <!-- Body -->
    <div class="body-text">
        <p>This is to certify that <strong>{{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}</strong>@if($consultation->patient->id_number), ID: {{ $consultation->patient->id_number }}@endif, was examined by me on <strong>{{ $consultation->started_at ? $consultation->started_at->format('d F Y') : 'N/A' }}</strong> and is unfit for duties from <strong>{{ \Carbon\Carbon::parse($dateFrom)->format('d F Y') }}</strong> to <strong>{{ \Carbon\Carbon::parse($dateTo)->format('d F Y') }}</strong> (inclusive).</p>
    </div>

    <!-- Diagnosis (optional) -->
    @if($showDiagnosis && $consultation->diagnosis)
    <div class="diagnosis-section">
        <div class="diagnosis-label">Diagnosis</div>
        <div class="diagnosis-value">{{ $consultation->diagnosis }}</div>
    </div>
    @endif

    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-line"></div>
        <div class="signature-name">Dr {{ $consultation->doctor->first_name }} {{ $consultation->doctor->last_name }}</div>
        <div class="signature-detail">HPCSA: {{ $consultation->doctor->doctorProfile->hpcsa_number ?? 'N/A' }}</div>
        <div class="signature-detail">
            Digitally signed via Zapmed on {{ $consultation->completed_at ? $consultation->completed_at->format('d F Y') : now()->format('d F Y') }}
        </div>

        <div class="date-line">
            Date: {{ now()->format('d F Y') }}
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-disclaimer">
            This certificate was generated electronically via the Zapmed telemedicine platform and is valid without a physical signature per HPCSA guidelines for telemedicine.
        </div>
        <div class="footer-company">
            Zapmed (Pty) Ltd | www.zapmed.co.za | support@zapmed.co.za
        </div>
    </div>
</body>
</html>
