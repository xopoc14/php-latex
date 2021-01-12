<?php

namespace Xopoc14\PhpLatex\Filter;

// Paragraph container. Only non-empty paragraphs are stored,
// a paragraph cannot contain LF characters,
// line break or paragraph start must be explicitly given
class ParagraphList implements Countable, IteratorAggregate
{
    /**
     * @var array
     */
    protected $_paragraphs = []; // non-empty paragraphs

    /**
     * @var int
     */
    protected $_pos = 0;

    /**
     * @var bool
     */
    protected $_nl = false;

    public function addText($text)
    {
        $text = preg_replace('/\s+/', ' ', $text);

        if (strlen($text)) {
            // echo '[' . @$this->_paragraphs[$this->_pos] . '](', $text, ') -> ';

            if (isset($this->_paragraphs[$this->_pos])) {
                if ($this->_nl) {
                    if ($text !== ' ') {
                        $this->_nl = false;
                        $par = $this->_paragraphs[$this->_pos] . "\\\\\n" . $text;
                    } else {
                        // do nothing - do not append space-only string or line break
                        // wait for more text to come
                        $par = $text;
                    }
                } else {
                    // append new text to existing paragraph, merge spaces on the
                    // strings boundary into a single space
                    $par = $this->_paragraphs[$this->_pos] . $text;
                    $par = str_replace('  ', ' ', $par);
                }
            } else {
                // new paragraph must start with a non-space character,
                // no line break at the beginning of the paragraph, trailing
                // spaces are allowed (there will be no more than 2)
                $par = $text;
            }

            if (strlen($par)) {
                $this->_paragraphs[$this->_pos] = $par;
            }

            // echo '[' . @$this->_paragraphs[$this->_pos] . ']', "\n\n";
        }


        return $this;
    }

    public function breakLine()
    {
        if ($this->_nl) {
            $this->newParagraph();
        } elseif (isset($this->_paragraphs[$this->_pos]) && !ctype_space($this->_paragraphs[$this->_pos])) {
            // line break can only be placed in a non-empty paragraph
            $this->_nl = true;
        }
        return $this;
    }

    public function newParagraph()
    {
        $this->_nl = false;
        if (isset($this->_paragraphs[$this->_pos])) {
            ++$this->_pos;
        }
        return $this;
    }

    public function clear()
    {
        $this->_paragraphs = [];
        $this->_pos = 0;
        $this->_nl = false;
        return $this;
    }

    public function count()
    {
        return count($this->_paragraphs);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->_paragraphs);
    }

    public function __toString()
    {
        if (count($this->_paragraphs)) {
            return preg_replace('/[ ]+/', ' ', implode("\n\n", $this->_paragraphs)) . "\n\n";
        }
        return '';
    }

    public function toArray()
    {
        return $this->_paragraphs;
    }
}
