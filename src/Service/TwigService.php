<?php

namespace App\Service;

use App\Twig\Loader\TextModuleLoader;
use App\Twig\TokenParser\ExitbTm;
use Priotas\Twig\Extension\QrCode;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFunction;

class TwigService
{
    /**
     * @param string $templateName
     * @param array $context
     * @param array $mapping
     * @param string $kind
     * @return string
     * @throws TwigLoaderError
     * @throws TwigRuntimeError
     * @throws TwigSyntaxError
     */
    public function renderTemplate(
        string $templateName,
        array $context,
        array $mapping,
        string $kind
    ): string {
        $paths = [
            __DIR__ . '/../Templates',
        ];

        if ($kind === TypesService::TEMPLATE_TYPE_DOCUMENT_NAME) {
            $paths[] = __DIR__ . '/../Templates/slip';
            $paths[] = __DIR__ . '/../Templates/email';
        } else {
            $paths[] = __DIR__ . '/../Templates/email';
            $paths[] = __DIR__ . '/../Templates/slip';
        }

        $loader = new TwigFilesystemLoader($paths);
        $tmLoader = new TextModuleLoader($mapping);

        $chainLoader = new TwigChainLoader([
            $loader,
            $tmLoader,
        ]);

        $tmParser = new ExitbTm();
        $twig = new TwigEnvironment($chainLoader, [ 
            'auto_reload' => true,
            'debug' => true,
        ]);
        foreach ($mapping as $key => $value) {
            $twig->addGlobal($key, $value);
        }

        $twig->addExtension(new QrCode());
        $twig->addTokenParser($tmParser);
        $twig->addExtension(new \Twig\Extension\DebugExtension());
        $exitbTmTwigFunction = new TwigFunction(
            'exitbTm',
            [
                'needs_environment' => true,
                'needs_context' => true,
                'is_safe' => [ 'all' ],
            ]
        );

        $twig->addFunction($exitbTmTwigFunction);

        $templateWrapper = $twig->load($templateName);

        return $templateWrapper->render($context);
    }
}
