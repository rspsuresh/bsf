<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Ats\Controller;

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

class RfqController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function newRfqAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		$iQuotType = $this->params()->fromRoute('quottypeid');

        $sQuotType="T";
        if($iQuotType==1)
        {
            $sQuotType="Q";
        }
			
		$vNo = CommonHelper::getVoucherNo(203,date('Y/m/d') ,0,0, $dbAdapter,"");
		if($request->isXmlHttpRequest()){
			if ($request->isPost()) {
				//Write your Ajax post code here
				$resp =  array();
				$postParams = $request->getPost();
                $mode = $this->bsf->isNullCheck($postParams['mode'], 'string');
                if($mode == 'decisionPicklist')
				{				
					$select1 = $sql->select(); 
					$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
							->columns(array("DecisionId","IndentQty", "QuotQty"=>new Expression("1-1")));
						 
					$select2 = $sql->select(); 
					$select2->from(array("a"=>'VM_RFQTrans'))
							->columns(array("DecisionId","IndentQty"=>new Expression("1-1"), "QuotQty"=>new Expression("Quantity")));			
					$select2->combine($select1,'Union ALL');
					
					$resSelect = $sql->select(); 
					$resSelect->from(array("g"=>$select2))
							->columns(array("DecisionId","IndQty"=>new Expression("SUM(isnull(g.IndentQty,0))"),"QtQty"=>new Expression("SUM(isnull(g.QuotQty,0))") ))	
							->group(new expression('g.DecisionId HAVING (SUM(isnull(g.IndentQty,0))-SUM(isnull(g.QuotQty,0)))>0'));
							
					$resFinal = $sql->select(); 
					$resFinal->from(array("g1"=>$resSelect))
							->columns(array("DecisionId"=>new Expression("Distinct g1.DecisionId ")));	
							
					$select = $sql->select();		
					$select->from(array("a"=>"VM_RequestDecision"))
						   ->columns(array(new Expression("a.DecisionId,a.RDecisionNo,Convert(varchar(10),a.DecDate,105) as DecDate")))
						   ->join(array('b'=>'VM_ReqDecTrans'), 'a.DecisionId=b.DecisionId', array(), $select:: JOIN_INNER)
						   ->join(array('c'=>'VM_RequestRegister'), 'b.RequestId=c.RequestId', array(), $select:: JOIN_INNER)				   
						   ->where(array('c.CostCentreId'=>$postParams['project_name'],
								'a.RequestType'=>($postParams['qtn_type']),"a.Approve"=>'Y'));
					$select->where->expression('a.DecisionId IN ?', array($resFinal));			
					$select->group(new expression('a.DecisionId,a.RDecisionNo,a.DecDate'))
						   ->order('a.DecDate desc');
                     $statementDecision = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statementDecision, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
                else if($mode == 'requestPicklist'){

                    $type = $this->bsf->isNullCheck($postParams['qtype'], 'string');
                    $Select = $sql->select();
                    $Select->from(array("a" =>"VM_RequestRegister"))
                        ->columns(array(new Expression("a.RequestId,a.RequestNo,Convert(varchar(10),a.RequestDate,105) as RequestDate")))
                        ->where(array("a.Approve = 'Y' and a.RequestType = '$type'"));
                    $Statement = $sql->getSqlStringForSqlObject($Select);
                    $resp['data'] = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
				else if($mode == 'decisionEntry'){
					//Resource
					/*$resSelect = $sql->select();		
					$resSelect->from(array('a'=>'VM_ReqDecQtyTrans'))
						->columns(array("Quantity"=>new Expression("sum(a.IndentQty)")), array("ResourceId"), array("Code","ResourceName","UnitId"), array("UnitName"))
						->join(array('b'=>'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array("ResourceId"), $resSelect:: JOIN_INNER)
						->join(array('c'=>'Proj_Resource'), 'b.ResourceId=c.ResourceId', array("Code","ResourceName","UnitId"), $resSelect:: JOIN_INNER)
						->join(array('d'=>'Proj_UOM'), 'c.UnitId=d.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)			   
						->where(array('a.DecisionId'=>explode(',', $postParams['decId'])))
						->group(new expression('b.ResourceId,c.Code,c.ResourceName,c.UnitId,d.UnitName'));*/
						

					$select1 = $sql->select(); 
					$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
							->columns(array("Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
							->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId","ItemId"), $select1::JOIN_INNER)
							->where(array('a.DecisionId'=>explode(',',($postParams['decId']))));
						 
					$select2 = $sql->select(); 
					$select2->from(array("a"=>'VM_RFQTrans'))
							->columns(array("Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId","ItemId"))
							->where(array('a.DecisionId'=>explode(',',($postParams['decId']))));
					$select2->combine($select1,'Union ALL');

					$resSelect = $sql->select(); 
					$resSelect->from(array("g"=>$select2))
//							->columns(array("ResourceId","ItemId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) "),"Code"=>array(new Expression("Case when g.ItemId>0 Then e.ItemCode Else b.Code End As Code")),"ResourceName"=>array(new Expression("Case When g.ItemId>0 Then e.BrandName Else b.ResourceName End As ResourceName")),"UnitId"=>array(new Expression("b.UnitId")), array("UnitName")))
                            ->columns(array(new Expression("g.ResourceId,g.ItemId,sum(isnull(g.Quantity,0)) As Quantity,
                                 Case When g.ItemId>0 then e.ItemCode Else b.Code End As Code,Case When g.ItemId>0 Then e.BrandName Else b.ResourceName End As ResourceName,
                                 b.UnitId,d.UnitName ")))
							->join(array("b"=>"Proj_Resource"), "g.ResourceId=b.ResourceId", array(), $resSelect::JOIN_INNER)
							->join(array("d"=>"Proj_UOM"), 'b.UnitId=d.UnitId', array(), $resSelect:: JOIN_LEFT)
                            ->join(array("e"=>"MMS_Brand"),'g.ResourceId=e.ResourceId and g.ItemId=e.BrandId',array(),$resSelect::JOIN_LEFT)
							->group(new expression('g.ResourceId,b.Code,b.ResourceName,b.UnitId,d.UnitName,e.ItemCode,e.BrandName,g.ItemId,e.ItemCode,e.BrandName'));
						
					 $resStmt = $sql->getSqlStringForSqlObject($resSelect);
					$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($resResult as $rs){
						//Decision			
						/* $decSelect = $sql->select();		
						$decSelect->from(array('a'=>'VM_ReqDecQtyTrans'))
							->columns(array("TransId","DecisionId","ReqTransId","IndentQty"), array("RDecisionNo","DecDate"), array("ResourceId"))
							->join(array('a1'=>'VM_RequestDecision'), 'a.DecisionId=a1.DecisionId', array("RDecisionNo","DecDate"), $decSelect:: JOIN_INNER)
							->join(array('b'=>'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array("ResourceId"), $decSelect:: JOIN_INNER)			   
							->where(array('a.DecisionId'=>explode(',', $postParams['decId']),
							'b.ResourceId'=>array($rs['ResourceId']) )); */
							
						$selectDecs1 = $sql->select(); 
						$selectDecs1->from(array("a"=>"VM_ReqDecQtyTrans"))
							->columns(array("TransId","DecisionId","Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
							->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $selectDecs1::JOIN_INNER)
							->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
							'b.ResourceId'=>array($rs['ResourceId'])  ));
						
						$selectDecs2 = $sql->select(); 
						$selectDecs2->from(array("a"=>'VM_RFQTrans'))
							->columns(array("TransId"=>"DecisionTransId","DecisionId","Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
							->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
							'a.ResourceId'=>array($rs['ResourceId']) ));			
						$selectDecs2->combine($selectDecs1,'Union ALL');

						$decSelect = $sql->select(); 
						$decSelect->from(array("g"=>$selectDecs2))
						->columns(array("TransId","DecisionId","ResourceId","IndentQty"=>new Expression("sum(isnull(g.Quantity,0)) ")),array("RDecisionNo","DecDate"))
						->join(array("a1"=>"VM_RequestDecision"), "g.DecisionId=a1.DecisionId", array("RDecisionNo","DecDate"), $decSelect::JOIN_INNER)
						->group(new expression('g.TransId,g.DecisionId,g.ResourceId,a1.RDecisionNo,a1.DecDate'));							
							
						$decisionStmt = $sql->getSqlStringForSqlObject($decSelect);
						$rs['decision'] = $dbAdapter->query($decisionStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						array_push($resp, $rs);
					}
				}
                else if($mode == 'requestEntry'){

                    $select1 = $sql->select();
                    $select1->from(array("a"=>"VM_requestTrans"))
//                        ->columns(array("Quantity"=>new Expression("sum(isnull(a.IndentQty,0)) "),"ResourceId"))
                        ->columns(array(new Expression("Case When a.ItemId>0 Then d.ItemCode Else b.Code End As Code,
                           Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,sum(isnull(a.IndentQty,0)) As Quantity,
                           c.UnitName,c.UnitId,a.ResourceId,a.ItemId ")))
                        ->join(array("b"=>"proj_resource"), "a.ResourceId=b.ResourceId", array(), $select1::JOIN_INNER)
                        ->join(array("c"=>"Proj_UOM"), "b.UnitId=c.UnitId", array(), $select1:: JOIN_LEFT)
                        ->join(array("d"=>"MMS_Brand"),"a.ResourceId=d.ResourceId and a.ItemId=d.BrandId",array(),$select1::JOIN_INNER)
                        ->where(array('a.RequestId'=>explode(',',($postParams['reqId']))))
                        ->group(new expression('a.ResourceId,a.ItemId,b.Code,b.ResourceName,c.UnitId,c.UnitName,d.ItemCode,d.BrandName'));
                    $selStatement = $sql->getSqlStringForSqlObject($select1);
                    $result12 = $dbAdapter->query($selStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($result12 as $rd) {

                        $selectreq = $sql->select();
                        $selectreq->from(array("a" => 'VM_RequestTrans'))
                            ->columns(array("RequestTransId" => "RequestTransId",
                                "RequestId", "Quantity" => new Expression("a.Quantity"), "ResourceId"))
                            ->join(array("b" => "VM_RequestRegister"), "a.RequestId=b.RequestId", array("RequestNo"), $selectreq:: JOIN_INNER)
                            ->where(array('a.RequestId' => explode(',', ($postParams['reqId'])),
                                'a.ResourceId' => array($rd['ResourceId'])));

                        $selectreqStatement = $sql->getSqlStringForSqlObject($selectreq);
                        $rd['request'] = $dbAdapter->query($selectreqStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        array_push($resp, $rd);
                    }
                }
				else if($mode == 'voucherValid'){
					$select = $sql->select();		
					$select->from(array('a' => 'VM_RFQRegister'))
						->columns(array('RFQNo'))
						->where(array('a.RFQNo'=>trim(($postParams['voucherno']))));
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();						
				}
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		}
		else if($request->isPost()){
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
//                           echo"<pre>";
//                 print_r($postParams);
//                  echo"</pre>";
//                 die;
//                   return;
			//echo  json_encode($postParam);die;
			try {
				if($vNo['genType']){
					$voucher = CommonHelper::getVoucherNo(203,date('Y/m/d', strtotime(($postParams['decision_date']))) ,0,0, $dbAdapter,"I");
					$voucherNo = $voucher['voucherNo'];
				}
				else{
					$voucherNo = $postParams['VoucherNo'];
				}
				//RFQNo,RFQDate,RFQType,TechVerification,Submittal,BidafterVerification
				$registerInsert = $sql->insert('VM_RFQRegister');
				$registerInsert->values(array(
                    "RFQDate"=>date('Y-m-d'),
                    "FinalBidDate"=>date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['biddue_date'],'date'))),
					"RFQType"=>$this->bsf->isNullCheck($postParams['qtn_type'],'string'),
                    "RFQNo"=>$voucherNo,
                    "TechVerification"=>(($postParams['verify_yes'])?'1':'0'),
					"Submittal"=>(($postParams['submittal_yes'])?'1':'0'),
                    "SubmittalNarration"=>$this->bsf->isNullCheck($postParams['narration_submittal'],'string'),
					"BidafterVerification"=>(($postParams['web_finalization'])?'1':'0'),
                    "Narration"=>$this->bsf->isNullCheck($postParams['detailed_description'],'string'),
					"ContactName"=>$this->bsf->isNullCheck($postParams['contact_name'],'string'),
                    "ContactNo"=>$this->bsf->isNullCheck($postParams['contact_number'],'number'),
                    "Designation"=>$this->bsf->isNullCheck($postParams['contact_designation'],'string'),
					"ContactAddress"=>$this->bsf->isNullCheck($postParams['delivery_address1'].'|'.$postParams['delivery_address2'].'|'.$postParams['delivery_address3'],'string'),
					"BidInformation"=>$this->bsf->isNullCheck($postParams['bid_description'],'string'),
                    "QuotType"=>$sQuotType ));
			    $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$RFQRegId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				/*File upload*/
				$fileList = json_decode($postParams['fileList'], true);
				foreach($fileList as $files){
					$dir = 'public/uploads/doc_files/';
					$uploadDir = 'public/uploads/rfq/'.$RFQRegId.'/';
					if(!is_dir($uploadDir))
						mkdir($uploadDir, 0755, true);
					
					copy($dir.$files, $uploadDir.$files);
					unlink($dir.$files);
				}
                if($postParams['qtype'] == 'Q'){
                    $projectListId = $postParams['project_name'];
                    foreach($projectListId as $pid){
                        $projectInsert = $sql->insert('VM_RFQMultiCCTrans');
                        $projectInsert->values(array(
                            "RFQId"=>$RFQRegId,
                            "CostCentreId"=>$pid
                        ));
                        $projectStatement = $sql->getSqlStringForSqlObject($projectInsert);
                        $dbAdapter->query($projectStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }else{
                    $projectListId = $postParams['project_name'];
                    foreach($projectListId as $eid){
                        $projectInsert = $sql->insert('VM_RFQMultiCCTrans');
                        $projectInsert->values(array(
                            "RFQId"=>$RFQRegId,
                            "EnquiryId"=>$eid
                        ));
                        $projectStatement = $sql->getSqlStringForSqlObject($projectInsert);
                        $dbAdapter->query($projectStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
				//Technical Verification Doc
				$rowCountdoc = $postParams['RowCountdoc'];
				foreach(range(1,$rowCountdoc) as $count){
					if($postParams['Document_name_'.$count]!="" || $postParams['Description_doc_'.$count]!="" || $postParams['File_format_doc_'.$count]!="" )
					{					
						$insert = $sql->insert('VM_RFQTechVerificationTrans');
						$insert->values(array(
							'RFQId'  => $RFQRegId,
							'DocumentName'  =>$this->bsf->isNullCheck($postParams['Document_name_'.$count],	'string'),
							'Description' => $this->bsf->isNullCheck($postParams['Description_doc_'.$count],'string'),
							'DocumentFormat' => $this->bsf->isNullCheck($postParams['File_format_doc_'.$count],'string')
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}						
				}				
						
				//VendorPickList Selection
				$vendor_id = array_filter(explode(",", $postParams['vendorListId']));
				foreach($vendor_id as $vendorId){				
					//Multiple Vendor
					$requestMultiVendorInsert = $sql->insert('VM_RFQVendorTrans');
					$requestMultiVendorInsert->values(array("RFQId"=>$RFQRegId, "VendorId"=>$vendorId));							
					$requestMultiVendorStatement = $sql->getSqlStringForSqlObject($requestMultiVendorInsert);
					$dbAdapter->query($requestMultiVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//TermsPickList Selection
				$term_id = array_filter(explode(",", $postParams['termListId']));
				foreach($term_id as $termId){				
					//Multiple Vendor
					$requestMultiTermsInsert = $sql->insert('VM_RFQTerms');
					$requestMultiTermsInsert->values(array("RFQId"=>$RFQRegId, "TermsId"=>$termId));							
					$requestMultiTermsStatement = $sql->getSqlStringForSqlObject($requestMultiTermsInsert);
					$dbAdapter->query($requestMultiTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//Submittal Name
				$rowCountsubmittal =$postParams['RowCountsubmittal'];
				foreach(range(1,$rowCountsubmittal) as $count1){
					if($postParams['submittal_name_'.$count1]!="" )
					{					
						$insert = $sql->insert('VM_RFQSubmittalTrans');
						$insert->values(array(
							'RFQId'  => $RFQRegId,
							'SubmittalName' => $this->bsf->isNullCheck($postParams['submittal_name_'.$count1],'string')
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}						
				}
                // request and decision save
                if($postParams['qtype'] == 'Q'){
                    $json = json_decode($postParams['hidJson'], true);

                    foreach($json as $res){
                        foreach($res['decTrans'] as $decTrans){
                            //insert VM_RFQTrans-decision
                            $rfqDectransInsert = $sql->insert('VM_RFQTrans');
                            $rfqDectransInsert->values(array(
                                "RFQId"=>$RFQRegId,
                                "DecisionId"=>$this->bsf->isNullCheck($postParams['decisionId_'.$decTrans],'number'),
                                "DecisionTransId"=>$decTrans,
                                "ResourceId"=>$res['resourceId'],
                                "Quantity"=>$this->bsf->isNullCheck($postParams['decQuantity_'.$decTrans],'number')
                            ));
                            $rfqDectransStatement = $sql->getSqlStringForSqlObject($rfqDectransInsert);
                            $dbAdapter->query($rfqDectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $rfqtransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $updaterfqtrans = $sql->update();
                            $updaterfqtrans->table('VM_RFQTrans');
                            $updaterfqtrans->set(array("ItemId" => new Expression('(Select ISNULL(b.ItemId,0) As ItemId From VM_ReqDecQtyTrans A
                                  Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId='.$decTrans.')')));
                            $updaterfqtrans->where(array("RFQTransId"=>$rfqtransid,"DecisionTransId>0" ));
                             $updaterfqStatement = $sql->getSqlStringForSqlObject($updaterfqtrans);
                            $dbAdapter->query($updaterfqStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                }
                else{
                    $json = json_decode($postParams['hidJson'], true);
                    foreach($json as $req){
                        foreach($req['reqTrans'] as $reqTrans){
                            //insert VM_RFQTrans - request
                            $rfqreqTransInsert = $sql->insert('VM_RFQTrans');
                            $rfqreqTransInsert->values(array(
                                "RFQId"=>$RFQRegId,
                                "RequestId"=>$this->bsf->isNullCheck($postParams['requestId_'.$reqTrans],'number'),
                                "RequestTransId"=>$reqTrans,
                                "ResourceId"=>$req['resourceId'],
                                "Quantity"=>$this->bsf->isNullCheck($postParams['reqQuantity_'.$reqTrans],'number')
                            ));
                            $rfqreqTransInsertStatement = $sql->getSqlStringForSqlObject($rfqreqTransInsert);
                            $dbAdapter->query($rfqreqTransInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $rfqtransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $updaterfqtrans = $sql->update();
                            $updaterfqtrans->table('VM_RFQTrans');
                            $updaterfqtrans->set(array("ItemId" => new Expression('(Select ISNULL(ItemId,0) As ItemId From VM_RequestTrans
                                   Where RequestTransId='.$reqTrans.')')));
                            $updaterfqtrans->where(array("RFQTransId"=>$rfqtransid,"RequestTransId>0" ));
                            $updaterfqStatement = $sql->getSqlStringForSqlObject($updaterfqtrans);
                            $dbAdapter->query($updaterfqStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                }
				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'RFQ-Create','N','RFQ',$RFQRegId,0,0,'Vendor',$voucherNo,$this->auth->getIdentity()->UserId,0,0);
				$this->redirect()->toRoute('ats/rfq-detailed', array('controller' => 'rfq','action' => 'rfq-detailed','rfqid' => $RFQRegId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends
		}

        $select = $sql->select();
        $select->from('Proj_ResourceType')
             ->columns(array(new Expression("8 As TypeId,'TurnKey' As TypeName")));

        $select1 = $sql->select()
            ->columns(array(new Expression("7 As TypeId,'Service' As TypeName")));
        $select1->combine($select, 'Union ALL');

        $select2 = $sql->select()
            ->columns(array(new Expression("6 As TypeId,'Sub-IOW' As TypeName")));
        $select2->combine($select1, 'Union ALL');

        $select3 = $sql->select()
            ->columns(array(new Expression("5 As TypeId,'IOW' As TypeName")));
        $select3->combine($select2, 'Union ALL');

        $select4 = $sql->select()
            ->columns(array(new Expression("4 As TypeId,'Activity' As TypeName")));
        $select4->combine($select3, 'Union ALL');

        $select5 = $sql->select()
            ->columns(array(new Expression("3 As TypeId,'Asset' As TypeName")));
        $select5->combine($select4, 'Union ALL');

        $select6 = $sql->select()
            ->columns(array(new Expression("2 As TypeId,'Material' As TypeName")));
        $select6->combine($select5, 'Union ALL');

        $select7 = $sql->select()
            ->columns(array(new Expression("1 As TypeId ,'Labour' As TypeName")));
        $select7->combine($select6, 'Union ALL');


        $quotationType = $sql->select();
        $quotationType->from(array("a" => $select7))
            ->columns(array(new Expression("distinct(TypeId) As data,TypeName As value")))
            ->order("TypeId");
        $quotationTypeStatement = $sql->getSqlStringForSqlObject($quotationType);
        $quotationTypeResults = $dbAdapter->query($quotationTypeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$projSelect = $sql->select();
		$projSelect->from('WF_OperationalCostCentre')
				->columns(array("id"=>"CostCentreId", "name"=>"CostCentreName"));
		$projStatement = $sql->getSqlStringForSqlObject($projSelect);
		$proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $tProjSelect = $sql->select();
        $tProjSelect->from('Proj_TenderEnquiry')
            ->columns(array("id"=>"TenderEnquiryId", "name"=>"NameOfWork"));
        $tProjStatement = $sql->getSqlStringForSqlObject($tProjSelect);
        $tprojResults = $dbAdapter->query($tProjStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        /*Vendor picklis*/
		$select = $sql->select();
		$select->from(array("a"=>"Vendor_Master"))
			   ->columns(array('VendorId','VendorName'))					   				   
			   ->where(array('a.Approve'=>array('Y')))
			   ->order('a.VendorName');
		$statementVendor = $sql->getSqlStringForSqlObject($select);
		$vendorList = $dbAdapter->query($statementVendor, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
		
		$select = $sql->select(); 
		$select->from(array("a"=>"WF_TermsMaster"))
			->columns(array('TermsId', 'Title'))
			->where->like('a.TermType', 'S');
		$select->order("a.Title");
		$statementTerms = $sql->getSqlStringForSqlObject($select);
		$termList = $dbAdapter->query($statementTerms, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		

		$this->_view->genType = $vNo["genType"];
		
		if($vNo["genType"])
			$this->_view->voucherNo = $vNo["voucherNo"];
		else
			$this->_view->voucherNo = "";
			
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->quotationType = $quotationTypeResults;
		$this->_view->quotType = $sQuotType;
		$this->_view->projects = $proResults;
		$this->_view->tenderProjects = $tprojResults;
		$this->_view->vendorList = $vendorList;
		$this->_view->termList = $termList;
		
		return $this->_view;			
	}

	public function rfqeditAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
        //--update [michrm]..empattendance set TimeIn='2015-02-01 09:35:00.000',Late=0,AType='09:35',Permission=0 where employeeid=4 and ADate='2015-02-26 00:00:00.000'
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vNo = CommonHelper::getVoucherNo(203,date('Y/m/d') ,0,0, $dbAdapter,"");
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$resp =  array();
				$postParams = $request->getPost();
                $mode = $this->bsf->isNullCheck($postParams['mode'], 'string');
                if($mode == 'decisionEntry'){
					//start resource
					$select1 = $sql->select(); 
					$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
							->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId") )
							->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId","ItemId"), $select1::JOIN_INNER)
							->where(array('a.DecisionId'=>explode(',',($postParams['decId']))));
						 
					$select2 = $sql->select(); 
					$select2->from(array("a"=>'VM_RFQTrans'))
							->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId","ItemId"))
							->where(array('a.DecisionId'=>explode(',',($postParams['decId']))));
					$select2->where->and->expression('a.RFQId not like ?',($postParams['rfqId']));
					$select2->combine($select1,'Union ALL');
					
					$select3 = $sql->select(); 
					$select3->from(array("a"=>'VM_RFQTrans'))
							->columns(array("hidQty"=>new Expression("a.Quantity"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId","ItemId"))
							->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
									'a.RFQId'=>($postParams['rfqId'])));
					$select3->combine($select2,'Union ALL');
					
					$resSelect = $sql->select(); 
					$resSelect->from(array("g"=>$select3))
//							->columns(array("ResourceId","ItemId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) "),"hidQty"=>new Expression("sum(isnull(g.hidQty,0)) ")),array("Code","ResourceName","UnitId"), array("UnitName"))
                            ->columns(array(new Expression("g.ResourceId,g.ItemId,sum(isnull(g.Quantity,0)) As Quantity,
                               sum(isnull(g.hidQty,0)) As hidQty,Case when g.itemid>0 then e.ItemCode Else b.Code End As Code,
                               Case when g.itemid>0 then e.BrandName ELse b.ResourceName End As ResourceName,d.UnitId,d.UnitName ")))
							->join(array("b"=>"Proj_Resource"), "g.ResourceId=b.ResourceId", array(), $resSelect::JOIN_INNER)
							->join(array('d'=>'Proj_UOM'), 'b.UnitId=d.UnitId', array("UnitId","UnitName"), $resSelect::JOIN_LEFT)
                            ->join(array('e'=>'MMS_Brand'),'g.ResourceId=e.ResourceId and g.ItemId=e.ItemId',array(),$resSelect::JOIN_INNER)
							->group(new expression('g.ResourceId,g.ItemId,b.Code,b.ResourceName,b.UnitId,d.UnitName,e.ItemCode,e.BrandName'));
					$resStmt = $sql->getSqlStringForSqlObject($resSelect);
					$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();					
							
					foreach($resResult as $data){
						//Start Decision
						$selectDecs1 = $sql->select(); 
						$selectDecs1->from(array("a"=>"VM_ReqDecQtyTrans"))
									->columns(array("TransId","DecisionId","hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId","ItemId"))
									->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId","ItemId"), $selectDecs1::JOIN_INNER)
									->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
												'b.ResourceId'=>$data['ResourceId'],'b.ItemId'=>$data['ItemId']));
						
						$selectDecs2 = $sql->select(); 
						$selectDecs2->from(array("a"=>'VM_RFQTrans'))
									->columns(array("TransId"=>"DecisionTransId","DecisionId","hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId","ItemId"))
									->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
									'a.ResourceId'=>$data['ResourceId'],'a.ItemId'=>$data['ItemId']));
						$selectDecs2->where->and->expression('a.RFQId not like ?', $postParams['rfqId']);									
						$selectDecs2->combine($selectDecs1,'Union ALL');

						$selectDecs3 = $sql->select(); 
						$selectDecs3->from(array("a"=>'VM_RFQTrans'))
									->columns(array("TransId"=>"DecisionTransId","DecisionId","hidQty"=>new Expression("a.Quantity"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId","ItemId"))
									->where(array('a.DecisionId'=>explode(',',($postParams['decId'])),
												'a.ResourceId'=>$data['ResourceId'],'a.ItemId'=>$data['ItemId'],
												'a.RFQId'=>($postParams['rfqId'])));
						$selectDecs3->combine($selectDecs2,'Union ALL');
						
						$decSelect = $sql->select(); 
						$decSelect->from(array("g"=>$selectDecs3))
								->columns(array("TransId","DecisionId","ResourceId","ItemId","hidQty"=>new Expression("sum(isnull(g.hidQty,0)) "),"IndentQty"=>new Expression("sum(isnull(g.Quantity,0)) ")),array("RDecisionNo","DecDate"))
								->join(array("a1"=>"VM_RequestDecision"), "g.DecisionId=a1.DecisionId", array("RDecisionNo","DecDate"), $decSelect::JOIN_INNER)
								->group(new expression('g.TransId,g.DecisionId,g.ResourceId,g.ItemId,a1.RDecisionNo,a1.DecDate'));
						$decStmt = $sql->getSqlStringForSqlObject($decSelect);
						$data['decision'] = $dbAdapter->query($decStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						array_push($resp, $data);
					}
				}
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		} else if($request->isPost()) {
				//Write your Normal form post code here
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			try {								
				
				$RFQRegId= $postParams['rfqId'];

				//delete MultiCCRFQ
				$subQuery   = $sql->delete();
				$subQuery->from("VM_RFQMultiCCTrans")
						->where(array('RFQId'=>$RFQRegId));
				$DelMulticcRFQStatement = $sql->getSqlStringForSqlObject($subQuery);
				$register1 = $dbAdapter->query($DelMulticcRFQStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete VM_RFQTechVerificationTrans
				$selRFQTechVer   = $sql->delete();
				$selRFQTechVer->from("VM_RFQTechVerificationTrans")
						->where(array('RFQId'=>$RFQRegId));
				$DelRFQTechVerRFQStatement = $sql->getSqlStringForSqlObject($selRFQTechVer);
				$register2 = $dbAdapter->query($DelRFQTechVerRFQStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete VM_RFQVendorTrans
				$selRFQVendor   = $sql->delete();
				$selRFQVendor->from("VM_RFQVendorTrans")
						->where(array('RFQId'=>$RFQRegId));
				$DelRFQVendorStatement = $sql->getSqlStringForSqlObject($selRFQVendor);
				$register3 = $dbAdapter->query($DelRFQVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete VM_RFQTerms
				$selRFQTerms   = $sql->delete();
				$selRFQTerms->from("VM_RFQTerms")
						->where(array('RFQId'=>$RFQRegId));
				$DelRFQTermsStatement = $sql->getSqlStringForSqlObject($selRFQTerms);
				$register4 = $dbAdapter->query($DelRFQTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete VM_RFQSubmittalTrans
				$selRFQSubmittal   = $sql->delete();
				$selRFQSubmittal->from("VM_RFQSubmittalTrans")
						->where(array('RFQId'=>$RFQRegId));
				$DelRFQSubmittalStatement = $sql->getSqlStringForSqlObject($selRFQSubmittal);
				$register5 = $dbAdapter->query($DelRFQSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete VM_RFQTrans
				$selRFQTrans   = $sql->delete();
				$selRFQTrans->from("VM_RFQTrans")
						->where(array('RFQId'=>$RFQRegId));
				$DelRFQtraStatement = $sql->getSqlStringForSqlObject($selRFQTrans);
				$register6 = $dbAdapter->query($DelRFQtraStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $getRfqNo = $sql -> select();
                $getRfqNo->from("VM_RFQRegister")
                    ->columns(array("RFQNo"));
                $getRfqNo->where(array('RFQRegId'=>$RFQRegId));
                $rfStatement = $sql->getSqlStringForSqlObject($getRfqNo);
                $rfqdet = $dbAdapter->query($rfStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $rfqNo=$this->bsf->isNullCheck($rfqdet['RFQNo'],'string');

							
				//update VM_RFQRegister
				$registerUpdate = $sql->update();
				$registerUpdate->table('VM_RFQRegister');
				$registerUpdate->set(array(
					'RFQDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['decision_date'],'date'))),
					'RFQType' => $this->bsf->isNullCheck($postParams['qtn_type'],'string'),
					'FinalBidDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['biddue_date'],'date'))),
					//'RFQNo' => $postParams['VoucherNo'],
					'TechVerification' => (($postParams['verify_yes'])?'1':'0'),
					'Submittal' => (($postParams['submittal_yes'])?'1':'0'),
					'SubmittalNarration' => $this->bsf->isNullCheck($postParams['narration_submittal'],'string'),
					'BidafterVerification' => (($postParams['web_finalization'])?'1':'0'),
					'Narration' =>$this->bsf->isNullCheck($postParams['detailed_description'],'string'),
					'ContactName'=>$this->bsf->isNullCheck($postParams['contact_name'],'string'),
					'ContactNo'=>$this->bsf->isNullCheck($postParams['contact_number'],'number'),
					'Designation'=>$this->bsf->isNullCheck($postParams['contact_designation'],'string'),
					'ContactAddress'=>$this->bsf->isNullCheck($postParams['delivery_address1'].'|'.$postParams['delivery_address2'].'|'.$postParams['delivery_address3'],'string'),
					'BidInformation'=>$this->bsf->isNullCheck($postParams['bid_description'],'string')
				 ));
				$registerUpdate->where(array('RFQRegId'=>$RFQRegId));			
				$registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$projectListId = $postParams['project_name'];
				//print_r($projectListId);die;
				foreach($projectListId as $pid){
					$projectInsert = $sql->insert('VM_RFQMultiCCTrans');
					$projectInsert->values(array("RFQId"=>$RFQRegId, "CostCentreId"=>$pid));							
					$projectStatement = $sql->getSqlStringForSqlObject($projectInsert);
					$dbAdapter->query($projectStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//Technical Verification Doc
				$rowCountdoc = $postParams['RowCountdoc'];
				foreach(range(1,$rowCountdoc) as $count){
					if($postParams['Document_name_'.$count]!="" || $postParams['Description_doc_'.$count]!="" || $postParams['File_format_doc_'.$count]!="" )
					{					
						$insert = $sql->insert('VM_RFQTechVerificationTrans');
						$insert->values(array(
							'RFQId'  => $RFQRegId,
							'DocumentName'  => $this->bsf->isNullCheck($postParams['Document_name_'.$count],'string'),
							'Description' => $this->bsf->isNullCheck($postParams['Description_doc_'.$count],'string'),
							'DocumentFormat' => $this->bsf->isNullCheck($postParams['File_format_doc_'.$count],'string')
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}						
				}				
						
				//VendorPickList Selection
				$vendor_id = array_filter(explode(",", $postParams['vendorListId']));
				foreach($vendor_id as $vendorId){				
					//Multiple Vendor
					$requestMultiVendorInsert = $sql->insert('VM_RFQVendorTrans');
					$requestMultiVendorInsert->values(array("RFQId"=>$RFQRegId, "VendorId"=>$vendorId));							
					$requestMultiVendorStatement = $sql->getSqlStringForSqlObject($requestMultiVendorInsert);
					$dbAdapter->query($requestMultiVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//TermsPickList Selection
				$term_id = array_filter(explode(",", $postParams['termListId']));
				foreach($term_id as $termId){				
					//Multiple Vendor
					$requestMultiTermsInsert = $sql->insert('VM_RFQTerms');
					$requestMultiTermsInsert->values(array("RFQId"=>$RFQRegId, "TermsId"=>$termId));							
					$requestMultiTermsStatement = $sql->getSqlStringForSqlObject($requestMultiTermsInsert);
					$dbAdapter->query($requestMultiTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//Submittal Name
				$rowCountsubmittal =$postParams['RowCountsubmittal'];
				foreach(range(1,$rowCountsubmittal) as $count1){
					if($postParams['submittal_name_'.$count1]!=""){
						$insert = $sql->insert('VM_RFQSubmittalTrans');
						$insert->values(array(
							'RFQId'  => $RFQRegId,
							'SubmittalName'  => $this->bsf->isNullCheck($postParams['submittal_name_'.$count1],'string')
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}						
				}
				$json = json_decode($postParams['hidJson'], true);
				foreach($json as $res){
					foreach($res['decTrans'] as $decTrans){
						//insert VM_RFQTrans
						$rfqDectransInsert = $sql->insert('VM_RFQTrans');
						$rfqDectransInsert->values(array(
                            "RFQId"=>$RFQRegId,
                            "DecisionId"=>$this->bsf->isNullCheck($postParams['decisionId_'.$decTrans],'number'),
                            "DecisionTransId"=>$decTrans,
						    "ResourceId"=>$res['resourceId'],
                            "Quantity"=>$this->bsf->isNullCheck($postParams['decQuantity_'.$decTrans],'number')
                        ));
						$rfqDectransStatement = $sql->getSqlStringForSqlObject($rfqDectransInsert);
						$dbAdapter->query($rfqDectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $rfqtransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $updaterfqtrans = $sql->update();
                        $updaterfqtrans->table('VM_RFQTrans');
                        $updaterfqtrans->set(array("ItemId" => new Expression('(Select ISNULL(b.ItemId,0) As ItemId From VM_ReqDecQtyTrans A
                                  Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId='.$decTrans.')')));
                        $updaterfqtrans->where(array("RFQTransId"=>$rfqtransid,"DecisionTransId>0" ));
                        $updaterfqStatement = $sql->getSqlStringForSqlObject($updaterfqtrans);
                        $dbAdapter->query($updaterfqStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'RFQ-Modify','E','RFQ',$RFQRegId,0,0,'Vendor',$rfqNo,$this->auth->getIdentity()->UserId,0,0);
				$this->redirect()->toRoute('ats/rfq-detailed', array('controller' => 'rfq','action' => 'rfq-detailed','rfqid' => $RFQRegId));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}			
		}
		
		$rfqRegId = $this->params()->fromRoute('rfqid');
		
		$quotationType = $sql->select();
		$quotationType->from('Proj_ResourceType')
						->columns(array("data"=>"TypeId", "value"=>"TypeName"))
						->where(array('TypeId' => array('2', '3')));
		$quotationTypeStatement = $sql->getSqlStringForSqlObject($quotationType);
		$quotationTypeResults = $dbAdapter->query($quotationTypeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*$projSelect = $sql->select();
		$projSelect->from('WF_OperationalCostCentre')
				->columns(array("id"=>"CostCentreId", "name"=>"CostCentreName"));*/
				
		$projSelListSelect = $sql->select();
		$projSelListSelect->from(array("a"=>"WF_OperationalCostCentre"))
				->columns(array(new Expression("b.CostCentreId as id,a.CostCentreName as name, 1 sel")))
				->join(array('b'=>'VM_RFQMultiCCTrans'), 'a.CostCentreId=b.CostCentreId', array(), $projSelListSelect:: JOIN_INNER)
				->where(array('b.RFQId' => $rfqRegId ));

		$Subselect2 = $sql->select();
		$Subselect2->from("VM_RFQMultiCCTrans")
			 ->columns(array("CostCentreId"))
			 ->where(array('RFQId'=>$rfqRegId));
		 
		$select2 = $sql->select(); 
		$select2->from(array("a"=>'WF_OperationalCostCentre'))
			->columns(array(new Expression("a.CostCentreId as id,a.CostCentreName as name, 0 sel")))
			->where->notIn('a.CostCentreId',$Subselect2);																		
		$select2->combine($projSelListSelect,'Union ALL');
		$projStatement = $sql->getSqlStringForSqlObject($select2);
		$proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selProj = array_column(array_filter($proResults, function($arr){
						return ($arr['sel'] == 1);
					}), 'id');
		
		$projSelListSelect = $sql->select();
		$projSelListSelect->from(array("a"=>"WF_OperationalCostCentre"))
				->columns(array("id"=>"CostCentreId", "name"=>"CostCentreName"))
				->join(array('b'=>'VM_RFQMultiCCTrans'), 'a.CostCentreId=b.CostCentreId', array(), $projSelListSelect:: JOIN_INNER)
				->where(array('b.RFQId' => $rfqRegId ));
		$projSelStatement = $sql->getSqlStringForSqlObject($projSelListSelect);
		$projectsSelList = $dbAdapter->query($projSelStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
		$selectRfqReg = $sql->select(); 
		$selectRfqReg->from(array("a"=>"VM_RFQRegister"))
				->columns(array("RFQNo","RFQDate","RFQType","FinalBidDate","RFQType","Narration","TechVerification","Submittal","BidafterVerification","SubmittalNarration","ContactName","ContactNo","Designation","ContactAddress","BidInformation"),array("TypeName"))
				->join(array('b'=>'Proj_ResourceType'), 'a.RFQType=b.TypeId', array("TypeName"), $selectRfqReg:: JOIN_INNER)
				->where(array('a.RFQRegId' => $rfqRegId ));				
		$rfqregStatement = $sql->getSqlStringForSqlObject($selectRfqReg);
		$rfqResult = $dbAdapter->query($rfqregStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectTechVer = $sql->select(); 
		$selectTechVer->from(array("a"=>"VM_RFQTechVerificationTrans"))
			->where(array('RFQId' => $rfqRegId ));				
		$rfqTechVerStatement = $sql->getSqlStringForSqlObject($selectTechVer);
		$rfqTechVerResult = $dbAdapter->query($rfqTechVerStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectSubmittal = $sql->select(); 
		$selectSubmittal->from(array("a"=>"VM_RFQSubmittalTrans"))
			->where(array('RFQId' => $rfqRegId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
		$decisionSelect = $sql->select(); 
		$decisionSelect->from(array("a"=>"VM_RFQTrans"))
				->columns(array("DecisionId"))
				->where(array('a.RFQId' => $rfqRegId ));	
		$decisionStmt = $sql->getSqlStringForSqlObject($decisionSelect);
		$decisionresult = $dbAdapter->query($decisionStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$decId = array_unique(array_column($decisionresult, 'DecisionId'));
		$resp = array();
		//start resource
		$select1 = $sql->select(); 
		$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
				->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
				->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $select1::JOIN_INNER)
				->where(array('a.DecisionId'=>$decId));
			 
		$select2 = $sql->select(); 
		$select2->from(array("a"=>'VM_RFQTrans'))
				->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
				->where(array('a.DecisionId'=>$decId));							
		$select2->combine($select1,'Union ALL');
		
		$select3 = $sql->select(); 
		$select3->from(array("a"=>'VM_RFQTrans'))
				->columns(array("hidQty"=>new Expression("a.Quantity"),"Quantity"=>new Expression("1-1"),"ResourceId"))
				->where(array('a.DecisionId'=>$decId,
						'a.RFQId'=>$rfqRegId));							
		$select3->combine($select2,'Union ALL');
		
		$resSelect = $sql->select(); 
		$resSelect->from(array("g"=>$select3))
				->columns(array("ResourceId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) "),"hidQty"=>new Expression("sum(isnull(g.hidQty,0)) ")),array("Code","ResourceName","UnitId"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "g.ResourceId=b.ResourceId", array("Code","ResourceName","UnitId"), $resSelect::JOIN_INNER)
				->join(array('d'=>'Proj_UOM'), 'b.UnitId=d.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)	
				->group(new expression('g.ResourceId,b.Code,b.ResourceName,b.UnitId,d.UnitName'));
		$resStmt = $sql->getSqlStringForSqlObject($resSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();					
				
		foreach($resResult as $data){
			//Start Decision
			$selectDecs1 = $sql->select(); 
			$selectDecs1->from(array("a"=>"VM_ReqDecQtyTrans"))
						->columns(array("TransId","DecisionId","hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
						->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $selectDecs1::JOIN_INNER)
						->where(array('a.DecisionId'=>$decId,
									'b.ResourceId'=>$data['ResourceId']));
			
			$selectDecs2 = $sql->select(); 
			$selectDecs2->from(array("a"=>'VM_RFQTrans'))
						->columns(array("TransId"=>"DecisionTransId","DecisionId","hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
						->where(array('a.DecisionId'=>$decId,
						'a.ResourceId'=>$data['ResourceId']));			
			$selectDecs2->combine($selectDecs1,'Union ALL');

			$selectDecs3 = $sql->select(); 
			$selectDecs3->from(array("a"=>'VM_RFQTrans'))
						->columns(array("TransId"=>"DecisionTransId","DecisionId","hidQty"=>new Expression("a.Quantity"),"Quantity"=>new Expression("1-1"),"ResourceId"))
						->where(array('a.DecisionId'=>$decId,
									'a.ResourceId'=>$data['ResourceId'],
									'a.RFQId'=>$rfqRegId ));			
			$selectDecs3->combine($selectDecs2,'Union ALL');
			
			$decSelect = $sql->select(); 
			$decSelect->from(array("g"=>$selectDecs3))
					->columns(array("TransId","DecisionId","ResourceId","hidQty"=>new Expression("sum(isnull(g.hidQty,0)) "),"IndentQty"=>new Expression("sum(isnull(g.Quantity,0)) ")),array("RDecisionNo","DecDate"))
					->join(array("a1"=>"VM_RequestDecision"), "g.DecisionId=a1.DecisionId", array("RDecisionNo","DecDate"), $decSelect::JOIN_INNER)
					->group(new expression('g.TransId,g.DecisionId,g.ResourceId,a1.RDecisionNo,a1.DecDate'));	
			$decStmt = $sql->getSqlStringForSqlObject($decSelect);
			$data['decision'] = $dbAdapter->query($decStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			array_push($resp, $data);
		}
		
		/*Vendor picklist*/
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
			->columns(array("VendorId","Sel"=>new Expression("1")), array("VendorName"))
			->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
			->where(array('a.RFQId'=>$rfqRegId));
				
		$SubVendorselect2= $sql->select();
		$SubVendorselect2->from("VM_RFQVendorTrans")
			 ->columns(array("VendorId"))
			 ->where(array('RFQId'=>$rfqRegId));
		 
		$selectVendor2 = $sql->select(); 
		$selectVendor2->from(array("a"=>"Vendor_Master"))
			->columns(array('VendorId',"Sel"=>new Expression("1-1"),'VendorName'))				
			->where(array('a.Approve'=>'Y'))
			->where->notIn('a.VendorId',$SubVendorselect2);
		
		$selectVendor2->combine($selectVendor1,'Union ALL');
		$selectVendormaster = $sql->select(); 
		$selectVendormaster->from(array("g"=>$selectVendor2))
			->order('g.VendorName asc');
		$statementVendor = $sql->getSqlStringForSqlObject($selectVendormaster);
		$vendorList = $dbAdapter->query($statementVendor, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*Term picklist*/
		$selectTerms1 = $sql->select(); 
		$selectTerms1->from(array("a"=>"VM_RFQTerms"))
			->columns(array("TermsId","Sel"=>new Expression("1")), array("Title"))
			->join(array("b"=>new Expression("WF_TermsMaster")), "a.TermsId=b.TermsId", array("Title"), $selectTerms1::JOIN_INNER)
			->where(array('a.RFQId'=>$rfqRegId));
				
		$SubTermsselect2= $sql->select();
		$SubTermsselect2->from("VM_RFQTerms")
			 ->columns(array("TermsId"))
			 ->where(array('RFQId'=>$rfqRegId));
		 
		$selectTerms2 = $sql->select(); 
		$selectTerms2->from(array("a"=>"WF_TermsMaster"))
			->columns(array('TermsId',"Sel"=>new Expression("1-1"),'Title'))				
			->where(array('a.TermType'=>'S'))
			->where->notIn('a.TermsId',$SubTermsselect2);
		
		$selectTerms2->combine($selectTerms1,'Union ALL');
		$selectTermsmaster = $sql->select(); 
		$selectTermsmaster->from(array("h"=>$selectTerms2))
			->order('h.Title asc');
			
		$statementTerms = $sql->getSqlStringForSqlObject($selectTermsmaster);
		$termList = $dbAdapter->query($statementTerms, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*Decision picklist*/
		$selectDec1 = $sql->select(); 
		$selectDec1->from(array("a"=>"VM_RFQTrans"))
			->columns(array("DecisionId","Sel"=>new Expression("1"),"DecDate"=>new Expression("Convert(varchar(10),b.DecDate,105)")), array("RDecisionNo"))
			->join(array("b"=>new Expression("VM_RequestDecision")), "a.DecisionId=b.DecisionId", array("RDecisionNo"), $selectDec1::JOIN_INNER)
			->where(array('a.RFQId'=>$rfqRegId))
			->group(new expression('a.DecisionId,b.RDecisionNo,b.DecDate'));
				
		$SubDecselect2= $sql->select();
		$SubDecselect2->from("VM_RFQTrans")
			 ->columns(array("DecisionId"))
			 ->where(array('RFQId'=>$rfqRegId));
										   
		$selectDec2 = $sql->select(); 
		$selectDec2->from(array("a"=>"VM_RequestDecision"))
			->columns(array(new Expression("a.DecisionId,0 Sel,Convert(varchar(10),a.DecDate,105) as DecDate,a.RDecisionNo")))
			->join(array('b'=>'VM_ReqDecTrans'), 'a.DecisionId=b.DecisionId', array(), $selectDec2:: JOIN_INNER)
			->join(array('c'=>'VM_RequestRegister'), 'b.RequestId=c.RequestId', array(), $selectDec2:: JOIN_INNER)				   
			->where(array('c.CostCentreId'=>$selProj,
								'a.RequestType'=>$rfqResult[0]['RFQType']))			
			->where->notIn('a.DecisionId',$SubDecselect2);
			$selectDec2->group(new expression('a.DecisionId,a.RDecisionNo,a.DecDate'));
		
		$selectDec2->combine($selectDec1,'Union ALL');
		
		$selectDecsmaster = $sql->select(); 
		$selectDecsmaster->from(array("j"=>$selectDec2))
			->order('j.DecDate desc');
		$statementDecison = $sql->getSqlStringForSqlObject($selectDecsmaster);
		$decisionList = $dbAdapter->query($statementDecison, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
		
		//end decision entry
		$this->_view->genType = $vNo["genType"];	
		$this->_view->rfqRegId =$rfqRegId;
		$this->_view->rfqResult = $rfqResult;
		$this->_view->rfqTechVerResult = $rfqTechVerResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		$this->_view->quotationType = $quotationTypeResults;
		$this->_view->projectsSelList = $projectsSelList;
		$this->_view->projects = $proResults;
		$this->_view->decisionResult = json_encode($resp);
		$this->_view->vendorList = $vendorList;
		$this->_view->termList = $termList;
		$this->_view->decisionList = $decisionList;
		
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}

	public function rfqRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$resp = array();
				//Write your Ajax post code here
				/*$selectRFQ = $sql->select();
				$selectRFQ->from(array("a"=>"VM_RFQRegister"));
				$selectRFQ->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,COUNT(*) as totalsent,0 Received,0 Pending"),
								new Expression("CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectRFQ::JOIN_LEFT)
							->join(array("c"=>"VM_RFQVendorTrans"), "a.RFQRegId=c.RFQId", array(), $selectRFQ::JOIN_LEFT)
							->group(new expression('a.RFQRegId,a.RFQDate,a.RFQNo,b.TypeName,a.TechVerification,a.Approve'))
							->order("a.RFQDate Desc");
				*/							
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}			
		} else if($request->isPost()) {
				//Write your Normal form post code here				
		}
			
		$selectTotsentRFQ = $sql->select();
		$selectTotsentRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotsentRFQ->columns(array(new Expression("a.RFQRegId,COUNT(*) as totalsent,0 Received")))
					->join(array("c"=>"VM_RFQVendorTrans"), "a.RFQRegId=c.RFQId", array(), $selectTotsentRFQ::JOIN_LEFT)
					->where(array('a.DeleteFlag'=>0))
					->group(new expression('a.RFQRegId'));
					
		$selectTotRecRFQ = $sql->select();
		$selectTotRecRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotRecRFQ->columns(array(new Expression("a.RFQRegId,0 totalsent,COUNT(c.VendorId) as Received")))
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RFQRegId=c.RFQId", array(), $selectTotRecRFQ::JOIN_INNER)
					->where(array('a.DeleteFlag'=>0))
					->group(new expression('a.RFQRegId'));					
		$selectTotRecRFQ->combine($selectTotsentRFQ,'Union ALL');
			
		$selectRFQ = $sql->select();
		$selectRFQ->from(array("G"=>$selectTotRecRFQ))
				->columns(array(new Expression("G.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate, 
					CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification, 
					sum(G.totalsent) as totalsent,Sum(G.Received) as Received,Sum(G.totalsent-G.Received) as Pending, 
					CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,CASE WHEN a.QuotType='T' THEN 'Tender' Else 'Quotation' END as QuotType,b.TypeName  ") ))
				->join(array("a"=>"VM_RFQRegister"), "G.RFQRegId=a.RFQRegId", array(), $selectRFQ::JOIN_INNER)
				->join(array('b'=>'Proj_ResourceType'), 'a.RFQType=b.TypeId', array(), $selectRFQ:: JOIN_LEFT)
				->where(array('a.DeleteFlag'=>0))
				->group(new expression('G.RFQRegId,a.RFQDate,a.RFQNo,b.TypeName,a.TechVerification,a.Approve,a.QuotType'))
				->order("a.RFQDate Desc");
				
		$statement = $sql->getSqlStringForSqlObject($selectRFQ);		
		$regResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->regResult = $regResult;
		
		return $this->_view;
	}

	public function rfqDetailedAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$RfqId = $this->params()->fromRoute('rfqid');
		$request = $this->getRequest();
		$response = $this->getResponse();		
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
				
			}
		}
        $resultsVen = [];
		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array("RFQRegId","QuotType"))
			   ->where(array("RFQRegId"=>$RfqId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
       // print_r($resultsVen["QuotType"]); die;
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
        if($resultsVen['QuotType'] == 'Q'){
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a"=>"VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
								a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
                ->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
            $selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
        }else{
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a"=>"VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
								a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
								multiEN = STUFF((SELECT ', ' + b1.NameOfWork FROM VM_RFQMultiCCTrans t
								INNER JOIN Proj_TenderEnquiry b1 on t.EnquiryId=b1.TenderEnquiryId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
                ->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
            $selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
        }
        $statement = $sql->getSqlStringForSqlObject($selectCurRequest);
        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$selectMultiCC = $sql->select();
		$selectMultiCC->from(array("a"=>"VM_RFQMultiCCTrans"));
		$selectMultiCC->columns(array("CostCentreId"),array("CostCentreName"))
							->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array("CostCentreName"), $selectMultiCC::JOIN_INNER);
		$selectMultiCC->where(array("a.RFQId"=>$RfqId));
		$statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
		$resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectSubmittalDet = $sql->select();
		$selectSubmittalDet->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectSubmittalDet->columns(array("SubmittalName"))
							->where(array("a.RFQId"=>$RfqId));
		$statementSubmittalDet = $sql->getSqlStringForSqlObject($selectSubmittalDet); 
		$resultSubmittal = $dbAdapter->query($statementSubmittalDet, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectTechVer = $sql->select(); 
		$selectTechVer->from(array("a"=>"VM_RFQTechVerificationTrans"))
			->where(array('a.RFQId' => $RfqId ));				
		$rfqTechVerStatement = $sql->getSqlStringForSqlObject($selectTechVer);
		$rfqTechVerResult = $dbAdapter->query($rfqTechVerStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectTerms1 = $sql->select(); 
		$selectTerms1->from(array("a"=>"VM_RFQTerms"))
				->columns(array("TermsId"), array("Title"))
				->join(array("b"=>new Expression("WF_TermsMaster")), "a.TermsId=b.TermsId", array("Title"), $selectTerms1::JOIN_INNER)
				->where(array('a.RFQId'=>$RfqId));
		$rfqTermStatement = $sql->getSqlStringForSqlObject($selectTerms1);
		$rfqTermResult = $dbAdapter->query($rfqTermStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$RfqId));
		$rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if($resultsVen['QuotType'] == 'Q'){
            $selectDec1 = $sql->select();
            $selectDec1->from(array("a"=>"VM_RFQTrans"))
                ->columns(array("DecisionId","DecDate"=>new Expression("Convert(varchar(10),b.DecDate,105)")), array("RDecisionNo"))
                ->join(array("b"=>new Expression("VM_RequestDecision")), "a.DecisionId=b.DecisionId", array("RDecisionNo"), $selectDec1::JOIN_INNER)
                ->where(array('a.RFQId'=>$RfqId))
                ->group(new expression('a.DecisionId,b.RDecisionNo,b.DecDate'));
        }else{
            $selectDec1 = $sql->select();
            $selectDec1->from(array("a"=>"VM_RFQTrans"))
                ->columns(array("RequestId","RequestDate"=>new Expression("Convert(varchar(10),b.RequestDate,105)")), array("RequestNo"))
                ->join(array("b"=>new Expression("VM_RequestRegister")), "a.RequestId=b.RequestId", array("RequestNo"), $selectDec1::JOIN_INNER)
                ->where(array('a.RFQId'=>$RfqId))
                ->group(new expression('a.RequestId,b.RequestNo,b.RequestDate'));
        }
		$rfqDecStatement = $sql->getSqlStringForSqlObject($selectDec1);
		$rfqDecResult = $dbAdapter->query($rfqDecStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resSelect = $sql->select(); 
		$resSelect->from(array("a"=>"VM_RFQTrans"))
                ->columns(array(new Expression("a.ResourceId,a.ItemId,SUM(ISNULL(a.Quantity,0)) As Quantity,Case when a.ItemId>0 Then d.ItemCode Else b.Code End As Code,
                        Case when a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,c.UnitName  ")))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array(), $resSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resSelect:: JOIN_LEFT)
                ->join(array('d'=>'MMS_Brand'),'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId',array(),$resSelect::JOIN_LEFT)
				->where(array('a.RFQId'=>$RfqId))
				->group(new expression('a.ResourceId,a.ItemId,b.Code,b.ResourceName,c.UnitName,d.ItemCode,d.BrandName'));
		$resStmt = $sql->getSqlStringForSqlObject($resSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		//Bid Send for
		$iRFQtotalsent = 0;
		$selectTotsentRFQ = $sql->select();
		$selectTotsentRFQ->from(array("a"=>"VM_RFQVendorTrans"));
		$selectTotsentRFQ->columns(array(new Expression("COUNT(VendorId) as totalsent")))
					->where(array('a.RFQId'=>$RfqId));
		$resStmtTotSendRFQ = $sql->getSqlStringForSqlObject($selectTotsentRFQ);
		$resResultTotSendRFQ = $dbAdapter->query($resStmtTotSendRFQ, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultTotSendRFQ){
			$iRFQtotalsent = $resResultTotSendRFQ['totalsent'];
		}
		//Bid received from
		$iRFQReceived = 0;		
		$selectTotRecRFQ = $sql->select();
		$selectTotRecRFQ->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectTotRecRFQ->columns(array(new Expression("COUNT(VendorId) as Received")))
					->where(array('a.RFQId'=>$RfqId));
		$resStmtTotRecRFQ = $sql->getSqlStringForSqlObject($selectTotRecRFQ);
		$resResultTotRecRFQ = $dbAdapter->query($resStmtTotRecRFQ, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultTotRecRFQ){
			$iRFQReceived = $resResultTotRecRFQ['Received'];
		}

        $RfqUsed = $sql->select();
        $RfqUsed->from('VM_AnalysisRegister')
            ->columns(array('AnalysisRegId'))
            ->where("RFQId=$RfqId");
        $statement = $sql->getSqlStringForSqlObject($RfqUsed);
        $RfqUsedVal=$this->_view->RfqUsedVal  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->results = $results;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resultMultiCC = $resultMultiCC;
		$this->_view->resultSubmittal = $resultSubmittal;
		$this->_view->rfqTechVerResult = $rfqTechVerResult;
		$this->_view->rfqTermResult = $rfqTermResult;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqDecResult = $rfqDecResult;
		$this->_view->resResult = $resResult;
		$this->_view->RfqId = $RfqId;
		$this->_view->iRFQtotalsent = $iRFQtotalsent;
		$this->_view->iRFQReceived =$iRFQReceived;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);		
		return $this->_view;
	}
	public function uploadfileAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();		
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			$id = $this->params()->fromRoute('rfqId');
			if ($request->isPost()) {
				//Write your Ajax post code here
				$resp =  array();
				if($id == 0)
					$dir = 'public/uploads/doc_files/';
				else
					$dir = 'public/uploads/rfq/'.$id.'/';
				
				if($request->getPost('mode')){
					unlink($dir.$_POST['fname']);
				}
				else{
					$files = $request->getFiles();
					
					if(!is_dir($dir))
						mkdir($dir, 0755, true);
					
					$i = 1;
					$fname = $files['file']['name'];
					$parts = pathinfo($files['file']['name']);
					while(file_exists($dir.$fname)){
						$fname = $parts['filename'].' ('.$i.').'.$parts['extension'];
						$i++;
					}
					move_uploaded_file($files["file"]["tmp_name"], $dir.$fname);
						
					$resp['fname'] = $fname;
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;			
		} 
		else if ($request->isPost()){
			//Write your Normal form post code here
			
		}		
	}
	public function rfqresponseTrackAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		$rfqId = $this->params()->fromRoute('rfqid');
		if($this->getRequest()->isXmlHttpRequest()){
			if ($request->isPost()){
				//Write your Ajax post code here
				$resp =  array();
				
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;			
		}
		else if ($request->isPost()){
			//Write your Normal form post code here
			//begin trans try block example starts
			/*$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/
			//begin trans try block example ends
		}
		
		$stageSelect = $sql->select();
		$stageSelect->from(array("a"=>"VM_RFQRegister"))
					->columns(array("RFQRegId", "RFQNo", "RFQDate" => new Expression("CONVERT(VARCHAR(10), RFQDate, 105)"), "TechVerification", "Submittal"))
					->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQRegId=b.RFQId", array("VendorId"), $stageSelect::JOIN_INNER)
					->join(array("c"=>"Vendor_Master"), "b.VendorId=c.VendorId", array("VendorName", "LogoPath"), $stageSelect::JOIN_INNER)
					->join(array("d"=>"VM_TechInfoRegister"), "a.RFQRegId=d.RFQId and b.VendorId=d.VendorId", array("TechFound"=>"RegId", "techDate" => new Expression("CONVERT(VARCHAR(10), d.Entrydate, 105)"), "Valid", "ValidatedOn" => new Expression("CONVERT(VARCHAR(10), d.ValidatedOn, 105)")), $stageSelect::JOIN_LEFT)
					->join(array("e"=>"VM_RequestFormVendorRegister"), "a.RFQRegId=e.RFQId and b.VendorId=e.VendorId", array("BidFound"=>"RegId", "receivedBidDate" => new Expression("CONVERT(VARCHAR(10), e.Entrydate, 105)"), "Bidstatus", "FinalValid" =>"Valid", "FinalValidDate" => new Expression("CONVERT(VARCHAR(10), e.Validon, 105)"), "ResponseStatus"), $stageSelect::JOIN_LEFT)
					->where(array("a.RFQRegId"=>$rfqId));
					
		$stageStmt = $sql->getSqlStringForSqlObject($stageSelect);
		$stageResult = $dbAdapter->query($stageStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($stageResult) <= 0)
			$this->redirect()->toRoute("ats/default", array("controller" => "rfq","action" => "rfq-register"));
		
		//Bid Send for
		$iRFQtotalsent = 0;
		$selectTotsentRFQ = $sql->select();
		$selectTotsentRFQ->from(array("a"=>"VM_RFQVendorTrans"));
		$selectTotsentRFQ->columns(array(new Expression("COUNT(VendorId) as totalsent")))
					->where(array('a.RFQId'=>$rfqId));
		$resStmtTotSendRFQ = $sql->getSqlStringForSqlObject($selectTotsentRFQ);
		$resResultTotSendRFQ = $dbAdapter->query($resStmtTotSendRFQ, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultTotSendRFQ){
			$iRFQtotalsent = $resResultTotSendRFQ['totalsent'];
		}
		//Bid received from
		$iRFQReceived = 0;		
		$selectTotRecRFQ = $sql->select();
		$selectTotRecRFQ->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectTotRecRFQ->columns(array(new Expression("COUNT(VendorId) as Received")))
					->where(array('a.RFQId'=>$rfqId));
		$resStmtTotRecRFQ = $sql->getSqlStringForSqlObject($selectTotRecRFQ);
		$resResultTotRecRFQ = $dbAdapter->query($resStmtTotRecRFQ, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultTotRecRFQ){
			$iRFQReceived = $resResultTotRecRFQ['Received'];
		}
		
		//Valid
		$iRFQValid = 0;		
		$selectValid = $sql->select();
		$selectValid->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectValid->columns(array(new Expression("COUNT(Valid) as Valid")))
					->where(array('a.RFQId'=>$rfqId,'a.Valid'=>1));
		$resValid = $sql->getSqlStringForSqlObject($selectValid); 
		$resResultValid = $dbAdapter->query($resValid, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultValid){
			$iRFQValid = $resResultValid['Valid'];
		}
		
		//INValid
		$iRFQInValid = 0;		
		$selectInValid = $sql->select();
		$selectInValid->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectInValid->columns(array(new Expression("COUNT(Valid) as InValid")))
					->where(array('a.RFQId'=>$rfqId,'a.Valid'=>2));
		$resInValid = $sql->getSqlStringForSqlObject($selectInValid); 
		$resResultInValid = $dbAdapter->query($resInValid, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if($resResultInValid){
			$iRFQInValid = $resResultInValid['InValid'];
		}
		
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->rfqId = $rfqId;
		$this->_view->stageResult = $stageResult;
		$this->_view->iRFQtotalsent = $iRFQtotalsent;
		$this->_view->iRFQReceived = $iRFQReceived;
		$this->_view->iRFQValid = $iRFQValid;
		$this->_view->iRFQInValid = $iRFQInValid;
		return $this->_view;
	}

	public function validatingTechinfoAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else if ($request->isPost()) {
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			$rfqId = $this->bsf->isNullCheck($postParams['rfqId'], 'number'); 
			try {			
				$regId= $postParams['regId'];
				
				$acceptValid="R";
				if($postParams['acceptValid']==1)
				{ $acceptValid="A"; }
				else if($postParams['acceptValid']==2)
				{ $acceptValid="E"; }
				
				$isPortal=0;
				//update VM_TechInfoRegister
				$registerUpdate = $sql->update();
				$registerUpdate->table('VM_TechInfoRegister');
				$registerUpdate->set(array(					
					'Valid' => $acceptValid,
					'ValidatedOn' => date('Y-m-d'),
					'Statusdescription'=>$this->bsf->isNullCheck($postParams['statusdescription'],'string'),
					'Isportal'=>$isPortal
				 ));
				$registerUpdate->where(array('RegId'=>$regId));			
				$registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$techjson = json_decode($postParams['hidTechverification'], true);
				/*For admin --> public/uploads/vendor/rfq/technical/rfqId/vendorId/filename --*/
				foreach($techjson as $techVer){
									
					//update VM_RFVTechVerificationTrans
					$rfvTechVerUpdate = $sql->update();
					$rfvTechVerUpdate->table('VM_RFVTechVerificationTrans');
					$rfvTechVerUpdate->set(array(
						'Valid' => $this->bsf->isNullCheck($postParams['valid_'.$techVer],'number')
					 ));
					$rfvTechVerUpdate->where(array('TransId'=>$techVer,'RegId'=>$regId));	
						
					$rfvTechtransStatement = $sql->getSqlStringForSqlObject($rfvTechVerUpdate);
					$dbAdapter->query($rfvTechtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				/*
				Email Function regarding the status of techinal info whether its accepted or not
				Content = "status","Description about the status"
				*/
				$connection->commit();
				$this->redirect()->toRoute('ats/rfqresponse-track', array('controller' => 'rfq', 'action' => 'rfqresponse-track',"rfqid"=>$rfqId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends				
		}else{
			$regId = $this->params()->fromRoute('regid');
			$rfqRegId=0;
			$select = $sql->select();
			$select->from(array('a'=>'VM_TechInfoRegister'))
				   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'ValidFrom'=>new Expression("Convert(varchar(10),a.SubmittedOn,105)"),'Narration','Valid','Statusdescription'),array('RFQNo'), array("VendorName"))
				   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo"), $select::JOIN_INNER)
				   ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array("VendorName"), $select::JOIN_INNER)//Vendor_Contact for Email Field
				   ->where(array("a.RegId"=>$regId));
			$statementFound = $sql->getSqlStringForSqlObject($select);
			$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			if(!$resultsVen){
				$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
			}
			else { $rfqRegId=$resultsVen['RFQId'];}

			/*load Technical doc query*/
			$selectRFVTech = $sql->select(); 
			$selectRFVTech->from(array("a"=>"VM_RFVTechVerificationTrans"))
				->columns(array("TransId", "RFQTechTransId","TechDocPath","Valid"),array("DocumentName","Description","DocumentFormat"))
				->join(array("b"=>"VM_RFQTechVerificationTrans"), "a.RFQTechTransId=b.TransId", array("DocumentName","Description","DocumentFormat"), $selectRFVTech::JOIN_INNER)
				->where(array('a.RegId' => $regId ));				
			$rfqTechStatement = $sql->getSqlStringForSqlObject($selectRFVTech);
			$rfqTechResult = $dbAdapter->query($rfqTechStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			$this->_view->regId = $regId;
			$this->_view->resultsVen = $resultsVen;
			$this->_view->rfqTechResult = $rfqTechResult;
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
			return $this->_view;
		}
	}

	public function requestAnalysisAction(){
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
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$RfqId = $this->params()->fromRoute('rfqid');
		$RegId = 0;
		$request = $this->getRequest();
		$response = $this->getResponse();		
		$hidid = '';
        $vNo = CommonHelper::getVoucherNo(205,date('Y/m/d') ,0,0, $dbAdapter,"");

		if($this->getRequest()->isXmlHttpRequest())
        {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else if ($request->isPost())
        {
			$postParams = $request->getPost();
			$rfqId = $RfqId;
			$AnalRegId = $this->bsf->isNullCheck($postParams['regId'],'number');
            $aVNo=$this->bsf->isNullCheck($postParams['voucherNo'],'string');
            if ($this->bsf->isNullCheck($AnalRegId, 'number') > 0) {
                $Approve="E";
                $Role="Vendor-Analysis-Modify";
            }else{
                $Approve="N";
                $Role="Vendor-Analysis-Create";
            }
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				if($AnalRegId==0) {
                    if($vNo['genType']){
                        $vNo = CommonHelper::getVoucherNo(205,date('Y/m/d') ,0,0, $dbAdapter,"I");
                        $aVNo = $vNo['voucherNo'];
                    } else {
                        $aVNo = $this->bsf->isNullCheck($postParams['voucherNo'],'string');
                    }

					$registerInsert = $sql->insert('VM_AnalysisRegister');
					$registerInsert->values(array("AnalysisRegDate"=>date('Y-m-d'),"AnalysisRegNo"=>$aVNo, "RFQId"=>$rfqId ));
					$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
					$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$AnalRegId = $dbAdapter->getDriver()->getLastGeneratedValue();
					$status=0;
					$vendorId=0;
					$ResourceId=0;
					$rate=0;
					$resource = json_decode($postParams['selectedResource'], true);
					foreach($resource as $rid){
						$status=$rid['status'];
						$vendorId=$rid['vendor'];
						$ResourceId=$rid['resource'];
						$rate=$rid['rate'];

						$selectRFVStatus = $sql->select();
						$selectRFVStatus->from(array("a"=>"VM_RFVTrans"));
						$selectRFVStatus->columns(array("TransId"))
									->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectRFVStatus::JOIN_INNER)				
									->where(array("b.RFQId"=>$RfqId, "b.VendorId"=>$vendorId, "a.ResourceId"=>$ResourceId ));
						$statementRFVStatus = $sql->getSqlStringForSqlObject($selectRFVStatus); 
						$rsRFVStatus = $dbAdapter->query($statementRFVStatus, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($rsRFVStatus as $rsRFVRest) {
							$transId =  $rsRFVRest['TransId'];
							
							$RFVTransUpdate = $sql->update();
							$RFVTransUpdate->table('VM_RFVTrans');
							$RFVTransUpdate->set(array(
								'Status' => $status 
							 ));
							$RFVTransUpdate->where(array('TransId'=>$transId));
							$RFVTransupdateStatement = $sql->getSqlStringForSqlObject($RFVTransUpdate);
							$dbAdapter->query($RFVTransupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					
						if($status==1)
						{
							$analysisTransInsert = $sql->insert('VM_AnalysisVendorTrans');
							$analysisTransInsert->values(array("RegId"=>$AnalRegId, "ResourceId"=>$ResourceId, "Rate"=>$rate,"VendorId"=>$vendorId));							
							$analysisTransStatement = $sql->getSqlStringForSqlObject($analysisTransInsert);
							$dbAdapter->query($analysisTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					/*Terms Flag Insert*/
					$termList = json_decode($postParams['selectedTerms'], true);
					foreach($termList as $rstermList){
						$analysisTermDetInsert = $sql->insert('VM_AnalysisTermsDet');
						$analysisTermDetInsert->values(array("RegId"=>$AnalRegId, "VendorId"=>$rstermList['vendor'], "Term"=>$rstermList['termStatus'], "Submittal"=>$rstermList['subStatus']));
						$analysisTermDetStatement = $sql->getSqlStringForSqlObject($analysisTermDetInsert);
						$dbAdapter->query($analysisTermDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				} else {
					//Edit
					//delete VM_AnalysisVendorTrans
					$select = $sql->delete();
					$select->from("VM_AnalysisVendorTrans")
								->where(array('RegId'=>$AnalRegId));						
					$DelAnalysisVendorTransStatement = $sql->getSqlStringForSqlObject($select);
					$registerAnalysisVendorTrans = $dbAdapter->query($DelAnalysisVendorTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
					//delete VM_AnalysisTermsDet
					$select = $sql->delete();
					$select->from("VM_AnalysisTermsDet")
								->where(array('RegId'=>$AnalRegId));						
					$DelAnalysisTermsDetStatement = $sql->getSqlStringForSqlObject($select);
					$registerAnalysisTerms = $dbAdapter->query($DelAnalysisTermsDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					//Update VM_AnalysisRegister
					$registerUpdate = $sql->update();
					$registerUpdate->table('VM_AnalysisRegister');
					$registerUpdate->set(array(
						'AnalysisRegDate' => date('Y-m-d'),
                        'AnalysisRegNo' => $aVNo,
						'RFQId'=>$rfqId
					 ));
					$registerUpdate->where(array('AnalysisRegId'=>$AnalRegId));
					$registerupdateStatement = $sql->getSqlStringForSqlObject($registerUpdate);
					$dbAdapter->query($registerupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$status=0;
					$vendorId=0;
					$ResourceId=0;
					$rate=0;
					$resource = json_decode($postParams['selectedResource'], true);
					foreach($resource as $rid){
						$status=$rid['status'];
						$vendorId=$rid['vendor'];
						$ResourceId=$rid['resource'];
						$rate=$rid['rate'];

						$selectRFVStatus = $sql->select();
						$selectRFVStatus->from(array("a"=>"VM_RFVTrans"));
						$selectRFVStatus->columns(array("TransId"))
									->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectRFVStatus::JOIN_INNER)				
									->where(array("b.RFQId"=>$RfqId, "b.VendorId"=>$vendorId, "a.ResourceId"=>$ResourceId ));
						$statementRFVStatus = $sql->getSqlStringForSqlObject($selectRFVStatus); 
						$rsRFVStatus = $dbAdapter->query($statementRFVStatus, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($rsRFVStatus as $rsRFVRest) {
							$transId =  $rsRFVRest['TransId'];
							
							$RFVTransUpdate = $sql->update();
							$RFVTransUpdate->table('VM_RFVTrans');
							$RFVTransUpdate->set(array(
								'Status' => $status 
							 ));
							$RFVTransUpdate->where(array('TransId'=>$transId));
							$RFVTransupdateStatement = $sql->getSqlStringForSqlObject($RFVTransUpdate);
							$dbAdapter->query($RFVTransupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					
						if($status==1)
						{
							$analysisTransInsert = $sql->insert('VM_AnalysisVendorTrans');
							$analysisTransInsert->values(array("RegId"=>$AnalRegId, "ResourceId"=>$ResourceId, "Rate"=>$rate,"VendorId"=>$vendorId));							
							$analysisTransStatement = $sql->getSqlStringForSqlObject($analysisTransInsert);
							$dbAdapter->query($analysisTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					/*Terms Flag Insert*/
					$termList = json_decode($postParams['selectedTerms'], true);
					foreach($termList as $rstermList){
						$analysisTermDetInsert = $sql->insert('VM_AnalysisTermsDet');
						$analysisTermDetInsert->values(array("RegId"=>$AnalRegId, "VendorId"=>$rstermList['vendor'], "Term"=>$rstermList['termStatus'], "Submittal"=>$rstermList['subStatus']));
						$analysisTermDetStatement = $sql->getSqlStringForSqlObject($analysisTermDetInsert);
						$dbAdapter->query($analysisTermDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
				$connection->commit();
				$this->redirect()->toRoute('ats/default', array('controller' => 'rfq', 'action' => 'analysis-register'));
                CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Vendor-Analysis',$AnalRegId,0,0,'Vendor',$aVNo,$this->auth->getIdentity()->UserId,0,0);
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends		
		}
		
		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId','QuotType'))
			   ->where(array("RFQRegId"=>$RfqId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $qtype = $resultsVen['QuotType'];
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
		//Avoid Duplicate ENtry
		$select = $sql->select();
		$select->from('VM_AnalysisRegister')
			   ->columns(array('AnalysisRegId'))
			   ->where(array("RFQId"=>$RfqId));
		$statementFoundEntry = $sql->getSqlStringForSqlObject($select);
		$resultsVen1 = $dbAdapter->query($statementFoundEntry, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen1)==0){
		} else {
			$RegId=$resultsVen1[0]['AnalysisRegId'];
			//$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'analysis-register'));
		}
		if($qtype == 'Q'){
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a"=>"VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
                ->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
            $selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
        }else{
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a"=>"VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,
								multiEN = STUFF((SELECT ', ' + b1.NameOfWork FROM VM_RFQMultiCCTrans t
								INNER JOIN Proj_TenderEnquiry b1 on t.EnquiryId=b1.TenderEnquiryId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
                ->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
            $selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
        }
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();			
		 
		$selectRFQVendorList = $sql->select();
		$selectRFQVendorList->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFQVendorList->columns(array(new Expression("distinct a.VendorId as VendorId ,b.VendorName as VendorName")))
					->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array(), $selectRFQVendorList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId, "a.Valid"=>"1"));
		$selectRFQVendorList->order("b.VendorName");
		$statementRFQVendorList = $sql->getSqlStringForSqlObject($selectRFQVendorList); 
		$rsRFQVendorList = $dbAdapter->query($statementRFQVendorList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		//Terms
		$selectAnalysisTermtrans = $sql->select();
		$selectAnalysisTermtrans->from(array("a"=>"VM_AnalysisTermsDet"));
		$selectAnalysisTermtrans->columns(array("VendorId","Term"))				
					->where(array("a.RegId"=>$RegId));
		$statementAnalysisTermtrans = $sql->getSqlStringForSqlObject($selectAnalysisTermtrans);
		$rsAnalysisTermtrans = $dbAdapter->query($statementAnalysisTermtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->rsAnalysisTermtrans = $rsAnalysisTermtrans;
		//Submittals
		$selectAnalysisSubmitaltrans = $sql->select();
		$selectAnalysisSubmitaltrans->from(array("a"=>"VM_AnalysisTermsDet"));
		$selectAnalysisSubmitaltrans->columns(array("VendorId","Submittal"))				
					->where(array("a.RegId"=>$RegId));
		$statementAnalysisSubmitaltrans = $sql->getSqlStringForSqlObject($selectAnalysisSubmitaltrans);
		$rsAnalysisSubmitaltrans = $dbAdapter->query($statementAnalysisSubmitaltrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->rsAnalysisSubmitaltrans = $rsAnalysisSubmitaltrans;
		//
		
		$selectRFQResList = $sql->select();
		$selectRFQResList->from(array("a"=>"VM_RFQTrans"));
		$selectRFQResList->columns(array("ResourceId","Quantity"), array("Code","ResourceName"), array("UnitName"))
					->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $selectRFQResList::JOIN_INNER)
					->join(array("c"=>"Proj_UOM"), "b.UnitId=c.UnitId", array("UnitName"), $selectRFQResList::JOIN_LEFT)
					->where(array("a.RFQId"=>$RfqId))
					->order("b.ResourceName");
		$statementRFQResList = $sql->getSqlStringForSqlObject($selectRFQResList); 
		$rsRFQResList = $dbAdapter->query($statementRFQResList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$selectRFQRestrans = $sql->select();
		$selectRFQRestrans->from(array("a"=>"VM_RFVTrans"));
		$selectRFQRestrans->columns(array("ResourceId","Quantity","Rate","Amount", "Status"), array("Code","ResourceName"), array("VendorId"))
					->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $selectRFQRestrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegId=c.RegId", array("VendorId"), $selectRFQRestrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$RfqId));
		$statementRFQRestrans = $sql->getSqlStringForSqlObject($selectRFQRestrans);
		$rsRFQRestrans = $dbAdapter->query($statementRFQRestrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resourceTrans = array();
		foreach($rsRFQRestrans as $rsRFQRestDet) {
			$resourceId =  $rsRFQRestDet['ResourceId'];
			$vendorId =  $rsRFQRestDet['VendorId'];
			$resourceTrans[$resourceId][$vendorId] = array('rate'=>$rsRFQRestDet['Rate'], 'status'=>$rsRFQRestDet['Status']);
		}
		$this->_view->resourceTrans = $resourceTrans;
		
		//Resource trans Amount
		$selectRFQTotamount = $sql->select();
		$selectRFQTotamount->from(array("a"=>"VM_RFVTrans"));
		$selectRFQTotamount->columns(array(new Expression("b.VendorId,Sum(isnull(CAST(a.Amount As Decimal(18,3)),0)) as Amount")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectRFQTotamount::JOIN_INNER)					
					->where(array("b.RFQId"=>$RfqId))
					->group(new expression('b.VendorId'));
		$statementRFQTotamount = $sql->getSqlStringForSqlObject($selectRFQTotamount);
		$rsRFQTotamount = $dbAdapter->query($statementRFQTotamount, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$vendorTotamt = array();
		foreach($rsRFQTotamount as $rsRFQTotamt) {
			$vendorId =  $rsRFQTotamt['VendorId'];
			$vendorTotamt[$vendorId] = $rsRFQTotamt['Amount'];
		}
		$this->_view->vendorTotamt = $vendorTotamt;
		
		
		$selectRFVFound = $sql->select();
		$selectRFVFound->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFVFound->columns(array("RegId","RFQId","VendorId"))				
					->where(array("a.RFQId"=>$RfqId));
		$statementRFVFound = $sql->getSqlStringForSqlObject($selectRFVFound);
		$rsRFVFound = $dbAdapter->query($statementRFVFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$vendorRFVFound = array();
		foreach($rsRFVFound as $rsRfvFound) {
			$vendorId =  $rsRfvFound['VendorId'];
			$vendorRFVFound[$vendorId] = $rsRfvFound['RegId'];
		}

        $selectAnalVNo=$sql->select();
        $selectAnalVNo->from(array("a"=>"VM_AnalysisRegister"));
        $selectAnalVNo->columns(array("AnalysisRegNo"))
                      ->where(array("a.RFQId"=>$RfqId));
        $statementAnalVNo = $sql->getSqlStringForSqlObject($selectAnalVNo);
        $sAnalVNo = $dbAdapter->query($statementAnalVNo, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->sAnalVNo = $sAnalVNo;


		$this->_view->vendorRFVFound = $vendorRFVFound;
		$this->_view->qtype = $qtype;
		$this->_view->results = $results;
		$this->_view->rsRFQVendorList = $rsRFQVendorList;
		$this->_view->rsRFQRestrans = $rsRFQRestrans;
		$this->_view->rsRFQResList = $rsRFQResList;		
		$this->_view->RfqId = $RfqId;
		$this->_view->hidid = $hidid;
		$this->_view->RegId = $RegId;


		//termanalysis
		/*$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId'))
			   ->where(array("RFQRegId"=>$RfqId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
						
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RFQRegister"));
		$selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
		$selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();			
		
		$selectRFQVendorList = $sql->select();
		$selectRFQVendorList->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFQVendorList->columns(array(new Expression("distinct a.VendorId as VendorId ,b.VendorName as VendorName")))
					->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array(), $selectRFQVendorList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId, "a.Valid"=>"1"));
		$selectRFQVendorList->order("b.VendorName");
		$statementRFQVendorList = $sql->getSqlStringForSqlObject($selectRFQVendorList); 
		$rsRFQVendorList = $dbAdapter->query($statementRFQVendorList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		*/
		
		$selectRFQTermList = $sql->select();
		$selectRFQTermList->from(array("a"=>"VM_RFQTerms"));
		$selectRFQTermList->columns(array("TermsId"), array("Title"))
					->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("Title"), $selectRFQTermList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId))
					->order("b.Title");
		$statementRFQTermList = $sql->getSqlStringForSqlObject($selectRFQTermList); 
		$rsRFQTermList = $dbAdapter->query($statementRFQTermList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectRFQTermtrans = $sql->select();
		$selectRFQTermtrans->from(array("a"=>"VM_RFVTerms"));
		$selectRFQTermtrans->columns(array("TermsId","ValueFromNet","Per","Value","Period"), array("SlNo","Title"), array("VendorId"))
					->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("SlNo","Title"), $selectRFQTermtrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegisterId=c.RegId", array("VendorId"), $selectRFQTermtrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$RfqId));
		$statementRFQTermtrans = $sql->getSqlStringForSqlObject($selectRFQTermtrans); 
		$rsRFQTermtrans = $dbAdapter->query($statementRFQTermtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		
		$termPercentage = array();
		foreach($rsRFQTermtrans as $rsRFQTermPercent) {
			$termId =  $rsRFQTermPercent['TermsId'];
			$vendorId =  $rsRFQTermPercent['VendorId'];
			$termPercentage[$termId][$vendorId] = $rsRFQTermPercent['Per'];
		}
		$this->_view->termPercentage = $termPercentage;
		
		//Terms amount
		$selectRFQExtraamount = $sql->select();
		$selectRFQExtraamount->from(array("a"=>"VM_RFVTerms"));
		$selectRFQExtraamount->columns(array(new Expression("b.VendorId,Sum(isnull(CAST(a.Value As Decimal(18,3)),0)) as Value")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegisterId=b.RegId", array(), $selectRFQExtraamount::JOIN_INNER)					
					->where(array("b.RFQId"=>$RfqId))
					->group(new expression('b.VendorId'));
		$statementRFQExtraamount = $sql->getSqlStringForSqlObject($selectRFQExtraamount);
		$rsRFQExtraamount = $dbAdapter->query($statementRFQExtraamount, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$vendorExtamt = array();
		foreach($rsRFQExtraamount as $rsRFQExtamt) {
			$vendorId =  $rsRFQExtamt['VendorId'];
			$vendorExtamt[$vendorId] = $rsRFQExtamt['Value'];
		}
		$this->_view->vendorExtamt = $vendorExtamt;
		//Net Amount
		$selectRFQResTransamount = $sql->select(); 
		$selectRFQResTransamount->from(array("a"=>"VM_RFVTrans"));
		$selectRFQResTransamount->columns(array(new Expression("b.VendorId,a.Amount as Amount")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectRFQResTransamount::JOIN_INNER)					
					->where(array("b.RFQId"=>$RfqId));
		
		$selectRFQTermTransamount = $sql->select(); 
		$selectRFQTermTransamount->from(array("a"=>"VM_RFVTerms"));
		$selectRFQTermTransamount->columns(array(new Expression("b.VendorId,a.Value as Amount")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegisterId=b.RegId", array(), $selectRFQTermTransamount::JOIN_INNER)					
					->where(array("b.RFQId"=>$RfqId));							
		$selectRFQTermTransamount->combine($selectRFQResTransamount,'Union ALL');
			
		$selectNetAmount = $sql->select(); 
		$selectNetAmount->from(array("g"=>$selectRFQTermTransamount))
					->columns(array("VendorId","Amount"=>new Expression("sum(isnull(CAST(g.Amount As Decimal(18,3)),0)) ")))	
					->group(new expression('g.VendorId'));
		$statementRFQNetamount = $sql->getSqlStringForSqlObject($selectNetAmount);
		$rsRFQNetamount = $dbAdapter->query($statementRFQNetamount, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
		$vendorNetamt = array();
		foreach($rsRFQNetamount as $rsRFQNetamt) {
			$vendorId =  $rsRFQNetamt['VendorId'];
			$vendorNetamt[$vendorId] = $rsRFQNetamt['Amount'];
		}
		$this->_view->vendorNetamt = $vendorNetamt;		
		//
		
		$selectRFQSubList = $sql->select();
		$selectRFQSubList->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectRFQSubList->columns(array("TransId","SubmittalName"))									
					->where(array("a.RFQId"=>$RfqId))
					->order("a.SubmittalName");
		$statementRFQSubList = $sql->getSqlStringForSqlObject($selectRFQSubList); 
		$rsRFQSubList = $dbAdapter->query($statementRFQSubList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$selectRFQSubTrans = $sql->select();
		$selectRFQSubTrans->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectRFQSubTrans->columns(array("TransId","SubmittalName"), array("VendorId"))
					->join(array("b"=>"VM_RFVSubmittalTrans"), "a.TransId=b.RFQSubmittalTransId", array("VendorId", "SubmittalDocPath"), $selectRFQSubTrans::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId))
					->order("a.SubmittalName");
		$statementRFQSubTrans = $sql->getSqlStringForSqlObject($selectRFQSubTrans); 
		$rsRFQSubTrans = $dbAdapter->query($statementRFQSubTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$submittalFound = array();
		foreach($rsRFQSubTrans as $rsRFQSubTransFound) {
			$transId =  $rsRFQSubTransFound['TransId'];
			$vendorId =  $rsRFQSubTransFound['VendorId'];
			if($rsRFQSubTransFound['SubmittalDocPath'] != ''){
				$submittalFound[$transId][$vendorId] = 1;
			}
		}
		$this->_view->submittalFound = $submittalFound;
		$this->_view->rsRFQTermtrans = $rsRFQTermtrans;
		$this->_view->rsRFQTermList = $rsRFQTermList;
		$this->_view->rsRFQSubList = $rsRFQSubList;
        $this->_view->vNo = $vNo;
		
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
		
	}

	public function requestTermanalysisAction(){
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
		$RfqId = $this->params()->fromRoute('rfqid');
		$request = $this->getRequest();
		$response = $this->getResponse();
		
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
				
			}
			
			//begin trans try block example starts
			/*$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/
			//begin trans try block example ends			
		}
		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId'))
			   ->where(array("RFQRegId"=>$RfqId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
						
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RFQRegister"));
		$selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
		$selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();			
		
		$selectRFQVendorList = $sql->select();
		$selectRFQVendorList->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFQVendorList->columns(array(new Expression("distinct a.VendorId as VendorId ,b.VendorName as VendorName")))
					->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array(), $selectRFQVendorList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId, "a.Valid"=>"1"));
		$selectRFQVendorList->order("b.VendorName");
		$statementRFQVendorList = $sql->getSqlStringForSqlObject($selectRFQVendorList); 
		$rsRFQVendorList = $dbAdapter->query($statementRFQVendorList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
		$selectRFQTermList = $sql->select();
		$selectRFQTermList->from(array("a"=>"VM_RFQTerms"));
		$selectRFQTermList->columns(array("TermsId"), array("Title"))
					->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("Title"), $selectRFQTermList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId))
					->order("b.Title");
		$statementRFQTermList = $sql->getSqlStringForSqlObject($selectRFQTermList); 
		$rsRFQTermList = $dbAdapter->query($statementRFQTermList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectRFQTermtrans = $sql->select();
		$selectRFQTermtrans->from(array("a"=>"VM_RFVTerms"));
		$selectRFQTermtrans->columns(array("TermsId","ValueFromNet","Per","Value","Period"), array("SlNo","Title"), array("VendorId"))
					->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("SlNo","Title"), $selectRFQTermtrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegisterId=c.RegId", array("VendorId"), $selectRFQTermtrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$RfqId));
		$statementRFQTermtrans = $sql->getSqlStringForSqlObject($selectRFQTermtrans); 
		$rsRFQTermtrans = $dbAdapter->query($statementRFQTermtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		
		$termPercentage = array();
		foreach($rsRFQTermtrans as $rsRFQTermPercent) {
			$termId =  $rsRFQTermPercent['TermsId'];
			$vendorId =  $rsRFQTermPercent['VendorId'];
			$termPercentage[$termId][$vendorId] = $rsRFQTermPercent['Per'];
		}
		$this->_view->termPercentage = $termPercentage;
		
		
		$selectRFQSubList = $sql->select();
		$selectRFQSubList->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectRFQSubList->columns(array("TransId","SubmittalName"))									
					->where(array("a.RFQId"=>$RfqId))
					->order("a.SubmittalName");
		$statementRFQSubList = $sql->getSqlStringForSqlObject($selectRFQSubList); 
		$rsRFQSubList = $dbAdapter->query($statementRFQSubList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$selectRFQSubTrans = $sql->select();
		$selectRFQSubTrans->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectRFQSubTrans->columns(array("TransId","SubmittalName"), array("VendorId"))
					->join(array("b"=>"VM_RFVSubmittalTrans"), "a.TransId=b.RFQSubmittalTransId", array("VendorId", "SubmittalDocPath"), $selectRFQSubTrans::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId))
					->order("a.SubmittalName");
		$statementRFQSubTrans = $sql->getSqlStringForSqlObject($selectRFQSubTrans); 
		$rsRFQSubTrans = $dbAdapter->query($statementRFQSubTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$submittalFound = array();
		foreach($rsRFQSubTrans as $rsRFQSubTransFound) {
			$transId =  $rsRFQSubTransFound['TransId'];
			$vendorId =  $rsRFQSubTransFound['VendorId'];
			if($rsRFQSubTransFound['SubmittalDocPath'] != ''){
				$submittalFound[$transId][$vendorId] = 1;
			}
		}
		$this->_view->submittalFound = $submittalFound;
		
		
		$this->_view->results = $results;
		$this->_view->rsRFQVendorList = $rsRFQVendorList;
		$this->_view->rsRFQTermtrans = $rsRFQTermtrans;
		$this->_view->rsRFQTermList = $rsRFQTermList;
		$this->_view->rsRFQSubList = $rsRFQSubList;
		//$this->_view->resp = $resp;		
		$this->_view->RfqId = $RfqId;
		
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
		
	}

	public function analysisRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$resp = array();
				//Write your Ajax post code here						
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}			
		} else if($request->isPost()) {
				//Write your Normal form post code here				
		}
		
		$selectAnal = $sql->select(); 
		$selectAnal->from(array("G"=>"VM_AnalysisRegister"))
				->columns(array(new Expression("G.AnalysisRegId,G.AnalysisRegNo,Convert(varchar(10),G.AnalysisRegDate,105) as AnalysisRegDate, 
					CASE WHEN G.Approve='Y' THEN 'Yes' WHEN G.Approve='P' THEN 'Partial' Else 'No' END as Approve,b.RFQNo") ))
				->join(array("b"=>"VM_RFQRegister"), "G.RFQId=b.RFQRegId", array(), $selectAnal::JOIN_INNER)
                ->where (array("G.DeleteFlag"=>0))
				->order("G.AnalysisRegDate Desc");		
		$statement = $sql->getSqlStringForSqlObject($selectAnal);		
		$regResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->regResult = $regResult;
		return $this->_view;	
	}

	public function analysisDetailedAction(){
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
		$RegId = $this->params()->fromRoute('regid');
		$request = $this->getRequest();
		$response = $this->getResponse();		
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
				
			}
		}
		$RfqId=0;
		$select = $sql->select();
		$select->from('VM_AnalysisRegister')
			   ->columns(array('RFQId'))
			   ->where(array("AnalysisRegId"=>$RegId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'analysis-register'));
		}
		else { $RfqId=$resultsVen[0]['RFQId']; }
		
		$select = $sql->select();
		$select->from('VM_AnalysisRegister')
			   ->columns(array('RFQId','AnalysisRegNo','Approve'))
			   ->where(array("AnalysisRegId"=>$RegId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsAnalReg = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RFQRegister"));
		$selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
		$selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		//
		$selectRFQVendorList = $sql->select();
		$selectRFQVendorList->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFQVendorList->columns(array(new Expression("distinct a.VendorId as VendorId ,b.VendorName as VendorName")))
					->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array(), $selectRFQVendorList::JOIN_INNER)				
					->where(array("a.RFQId"=>$RfqId, "a.Valid"=>"1"));
		$selectRFQVendorList->order("b.VendorName");
		$statementRFQVendorList = $sql->getSqlStringForSqlObject($selectRFQVendorList); 
		$rsRFQVendorList = $dbAdapter->query($statementRFQVendorList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectRFQResList = $sql->select();
		$selectRFQResList->from(array("a"=>"VM_RFQTrans"));
		$selectRFQResList->columns(array("ResourceId","Quantity"), array("Code","ResourceName"), array("UnitName"))
					->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $selectRFQResList::JOIN_INNER)
					->join(array("c"=>"Proj_UOM"), "b.UnitId=c.UnitId", array("UnitName"), $selectRFQResList::JOIN_LEFT)					
					->where(array("a.RFQId"=>$RfqId))
					->order("b.ResourceName");
		$statementRFQResList = $sql->getSqlStringForSqlObject($selectRFQResList); 
		$rsRFQResList = $dbAdapter->query($statementRFQResList, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$selectRFQRestrans = $sql->select();
		$selectRFQRestrans->from(array("a"=>"VM_RFVTrans"));
		$selectRFQRestrans->columns(array("ResourceId","Quantity","Rate","Amount", "Status"), array("Code","ResourceName"), array("VendorId"))
					->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $selectRFQRestrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegId=c.RegId", array("VendorId"), $selectRFQRestrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$RfqId));
		$statementRFQRestrans = $sql->getSqlStringForSqlObject($selectRFQRestrans);
		$rsRFQRestrans = $dbAdapter->query($statementRFQRestrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resourceTrans = array();
		foreach($rsRFQRestrans as $rsRFQRestDet) {
			$resourceId =  $rsRFQRestDet['ResourceId'];
			$vendorId =  $rsRFQRestDet['VendorId'];
			$resourceTrans[$resourceId][$vendorId] = array('rate'=>$rsRFQRestDet['Rate'], 'status'=>$rsRFQRestDet['Status']);
		}
		$this->_view->resourceTrans = $resourceTrans;
		
		
		$selectRFQTotamount = $sql->select();
		$selectRFQTotamount->from(array("a"=>"VM_RFVTrans"));
		$selectRFQTotamount->columns(array(new Expression("b.VendorId,Sum(isnull(a.Amount,0)) as Amount")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectRFQTotamount::JOIN_INNER)					
					->where(array("b.RFQId"=>$RfqId))
					->group(new expression('b.VendorId'));
		$statementRFQTotamount = $sql->getSqlStringForSqlObject($selectRFQTotamount);
		$rsRFQTotamount = $dbAdapter->query($statementRFQTotamount, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$vendorTotamt = array();
		foreach($rsRFQTotamount as $rsRFQTotamt) {
			$vendorId =  $rsRFQTotamt['VendorId'];
			$vendorTotamt[$vendorId] = $rsRFQTotamt['Amount'];
		}
		$this->_view->vendorTotamt = $vendorTotamt;
		
		
		$selectRFVFound = $sql->select();
		$selectRFVFound->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFVFound->columns(array("RegId","RFQId","VendorId"))				
					->where(array("a.RFQId"=>$RfqId));
		$statementRFVFound = $sql->getSqlStringForSqlObject($selectRFVFound);
		$rsRFVFound = $dbAdapter->query($statementRFVFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$vendorRFVFound = array();
		foreach($rsRFVFound as $rsRfvFound) {
			$vendorId =  $rsRfvFound['VendorId'];
			$vendorRFVFound[$vendorId] = $rsRfvFound['RegId'];
		}

		$this->_view->vendorRFVFound = $vendorRFVFound;
		
		$this->_view->rsRFQVendorList = $rsRFQVendorList;
		$this->_view->rsRFQRestrans = $rsRFQRestrans;
		$this->_view->rsRFQResList = $rsRFQResList;		
		$this->_view->RfqId = $RfqId;
		//
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->RegId = $RegId;
		$this->_view->resultsAnalReg = $resultsAnalReg;
		$this->_view->results = $results;
		return $this->_view;
	}

	public function indexAction(){
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
	public function deleteAction()
    {
        $rfq = $this->getRequest();
        if ($rfq->isPost()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $status = '';
                $RfqId = $this->bsf->isNullCheck($this->params()->fromPost('RfqId'), 'number');
                $sql = new Sql($dbAdapter);
                $response = $this->getResponse();
                $connection->beginTransaction();

                $deleteTechInfo = $sql->delete();
                $deleteTechInfo->from('VM_TechInfoRegister')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteTechInfo);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteSubmittal = $sql->delete();
                $deleteSubmittal->from('VM_RFQSubmittalTrans')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteSubmittal);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteTerms = $sql->delete();
                $deleteTerms->from('VM_RFQTerms')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteTerms);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteVendorTrans = $sql->delete();
                $deleteVendorTrans->from('VM_RFQVendorTrans')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteVendorTrans);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteTechVerify = $sql->delete();
                $deleteTechVerify->from('VM_RFQTechVerificationTrans')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteTechVerify);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteMultiCC = $sql->delete();
                $deleteMultiCC->from('VM_RFQMultiCCTrans')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteMultiCC);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteRFQTrans = $sql->delete();
                $deleteRFQTrans->from('VM_RFQTrans')
                    ->where('RFQId=' . $RfqId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteRFQTrans);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteRFQ = $sql->update();
                $deleteRFQ->table('VM_RFQRegister')
                    ->set(array('DeleteFlag' => 1))
                    ->where(array("RFQRegId" => $RfqId));
                $DelStatement = $sql->getSqlStringForSqlObject($deleteRFQ);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);$deleteRFQ = $sql->update();
				
				//response delete
				$deleteResponse = $sql->update();
                $deleteResponse->table('VM_RequestFormVendorRegister')
                    ->set(array('DeleteFlag' => 1))
                    ->where(array("RFQId" => $RfqId));
                $DelStatement = $sql->getSqlStringForSqlObject($deleteResponse);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				// selecting Trans Detail
				$selectRegister= $sql->select()
					->from('VM_RequestFormVendorRegister')
					->columns(array('RegId'))
                    ->where(array("RFQId" => $RfqId));
				$selectRegisterStatement = $sql->getSqlStringForSqlObject($selectRegister);
                $selectRegisterDetail = $dbAdapter->query($selectRegisterStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($selectRegisterDetail as $selectRegister){
					//rfvtrans delete
					$deleteRFVTrans = $sql->delete();
					$deleteRFVTrans->from('VM_RFVTrans')
						->where('RegId=' . $selectRegister['RegId']);
					$DelRFVStatement = $sql->getSqlStringForSqlObject($deleteRFVTrans);
					$dbAdapter->query($DelRFVStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//VM_RFVSubmittalTrans
					$deleteRFVSubmittalTrans = $sql->delete();
					$deleteRFVSubmittalTrans->from('VM_RFVSubmittalTrans')
						->where('RegId=' . $selectRegister['RegId']);
					$DelRFVSubmittalStatement = $sql->getSqlStringForSqlObject($deleteRFVSubmittalTrans);
					$dbAdapter->query($DelRFVSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//VM_RFVTerms
					$deleteRFVTermsTrans = $sql->delete();
					$deleteRFVTermsTrans->from('VM_RFVTerms')
						->where('RegisterId=' . $selectRegister['RegId']);
					$DelRFVTermsStatement = $sql->getSqlStringForSqlObject($deleteRFVTermsTrans);
					$dbAdapter->query($DelRFVTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//VM_RFVTechVerificationTrans
					$deleteRFVTechTrans = $sql->delete();
					$deleteRFVTechTrans->from('VM_RFVTechVerificationTrans')
						->where('RegId=' . $selectRegister['RegId']);
					$DelRFVTechStatement = $sql->getSqlStringForSqlObject($deleteRFVTechTrans);
					$dbAdapter->query($DelRFVTechStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
				}
				//reponse VM_TechInfoRegister
				$deleteRFVTechInfoTrans = $sql->delete();
				$deleteRFVTechInfoTrans->from('VM_TechInfoRegister')
					->where('RegId=' . $selectRegister['RegId']);
				$DelRFVTechInfoStatement = $sql->getSqlStringForSqlObject($deleteRFVTechInfoTrans);
				$dbAdapter->query($DelRFVTechInfoStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $status = 'Deleted';
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }

            $response->setContent($status);
            return $response;
        }
    }

    public function deletevaAction()
    {
        $rfq = $this->getRequest();
        if ($rfq->isPost()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $status = '';
                $AnalysisRegId = $this->bsf->isNullCheck($this->params()->fromPost('AnalysisRegId'), 'number');
                $sql = new Sql($dbAdapter);
                $response = $this->getResponse();
                $connection->beginTransaction();

                $deleteAnalTerm = $sql->delete();
                $deleteAnalTerm->from('VM_AnalysisTermsDet')
                    ->where('RegId=' . $AnalysisRegId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteAnalTerm);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteAnalVTerm = $sql->delete();
                $deleteAnalVTerm->from('VM_AnalysisVendorTrans')
                    ->where('RegId=' . $AnalysisRegId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteAnalVTerm);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteAnalysis = $sql->update();
                $deleteAnalysis->table('VM_AnalysisRegister')
                    ->set(array('DeleteFlag' => 1))
                    ->where(array("AnalysisRegId" => $AnalysisRegId));
                $DelStatement = $sql->getSqlStringForSqlObject($deleteAnalysis);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $status = 'Deleted';
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }

            $response->setContent($status);
            return $response;
        }
    }
}