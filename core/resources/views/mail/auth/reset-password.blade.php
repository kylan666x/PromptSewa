{{--
    A1 (v1.7.6) — the branded reset email.

    Email clients ignore <style> blocks at unpredictable rates, so every
    rule here is INLINE and the layout is table-based. The palette is the
    paper-world token set (ink #171715, saffron #f5c518, paper #fafaf7,
    creak #6b6a63) so the mail reads as PromptSewa in Gmail, Outlook and
    Apple Mail alike. A plain-text alternative ships alongside it
    (reset-password-text.blade.php) — this file is never the only copy.

    @var string $url      the working reset URL (token + email)
    @var int    $minutes  link lifetime, from config('auth.passwords.users.expire')
    @var string $siteName
    @var string $name     the account's display name
    @var string|null $sikkaMonoUrl  S9: the mono Sikka mark for the header (null = none)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your {{ $siteName }} password</title>
</head>
<body style="margin:0;padding:0;background:#f2f1ec;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#f2f1ec;padding:24px 12px;font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px;background:#fafaf7;border-radius:16px;overflow:hidden;border:1px solid #e6e5df;">

                {{-- ink header bar — the brand mark; S9 (v1.8.0): the Sikka
                     unit mark rides the MONO variant here (color would be a
                     different asset on an ink ground), and renders nothing
                     when unset — no broken image, ever. --}}
                <tr>
                    <td style="background:#171715;padding:18px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td>
                                    <span style="color:#f5c518;font-size:15px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">{{ $siteName }}</span>
                                    <span style="color:#8a8981;font-size:12px;letter-spacing:0.04em;">&nbsp;·&nbsp;Password reset</span>
                                </td>
                                @if (! empty($sikkaMonoUrl))
                                    <td align="right" style="white-space:nowrap;">
                                        <img src="{{ $sikkaMonoUrl }}" alt="Sikka" width="16" height="16"
                                             style="vertical-align:middle;height:16px;width:16px;object-fit:contain;">
                                        <span style="color:#8a8981;font-size:11px;letter-spacing:0.06em;text-transform:uppercase;">&nbsp;Sikka</span>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 28px 8px 28px;">
                        <h1 style="margin:0 0 12px 0;color:#171715;font-size:22px;line-height:1.3;font-weight:700;">
                            Reset your password
                        </h1>
                        <p style="margin:0;color:#4a4942;font-size:15px;line-height:1.6;">
                            Hi {{ $name }}, we received a request to reset the password on your
                            {{ $siteName }} account. Press the button below to choose a new one.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:24px 28px 4px 28px;">
                        <a href="{{ $url }}"
                           style="display:inline-block;background:#f5c518;color:#171715;border:1px solid #e0a800;border-radius:10px;padding:13px 28px;font-size:15px;font-weight:700;text-decoration:none;">
                            Choose a new password
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 28px 0 28px;">
                        <p style="margin:0;color:#6b6a63;font-size:12px;line-height:1.6;">
                            This link works once and expires in {{ $minutes }} minutes. If the button
                            does nothing, copy this address into your browser:
                        </p>
                        <p style="margin:8px 0 0 0;color:#171715;font-size:12px;line-height:1.6;word-break:break-all;">
                            {{ $url }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 28px 32px 28px;">
                        <p style="margin:0;color:#6b6a63;font-size:13px;line-height:1.6;">
                            Did not ask for this? Nothing has changed on your account — ignore this
                            email and the old password stays active. If this keeps happening, reply
                            to your support address.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="border-top:1px solid #e6e5df;padding:14px 28px;color:#8a8981;font-size:11px;letter-spacing:0.03em;">
                        {{ $siteName }} — premium AI prompts, Nepal.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>