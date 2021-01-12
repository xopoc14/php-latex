<?php

namespace Xopoc14\PhpLatex\Renderer;

use Xopoc14\PhpLatex\Node;
use Xopoc14\PhpLatex\Parser;
use Xopoc14\PhpLatex\Utils;

abstract class AbstractRenderer
{
    /**
     * Creates LaTeX representation of the given document node.
     *
     * This method is useful when parts of the rendered document should be
     * presented as the LaTeX source for processing (validating and rendering)
     * by external tools, i.e. MathJaX, mathTeX or mimeTeX.
     *
     * @param Node|Node[] $node
     * @return string
     */
    public static function toLatex($node) // {{{
    {
        if ($node instanceof Node) {
            switch ($node->getType()) {
                case Parser::TYPE_SPECIAL:
                    if ($node->value === '_' || $node->value === '^') {
                        return $node->value . self::toLatex($node->getChildren());
                    }
                    return $node->value;

                case Parser::TYPE_TEXT:
                    // make sure text is properly escaped
                    $source = Utils::escape($node->value);
                    return $source;

                case Parser::TYPE_GROUP:
                    $source = $node->optional ? '[{' : '{';
                    $source .= self::toLatex($node->getChildren());
                    $source .= $node->optional ? '}]' : '}';
                    return $source;

                case Parser::TYPE_VERBATIM:
                    return $node->value;

                case Parser::TYPE_MATH:
                    $source = self::toLatex($node->getChildren());
                    if ($node->inline) {
                        return '\\(' . $source . '\\)';
                    } else {
                        return '\\[' . $source . '\\]';
                    }

                case Parser::TYPE_COMMAND:
                    $value = $node->value;
                    if ($node->starred) {
                        $value .= '*';
                    }
                    if ($node->value === '\\string') {
                        foreach ($node->getChildren() as $child) {
                            $value .= self::toLatex($child);
                        }
                        return $value;
                    }
                    if ($node->symbol || $node->hasChildren()) {
                        return $value . self::toLatex($node->getChildren());
                    }
                    // control word, add space that was removed after
                    return $value . ' ';

                case Parser::TYPE_ENVIRON:
                    return "\\begin{" . $node->value . "}\n"
                         . self::toLatex($node->getChildren())
                         . "\\end{" . $node->value . "}\n";

                case Parser::TYPE_DOCUMENT:
                    return self::toLatex($node->getChildren());
            }
        } elseif (is_array($node)) {
            // render node list and concatenate results
            $latex = '';
            foreach ($node as $child) {
                $latex .= self::toLatex($child);
            }
            return $latex;
        }
    }

    /**
     * @param Node|string $node
     * @return string
     */
    abstract public function render($node);

    protected $_commandRenderers = [];

    public function addCommandRenderer($command, $renderer)
    {
        if (!is_callable($renderer) && !$renderer instanceof NodeRenderer) {
            throw new InvalidArgumentException(sprintf(
                'Renderer must be an instance of NodeRenderer or a callable, %s given',
                is_object($renderer) ? get_class($renderer) : gettype($renderer)
            ));
        }
        $this->_commandRenderers[$command] = $renderer;
        return $this;
    }

    public function hasCommandRenderer($command)
    {
        return isset($this->_commandRenderers[$command]);
    }

    public function executeCommandRenderer($command, Node $node)
    {
        if (!$this->hasCommandRenderer($command)) {
            throw new InvalidArgumentException('Renderer for command ' . $command . ' not available');
        }
        $renderer = $this->_commandRenderers[$command];
        if ($renderer instanceof NodeRenderer) {
            return $renderer->render($node);
        }
        return call_user_func($renderer, $node);
    }

}
