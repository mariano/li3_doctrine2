<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_doctrine2\models;

/**
 * This interface defines the basic behavior a Doctrine model should implement
 * to be able to be used as the model for li3_doctrine2's session adapter.
 */
interface ISession {
    /**
     * Set ID
     *
     * @param string $id ID
     */
    public function setId($id);

    /**
     * Get data
     *
     * @return string
     */
    public function getData();

    /**
     * Set data
     *
     * @param string $data Data
     */
    public function setData($data);

    /**
     * Set expiration date
     *
     * @param DateTime $expires Expiration date
     */
    public function setExpires(\DateTime $expires);
}
?>
