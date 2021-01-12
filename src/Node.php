<?php

namespace Xopoc14\PhpLatex;

/**
 * Class representing AST node of a parsed document.
 */
class Node
{
    protected $_type;
    protected $_props;
    protected $_children = [];

    /**
     * @param mixed $type
     * @param array $props
     */
    public function __construct($type, array $props = null)
    {
        $this->_type = $type;

        // _props and _children properties are lazily-initialized
        // on first write

        if (null !== $props) {
            $this->setProps($props);
        }
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return Node
     */
    public function addChild(Node $node)
    {
        return $this->appendChild($node);
    }

    public function appendChild(Node $child)
    {
        $this->_children[] = $child;
        return $this;
    }

    public function appendTo(Node $parent)
    {
        $parent->appendChild($this);
        return $this;
    }

    /**
     * Retrieves the child node corresponding to the specified index.
     *
     * @param  int $index   The zero-based index of the child
     * @return Node
     */
    public function getChild($index)
    {
        return isset($this->_children[$index]) ? $this->_children[$index] : null;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->_children;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return (bool) count($this->_children);
    }

    /**
     * @return Node
     */
    public function setProps(array $props)
    {
        foreach ($props as $key => $value) {
            $this->setProp($key, $value);
        }
        return $this;
    }

    /**
     * @param  string $key
     * @param  mixed $value
     * @return Node
     */
    public function setProp($key, $value)
    {
        if (null === $value) {
            // unsetting an unexistant element from an array does not trigger
            // "Undefined variable" notice, see:
            // http://us.php.net/manual/en/function.unset.php#77310
            unset($this->_props[$key]);
        } else {
            $this->_props[$key] = $value;
        }
        return $this;
    }

    /**
     * @param  string $key
     * @return mixed
     */
    public function getProp($key)
    {
        return isset($this->_props[$key]) ? $this->_props[$key] : null;
    }

    public function __set($key, $value)
    {
        $this->setProp($key, $value);
    }

    public function __get($key)
    {
        return $this->getProp($key);
    }

    public function __isset($key)
    {
        return $this->getProp($key) !== null;
    }
}
