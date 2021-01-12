<?php

use Xopoc14\PhpLatex\Parser;

return [
    'verbatim' => [
        'verbatim' => true,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
        'starred' => true,
        // verbatim in tabular causes
        // ! LaTeX Error: Something's wrong--perhaps a missing \item.
    ],
    'Verbatim' => [
        'verbatim' => true,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
    ],
    'lstlisting' => [
        'verbatim' => true,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
    ],
    'enumerate' => [
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
    ],
    'itemize' => [
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
        // itemize in tabular causes
        // ! LaTeX Error: Something's wrong--perhaps a missing \item.
    ],
    'displaymath' => [
        'math' => true,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate'],
        // displaymath in tabular causes
        // ! LaTeX Error: Bad math environment delimiter.
    ],
    'math' => [
        'math' => true,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate', 'tabular'],
    ],
    'equation' => [
        'mode' => Parser::MODE_TEXT,
        'math' => true,
        'starred' => true,
    ],
    'eqnarray' => [
        'mode' => Parser::MODE_TEXT,
        'math' => true,
        'starred' => true,
    ],
    'tabular' => [
        'numArgs' => 1,
        'mode' => Parser::MODE_TEXT,
        'environs' => ['itemize', 'enumerate', 'tabular'],
    ],
];
