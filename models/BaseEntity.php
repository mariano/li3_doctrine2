<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\models;

use lithium\util\Inflector;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * This class can be used as the base class of your doctrine models, to allow
 * for lithium validation to work on doctrine models.
 */
abstract class BaseEntity extends \lithium\data\Entity implements IModel, IUser {
    /**
     * Criteria for data validation.
     *
     * Example usage:
     * {{{
     * public $validates = array(
     *     'title' => 'please enter a title',
     *     'email' => array(
     *         array('notEmpty', 'message' => 'Email is empty.'),
     *         array('email', 'message' => 'Email is not valid.'),
     *     )
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
     * Constructor, instance at which the entity fields are loaded from
     * doctrine's class metadata
     */
    public function __construct() {
        $this->_model = get_called_class();
    }

    /**
     * Get the entity manager linked to the connection defined in the property
     * `$connectionName`
     *
     * @see IModel::getEntityManager()
     * @return EntityManager entity manager
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
     * Get the repository for this model
     *
     * @see IModel::getRepository()
     * @return EntityRepository entity repository
     */
    public static function getRepository() {
        return static::getEntityManager()->getRepository(get_called_class());
    }

    /**
     * Doctrine callback executed after a record was loaded
     *
     * @param object $eventArgs Event arguments
     */
    public function onPostLoad(LifecycleEventArgs $eventArgs) {
        $this->_exists = true;
    }

    /**
     * Doctrine callback executed before persisting a new record
     *
     * @param object $eventArgs Event arguments
     * @throws ValidateException
     */
    public function onPrePersist(LifecycleEventArgs $eventArgs) {
        $this->_exists = false;
        if (!$this->validates()) {
            throw new ValidateException($this->_errors);
        }
    }

    /**
     * Doctrine callback executed before persisting an existing record
     *
     * @param object $eventArgs Event arguments
     * @throws ValidateException
     */
    public function onPreUpdate(PreUpdateEventArgs $eventArgs) {
        $this->_exists = true;
        if (!$this->validates()) {
            throw new ValidateException($this->_errors);
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

        if (!empty($rules) && $this->_errors = $validator::check($this->_getData(true), $rules, $options)) {
            return false;
        }
        return true;
    }

   /**
     * Allows several properties to be assigned at once, i.e.:
     * {{{
     * $record->set(array('title' => 'Lorem Ipsum', 'value' => 42));
     * }}}
     *
     * @see IModel::validates()
     * @param array $data An associative array of fields and values to assign to this instance.
     */
    public function set(array $data) {
        if (empty($data)) {
            return;
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
     * @return mixed Entire data array if $name is empty, otherwise the value from the named field.
     */
    public function data($name = null) {
        $data = $this->_getData();
        if (isset($name)) {
            return array_key_exists($name, $data) ? $data[$name] : null;
        }
        return $data;
    }

    /**
     * Get the entity fields
     *
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
    protected function _getData($allProperties = false) {
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
            return array_key_exists($name, $data) ? $data[$name] : null;
        }
        return $data;
    }
}
?>
