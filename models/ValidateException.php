<?php
namespace li3_doctrine2\models;

class ValidateException extends \Exception {
	protected $entity;
	protected $errors;

	public function __construct($entity = null) {
		if (empty($entity)) {
			return parent::__construct();
		}

		if ($entity instanceof BaseEntity) {
			$this->entity = $entity;
			$this->errors = $entity->errors();
		} else if (is_array($entity)) {
			$this->errors = $entity;
		}

		$message = is_string($entity) ? $entity : '';
		if (!empty($this->errors)) {
			$first = current($this->errors);
			$message = is_array($first) ? current($first) : $first;
		}
		return parent::__construct($message);
	}

	public function getEntity() {
		return $this->entity;
	}

	public function setEntity($entity) {
		$this->entity = $entity;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function setErrors($errors) {
		$this->errors = $errors;
	}
}
?>
