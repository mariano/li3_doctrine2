<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
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
abstract class BaseSession extends BaseEntity {
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
     * Get ID
     *
     * @return string
     */
    public function getId() {
        return $this->id;
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
     * @param DateTime $expires Expiration date
     */
    public function setExpires(\DateTime $expires) {
        $this->expires = $expires;
    }
}
?>
