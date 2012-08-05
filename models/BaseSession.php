<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * Base class for session models to use with the doctrine session adapter. You
 * can extend from this class to provide your own session model.
 */
abstract class BaseSession implements ISession {
	/**
	 * Class dependencies.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'connections' => 'lithium\data\Connections'
	);

	/**
	 * Connection name used for persisting / loading this record
	 *
	 * @var string
	 */
	protected static $connectionName = 'default';

	/**
	 * @Id
	 * @Column(type="string")
	 */
	protected $id;

	/**
	 * @Column(type="text",nullable=true)
	 */
	protected $data;

	/**
	 * @Column(type="datetime")
	 */
	protected $expires;

	/**
	 * Get the entity manager linked to the connection defined in the property
	 * `$connectionName`
	 *
	 * @see IModel::getEntityManager()
	 * @return \Doctrine\ORM\EntityManager entity manager
	 */
	public static function getEntityManager() {
		static $entityManager;
		if (!isset($entityManager)) {
			$connections = static::$_classes['connections'];
			$entityManager = $connections::get(static::$connectionName)->getEntityManager();
		}
		return $entityManager;
	}

	/**
	 * Set ID
	 *
	 * @param string $id ID
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get data
	 *
	 * @return string
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Set data
	 *
	 * @param string $data Data
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * Set expiration date
	 *
	 * @param \DateTime $expires Expiration date
	 */
	public function setExpires(\DateTime $expires) {
		$this->expires = $expires;
	}
}
?>
