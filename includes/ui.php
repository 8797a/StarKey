<?php
function faka_asset($path)
{
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $basePath = '';

    if ($documentRoot !== '' && strpos($projectRoot, $documentRoot) === 0) {
        $basePath = substr($projectRoot, strlen($documentRoot));
    }

    $basePath = '/' . trim((string)$basePath, '/');
    if ($basePath === '/') {
        $basePath = '';
    }

    return $basePath . '/' . ltrim($path, '/');
}

function faka_ui_head()
{
    return '<link rel="stylesheet" href="' . h(faka_asset('assets/css/faka-ui.css')) . '">';
}

function faka_ui_background($rings = true)
{
    $classes = 'fluid-bg' . ($rings ? ' faka-bg-rings' : '');
    return '<div class="' . $classes . '"><div class="fluid-blob blob-1"></div><div class="fluid-blob blob-2"></div><div class="fluid-blob blob-3"></div></div>';
}

function faka_ui_script()
{
    return '<script src="' . h(faka_asset('assets/js/faka-ui.js')) . '"></script>';
}
