<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\extensions\data\source;

use lithium\core\Environment;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Tools\Setup;

/**
 * This datasource provides integration of Doctrine2 models
 */
class Doctrine extends \lithium\core\Object {
	/**
	 * Connection settings for Doctrine
	 *
	 * @var array
	 */
	protected $connectionSettings;

	/**
	 * Doctrine's configuration
	 *
	 * @var object
	 */
	protected $configuration;

	/**
	 * Doctrine's event manager
	 *
	 * @var object
	 */
	protected $eventManager;

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
			'proxyNamespace' => 'proxies'
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
	 * Initialize datasource
	 */
	protected function _init() {
		$this->configuration = Setup::createAnnotationMetadataConfiguration(
			(array) $this->_config['models'],
			Environment::is('development'),
			$this->_config['proxies']
		);
		$this->configuration->setProxyNamespace($this->_config['proxyNamespace']);

		$this->eventManager = new \Doctrine\Common\EventManager();
		$this->eventManager->addEventListener(array(
			Events::postLoad,
			Events::prePersist,
			Events::preUpdate
		), $this);
	}

	/**
	 * Create an entity manager
	 *
	 * @param array $params Parameters
	 * @return object Entity manager
	 * @filter
	 */
	protected function createEntityManager() {
		$connection = $this->connectionSettings;
		$configuration = $this->configuration;
		$eventManager = $this->eventManager;
		$params = compact('connection', 'configuration', 'eventManager');
		return $this->_filter(__METHOD__, $params,
			function($self, $params) {
				return \Doctrine\ORM\EntityManager::create(
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
			$this->entityManager = $this->createEntityManager();
		}
		return $this->entityManager;
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
}
?>
