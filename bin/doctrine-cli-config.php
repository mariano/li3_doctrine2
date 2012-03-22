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

require_once($appPath . 'config/bootstrap/libraries.php');
require_once($appPath . 'config/bootstrap/connections.php');

$connection = \lithium\data\Connections::get('default');

/**
 * Continue with doctrine cli config
 */

Doctrine\ORM\Tools\Setup::registerAutoloadGit(PLUGIN_PATH . '/_source/doctrine2');

$em = $connection->getEntityManager();

$helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
    'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($em->getConnection()),
    'em' => new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($em)
));

?>
