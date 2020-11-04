<?php

namespace App\Twig\Node;

use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;
use Twig\Compiler;

class ExitbTm extends Node implements NodeOutputInterface
{
    /**
     * @param $expr
     * @param $lineno
     * @param null $variables
     * @param null $default
     * @param false $only
     * @param bool $ignoreMissing
     * @param null $tag
     */
    public function __construct($expr, $lineno, $variables = null, $default = null, $only = false, $ignoreMissing = true, $tag = null)
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

    /**
     * @param Compiler $compiler
     */
    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->write("try {\n")
            ->indent();

        $default = '';
        if ($this->hasNode('default')) {
            $default = str_replace("'","\'", $this->getNode('default')->getAttribute('value'));
        }

        $compiler->write('if (!array_key_exists("' . $this->getNode('expr')->getAttribute('value') . '", $this->env->getGlobals())) {')
            ->indent()
            ->write('$this->env->getLoader()->getLoaders()[1]->addMapping("' . $this->getNode('expr')->getAttribute('value') . '", "' . $default . '");')
            ->outdent()
            ->write('}');

        $compiler->write('echo sprintf(twig_include($this->env, $context, "' . $this->getNode('expr')->getAttribute('value') . '")');
        if ($this->hasNode('variables')) {
            $compiler->raw(', ...');
            $compiler->subcompile($this->getNode('variables'));
        }

        $compiler->write(");\n\n");

        if ($this->hasNode('default')) {
            $compiler->outdent()
                ->write("} catch (\Twig\Error\LoaderError \$e) {\n")
                ->indent()
                ->write('echo sprintf(\'' . str_replace("'","\'", $this->getNode('default')->getAttribute('value')) . '\'');

            if ($this->hasNode('variables')) {
                $compiler->raw(', ...');
                $compiler->subcompile($this->getNode('variables'));
            }

            $compiler->raw(');')
                ->outdent()
                ->write("}\n\n");
        } else {
            $compiler->outdent()
                ->write("} catch (\Twig\Error\LoaderError \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->outdent()
                ->raw("}\n\n");
        }
    }

}
