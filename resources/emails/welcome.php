<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to <?= htmlspecialchars($appName ?? 'TailorPro'); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f5f6fa;font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(17, 24, 39, 0.12);">
                    <tr>
                        <td align="center" style="padding:40px 40px 24px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                            <img src="<?= htmlspecialchars($logoUrl ?? ''); ?>" alt="<?= htmlspecialchars($appName ?? 'TailorPro'); ?>" style="max-width:180px;height:auto;margin-bottom:20px;">
                            <h1 style="margin:0;color:#fff;font-size:28px;line-height:1.3;">Welcome to <?= htmlspecialchars($appName ?? 'TailorPro'); ?>!</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px 12px;color:#2d3748;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Hi <?= htmlspecialchars($ownerName ?? 'there'); ?>,
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Thanks for registering <strong><?= htmlspecialchars($companyName ?? 'your tailor shop'); ?></strong> with <?= htmlspecialchars($appName ?? 'TailorPro'); ?>. Your dashboard is ready—track orders, manage customers, assign tailors, and follow payments in one place.
                            </p>
                            <?php if (!empty($username)): ?>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                <strong>Username:</strong> <?= htmlspecialchars($username); ?>
                            </p>
                            <?php endif; ?>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">
                                Click the button below to start managing your tailor shop right away.
                            </p>
                            <p style="text-align:center;margin:32px 0; color: #ffffff;">
                                <a href="<?= htmlspecialchars($manageUrl ?? '#'); ?>" style="display:inline-block;padding:16px 32px;background:linear-gradient(135deg,#4c51bf 0%,#667eea 100%);color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
                                    Manage Your Tailor
                                </a>
                            </p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#4a5568;">
                                Need a hand? Reply to this email or contact us at <a href="mailto:<?= htmlspecialchars($supportEmail ?? 'support@example.com'); ?>" style="color:#667eea;text-decoration:none;"><?= htmlspecialchars($supportEmail ?? 'support@example.com'); ?></a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px 40px;border-top:1px solid #edf2f7;color:#718096;font-size:13px;text-align:center;">
                            <p style="margin:0 0 8px;">Cheers,<br><?= htmlspecialchars($appName ?? 'TailorPro'); ?> Team</p>
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



