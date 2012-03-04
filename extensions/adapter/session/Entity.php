<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\extensions\adapter\session;

use Doctrine\ORM\EntityManager;
use lithium\core\ConfigException;
use lithium\core\Libraries;
use lithium\util\Set;

/**
 * This session adapter offers a way to store session records in the database
 * using Doctrine for database interaction. To use this adapter you must have a
 * model, which can extend from the `BaseSession` model provided in this
 * plugin. If your model does not extend from `BaseSession`, you must either
 * specify the configuration option `entityManager`, or implement a static
 * method named `getEntityManager` in your model.
 *
 * Example model:
 *
 * {{{
 * /**
 *	* @Entity
 *	* @Table(name="sessions")
 *	*\/
 * class Session extends \li3_doctrine2\models\BaseSession {
 * }
 * }}}
 *
 * Once you have your session model, you must set your bootstrap/session.php to
 * use this adapter. For example:
 *
 * {{{
 * use lithium\storage\Session;
 * Session::config(array(
 *	   'default' => array(
 *		   'adapter' => 'li3_doctrine2\extensions\adapter\session\Entity',
 *		   'model' => 'app\models\Session',
 *	   )
 * ));
 * }}}
 */
class Entity {
	/**
	 * Settings for this session adapter
	 *
	 * @var array
	 */
	protected $config = array(
		'start' => true,
		'model' => null,
		'entityManager' => null,
		'expiration' => '+3 days',
		'ini' => array(
			'cookie_lifetime' => '0',
			'cookie_httponly' => false,
			'gc_divisor' => 100
		)
	);

	/**
	 * Entity manager
	 *
	 * @var object
	 */
	protected $entityManager;

	/**
	 * Entity (record) instance
	 *
	 * @var object
	 */
	protected $record;

	/**
	 * Repository for records
	 *
	 * @var object
	 */
	protected $repository;

	/**
	 * Sets up the adapter with the configuration assigned by the `Session` class.
	 *
	 * @param array $config Available configuration options for this adapter:
	 *				- `'config'` _string_: The name of the model that this adapter should use.
	 */
	public function __construct(array $config = array()) {
		$this->config = Set::merge($this->config, $config);

		if (empty($this->config['model']) || !class_exists($this->config['model'])) {
			throw new ConfigException("No valid model \"{$this->config['model']}\" available to use for Session interaction");
		} elseif (empty($this->config['entityManager']) && (
			!method_exists($this->config['model'], 'getEntityManager') ||
			!is_callable($this->config['model'] . '::getEntityManager')
		)) {
			throw new ConfigException("The session model {$this->config['model']} must define a getEntityManager() static method, or you must set the entityManager session config variable");
		}

		$reflection = new \ReflectionClass($this->config['model']);
		if (!$reflection->implementsInterface('li3_doctrine2\models\ISession')) {
			throw new ConfigException("The model {$this->config['model']} must implement ISession");
		}

		$this->entityManager = $this->config['entityManager'] ?:
			call_user_func($this->config['model'] . '::getEntityManager');
		if (!isset($this->entityManager) || !($this->entityManager instanceof EntityManager)) {
			throw new ConfigException('Not a valid entity manager');
		}

		$this->repository = $this->entityManager->getRepository($this->config['model']);

		foreach ($this->config['ini'] as $key => $config) {
			if (
				isset($this->config['ini'][$key]) &&
				ini_set("session.{$key}", $this->config['ini'][$key]) === false
			) {
				throw new ConfigException("Could not initialize the session variable {$key}");
			}
		}

		session_set_save_handler(
			array(&$this, '_open'),
			array(&$this, '_close'),
			array(&$this, '_read'),
			array(&$this, '_write'),
			array(&$this, '_destroy'),
			array(&$this, '_gc')
		);
		register_shutdown_function('session_write_close');

		if ($this->config['start']) {
			$this->_startup();
		}
	}

	/**
	 * Starts the session.
	 *
	 * @return boolean True if session successfully started (or has already been started),
	 *		   false otherwise.
	 */
	protected function _startup() {
		if (session_id()) {
			return true;
		}
		if (!isset($_SESSION)) {
			session_cache_limiter('nocache');
		}
		return session_start();
	}

	/**
	 * Sets or obtains the session ID.
	 *
	 * @param string $key Optional. If specified, sets the session ID to the value of `$key`.
	 * @return mixed Session ID, or `null` if the session has not been started.
	 */
	public function key($key = null) {
		if ($key) {
			return session_id($key);
		}
		return session_id() ?: null;
	}

	/**
	 * Obtain the status of the session.
	 *
	 * @return boolean True if $_SESSION is accessible and if a '_timestamp' key
	 *		   has been set, false otherwise.
	 */
	public function isStarted() {
		return (boolean) session_id();
	}

	/**
	 * Checks if a value has been set in the session.
	 *
	 * @param string $key Key of the entry to be checked.
	 * @param array $options Options array. Not used for this adapter method.
	 * @return closure Function returning boolean `true` if the key exists, `false` otherwise.
	 */
	public function check($key, array $options = array()) {
		if (!$this->isStarted() && !$this->_startup()) {
			throw new RuntimeException("Could not start session");
		}
		return function($self, $params) {
			return Set::check($_SESSION, $params['key']);
		};
	}

	/**
	 * Read a value from the session.
	 *
	 * @param null|string $key Key of the entry to be read. If no key is passed, all
	 *		  current session data is returned.
	 * @param array $options Options array. Not used for this adapter method.
	 * @return closure Function returning data in the session if successful, `false` otherwise.
	 */
	public function read($key = null, array $options = array()) {
		if (!$this->isStarted() && !$this->_startup()) {
			throw new RuntimeException("Could not start session");
		}

		return function($self, $params) {
			$key = $params['key'];
			if (!$key) {
				return $_SESSION;
			}
			if (strpos($key, '.') === false) {
				return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
			}
			$filter  = function($keys, $data) use (&$filter) {
				$key = array_shift($keys);
				if (isset($data[$key])) {
					return (empty($keys)) ?
						$data[$key] :
						$filter($keys, $data[$key]);
				}
			};
			return $filter(explode('.', $key), $_SESSION);
		};
	}

	/**
	 * Write a value to the session.
	 *
	 * @param string $key Key of the item to be stored.
	 * @param mixed $value The value to be stored.
	 * @param array $options Options array. Not used for this adapter method.
	 * @return closure Function returning boolean `true` on successful write, `false` otherwise.
	 */
	public function write($key, $value, array $options = array()) {
		if (!$this->isStarted() && !$this->_startup()) {
			throw new RuntimeException("Could not start session");
		}

		$class = get_called_class();
		return function($self, $params) use ($class) {
			return $class::overwrite(
				$_SESSION, Set::insert($_SESSION, $params['key'], $params['value'])
			);
		};
	}

	/**
	 * Delete value from the session
	 *
	 * @param string $key The key to be deleted
	 * @param array $options Options array. Not used for this adapter method.
	 * @return closure Function returning boolean `true` if the key no longer exists
	 *		   in the session, `false` otherwise
	 */
	public function delete($key, array $options = array()) {
		if (!$this->isStarted() && !$this->_startup()) {
			throw new RuntimeException("Could not start session");
		}
		$class = get_called_class();
		return function($self, $params) use ($class) {
			$key = $params['key'];
			$class::overwrite($_SESSION, Set::remove($_SESSION, $key));
			return !Set::check($_SESSION, $key);
		};
	}

	/**
	 * Clears all keys from the session.
	 *
	 * @param array $options Options array. Not used fro this adapter method.
	 * @return closure Function returning boolean `true` on successful clear, `false` otherwise.
	 */
	public function clear(array $options = array()) {
		if (!$this->isStarted() && !$this->_startup()) {
			throw new RuntimeException("Could not start session");
		}
		return function($self, $params) {
			return session_destroy();
		};
	}

	/**
	 * Called when opening the session - the equivalent of a 'session constructor'.
	 * Creates & memoizes a Model record/document, on which future session operations will interact
	 * to reduce the number of roundtrip operations on the persistent storage engine.
	 *
	 * @param string $path Not used for this adapter.
	 * @param string $name Not used for this adapter.
	 * @return void
	 */
	public function _open($path, $name) {
		$id = $this->key();
		if (isset($this->record)) {
			$this->record = null;
		}

		if ($id) {
			$this->record = $this->repository->findOneById($id);
		}

		if (!$this->record) {
			$expires = new \DateTime();
			$expires->add(
				\DateInterval::createFromDateString($this->config['expiration'])
			);

			$this->record = new $this->config['model']();
			$this->record->setExpires($expires);
		}
	}

	/**
	 * Closes the session.
	 *
	 * @return boolean Always returns true.
	 */
	public function _close() {
		unset($this->record);
		return true;
	}

	/**
	 * Session save handler callback for session destruction - called when session_destroy()
	 * is invoked.
	 *
	 * @param string $id The session ID to be destroyed. This is not used explicitly - rather,
	 *		  the memoized DB record object's delete() method is called.
	 * @param return boolean True on successful destruction, false otherwise.
	 */
	public function _destroy($id) {
		$this->entityManager->remove($this->record);
		$this->entityManager->flush();
	}

	/**
	 * Delete all expired entries from the session.
	 *
	 * @param integer $lifetime Maximum valid session lifetime.
	 * @return boolean True on successful garbage collect, false otherwise.
	 */
	public function _gc($lifetime) {
		$expires = new \DateTime();
		$expires->sub(new \DateInterval("PT{$lifetime}S"));

		$this->entityManager
		->createQuery("DELETE {$this->config['model']} e WHERE e.expires <= :expires")
		->setParameter('expires', new \DateTime($lifetime))
		->getResult();
	}

	/**
	 * The session save handler callback for reading data from the session.
	 *
	 * @param string $key The key of the data to be returned. If no key is specified,
	 *		  then all session data is returned in an array of key/value pairs.
	 * @return mixed Value corresponding to key if set, null otherwise.
	 */
	public function _read($id) {
		return $this->record->getData();
	}

	/**
	 * The session save handler callback for writing data to the session.
	 *
	 * @param string $key The key of the data to be returned.
	 * @param mixed $value The value to be written to the session.
	 * @return boolean True if write was successful, false otherwise.
	 */
	public function _write($id, $data) {
		$expires = new \DateTime();
		$expires->add(\DateInterval::createFromDateString($this->config['expiration']));

		$this->record->setId($id);
		$this->record->setData($data ?: null);
		$this->record->setExpires($expires);

		$this->entityManager->persist($this->record);
		$this->entityManager->flush($this->record);
	}

	/**
	 * Overwrites session keys and values.
	 *
	 * @param array $old Reference to the array that needs to be overwritten. Will usually
	 *        be `$_SESSION`.
	 * @param array $new The data that should overwrite the keys/values in `$old`.
	 * @return boolean Always `true`
	 */
	public static function overwrite(&$old, $new) {
		if (!empty($old)) {
			foreach ($old as $key => $value) {
				if (!isset($new[$key])) {
					unset($old[$key]);
				}
			}
		}
		foreach ($new as $key => $value) {
			$old[$key] = $value;
		}
		return true;
	}
}
?>
