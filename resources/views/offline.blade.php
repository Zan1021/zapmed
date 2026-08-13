<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline - Zapmed</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f9fafb; padding: 24px; }
        .container { text-align: center; max-width: 400px; }
        .icon { width: 64px; height: 64px; margin: 0 auto 24px; color: #9ca3af; }
        h1 { font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 8px; }
        p { font-size: 14px; color: #6b7280; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: #059669; color: #fff; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .btn:hover { background: #047857; }
    </style>
</head>
<body>
    <div class="container">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M12 12v.01M8.464 15.536a5 5 0 010-7.072M15.536 8.464a5 5 0 010 7.072"/>
        </svg>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection. Please check your connection and try again.</p>
        <a href="/" class="btn" onclick="window.location.reload(); return false;">Try Again</a>
    </div>
</body>
</html>
