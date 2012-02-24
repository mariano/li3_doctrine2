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
 * to be able to be used as the model for the Form auth session adapter.
 */
interface IUser {
	/**
	 * Access the data fields of the record. Can also access a $named field.
	 * Only returns data for fields that have a getter method defined.
	 *
	 * @param string $name Optionally included field name.
	 * @return mixed Entire data array if $name is empty, otherwise the value from the named field.
	 */
	public function data($name = null);
}
?>
