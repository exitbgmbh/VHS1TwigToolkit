<?php

namespace App\Service;

use App\Twig\Loader\TextModuleLoader;
use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

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
     * @param string $advertisingMediumCode
     * @return string
     * @throws Exception
     * @throws TwigLoaderError
     * @throws TwigRuntimeError
     * @throws TwigSyntaxError
     */
    public function renderTemplate(
        string $templateName,
        array $context,
        string $advertisingMediumCode = ''
    ): string {
        $loader = new TwigFilesystemLoader([
            __DIR__ . '/../Templates/email',
            __DIR__ . '/../Templates/slip',
        ]);

        $tmLoader = new TextModuleLoader($this->_configService->getTranslatedTextModules($advertisingMediumCode));

        $chainLoader = new TwigChainLoader([
            $loader,
            $tmLoader,
        ]);

        $twig = new TwigEnvironment($chainLoader);
        $templateWrapper = $twig->load($templateName);

        return $templateWrapper->render($context);
    }
}
