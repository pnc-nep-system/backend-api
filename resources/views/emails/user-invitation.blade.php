<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the NEP System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 30px;
        }
        .header {
            background-color: #2c3e50;
            color: #fff;
            padding: 20px;
            border-radius: 5px 5px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .credentials {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .credential-item {
            margin: 15px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-left: 4px solid #3498db;
        }
        .credential-label {
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        .credential-value {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            color: #2c3e50;
            margin-top: 5px;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: #fff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .notice strong {
            color: #856404;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to the NEP System</h1>
        </div>

        <p>Hello {{ $userName }},</p>

        <p>Your NEP System account has been created successfully. You can now access the system using the credentials below.</p>

        <div class="credentials">
            <h2>Your Login Credentials</h2>

            <div class="credential-item">
                <div class="credential-label">Email Address:</div>
                <div class="credential-value">{{ $userEmail }}</div>
            </div>

            <div class="credential-item">
                <div class="credential-label">Default Password:</div>
                <div class="credential-value">{{ $defaultPassword }}</div>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" class="button">Login to NEP System</a>
        </div>

        <p>Or copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; color: #3498db;">{{ $loginUrl }}</p>

        <div class="notice">
            <strong>⚠️ Important Security Notice:</strong>
            <p>For security reasons, please change your password immediately after your first login. Do not share your credentials with anyone.</p>
        </div>

        <p>If you have any questions or need assistance, please contact the NEP Administrator.</p>

        <div class="footer">
            <p>Best regards,<br>NEP System</p>
        </div>
    </div>
</body>
</html>