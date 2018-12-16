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

class FollowupController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	public function followupAction()	{
    }
    public function siteinvestigationAction()	{
    }
	public function technicalspecificationAction()	{
    }
	public function documentpurchaseAction()	{
    }
	public function documentsAction()	{
    }
	public function quotationtermsandconditionAction()	{
    }
	public function quotationboqAction()	{
    }
	public function bidwinAction()	{
    }
	public function bidwinsAction()	{
    }
	public function workorderAction()	{
    }
	public function workordertermsandconditionAction()	{
    }
	public function workorderboqAction()	{
		
    }
	public function followupssteponeAction()	{
		
    }
	public function followupsAction()	{
		
    }
	public function quotationAction()	{
		
    }


	
    

}
