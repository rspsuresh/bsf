<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;
use Zend\Session\Container;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class DeveloperController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
    public function indexAction()	{
        $this->layout('layout/developer');
		$this->getServiceLocator()->get('ViewHelperManager')->get('HeadTitle')->set('Developer Framework');
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		
		$request = $this->getRequest();
        if ($request->isPost()) {
			$postParams = $request->getPost();
			$developerFramework = new Container('DeveloperFramework');
			$developerFramework->pageType = $postParams['pageType'];
			
			$this->redirect()->toRoute('application/default', array('controller' => 'developer','action' => 'step2'));
		}
    }
	
	public function step2Action()	{
        $this->layout('layout/developer');
		$this->getServiceLocator()->get('ViewHelperManager')->get('HeadTitle')->set('Developer Framework');
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		
		$request = $this->getRequest();
        if ($request->isPost()) {
			$postParams = $request->getPost();
			
			$moduleName = strtolower($postParams['module_name']);
			$controllerName = strtolower($postParams['controller_name']);
			$actionName = strtolower($postParams['action_name']);
			
			$sql     = new Sql($dbAdapter);
			$select = $sql->select();
			$select->from('FW_Pages')
				   ->columns(array('PageId'))
				   ->where("ModuleName = '".$moduleName."'")
				   ->where("ControllerName = '".$controllerName."'")
				   ->where("ActionName = '".$actionName."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(sizeof($results) == 0) {
				$sql = new Sql($dbAdapter);
				$insert = $sql->insert('FW_Pages');
				$insert->values(array("ModuleName"=>$moduleName, "ControllerName"=>$controllerName,"ActionName"=>$actionName));
				$Statement = $sql->getSqlStringForSqlObject($insert);
				$results = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
				$pageLastInsertId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$developerFramework = new Container('DeveloperFramework');
				$developerFramework->pageInsertId = $pageLastInsertId;
				$developerFramework->moduleName = $moduleName;
				$developerFramework->controllerName = $controllerName;
				$developerFramework->actionName = $actionName;
				
				$this->redirect()->toRoute('application/default', array('controller' => 'developer','action' => 'step3'));
			} else {
				$this->redirect()->toRoute('application/default', array('controller' => 'developer','action' => 'step2'));
			}
		}
		return $this->_view;
    }
	
	public function step3Action()	{
        $this->layout('layout/developer');
		$this->getServiceLocator()->get('ViewHelperManager')->get('HeadTitle')->set('Developer Framework');
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		
		$developerFramework = new Container('DeveloperFramework');
		$this->_view->sessionValue = $developerFramework;
		
		$request = $this->getRequest();
        if ($request->isPost()) {
			$postParams = $request->getPost();
			
			$moduleName = strtolower($postParams['module_name']);
			$controllerName = strtolower($postParams['controller_name']);
			$actionName = strtolower($postParams['action_name']);
			if(isset($postParams['functions'])) {
				
			}
			$controllerFilePath = getcwd().'/module/'.ucfirst($moduleName).'/src/'.ucfirst($moduleName).'/Controller/'.ucfirst($controllerName).'Controller.php';
			if(!file_exists($controllerFilePath)) {
				$myfile = fopen($controllerFilePath, "w");
				$txt = '<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace '.ucfirst($moduleName).'\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;
use Zend\Session\Container;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class '.ucfirst($controllerName).'Controller extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
}';
				fwrite($myfile, $txt);
				fclose($myfile);
				$actionPath = getcwd().'/module/'.ucfirst($moduleName).'/view/'.$moduleName.'/'.$controllerName;
				if (!file_exists($actionPath)) {
					mkdir($actionPath, 0777, true);
				}
			}
			$actionPathFile = getcwd().'/module/'.ucfirst($moduleName).'/view/'.$moduleName.'/'.$controllerName.'/'.$actionName.'.phtml';
			if(!file_exists($actionPathFile)) {
				$myfile = fopen($actionPathFile, "w");
				$txt = '<!-- Script Tags -->
	
<!-- Script Rags -->

<!-- Style Tags -->
	
<!-- Style Rags -->';
				fwrite($myfile, $txt);
				fclose($myfile);
			}
			$actionControllerName = ucwords(str_replace("-"," ",$actionName));
			$actionControllerName = lcfirst(str_replace(" ","",$actionControllerName));
			$closePosition = strripos(file_get_contents($controllerFilePath), '}');
			$myfile = fopen($controllerFilePath, "c");
			fseek($myfile, $closePosition,SEEK_SET );
			$txt = '
	public function '.$actionControllerName.'Action(){';
			fwrite($myfile, $txt);
			fclose($myfile);
			$myfile = fopen($controllerFilePath, "a+");
			if($developerFramework->pageType == 2) {
				$txt = '
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}';
				fwrite($myfile, $txt);
			}
			$txt = '
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}
			
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}';
			fwrite($myfile, $txt);
			fclose($myfile);
			$this->redirect()->toRoute('application/default', array('controller' => 'developer','action' => 'index'));
		}
		return $this->_view;
	}
	
	public function checkControllernameAction()	{
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				$sql     = new Sql($dbAdapter);
				$select = $sql->select();
				$select->from('FW_Pages')
					   ->columns(array('ControllerName'))
					   ->where("ModuleName = '".strtolower($postParams['moduleName'])."'");
				$statement = $sql->getSqlStringForSqlObject($select);
				$results  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			}
			$this->_view->setTerminal(true);
			$response = $this->getResponse()->setContent(json_encode($results));
			return $response;
		}	
    }
	
	public function checkActionnameAction()	{
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				$sql     = new Sql($dbAdapter);
				$select = $sql->select();
				$select->from('FW_Pages')
					   ->columns(array('PageId'))
					   ->where("ModuleName = '".strtolower($postParams['moduleName'])."'")
					   ->where("ControllerName = '".strtolower($postParams['controllerName'])."'")
					   ->where("ActionName = '".strtolower($postParams['actionName'])."'");
				$statement = $sql->getSqlStringForSqlObject($select);
				$results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$result = sizeof($results);
			}
			$this->_view->setTerminal(true);
			$response = $this->getResponse()->setContent($result);
			return $response;
		}	
    }
	
	public function sampleAjaxAction()	{
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$result =  'No';
		if($this->getRequest()->isXmlHttpRequest())	{
			$result =  'yes';
			$this->_view->setTerminal(true);
			$response = $this->getResponse()->setContent($result);
			return $response;
		} else {
			$this->_view->result = $result;
			return $this->_view;
		}		
    }
}