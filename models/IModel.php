<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\models;

/**
 * This interface defines the basic behavior a Doctrine model should implement
 * to be able to use Lithium's validation & form system.
 */
interface IModel {
	/**
	 * Get the entity manager for this model
	 *
	 * @return EntityManager entity manager
	 */
	public static function getEntityManager();

	/**
	 * Get the repository for this model
	 *
	 * @return EntityRepository entity repository
	 */
	public static function getRepository();

	/**
	 * Returns the model which this entity is bound to.
	 * Usually this would be the fully qualified class name this method belongs
	 * to.
	 *
	 * @return string The fully qualified model class name.
	 */
	public function model();

	/**
	 * A flag indicating whether or not this record exists.
	 *
	 * @return boolean `True` if the record was `read` from the data-source, or has been `create`d
	 *		   and `save`d. Otherwise `false`.
	 */
	public function exists();

   /**
	 * Allows several properties to be assigned at once, i.e.:
	 * {{{
	 * $record->set(array('title' => 'Lorem Ipsum', 'value' => 42));
	 * }}}
	 *
	 * @param array $data An associative array of fields and values to assign to this instance.
	 */
	public function set(array $data);

	/**
	 * Access the data fields of the record. Can also access a $named field.
	 * Only returns data for fields that have a getter method defined.
	 *
	 * @param string $name Optionally included field name.
	 * @return mixed Entire data array if $name is empty, otherwise the value from the named field.
	 */
	public function data($name = null);

	/**
	 * Perform validation
	 *
	 * @see lithium\data\Model::validates()
	 * @param array $options Options
	 * @return boolean Returns `true` if all validation rules on all fields succeed, otherwise
	 *		   `false`. After validation, the messages for any validation failures
	 *		   are accessible through the `errors()` method.
	 */
	public function validates(array $options = array());

	/**
	 * Access the errors of the record.
	 *
	 * @param array|string $field If an array, overwrites `$this->_errors`. If a string, and
	 *		  `$value` is not `null`, sets the corresponding key in `$this->_errors` to `$value`.
	 * @param string $value Value to set.
	 * @return mixed Either the `$this->_errors` array, or single value from it.
	 */
	public function errors($field = null, $value = null);
}
?>
