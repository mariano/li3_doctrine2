<?php

define('PLUGIN_PATH', dirname(dirname(__FILE__)));
define('ROOT', dirname(dirname(PLUGIN_PATH)));

$candidates = array(
    ROOT . '/',
    ROOT . '/app/'
);

if (isset($_SERVER['LITHIUM_APP'])) {
    $appPath = str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['LITHIUM_APP']);
    if (empty($appPath) || $appPath[strlen($appPath) - 1] != '/') {
        $appPath .= '/';
    }
    if (!file_exists($appPath . 'config/bootstrap.php')) {
        unset($appPath);
    }
} else {
    foreach($candidates as $candidate) {
        if (file_exists($candidate . 'config/bootstrap.php')) {
            $appPath = $candidate;
            break;
        }
    }
}

if (!isset($appPath)) {
    trigger_error(
        'Can\'t locate lithium\'s application path (looking for config/bootstrap.php file). Set the environment var LITHIUM_APP accordingly',
        E_USER_ERROR
    );
}

define('DOCTRINE_PATH', PLUGIN_PATH . '/_source/doctrine2');

/**
 * Load lithium connection settings
 */

$loader = new \Doctrine\Common\ClassLoader("lithium", __DIR__ . '/../..');
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("app", dirname($appPath));
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("li3_doctrine2", dirname(PLUGIN_PATH));
$loader->register();

require_once($appPath . 'config/bootstrap/connections.php');

$connection = array_diff_key(
    \lithium\data\Connections::get('default', array('config' => true)),
    array('type'=>null, 'libraries'=>null, 'adapter'=>null, 'login'=>null, 'filters'=>null)
);

/**
 * Continue with doctrine cli config
 */

require('Doctrine/ORM/Tools/Setup.php');
Doctrine\ORM\Tools\Setup::registerAutoloadGit(DOCTRINE_PATH);

$config = new \Doctrine\ORM\Configuration();
$annotationDriver = $config->newDefaultAnnotationDriver(array($appPath . 'models'));

$config->setProxyDir($appPath . 'models/proxies');
$config->setProxyNamespace('proxies');
$config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
$config->setMetadataDriverImpl($annotationDriver);

$em = \Doctrine\ORM\EntityManager::create($connection, $config);

$helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
    'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($em->getConnection()),
    'em' => new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($em)
));

?>
