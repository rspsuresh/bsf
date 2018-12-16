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

class CrmController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
    public function onboardingAction()					{}
	public function projectInfoAction()					{}
	public function generalAction()						{}
	public function landareaDetailsAction()				{}
	public function landcostCalculationAction()			{}
	public function othercostDetailsAction()			{}
	public function paymentScheduleAction()				{}
	public function unittypeGenerationAction()			{}
	public function carparkManagementAction()			{}
	public function checklistManagementAction()			{}
	public function penalityInterestAction()			{}
	public function flatGridAction()					{}
	public function flatGridDetailsAction()				{}
	public function followupAction()					{}
	public function followupDetailsAction()				{}
	public function leadregisterAction()				{}
	public function followupHistoryAction()				{}
	public function modelPageAction()					{}
	public function executiveTargetAction()				{}
	public function allocationRegisterAction()			{}
	public function leadAllocationAction()				{}
	public function projectWiseAction()					{}
	public function executiveWiseAction()				{}
	public function leadAllocationTableAction()			{}
	public function stageCompletionAction()				{}
	public function stageCompletion1Action()			{}
	public function progressBillAction()				{}
	public function extraBillAction()					{}
	public function receiptBillAction()					{}
	public function socialLeadAction()					{}
	public function conceptionAction()					{}
	public function conceptionDetailAction()			{}
	public function unitAction()						{}
	public function unitDetailAction()					{}
	public function CampaignentryAction()				{}
	public function QualifierAction()					{}
	public function turnaroundCostAction()				{}
	public function turnaroundScheduleAction()			{}
	public function criticalAreaAction()				{}
	public function makeBrandAction()					{}
	public function documentsAction()					{}
	public function setupAction()						{}
	public function finalisationAction()				{}
	public function campaignregisterAction()			{}
	public function dashboardAction()					{}
	public function maintenancebillAction()				{}
	public function inventorybillAction()				{}
	public function rentalRegisterPage1Action()			{}
	public function paymentAction()						{}
	public function leaserentalbillAction()				{}
	public function propertyManagementPage1Action()		{}
	public function rentalAgreeementPage1Action()		{}
	public function paymentregisterAction()				{}
	public function cmdashboardAction()					{}
	public function maintenancePeriodAction()			{}
	public function ticketforumsAction()				{}
	public function	crmreportlistAction()				{}
	public function	basedashboardAction()				{}
	public function	newTicketAction()					{}
	public function	pdffinalizationAction()				{}
	public function	mailboxAction()						{}
	public function	readMailAction()					{}
	public function	regencyHotelAction()		        {}
	public function	totalCostAction()		   		    {}
	public function	printTableAction()		   		    {}
	public function	unitNoAction()		   			    {}
	public function	fullpageviewAction()		   		{}
	public function	unittransferAction()				{}
	public function	telecalldashAction()				{}
	public function	demandletterAction()				{}
	public function	demandAction()			         	{}
}

