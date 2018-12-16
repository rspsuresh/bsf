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

class VendorController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
    public function vendorAction()	         			{}
	public function vendorOtherinfoAction()	 			{}
	public function vendorContactdetailAction()			{}
	public function vendorTableAction()	 	    		{}
	public function vendorVatdetailAction()	 	    	{}
	public function vendorServicetaxdetailAction()	 	{}
	public function vendorExcisedetailsAction()	 		{}
	public function vendorBankdetailAction()	 		{}
	public function vendorBranchdetailAction()	 		{}
	public function vendorExperiencedetailAction()	 	{}
	public function vendorcapabilityAction()	 		{}
	public function vendortermsandconditionAction()	 	{}
	public function vendorassessmentAction()	 		{}
	public function vendorregistrationAction()	 		{}
    public function vendorregisterAction()	 		    {}
	public function vendorprofileAction()	 			{}	
    public function vendorprofile2Action()	 			{}
	public function invitevendorAction()	 			{}
	public function vendorrequestAction()	 			{}
	public function vendorsequentialAction()	 		{}
	public function onlineregisterAction()	 		    {}
	public function vendoropenpageAction()				{}
	public function vendorindexopenpageAction()			{}
	public function openpagetotalAction()			    {}
	public function vendorindexAction()			    	{}
	public function rfqregisterAction()			    	{}
	public function leadvendorcpAction()			    {}
	public function registeredvendorAction()			{}
	public function responsedfromvendorAction()			{}
	public function uploadtechnicalAction()				{}
	public function validatingtechinfoAction()			{}
	public function validatingtechinfo2Action()			{}
	public function trackrfq1Action()					{}
	public function trackrfq2Action()					{}
	public function trackrfq3Action()					{}
	public function trackrfq4Action()					{}
	public function ratecomparisonAction()				{}
	public function ratecomparisononeAction()			{}
	public function responsedetailAction()				{}
	public function rfqVendorsAction()					{}
	public function newpurchaseorderAction()			{}
	public function popupAction()						{}
	public function roughdemoAction()					{}
	public function tagAction()							{}
	public function vehicleAction()						{}
	public function vehiclemaster()						{}
	public function sampleAction()						{}

	
	
}