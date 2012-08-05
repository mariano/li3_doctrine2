<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\models;

use lithium\util\Inflector;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use li3_doctrine2\models\ValidateException;

/**
 * This class can be used as the base class of your doctrine models, to allow
 * for lithium validation to work on doctrine models.
 */
abstract class BaseEntity extends \lithium\data\Entity implements IModel {
	/**
	 * Criteria for data validation.
	 *
	 * Example usage:
	 * {{{
	 * public $validates = array(
	 *	   'title' => 'please enter a title',
	 *	   'email' => array(
	 *		   array('notEmpty', 'message' => 'Email is empty.'),
	 *		   array('email', 'message' => 'Email is not valid.'),
	 *	   )
	 * );
	 * }}}
	 *
	 * @var array
	 */
	protected $validates = array();

	/**
	 * Class dependencies.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'connections' => 'lithium\data\Connections',
		'validator'   => 'lithium\util\Validator'
	);

	/**
	 * Connection name used for persisting / loading this record
	 *
	 * @var string
	 */
	protected static $connectionName = 'default';

	/**
	 * @var bool
	 */
	protected $_exists = FALSE;

	/**
	 * Constructor, instance at which the entity fields are loaded from
	 * doctrine's class metadata
	 */
	public function __construct() {
		$this->_model = get_called_class();
	}

	/**
	 * Get connection name
	 *
	 * @return string Connection name
	 */
	public static function getConnectionName() {
		return static::$connectionName;
	}

	/**
	 * Change the connection name
	 *
	 * @param string $connectionName Connection name
	 */
	public static function setConnectionName($connectionName) {
		static::$connectionName = $connectionName;
	}

	/**
	 * Get the entity manager linked to the connection defined in the property
	 * `$connectionName`
	 *
	 * @param string $connectionName Connection name, or use the property `$connectionName` if empty
	 * @see IModel::getEntityManager()
	 * @return \Doctrine\ORM\EntityManager entity manager
	 */
	public static function getEntityManager($connectionName = NULL) {
		static $entityManagers = array();
		if (!$connectionName) {
			$connectionName = static::getConnectionName();
		}
		if (!isset($entityManager[$connectionName])) {
			$connections = static::$_classes['connections'];
			$entityManagers[$connectionName] = $connections::get($connectionName)->getEntityManager();
		}
		return $entityManagers[$connectionName];
	}

	/**
	 * Get the repository for this model
	 *
	 * @param string $connectionName Connection name, or use the property `$connectionName` if empty
	 * @see IModel::getRepository()
	 * @return \Doctrine\ORM\EntityRepository entity repository
	 */
	public static function getRepository($connectionName = NULL) {
		return static::getEntityManager($connectionName)->getRepository(get_called_class());
	}

	/**
	 * Doctrine callback executed after a record was loaded
	 *
	 * @param \Doctrine\ORM\Event\LifecycleEventArgs $eventArgs Event arguments
	 * @return void
	 */
	public function onPostLoad(LifecycleEventArgs $eventArgs) {
		$this->_exists = TRUE;
	}

	/**
	 * Doctrine callback executed before persisting a new record
	 *
	 * @param \Doctrine\ORM\Event\LifecycleEventArgs $eventArgs Event arguments
	 * @throws ValidateException
	 * @return void
	 */
	public function onPrePersist(LifecycleEventArgs $eventArgs) {
		$this->_exists = FALSE;
		if (!$this->validates()) {
			throw new ValidateException($this);
		}
	}

	/**
	 * Doctrine callback executed before persisting an existing record
	 *
	 * @param \Doctrine\ORM\Event\PreUpdateEventArgs|object $eventArgs Event arguments
	 * @throws ValidateException
	 * @return void
	 */
	public function onPreUpdate(PreUpdateEventArgs $eventArgs) {
		$this->_exists = TRUE;
		if (!$this->validates()) {
			throw new ValidateException($this);
		}
	}

	/**
	 * Perform validation
	 *
	 * @see IModel::validates()
	 * @param array $options Options
	 * @return boolean Success
	 */
	public function validates(array $options = array()) {
		$defaults = array(
			'rules' => $this->validates,
			'events' => $this->exists() ? 'update' : 'create',
			'model' => get_called_class()
		);
		$options += $defaults;
		$validator = static::$_classes['validator'];

		$rules = $options['rules'];
		unset($options['rules']);

		if (!empty($rules) && $this->_errors = $validator::check($this->_getData(TRUE), $rules, $options)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Allows several properties to be assigned at once, i.e.:
	 * {{{
	 * $record->set(array('title' => 'Lorem Ipsum', 'value' => 42));
	 * }}}
	 *
	 * @see IModel::validates()
	 * @param array $data An associative array of fields and values to assign to this instance.
	 * @param array $whitelist Fields to allow setting
	 * @param bool $useWhitelist Set to false to ignore whitelist
	 * @throws \Exception
	 * @return void
	 */
	public function set(array $data, array $whitelist = array(), $useWhitelist = TRUE) {
		if (empty($data)) {
			return;
		} elseif ($useWhitelist && empty($whitelist)) {
			throw new \Exception('Must set whitelist of fields');
		}

		if ($useWhitelist) {
			$data = array_intersect_key($data, array_flip($whitelist));
		}

		foreach($data as $field => $value) {
			if (!is_string($field) || !in_array($field, $this->_getEntityFields())) {
				continue;
			}
			$method = 'set' . Inflector::camelize($field);
			if (method_exists($this, $method) && is_callable(array($this, $method))) {
				$this->{$method}($value);
			} else if (property_exists($this, $field)) {
				$this->{$field} = $value;
			}
		}
	}

	/**
	 * Access the data fields of the record. Can also access a $named field.
	 * Only returns data for fields that have a getter method defined.
	 *
	 * @see IModel::validates()
	 * @param string $name Optionally included field name.
	 * @param bool $allProperties If true, get also properties without getter methods
	 * @return mixed Entire data array if $name is empty, otherwise the value from the named field.
	 */
	public function data($name = NULL, $allProperties = FALSE) {
		$data = $this->_getData($allProperties);
		if (isset($name)) {
			return array_key_exists($name, $data) ? $data[$name] : NULL;
		}
		return $data;
	}

	/**
	 * Get the entity fields
	 *
	 * @throws \Exception
	 * @return array
	 */
	protected function _getEntityFields() {
		static $entityFields;
		if (!isset($entityFields)) {
			$entityFields = array_values(static::getEntityManager()->getClassMetadata(get_called_class())->fieldNames);
			if (empty($entityFields)) {
				throw new \Exception($class . ' does not seem to have fields defined');
			}
		}
		return $entityFields;
	}

	/**
	 * Get record data as an array
	 *
	 * @param bool $allProperties If true, get also properties without getter methods
	 * @return array Data
	 */
	protected function _getData($allProperties = FALSE) {
		$data = array();
		foreach($this->_getEntityFields() as $field) {
			$method = 'get' . Inflector::camelize($field);
			if (method_exists($this, $method) && is_callable(array($this, $method))) {
				$data[$field] = $this->{$method}();
			} elseif ($allProperties && property_exists($this, $field)) {
				$data[$field] = $this->{$field};
			}
		}
		if (isset($name)) {
			return array_key_exists($name, $data) ? $data[$name] : NULL;
		}
		return $data;
	}
}
?>
