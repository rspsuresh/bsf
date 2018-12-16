<?php  
namespace Application\View\Helper;  
use Zend\View\Helper\AbstractHelper;  
use Zend\ServiceManager\ServiceLocatorAwareInterface;  
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\Adapter\Adapter;

class OpenDatabase extends AbstractHelper implements ServiceLocatorAwareInterface  
{  
    protected $connection = null;  
    /** 
     * Set the service locator. 
     * 
     * @param ServiceLocatorInterface $serviceLocator 
     * @return CustomHelper 
     */  
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)  
    {  
        $this->serviceLocator = $serviceLocator;  
        return $this;  
    }  
    /** 
     * Get the service locator. 
     * 
     * @return \Zend\ServiceManager\ServiceLocatorInterface 
     */  
    public function getServiceLocator()  
    {  
        return $this->serviceLocator;  
    }  
    public function setConnection($connection)  
    {  
        $this->connection = $connection;  
        return $this;  
    }  
    public function getConnection($dbName)  
    {  
		$sm = $this->getServiceLocator()->getServiceLocator();  
        $config = $sm->get('application')->getConfig();  
			
		$this->connection = new Adapter(array(
			'driver' => 'pdo_sqlsrv',
			'hostname' => $config['db_details']['hostname'],
			'username' => $config['db_details']['username'],
			'password' => $config['db_details']['password'],
			'database' => $dbName,
		));
        return $this->connection;  
    }  
    public function __invoke($dbName)  
    {  
        $connection = $this->getConnection($dbName);  
        return $connection;  
    }
}
?>