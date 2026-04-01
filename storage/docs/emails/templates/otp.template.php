<?php
/**
 * Email Template: OTP / Password Reset
 *
 * Sent when a user requests a password reset.
 *
 * @param array $vars {
 *   string $name            — User's display name (required)
 *   string $otp_code        — The 6-digit OTP (required)
 *   int    $expiry_minutes  — OTP validity in minutes (default 10)
 * }
 * @return string Full HTML email body
 */
function emailTemplateOtp(array $vars): string
{
    $name           = htmlspecialchars($vars['name']           ?? 'there', ENT_QUOTES, 'UTF-8');
    $otpCode        = htmlspecialchars($vars['otp_code']       ?? '------', ENT_QUOTES, 'UTF-8');
    $expiryMinutes  = (int) ($vars['expiry_minutes'] ?? 10);
    $year           = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Verification Code — CodeCanvas</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#000000;-webkit-font-smoothing:antialiased;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;width:100%;">
    <tr>
      <td align="center" style="padding: 80px 0;">
        
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #000000;">
          
          <tr>
            <td align="center" style="padding: 60px 40px;">
              
              <!-- Header -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center" style="padding-bottom: 40px; border-bottom: 1px solid #000000;">
                    <h1 style="margin:0; font-size: 20px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase;">
                      CodeCanvas
                    </h1>
                  </td>
                </tr>
              </table>

              <!-- Body Message -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center" style="padding-top: 50px; padding-bottom: 20px;">
                    <h2 style="margin:0; font-size: 32px; font-weight: 800; line-height: 1.2; letter-spacing: -0.5px;">
                      Verify It's You.
                    </h2>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding-bottom: 40px;">
                    <p style="margin:0; font-size: 16px; line-height: 1.6; color: #000000;">
                      Use the secure code below to complete your sign-in or password reset. This code will expire in <strong>{$expiryMinutes} minutes</strong>.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- OTP Box -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center">
                    <div style="background-color: #f5f5f5; border: 1px dashed #000000; padding: 50px 20px;">
                      <p style="margin:0 0 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; opacity: 0.5;">
                        Your Security Code
                      </p>
                      <p style="margin:0; font-size: 64px; font-weight: 900; letter-spacing: 12px; font-family: 'Courier New', Courier, monospace; color: #000000;">
                        {$otpCode}
                      </p>
                    </div>
                  </td>
                </tr>
              </table>

              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding-top: 40px;">
                <tr>
                  <td align="center">
                    <p style="margin:0; font-size: 13px; color: #666666; font-style: italic;">
                      If you didn't request this, please ignore this message.
                    </p>
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- Footer Area -->
          <tr>
            <td align="center" style="padding: 40px; border-top: 1px solid #000000; background-color: #ffffff;">
              <p style="margin:0; font-size: 10px; font-weight: 500; letter-spacing: 1px; line-height: 1.8; text-transform: uppercase;">
                &copy; {$year} CODECANVAS. ALL RIGHTS RESERVED.<br/>
                SECURE AUTHENTICATION SYSTEM.
              </p>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
HTML;
}
