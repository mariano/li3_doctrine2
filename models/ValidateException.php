<?php
namespace li3_doctrine2\models;

class ValidateException extends \Exception {
    protected $_errors;

    public function __construct($field, $error = null) {
        $errors = $field;
        if (!is_array($field)) {
            $errors = array($field => array($error));
        }
        $this->_errors = $errors;
        $message = '';
        if (!empty($this->_errors)) {
            $message = current(current($this->_errors));
        }
        parent::__construct($message);
    }

    public function getErrors() {
        return $this->_errors;
    }
}
?>
