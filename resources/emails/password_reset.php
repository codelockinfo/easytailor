<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($appName ?? 'TailorPro'); ?> Password Reset</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f6fa;font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(17, 24, 39, 0.12);">
                    <tr>
                        <td align="center" style="padding:36px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                            <img src="<?= htmlspecialchars($logoUrl ?? ''); ?>" alt="<?= htmlspecialchars($appName ?? 'TailorPro'); ?>" style="max-width:160px;height:auto;margin-bottom:18px;">
                            <h2 style="margin:0;color:#fff;font-size:26px;line-height:1.3;">Password Reset Request</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px;color:#2d3748;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Hi <?= htmlspecialchars($ownerName ?? 'there'); ?>,
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                We received a request to reset the password for your <?= htmlspecialchars($appName ?? 'TailorPro'); ?> account.
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Enter the verification code below or click the button to choose a new password.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="padding:18px;background-color:#f7f8ff;border:2px dashed #667eea;border-radius:12px;">
                                        <span style="display:inline-block;font-size:30px;letter-spacing:10px;font-weight:700;color:#4c51bf;">
                                            <?= htmlspecialchars($code ?? ''); ?>
                                        </span>
                                        <p style="margin:12px 0 0;font-size:13px;color:#718096;">Code expires in 15 minutes</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="text-align:center;margin:28px 0;">
                                <a href="<?= htmlspecialchars($resetUrl ?? '#'); ?>" style="display:inline-block;padding:16px 32px;background:linear-gradient(135deg,#e53e3e 0%,#f56565 100%);color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
                                    Reset Password
                                </a>
                            </p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#4a5568;">
                                If you didn’t ask to reset your password, you can safely ignore this email. Your account will stay secure.
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#4a5568;">
                                Need assistance? Contact us at <a href="mailto:<?= htmlspecialchars($supportEmail ?? 'support@example.com'); ?>" style="color:#667eea;text-decoration:none;"><?= htmlspecialchars($supportEmail ?? 'support@example.com'); ?></a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px 40px;border-top:1px solid #edf2f7;color:#718096;font-size:13px;text-align:center;">
                            <p style="margin:0 0 8px;">Stay stitched & secure,<br><?= htmlspecialchars($appName ?? 'TailorPro'); ?> Team</p>
                            <p style="margin:0;font-size:12px;color:#a0aec0;">
                                &copy; <?= date('Y'); ?> <?= htmlspecialchars($appName ?? 'TailorPro'); ?>. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>



