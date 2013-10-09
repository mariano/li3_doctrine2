<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\extensions\data\source;

use lithium\core\Environment;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Tools\Setup;

/**
 * This datasource provides integration of Doctrine2 models
 */
class Doctrine extends \lithium\data\Source {
	/**
	 * Connection settings for Doctrine
	 *
	 * @var array
	 */
	protected $connectionSettings;

	/**
	 * Doctrine's entity manager
	 *
	 * @var object
	 */
	protected $entityManager;

	/**
	 * Build data source
	 *
	 * @param array $config Configuration
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'models' => LITHIUM_APP_PATH . '/models',
			'proxies' => LITHIUM_APP_PATH . '/models/proxies',
			'proxyNamespace' => 'proxies',
			'cache' => null
		);
		$this->connectionSettings = array_diff_key($config, array_merge(
			$defaults, array(
				'type' => null,
				'adapter' => null,
				'login' => null,
				'filters' => null
			)
		));
		parent::__construct($config + $defaults);
	}

	/**
	 * Create an entity manager
	 *
	 * @param array $params Parameters
	 * @return object Entity manager
	 * @filter
	 */
	protected function createEntityManager() {
		$configuration = Setup::createAnnotationMetadataConfiguration(
			array($this->_config['models']),
			Environment::is('development'),
			$this->_config['proxies'],
			isset($this->_config['cache']) ? call_user_func($this->_config['cache']) : null
		);
		$configuration->setProxyNamespace($this->_config['proxyNamespace']);

		$eventManager = new EventManager();
		$eventManager->addEventListener(array(
			Events::postLoad,
			Events::prePersist,
			Events::preUpdate
		), $this);

		$connection = $this->connectionSettings;
		$params = compact('connection', 'configuration', 'eventManager');
		return $this->_filter(__METHOD__, $params,
			function($self, $params) {
				return EntityManager::create(
					$params['connection'],
					$params['configuration'],
					$params['eventManager']
				);
			}
		);
	}

	/**
	 * Get the entity manager
	 *
	 * @return object Entity Manager
	 */
	public function getEntityManager() {
		if (!isset($this->entityManager)) {
			$this->recreateEntityManager();
		}
		return $this->entityManager;
	}

	/**
	 * Recreate entity manager
	 */
	public function recreateEntityManager() {
		$this->entityManager = $this->createEntityManager();
	}

	/**
	 * Event executed after a record was loaded
	 *
	 * @param object $eventArgs Event arguments
	 */
	public function postLoad(LifecycleEventArgs $eventArgs) {
		$this->dispatchEntityEvent($eventArgs->getEntity(), 'onPostLoad',
			array($eventArgs)
		);
	}

	/**
	 * Event executed before a record is to be created
	 *
	 * @param object $eventArgs Event arguments
	 */
	public function prePersist(LifecycleEventArgs $eventArgs) {
		$this->dispatchEntityEvent($eventArgs->getEntity(), 'onPrePersist',
			array($eventArgs)
		);
	}

	/**
	 * Event executed before a record is to be updated
	 *
	 * @param object $eventArgs Event arguments
	 */
	public function preUpdate(PreUpdateEventArgs $eventArgs) {
		$this->dispatchEntityEvent($eventArgs->getEntity(), 'onPreUpdate',
			array($eventArgs)
		);
	}

	/**
	 * Dispatch an entity event
	 *
	 * @param object $entity Entity on where to dispatch event
	 * @param string $event Event name
	 * @param array $args Event arguments
	 */
	protected function dispatchEntityEvent($entity, $eventName, array $args) {
		if (
			method_exists($entity, $eventName) &&
			is_callable(array($entity, $eventName))
		) {
			call_user_func_array(array($entity, $eventName), $args);
		}
	}

	/**
	 * @todo
	 */
	public function connect() {}

	/**
	 * @todo
	 */
	public function disconnect() {}

	/**
	 * Returns a list of objects (sources) that models can bind to, i.e. a list of tables in the
	 * case of a database, or REST collections, in the case of a web service.
	 *
	 * @param string $class The fully-name-spaced class name of the object making the request.
	 * @return array Returns an array of objects to which models can connect.
	 * @todo
	 */
	public function sources($class = null) {}

	/**
	 * Gets the column schema for a given entity (such as a database table).
	 *
	 * @param mixed $entity Specifies the table name for which the schema should be returned, or
	 *        the class name of the model object requesting the schema, in which case the model
	 *        class will be queried for the correct table name.
	 * @param array $meta
	 * @return array Returns an associative array describing the given table's schema, where the
	 *         array keys are the available fields, and the values are arrays describing each
	 *         field, containing the following keys:
	 *         - `'type'`: The field type name
	 * @todo
	 */
	public function describe($entity, $schema = array(), array $meta = array()) {}

	/**
	 * Defines or modifies the default settings of a relationship between two models.
	 *
	 * @param $class the primary model of the relationship
	 * @param $type the type of the relationship (hasMany, hasOne, belongsTo)
	 * @param $name the name of the relationship
	 * @param array $options relationship options
	 * @return array Returns an array containing the configuration for a model relationship.
	 */
	public function relationship($class, $type, $name, array $options = array()) {}

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 * @todo
	 */
	public function create($query, array $options = array()) {}

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 * @todo
	 */
	public function read($query, array $options = array()) {}

	/**
	 * Updates a set of records in a concrete data store.
	 *
	 * @param mixed $query An object which defines the update operation(s) that should be performed
	 *        against the data store.  This can be a `Query`, a `RecordSet`, a `Record`, or a
	 *        subclass of one of the three. Alternatively, `$query` can be an adapter-specific
	 *        query string.
	 * @param array $options Options to execute, which are defined by the concrete implementation.
	 * @return boolean Returns true if the update operation was a success, otherwise false.
	 * @todo
	 */
	public function update($query, array $options = array()) {}

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 * @todo
	 */
	public function delete($query, array $options = array()) {}
}