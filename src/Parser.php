<?php

namespace Xopoc14\PhpLatex;

/**
 * This parser attempts to create LaTeX document tree, which by stringified
 * creates semantically correct and valid LaTeX document.
 */
class Parser
{
    // Supported grammar for top-down parser
    //
    // <doc> ::= <exprList>
    // <exprList> ::= <expr> <exprList> | <empty>
    // <expr> ::= <command> | '{' <exprList> '}' | <word>
    //
    // <command>        \\[a-zA-Z]+ | \\[^a-zA-Z]
    // <word>           not a command

    const MODE_MATH = 2;
    const MODE_TEXT = 1;
    const MODE_BOTH = 3;

    const STATE_TEXT = 1;
    const STATE_MATH = 2;
    const STATE_ARG = 4;
    const STATE_OPT_ARG = 8;

    const TYPE_DOCUMENT = 'document';
    const TYPE_TEXT = 'text';
    const TYPE_MATH = 'math';
    const TYPE_GROUP = 'group';
    const TYPE_SPECIAL = 'special';
    const TYPE_COMMAND = 'command';
    const TYPE_ENVIRON = 'environ';
    const TYPE_VERBATIM = 'verbatim';

    protected $_lexer;
    protected $_verbatims;

    /**
     * Environments specification.
     *
     * Supported keys:
     *      int mode            null    - mode in which this environ may be
     *                                    present, one of MODE_ flag constants
     *      bool verbatim       false   - is this environ verbatim
     *      bool math           false   - does this environ start math mode?
     *      string[] environs   array() - list of environments this environ may
     *                                    occur inside, if not given or empty
     *                                    this environ cannot be nested inside
     *                                    other environments
     *      int args            0       - number of arguments this environment
     *                                    requires
     *
     * @var array
     */
    protected $_environs = [];
    protected $_commands = [];

    protected $_skipUndefinedCommands = true;
    protected $_skipUndefinedEnvirons = true;

    protected $refs = [];

    public function __construct()
    {
        $this->addCommands(require dirname(__FILE__) . '/commands.php');
        $this->_environs = require dirname(__FILE__) . '/environs.php';
    }

    /**
     * @param string $name
     * @param array $options
     * @return $this
     */
    public function addCommand($name, array $options) // {{{
    {
        if (!preg_match('/^\\\\([a-zA-Z]+|[^a-zA-Z])$/', $name)) {
            throw new InvalidArgumentException(sprintf('Invalid command name: "%s"', $name));
        }

        if (isset($options['mode'])) {
            $mode = $options['mode'];
            switch ($mode) {
                case 'both':
                    $mode = self::MODE_BOTH;
                    break;

                case 'math':
                    $mode = self::MODE_MATH;
                    break;

                case 'text':
                    $mode = self::MODE_TEXT;
                    break;

                default:
                    $mode = intval($mode);
                    break;
            }
        } else {
            $mode = self::MODE_BOTH;
        }

        $this->_commands[$name] = [
            'mode' => $mode,
            'numArgs' => isset($options['numArgs']) ? intval($options['numArgs']) : 0,
            'numOptArgs' => isset($options['numOptArgs']) ? intval($options['numOptArgs']) : 0,
            'parseArgs' => !isset($options['parseArgs']) || $options['parseArgs'], // parse by default
            'starred' => isset($options['starred']) ? $options['starred'] : false,
        ];
        return $this;
    } // }}}

    public function addCommands(array $commands) // {{{
    {
        foreach ($commands as $name => $spec) {
            $this->addCommand($name, (array)$spec);
        }
        return $this;
    } // }}}

    protected function _getRandomString($length)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($chars) - 1;
        $string = '';
        while (strlen($string) < $length) {
            $string .= substr($chars, mt_rand(0, $max), 1);
        }
        return $string;
    }

    public function _readVerbatim($match)
    {
        do {
            $id = $this->_getRandomString(8);
        } while (isset($this->_verbatims[$id]));
        $this->_verbatims[$id] = [
            'name' => $match['name'],
            'content' => $match['content'],
        ];
        return "\\verbatim" . $id . " ";
    }

    protected $_token;
    protected $_tokenQueue = [];

    /**
     * Read next token from lexer.
     */
    protected function _next() // {{{
    {
        if (empty($this->_tokenQueue)) {
            // token queue is empty, get next token from lexer and return it
            // without modyfying the queue
            return $this->_token = $this->_lexer->next();
        }
        $this->_token = array_shift($this->_tokenQueue);
        return $this->_token;
    } // }}}

    /**
     * Peek at the next token.
     */
    protected function _peek() // {{{
    {
        if (empty($this->_tokenQueue)) {
            $next = $this->_lexer->next();
            if ($next) {
                $this->_tokenQueue[] = $next;
            } else {
                // no more tokens
                return false;
            }
        }
        return $this->_tokenQueue[0];
    } // }}}

    /**
     * Get current token.
     */
    protected function _current() // {{{
    {
        return $this->_token;
    } // }}}

    /**
     * Put token so that it will be loaded in the next call of _next()
     */
    protected function _unget(array $token) // {{{
    {
        array_unshift($this->_tokenQueue, $token);
        return $this;
    } // }}}

    public function parse($str)
    {
        $this->_verbatims = [];

        // smart comments: when a digit precedes a percent sign it is not
        // considered as start of comment
        $str = preg_replace('/([0-9])%/', '\1\\%', $str);

        // echo mb_strlen(preg_replace('/[^\d\p{L}]/u', '', $str)) / 5;

        // transform string for better tokenization
        // extract verbatims to ensure their whitespaces remain unchanged
        // * (greedy), *? (lazy)

        foreach ($this->_environs as $name => $spec) {
            if (!isset($spec['verbatim']) || !$spec['verbatim']) {
                continue;
            }

            // prepare name for regex
            $name = preg_quote($name, '/');

            // if environment has starred version, add match for optional star
            if (isset($spec['starred']) && $spec['starred']) {
                $name .= '\\*?';
            }

            // negative lookbehind to make sure \begin is not escaped
            $rx = '/(?<!\\\\)\\\\begin\s*\{(?P<name>' . $name . ')\}(?P<content>(.|\s)*?)\\\\end\s*\{\1\}/';

            $str = preg_replace_callback($rx, [$this, '_readVerbatim'], $str);
        }

        $this->_lexer = new Lexer($str);
        $root = new Node(self::TYPE_DOCUMENT);
        $this->_parseExprList($root, null, self::MODE_TEXT);

        // scan parsed tree in infix mode, assign numberings and refs and labels

        return $root;
    }

    /**
     * @param string $stopAtToken
     * @param string $state
     * @return array
     */
    protected function _parseExprList(Node $parent, $stopAtToken, $state, $environ = null) // {{{
    {
        $tree = [];
        while (false !== ($token = $this->_peek())) {
            if ($token['value'] === $stopAtToken) {
                // consume terminating token
                $this->_next();
                break;
            }
            $node = $this->_parseExpr($state, $environ);
            if ($node) {
                $parent->appendChild($node);
            }
        }
        return $tree;
    } // }}}

    protected function _parseExpr($state, $environ = null) // {{{
    {
        $token = $this->_next();
        if ($token) {
            switch ($token['type']) {
                case Lexer::TYPE_CSYMBOL:
                case Lexer::TYPE_CWORD:
                    return $this->_parseControl($token, $state, $environ);

                case Lexer::TYPE_SPECIAL:
                    return $this->_parseSpecial($token, $state, $environ);

                case Lexer::TYPE_SPACE:
                case Lexer::TYPE_TEXT:
                    return $this->_parseText($token, $state);

                case Lexer::TYPE_COMMENT:
                    $this->_skipSpaces();
                    break;

                default:
                    break;
            }
        }

        return false;
    } // }}}

    /**
     * @param string $type
     * @param int $mode
     * @param string $environ
     * @return Node
     */
    protected function _createNode($type, $mode, $environ = null) // {{{
    {
        return new Node($type, [
            'mode' => intval($mode),
            'environ' => null === $environ ? null : strval($environ),
        ]);
    } // }}}

    /**
     * @param string $name
     *     name of a tested environment
     * @param int $mode
     *     mode the tested environment is encountered in
     * @param string $environ
     *     OPTIONAL name of a parent environment
     * @return Node
     * @throws Exception
     *     when environment is encountered in invalid mode or
     *     when environment can't be nested within the parent environment
     */
    protected function _createEnviron($name, $mode, $environ = null) // {{{
    {
        assert(($mode & ($mode - 1)) === 0); // mode must be a power of 2

        $math = false;
        $args = [];

        if (isset($this->_environs[$name])) {
            $spec = $this->_environs[$name];

            // if mode specification is present, check if it matches
            // given mode flag
            if (isset($spec['mode']) && !($spec['mode'] & $mode)) {
                throw new Exception('Environment in invalid mode');
            }

            // if parent environ and environs spec for environ of given name
            // are given, check if the parent environ is a valid container
            if (null !== $environ &&
                (empty($spec['environs']) ||
                    !in_array($environ, (array)$spec['environs'], true))
            ) {
                throw new Exception('Environment ' . $name . ' cannot be nested in ' . $environ . ' environment');
            }

            // check if this environ is an alias for a math mode (i.e. math
            // or displaymath), if so, prepare math node instead of environ node
            $math = isset($spec['math']) && $spec['math'];

            // parse args, will be placed as environs first children, with
            // no spaces between them, btw: \begin{tabular}c is a perfectly
            // correct specification for a single-column table.
            $nargs = isset($spec['numArgs']) ? intval($spec['numArgs']) : 0;
            while (count($args) < $nargs) {
                if (false === ($arg = $this->_parseArg($mode, $environ))) {
                    $arg = $this->_createNode(self::TYPE_GROUP, $mode);
                }
                $args[] = $arg;
            }
        } elseif ($this->_skipUndefinedEnvirons) {
            throw new Exception(sprintf('Environment %s undefined', $name));
        }

        $node = $this->_createNode(self::TYPE_ENVIRON, $mode, $environ);
        $node->value = $name;

        if ($math) {
            $node->math = $math;
        }

        foreach ($args as $arg) {
            $node->appendChild($arg);
        }

        return $node;
    } // }}}

    /**
     * @throw Exception if mode is different than MODE_TEXT and math delimiters
     *                  are found
     */
    protected function _tryParseMathControl($token, $mode, $environ = null) // {{{
    {
        // if in text mode try first to parse math
        // predefined delimiters: left, right, inline
        $mathControls = [
            ['\\(', '\\)', true],
            ['\\[', '\\]', false],
        ];
        foreach ($mathControls as $pair) {
            if ($token['value'] === $pair[0]) {
                if ($mode === self::MODE_TEXT) {
                    $node = $this->_createNode(self::TYPE_MATH, $mode, $environ);
                    $node->inline = $pair[2];

                    $this->_parseExprList($node, $pair[1], self::MODE_MATH, $environ);

                    return $node;
                } else {
                    // math delimiter detected in invalid mode, stop processing
                    // ! LaTeX Error: Bad math environment delimiter.
                    throw new Exception('Math delimiter in invalid mode');
                }
            }
        }

        // no math found
        return false;
    } // }}}

    /**
     * Parse verbatim placeholder.
     *
     * @param array $token
     * @param int $mode
     * @param string $environ OPTIONAL
     * @return Node
     * @throws Exception
     */
    protected function _tryParseVerbatimControl($token, $mode, $environ = null) // {{{
    {
        $value = $token['value'];

        if (!strncmp($value, '\\verbatim', 9)) {
            // \verbatim prefix matched, check if this is indeed a placeholder
            $id = substr($value, 9);
            if (isset($this->_verbatims[$id])) {
                $name = $this->_verbatims[$id]['name'];
                $node = $this->_createEnviron($name, $mode, $environ);

                $verb = $this->_createNode(self::TYPE_VERBATIM, $mode, $name);
                $verb->value = $this->_verbatims[$id]['content'];

                $node->addChild($verb);

                return $node;
            }
        }

        return false;
    } // }}}

    /**
     * Parse control sequence
     * @return false|Node
     */
    protected function _parseControl($token, $mode, $environ = null) // {{{
    {
        $value = $token['value'];

        try {
            $node = $this->_tryParseMathControl($token, $mode, $environ);
            if ($node) {
                return $node;
            }
        } catch (Exception $e) {
            return false;
        }

        try {
            $node = $this->_tryParseVerbatimControl($token, $mode, $environ);
            if ($node) {
                return $node;
            }
        } catch (Exception $e) {
            return false;
        }

        switch ($value) {
            case '\\begin':
                if (false !== ($name = $this->_parseEnvName())) {
                    try {
                        $node = $this->_createEnviron($name, $mode, $environ);

                        if ($node->math) {
                            $this->_parseExprList($node, '\\end', self::MODE_MATH, $environ);
                        } else {
                            $this->_parseExprList($node, '\\end', $mode, $name);
                        }

                        // consume environment name, don't care if this succeeds
                        // or not
                        $this->_parseEnvName();

                        return $node;

                    } catch (Exception $e) {
                        // environ in invalid mode or invalid environ nesting
                    }
                }
                return false;

            case '\\end':
                // \end with no \begin, skip environ name
                $this->_parseEnvName();
                return false;

            case '\\]':
            case '\\)':
                // unmatched math delimiter, skip
                return false;
        }

        // skip space after control word (before parsing arguments)
        //
        // "When a space comes after a control word (an all-letter control
        // sequence), it is ignored by TeX; i.e., it is not considered to be
        // a "real" space belonging to the manuscript that is being typeset.
        // But when a space comes after a control symbol, it's truly a space."
        //
        // Donald E. Knuth, "TeXbook", Chapter 3
        //
        // Skip all spaces and comments occuring after this token, if this
        // token is a control word.
        if ($token['type'] === Lexer::TYPE_CWORD) {
            $this->_skipSpaces();
        }

        $mathWrapper = null;

        $nodeMode = $mode;
        $nodeArgs = [];
        $nodeOptArgs = [];
        $nodeStarred = false;

        // validate control sequence and parse arguments
        if (isset($this->_commands[$value])) {
            $spec = $this->_commands[$value];

            // check if this command requires an environment, if so, check
            // if current environment is among listed ones
            if (isset($spec['environs']) &&
                !in_array($environ, (array)$spec['environs'], true)
            ) {
                return false;
            }

            // check if command is used in proper mode
            if (isset($spec['mode']) && !($spec['mode'] & $mode)) {
                // when math mode command is encountered in text mode, wrap it
                // in inline math mode (never the other way around).
                if ($spec['mode'] & self::MODE_MATH) {
                    // We're outside math mode here.
                    $nodeMode = self::MODE_MATH;
                    $mathWrapper = $this->_createNode(self::TYPE_MATH, $mode);
                    $mathWrapper->inline = true;
                } else {
                    return false;
                }
            }

            // check if this command can appear in a starred version, if so,
            // parse any the following asterisk token
            if ((isset($spec['starred']) && $spec['starred']) &&
                ($next = $this->_peek()) &&
                ($next['type'] === Lexer::TYPE_TEXT) &&
                (0 === strncmp($next['value'], '*', 1))
            ) {
                $this->_next();
                $nodeStarred = true;
                // remove asterisk from the beginning of token value, no need
                // to use mbstring functions
                $next['value'] = substr($next['value'], 1);
                if (strlen($next['value'])) {
                    $this->_unget($next);
                }
            }

            // parse optional arguments
            $numOptArgs = isset($spec['numOptArgs']) ? intval($spec['numOptArgs']) : 0;
            $parseArgs = isset($spec['parseArgs']) ? $spec['parseArgs'] : true;

            while (count($nodeOptArgs) < $numOptArgs) {
                if (false !== ($arg = $this->_parseOptArg($nodeMode, $environ, $parseArgs))) {
                    $nodeOptArgs[] = $arg;
                } else {
                    break;
                }
            }

            // parse arguments
            $numArgs = isset($spec['numArgs']) ? intval($spec['numArgs']) : 0;

            while (count($nodeArgs) < $numArgs) {
                if (false === ($arg = $this->_parseArg($nodeMode, $environ, $parseArgs))) {
                    // no argument found, create an artificial one
                    $arg = $this->_createNode(self::TYPE_GROUP, $nodeMode);
                }
                $nodeArgs[] = $arg;
            }
        } elseif ($this->_skipUndefinedCommands) {
            return false;
        }

        $node = $this->_createNode(self::TYPE_COMMAND, $nodeMode, $environ);
        $node->value = $value;

        if ($token['type'] === Lexer::TYPE_CSYMBOL) {
            $node->symbol = true; // control symbol
        }

        if ($nodeStarred) {
            $node->starred = $nodeStarred;
        }

        foreach ($nodeOptArgs as $arg) {
            $node->appendChild($arg);
        }

        foreach ($nodeArgs as $arg) {
            $node->appendChild($arg);
        }

        if ($mathWrapper) {
            $mathWrapper->appendChild($node);
            return $mathWrapper;
        }

        return $node;
    } // }}}

    /**
     * Skip spaces and comments starting from the current lexer position.
     *
     * After this function has run current token, if exists, is neither space
     * nor comment.
     */
    protected function _skipSpaces() // {{{
    {
        while ($next = $this->_peek()) {
            if ($next['type'] === Lexer::TYPE_SPACE ||
                $next['type'] === Lexer::TYPE_COMMENT
            ) {
                $this->_next();
            } else {
                break;
            }
        }
    } // }}}

    protected function _parseArg($mode, $environ, $parseArgs = true) // {{{
    {
        $this->_skipSpaces();

        if ($next = $this->_peek()) {
            switch ($next['type']) {
                case Lexer::TYPE_SPECIAL:
                    switch ($next['value']) {
                        case '{':
                            // if args are not to be parsed consume all contents up to the
                            // first encountered right curly bracket
                            if (!$parseArgs) {
                                $group = $this->_createNode(self::TYPE_GROUP, $mode);
                                $this->_next();
                                $text = '';
                                while ($next = $this->_peek()) {
                                    if ($next['type'] === Lexer::TYPE_SPECIAL
                                        && $next['value'] === '}') {
                                        $this->_next();
                                        break;
                                    }

                                    $text .= $next['value'];
                                    $this->_next();
                                }
                                $node = $this->_createNode(self::TYPE_VERBATIM, $mode);
                                $node->value = $text;
                                $node->appendTo($group);

                                return $group;
                            }

                            // found group
                            $this->_next();

                            $group = $this->_createNode(self::TYPE_GROUP, $mode);

                            // TODO stop at first encountered \par control
                            $this->_parseExprList($group, '}', $mode, $environ);

                            return $group;

                        case '[':
                        case ']':
                            // square brackets may be treated as text (they are returned as
                            // specials to make easier parsing of optional parameters).
                            // Encountered bracket, not enveloped in a pair of curly brackets
                            // forms a separate group.
                            $this->_next();

                            $group = $this->_createNode(self::TYPE_GROUP, $mode);

                            $node = $this->_createNode(self::TYPE_TEXT, $mode);
                            $node->value = $next['value'];
                            $node->appendTo($group);

                            return $group;

                        default:
                            // other specials (~ ^ _ & # $) are silently ignored
                            break;
                    }
                    break;

                case Lexer::TYPE_TEXT:
                    // found text token, extract first character, leave the
                    // rest of its value for further processing
                    $this->_next();

                    $group = $this->_createNode(self::TYPE_GROUP, $mode);

                    $node = $this->_createNode(self::TYPE_TEXT, $mode);
                    $node->value = mb_substr($next['value'], 0, 1);
                    $node->appendTo($group);

                    $next['value'] = mb_substr($next['value'], 1);
                    if (mb_strlen($next['value'])) {
                        $this->_unget($next);
                    }

                    return $group;

                case Lexer::TYPE_CWORD:
                case Lexer::TYPE_CSYMBOL:
                    // found control sequence

                    if ($next['value'] === '\\par') {
                        // Runaway argument?
                        // ! Paragraph ended before command was complete.
                        return false;
                    }

                    $this->_next();

                    $group = $this->_createNode(self::TYPE_GROUP, $mode);

                    if (($node = $this->_parseControl($next, $mode, $environ))) {
                        $node->appendTo($group);
                    }

                    return $group;
            }
        }

        return false;
    } // }}}

    /**
     * Try and parse optional argument. Optional argument must be delimited
     * with square brackets, otherwise it is ignored.
     */
    protected function _parseOptArg($state, $environ) // {{{
    {
        $this->_skipSpaces();

        if (($next = $this->_peek()) &&
            ($next['type'] === Lexer::TYPE_SPECIAL) &&
            ($next['value'] === '[')
        ) {
            $this->_next();

            $group = $this->_createNode(self::TYPE_GROUP, $state);
            $group->optional = true;

            // TODO stop at first encountered \par control
            $this->_parseExprList($group, ']', $state | self::STATE_OPT_ARG, $environ);

            return $group;
        }

        return false;
    } // }}}

    /**
     * This method will consume all valid tokens, first invalid token
     * encountered will be put back to lexer.
     *
     * @return string|false
     */
    protected function _parseEnvName() // {{{
    {
        while (false !== ($next = $this->_peek())) {
            if ($next['type'] === Lexer::TYPE_SPACE ||
                $next['type'] === Lexer::TYPE_COMMENT
            ) {
                // 1. skip spaces and comments
                $this->_next();
                continue;

            } elseif ($next['value'] !== '{') {
                // 2A. first encountered non-space token is not a curly bracket
                // Since start of group was expected, this token breaks opening
                // of an environment. Give it back and report failure.
                break;

            } else {
                // 2B. first encountered non-space token is a curly bracket that
                //     begins a group containing environment name, skip it
                $this->_next();

                // Names of environmens in LaTeX may contain any characters,
                // any curly brackets must be matched.

                $par = 1;   // unmatched curly brackets counter
                $name = ''; // environment name

                while (false !== ($next = $this->_next())) {
                    if ($next['value'] === '{') {
                        ++$par;
                    } elseif ($next['value'] === '}') {
                        --$par;
                        if (!$par) {
                            // last required right curly bracket
                            break;
                        }
                    }
                    $name .= $next['value'];
                }
                if (strlen($name)) {
                    return $name;
                }
            }
        }

        // no valid environment name was found
        return false;
    } // }}}

    /**
     * Build text node starting from current token and by appending any
     * following text, space and square bracket tokens.
     *
     * @param Node $parent
     * @param array $token
     */
    protected function _parseText($token, $state) // {{{
    {
        $value = $token['value'];

        // concatenate output as long as next token is TEXT, SPACE or square
        // brackets
        while ($next = $this->_peek()) {
            if ($this->_isText($next, $state)) {
                $value .= $next['value'];
                $this->_next();
            } else {
                break;
            }
        }

        $node = $this->_createNode(self::TYPE_TEXT, $state);
        $node->value = $value;
        return $node;
    } // }}}

    /**
     * @param Node $parent
     * @param array $token
     * @param int $state
     * @param string $environ
     */
    protected function _parseSpecial($token, $state, $environ) // {{{
    {
        $value = $token['value'];
        switch ($value) {
            case '{':
                $node = $this->_createNode(self::TYPE_GROUP, $state);
                $this->_parseExprList($node, '}', $state);
                return $node;

            case '}':
                // unmatched right curly bracket, skip
                break;

            case '$':
                if ($state & self::STATE_TEXT) {
                    if (($next = $this->_peek())) {
                        $node = new Node(self::TYPE_MATH);
                        $node->mode = $state;
                        if ($next['value'] === '$') { // displaymath
                            $node->inline = false;
                            $this->_next(); // consume second dollar

                            // consume expressions up to first double dollars
                            // encountered
                            do {
                                $this->_parseExprList($node, '$', self::MODE_MATH);
                                $next = $this->_peek();
                                if ($next && $next['value'] === '$') {
                                    // second terminating dollar found, consume
                                    // it and stop looping
                                    $this->_next();
                                    break;
                                }
                            } while ($next);
                        } else {
                            $node->inline = true;
                            $this->_parseExprList($node, '$', self::MODE_MATH);
                        }

                        return $node;
                    }
                    // unterminated document (and math mode)
                }
                break;

            case '[':
            case ']':
                // square brackets that are not part of optional arguments
                // (those are handled when parsing control sequences)
                while ($next = $this->_peek()) {
                    if ($this->_isText($next, $state)) {
                        $value .= $next['value'];
                        $this->_next();
                    } else {
                        break;
                    }
                }

                $node = $this->_createNode(self::TYPE_TEXT, $state);
                $node->value = $value;
                return $node;

            case '^':
            case '_':
                // subscript and superscript, require math mode
                if ((self::STATE_MATH & $state) && ($arg = $this->_parseArg($state, $environ))) {
                    $node = $this->_createNode(self::TYPE_SPECIAL, $state);
                    $node->value = $value;
                    $node->appendChild($arg);
                    return $node;
                }
                break;

            /** @noinspection PhpMissingBreakStatementInspection */
            case '&': // TODO may occur only in table
                if (empty($environ)) {
                    // not in environment, escape it
                    $node = $this->_createNode(self::TYPE_COMMAND, $state);
                    $node->symbol = true; // control symbol \&
                    $node->value = '\\&';
                    return $node;
                }
            // otherwise fall through to get special

            case '~':
                $node = $this->_createNode(self::TYPE_SPECIAL, $state);
                $node->value = $value;
                return $node;

            case '#':
                // currently not supported
                break;
        }

        return false;
    } // }}}

    /**
     * @param array $token
     * @return bool
     */
    protected function _isText($token, $state) // {{{
    {
        $type = $token['type'];

        return $type === Lexer::TYPE_TEXT
            || $type === Lexer::TYPE_SPACE
            || ($type === Lexer::TYPE_SPECIAL
                && ($token['value'] === '[' || (
                        // right square bracket is treated as special when
                        // encountered during parsing of optional arguments
                        $token['value'] === ']' && !($state & self::STATE_OPT_ARG)
                    ))
            );
    } // }}}
}
