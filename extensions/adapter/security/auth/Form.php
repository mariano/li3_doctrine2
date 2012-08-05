<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\extensions\adapter\security\auth;

use Doctrine\ORM\EntityManager;
use lithium\core\ConfigException;

/**
 * This auth adapter offers a way to integrate lithium's Auth with doctrine
 * models. To use this adapter you must have a model, which can extend from the
 * `BaseEntity` model provided in this plugin. If your model does not extend
 * from `BaseEntity`, you must either specify the configuration option
 * `entityManager`, or implement a static method named `getEntityManager` in
 * your model, which should also contain a method named `data()` to return
 * an array of the record's values.
 *
 * Once you have your model, you must set your bootstrap/session.php to use
 * this adapter. For example:
 *
 * {{{
 * use lithium\security\Auth;
 * Auth::config(array(
 *	   'default' => array(
 *		   'adapter' => 'li3_doctrine2\extensions\adapter\security\auth\Form',
 *		   'model' => 'app\models\User',
 *		   'fields' => array('email', 'password')
 *	   )
 * ));
 * }}}
 */
class Form extends \lithium\security\auth\adapter\Form {
	/**
	 * Repository for records
	 *
	 * @var object
	 */
	protected $repository;

	/**
	 * Sets the initial configuration for the `Form` adapter.
	 *
	 * @see lithium\security\auth\adapter\Form::$__construct
	 * @param array $config Sets the configuration for the adapter, which has the following options:
	 *                - `'model'` _string_: The name of the model class to use. See the `$_model`
	 *                  property for details.
	 *                - `'fields'` _array_: The model fields to query against when taking input from
	 *                  the request data. See the `$_fields` property for details.
	 *                - `'scope'` _array_: Any additional conditions used to constrain the
	 *                  authentication query. For example, if active accounts in an application have
	 *                  an `active` field which must be set to `true`, you can specify
	 *                  `'scope' => array('active' => true)`. See the `$_scope` property for more
	 *                  details.
	 *                - `'filters'` _array_: Named callbacks to apply to request data before the user
	 *                  lookup query is generated. See the `$_filters` property for more details.
	 *                - `'validators'` _array_: Named callbacks to apply to fields in request data and
	 *                  corresponding fields in database data in order to do programmatic
	 *                  authentication checks after the query has occurred. See the `$_validators`
	 *                  property for more details.
	 *                - `'query'` _string_: Determines the model method to invoke for authentication
	 *                  checks. See the `$_query` property for more details.
	 * @throws \lithium\core\ConfigException
	 */
	public function __construct(array $config = array()) {
		$config += array(
			'model' => NULL,
			'entityManager' => NULL,
			'repositoryMethod' => 'findOneBy'
		);
		if (empty($config['model']) || !class_exists($config['model'])) {
			throw new ConfigException("No valid model \"{$config['model']}\" available to use for Form auth adapter");
		} elseif (empty($config['entityManager']) && (
			!method_exists($config['model'], 'getEntityManager') ||
			!is_callable($config['model'] . '::getEntityManager')
		)) {
			throw new ConfigException("The model {$config['model']} must define a getEntityManager() static method, or you must set the entityManager auth config variable");
		}

		$reflection = new \ReflectionClass($config['model']);
		if (
			!$reflection->implementsInterface('li3_doctrine2\models\IModel') &&
			!$reflection->implementsInterface('li3_doctrine2\models\IUser')
		) {
			throw new ConfigException("The model {$config['model']} must implement IUser");
		}

		$entityManager = $config['entityManager'] ?:
			call_user_func($config['model'] . '::getEntityManager');
		if (!isset($entityManager) || !($entityManager instanceof EntityManager)) {
			throw new ConfigException('Not a valid entity manager');
		}

		$this->repository = $entityManager->getRepository($config['model']);
		parent::__construct($config);
	}

	/**
	 * Called by the `Auth` class to run an authentication check against a model class using the
	 * credientials in a data container (a `Request` object), and returns an array of user
	 * information on success, or `false` on failure.
	 *
	 * @param object $credentials A data container which wraps the authentication credentials used
	 *				 to query the model (usually a `Request` object). See the documentation for this
	 *				 class for further details.
	 * @param array $options Additional configuration options. Not currently implemented in this
	 *				adapter.
	 * @return array|bool Returns an array containing user information on success, or `false` on failure.
	 */
	public function check($credentials, array $options = array()) {
		$data = $this->_filters($credentials->data);
		if (count(array_filter($data)) === 0) {
			return FALSE;
		}
		$conditions = $this->_scope + array_diff_key($data, $this->_validators);

		$user = call_user_func(array($this->repository, $this->_config['repositoryMethod']), $conditions);
		return $user ? $this->_validate($user, $data) : FALSE;
	}
}

?>
