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

class RequestController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
    public function requestentryAction()			{}
	public function requestentrytableAction()		{}
	public function requestdetailAction()			{}
	public function requestregisterAction()			{}
	public function tokenAction()					{}
	public function decisionentryAction()			{}
	public function decisionentrytableAction()		{}
	public function decisiondetailAction()			{}
	public function decisionregisterAction()		{}
	public function mtableAction()					{}
	public function requestmatrixviewAction()		{}
	public function requestmatrixviewNewAction()	{}
	public function requestentrypart2Action()	    {}
	public function requestsequentialAction()	    {}
	public function requestsequential1Action()	    {}
	public function decisiontableAction()	 		{}	
}
