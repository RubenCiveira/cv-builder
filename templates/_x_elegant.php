<?php
return function (string $html): string {
    // 1) Inyectar cabecera elegante antes de <main class="container">
    $header = <<<HTML
    <header style="display:flex;justify-content:space-between;align-items:center;padding:8mm 12mm;border-bottom:1px solid #ddd; margin-bottom:8mm;">
      <div style="font-family:Georgia,serif;font-size:20pt;letter-spacing:.3px;">Ruben Civeira Iglesias</div>
      <div style="font-size:9pt;color:#666;">Líder Técnico · PMP®</div>
    </header>
    HTML;

    $html = preg_replace(
        '#(<body[^>]*>)#i',
        '$1' . $header,
        $html,
        1
    );

    // 2) Marca de agua sutil (solo en PDF suele respetarse con mPDF)
    $extraCss = <<<CSS
    @page { margin-top: 20mm; margin-bottom: 15mm; }
    body::before{
      content:"Ruben Civeira CV";
      position:fixed; top:50%; left:50%;
      transform:translate(-50%,-50%) rotate(-30deg);
      color:#000; opacity:0.05; font-size:48pt; font-family:Georgia,serif; z-index:-1;
      pointer-events:none;
    }
    summary::marker{ content: ""; } /* ocultar marcador nativo */
    summary::before{ content:"▶ "; }
    details[open] > summary::before{ content:"▼ "; }
    CSS;

    $html = preg_replace(
        '#</style>#i',
        $extraCss . "\n</style>",
        $html,
        1
    );

    // 3) (Opcional) Otras transformaciones DOM/string…
    //    por ejemplo, asegurar que todos los <details> vengan abiertos en PDF:
    $html = preg_replace('#<details>#i', '<details open>', $html);

    return $html;
};
