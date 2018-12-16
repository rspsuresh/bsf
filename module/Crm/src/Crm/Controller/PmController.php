<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Crm\Controller;

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
use Application\View\Helper\CommonHelper;

class PmController extends AbstractActionController
{
	public function __construct()	{
		$this->bsf = new \BuildsuperfastClass();
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function rentalEntryAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		$userId = $this->auth->getIdentity()->UserId;
		$aVNo = CommonHelper::getVoucherNo(821, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];
        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
            ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
            ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
            ->join(array("f" => "PM_RentalTenantTrans"),"e.RentalRegisterId=f.RentalRegisterId",array('LeaserName'),$select::JOIN_LEFT)
            ->join(array("g" => "PM_RentalRentTrans"),"e.RentalRegisterId=g.RentalRegisterId",array('RentalRegisterId','RentAmount','RentPerPeriod'),$select::JOIN_LEFT)
            ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($unitList as &$list) {
            $select = $sql->select();
            $select->from(array("a" => "PM_RentalOtherCostTrans"))
                ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName','ServiceId'),$select::JOIN_LEFT)
                ->columns(array('Amount'))
                ->where(array("RentalRegisterId" => $list['RentalRegisterId']));
            $statement = $sql->getSqlStringForSqlObject($select); 
            $list['services'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $this->_view->unitList = $unitList;
        $request = $this->getRequest();
        if ($request->isPost()) {
            try {
				$postData = $request->getPost();
					
                $postParams = $request->getPost();
                $pvDate = $postParams['entry_date'];
               // $pvNo = $extraRequestNo;
                $unitId = $postParams['unitId'];
                $receiptAmount = $postParams['rent_charge'];
                $mainBillAmt = $postParams['main_bill'];
                $ebChargeAmt = $postParams['eb_charge'];
                $teleChargeAmt = $postParams['tele_charge'];
                $mainBillId = $postParams['main_id'];
                $ebChargeId = $postParams['eb_id'];
                $teleChargeId = $postParams['tele_id'];
                $paymentAmt = $postParams['gross_amount'];
                $totalPayAmount = $postParams['totalpayamount'];
                $remarks = $postParams['remarks'];
                $RentalRegisterId=$postParams['RentalRegId'];
                $connection->beginTransaction();
                $sVno= $this->bsf->isNullCheck($postParams['voucher_no'], 'string');
                $insert  = $sql->insert('PM_PaymentRegister');
                $newData = array(
                    'PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),
                    'PVNo' => $sVno,
                    'UnitId' => $unitId,
                    'ReceiptAmount' => $receiptAmount,
                    'PaymentAmount' => $paymentAmt,
                    'Amount' => $totalPayAmount,
                    'RentalRegisterId' => $RentalRegisterId,
                    'Remarks' => $remarks
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE); 
                $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                $service = array("$mainBillId"=>"$mainBillAmt", "$ebChargeId"=>"$ebChargeAmt", "$teleChargeId"=>"$teleChargeAmt");
                foreach($service as $id => $amount):
                    if($id == 0){
                        continue;
                    }
                    $insert = $sql->insert();
                    $insert->into( 'PM_PaymentTrans' );
                    $insert->Values( array( 'RegisterId' => $registerId, 'ServiceId' => $id, 'TransType' => 'P', 'Amount' => $amount));
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                endforeach;

                $connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'RentalBill-Entry-Add','N','RentalBill-Entry',$registerId,0, 0, 'CRM', $sVno,$userId, 0 ,0);

            } catch (PDOException $e) {
                $connection->rollback();
            }
        }
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
	}

	public function paymentRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		//total Payment//
		
		$select = $sql->select();
        $select->from(array('a' =>'PM_PaymentRegister'))
				->columns(array('RegisterId' => new expression('count(*)')))
				->where(array('DeleteFlag'=>'0'));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->paymentreg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
        $select = $sql->select();
        $select->from(array("a" => "PM_PaymentRegister"))
            ->join(array('p' => 'KF_UnitMaster'), 'a.UnitId=p.UnitId', array('UnitNo'), $select::JOIN_LEFT)
			->join(array("b" => "Crm_UnitBooking"), 'a.UnitId=b.UnitId', array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "p.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
			 ->order('a.RegisterId desc');
        $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->payment = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
		 return $this->_view;
	}

	public function paymentAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$userId = $this->auth->getIdentity()->UserId;
		$sql = new Sql($dbAdapter);
        $select = $sql->select();
		$select->from(array("a" => "KF_UnitMaster"))
			->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
           if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postData = $request->getPost();
				$select = $sql->select();
				$select->from(array('a' =>'PM_RentalRegister'))
						->columns(array('RentalRegisterId'))
						->join(array("b"=>"PM_RentalRentTrans"), "a.RentalRegisterId=b.RentalRegisterId", array('RentAmount'), $select::JOIN_INNER)
						->where(array('a.UnitId' => $postData['unitId']));
				  $statement = $sql->getSqlStringForSqlObject($select);
				$list['rental'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
           
				$select = $sql->select();
				$select->from(array('a' =>'PM_MaintenanceBillRegister'))
				       ->join(array("b"=>"PM_MaintenanceBillTrans"), "a.RegisterId=b.RegisterId", array('Amount'), $select::JOIN_LEFT)
						->join(array("c"=>"PM_ServiceMaster"), "b.ServiceId=c.serviceId", array('ServiceName','ServiceId'), $select::JOIN_LEFT)
			            ->where(array('a.UnitId' => $postData['unitId']));
				 $statement = $sql->getSqlStringForSqlObject($select);
				 $list['payment'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				 
				 $select = $sql->select();
				$select->from(array('a' =>'PM_MaintenanceBillRegister'))
				      ->columns(array("Total"=>new Expression("Sum(b.Amount)")))
				       ->join(array("b"=>"PM_MaintenanceBillTrans"), "a.RegisterId=b.RegisterId", array(), $select::JOIN_LEFT)
						->where(array('a.UnitId' => $postData['unitId']));
				 $statement = $sql->getSqlStringForSqlObject($select);
				 $list['total'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($list));
				return $response;
			
		}} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                   $postData = $request->getPost();
					//Print_r($postData);die;
					$pvDate = $this->bsf->isNullCheck($postData['pay_date'],'string');
                    $pvNo = $this->bsf->isNullCheck($postData['pay_no'],'string');
                    $unitId = $this->bsf->isNullCheck($postData['unitId'],'string');
                    $rentalRegId = $this->bsf->isNullCheck($postData['rentalRegId'],'number');
                    $receiptAmount = $this->bsf->isNullCheck($postData['rent_amount'],'number');
                    $paymentAmt = $this->bsf->isNullCheck($postData['payment_amount'],'number');
                    $totalPayAmount = $this->bsf->isNullCheck($postData['amount'],'number');
                    $chequeno = $this->bsf->isNullCheck($postData['cheque_no'],'string');
                    $chequedate = $this->bsf->isNullCheck($postData['cheque_date'],'string');
                    $bank = $this->bsf->isNullCheck($postData['bank_name'],'string');
                    $remarks = $this->bsf->isNullCheck($postData['remarks'],'string');
					$count=$this->bsf->isNullCheck($postData['RowCount'],'number');
					$insert  = $sql->insert('PM_PaymentRegister');
                    $newData = array(
                        'PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),
                        'PVNo' => $pvNo,
                        'UnitId' => $unitId,
                        'ReceiptAmount' => $receiptAmount,
                        'RentalRegisterId' => $rentalRegId,
                        'PaymentAmount' => $paymentAmt,
                        'Amount' => $totalPayAmount,
                        'ChequeNo' => $chequeno,
                        'ChequeDate' => date('Y/m/d H:i:s', strtotime($chequedate)),
                        'BankName' => $bank,
                        'Remarks' => $remarks
                    );
                    $insert->values($newData);
                   $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
					 $count=$postData['RowCount'];
					if($count!=0){
					 for($i=1;$i<=$count;$i++){
					 $ser=$postData['service_'.$i];
					 $sam=$postData['sample_'.$i];
					 
					$select = $sql->insert('PM_PaymentTrans');
                    $newData = array(
					    'RegisterId' =>$registerId,
                        'ServiceId' =>$this->bsf->isNullCheck($sam,'number'),
                        'TransType' => 'P',
                        'Amount' =>$this->bsf->isNullCheck($ser,'number'),
						);
                    $select->values($newData);
				 $statement = $sql->getSqlStringForSqlObject($select);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					 }}

				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Add','N','Payment-Entry',$registerId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);

                $this->redirect()->toRoute('crm/register', array('controller' => 'pm', 'action' => 'payment-register'));
			}} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
		}$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	
	public function rentalRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		/* added lines */
		$sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
            ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
            ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
            ->join(array("f" => "PM_PaymentRegister"),"e.RentalRegisterId=f.RentalRegisterId",array('PVNo','RegisterId','PVDate','Amount'),$select::JOIN_LEFT)
            ->columns(array('UnitNo','UnitId'))
            ->where("f.DeleteFlag='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->payList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		/*
		$sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
		    ->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
           ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			 ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
            ->join(array("g" => "PM_RentalRentTrans"),"e.RentalRegisterId=g.RentalRegisterId",array('RentalRegisterId','RentAmount'),$select::JOIN_LEFT)
            ->join(array("f" => "PM_MaintenanceBillRegister"),"a.UnitId=f.UnitId",array('RegisterId','NetAmount'),$select::JOIN_INNER)
             ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($unitList as &$list) {
            $select = $sql->select();
            $select->from(array("a" => "PM_MaintenanceBillTrans"))
                ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName','ServiceId'),$select::JOIN_LEFT)
                ->columns(array('Amount'))
                ->where(array("RegisterId" => $list['RegisterId']));
            $statement = $sql->getSqlStringForSqlObject($select);
            $list['services'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $this->_view->unitList = $unitList;*/

			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}

	public function rentalEditAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

		$sql = new Sql($dbAdapter);
		$userId = $this->auth->getIdentity()->UserId;

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
                try {
					$connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
					
                    $postParams = $request->getPost();
					$pvDate = $postParams['entry_date'];
                    $pvNo = $postParams['voucher_no'];
                    $unitId = $postParams['unitId'];
                    $receiptAmount = $postParams['rent_charge'];
                    $mainBillAmt = $postParams['main_bill'];
                    $ebChargeAmt = $postParams['eb_charge'];
                    $teleChargeAmt = $postParams['tele_charge'];
                    $mainBillId = $postParams['main_id'];
                    $ebChargeId = $postParams['eb_id'];
                    $teleChargeId = $postParams['tele_id'];
                    $paymentAmt = $postParams['gross_amount'];
                    $totalPayAmount = $postParams['totalpayamount'];
                    $remarks = $postParams['remarks'];
                    $RentalRegisterId=$postParams['RentalRegId'];
                    $RegisterId=$postParams['RegisterId'];


                    $update = $sql->update();
                    $update->table('PM_PaymentRegister')
                        ->set(array('PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),'PVNo' => $pvNo,
                            'UnitId' => $unitId,'ReceiptAmount' => $receiptAmount,'PaymentAmount' => $paymentAmt,
                            'Amount' => $totalPayAmount,'RentalRegisterId' => $RentalRegisterId,'Remarks' => $remarks))
                        ->where(array('RegisterId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('PM_PaymentTrans')
                        ->where("RegisterId='$RegisterId'");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $service = array("$mainBillId"=>"$mainBillAmt", "$ebChargeId"=>"$ebChargeAmt", "$teleChargeId"=>"$teleChargeAmt");
                    foreach($service as $id => $amount):
                        if($id == 0){
                            continue;
                        }
                        $insert = $sql->insert();
                        $insert->into( 'PM_PaymentTrans' );
                        $insert->Values( array( 'RegisterId' => $RegisterId, 'ServiceId' => $id, 'TransType' => 'P', 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    endforeach;
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'RentalBill-Entry-Modify','E','RentalBill-Entry',$RegisterId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);

                    $this->redirect()->toRoute("crm/default", array("controller" => "pm","action" => "rental-register"));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
			}
            else{
                $RegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'RegisterId' ), 'number' );
                if($RegisterId==0){
                    $this->redirect()->toRoute("crm/default", array("controller" => "pm", "action" => "rental-register"));
                }

                $select = $sql->select();
                $select->from(array("a" => "KF_UnitMaster"))
                    ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                    ->join(array("g" => "PM_RentalTenantTrans"),"e.RentalRegisterId=g.RentalRegisterId",array('LeaserName'),$select::JOIN_LEFT)
                    ->join(array("f" => "PM_PaymentRegister"),"e.RentalRegisterId=f.RentalRegisterId",array('PVNo','RegisterId','PVDate','Amount','ReceiptAmount','PaymentAmount','RentalRegisterId','Remarks'),$select::JOIN_LEFT)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where("f.DeleteFlag='0'AND f.RegisterId='$RegisterId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->payVal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "PM_PaymentTrans"))
                    ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName'),$select::JOIN_LEFT)
                    ->columns(array('ServiceId','Amount'))
                    ->where(array("a.RegisterId" => $RegisterId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->payService = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
			
			//begin trans try block example starts
			//begin trans try block example ends
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function paymentEditAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $registerId = $this->params()->fromRoute('RegisterId'); 
		$userId = $this->auth->getIdentity()->UserId;
		
		$sql     = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' =>'PM_PaymentRegister'))
			->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array('UnitNo'), $select::JOIN_LEFT)
			->join(array("e" => "Crm_UnitBooking"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=e.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('data' => 'UnitId','PVDate','PaymentAmount','ReceiptAmount','PVNo','RentalRegisterId','ChequeNo','ChequeDate','BankName','Amount','Remarks', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName+ ')'")))
			->where(array('a.RegisterId' => $registerId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultpay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		 
		$select = $sql->select();
		$select->from(array("a" => "PM_PaymentTrans"))
			->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName'),$select::JOIN_LEFT)
			->columns(array('ServiceId','Amount'))
			->where(array("a.RegisterId" => $registerId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resulttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		 
		$select = $sql->select(); 
		$select->from('PM_ServiceMaster')
		       ->columns(array('ServiceId','ServiceName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsservice = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
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
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($request->isPost()) {
                        $postData = $request->getPost();
                        //Print_r($postData); die;
                        $pvDate = $this->bsf->isNullCheck($postData['pay_date'], 'string');
                        $pvNo = $this->bsf->isNullCheck($postData['pay_no'], 'string');
                        $unitId = $this->bsf->isNullCheck($postData['unitId'], 'string');
                        $rentalRegId = $this->bsf->isNullCheck($postData['rentalRegId'], 'number');
                        $receiptAmount = $this->bsf->isNullCheck($postData['rent_amount'], 'number');
                        $paymentAmt = $this->bsf->isNullCheck($postData['payment_amount'], 'number');
                        $totalPayAmount = $this->bsf->isNullCheck($postData['amount'], 'number');
                        $chequeno = $this->bsf->isNullCheck($postData['cheque_no'], 'string');
                        $chequedate = $this->bsf->isNullCheck($postData['cheque_date'], 'string');
                        $bank = $this->bsf->isNullCheck($postData['bank_name'], 'string');
                        $remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');

                        $delete = $sql->delete();
                        $delete->from('PM_PaymentTrans')
                            ->where(array('RegisterId' => $registerId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $count = $postData['RowCount'];
                        if ($count != 0) {
                            for ($i = 1; $i <= $count; $i++) {
                                $ser = $postData['service_' . $i];
                                $sam = $postData['sample_' . $i];
                                $select = $sql->insert('PM_PaymentTrans');
                                $newData = array(
                                    'RegisterId' => $registerId,
                                    'ServiceId' => $this->bsf->isNullCheck($sam, 'number'),
                                    'TransType' => 'P',
                                    'Amount' => $this->bsf->isNullCheck($ser, 'number'),
                                );
                                $select->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $update = $sql->update();
                        $update->table('PM_PaymentRegister');
                        $update->set(array(
                            'PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),
                            'PVNo' => $pvNo,
                            'UnitId' => $unitId,
                            'RentalRegisterId' => $rentalRegId,
                            'ReceiptAmount' => $receiptAmount,
                            'PaymentAmount' => $paymentAmt,
                            'Amount' => $totalPayAmount,
                            'ChequeNo' => $chequeno,
                            'ChequeDate' => date('Y/m/d H:i:s', strtotime($chequedate)),
                            'BankName' => $bank,
                            'Remarks' => $remarks
                        ));
                        $update->where(array('RegisterId' => $registerId));
                       $statement = $sql->getSqlStringForSqlObject($update); 
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                       // $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Modify','E','Payment-Entry',$registerId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);

                    $this->redirect()->toRoute('crm/register', array('controller' => 'pm', 'action' => 'payment-register'));

                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}

    public function rentaldeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                        $connection->beginTransaction();
                        $update = $sql->update();
                        $update->table('PM_PaymentRegister')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                            ->where(array('RegisterId' => $RegisterId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'RentalBill-Entry-Delete','D','RentalBill-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);


                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
	public function paymentDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$connection->beginTransaction();
					$update = $sql->update();
					$update->table('PM_PaymentRegister')
						->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
						->where(array('RegisterId' => $RegisterId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

					$connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Delete','D','Payment-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
	
	public function ticketEntryAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$ticketId = $this->params()->fromRoute('TicketId');
		
		$sql = new Sql($dbAdapter);
		$userName = $this->auth->getIdentity()->EmployeeName;
		$userId = $this->auth->getIdentity()->UserId;
		$leadId = $this->params()->fromRoute('leadId');
		
		if($ticketId !=0){
			$select = $sql->select();
           $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
           //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
		   ->where("a.TicketId=$ticketId");
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
		}
		
		//Executives//
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId','EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Lead Name//
		$select = $sql->select(); 
		$select->from('Crm_Leads')
		       ->columns(array('data' => 'LeadId', 'value'=>'LeadName','mail'=>'Email','phone'=>'Mobile'));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	
		
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                   $postData = $request->getPost();
					//Print_r($postData);die;
					$requester = $this->bsf->isNullCheck($postData['requester'],'string');
					$leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
					$mailId = $this->bsf->isNullCheck($postData['mailId'],'string');
					$phone = $this->bsf->isNullCheck($postData['phonenumber'],'number');
					$subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
					$description = $this->bsf->isNullCheck($postData['description'],'string');
                    //$tags = $this->bsf->isNullCheck($postData['tags'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');
                    
                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
					$executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
					$date=date('m-d-Y H:i:s');
					
					if($ticketId ==0){
					$insert  = $sql->insert('Crm_TicketRegister');
                    $newData = array(
                        'CreatedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Status' => $status,
                        'Priority' => $priority,
                        'Email' => $mailId,
                        'Mobile' => $phone,
                        'Description' => $description,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
					if($type=='Lead' && $leadId!=''){
						$insert  = $sql->insert('Crm_Leads');
                        $newData = array(
						'LeadName' => $requester,
                        'Mobile' => $this->bsf->isNullCheck($phone,'string'),
                        'Email' => $this->bsf->isNullCheck($mailId,'string'),
                        'UserId' => $this->bsf->isNullCheck($userId,'number'),
						'NextCallDate'=>date('m-d-Y H:i:s'),
						);
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
					
					$mailData = array(
                        array(
                            'name' => 'TICKETID',
                            'content' => $ticketId
                        ),
                        array(
                            'name' => 'DATE',
                            'content' => $date
                        ),
                        array(
                            'name' => 'STATUS',
                            'content' => $status
                        )
				    );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                 $viewRenderer->MandrilSendMail()->sendMailTo($postData['mailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
					
					
					}
					else{
						$update = $sql->update();
                        $update->table('Crm_TicketRegister');
                        $update->set(array(
                        'ModifiedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Description' => $description,
                        'CliUpdate' => $updatecli,
                        'Status' => $status,
                        'Priority' => $priority,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    ));
                        $update->where(array('TicketId'=>$ticketId));
   					    $statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						if($postData['updatecli']==1){
						$mailData = array(
					array(
						'name' => 'TICKETID',
						'content' => $ticketId
					),
					array(
						'name' => 'DATE',
						'content' => $date
					),
					array(
						'name' => 'STATUS',
						'content' => $status
					)
				);
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                 $viewRenderer->MandrilSendMail()->sendMailTo($postData['MailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
					}
					}
				}
				$connection->commit();
                $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
		}$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function newAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		 
		$sql = new Sql($dbAdapter);
		$userName = $this->auth->getIdentity()->EmployeeName;
		$userId = $this->auth->getIdentity()->UserId;
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				//Print_r($postParams);die;
				$request = $postParams['request'];
				$phone = $postParams['phoneId'];
				$email = $postParams['emailId'];
				
			    $insert  = $sql->insert('Crm_Leads');
                    $newData = array(
                        'LeadName' => $this->bsf->isNullCheck($request,'string'),
						'UserId' => $this->bsf->isNullCheck($userId,'number'),
						'Mobile' => $this->bsf->isNullCheck($phone,'number'),
						'EMail' => $this->bsf->isNullCheck($email,'string'),
						'NextCallDate'=>date('m-d-Y H:i:s'),
                    );
                    $insert->values($newData);
                 $statement = $sql->getSqlStringForSqlObject($insert); 
				   $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				   
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
	
	public function ticketRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$status = $this->params()->fromRoute('status');
        $sql = new Sql($dbAdapter);
		
		
		$where="";
		if(isset($status)){
		
			$where =" where status =".$status;
			$select = $sql->select();
			$select->from('Crm_TicketRegister')
				->where(array("Status"=>$status));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->status = $status;
			
		}
		
		
		
		//selecting values from Executive Table
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/* added lines */
		
        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
            ->where("a.DeleteFlag='0'")
			->order('a.TicketId desc');
			if(isset($status)){
				$select->where(array('a.Status' => $status));
			}
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParams = $request->getPost();
				$ticketId = $postParams['ticketId'];
				$executiveId = $postParams['executiveId'];
                $update = $sql->update();
						$select->table('Crm_TicketRegister');
						$select->set(array(	
                        'ExecutiveId' => $executiveId
                    ));
                $update->where(array('TicketId'=>$ticketId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
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
	public function ticketEditAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$ticketId = $this->params()->fromRoute('TicketId'); 
		$sql = new Sql($dbAdapter);
		
		$select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
           //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
		   ->where("a.TicketId=$ticketId");
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//edit tags//
		//multi tags selection
		$select = $sql->select();
		$select->from('Crm_TicketTags')
			   ->columns(array('TagId'))
			   ->where(array("TicketId"=>$ticketId));
	    $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsMulti  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->leadProjects = array();
		foreach($this->_view->resultsMulti as $this->resultsMulti) {
			$this->leadProjects[] = $this->resultsMulti['TagId'];
		}
		$this->_view->leadProjects = $this->leadProjects;
		
		
		//selecting tags//$select = $sql->select();
		$select->from('Crm_TicketTags')
		       ->columns(array('TagId','TagName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Executives//
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId','EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Lead Name//
		$select = $sql->select(); 
		$select->from('Crm_Leads')
		       ->columns(array('data' => 'LeadId', 'value'=>'LeadName'));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	
		
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postData = $request->getPost();
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($list));
				return $response;
			
		}} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                   $postData = $request->getPost();
					//Print_r($postData);die;
					$requester = $this->bsf->isNullCheck($postData['requester'],'string');
					$leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
					$mailId = $this->bsf->isNullCheck($postData['MailId'],'string');
					$subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');
                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $description = $this->bsf->isNullCheck($postData['description'],'string');
                   // $tags = $this->bsf->isNullCheck($postData['tags'],'string');
				   $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
					$executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
					$date=date('m-d-Y H:i:s');
					// $select = $sql->delete();
					// $select->from('Crm_TicketTags')
							// ->where(array('TicketId' => $ticketId,));
				   // $DelStatement = $sql->getSqlStringForSqlObject($select); 
					// $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Crm_TicketRegister');
                    $update->set(array(
                        'ModifiedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Description' => $description,
                        'CliUpdate' => $updatecli,
                        'Status' => $status,
                        'Priority' => $priority,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    ));
                    $update->where(array('TicketId'=>$ticketId));
					    $statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						if($postData['updatecli']==1){
						$mailData = array(
					array(
						'name' => 'TICKETID',
						'content' => $ticketId
					),
					array(
						'name' => 'DATE',
						'content' => $date
					),
					array(
						'name' => 'STATUS',
						'content' => $status
					)
				);
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                 $viewRenderer->MandrilSendMail()->sendMailTo($postData['MailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
						}
						// foreach ($postData['tags'] as $value){
						// $select = $sql->insert('Crm_TicketTags');
						// $newData = array(
							// 'TicketId' => $ticketId,
							// 'TagName'=> $value,
						// );
					// $select->values($newData);
					// $statement = $sql->getSqlStringForSqlObject($select); 
					// $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				// }
				}
				$connection->commit();
                $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
		}$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function clientbasedserviceAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$ticketId = $this->params()->fromRoute('TicketId'); 
		$sql = new Sql($dbAdapter);
		
		$select = $sql->select();
        $select->from(array("a" => "PM_ServiceMaster"))
            ->columns(array('ServiceId','ServiceName'));
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->service = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->columns(array('TicketId','Requester'));
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				$leadId=$postParams['LeadId'];
				$select->from(array('a' => 'KF_UnitMaster'))
			->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
			->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
			->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
			->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
			->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
		 ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
			->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
			->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('LeadId','BuyerName' => 'BookingName'), $select::JOIN_LEFT)
			//->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
			->where(array("h.LeadId"=>$leadId));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($unitInfo));
				$response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                $response->setStatusCode(200);
				return $response;
			}
		} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			//try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();
				  
				$ticketId = $this->bsf->isNullCheck($postData['ticketId'],'number');
					foreach($postData as $key => $data) {
                        if(preg_match('/^service_[\d]+$/', $key)) {

                            preg_match_all('/^service_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $serviceId = $this->bsf->isNullCheck($postData['service_' . $id], 'number');
                            if($serviceId <= 0) {
                                continue;
                            }

                            $serviceDoneTrans = array(
                                'TicketId' => $ticketId,
                                'ServiceId' => $serviceId,
								'CreatedDate'=>date('m-d-Y H:i:s')
                                
                            );

                            $insert = $sql->insert('Crm_ClientService');
                            $insert->values($serviceDoneTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
						//$this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
                    }
					
				}

				$connection->commit();
			/*} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}