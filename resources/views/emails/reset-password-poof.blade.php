<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f1ff; font-family:Arial, Helvetica, sans-serif; color:#24164a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f1ff; margin:0; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px; background:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 12px 40px rgba(71, 44, 163, 0.12);">
                <tr>
                    <td style="background:linear-gradient(135deg, #6d28d9 0%, #a855f7 100%); padding:32px 40px; text-align:center; color:#ffffff;">
                        <div style="font-size:13px; letter-spacing:3px; text-transform:uppercase; opacity:0.92; margin-bottom:12px;">POOF</div>
                        <h1 style="margin:0; font-size:28px; line-height:1.25; font-weight:700;">Скидання пароля</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:40px;">
                        <p style="margin:0 0 16px; font-size:16px; line-height:1.65;">Вітаємо{{ isset($user) && filled($user->name ?? null) ? ', ' . e($user->name) : '' }}!</p>
                        <p style="margin:0 0 16px; font-size:16px; line-height:1.65;">Ми отримали запит на скидання пароля для вашого акаунта в POOF.</p>
                        <p style="margin:0 0 28px; font-size:16px; line-height:1.65;">Натисніть кнопку нижче, щоб створити новий пароль. Посилання дійсне протягом <strong>{{ $expireMinutes }} хвилин</strong>.</p>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 28px;">
                            <tr>
                                <td align="center" style="border-radius:999px; background:#6d28d9;">
                                    <a href="{{ $resetUrl }}" style="display:inline-block; padding:16px 28px; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px;">Скинути пароль</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px; font-size:14px; line-height:1.65; color:#5b4b8a; word-break:break-all;">Якщо кнопка не працює, скопіюйте та вставте це посилання у браузер:<br><a href="{{ $resetUrl }}" style="color:#6d28d9; text-decoration:none;">{{ $resetUrl }}</a></p>

                        <p style="margin:0; font-size:15px; line-height:1.65; color:#5b4b8a;">Якщо ви не запитували скидання пароля, просто проігноруйте цей лист.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
