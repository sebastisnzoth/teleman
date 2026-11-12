<?php

/**
 * Minimal, framework-free error page for the two situations in which the
 * application cannot boot far enough to render its own: a missing vendor/
 * folder, or an application root that cannot be written to. Plain HTML, no
 * dependencies, so it works in any state the upload might be in.
 */
function install_blocked(string $title, string $html): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 30');

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
  body{margin:0;font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7f9;color:#1f2937}
  main{max-width:640px;margin:12vh auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px 36px;box-shadow:0 8px 24px rgba(0,0,0,.05)}
  h1{font-size:22px;margin:0 0 12px}
  p{margin:0 0 12px}
  code{background:#f3f4f6;border-radius:4px;padding:1px 6px;font-size:14px}
  small{color:#6b7280}
</style>
</head>
<body>
<main>
  <h1>{$title}</h1>
  <p>{$html}</p>
  <p><small>This page is shown by the installer before the application starts. Once the problem is fixed, reload and the setup wizard will continue.</small></p>
</main>
</body>
</html>
HTML;

    exit(1);
}
