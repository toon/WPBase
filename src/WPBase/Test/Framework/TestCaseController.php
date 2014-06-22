<?php
namespace WPBase\Test\Framework;

use Doctrine\ORM\Tools\SchemaTool;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use RuntimeException;

class TestCaseController extends AbstractHttpControllerTestCase
{
    protected $em;
    protected $configTest;
    protected $configModule;

    public function setUp()
    {
        parent::setUp();

        if (! file_exists($this->configTest)) {
            throw new RuntimeException('Arquivo '.$this->configTest.' não foi encontrado!');
        }

        $this->setApplicationConfig(include $this->configTest);

        $this->getApplicationServiceLocator()->setAllowOverride(true);
        self::initDoctrine($this->getApplicationServiceLocator());

        foreach($this->filterModules() as $m)
            $this->createDatabase($m);
    }

    /**
     * @param $serviceManager \Zend\ServiceManager\ServiceManager
     */
    public static function initDoctrine($serviceManager)
    {
        $config = $serviceManager->get('Config');

        $config['doctrine']['connection']['orm_default'] =
            array(
                'driverClass' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver',
                'params' => array(
                    'memory' => true
                )
            );

        $serviceManager->setService('Config', $config);
        $serviceManager->get('doctrine.entity_resolver.orm_default');
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    protected function filterModules()
    {
        if(! file_exists($this->configTest)){
            throw new RuntimeException('Arquivo '.$this->configTest. ' não encontrado!');
        }

        $config = include $this->configTest;

        $array = array();
        /**
         * @var $moduleManager \Zend\ModuleManager\ModuleManager
         */
        $moduleManager = $this->getApplicationServiceLocator()->get('ModuleManager');

        foreach($moduleManager->getModules() as $m) {
            if (! in_array($m, array_merge($config['not_load_entity'], array('DoctrineModule','DoctrineORMModule', 'ZendDeveloperTools'))))
                $array[] = $m;
        }

        return $array;
    }

    /**
     * createDatabase
     * @param $module
     * @throws \RuntimeException
     */
    public function createDatabase($module)
    {

        $this->tearDown();
        if (file_exists($this->configModule.'config/module.config.php')) {

            $config = require $this->configModule.'/config/module.config.php';

            if (isset($config['doctrine'])){

                $dh = $config['doctrine']['driver'][$module.'_driver']['paths'][0];

                if (is_dir($dh)){

                    $dir = opendir($dh);

                    $tool = new SchemaTool($this->getEm());

                    $class = array();
                    while (false !== ($filename = readdir($dir))) {
                        if (substr($filename,-4) == ".php") {

                            $class[] = $this->getEm()->getClassMetadata($module.'\\Entity\\'.str_replace('.php', '',$filename));
                        }
                    }
                    $tool->createSchema($class);
                }
            }
        }
    }

    /**
     * tearDown
     */
    public function tearDown() {
        parent::tearDown();

        $module = $this->filterModules();

        foreach($module as $m){

            if (file_exists($this->configModule.'config/module.config.php')) {

                $config = require $this->configModule.'config/module.config.php';

                if(isset($config['doctrine'])){
                    $dh = $config['doctrine']['driver'][$m.'_driver']['paths'][0];
                    if (is_dir($dh)){
                        $dir = opendir($dh);
                        while (false !== ($filename = readdir($dir))) {
                            if (substr($filename,-4) == ".php") {
                                $tool = new SchemaTool($this->getEm());
                                $class = array(
                                    $this->getEm()->getClassMetadata($m.'\\Entity\\'.str_replace('.php', '',$filename))
                                );
                                $tool->dropSchema($class);
                            }
                        }

                    }
                }
            }
        }
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEm() {
        return $this->em =  $this->getApplicationServiceLocator()->get('Doctrine\ORM\EntityManager');
    }

}
