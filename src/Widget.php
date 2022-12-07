<?php

use Totoprayogo\Codeigniter3\Template\Partial;

class Widget extends Partial
{
    /** (non-PHPdoc)
     * @see Partial::content()
     */
    public function content()
    {
        if (! $this->_cached) {
            if (method_exists($this, 'display')) {
                // capture output
                ob_start();
                $this->display($this->_args);
                $buffer = ob_get_clean();

                // if no content is produced but there was direct ouput we set
                // that output as content
                if (! $this->_content && $buffer) {
                    $this->set($buffer);
                }
            }
        }

        return parent::content();
    }
}
