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

class RfcController extends AbstractActionController
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
     public function rfcAction(){
	}
    public function testAction(){
    }
     public function rfcWhatAction(){
	}
     public function rfcResourceDeleteAction(){
    }
	 public function rfcResourceEditAction(){
    }
	 public function rfcResourceAddAction(){
    }
	 public function rfcWorkgroupAddAction(){
    }
     public function rfcWorkgroupDeleteAction(){
	}
	 public function rfcWorkgroupEditAction(){
	}
	 public function rfcWorkTypeEditAction(){
	}
	 public function rfcWorkTypeEdit1Action(){
	}
	 public function rfcIowAction(){
	}
	 public function rfcResourceAction(){
	}
	 public function rfcResourcegroupaddAction(){
    }
	 public function rfcResourcegroupeditAction(){
    }
	 public function rfcResourcegroupdeleteAction(){
	}
	 public function codesetupAction(){
    }
	 public function iowmasterAction(){
	}
	 public function iowmastersAction(){
    }
	 public function resourcegroupmasterAction(){
	}
	 public function rfcwbsAction(){
	}
	 public function rfcprojectiowAction(){
	}
	public function resourcerateAction(){
	}
	public function headesAction(){
	}
	public function qualifiersettingAction(){
	}
	public function tdssettingAction(){
	}
    public function dashboardAction(){
	}
	public function rfcPreworkAction(){
	}
	public function approvalAction(){
	}
	public function rfcCreationAction(){
	}
	public function assetsAction(){
	}
	public function reportDashboardAction(){
	}
	public function labourAction(){
	}
	public function multiTreeTableAction(){}
	public function revisionnamesetupAction(){}
	public function scrollAction(){}
	

	
}
