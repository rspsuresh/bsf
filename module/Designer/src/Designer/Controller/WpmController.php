<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Designer\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class WpmController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	public function workorderAction()      			   		{}
	public function boqAction()      	   			   		{}
	public function labourstrengthAction()      	   		{}
	public function labourstrengthgridAction()         		{}
	public function labourstrengthdetailsAction()      		{}
	public function workprogressentryAction()      	   		{}
	public function workprogressentrygridAction()      		{}
	public function workprogressentrydetailsAction()   		{}
	public function workcompletionentryAction()        		{}
	public function workcompletiongridAction()         		{}
	public function listofitemofworksgridAction()      		{}
	public function billformatAction()     			   		{}
	public function labourmasterAction()     		   		{}
	public function wpmbillindexAction()     		   		{}
	public function wpmbillientryAction()     		   		{}
	public function labourrateapprovalenteryAction()   		{}
	public function labourrateapprovalegridAction()    		{}
	public function labourrateapprovaledetailsAction() 		{}
	public function serviceentryAction() 			   		{}
	public function servicebillentryAction() 		   		{}
	public function workprogressregisterAction() 	   		{}
	public function servicedoneAction() 	   		   		{}
	public function servicedoneindexAction() 	   	   		{}
	public function workbillregisterAction() 	   	   		{}
	public function serviceorderregisterAction() 	   		{}
	public function labourrateapprovaleregisterAction() 	{}
	public function hireorderindexAction() 					{}
	public function hireorderentryAction() 					{}



	
	
	


	
    

}
