{{--
    A3 (v1.7.6) — Admin &rarr; Email "Send test email" (HTML part).
    Same paper-world shell as the reset mail: ink header bar, paper card,
    inline CSS only.

    @var string $mailer
    @var string $host
    @var int    $port
    @var string $siteName
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }} — test email</title>
</head>
<body style="margin:0;padding:0;background:#f2f1ec;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#f2f1ec;padding:24px 12px;font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:560px;background:#fafaf7;border-radius:16px;overflow:hidden;border:1px solid #e6e5df;">
                <tr>
                    <td style="background:#171715;padding:18px 28px;">
                        <span style="color:#f5c518;font-size:15px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">{{ $siteName }}</span>
                        <span style="color:#8a8981;font-size:12px;letter-spacing:0.04em;">&nbsp;·&nbsp;Test</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px 28px 8px 28px;">
                        <h1 style="margin:0 0 12px 0;color:#171715;font-size:20px;line-height:1.3;">The mail rail is alive</h1>
                        <p style="margin:0;color:#4a4942;font-size:15px;line-height:1.6;">
                            This message was sent to your own address from Admin &rarr; Email. Nothing else
                            depends on it; it is a probe.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 30px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="border:1px solid #e6e5df;border-radius:10px;background:#f2f1ec;">
                            <tr>
                                <td style="padding:12px 14px;color:#6b6a63;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;">Mailer</td>
                                <td style="padding:12px 14px;color:#171715;font-size:13px;font-weight:700;text-align:right;">{{ $mailer }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 14px 12px;color:#6b6a63;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;">Host</td>
                                <td style="padding:0 14px 12px;color:#171715;font-size:13px;text-align:right;">{{ $host !== '' ? $host : 'local sendmail' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 14px 12px;color:#6b6a63;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;">Port</td>
                                <td style="padding:0 14px 12px;color:#171715;font-size:13px;text-align:right;">{{ $port }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #e6e5df;padding:14px 28px;color:#8a8981;font-size:11px;">
                        {{ $siteName }} — premium AI prompts, Nepal.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>