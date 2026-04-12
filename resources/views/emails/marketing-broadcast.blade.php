<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;">
                    <tr>
                        <td style="padding:0 0 20px 0;text-align:center;">
                            <span style="display:inline-block;font-size:13px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#a5b4fc;">
                                {{ $appName }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#6366f1 100%);border-radius:16px 16px 0 0;padding:28px 28px 22px 28px;text-align:center;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">
                                {{ $appName }}
                            </h1>
                            <p style="margin:10px 0 0 0;font-size:14px;line-height:1.5;color:rgba(255,255,255,0.88);">
                                AI storybooks for kids &amp; families
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border-radius:0 0 16px 16px;padding:28px 28px 24px 28px;border:1px solid #e2e8f0;border-top:none;">
                            <div style="font-size:15px;line-height:1.65;color:#334155;">
                                {!! $htmlBody !!}
                            </div>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:28px;width:100%;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $appUrl }}" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 24px;border-radius:9999px;box-shadow:0 4px 14px rgba(79,70,229,0.35);">
                                            Open {{ $appName }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 8px 0 8px;text-align:center;font-size:12px;line-height:1.5;color:#94a3b8;">
                            You are receiving this message as part of a product announcement from {{ $appName }}.
                            <br>
                            <a href="{{ $appUrl }}" style="color:#a5b4fc;text-decoration:none;">Visit the app</a>
                            &nbsp;·&nbsp;
                            <span style="color:#64748b;">{{ parse_url($appUrl, PHP_URL_HOST) ?? 'dreamforge' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
