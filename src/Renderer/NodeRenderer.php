<?php

namespace Xopoc14\PhpLatex\Renderer;

use Xopoc14\PhpLatex\Node;

interface NodeRenderer
{
    /**
     * @param Node $node
     * @return string
     */
    public function render(Node $node);
}
