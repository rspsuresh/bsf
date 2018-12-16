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

class LandbankController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
    public function indexAction()	{
    }
    public function newLeadAction(){
	}
	public function landbankAction(){
	}
	public function landenquiryAction(){
	}
	public function initialfeasibilityAction(){
	}
	public function businessfeasibilityAction(){
	}
	public function financiafeasibilityAction(){
	}
	public function financiafeasibilitysAction(){
    }
	public function duediligenceAction(){
	}
	public function financialfeasibilityAction(){
	}
	public function financialfeasibilitysAction(){
	}
	public function finalisationAction(){
	}
	public function tenderenquiryAction(){
	}
	public function tenderenquirydetalisAction(){
	}
	public function landbankregisterAction(){
	}
	public function followupAction(){
	}
	public function siteinvestigationAction(){
	}
    public function technicalspecificationAction(){
    }
	public function projectconceptionsAction(){
	}
	public function projectconceptionsteptwoAction(){
	}
	public function dashboardAction(){
	}
	public function landdashboardAction(){
	}
	public function overalldashboardAction(){
	}
	public function photoviewAction(){
	}
      
}
