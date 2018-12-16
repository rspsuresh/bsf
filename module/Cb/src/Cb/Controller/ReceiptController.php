<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Cb\Controller;

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

class ReceiptController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function indexAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
        
        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX 
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Receipt");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $userId = $this->auth->getIdentity()->CbUserId;

        if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );

                $select = $sql->select();
                switch($RType) {
					case 'receiptno':
						$data = 'N';
						
                        $select->from( 'CB_ReceiptRegister' )
                        ->columns( array( 'ReceiptId' ) )
                        ->where( "ReceiptNo='$PostDataStr'" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if (sizeof($results) !=0 )
                            $data ='Y';
                        break;
					case 'getProject':
						$select = $sql->select();
						$select->from( 'CB_ProjectMaster' )
							->columns( array( 'data' => 'ProjectId', 'value' => 'ProjectName') )
								->where( "ClientId='$PostDataStr' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						$data = json_encode($results);
                        break;									
                    case 'getWONo':
                        $select->from( 'CB_WORegister' )
                            ->columns( array( 'data' => 'WorkOrderId', 'value' => 'WONo', 'WODate' => new Expression("FORMAT(WODate, 'dd-MM-yyyy')") ) )
                            ->where( "ProjectId='$PostDataStr'" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $data = json_encode($results);
                        break;
                    case 'billformat':
                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillMaster' ))
                            ->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount', 'CertifyAmount') )
                            ->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression("Sum(b.Amount)")), $select::JOIN_INNER)
                            ->join(array('c' => 'CB_ReceiptRegister'), 'b.ReceiptId=c.ReceiptId', array(), $select::JOIN_INNER)
                            ->where("a.WORegisterId=$PostDataStr And a.IsSubmittedBill='1' And a.IsCertifiedBill='1' AND c.DeleteFlag=0")
                            ->group(new Expression('a.BillId,a.BillNo,a.BillDate,a.SubmitAmount,a.CertifyAmount'));

                        $subSelect = $sql->select();
                        $subSelect->from( array('a' => 'CB_ReceiptAdustment' ))
                            ->columns( array('BillId') )
                            ->join(array('b' => 'CB_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array( ), $subSelect::JOIN_INNER)
                            ->where("b.DeleteFlag=0");

                        $select2 = $sql->select();
                        $select2->from( array('a' => 'CB_BillMaster' ))
                            ->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount', 'CertifyAmount','AdjAmount' => new Expression("1-1")) )
                            ->where("a.WORegisterId=$PostDataStr And a.IsSubmittedBill='1' And a.IsCertifiedBill='1'")
                            ->where->notIn('a.BillId', $subSelect);
                        $select2->combine($select,'Union ALL');

                        $selectFinal = $sql->select();
                        $selectFinal->from(array('g'=>$select2))
                            ->columns(array('BillId' ,'BillDate','BillNo', 'SubmitAmount', 'CertifyAmount','AdjAmount' => new Expression("SUM(g.AdjAmount)") ));
                        $selectFinal->group(new Expression('g.BillId,g.BillDate,g.BillNo,g.SubmitAmount,g.CertifyAmount'));

                        $statement = $sql->getSqlStringForSqlObject($selectFinal);
                        $billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach($billformats as &$bill) {
                            $billId = $bill['BillId'];
                            $select = $sql->select();
                            $select->from( array('a' => 'CB_BillAbstract' ))
                                ->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( 'BillId'), $select::JOIN_LEFT)
                                ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', array( 'AdjAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                                ->join(array('c1' => 'CB_ReceiptRegister'), 'c.ReceiptId=c1.ReceiptId', array(), $select::JOIN_INNER)
								->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select:: JOIN_INNER)
                                ->columns( array('BillAbsId', 'BillFormatId','CurAmount' => 'CerCurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")))
                                ->where("a.BillId=$billId AND a.CerCurAmount<>0 AND a.BillFormatId<>0 AND c1.DeleteFlag=0")
                                ->group(new Expression('a.BillAbsId,a.BillFormatId,a.CerCurAmount,d.Description,b.TypeName,a1.BillId'));

                            $statement = $sql->getSqlStringForSqlObject($select);
                            $billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $bill['BillAbs'] = $billabs;
                        }
                        $data = json_encode($billformats);
                        break;
                    case 'getAmounts':
                        $ReceiptAgainst = $this->bsf->isNullCheck( $postData[ 'ReceiptAgainst' ], 'string' );
                        switch($ReceiptAgainst) {
                            case 'M': //Mobilization Advance
                                $select = $sql->select();
                                $select->from( 'CB_WOTerms' )
                                    ->columns( array( 'MobilisationAmount' ) )
                                    ->where( "WORegisterId=$PostDataStr" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $woTerms = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='M' AND WORegisterId=$PostDataStr AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $data = json_encode( array( 'Amount' => $woTerms[ 'MobilisationAmount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $woTerms[ 'MobilisationAmount' ] - $receipt[ 'Amount' ] ) ) );
                                break;
                            case 'R': //Retention
                                $subQuery = $sql->select();
                                $subQuery->from("CB_BillMaster")
                                    ->columns(array('BillId'))
                                    ->where("WORegisterId=$PostDataStr AND Certified=1 AND IsCertifiedBill=1 AND DeleteFlag=0");

                                $select = $sql->select();
                                $select->from( 'CB_BillAbstract' )
                                    ->columns( array( 'Amount' => new Expression('Sum(Case When CerCurAmount<>0 then CerCurAmount else CurAmount End)') ) )
                                    ->where->expression('BillFormatId=9 and BillId  IN ?', array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $billAbs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='R' AND WORegisterId=$PostDataStr AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $data = json_encode( array( 'Amount' => $billAbs[ 'Amount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $billAbs[ 'Amount' ] - $receipt[ 'Amount' ] ) ) );
                                break;
                            case 'W': //With held
                                $subQuery = $sql->select();
                                $subQuery->from("CB_BillMaster")
                                    ->columns(array('BillId'))
                                    ->where("WORegisterId=$PostDataStr AND DeleteFlag=0");

                                $select = $sql->select();
                                $select->from( 'CB_BillAbstract' )
                                    ->columns( array( 'Amount' => new Expression('Sum(Case When CerCurAmount<>0 then CerCurAmount else CurAmount End)') ) )
                                    ->where->expression('BillFormatId=20 and BillId  IN ?', array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $billAbs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='R' AND WORegisterId=$PostDataStr AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $data = json_encode( array( 'Amount' => $billAbs[ 'Amount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $billAbs[ 'Amount' ] - $receipt[ 'Amount' ] ) ) );
                                break;
                        }
                        break;
                }

                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
				$recpid = $this->bsf->isNullCheck($postData['receiptid'],'number');
				$ReceiptAgainst = $this->bsf->isNullCheck($postData['ReceiptAgainst'],'string');
                try {
					if($recpid == 0) {
						$PWorkOrderId = $this->bsf->isNullCheck($postData['PWorkOrderId'],'number');
						$ReceiptDate = $this->bsf->isNullCheck($postData['ReceiptDate'],'string');
                        $ReceiptDate = date('Y-m-d', strtotime($ReceiptDate));
						$ReceiptNo = $this->bsf->isNullCheck($postData['ReceiptNo'],'string');
						$PaymentMode = $this->bsf->isNullCheck($postData['PaymentMode'],'string');
						$TDate = $this->bsf->isNullCheck($postData['TDate'],'string');
						$Remarks = $this->bsf->isNullCheck($postData['Remarks'],'string');
						$Amount = $this->bsf->isNullCheck($postData['Amount'],'number');

                        $TNo = "";
                        $BankName = "";
                        if($PaymentMode != 'CASH') {
                            $TNo = $this->bsf->isNullCheck($postData['TNo'],'string');
                            $BankName = $this->bsf->isNullCheck($postData['BankName'],'string');
                        }

                        $connection->beginTransaction();
						$insert = $sql->insert();
						$insert->into('CB_ReceiptRegister');
						$insert->Values(array('WORegisterId' => $PWorkOrderId, 'ReceiptNo' => $ReceiptNo, 'ReceiptDate' => $ReceiptDate
										, 'ReceiptAgainst' => $ReceiptAgainst, 'ReceiptMode' => $PaymentMode, 'TransactionNo' => $TNo,'TransactionDate' => date('Y-m-d', strtotime($TDate))
										, 'TransactionRemarks' => $Remarks, 'Amount' => $Amount, 'BankName' => $BankName
										, 'ClientId' => $this->bsf->isNullCheck($postData['ClientId'],'number') ));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ReceiptId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        // if receipt type is bill
						if($postData['billrowid']) {
                            $billrowid = $this->bsf->isNullCheck( $postData[ 'billrowid' ], 'number' );
                            for ( $i = 1; $i <= $billrowid; $i++ ) {
                                $BillId = $this->bsf->isNullCheck( $postData[ 'BillId_' . $i ], 'number' );
                                $BAmount = $this->bsf->isNullCheck( $postData[ 'CurAmt_' . $i ], 'number' );

                                if ( $BAmount == 0 || $BillId == 0 )
                                    continue;

                                $insert = $sql->insert();
                                $insert->into( 'CB_ReceiptAdustment' );
                                $insert->Values( array( 'ReceiptId' => $ReceiptId, 'BillId' => $BillId, 'Amount' => $BAmount ) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                                $billabsrowid = $this->bsf->isNullCheck( $postData[ 'billabsrowid_' . $i ], 'number' );
                                for ( $j = 1; $j <= $billabsrowid; $j++ ) {
                                    $FormatId = $this->bsf->isNullCheck( $postData[ 'BillAbs_' . $i . '_FormatId_' . $j ], 'number' );
                                    $AbsAmount = $this->bsf->isNullCheck( $postData[ 'BillAbs_' . $i . '_CurAmt_' . $j ], 'number' );

                                    if ( $AbsAmount == 0 || $FormatId == 0 )
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into( 'CB_ReceiptAdustmentTrans' );
                                    $insert->Values( array( 'ReceiptId' => $ReceiptId, 'BillFormatId' => $FormatId, 'BillId' => $BillId, 'Amount' => $AbsAmount ) );
                                    $statement = $sql->getSqlStringForSqlObject( $insert );
                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                }
                            }
                        }
                        CommonHelper::insertCBLog('Client-Receipt-Add',$ReceiptId,$ReceiptNo,$dbAdapter);
                        $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "I");

                        // trigger mail
                        $select = $sql->select();
                        $select->from("CB_SubscriberMaster")
                            ->columns(array('Email'))
                            ->where("SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $subscriber = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from("CB_Users")
                            ->columns(array('Email'))
                            ->where("CbUserId=$userId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $user = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => "CB_WORegister"))
                            ->join(array('c' => 'CB_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'CB_ProjectMaster'), 'a.ProjectId=d.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
                            ->columns(array('WONo', 'WODate'))
                            ->where(array("a.WorkOrderId" => $PWorkOrderId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $workOrder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $mailData = array(
                            array(
                                'name' => 'RECEIPTNO',
                                'content' => $ReceiptNo
                            ),
                            array(
                                'name' => 'RECEIPTDATE',
                                'content' => date('d-m-Y', strtotime($ReceiptDate))
                            ),
                            array(
                                'name' => 'ORDERID',
                                'content' => $workOrder['WONo']
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => date('d-m-Y', strtotime($workOrder['WODate']))
                            ),
                            array(
                                'name' => 'PROJECTNAME',
                                'content' => $workOrder['ProjectName']
                            ),
                            array(
                                'name' => 'CLIENTNAME',
                                'content' => $workOrder['ClientName']
                            ),
                            array(
                                'name' => 'DESCRIPTION',
                                'content' => $Remarks
                            ),
                            array(
                                'name' => 'PAYMENTMODE',
                                'content' => ucfirst($PaymentMode)
                            ),
                            array(
                                'name' => 'PAYMENTDATE',
                                'content' => date('d-m-Y', strtotime($ReceiptDate))
                            ),
                            array(
                                'name' => 'AMOUNT',
                                'content' => $viewRenderer->commonHelper()->sanitizeNumber($Amount,2,true)
                            )
                        );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        if($subscriber && $subscriber['Email'] != '') {
                            $viewRenderer->MandrilSendMail()->sendMailTo($subscriber['Email'], $config['general']['mandrilEmail'], 'Receipt Created', 'cb_billreceipt', $mailData );
                        }
                        if($user && $user['Email'] != '' && ($subscriber && $subscriber['Email'] != $user['Email'])) {
                            $viewRenderer->MandrilSendMail()->sendMailTo($user['Email'], $config['general']['mandrilEmail'], 'Receipt Created', 'cb_billreceipt', $mailData );
                        }

                        $connection->commit();
                        $this->redirect()->toRoute('cb/receipt', array('controller' => 'receipt', 'action' => 'index'));
					} elseif($recpid != 0 && $ReceiptAgainst != 'B'){
                        $PWorkOrderId = $this->bsf->isNullCheck($postData['PWorkOrderId'],'number');
						$ReceiptNo = $this->bsf->isNullCheck($postData['ReceiptNo'],'string');
                        $ReceiptDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ReceiptDate'],'string')));
                        $PaymentMode = $this->bsf->isNullCheck($postData['PaymentMode'],'string');
                        $TDate = $this->bsf->isNullCheck($postData['TDate'],'string');
                        $Remarks = $this->bsf->isNullCheck($postData['Remarks'],'string');
                        $Amount = $this->bsf->isNullCheck($postData['Amount'],'number');

                        $TNo = "";
                        $BankName = "";
                        if($PaymentMode != 'CASH') {
                            $TNo = $this->bsf->isNullCheck($postData['TNo'],'string');
                            $BankName = $this->bsf->isNullCheck($postData['BankName'],'string');
                        }

                        $connection->beginTransaction();
                        $update = $sql->update();
                        $update->table('CB_ReceiptRegister')
                            ->set(array('WORegisterId' => $PWorkOrderId, 'ReceiptDate' => $ReceiptDate, 'ReceiptMode' => $PaymentMode
                                        , 'ReceiptAgainst' => $ReceiptAgainst , 'TransactionNo' => $TNo,'TransactionDate' => date('Y-m-d', strtotime($TDate))
                                        , 'TransactionRemarks' => $Remarks, 'Amount' => $Amount, 'BankName' =>  $BankName))
                            ->where(array('ReceiptId' => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('Client-Receipt-Edit',$recpid,$ReceiptNo,$dbAdapter);
                        $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        $connection->commit();
                        $this->redirect()->toRoute('cb/receipt', array('controller' => 'receipt', 'action' => 'index'));
                    } elseif($recpid != 0 && $ReceiptAgainst == 'B'){
						//Edit Receipt Entry					
						$Amount = $this->bsf->isNullCheck($postData['Amount'],'number');
                        $ReceiptNo = $this->bsf->isNullCheck($postData['ReceiptNo'],'string');

                        $connection->beginTransaction();

						//delete CB_ReceiptAdustmentTrans
						$delete = $sql->delete();
						$delete->from('CB_ReceiptAdustmentTrans')
								->where(array("ReceiptId" => $recpid));
						$statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//delete CB_ReceiptAdustment
                        $delete = $sql->delete();
                        $delete->from('CB_ReceiptAdustment')
                            ->where(array("ReceiptId" => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

						//Update Receipt Register
						$update = $sql->update();
                        $update->table('CB_ReceiptRegister');
                        $update->set(array('Amount' => $Amount ));
                        $update->where(array('ReceiptId' => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

						$billrowid = $this->bsf->isNullCheck($postData['billrowid'],'number');
						for ($i = 1; $i <= $billrowid; $i++) {
							$BillId = $this->bsf->isNullCheck($postData['BillId_' . $i],'number');
							$BAmount = $this->bsf->isNullCheck($postData['CurAmt_' . $i],'number');

							if ($BAmount == 0 || $BillId==0) continue;

							$insert = $sql->insert();
							$insert->into('CB_ReceiptAdustment');
							$insert->Values(array('ReceiptId' => $recpid,'BillId' => $BillId, 'Amount'=> $BAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

							$billabsrowid = $this->bsf->isNullCheck($postData['billabsrowid_'.$i],'number');
							for ($j = 1; $j <= $billabsrowid; $j++) {
								$FormatId = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_FormatId_' . $j],'number');
								$AbsAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_CurAmt_' . $j],'number');

								if ($AbsAmount == 0 || $FormatId==0) continue;

								$insert = $sql->insert();
								$insert->into('CB_ReceiptAdustmentTrans');
								$insert->Values(array('ReceiptId' => $recpid,'BillFormatId' => $FormatId,'BillId' => $BillId,'Amount'=> $AbsAmount));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							}
						}
                        CommonHelper::insertCBLog('Client-Receipt-Edit',$recpid,$ReceiptNo,$dbAdapter);
                        $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "I");

                        $connection->commit();
                        $this->redirect()->toRoute('cb/receipt', array('controller' => 'receipt', 'action' => 'index'));
					}
                } catch(PDOException $e){
                    $connection->rollback();
                }
			} else {
				$editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
                $mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );

				if ($editid != 0) {
                    $select = $sql->select();
                    $select->from( array( "a" => "CB_ReceiptRegister" ) )
                        ->join( array( "b" => "CB_WORegister" ), "a.WORegisterId=b.WorkOrderId", array("WONo" ), $select::JOIN_INNER )
                        ->join( array( 'c' => 'CB_ProjectMaster' ), 'b.ProjectId=c.ProjectId', array( "ProjectName", "ProjectId" ), $select::JOIN_LEFT )
                        ->join( array( 'e' => 'CB_ClientMaster' ), 'b.ClientId=e.ClientId', array( "ClientName", "ClientId" ), $select::JOIN_LEFT )
                        ->columns( array( "WORegisterId", "ReceiptNo", "ReceiptDate" => new Expression( "FORMAT(a.ReceiptDate, 'dd-MM-yyyy')" )
                                   , "ReceiptAgainst", "ReceiptMode", "TransactionNo", "TransactionDate" => new Expression( "FORMAT(a.TransactionDate, 'dd-MM-yyyy')" )
                                   , "TransactionRemarks", "BankName","Amount" ), array( "ProjectId", "WONo" ), array( "ProjectName" ) );
                    $select->where( array( 'a.DeleteFlag' => '0', 'a.ReceiptId' => $editid ) );
                    $statement = $statement = $sql->getSqlStringForSqlObject( $select );
                    $receiptregister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $rworegid = $receiptregister->WORegisterId;
                    $this->_view->receiptregister = $receiptregister;

                    if($receiptregister->ReceiptAgainst == 'B') {
                        //trans
                        $select1 = $sql->select();
                        $select1->from( array( 'a' => 'CB_BillMaster' ) )
                            ->columns( array( 'BillId', 'BillDate' => new Expression( "FORMAT(BillDate, 'dd-MM-yyyy')" ), 'BillNo', 'SubmitAmount', 'CertifyAmount' ) )
                            ->join( array( 'b' => 'CB_ReceiptAdustment' ), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression( "CAST(0 As Decimal(18,2))" ), 'PrevAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select1::JOIN_LEFT )
                            ->where( array( 'a.WoRegisterId' => $rworegid ) );
                        $select1->group( new Expression( 'a.BillId,a.BillNo,a.BillDate,a.SubmitAmount,a.CertifyAmount' ) );

                        $select2 = $sql->select();
                        $select2->from( array( "a" => "CB_BillMaster" ) )
                            ->columns( array( 'BillId', 'BillDate' => new Expression( "FORMAT(BillDate, 'dd-MM-yyyy')" ), 'BillNo', 'SubmitAmount' => new Expression( "CAST(0 As Decimal(18,2))" )
                                       , 'CertifyAmount' => new Expression( "CAST(0 As Decimal(18,2))" ), 'AdjAmount' => new Expression( "CAST(0 As Decimal(18,2))" ) ) )
                            ->join( array( 'b' => 'CB_ReceiptAdustment' ), 'a.BillId=b.BillId', array( 'PrevAmt' => new Expression( "Sum(b.Amount)" ) ), $select2::JOIN_INNER );
                        $select2->where( "b.ReceiptId<>$editid and a.WoRegisterId=$rworegid" );
                        $select2->combine( $select1, 'Union ALL' );
                        $select2->group( new Expression( 'a.BillId,a.BillNo,a.BillDate' ) );

                        $select2Edit = $sql->select();
                        $select2Edit->from( array( "a" => "CB_BillMaster" ) )
                            ->columns( array( 'BillId', 'BillDate' => new Expression( "FORMAT(BillDate, 'dd-MM-yyyy')" ), 'BillNo', 'SubmitAmount' => new Expression( "CAST(0 As Decimal(18,2))" )
                                       , 'CertifyAmount' => new Expression( "CAST(0 As Decimal(18,2))" ) ) )
                            ->join( array( 'b' => 'CB_ReceiptAdustment' ), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression( "Sum(b.Amount)" ), 'PrevAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select2Edit::JOIN_INNER );
                        $select2Edit->where( "b.ReceiptId=$editid" );
                        $select2Edit->combine( $select2, 'Union ALL' );
                        $select2Edit->group( new Expression( 'a.BillId,a.BillNo,a.BillDate' ) );

                        $select3 = $sql->select();
                        $select3->from( array( "g" => $select2Edit ) )
                            ->columns( array( 'BillId', 'BillDate', 'BillNo', "SubmitAmount" => new Expression( "Sum(g.SubmitAmount)" )
                                       , "CertifyAmount" => new Expression( "Sum(g.CertifyAmount)" ), "CurAmount" => new Expression( "Sum(g.AdjAmount)" ), "AdjAmount" => new Expression( "Sum(g.PrevAmt)" ) ) );
                        $select3->group( new Expression( 'g.BillId,g.BillNo,g.BillDate' ) );
                        $statement = $sql->getSqlStringForSqlObject( $select3 );
                        $billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach ( $billformats as &$bill ) {
                            $billId = $bill[ 'BillId' ];
                            $select = $sql->select();
                            $select->from( array( 'a' => 'CB_BillAbstract' ) )
                                ->columns( array( 'BillId', 'BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName' => new Expression( "Case When d.Description<>'' then d.Description else b.TypeName End" ) ) )
                                ->join( array( 'a1' => 'CB_ReceiptAdustment' ), 'a.BillId=a1.BillId', array(), $select::JOIN_LEFT )
                                ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'CB_ReceiptAdustmentTrans' ), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId',
                                        array( 'AdjAmount' => new Expression( "CAST(0 As Decimal(18,2))" ), 'CurrentAmount' => new Expression( "Sum(c.Amount)" ) ), $select::JOIN_LEFT )
                                ->join( array( 'd' => 'CB_BillFormatTrans' ), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select:: JOIN_INNER );
                            //->where("a.BillId=$billId")
                            $select->where( array( 'a.BillId' => $billId, 'c.ReceiptId' => $editid ) );
                            $select->where( "a.BillFormatId<>0" );
                            $select->group( new Expression( 'a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName' ) );

                            $select2 = $sql->select();
                            $select2->from( array( 'a' => 'CB_BillAbstract' ) )
                                ->columns( array( 'BillId', 'BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName' => new Expression( "Case When d.Description<>'' then d.Description else b.TypeName End" ) ) )
                                ->join( array( 'a1' => 'CB_ReceiptAdustment' ), 'a.BillId=a1.BillId', array(), $select2::JOIN_LEFT )
                                ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array(), $select2::JOIN_LEFT )
                                ->join( array( 'c' => 'CB_ReceiptAdustmentTrans' ), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId',
                                        array( 'AdjAmount' => new Expression( "Sum(c.Amount)" ), 'CurrentAmount' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select2::JOIN_LEFT )
                                ->join( array( 'd' => 'CB_BillFormatTrans' ), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select2:: JOIN_INNER );
                            //->where("a.BillId=$billId")
                            $select2->where( "a.BillId=$billId AND c.ReceiptId<>$editid" );
                            $select2->where( "a.BillFormatId<>0" );
                            $select2->group( new Expression( 'a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName' ) );
                            $select2->combine( $select, 'Union ALL' );

                            $select21 = $sql->select();
                            $select21->from( array( 'a' => 'CB_BillAbstract' ) )
                                ->columns( array( 'BillId', 'BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName' => new Expression( "Case When d.Description<>'' then d.Description else b.TypeName End" ) ) )
                                ->join( array( 'a1' => 'CB_ReceiptAdustment' ), 'a.BillId=a1.BillId', array(), $select21::JOIN_LEFT )
                                ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId',
                                        array( 'AdjAmount' => new Expression( "CAST(0 As Decimal(18,2))" ), 'CurrentAmount' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select21::JOIN_LEFT )
                                ->join( array( 'd' => 'CB_BillFormatTrans' ), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select21:: JOIN_INNER );
                            //->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId',
                            //array( 'AdjAmount' => new Expression("Sum(c.Amount)"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT);
                            $select21->where( "a.BillId=$billId AND a.CurAmount<>0" );
                            $select21->where( "a.BillFormatId<>0" );
                            $select21->group( new Expression( 'a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName' ) );
                            $select21->combine( $select2, 'Union ALL' );

                            $select3 = $sql->select();
                            $select3->from( array( "g" => $select21 ) )
                                ->columns( array( 'BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName', 'BillId', "AdjAmount" => new Expression( "Sum(g.AdjAmount)" )
                                           , "CurrentAmount" => new Expression( "Sum(g.CurrentAmount)" ) ) );
                            $select3->group( new Expression( 'g.BillAbsId,g.BillFormatId,g.CurAmount,g.TypeName,g.BillId' ) );

                            $statement = $sql->getSqlStringForSqlObject( $select3 );
                            $billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $bill[ 'BillAbs' ] = $billabs;
                        }
                        $this->_view->billformats = $billformats;
                    } else {
                        switch($receiptregister->ReceiptAgainst) {
                            case 'M': //Mobilization Advance
                                $select = $sql->select();
                                $select->from( 'CB_WOTerms' )
                                    ->columns( array( 'MobilisationAmount' ) )
                                    ->where( "WORegisterId=$rworegid" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $woTerms = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='M' AND WORegisterId=$rworegid AND ReceiptId<>$editid AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $this->_view->receiptAmounts = array( 'Amount' => $woTerms[ 'MobilisationAmount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $woTerms[ 'MobilisationAmount' ] - $receipt[ 'Amount' ] ) );
                                break;
                            case 'R': //Retention
                                $subQuery = $sql->select();
                                $subQuery->from("CB_BillMaster")
                                    ->columns(array('BillId'))
                                    ->where("WORegisterId=$rworegid AND DeleteFlag=0");

                                $select = $sql->select();
                                $select->from( 'CB_BillAbstract' )
                                    ->columns( array( 'Amount' => new Expression('Sum(Case When CerCurAmount<>0 then CerCurAmount else CurAmount End)') ) )
                                    ->where->expression('BillFormatId=9 and BillId  IN ?', array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $billAbs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='R' AND WORegisterId=$rworegid AND ReceiptId<>$editid AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $this->_view->receiptAmounts = array( 'Amount' => $billAbs[ 'Amount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $billAbs[ 'Amount' ] - $receipt[ 'Amount' ] ) );
                                break;
                            case 'W': //With held
                                $subQuery = $sql->select();
                                $subQuery->from("CB_BillMaster")
                                    ->columns(array('BillId'))
                                    ->where("WORegisterId=$rworegid AND DeleteFlag=0");

                                $select = $sql->select();
                                $select->from( 'CB_BillAbstract' )
                                    ->columns( array( 'Amount' => new Expression('Sum(Case When CerCurAmount<>0 then CerCurAmount else CurAmount End)') ) )
                                    ->where->expression('BillFormatId=20 and BillId  IN ?', array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $billAbs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                $select = $sql->select();
                                $select->from( 'CB_ReceiptRegister' )
                                    ->columns( array( 'Amount' => new Expression( 'Sum(Amount)' ) ) )
                                    ->where( "ReceiptAgainst='R' AND WORegisterId=$rworegid AND ReceiptId<>$editid AND DeleteFlag=0" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $this->_view->receiptAmounts = array( 'Amount' => $billAbs[ 'Amount' ], 'RecoveredAmount' => $receipt[ 'Amount' ]
                                                     , 'BalanceAmount' => ( $billAbs[ 'Amount' ] - $receipt[ 'Amount' ] ) );
                                break;
                        }
                    }
				}
				
				// Client
                $select = $sql->select();
                $select->from( 'CB_ClientMaster' )
                    ->columns( array( 'data' => 'ClientId', 'value' => 'ClientName') )
                    ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->clients = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				
				// BankName
                $select = $sql->select();
                $select->from(array("a"=>"CB_ReceiptRegister"))
                    ->join(array("b"=>"CB_WORegister"), "a.WORegisterId=b.WorkOrderId", array(), $select::JOIN_INNER)
                    ->columns( array( 'data' => new Expression("CAST(0 As Decimal(18,0))"), 'value' => 'BankName') )
					->where("a.BankName<>'' and b.SubscriberId=$subscriberId");
				$select->group(new Expression('BankName'));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->banks = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if (!$aVNo["genType"])
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];
					
				$this->_view->receiptid = $editid;
                $this->_view->mode = $mode;

                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}

	public function registerAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Receipt Register");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $select = $sql->select();
        $select->from(array('a' => 'CB_ReceiptRegister'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
            ->join(array('d' => 'CB_ClientMaster'), 'b.ClientId=d.ClientId', array('ClientName'), $select::JOIN_LEFT)
            ->columns(array( 'ReceiptId','ReceiptNo','ReceiptDate' => new Expression("FORMAT(ReceiptDate, 'dd-MM-yyyy')"),'WORegisterId'
                       ,'ReceiptMode', 'ReceiptAgainst', 'TransactionNo', 'TransactionDate' => new Expression("FORMAT(TransactionDate, 'dd-MM-yyyy')"),
                       'TransactionRemarks', 'Amount'))
            ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId");

        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->receipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'CB_BillMaster'))
               ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
               ->columns(array('submitAmount' =>new Expression("Sum(SubmitAmount)")))
               ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->submitvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_BillMaster'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
            ->columns(array('certifyAmount' =>new Expression("Sum(CertifyAmount)")))
            ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->certifyvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_ReceiptRegister'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
            ->columns(array('receiptAmount' =>new Expression("Sum(Amount)")))
            ->where("a.DeleteFlag='0' and a.ReceiptAgainst='B' and b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->receivedvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();


        $select = $sql->select();
        $select->from(array('a' => 'CB_BillAbstract'))
            ->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_WORegister'), 'b.WORegisterId=c.WorkOrderId', array(),$select:: JOIN_LEFT)
            ->columns(array('Amount' =>new Expression("Sum(CerCurAmount)")))
            ->where("(a.BillFormatId='9' or a.BillFormatId='20') and b.IsCertifiedBill = 1 and b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->retvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_ReceiptRegister'))
               ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
               ->columns(array('Amount' =>new Expression("Sum(Amount)")))
               ->where("a.DeleteFlag='0' and a.ReceiptAgainst='M' and b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->advancevalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_BillAdvanceRecovery'))
            ->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(),$select:: JOIN_LEFT)
            ->join(array('d' => 'CB_WORegister'), 'c.WORegisterId=d.WorkOrderId', array(),$select:: JOIN_LEFT)
            ->columns(array('Amount' =>new Expression("Sum(Amount)")))
            ->where("a.ReceiptId<>0 and d.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->advancedeductvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_ReceiptRegister'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('ClientId'),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_ClientMaster'), 'b.ClientId=c.ClientId',array('ClientName'), $select:: JOIN_LEFT)
            ->columns(array('Amount' =>new Expression("sum(a.Amount)")),array('ClientName'))
            ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId")
            ->group(array('c.ClientName','b.ClientId'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->clientreceipts = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'CB_ReceiptRegister'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId',array(),$select:: JOIN_LEFT)
            ->columns(array('Mon' => new Expression("month(ReceiptDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),3) + '-' + ltrim(str(Year(ReceiptDate)))"),'Amount' =>new Expression("sum(Amount)")))
            ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId")
            ->group(new Expression('month(ReceiptDate), LEFT(DATENAME(MONTH,ReceiptDate),3),Year(ReceiptDate)'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->monreceipt = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}

    public function deletereceiptAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $ReceiptId = $this->bsf->isNullCheck($this->params()->fromPost('ReceiptId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('CB_ReceiptRegister')
                        ->columns(array('ReceiptNo'))
                        ->where(array("ReceiptId" => $ReceiptId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $ReceiptNo="";
                    if (!empty($bills)) { $ReceiptNo =$bills->ReceiptNo; }

                    $update = $sql->update();
                    $update->table('CB_ReceiptRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                        ->where(array('ReceiptId' => $ReceiptId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Client-Receipt-Delete',$ReceiptId,$ReceiptNo,$dbAdapter);

                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
}