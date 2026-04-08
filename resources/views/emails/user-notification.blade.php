<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 24px;background:#4f46e5;color:#ffffff;font-size:16px;font-weight:700;">
                            {{ $appName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 14px 0;font-size:24px;line-height:1.3;color:#0f172a;">{{ $headline }}</h1>

                            @foreach($lines as $line)
                                <p style="margin:0 0 10px 0;font-size:15px;line-height:1.6;color:#334155;">{{ $line }}</p>
                            @endforeach

                            @if(!empty($ctaLabel) && !empty($ctaUrl))
                                <p style="margin:20px 0 0 0;">
                                    <a href="{{ $ctaUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;padding:10px 16px;border-radius:8px;">
                                        {{ $ctaLabel }}
                                    </a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            You received this email because of activity on your {{ $appName }} account.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

