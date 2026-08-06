<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Certificate - {{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}</title>
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
            font-size: 16px;
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
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .section-content {
            font-size: 12px;
            line-height: 1.8;
            color: #1a1a1a;
        }
        .section-content strong {
            color: #000;
        }
        .info-box {
            padding: 10px 12px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-top: 8px;
        }
        .validity-section {
            margin: 20px 0;
            padding: 10px 12px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
        }
        .validity-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #16a34a;
            margin-bottom: 3px;
        }
        .validity-value {
            font-size: 12px;
            font-weight: 600;
            color: #166534;
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
                    <div class="doc-type">MEDICAL CERTIFICATE</div>
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

    <!-- Patient & Examination Statement -->
    <div class="section">
        <div class="section-title">Statement of Examination</div>
        <div class="section-content">
            <p>I, <strong>Dr {{ $consultation->doctor->first_name }} {{ $consultation->doctor->last_name }}</strong>, hereby certify that I examined <strong>{{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}</strong>@if($consultation->patient->id_number) (ID: {{ $consultation->patient->id_number }})@endif on <strong>{{ $consultation->started_at ? $consultation->started_at->format('d F Y') : 'N/A' }}</strong> via the Zapmed telemedicine platform.</p>
        </div>
    </div>

    <!-- Findings -->
    @if($findings)
    <div class="section">
        <div class="section-title">Findings</div>
        <div class="info-box">
            <div class="section-content">{{ $findings }}</div>
        </div>
    </div>
    @endif

    <!-- Recommendations -->
    @if($recommendations)
    <div class="section">
        <div class="section-title">Recommendations</div>
        <div class="info-box">
            <div class="section-content">{{ $recommendations }}</div>
        </div>
    </div>
    @endif

    <!-- Diagnosis (if recorded) -->
    @if($consultation->diagnosis)
    <div class="section">
        <div class="section-title">Diagnosis</div>
        <div class="info-box">
            <div class="section-content">{{ $consultation->diagnosis }}@if($consultation->icd10_code) (ICD-10: {{ $consultation->icd10_code }})@endif</div>
        </div>
    </div>
    @endif

    <!-- Validity Period -->
    <div class="validity-section">
        <div class="validity-label">Certificate Validity Period</div>
        <div class="validity-value">{{ \Carbon\Carbon::parse($validFrom)->format('d F Y') }} &mdash; {{ \Carbon\Carbon::parse($validTo)->format('d F Y') }}</div>
    </div>

    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-line"></div>
        <div class="signature-name">Dr {{ $consultation->doctor->first_name }} {{ $consultation->doctor->last_name }}</div>
        <div class="signature-detail">HPCSA: {{ $consultation->doctor->doctorProfile->hpcsa_number ?? 'N/A' }}</div>
        <div class="signature-detail">
            Digitally signed via Zapmed on {{ now()->format('d F Y') }}
        </div>

        <div class="date-line">
            Date: {{ now()->format('d F Y') }}
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-disclaimer">
            This medical certificate was generated electronically via the Zapmed telemedicine platform and is valid without a physical signature per HPCSA guidelines for telemedicine.
        </div>
        <div class="footer-company">
            Zapmed (Pty) Ltd | www.zapmed.co.za | support@zapmed.co.za
        </div>
    </div>
</body>
</html>
