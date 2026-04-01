<?php
/**
 * Email Template: Portfolio New Message Notification
 *
 * Sent to portfolio owner when a visitor submits the contact form.
 *
 * @param array $vars {
 *   string $owner_name      — Portfolio owner's name (required)
 *   string $visitor_name    — Visitor's name (required)
 *   string $visitor_email   — Visitor's email (required)
 *   string $portfolio_name  — Name of the portfolio project (required)
 *   string $message_preview — First ~300 chars of the message (required)
 *   string $dashboard_link  — Full URL to the owner's messages dashboard (required)
 * }
 * @return string Full HTML email body
 */
function emailTemplateNewMessage(array $vars): string
{
    $ownerName      = htmlspecialchars($vars['owner_name']      ?? 'there',         ENT_QUOTES, 'UTF-8');
    $visitorName    = htmlspecialchars($vars['visitor_name']    ?? 'A Visitor',      ENT_QUOTES, 'UTF-8');
    $visitorEmail   = htmlspecialchars($vars['visitor_email']   ?? '',               ENT_QUOTES, 'UTF-8');
    $portfolioName  = htmlspecialchars($vars['portfolio_name']  ?? 'Your Portfolio', ENT_QUOTES, 'UTF-8');
    $messagePreview = htmlspecialchars($vars['message_preview'] ?? '',               ENT_QUOTES, 'UTF-8');
    $dashboardLink  = htmlspecialchars($vars['dashboard_link']  ?? 'https://codecanvas.page/app/messages.php', ENT_QUOTES, 'UTF-8');
    $year           = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>New Message — CodeCanvas</title>
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

              <!-- New Message Header -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center" style="padding-top: 50px; padding-bottom: 20px;">
                    <h2 style="margin:0; font-size: 32px; font-weight: 800; line-height: 1.2; letter-spacing: -0.5px;">
                      New Message.
                    </h2>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding-bottom: 40px;">
                    <p style="margin:0; font-size: 16px; line-height: 1.6; color: #666666; max-width: 440px;">
                      A potential client or collaborator contacted you via <strong>{$portfolioName}</strong>.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Sender Info Box -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f5f5f5; border: 1px solid #000000;">
                <tr>
                  <td align="center" style="padding: 30px;">
                    <p style="margin:0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.5;">
                      Sender Information
                    </p>
                    <p style="margin:12px 0 4px; font-size: 20px; font-weight: 800;">
                      {$visitorName}
                    </p>
                    <p style="margin:0; font-size: 14px; color: #666666;">
                      {$visitorEmail}
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Message Content Area -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding-top: 40px; padding-bottom: 40px;">
                <tr>
                  <td align="left" style="background-color: #ffffff; border: 1px solid #000000; padding: 40px;">
                    <p style="margin:0 0 15px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px;">Message Preview</p>
                    <p style="margin:0; font-size: 16px; line-height: 1.7; color: #000000;">
                      "{$messagePreview}"
                    </p>
                  </td>
                </tr>
              </table>

              <!-- CTA Section -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center">
                    <a href="{$dashboardLink}" 
                       style="display: inline-block; background-color: #000000; color: #ffffff; padding: 18px 48px; font-size: 13px; font-weight: 700; text-decoration: none; letter-spacing: 2px; text-transform: uppercase;">
                      Reply In Dashboard
                    </a>
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
                DIRECT PORTFOLIO COMMUNICATION SYSTEM.
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
