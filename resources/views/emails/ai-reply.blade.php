<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $organizationName }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f6f8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6f8;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                <tr>
                    <td style="padding:24px 28px 8px;border-bottom:1px solid #eef0f3;">
                        <div style="font-size:15px;font-weight:600;color:#111827;">{{ $organizationName }}</div>
                        <div style="font-size:13px;color:#6b7280;margin-top:2px;">Service client</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 28px;font-size:15px;line-height:1.6;color:#1f2937;white-space:pre-line;">{{ $body }}</td>
                </tr>
                <tr>
                    <td style="padding:16px 28px 24px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                        Répondez directement à cet email pour poursuivre l'échange.
                        <br>Référence : #{{ $conversationId }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
