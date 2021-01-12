<?php

namespace Xopoc14\PhpLatex\Test;

use PHPUnit\Framework\TestCase;
use Xopoc14\PhpLatex\Parser;
use Xopoc14\PhpLatex\Renderer\AbstractRenderer;

class ParserTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp()
    {
        parent::setUp();
        $this->parser = new Parser();
    }

    /**
     * @param string $input
     * @param string $expected
     * @dataProvider provideNewlines
     */
    public function testNewlines($input, $expected)
    {
        $tree = $this->parser->parse($input);
        $this->assertSame($expected, AbstractRenderer::toLatex($tree));
    }

    public function provideNewlines()
    {
        return [
            [
                "A\n \nB",
                'A\par B',
            ],
            [
                "A\n { }\nB",
                'A { } B',
            ],
            [
                "A\nB",
                'A B',
            ],
            [
                "A\n % comment\nB",
                'A B',
            ],
            "LaTeX Error: There's no line here to end." => [
                "A\n \n\\newline B",
                'A\par \newline B',
            ],
        ];
    }

    public function testStarred()
    {
        $tree = $this->parser->parse('\\* \\section*{Foo} \\LaTeX*');
        $this->assertSame('\\* \\section*{Foo} \\LaTeX *', AbstractRenderer::toLatex($tree));
    }

    public function testSpaces()
    {
        $tree = $this->parser->parse('\\ \\, \\: \\;');
        $this->assertSame('\\ \\, \(\\:\) \(\\;\)', AbstractRenderer::toLatex($tree));
    }
}
