<?php
/**
 * Email Template: Portfolio Deployed (Premium Redesign)
 */
function emailTemplateDeployed(array $vars): string
{
    $name       = htmlspecialchars($vars['name']        ?? 'Creator', ENT_QUOTES, 'UTF-8');
    $project    = htmlspecialchars($vars['projectName'] ?? 'Portfolio', ENT_QUOTES, 'UTF-8');
    $url        = htmlspecialchars($vars['liveUrl']     ?? '#', ENT_QUOTES, 'UTF-8');
    $year       = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Site is Live - CodeCanvas</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 0; background-color: #0a0a0a; color: #ffffff; }
    .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
    .card { background-color: #141414; border: 1px solid #262626; border-radius: 16px; padding: 48px; text-align: center; }
    .logo { font-weight: 800; font-size: 20px; letter-spacing: -0.02em; color: #ffffff; margin-bottom: 48px; text-transform: uppercase; }
    .badge { display: inline-block; padding: 6px 12px; background: rgba(0, 255, 127, 0.1); border: 1px solid rgba(0, 255, 127, 0.2); color: #00ff7f; font-size: 12px; font-weight: 600; border-radius: 99px; margin-bottom: 24px; text-transform: uppercase; }
    h1 { font-size: 36px; font-weight: 800; line-height: 1.1; margin: 0 0 16px 0; letter-spacing: -0.04em; }
    p { font-size: 16px; line-height: 1.6; color: #a1a1aa; margin: 0 0 32px 0; }
    .url-box { background-color: #1f1f1f; border-radius: 12px; padding: 20px; margin-bottom: 32px; border: 1px solid #333333; }
    .url-label { font-size: 11px; color: #71717a; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; font-weight: 600; }
    .url-link { font-size: 15px; color: #ffffff; font-family: monospace; text-decoration: none; word-break: break-all; }
    .btn { display: inline-block; background-color: #ffffff; color: #000000; padding: 16px 32px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 8px; transition: all 0.2s; }
    .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid #262626; color: #52525b; font-size: 12px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="logo">CodeCanvas</div>
      <div class="badge">Success!</div>
      <h1>World, meet {$project}.</h1>
      <p>Hey {$name}, your vision is now a reality. We've optimized your assets, finalized the build, and pushed your site to our global edge network.</p>
      
      <div class="url-box">
        <div class="url-label">Production URL</div>
        <a href="{$url}" class="url-link">{$url}</a>
      </div>

      <a href="{$url}" class="btn">View Live Portfolio</a>

      <div class="footer">
        &copy; {$year} CodeCanvas Platform. All systems operational. <br>
        Built with precision by the CodeCanvas Engine.
      </div>
    </div>
  </div>
</body>
</html>
HTML;
}
