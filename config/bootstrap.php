<?php
use lithium\core\Libraries;

/**
 * Include libraries
 */

$libsPath = dirname(dirname(__FILE__)) . '/_source/';
$doctrinePath = $libsPath . 'doctrine2';

Libraries::add('Doctrine\Common', array(
    'path' => $doctrinePath . '/lib/vendor/doctrine-common/lib/Doctrine/Common',
    'bootstrap' => false
));

Libraries::add('Doctrine\DBAL', array(
    'path' => $doctrinePath . '/lib/vendor/doctrine-dbal/lib/Doctrine/DBAL',
    'bootstrap' => false
));

Libraries::add('Doctrine\ORM', array(
    'path' => $doctrinePath . '/lib/Doctrine/ORM',
    'bootstrap' => false
));

Libraries::add('Gedmo', array(
    'path' => $libsPath . '/DoctrineExtensions/lib/Gedmo'
));

?>
