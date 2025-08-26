<?php

namespace App;

use GuzzleHttp\Client;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

class Renderer
{
    public static function toPdf(string $template, bool $withDetails, string $url = ''): string
    {
        $html = self::getHtml($url, $template, $withDetails);
        // Generar PDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir(), // asegura carpeta temporal válida
        ]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S'); // obtener como string
    }

    public static function toHtml(string $template, bool $withDetails, string $url = ''): string
    {
        return self::getHtml($url, $template, $withDetails);
    }

    private static function getHtml(string $url, string $template, $withDetails)
    {
        $renderer = new Renderer($url);
        $md = $renderer->fetchMarkdown();
        if (!$withDetails) {
            $md = $renderer->stripDetails($md);
        }
        // cargar CSS del template si existe (si no, PDF irá con el estilo base)
        $css = $renderer->loadTemplateCss($template);
        $html = $renderer->html($md, $css);
        if ($fn = $renderer->loadHtmlTransformer($template)) {
            $html = $fn($html);
        }
        return $html;
    }

    private string $rawUrl;
    private Client $http;
    private CommonMarkConverter $md;

    public function __construct(
        string $rawUrl = ''
    ) {
        $this->rawUrl = !!$rawUrl ? $rawUrl : 'https://raw.githubusercontent.com/RubenCiveira/RubenCiveira/refs/heads/main/README.md';
        $this->http = new Client([
            'timeout' => 10,
            'http_errors' => true,
        ]);

        $env = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
        ]);
        // Extensiones útiles para GitHub-flavored Markdown
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new TableExtension());
        $env->addExtension(new AttributesExtension());

        $this->md = new CommonMarkConverter([], $env);
    }

    private function fetchMarkdown(): string
    {
        $res = $this->http->get($this->rawUrl);
        return (string)$res->getBody();
    }

    private function stripDetails(string $markdown): string
    {
        // eliminar bloques <details>...</details> incluyendo multilínea
        return preg_replace('/<details[\s\S]*?<\/details>/i', '', $markdown);
    }

    private function html(string $markdown, ?string $templateCss = null): string
    {
        $content = $this->md->convert($markdown)->getContent();

        // CSS base para pantalla/impresión
        $baseCss = <<<CSS
        body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif; line-height:1.45; color:#111; margin:0; padding:0;}
        .container{max-width:900px; margin:0 auto; padding:32px;}
        h1,h2,h3{margin-top:1.2em}
        h1{font-size:1.8rem} h2{font-size:1.4rem} h3{font-size:1.15rem}
        code, pre{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
        table{border-collapse:collapse} th,td{border:1px solid #ddd; padding:6px}
        details{margin: .75rem 0}
        summary{cursor:pointer; font-weight:600}
        @media print {
          a[href]:after{content:""}
          details[open] summary{margin-bottom:.25rem}
          .container{max-width:100%; padding:0 12mm}
        }
        CSS;

        $templateCssTag = $templateCss ? "<style>\n{$templateCss}\n</style>" : '';

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>CV – Ruben Civeira Iglesias</title>
          <style>{$baseCss}</style>
          {$templateCssTag}
        </head>
        <body>
          <main class="container">
            {$content}
          </main>
        </body>
        </html>
        HTML;
    }

    private function loadHtmlTransformer(?string $templateName): ?callable
    {
        if (!$templateName) {
            return null;
        }
        $path = dirname(__DIR__) . '/templates/' . basename($templateName) . '.php';
        if (!is_file($path)) {
            return null;
        }

        // El fichero debe devolver una función/callable
        $fn = require $path;

        return is_callable($fn) ? $fn : null;
    }

    private function loadTemplateCss(?string $templateName): ?string
    {
        if (!$templateName) {
            return null;
        }
        $path = dirname(__DIR__) . '/templates/' . basename($templateName) . '.css';
        if (is_file($path)) {
            return (string)file_get_contents($path);
        }
        return null;
    }

    public function listTemplates(): array
    {
        $dir = dirname(__DIR__) . '/templates';
        if (!is_dir($dir)) {
            return [];
        }
        $files = array_values(array_filter(scandir($dir) ?: [], fn ($f) => str_ends_with($f, '.css')));
        // devolver nombres sin extensión
        return array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
    }
}
