<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Appointment</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 32px 40px 24px; border-bottom: 1px solid #e5e7eb;">
                            <span style="font-size: 24px; font-weight: 700; color: #1f2937;">zapmed<span style="color: #10b981;">.</span></span>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; font-weight: 600; color: #1f2937;">New Appointment Booked</h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #4b5563;">
                                A new appointment has been booked with you. Here are the details:
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f9fafb; border-radius: 6px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Patient</p>
                                        <p style="margin: 0 0 16px; font-size: 15px; color: #1f2937;">{{ $appointment->patient->name }}</p>
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Date</p>
                                        <p style="margin: 0 0 16px; font-size: 15px; color: #1f2937;">{{ $appointment->appointment_date->format('d M Y') }}</p>
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Time</p>
                                        <p style="margin: 0 0 16px; font-size: 15px; color: #1f2937;">{{ $appointment->start_time }}</p>
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Type</p>
                                        <p style="margin: 0 0 16px; font-size: 15px; color: #1f2937;">{{ $appointment->type_label }}</p>
                                        @if($appointment->reason)
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Reason</p>
                                        <p style="margin: 0; font-size: 15px; color: #1f2937;">{{ $appointment->reason }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <a href="{{ url('/doctor/appointments') }}" style="display: inline-block; padding: 12px 28px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;">View Schedule</a>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; border-top: 1px solid #e5e7eb; background-color: #f9fafb;">
                            <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">Zapmed (Pty) Ltd | support@zapmed.co.za</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
