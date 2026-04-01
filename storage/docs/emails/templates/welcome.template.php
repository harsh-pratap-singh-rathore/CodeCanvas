<?php
/**
 * Email Template: Welcome
 *
 * Sent to new users immediately after account creation.
 *
 * @param array $vars {
 *   string $name  — User's display name (required)
 *   string $email — User's email address (required)
 * }
 * @return string Full HTML email body
 */
function emailTemplateWelcome(array $vars): string
{
    $name  = htmlspecialchars($vars['name']  ?? 'there', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($vars['email'] ?? '',       ENT_QUOTES, 'UTF-8');
    $year  = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Welcome to CodeCanvas</title>
  <style>
    @media only screen and (max-width: 620px) {
      .wrapper { width: 100% !important; padding: 20px !important; }
      .container { width: 100% !important; padding: 40px 20px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#000000;-webkit-font-smoothing:antialiased;">

  <!-- Main Wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;width:100%;">
    <tr>
      <td align="center" style="padding: 80px 0;">
        
        <!-- Email Container -->
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #000000;border-radius:0;">
          
          <!-- Symmetrical Content Area -->
          <tr>
            <td align="center" style="padding: 60px 40px;">
              
              <!-- Brand Header -->
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
                      Welcome, {$name}.
                    </h2>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding-bottom: 40px;">
                    <p style="margin:0; font-size: 16px; line-height: 1.6; color: #000000; max-width: 440px;">
                      Your journey to a professional online presence starts now. We've prepared everything you need to build, deploy, and scale.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Account Info Box -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f5f5f5; border: 1px solid #000000;">
                <tr>
                  <td align="center" style="padding: 24px;">
                    <p style="margin:0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.5;">
                      Registered Account
                    </p>
                    <p style="margin:10px 0 0; font-size: 16px; font-weight: 600; font-family: 'Courier New', Courier, monospace;">
                      {$email}
                    </p>
                  </td>
                </tr>
              </table>

              <!-- CTA Section -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding-top: 50px;">
                <tr>
                  <td align="center">
                    <a href="https://codecanvas.page/public/login.html" 
                       style="display: inline-block; background-color: #000000; color: #ffffff; padding: 18px 48px; font-size: 13px; font-weight: 700; text-decoration: none; letter-spacing: 2px; text-transform: uppercase;">
                      Go To Dashboard
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Feature Highlight -->
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding-top: 60px;">
                <tr>
                  <td align="center" style="border-top: 1px solid #000000; padding-top: 40px;">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td width="33.33%" align="center" style="vertical-align: top;">
                          <p style="margin:0; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Templates</p>
                        </td>
                        <td width="33.33%" align="center" style="vertical-align: top;">
                          <p style="margin:0; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Deploy</p>
                        </td>
                        <td width="33.33%" align="center" style="vertical-align: top;">
                          <p style="margin:0; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Lead Gen</p>
                        </td>
                      </tr>
                    </table>
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
                PREMODERN SITE BUILDER FOR INNOVATORS.
              </p>
            </td>
          </tr>

        </table>

        <!-- Unsubscribe / Meta -->
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%; padding-top: 30px;">
          <tr>
            <td align="center">
              <p style="margin:0; font-size: 11px; color: #666666;">
                Sent to {$email}. <a href="#" style="color: #000000; text-decoration: underline;">Manage preferences</a> or <a href="#" style="color: #000000; text-decoration: underline;">unsubscribe</a>.
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
