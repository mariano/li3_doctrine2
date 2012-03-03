<?php
namespace li3_doctrine2\models;

class ValidateException extends \Exception {
	protected $entity;

	public function __construct(BaseEntity $entity) {
		$this->entity = $entity;
		$errors = $this->getErrors();
		$message = '';
		if (!empty($errors)) {
			$message = current(current($errors));
		}
		parent::__construct($message);
	}

	public function getEntity() {
		return $this->entity;
	}

	public function getErrors() {
		return $this->entity->errors();
	}
}
?>
