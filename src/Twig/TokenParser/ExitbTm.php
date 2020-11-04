<?php

namespace App\Twig\TokenParser;

use Twig\Error\SyntaxError;
use Twig\Node\Node;
use Twig\Token;
use App\Twig\Node\ExitbTm as ExitBTmNode;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Tms a template.
 *
 * <pre>
 *   {% tm 'header.html' %}
 *     Body
 *   {% tm 'footer.html' %}
 * </pre>
 */
class ExitbTm extends AbstractTokenParser
{
    /**
     * @param Token $token
     * @return ExitBTmNode|Node
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();
        list($variables, $only, $ignoreMissing, $default) = $this->parseArguments();

        return new ExitbTmNode($expr, $token->getLine(), $variables, $default, $only, $ignoreMissing, $this->getTag());
    }

    /**
     * @return array
     * @throws SyntaxError
     */
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

        $default = null;
        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'default')) {
            $default = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(/* Twig_Token::NAME_TYPE */ 5, 'only')) {
            $only = true;
        }

        $stream->expect(/* Twig_Token::BLOCK_END_TYPE */ 3);

        return [
            $variables,
            $only,
            $ignoreMissing,
            $default
        ];
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return 'exitbTm';
    }
}
