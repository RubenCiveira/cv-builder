<?php

declare(strict_types=1);

use App\Renderer;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$scriptName = $_SERVER['SCRIPT_NAME']; // Devuelve algo como "/midashboard/index.php"
$basePath = str_replace('/index.php', '', $scriptName); // "/midashboard"
$app->setBasePath($basePath);


// Ruta raíz: lista de templates disponibles
$app->get('/', function (Request $req, Response $res): Response {
    $renderer = new Renderer();
    $templates = $renderer->listTemplates();

    $html = '<!doctype html><html lang="es"><meta charset="utf-8"><title>Templates</title><style>
      body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding:24px}
      h1{font-size:1.4rem}
      ul{line-height:1.8}
      code{background:#f6f8fa; padding:2px 6px; border-radius:4px}
    </style><body>';
    $html .= '<h1>Templates disponibles</h1>';

    if (empty($templates)) {
        $html .= '<p>No hay templates. Crea archivos <code>.css</code> en <code>/templates</code>.</p>';
    } else {
        $html .= '<ul>';
        foreach ($templates as $t) {
            $html .= '<li><code>'.$t.'</code> <ul>'
                .'<li>- ES HTML <a href="./es/small/'.htmlspecialchars($t).'/html" target="_blank">Ver compacto</a>, <a href="./es/full/'.htmlspecialchars($t).'/html" target="_blank">Ver completo</a></li>'
                .'<li>- ES PDF <a href="./es/small/'.htmlspecialchars($t).'/pdf" target="_blank">Descargar compacto</a>, <a href="./es/full/'.htmlspecialchars($t).'/pdf" target="_blank">Descargar completo</a></li>'
                .'<li>- EN HTML <a href="./en/small/'.htmlspecialchars($t).'/html" target="_blank">Ver compacto</a>, <a href="./en/full/'.htmlspecialchars($t).'/html" target="_blank">Ver completo</a></li>'
                .'<li>- ES HTML <a href="./en/small/'.htmlspecialchars($t).'/html" target="_blank">Ver compacto</a>, <a href="./en/full/'.htmlspecialchars($t).'/html" target="_blank">Ver completo</a></li>'
                .'</ul></li>';
        }
        $html .= '</ul>';
    }

    $html .= '<hr><p>También puedes probar sin template: <a href="/plain/pdf" target="_blank">/plain/pdf</a></p>';
    $html .= '</body></html>';

    $res->getBody()->write($html);
    return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// Ruta PDF: /{template}/pdf → genera el PDF con el CSS de ese template (si existe) o sin template
$app->get('/{lang}/small/{template}/pdf', function (Request $req, Response $res, array $args): Response {
    try {
        $lang = $args['lang'] ?? 'es'; // por defecto castellano
        $contentLanguage = ($lang === 'en') ? 'en-EN' : 'es-ES';
        $pdf = Renderer::toPdf($args['template'] ?? null, false, url($args));
        $filename = 'CV_RubenCiveira.pdf';
        $res->getBody()->write($pdf);
        return $res
            ->withHeader('Content-Type', 'application/pdf')
             ->withHeader('Content-Language', $contentLanguage)
            ->withHeader('Content-Disposition', 'inline; filename="'.$filename.'"');
    } catch (\Throwable $e) {
        $res->getBody()->write('Error descargando Markdown: ' . $e->getMessage());
        return $res->withStatus(502);
    }
});

// Ruta opcional para ver el HTML renderizado (útil depurar)
$app->get('/{lang}/full/{template}/pdf', function (Request $req, Response $res, array $args): Response {
    try {
        $lang = $args['lang'] ?? 'es'; // por defecto castellano
        $contentLanguage = ($lang === 'en') ? 'en-EN' : 'es-ES';
        $pdf = Renderer::toPdf($args['template'] ?? null, true, url($args));
        $filename = 'CV_RubenCiveira.pdf';
        $res->getBody()->write($pdf);
        return $res
            ->withHeader('Content-Type', 'application/pdf')
             ->withHeader('Content-Language', $contentLanguage)
            ->withHeader('Content-Disposition', 'inline; filename="'.$filename.'"');
    } catch (\Throwable $e) {
        $res->getBody()->write('Error descargando Markdown: ' . $e->getMessage());
        return $res->withStatus(502);
    }
});

// Ruta PDF "full": renderiza el markdown completo
$app->get('/{lang}/small/{template}/html', function (Request $req, Response $res, array $args): Response {
    try {
        $lang = $args['lang'] ?? 'es'; // por defecto castellano
        $contentLanguage = ($lang === 'en') ? 'en-EN' : 'es-ES';
        $html = Renderer::toHtml($args['template'] ?? null, false, url($args));
        $res->getBody()->write($html);
        return $res
            ->withHeader('Content-Language', $contentLanguage)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    } catch (\Throwable $e) {
        $res->getBody()->write('Error descargando Markdown: ' . $e->getMessage());
        return $res->withStatus(502);
    }

});

// Ruta PDF "small": elimina los bloques <details>
$app->get('/{lang}/full/{template}/html', function (Request $req, Response $res, array $args): Response {
    try {
        $lang = $args['lang'] ?? 'es'; // por defecto castellano
        $contentLanguage = ($lang === 'en') ? 'en-EN' : 'es-ES';
        $html = Renderer::toHtml($args['template'] ?? null, true, url($args));
        $res->getBody()->write($html);
        return $res
            ->withHeader('Content-Language', $contentLanguage)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    } catch (\Throwable $e) {
        $res->getBody()->write('Error descargando Markdown: ' . $e->getMessage());
        return $res->withStatus(502);
    }
});


$app->run();

function url(array $args)
{
    $lang = $args['lang'] ?? 'es';
    return 'https://raw.githubusercontent.com/RubenCiveira/RubenCiveira/main/' . ($lang === 'en' ? 'README.en.md' : 'README.md');
}
