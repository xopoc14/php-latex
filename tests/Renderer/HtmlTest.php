<?php

namespace Xopoc14\PhpLatex\Test\Renderer;

use PHPUnit\Framework\TestCase;
use Xopoc14\PhpLatex\Renderer\Html;

class HtmlTest extends TestCase
{
    public function testRenderer()
    {
        $renderer = new Html();
        $html = $renderer->render('
            \textit{\textbf{Italic \textup{bold} text}}
        ');

        $this->assertEquals('<i><b>Italic <span style="font-style:normal">bold</span> text</b></i>', $html);
    }

    public function testSpaces()
    {
        $renderer = new Html();
        $html = $renderer->render('
            A B

            A\ B

            A\,B

            A\enspace{}B

            A\quad B
        ');

        $this->assertEquals('A B<br/><br/>A&nbsp;B<br/><br/>A&thinsp;B<br/><br/>A&ensp;B<br/><br/>A&emsp;B', $html);
    }
}
