<?php

namespace Totoprayogo\Codeigniter3\Template;

class Template {

    /* default values */
    private $_template = 'template';
    private $_parser = FALSE;
    private $_cache_ttl = 0;
    private $_widget_path = '';

    private $_ci;
    private $_partials = array();

    /**
     * Construct with configuration array. Codeigniter will use the config file otherwise
     * @param array $config
     */
    public function __construct($config = array()) {
        $this->_ci = & get_instance();

        // set the default widget path with APPPATH
        $this->_widget_path = APPPATH . 'widgets/';

        if (!empty($config)) {
            $this->initialize($config);
        }

        log_message('debug', 'Template library initialized');
    }

    /**
     * Initialize with configuration array
     * @param array $config
     * @return Template
     */
    public function initialize($config = array()) {
        foreach ($config as $key => $val) {
            $this->{'_' . $key} = $val;
        }

        if ($this->_widget_path == '') {
            $this->_widget_path = APPPATH . 'widgets/';
        }

        if ($this->_parser && !class_exists('CI_Parser')) {
            $this->_ci->load->library('parser');
        }
    }

    /**
     * Set a partial's content. This will create a new partial when not existing
     * @param string $index
     * @param mixed $value
     */
    public function __set($name, $value) {
        $this->partial($name)->set($value);
    }

    /**
     * Access to partials for method chaining
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return $this->partial($name);
    }

    /**
     * Check if a partial exists
     * @param string $index
     * @return boolean
     */
    public function exists($index) {
        return array_key_exists($index, $this->_partials);
    }

    /**
     * Set the template file
     * @param string $template
     */
    public function set_template($template) {
        $this->_template = $template;
    }

    /**
     * Publish the template with the current partials
     * You can manually pass a template file with extra data, or use the default template from the config file
     * @param string $template
     * @param array $data
     */
    public function publish($template = FALSE, $data = array()) {
        if (is_array($template) || is_object($template)) {
            $data = $template;
        } else if ($template) {
            $this->_template = $template;
        }

        if (!$this->_template) {
            show_error('There was no template file selected for the current template');
        }

        if (is_array($data) || is_object($data)) {
            foreach ($data as $name => $content) {
                $this->partial($name)->set($content);
            }
        }

        unset($data);

        if ($this->_parser) {
            $this->_ci->parser->parse($this->_template, $this->_partials);
        } else {
            $this->_ci->load->view($this->_template, $this->_partials);
        }
    }

    /**
     * Create a partial object with an optional default content
     * Can be usefull to use straight from the template file
     * @param string $name
     * @param string $default
     * @return Partial
     */
    public function partial($name, $default = FALSE) {
        if ($this->exists($name)) {
            $partial = $this->_partials[$name];
        } else {
            // create new partial
            $partial = new Partial($name);
            if ($this->_cache_ttl) {
                $partial->cache($this->_cache_ttl);
            }

            // detect local triggers
            if (method_exists($this, 'trigger_' . $name)) {
                $partial->bind($this, 'trigger_' . $name);
            }

            $this->_partials[$name] = $partial;
        }

        if (!$partial->content() && $default) {
            $partial->set($default);
        }

        return $partial;
    }

    /**
     * Create a widget object with optional parameters
     * Can be usefull to use straight from the template file
     * @param string $name
     * @param array $data
     * @return Widget
     */
    public function widget($name, $data = array()) {
        $class = str_replace('.php', '', trim($name, '/'));

        // determine path and widget class name
        $path = $this->_widget_path;
        if (($last_slash = strrpos($class, '/')) !== FALSE) {
            $path += substr($class, 0, $last_slash);
            $class = substr($class, $last_slash + 1);
        }

        // new widget
        if(!class_exists($class)) {
            // try both lowercase and capitalized versions
            foreach (array(ucfirst($class), strtolower($class)) as $class) {
                if (file_exists($path . $class . '.php')) {
                    include_once ($path . $class . '.php');

                    // found the file, stop looking
                    break;
                }
            }
        }

        if (!class_exists($class)) {
            show_error("Widget '" . $class . "' was not found.");
        }

        return new $class($class, $data);
    }

    /**
     * Enable cache for all partials with TTL, default TTL is 60
     * @param int $ttl
     * @param mixed $identifier
     */
    public function cache($ttl = 60, $identifier = '') {
        foreach ($this->_partials as $partial) {
            $partial->cache($ttl, $identifier);
        }

        $this->_cache_ttl = $ttl;
    }

    // ---- TRIGGERS -----------------------------------------------------------------

    /**
     * Stylesheet trigger
     * @param string $source
     */
    public function trigger_stylesheet($url, $attributes = FALSE) {
        // array support
        if (is_array($url)) {
            $return = '';
            foreach ($url as $u) {
                $return .= $this->trigger_stylesheet($u, $attributes);
            }
            return $return;
        }

        if (!stristr($url, 'http://') && !stristr($url, 'https://') && substr($url, 0, 2) != '//') {
            $url = $this->_ci->config->item('base_url') . $url;
        }

        // legacy support for media
        if (is_string($attributes)) {
            $attributes = array('media' => $attributes);
        }

        if (is_array($attributes)) {
        	$attributeString = "";

        	foreach ($attributes as $key => $value) {
	        	$attributeString .= $key . '="' . $value . '" ';
        	}

            return '<link rel="stylesheet" href="' . htmlspecialchars(strip_tags($url)) . '" ' . $attributeString . '>' . "\n\t";
        } else {
            return '<link rel="stylesheet" href="' . htmlspecialchars(strip_tags($url)) . '">' . "\n\t";
        }
    }

    /**
     * Javascript trigger
     * @param string $source
     */
    public function trigger_javascript($url) {
        // array support
        if (is_array($url)) {
            $return = '';
            foreach ($url as $u) {
                $return .= $this->trigger_javascript($u);
            }
            return $return;
        }

        if (!stristr($url, 'http://') && !stristr($url, 'https://') && substr($url, 0, 2) != '//') {
            $url = $this->_ci->config->item('base_url') . $url;
        }

        return '<script src="' . htmlspecialchars(strip_tags($url)) . '"></script>' . "\n\t";
    }

    /**
     * Meta trigger
     * @param string $name
     * @param mixed $value
     * @param enum $type
     */
    public function trigger_meta($name, $value, $type = 'meta') {
        $name = htmlspecialchars(strip_tags($name));
        $value = htmlspecialchars(strip_tags($value));

        if ($name == 'keywords' and !strpos($value, ',')) {
            $content = preg_replace('/[\s]+/', ', ', trim($value));
        }

        switch ($type) {
            case 'meta' :
                $content = '<meta name="' . $name . '" content="' . $value . '">' . "\n\t";
                break;
            case 'link' :
                $content = '<link rel="' . $name . '" href="' . $value . '">' . "\n\t";
                break;
        }

        return $content;
    }

    /**
     * Title trigger, keeps it clean
     * @param string $name
     * @param mixed $value
     * @param enum $type
     */
    public function trigger_title($title) {
        return htmlspecialchars(strip_tags($title));
    }

    /**
     * Title trigger, keeps it clean
     * @param string $name
     * @param mixed $value
     * @param enum $type
     */
    public function trigger_description($description) {
        return htmlspecialchars(strip_tags($description));
    }

}
