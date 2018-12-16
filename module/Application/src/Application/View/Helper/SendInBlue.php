<?php
namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\RendererInterface;

use Zend\Db\Adapter\Adapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class SendInBlue extends AbstractHelper implements ServiceLocatorAwareInterface
{
    protected $connection = null;
    protected $sRowId ="";
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

    public function sendMailTo($TemplateId,$to, $attr = false)
    {
        $sm = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $path =  getcwd()."/vendor/buildsuperfast/sendinblue/sendinblue.php";
        require_once($path);
        $mailin = new \Mailin('https://api.sendinblue.com/v2.0','KVj6ZwQvIf4CmhS2',5000);

        $data = array( "id" => $TemplateId,
            "to" => $to,
            "attr" => $attr,
            "attachment_url" => "",
            "attachment" => array(),
            "headers" => array("Content-Type"=> "text/html;charset=iso-8859-1")
        );

        return $mailin->send_transactional_template($data);
    }




}
?>