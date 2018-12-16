<?php  
namespace Application\View\Helper;  
use Zend\View\Helper\AbstractHelper;  
use Zend\ServiceManager\ServiceLocatorAwareInterface;  
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;


class CustomHelper extends AbstractHelper implements ServiceLocatorAwareInterface  
{  
    protected $message = null;  
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
    public function setMessage($message)  
    {  
        $this->message = $message;  
        return $this;  
    }  
    public function getMessage($id)  
    {  
		$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
		//$sql = new Sql($dbAdapter);
		
		$test = $id;
        return $test;  
    } 
    /*public function __invoke($id)  
    {  
        $message = $this->getMessage($id);  
        return $message;  
    }*/
}
?>