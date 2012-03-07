<?php
namespace li3_doctrine2\models;

class ValidateException extends \Exception {
	protected $entity;
	protected $errors;

	public function __construct($entity) {
		if ($entity instanceof BaseEntity) {
			$this->entity = $entity;
			$this->errors = $entity->errors();
		} else {
			$this->errors = $entity;
		}

		$message = '';
		if (!empty($this->errors)) {
			$first = current($this->errors);
			$message = is_array($first) ? current($first) : $first;
		}
		parent::__construct($message);
	}

	public function getEntity() {
		return $this->entity;
	}

	public function getErrors() {
		return $this->errors();
	}
}
?>
