<?php
namespace li3_doctrine2\extensions\data\source;

use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class Doctrine {
    protected $entityManager;

    public function __construct(array $config = array()) {
        $defaults = array(
            'models' => LITHIUM_APP_PATH . '/models',
            'proxies' => LITHIUM_APP_PATH . '/proxies',
            'proxyNamespace' => 'proxies',
            'createEntityManager' => function(array $params) {
                return \Doctrine\ORM\EntityManager::create(
                    $params['connection'],
                    $params['configuration'],
                    $params['eventManager']
                );
            }
        );
        $config += $defaults;
        $connection = array_diff_key($config, array_merge($defaults, array(
            'type' => null,
            'adapter' => null,
            'login' => null,
            'filters' => null
        )));

        $configuration = new \Doctrine\ORM\Configuration();
        $annotationDriver = $configuration->newDefaultAnnotationDriver((array) $config['models']);

        $configuration->setProxyDir($config['proxies']);
        $configuration->setProxyNamespace($config['proxyNamespace']);
        $configuration->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
        $configuration->setMetadataDriverImpl($annotationDriver);

        $eventManager = new \Doctrine\Common\EventManager();
        $eventManager->addEventListener(array(
            Events::postLoad,
            Events::prePersist,
            Events::preUpdate
        ), $this);

        $this->entityManager = $config['createEntityManager'](compact(
            'connection',
            'configuration',
            'eventManager'
        ));
    }

    public function postLoad(LifecycleEventArgs $eventArgs) {
        $this->dispatchEntityEvent($eventArgs->getEntity(), 'onPostLoad', array($eventArgs));
    }

    public function prePersist(LifecycleEventArgs $eventArgs) {
        $this->dispatchEntityEvent($eventArgs->getEntity(), 'onPrePersist', array($eventArgs));
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs) {
        $this->dispatchEntityEvent($eventArgs->getEntity(), 'onPreUpdate', array($eventArgs));
    }

    protected function dispatchEntityEvent($entity, $eventName, array $args) {
        if (method_exists($entity, $eventName) && is_callable(array($entity, $eventName))) {
            call_user_func_array(array($entity, $eventName), $args);
        }
    }

    public function getEntityManager() {
        return $this->entityManager;
    }
}
?>
