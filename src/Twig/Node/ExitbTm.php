<?php


namespace App\Twig\Node;
use Twig\Node\NodeOutputInterface;
use Twig\Compiler;

/**
 * Represents an tm node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExitbTm extends \Twig\Node\Node implements NodeOutputInterface
{
    public function __construct($expr, $variables = null, $default = null, $only = false, $ignoreMissing = true, $lineno, $tag = null)
    {
        $nodes = array('expr' => $expr);
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }
        if (null !== $default) {
            $nodes['default'] = $default;
        }

        parent::__construct($nodes, array('only' => (bool) $only, 'ignore_missing' => (bool) $ignoreMissing), $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler
            ->write("try {\n")
            ->indent()
        ;

        $compiler->write('echo sprintf(twig_include($this->env, $context, "' . $this->getNode('expr')->getAttribute('value') . '")');
        if ($this->hasNode('variables')) {
            $compiler->raw(', ...');
            $compiler->subcompile($this->getNode('variables'));
        }
        $compiler->write(");\n\n");

        if ($this->getAttribute('ignore_missing')) {
            $compiler
                ->outdent()
                ->write("} catch (\Twig\Error\LoaderError \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->outdent()
                ->raw("}\n\n")
            ;
        } elseif ($this->hasNode('default')) {
            $compiler
                ->outdent()
                ->write("} catch (\Twig\Error\LoaderError \$e) {\n")
                ->indent()
                ->write('echo sprintf(\'' . str_replace("'","\'", $this->getNode('default')->getAttribute('value')) . '\'');
            if ($this->hasNode('variables')) {
                $compiler->raw(', ...');
                $compiler->subcompile($this->getNode('variables'));
            }
            $compiler
                ->raw(');')
                ->outdent()
                ->write("}\n\n");
        }
    }

    protected function addGetTemplate(Compiler $compiler)
    {
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')')
        ;
    }

    protected function addTemplateArguments(Compiler $compiler)
    {
        if (!$this->hasNode('variables')) {
            $compiler->raw(false === $this->getAttribute('only') ? '$context' : 'array()');
        } elseif (false === $this->getAttribute('only')) {
            $compiler
                ->raw('array_merge($context, ')
                ->subcompile($this->getNode('variables'))
                ->raw(')')
            ;
        } else {
            $compiler->subcompile($this->getNode('variables'));
        }
    }
}
