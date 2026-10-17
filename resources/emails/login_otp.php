<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($appName ?? 'TailorPro'); ?> Login Verification Code</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f6fa;font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(17, 24, 39, 0.12);">
                    <tr>
                        <td align="center" style="padding:36px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                            <img src="<?= htmlspecialchars($logoUrl ?? ''); ?>" alt="<?= htmlspecialchars($appName ?? 'TailorPro'); ?>" style="max-width:160px;height:auto;margin-bottom:18px;">
                            <h2 style="margin:0;color:#fff;font-size:26px;line-height:1.3;">Login Verification Code</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px;color:#2d3748;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Hi <?= htmlspecialchars($userName ?? 'there'); ?>,
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                You recently requested to sign in to your <strong><?= htmlspecialchars($appName ?? 'TailorPro'); ?></strong> account using Email OTP.
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Please use the following 6-digit one-time verification code to complete your login:
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="padding:22px;background-color:#f7f8ff;border:2px dashed #667eea;border-radius:12px;">
                                        <span style="display:inline-block;font-size:36px;letter-spacing:12px;font-weight:700;color:#4c51bf;">
                                            <?= htmlspecialchars($code ?? ''); ?>
                                        </span>
                                        <p style="margin:12px 0 0;font-size:13px;color:#718096;font-weight:500;">
                                            This code will expire in <strong>10 minutes</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <div style="background-color:#fff8e6;border-left:4px solid #f6ad55;padding:14px 16px;border-radius:6px;margin:20px 0;">
                                <p style="margin:0;font-size:14px;color:#744210;line-height:1.5;">
                                    <strong>Security Notice:</strong> Never share this code with anyone. TailorPro staff will never ask for your verification code.
                                </p>
                            </div>
                            <p style="margin:16px 0 0;font-size:14px;line-height:1.6;color:#718096;">
                                If you did not make this request, you can safely ignore this email. No one can log in without this code.
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
