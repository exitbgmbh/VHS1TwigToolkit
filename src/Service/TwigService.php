<?php

namespace App\Service;

use App\Twig\Loader\TextModuleLoader;
use App\Twig\TokenParser\ExitbTm;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFunction;

class TwigService
{
    /** @var ConfigService */
    private $_configService;

    /**
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->_configService = $configService;
    }

    /**
     * @param string $templateName
     * @param array $context
     * @param array $mapping
     * @return string
     * @throws TwigLoaderError
     * @throws TwigRuntimeError
     * @throws TwigSyntaxError
     */
    public function renderTemplate(
        string $templateName,
        array $context,
        array $mapping
    ): string {
        $loader = new TwigFilesystemLoader([
            __DIR__ . '/../Templates',
            __DIR__ . '/../Templates/email',
            __DIR__ . '/../Templates/slip',
        ]);

        $tmLoader = new TextModuleLoader($mapping);

        $chainLoader = new TwigChainLoader([
            $loader,
            $tmLoader,
        ]);

        $tmParser = new ExitbTm();
        $twig = new TwigEnvironment($chainLoader, [ 'auto_reload' => true ]);

        $twig->addTokenParser($tmParser);

        $exitbTmTwigFunction = new TwigFunction(
            'exitbTm',
            'twig_exitbTm',
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
