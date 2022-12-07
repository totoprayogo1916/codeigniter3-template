<?php

namespace Totoprayogo\Codeigniter3\Template;

use Widget;

class Partial
{
    protected $_ci;
    protected $_content;
    protected $_name;
    protected $_cache_ttl = 0;
    protected $_cached    = false;
    protected $_identifier;
    protected $_trigger;
    protected $_args = [];

    /**
     * Construct with optional parameters
     *
     * @param array $args
     * @param mixed $name
     */
    public function __construct($name, $args = [])
    {
        $this->_ci   = &get_instance();
        $this->_args = $args;
        $this->_name = $name;
    }

    /**
     * Gives access to codeigniter's functions from this class if needed
     * This will be handy in extending classes
     *
     * @param string $index
     * @param mixed  $name
     */
    public function __get($name)
    {
        return $this->_ci->{$name};
    }

    /**
     * Alias methods
     *
     * @param mixed $name
     * @param mixed $args
     */
    public function __call($name, $args)
    {
        switch ($name) {
            case 'default':
                return call_user_func_array([$this, 'set_default'], $args);
                break;

            case 'add':
                return call_user_func_array([$this, 'append'], $args);
                break;
        }
    }

    /**
     * Returns the content when converted to a string
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->content();
    }

    /**
     * Returns the content
     *
     * @return string
     */
    public function content()
    {
        if ($this->_cache_ttl && ! $this->_cached) {
            $this->cache->save($this->cache_id(), $this->_content, $this->_cache_ttl);
        }

        return $this->_content;
    }

    /**
     * Overwrite the content
     *
     * @param mixed $content
     *
     * @return Partial
     */
    public function set()
    {
        if (! $this->_cached) {
            $this->_content = (string) $this->trigger(func_get_args());
        }

        return $this;
    }

    /**
     * Append something to the content
     *
     * @param mixed $content
     *
     * @return Partial
     */
    public function append()
    {
        if (! $this->_cached) {
            $this->_content .= (string) $this->trigger(func_get_args());
        }

        return $this;
    }

    /**
     * Prepend something to the content
     *
     * @param mixed $content
     *
     * @return Partial
     */
    public function prepend()
    {
        if (! $this->_cached) {
            $this->_content = (string) $this->trigger(func_get_args()) . $this->_content;
        }

        return $this;
    }

    /**
     * Set content if partial is empty
     *
     * @param mixed $default
     *
     * @return Partial
     */
    public function set_default($default)
    {
        if (! $this->_cached) {
            if (! $this->_content) {
                $this->_content = $default;
            }
        }

        return $this;
    }

    /**
     * Load a view inside this partial, overwrite if wanted
     *
     * @param string $view
     * @param array  $data
     * @param bool   $overwrite
     *
     * @return Partial
     */
    public function view($view, $data = [], $overwrite = false)
    {
        if (! $this->_cached) {

            // better object to array
            if (is_object($data)) {
                $array = [];

                foreach ($data as $k => $v) {
                    $array[$k] = $v;
                }
                $data = $array;
            }

            $content = $this->_ci->load->view($view, $data, true);

            if ($overwrite) {
                $this->set($content);
            } else {
                $this->append($content);
            }
        }

        return $this;
    }

    /**
     * Parses a view inside this partial, overwrite if wanted
     *
     * @param string $view
     * @param array  $data
     * @param bool   $overwrite
     *
     * @return Partial
     */
    public function parse($view, $data = [], $overwrite = false)
    {
        if (! $this->_cached) {
            if (! class_exists('CI_Parser')) {
                $this->_ci->load->library('parser');
            }

            // better object to array
            if (is_object($data)) {
                $array = [];

                foreach ($data as $k => $v) {
                    $array[$k] = $v;
                }
                $data = $array;
            }

            $content = $this->_ci->parser->parse($view, $data, true);

            if ($overwrite) {
                $this->set($content);
            } else {
                $this->append($content);
            }
        }

        return $this;
    }

    /**
     * Loads a widget inside this partial, overwrite if wanted
     *
     * @param string $name
     * @param array  $data
     * @param bool   $overwrite
     *
     * @return Partial
     */
    public function widget($name, $data = [], $overwrite = false)
    {
        if (! $this->_cached) {
            $widget = new Widget($name, $data);

            if ($overwrite) {
                $this->set($widget->content());
            } else {
                $this->append($widget->content());
            }
        }

        return $this;
    }

    /**
     * Enable cache with TTL, default TTL is 60
     *
     * @param int   $ttl
     * @param mixed $identifier
     */
    public function cache($ttl = 60, $identifier = '')
    {
        if (! class_exists('CI_Cache')) {
            $this->_ci->load->driver('cache', ['adapter' => 'file']);
        }

        $this->_cache_ttl  = $ttl;
        $this->_identifier = $identifier;

        if ($cached = $this->_ci->cache->get($this->cache_id())) {
            $this->_cached  = true;
            $this->_content = $cached;
        }

        return $this;
    }

    /**
     * Used for cache identification
     *
     * @return string
     */
    private function cache_id()
    {
        if ($this->_identifier) {
            return $this->_name . '_' . $this->_identifier . '_' . md5(static::class . implode('', $this->_args));
        }

        return $this->_name . '_' . md5(static::class . implode('', $this->_args));
    }

    /**
     * Trigger returns the result if a trigger is set
     *
     * @param array $args
     *
     * @return string
     */
    public function trigger($args)
    {
        if (! $this->_trigger) {
            return implode('', $args);
        }

        return ($this->_trigger)(...$args);
    }

    /**
     * Bind a trigger function
     * Can be used like bind($this, "function") or bind("function")
     *
     * @param mixed $arg
     */
    public function bind()
    {
        if ($count = func_num_args()) {
            if ($count >= 2) {
                $args = func_get_args();
                $obj  = array_shift($args);
                $func = array_pop($args);

                foreach ($args as $trigger) {
                    $obj = $obj->{$trigger};
                }

                $this->_trigger = [$obj, $func];
            } else {
                $args           = func_get_args();
                $this->_trigger = reset($args);
            }
        } else {
            $this->_trigger = false;
        }
    }
}
