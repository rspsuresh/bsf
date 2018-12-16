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

class MandrilSendMail extends AbstractHelper implements ServiceLocatorAwareInterface
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

	public function sendMailTo($recipient, $from, $subject, $template, $data = false, $fromName = false, $toName = false)
    {		
		$sm = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
		$path =  getcwd()."/vendor/buildsuperfast/mandrill/Mandrill.php";
		require_once($path);
		$mandrill = new \Mandrill();
		$message = array(
			'from_email' => $from,
			'subject' => $subject,
			'to' => array(array('email' => $recipient, 'name' => $toName)),
			'merge_vars' => array(array(
				'rcpt' => $recipient,
				'vars' => $data
				))
			);

		$template_name = $template;

		$template_content = array();

		$response = $mandrill->messages->sendTemplate($template_name, $template_content, $message);
    }
    public function UpdateTemplate($name, $code)
    {
		$path =  getcwd()."/vendor/buildsuperfast/mandrill/Mandrill.php";
		require_once($path);
		$mandrill = new \Mandrill();

		return $response = $mandrill->templates->update($name, $code);
    }
	public function sendMailWithAttachment($recipient, $from, $subject, $template, $attachment, $data = false, $fromName = false, $toName = false)
    {		
		$sm = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $path =  getcwd()."/vendor/buildsuperfast/mandrill/Mandrill.php";
        require_once($path);
		$mandrill = new \Mandrill();
		$message = array(
			'from_email' => $from,
			'subject' => $subject,
			'to' => array(array('email' => $recipient, 'name' => $toName)),
			'merge_vars' => array(array(
				'rcpt' => $recipient,
				'vars' => $data
				)),
			'attachments' => array($attachment)
        );

		$template_name = $template;

		$template_content = array();

		$response = $mandrill->messages->sendTemplate($template_name, $template_content, $message);
    }

    public function sendMailWithMultipleAttachment($recipient, $from, $subject, $template, $attachment, $data = false, $fromName = false, $toName = false)
    {
        $sm = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $path =  getcwd()."/vendor/buildsuperfast/mandrill/Mandrill.php";
        require_once($path);
        $mandrill = new \Mandrill();
        $message = array(
            'from_email' => $from,
            'subject' => $subject,
            'to' => array(array('email' => $recipient, 'name' => $toName)),
            'merge_vars' => array(array(
                'rcpt' => $recipient,
                'vars' => $data
            )),
            'attachments' => $attachment
        );

        $template_name = $template;

        $template_content = array();

        $response = $mandrill->messages->sendTemplate($template_name, $template_content, $message);
    }
    public function sendMailWithMultipleAttachmentWithoutTemplate($recipient, $from, $subject, $attachment, $data = false, $fromName = false, $toName = false)
    {
        $sm = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $path =  getcwd()."/vendor/buildsuperfast/mandrill/Mandrill.php";
        require_once($path);
        $mandrill = new \Mandrill();
        $message = array(
            'from_email' => $from,
            'subject' => $subject,
            'html'=>$data,
            'to' =>$recipient,
//                array(
//                array('email' => 'sairam@micromen.info','type'=>'to'),
//                array('email' => 'sai.hockey@gmail.com','type'=>'cc'),
//                array('email' => 'sai.hockey@gmail.com','type'=>'bcc'),
//                ),

//            'bcc_address'=>'sai.hockey@gmail.com',
//            'merge_vars' => array(array(
//                'rcpt' => $recipient,
//                'vars' => $data
//            )),
            'attachments' => $attachment
        );

        $response = $mandrill->messages->send($message);
    }
}
?>