<?php

namespace App\Twig\TokenParser;

use Twig\Token;
use App\Twig\Node\ExitbTm as ExitBTmNode;

/**
 * Tms a template.
 *
 * <pre>
 *   {% tm 'header.html' %}
 *     Body
 *   {% tm 'footer.html' %}
 * </pre>
 */
class ExitbTm extends \Twig\TokenParser\AbstractTokenParser
{
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        list($variables, $only, $ignoreMissing, $default) = $this->parseArguments();

        return new ExitbTmNode($expr, $variables, $default, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    protected function parseArguments()
    {
        $stream = $this->parser->getStream();

        $ignoreMissing = false;
        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'ignore')) {
            $stream->expect(/* Twig_Token::NAME_TYPE */ 5, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'with')) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'default')) {
            $default = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'only')) {
            $only = true;
        }

        $stream->expect(/* Twig_Token::BLOCK_END_TYPE */ 3);

        return array($variables, $only, $ignoreMissing, $default);
    }

    public function getTag()
    {
        return 'exitbTm';
    }
}
