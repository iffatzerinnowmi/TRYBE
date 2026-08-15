<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Paid study access unlocked</title>
</head>
<body style="margin:0;padding:0;background:#f5f4f2;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e7e4df;">
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <div style="font-size:32px;line-height:1;margin-bottom:12px;">🔓</div>
                            <h1 style="margin:0 0 12px;font-size:20px;color:#1a1a1a;">Paid study access unlocked</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3f3f3f;">
                                Hi {{ $user->name }},
                            </p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3f3f3f;">
                                You've completed <strong>{{ $completed }} of {{ $target }}</strong> free studies on TRYBE.
                                Your account has been automatically upgraded — you can now apply to
                                <strong>paid studies</strong> in addition to volunteer ones.
                            </p>
                            <p style="margin:24px 0;">
                                <a href="{{ url('/studies') }}"
                                   style="display:inline-block;background:#5b2a86;color:#ffffff;text-decoration:none;
                                          padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;">
                                    Browse paid studies
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#8a8a8a;">
                                Thanks for being an active participant on TRYBE.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
