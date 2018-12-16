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

class PurchaseController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	 public function indexAction()					{	}	
	 public function projectAction()				{	}
	 public function resourceAction()				{	}
	 public function gateAction()					{	}
	 public function materialAction()				{	}
	 public function materialsetupAction()			{	}
	 public function resourcesetupAction()			{	}
	 public function addmaterialAction()			{	}
	 public function materialelseAction()			{	}
	 public function importAction()					{	}
	 public function materialimportAction()			{	}
	 public function materialeditAction()			{	}
	 public function materialmultipleAction()		{	}
	 public function purchasetypeAction()			{	}
	 public function gateentryAction()				{	}
	 public function minorderAction()				{	}
	 public function minorderentryAction()			{	}
	 public function purchasebillAction()			{	}
	 public function purchaseentryAction()			{	}
	 public function termsmasterAction()			{	}
	 public function warehouseassignAction()		{	}
	 public function minconversionAction()			{	}
	 public function conversionentryAction()		{	}
	 public function mintransferAction()			{	}
	 public function transferentryAction()			{	}
	 public function reportsAction()				{	}
}
