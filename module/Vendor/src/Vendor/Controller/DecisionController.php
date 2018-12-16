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

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;

class DecisionController extends AbstractActionController
{
	public function __construct(){
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	public function requestDecisionAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vNo = CommonHelper::getVoucherNo(202,date('Y/m/d') ,0,0, $dbAdapter,"");
		//print_r($vNo);die;
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();
			if($request->isPost()){

				//Write your Ajax post code here
				$postParam = $request->getPost();
//                 echo"<pre>";
//                  print_r($postParam);
//                 echo"</pre>";
//                  die;
//                 return;
                $mode = $this->bsf->isNullCheck($postParam['mode'], 'string');
                $reqType=$this->bsf->isNullCheck($postParam['decision_type'],'string');
                if($reqType == 2){
                    $reqType='Material';
                }

                if($mode == 'firstStep'){
					$select1 = $sql->select();
					$select1->from(array("a"=>"VM_RequestRegister"))
						->columns(array("Sel"=>new Expression("1-1"),'RequestId', 'RequestDate'=>new Expression("Convert(varchar(10),a.RequestDate,105)"),
                            'RequestNo',
                                "Approve"=>new Expression("CASE WHEN a.Approve='Y' THEN 'Yes'
								 WHEN a.Approve='P' THEN 'Partial'
								 Else 'No' END")),

                            array("CostCentreName"))
						->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array("CostCentreName"), $select1::JOIN_LEFT)
						->where(array('a.RequestType'=>$reqType,'a.Approve'=>'Y') );
					$select1->where("a.RequestDate <= '".Date('m-d-Y', strtotime(($postParam['Decision_date'])))."'");
					$select1->where(array('a.DeleteFlag'=>0));
					$select1->order("a.RequestNo DESC");
                   	$mostStatement = $sql->getSqlStringForSqlObject($select1);
					$resp['data'] = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
				else if($mode == 'resourcePickList'){
					$resSelect = $sql->select(); 
					$resSelect->from(array("a"=>"VM_RequestTrans"))
						->columns(array("Sel"=>new Expression("1-1"),'ResourceId'), array("Code","ResourceName"))
						->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resSelect::JOIN_INNER)
						->where(array('a.RequestId'=>explode(",",($postParam['reqId']))));
					$resSelect->order("b.ResourceName");
                    $resStatement = $sql->getSqlStringForSqlObject($resSelect);
					$resp['data'] = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();					
				}
				else if($mode == 'secondStep'){

					$resourceListSelect = $sql->select();
					$resourceListSelect->from(array("a"=>"Proj_Resource"))
						->columns(array('ResourceId',"Code","ResourceName"), array("UnitName"))
						->join(array("b"=>"Proj_UOM"), "a.UnitId=b.UnitId", array("UnitName"), $resourceListSelect::JOIN_INNER)
						->where(array('a.ResourceId'=>explode(",",($postParam['resId']))));
					$resourceListSelect->order("a.ResourceName");
					$mostresourceListStatement = $sql->getSqlStringForSqlObject($resourceListSelect);
					$resourceResult = $dbAdapter->query($mostresourceListStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($resourceResult as $rs){
						$rs['request'] = array();
						$select1 = $sql->select();
						$select1->from(array("a"=>"VM_RequestTrans"))
							->columns(array('RequestId', 'RequestTransId',"Sel"=>new Expression("1"), 'ReqDate'=>new Expression("Convert(varchar(10),ReqDate,105)"),'ResourceId','ItemId','Quantity',
							"BalQty"=>new Expression("(a.Quantity-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty))"),
							"IndentApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"), 
							"TransferApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"), 
							"ProductionApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"),'HireApproveQty' ),
							array("RequestNo","CostCentreId","RApprove"),array("CostCentreName"),array("ResourceName"),array("UnitName"))
							->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("RequestNo","CostCentreId","RApprove"), $select1::JOIN_INNER)
							->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $select1::JOIN_LEFT)
							->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("ResourceName"), $select1::JOIN_INNER)
							->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
							->where(array('a.RequestId'=>explode(',',($postParam['reqId'])), "a.ResourceId"=>$rs['ResourceId']));
                        $select1->where("CAST(A.Quantity-(A.IndentApproveQty+A.TransferApproveQty+A.HireApproveQty+A.ProductionApproveQty) As Decimal(18,5)) > 0");
                        $mostStatement = $sql->getSqlStringForSqlObject($select1);
						$mostResult = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($mostResult as $data){
                            $wbsSelect = $sql->select();
                            $wbsSelect->from(array("a"=>"VM_RequestAnalTrans"))
										->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',
										"Quantity"=>'ReqQty',
										"BalQty"=>new Expression("(a.ReqQty-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty))"),
										"IndentApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"),
										"TransferApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"),
										"ProductionApproveQty"=>new Expression("CAST(0 As Decimal(18,5))"),"HireApproveQty"=>new Expression("CAST(0 As Decimal(18,5))") ),
										array("RequestTransId"),array("CostCentreId"),array("WbsName"))
										->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $wbsSelect::JOIN_INNER)
										->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $wbsSelect::JOIN_INNER)
										->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WbsName"=>"WBSName"), $wbsSelect::JOIN_INNER)
										->where(array('b.RequestTransId'=>$data['RequestTransId']));
							$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
							$data['wbsResults'] = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							
							if(count($data['wbsResults'])>0) {
								foreach($data['wbsResults'] as &$dWbs) {
									$dWbsSelect = $sql->select();
									$dWbsSelect->from(array("A"=>"WF_OperationalCostCentre"))
												->columns(array(
												"ResourceId"=>new Expression($dWbs['ResourceId']),
												"CostCentreId"=>new Expression($dWbs['CostCentreId']),
												"ItemId"=>new Expression($dWbs['ItemId']),
												"ToCostcentreId"=>new Expression("A.CostCentreId"),
												"CostCentreName"=>new Expression("A.CostCentreName"),
												"ClosingStock"=>new Expression("isnull(Sum(B.ClosingStock),0)"),
												"Qty"=>new Expression("CAST(0 As Decimal(18,5))")
												))
												->join(array("B"=>"MMS_Stock"), "A.CostCentreId=B.CostCentreId", array(), $dWbsSelect::JOIN_INNER)
												->where('B.ClosingStock>0 and B.ResourceId='.$dWbs['ResourceId'].' and A.CostCentreId Not in ('.$dWbs['CostCentreId'].') group by A.CostCentreId,A.CostCentreName');
									$dwbsStatement = $sql->getSqlStringForSqlObject($dWbsSelect);
									$dWbs['descision'] = $dbAdapter->query($dwbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								}
							} else {
								$dWbsSelect = $sql->select();
								$dWbsSelect->from(array("A"=>"WF_OperationalCostCentre"))
											->columns(array(
											"ResourceId"=>new Expression($data['ResourceId']),
											"CostCentreId"=>new Expression($data['CostCentreId']),
											"ItemId"=>new Expression($data['ItemId']),
											"ToCostcentreId"=>new Expression("A.CostCentreId"),
											"CostCentreName"=>new Expression("A.CostCentreName"),
											"ClosingStock"=>new Expression("isnull(Sum(B.ClosingStock),0)"),
											"Qty"=>new Expression("CAST(0 As Decimal(18,5))")
											))
											->join(array("B"=>"MMS_Stock"), "A.CostCentreId=B.CostCentreId", array(), $dWbsSelect::JOIN_INNER)
											->where('B.ClosingStock>0 and B.ResourceId='.$data['ResourceId'].' and A.CostCentreId Not in ('.$data['CostCentreId'].') group by A.CostCentreId,A.CostCentreName');
							 	$dwbsStatement = $sql->getSqlStringForSqlObject($dWbsSelect);
								$data['descision'] = $dbAdapter->query($dwbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							}
							array_push($rs['request'], $data);
						}
						array_push($resp, $rs);
					}
				}
				else if($mode == 'voucherValid'){
					$select = $sql->select();		
					$select->from(array('a' => 'VM_RequestDecision'))
						->columns(array('RDecisionNo'))
						->where(array('a.RDecisionNo'=>trim($this->bsf->isNullCheck($postParam['voucherno'],'string'))));
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();						
				}
				/* else if($postParam['mode'] == 'editSecondStep'){
					$resourceListSelect = $sql->select();
					$resourceListSelect->from(array("a"=>"Proj_Resource"))
						->columns(array('ResourceId',"Code","ResourceName"), array("UnitName"))
						->join(array("b"=>"Proj_UOM"), "a.UnitId=b.UnitId", array("UnitName"), $resourceListSelect::JOIN_INNER)
						->where(array('a.ResourceId'=>explode(",", $postParam['resId'])));
					$resourceListSelect->order("a.ResourceName");
					$mostresourceListStatement = $sql->getSqlStringForSqlObject($resourceListSelect);
					$resourceResult = $dbAdapter->query($mostresourceListStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($resourceResult as $rs){
						//Trans
						$Subselect2= $sql->select();
						$Subselect2->from("VM_ReqDecTrans")
								 ->columns(array("RequestId"))
								 ->where('DecisionId='. $postParam['decId']);
													 
						$select1 = $sql->select(); 
						$select1->from(array("a"=>"VM_RequestTrans"))
							->columns(array('RequestTransId',"Sel"=>new Expression("1"), 'ReqDate','ResourceId','ItemId','Quantity',
							"BalQty"=>new Expression("(a.Quantity-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty))")
							 ),
							array("RequestNo","CostCentreId","RApprove"),array("CostCentreName"),array("ResourceName"),array("UnitName"),array("RequestId")
							,array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
							"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),				
							"HideIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"HideTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
							"HidePApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HideHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") )
							)
							->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("RequestNo","CostCentreId","RApprove"), $select1::JOIN_INNER)
							->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $select1::JOIN_LEFT)
							->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("ResourceName"), $select1::JOIN_INNER)
							->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
							->join(array("f"=>"VM_ReqDecTrans"), "a.ResourceId=d.ResourceId", array("RequestId"), $select1::JOIN_INNER)
							->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
							"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),				
							"HideIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"HideTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
							"HidePApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HideHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ), $select1::JOIN_INNER)

							->where(array('a.RequestId' => explode(',', $postParam['reqId']), "a.ResourceId"=>$rs['ResourceId'], 'g.DecisionId'=>$postParam['decId'] ));

						$DecisionStatement = $sql->getSqlStringForSqlObject($select1);
						$DecisionTransResult = $dbAdapter->query($DecisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($DecisionTransResult as $data){	
							//WBSTrans
							$select1 = $sql->select(); 
							$select1->from(array("a"=>"VM_RequestAnalTrans"))
								->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',
								"Quantity"=>new Expression("CAST(a.ReqQty As Decimal(18,5))"),
								"BalQty"=>new Expression("CAST(a.ReqQty-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty) As Decimal(18,5))")),
								array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),
								array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
								"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
								"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
								"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),
								"HiddenIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
								"HiddenTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
								"HiddenPApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
								"HiddenHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ) )
								->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select1::JOIN_INNER)
								->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select1::JOIN_INNER)
								->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WbsName"=>"WBSName"), $select1::JOIN_INNER)
								->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
								->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
								"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
								"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
								"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),
								"HiddenIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
								"HiddenTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
								"HiddenPApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
								"HiddenHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ), $select1::JOIN_INNER)
									->where(array('c.RequestId' => $postParam['rid'],
											'f.DecisionId'=>$postParam['decId'] ,
											//'c.RequestType'=>$DecFill['RequestType'],
											'a.ReqTransId'=>$data['RequestTransId'] ));

							$wbsStatement = $sql->getSqlStringForSqlObject($select1);
							$data['wbsResults'] = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							array_push($rs['request'], $data);
						}
						array_push($resp, $rs);
					}
				} */
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			//$response->setContent($wbsStatement);
			return $response;
		}
		else if($request->isPost()){
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParam = $request->getPost();
				// echo"<pre>";
                 // print_r($postParam);
                // echo"</pre>";
                 // die;
                // return;
			try{
				if($vNo['genType']){
					$voucher = CommonHelper::getVoucherNo(202,date('Y/m/d', strtotime($postParam['decision_date'])) ,0,0, $dbAdapter,"I");
					$voucherNo = $voucher['voucherNo'];
				}
				else{
					$voucherNo = $this->bsf->isNullCheck($postParam['VoucherNo'],'string');
				}
				$registerInsert = $sql->insert('VM_RequestDecision');
				$registerInsert->values(array(
                        "DecDate"=>date('Y-m-d', strtotime($this->bsf->isNullCheck($postParam['decision_date'],'date'))),
                        "RequestType"=>$this->bsf->isNullCheck($postParam['decision_type'],'string'),
                        "RDecisionNo"=>$voucherNo
                ));
				$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$requestDecId = $dbAdapter->getDriver()->getLastGeneratedValue();
				$request_id = explode(",",$this->bsf->isNullCheck($postParam['requestId'],'number'));
				foreach($request_id as $reqd){
					//Multiple Request
					$requestMultiInsert = $sql->insert('VM_ReqDecTrans');
					$requestMultiInsert->values(array(
                        "DecisionId"=>$requestDecId,
                        "RequestId"=>$reqd));
					$requestMultiStatement = $sql->getSqlStringForSqlObject($requestMultiInsert);
					$requestMultiResults = $dbAdapter->query($requestMultiStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
					$rowid = $postParam['rowid'];
					for ($i = 1; $i <= $rowid; $i++) {
						$iowrowid =$postParam['iow_' . $i . '_rowid'];
						for ($j = 1; $j <= $iowrowid; $j++) {
							
							$select = $sql->update();
							$select->table('VM_RequestTrans');
							$select->set(array(
								'IndentApproveQty' => new Expression('IndentApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_IndentApproveQty_' . $j], 'number')),
								'TransferApproveQty' => new Expression('TransferApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_TransferApproveQty_' . $j], 'number')),
								'ProductionApproveQty' => new Expression('ProductionApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_ProductionApproveQty_' . $j], 'number')),
							));
							$select->where(array('RequestTransId'=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_RequestTransId_' . $j], 'number')));
							$requestupdateStatement = $sql->getSqlStringForSqlObject($select);
							$dbAdapter->query($requestupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
										
							$requestInsert = $sql->insert('VM_ReqDecQtyTrans');
							$requestInsert->values(array(
								"DecisionId"=>$requestDecId,
								"ReqTransId"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_RequestTransId_' . $j], 'number'),
								"IndentQty"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_IndentApproveQty_' . $j], 'number'),
								"TransferQty"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_TransferApproveQty_' . $j], 'number'),
								"ProductionQty"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_ProductionApproveQty_' . $j], 'number'),
								));
							$requestStatement = $sql->getSqlStringForSqlObject($requestInsert); 
							$dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $requestDecTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$requestrowid = $postParam['iow_' . $i . '_request_' . $j . '_rowid'];
							if ($requestrowid > 0) {
								for ($k = 1; $k <= $requestrowid; $k++) {
									
									$select = $sql->update();
									$select->table('VM_RequestAnalTrans');
									$select->set(array(
										'IndentApproveQty' => new Expression('IndentApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_IndentApproveQty_' . $k . ''], 'number')),
										'TransferApproveQty' => new Expression('TransferApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_TransferApproveQty_' . $k . ''], 'number')),
										'ProductionApproveQty' => new Expression('ProductionApproveQty +'.$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_ProductionApproveQty_' . $k . ''], 'number')),
									));
									$select->where(array('RequestAHTransId'=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_RequestAHTransId_' . $k . ''], 'number')));				
									$requestAnalupdateStatement = $sql->getSqlStringForSqlObject($select);
									$requestAnalUpdateResults = $dbAdapter->query($requestAnalupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
									$requestTransInsert = $sql->insert('VM_ReqDecQtyAnalTrans');
									$requestTransInsert->values(array(
										"TransId"=>$requestDecTransId,
										"DecisionId"=>$requestDecId,
										"ReqAHTransId"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_RequestAHTransId_' . $k . ''], 'number'),
										"ReqTransId"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_ReqTransId_' . $k . ''], 'number'),
										"IndentQty"=> $this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_IndentApproveQty_' . $k . ''], 'number'),
										"TransferQty"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_TransferApproveQty_' . $k . ''], 'number'),
										"ProductionQty"=>$this->bsf->isNullCheck($postParam['iow_' . $i . '_request_' . $j . '_ProductionApproveQty_' . $k . ''], 'number'),
									
									));
									$requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
									$requestTransResults = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);	
									
									$whrowid = $postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_wrowid'];
									for ($w = 1; $w <= $whrowid; $w++) {
										if ($this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_Qty_' . $w], 'number') > 0) {
											$whInsert = $sql->insert('VM_ReqDecMultiCCTrans');
											$whInsert->values(array(
												"DecisionId" => $requestDecId,
												"ResourceId" =>$this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_ResourceId_' . $w . ''], 'string'),
												"CostCentreId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_CostCentreId_' . $w . ''], 'string'),
												"ToCostCentreId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_ToCostcentreId_' . $w . ''], 'string'),
												"Quantity" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_Qty_' . $w . ''], 'number'),
												"ItemId" =>$this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_wbs_' . $k . '_ItemId_' . $w . ''], 'string'),
												));
											$whInsertStatement = $sql->getSqlStringForSqlObject($whInsert); 
											$dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
										}
									}
								}
							} else{
								$wahrowid = $postParam['wh_' . $i . '_dec_' . $j . '_wrowid'];
								for ($wa = 1; $wa <= $wahrowid; $wa++) {
									if ($this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_Qty_' . $wa . ''], 'number') > 0) {
										$pwhInsert = $sql->insert('MMS_DCWareHousePoTrans');
										$pwhInsert->values(array(
											"DecisionId" => $requestDecId,
											"ResourceId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_ResourceId_' . $wa . ''], 'number' . ''),
											"CostCentreId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_CostCentreId_' . $wa . ''], 'number' . ''),
											"ToCostCentreId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_ToCostcentreId_' . $wa . ''], 'number' . ''),
											"Quantity" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_Qty_' . $wa . ''], 'number' . ''),
											"ItemId" => $this->bsf->isNullCheck($postParam['wh_' . $i . '_dec_' . $j . '_ItemId_' . $wa . ''], 'number' . ''),
											));
										$pwhInsertStatement = $sql->getSqlStringForSqlObject($pwhInsert);
										$dbAdapter->query($pwhInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
									}
								}
							}
						}
					
					}
				// $data = json_decode($postParam['totTrans'], true);
				// $proType='ProductionApproveQty';
				// $proTypecolname='ProductionQty';
				// if($postParam['decision_type'] == 3){
					// $proType = 'HireApproveQty';
					// $proTypecolname='HireQty';
				// }

				// foreach($data as $resId){
					// foreach($resId['reqTransId'] as $reqTransId){
						// $transId=$reqTransId['reqTransId'];
						
						// $select = $sql->update();
						// $select->table('VM_RequestTrans');
						// $select->set(array(
							// 'IndentApproveQty' => new Expression('IndentApproveQty +'.$this->bsf->isNullCheck($postParam['poQty_'.$transId],'number')),
							// 'TransferApproveQty' => new Expression('TransferApproveQty +'.$this->bsf->isNullCheck($postParam['transfer_'.$transId],'number')),
							
							// $proType => new Expression($proType.' +'.$this->bsf->isNullCheck($postParam['production_'.$transId],'number'))
						 // ));
						// $select->where(array('RequestTransId'=>$transId));

						// $requestupdateStatement = $sql->getSqlStringForSqlObject($select);
						// $requestUpdateResults = $dbAdapter->query($requestupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						
						// $requestInsert = $sql->insert('VM_ReqDecQtyTrans');
						// $requestInsert->values(array(
                            // "DecisionId"=>$requestDecId,
                            // "ReqTransId"=>$transId,
						    // "IndentQty"=>$this->bsf->isNullCheck($postParam['poQty_'.$transId],'number'),
                            // "TransferQty"=>$this->bsf->isNullCheck($postParam['transfer_'.$transId],'number'),
						// $proTypecolname=>$this->bsf->isNullCheck($postParam['production_'.$transId],'number')));
						// $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
						// $requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						// $requestDecTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

					
						// foreach($reqTransId['reqAHTransId'] as $reqAHTransId){
							
                            // $IndentApproveQty=$this->bsf->isNullCheck($postParam['poWbsQty_'.$reqAHTransId],'number');
                            // $TransferApproveQty=$this->bsf->isNullCheck($postParam['transferWbs_'.$reqAHTransId],'number');
                            // $Product=$this->bsf->isNullCheck($postParam['productionWbs_'.$reqAHTransId],'number');
							// $select = $sql->update();
							// $select->table('VM_RequestAnalTrans');
							// $select->set(array(
								// 'IndentApproveQty' => new Expression('IndentApproveQty +'.$IndentApproveQty),
								// 'TransferApproveQty' => new Expression('TransferApproveQty +'.$TransferApproveQty),
								// $proType => new Expression($proType.' +'.$Product)
							 // ));
							// $select->where(array('RequestAHTransId'=>$reqAHTransId));				
							// $requestAnalupdateStatement = $sql->getSqlStringForSqlObject($select);
							// $requestAnalUpdateResults = $dbAdapter->query($requestAnalupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


							
							// $requestTransInsert = $sql->insert('VM_ReqDecQtyAnalTrans');
							
							// $requestTransInsert->values(array(
                                // "TransId"=>$requestDecTransId,
                                // "DecisionId"=>$requestDecId,
							    // "ReqAHTransId"=>$reqAHTransId,
                                // "ReqTransId"=>$transId,
							    // "IndentQty"=>$this->bsf->isNullCheck($postParam['poWbsQty_'.$reqAHTransId],'number'),
                                // "TransferQty"=>$this->bsf->isNullCheck($postParam['transferWbs_'.$reqAHTransId],'number'),
							    // $proTypecolname=>$postParam['productionWbs_'.$reqAHTransId]
							
							// ));

							 // $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
						    // $requestTransResults = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);	

						// }
					// }
				// }
				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'Request-Decision-Create','N','Request-Decision',$requestDecId,0,0,'Vendor',$voucherNo,$this->auth->getIdentity()->UserId,0,0);
				//$this->redirect()->toRoute('ats/detailed', array('controller' => 'decision','action' => 'detailed','decisionid' => $requestDecId));
				$this->redirect()->toRoute('ats/default', array('controller' => 'decision','action' => 'register'));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		$this->_view->genType = $vNo["genType"];
		
		if ($vNo["genType"] ==false)
			$this->_view->svNo = "";
		else
			$this->_view->svNo = $vNo["voucherNo"];
		
		$this->_view->vNo = $vNo;		
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	} 
	Public function registerAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){

			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		$recentDecision = $sql->select();
		$recentDecision->from(array("a"=>"VM_RequestDecision"));
		$recentDecision->columns(array(new Expression("top 1 a.DecisionId,Convert(varchar(10),a.DecDate,105) as DecDate,a.RDecisionNo,CASE WHEN a.Approve='Y' THEN 'Approved'
																 Else 'Pending' END as Approve")),array("TypeName"))
					->join(array("b"=>"Proj_ResourceType"), "b.TypeId=a.RequestType", array("TypeName"), $recentDecision::JOIN_LEFT)
					//->order("a.DecDate ,a.RDecisionNo Desc");
					->where(array('a.DeleteFlag'=>0))
					->order('a.DecDate Desc')
					->order('a.RDecisionNo Desc');
		 $statement = $sql->getSqlStringForSqlObject($recentDecision);
		 $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectRegister = $sql->select();
		$selectRegister->from(array("a"=>"VM_RequestDecision"));
		$selectRegister->columns(array(new Expression("a.DecisionId,a.RDecisionNo,Convert(varchar(10),a.DecDate,105) as DecDate"),new Expression("CASE WHEN a.Approve='Y' THEN 'Approved'
																WHEN a.Approve='N' THEN 'UnApproved'
																Else 'Pending'
														END as Approve")),array("TypeName"))
					->join(array("b"=>"Proj_ResourceType"), "b.TypeId=a.RequestType", array("TypeName"), $selectRegister::JOIN_LEFT)
					->where(array('a.DeleteFlag'=>0))
					->order('a.DecDate Desc')
					->order('a.RDecisionNo Desc');
		$selectRegisterStatement = $sql->getSqlStringForSqlObject($selectRegister);
		$gridResult = $dbAdapter->query($selectRegisterStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();


		 if(count($results) > 0){
			 $this->_view->RDecisionNo = $results[0]['RDecisionNo'];
			 $this->_view->DecDate = $results[0]['DecDate'];
			 $this->_view->id = $results[0]['DecisionId'];
			 $this->_view->TypeName = $results[0]['TypeName'];
			 $this->_view->Approve = $results[0]['Approve'];
			 $this->_view->gridResult = $gridResult;
		 }

		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}
	Public function detailedAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		$decId = $this->params()->fromRoute('decisionid');

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){

			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		$select = $sql->select();
		$select->from('VM_RequestDecision')
			   ->columns(array('DecisionId'))
			   ->where(array("DecisionId"=>$decId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsDecVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsDecVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'decision','action' => 'register'));
		}
		$selectCurDecision = $sql->select();
		$selectCurDecision->from(array("a"=>"VM_RequestDecision"));
		$selectCurDecision->columns(array(new Expression("a.RDecisionNo,Convert(varchar(10),a.DecDate,105) as DecDate,CASE WHEN a.Approve='Y' THEN 'Approved'
																 Else 'Pending' END as Approve")),array("TypeName"))
					->join(array("b"=>"Proj_ResourceType"), "b.TypeId=a.RequestType", array("TypeName"), $selectCurDecision::JOIN_LEFT);
					$selectCurDecision->where(array("a.decisionid"=>$decId));
		 $statement = $sql->getSqlStringForSqlObject($selectCurDecision);
		 $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$selectTrans = $sql->select();
		$selectTrans->from(array("a"=>"VM_ReqDecQtyTrans"))
					->columns(array("TransId","IndentApproveQty"=>"IndentQty","TransferApproveQty"=>"TransferQty","ProductionApproveQty"=>"ProductionQty"))
					->join(array("b"=>"VM_ReqDecTrans"), "a.DecisionId=b.DecisionId", array("RequestId"), $selectTrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestTrans"), "c.RequestId=b.RequestId and a.ReqTransId=c.RequestTransId", array("RequestTransId","ResourceId","Quantity","UnitId","ReqDate"=>NEW EXPRESSION("Convert(varchar(10),ReqDate,105)"),"BalQty"), $selectTrans::JOIN_INNER)
					->join(array("d"=>"VM_RequestRegister"), "c.RequestId=d.RequestId", array("RequestNo","CostCentreId"), $selectTrans::JOIN_INNER)
					->join(array("e"=>"WF_OperationalCostCentre"), "d.CostCentreId=e.CostCentreId", array("CostCentreName"), $selectTrans::JOIN_LEFT)
					->join(array("f"=>"Proj_Resource"), "c.ResourceId=f.ResourceId", array("ResourceName"), $selectTrans::JOIN_INNER)
					->join(array("g"=>"Proj_UOM"), "f.UnitId=g.UnitId", array("UnitName"), $selectTrans::JOIN_LEFT)

					->where(array('a.DecisionId'=>$decId));
		$selectTransStatement = $sql->getSqlStringForSqlObject($selectTrans);
		$trans = $dbAdapter->query($selectTransStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectAnalTrans = $sql->select();
		$selectAnalTrans->from(array("a"=>"VM_ReqDecQtyAnalTrans"))
						->columns(array("TransId","ReqAHTransId","IndentApproveQty"=>"IndentQty","TransferApproveQty"=>"TransferQty","ProductionApproveQty"=>"ProductionQty"))
						->join(array("b"=>"VM_ReqDecQtyTrans"), "a.TransId=b.TransId and a.DecisionId=b.DecisionId and a.ReqTransId=b.ReqTransId", array(), $selectAnalTrans::JOIN_INNER)
						->join(array("c"=>"VM_RequestAnalTrans"), "a.ReqAHTransId=c.RequestAHTransId and a.ReqTransId=b.ReqTransId", array("ReqTransId","AnalysisId"), $selectAnalTrans::JOIN_INNER)
						//->join(array("b"=>"VM_ReqDecQtyTrans"), "a.TransId=b.TransId and a.DecisionId=b.DecisionId and a.ReqTransId=b.ReqTransId", array("ReqTransId","AnalysisId"), $selectAnalTrans::JOIN_INNER)
						->join(array("d"=>"Proj_WBSMaster"), "c.AnalysisId=d.WBSId", array("WbsName"=>"WBSName"), $selectAnalTrans::JOIN_INNER)
					->where(array('a.DecisionId'=>$decId));
        $selectAnalTransStatement = $sql->getSqlStringForSqlObject($selectAnalTrans);
		$anal = $dbAdapter->query($selectAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->RDecisionNo = $results['RDecisionNo'];
		$this->_view->RequestDate = $results['DecDate'];
		$this->_view->TypeName = $results['TypeName'];
		$this->_view->Approve = $results['Approve'];
		$this->_view->decId = $decId;
		$this->_view->trans = $trans;
		$this->_view->anal = $anal;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}
	public function editrequestDecisionAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();

		$vNo = CommonHelper::getVoucherNo(101,date('Y/m/d') ,0,0, $dbAdapter,"");
		$VoucherNo="";
		$ReqdecFor=0;
		$EntryDate=date("d-m-Y");
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'editSecondStep'){
					$resInsertSelect = $sql->select();
					$resInsertSelect->from(array('a'=>'VM_RequestTrans'))
							->columns(array('ResourceId'))
							->join(array('b'=>'VM_ReqDecQtyTrans'), 'a.RequestTransId=b.ReqTransId', array(), $resInsertSelect::JOIN_INNER)
							->join(array('c'=>'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'ResourceName'), $resInsertSelect::JOIN_INNER)
							->join(array('d'=>'Proj_UOM'), 'c.UnitId=d.UnitId', array('UnitName'), $resInsertSelect::JOIN_LEFT)
							->where(array('b.DecisionId'=>($postParam['requestDecId']), 'a.ResourceId'=>explode(',',($postParam['resId']))));


					$resEditSelect = $sql->select();
					$resEditSelect->from(array('a'=>'Proj_Resource'))
							->columns(array('ResourceId', 'Code', 'ResourceName'))
							->join(array('b'=>'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $resEditSelect::JOIN_INNER)
							->where(array('a.ResourceId'=>explode(',',($postParam['resId']))));

					$resEditSelect->combine($resInsertSelect,'Union ALL');

					$resSelect = $sql->select();
					$resSelect->from(array('g'=>$resEditSelect))
							->group(array('g.ResourceId','g.Code','g.ResourceName','g.UnitName'));

					 $resStatement = $sql->getSqlStringForSqlObject($resSelect);
					$res = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();							

					foreach($res as $rs){
						$rs['request'] = array();
						/*Request select*/
						$reqInsertSelect = $sql->select();
						$reqInsertSelect->from(array('a'=>'VM_ReqDecQtyTrans'))
									->columns(array(new Expression("a.ReqTransId,b.RequestId,b.ResourceId, b.ItemId, CAST(a.IndentQty As Decimal(18,5)) AS [IndentApproveQty], 
											CAST(a.TransferQty As Decimal(18,5)) AS [TransferApproveQty],
											CAST(a.ProductionQty As Decimal(18,5)) AS [ProductionApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HireApproveQty],
											CAST(a.IndentQty As Decimal(18,5)) AS [HideIApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [HideTApproveQty],
											CAST(a.ProductionQty As Decimal(18,5)) AS [HidePApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HideHApproveQty]")))
									->join(array('b'=>'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array(), $reqInsertSelect::JOIN_INNER)
									->where(array('a.DecisionId'=>array(($postParam['requestDecId'])),
									'b.RequestId'=> explode(',',($postParam['reqId'])),
									'b.ResourceId'=>$rs['ResourceId']));
						$transSelect = $sql->select();
						$transSelect->from('VM_ReqDecQtyTrans')
								->columns(array('ReqTransId'))
								->where(array('DecisionId'=>array(($postParam['requestDecId']))));

						$reqEditSelect = $sql->select();
						$reqEditSelect->from(array('a'=>'VM_RequestTrans'))
									->columns(array(new Expression("a.RequestTransId,a.RequestId,a.ResourceId, a.ItemId, CAST(0 As Decimal(18,5)) AS [IndentApproveQty], CAST(0 As Decimal(18,5)) AS [TransferApproveQty],
													CAST(0 As Decimal(18,5)) AS [ProductionApproveQty], CAST(0 As Decimal(18,5)) AS [HireApproveQty],
													CAST(0 As Decimal(18,5)) AS [HideIApproveQty], CAST(0 As Decimal(18,5)) AS [HideTApproveQty],
													CAST(0 As Decimal(18,5)) AS [HidePApproveQty], CAST(0 As Decimal(18,5)) AS [HideHApproveQty]")))
									->where(array('a.RequestId'=> explode(',',$this->bsf->isNullCheck($postParam['reqId'],'number')), 'a.ResourceId'=>$rs['ResourceId']))
									->where->notIn('a.RequestTransId', $transSelect);			
						$reqEditSelect->combine($reqInsertSelect,'Union ALL');	

						$reqSelect = $sql->select();
						$reqSelect->from(array('g'=>$reqEditSelect))
								->join(array('a'=>'VM_RequestTrans'), 'g.RequestTransId=a.RequestTransId', 
											array( 'ReqDate' => new Expression('Convert(varchar(10),ReqDate,105)'), 'Quantity', 
											'BalQty' => new Expression('(a.Quantity-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty))')),
											$reqSelect::JOIN_INNER)
								->join(array('b'=>'VM_RequestRegister'), 'a.RequestId=b.RequestId', array('RequestNo', 'CostCentreId', 'RApprove'), $reqSelect::JOIN_INNER)
								->join(array('c'=>'WF_OperationalCostCentre'), 'b.CostCentreId=c.CostCentreId', array('CostCentreName'), $reqSelect::JOIN_LEFT)
								->join(array('d'=>'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('ResourceName'), $reqSelect::JOIN_INNER)
								->join(array('e'=>'Proj_UOM'), 'd.UnitId=e.UnitId ', array('UnitName'), $reqSelect::JOIN_LEFT);


						 $reqStatement = $sql->getSqlStringForSqlObject($reqSelect);
						$reqResult = $dbAdapter->query($reqStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();									
						/*wbs*/
						foreach($reqResult as $req){
							$selectDecs2 = $sql->select(); 
							$selectDecs2->from(array("a"=>'VM_ReqDecQtyAnalTrans'))
										->columns(array(new Expression("a.ReqAHTransId RequestAHTransId, [b].[AnalysisId] AS [AnalysisId],[b].[ReqTransId] AS [ReqTransId],
											 [b].[ResourceId] AS [ResourceId], [b].[ItemId] AS [ItemId], CAST(b.ReqQty As Decimal(18,5)) AS [Quantity], 
											 CAST(b.ReqQty-(b.IndentApproveQty+b.TransferApproveQty+b.HireApproveQty+b.ProductionApproveQty) As Decimal(18,5)) AS [BalQty], 
											 CAST(a.IndentQty As Decimal(18,5)) AS [IndentApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [TransferApproveQty], 
											 CAST(a.ProductionQty As Decimal(18,5)) AS [ProductionApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HireApproveQty], 
											 CAST(a.IndentQty As Decimal(18,5)) AS [HiddenIApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [HiddenTApproveQty], 
											 CAST(a.ProductionQty As Decimal(18,5)) AS [HiddenPApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HiddenHApproveQty]")))
										->join(array("b"=>"VM_RequestAnalTrans"), "a.ReqAHTransId=b.RequestAHTransId", array(), $selectDecs2::JOIN_INNER)
										->join(array("c"=>"VM_ReqDecQtyTrans"), "a.DecisionId=c.DecisionId and a.ReqTransId=c.ReqTransId", array(), $selectDecs2::JOIN_INNER)
										->where(array('c.DecisionId'=>array($this->bsf->isNullCheck($postParam['requestDecId'],'number')),
											'c.ReqTransId'=>array($req['RequestTransId'])));			

							$selectDecs1 = $sql->select(); 
							$selectDecs1->from(array("a"=>"VM_ReqDecQtyAnalTrans"))
										->columns(array("ReqAHTransId"))
										->where(array('a.DecisionId'=>array($this->bsf->isNullCheck($postParam['requestDecId'],'number')),
							'a.ReqTransId'=>array($req['RequestTransId'])  ));

							$selectDecs3 = $sql->select(); 
							$selectDecs3->from(array("a"=>'VM_RequestAnalTrans'))
										->columns(array(new Expression("a.RequestAHTransId,a.AnalysisId, [a].[ReqTransId] AS [ReqTransId],
											 [a].[ResourceId] AS [ResourceId], [a].[ItemId] AS [ItemId], CAST(a.ReqQty As Decimal(18,5)) AS [Quantity], 
											 CAST(a.ReqQty-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty) As Decimal(18,5)) AS [BalQty], 
											 CAST(0 As Decimal(18,5)) AS [IndentApproveQty], CAST(0 As Decimal(18,5)) AS [TransferApproveQty], 
											 CAST(0 As Decimal(18,5)) AS [ProductionApproveQty], CAST(0 As Decimal(18,5)) AS [HireApproveQty], 
											 CAST(0 As Decimal(18,5)) AS [HiddenIApproveQty], CAST(0 As Decimal(18,5)) AS [HiddenTApproveQty], 
											 CAST(0 As Decimal(18,5)) AS [HiddenPApproveQty], CAST(0 As Decimal(18,5)) AS [HiddenHApproveQty]")))
										->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array(), $selectDecs3::JOIN_INNER)
										->where(array('a.ReqTransId'=>array($req['RequestTransId'])))
										->where->notIn('a.RequestAHTransId',$selectDecs1);				
							$selectDecs3->combine($selectDecs2,'Union ALL');

							$decSelect = $sql->select(); 
							$decSelect->from(array("g"=>$selectDecs3))
									->columns(array("*"),array("WbsName"))
									->join(array("d"=>"Proj_WBSMaster"), "g.AnalysisId=d.WBSId", array("WbsName"), $decSelect::JOIN_INNER);
							
							$wbsStatement = $sql->getSqlStringForSqlObject($decSelect);
							$req['wbsResults'] = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							array_push($rs['request'], $req);
						}
						array_push($resp, $rs);
					}
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			//$response->setContent($DecisionStatement);
			return $response;
		}
		else if($request->isPost()){
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParam = $request->getPost();
			//echo $postParam['totTrans'];die;
			try{
				$requestDecId = $this->bsf->isNullCheck($postParam['decisionId'],'number');
				$sql = new Sql($dbAdapter);
				//delete MultiRequest
				$subQuery   = $sql->delete();
				$subQuery->from("VM_ReqDecTrans")
						->where(array('DecisionId'=>$requestDecId));
				$DelMultiReqTransStatement = $sql->getSqlStringForSqlObject($subQuery);
				$register1 = $dbAdapter->query($DelMultiReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//delete RequestDecWBSTrans
				$select = $sql->delete();
				$select->from("VM_ReqDecQtyAnalTrans")
							->where(array('DecisionId'=>$requestDecId));						
				$DelReqDecTransStatement = $sql->getSqlStringForSqlObject($select);
				$register2 = $dbAdapter->query($DelReqDecTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//delete RequestDecTrans
				$select = $sql->delete();
				$select->from("VM_ReqDecQtyTrans")
							->where(array('DecisionId'=>$requestDecId));						
				$DelReqDecWBSTransStatement = $sql->getSqlStringForSqlObject($select);
				$register3 = $dbAdapter->query($DelReqDecWBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//update VM_RequestDecision
				$select = $sql->update();
				$select->table('VM_RequestDecision');
				$select->set(array(
					'DecDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParam['decision_date'],'date'))),
					'RequestType' => $this->bsf->isNullCheck($postParam['decision_type'],'string'),
					'RDecisionNo' => $this->bsf->isNullCheck($postParam['VoucherNo'],'string')
				 ));
				$select->where(array('RequestId'=>$requestDecId));			
				$registerStatement = $sql->getSqlStringForSqlObject($select);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				$request_id = explode(",", $postParam['requestId']);
				foreach($request_id as $reqd){				
					//Multiple Request
					$requestMultiInsert = $sql->insert('VM_ReqDecTrans');
					$requestMultiInsert->values(array(
                        "DecisionId"=>$requestDecId,
                        "RequestId"=>$reqd
                    ));
					$requestMultiStatement = $sql->getSqlStringForSqlObject($requestMultiInsert);
					$requestMultiResults = $dbAdapter->query($requestMultiStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				$data = json_decode($postParam['totTrans'], true);
				$proType='ProductionApproveQty';
				$proTypecolname='ProductionQty';
				if($postParam['decision_type'] == 3){
					$proType = 'HireApproveQty';
					$proTypecolname='HireQty';
				}
				foreach($data as $resId){
					foreach($resId['reqTransId'] as $reqTransId){
						$transId=$reqTransId['reqTransId'];
						//update RequestTrans
						$select = $sql->update();
						$select->table('VM_RequestTrans');
						$select->set(array(
							'IndentApproveQty' => new Expression('IndentApproveQty -'.$this->bsf->isNullCheck($postParam['poQtyH_'.$transId],'number')),
							'TransferApproveQty' => new Expression('TransferApproveQty -'.$this->bsf->isNullCheck($postParam['transferH_'.$transId],'number')),
							//'HireApproveQty' => 'HireApproveQty +'.$postParam['quantity_'.$transId],
							$proType => new Expression('ProductionApproveQty -'.$this->bsf->isNullCheck($postParam['productionH_'.$transId],'number'))
						 ));
						$select->where(array('RequestTransId'=>$transId));						
						$requestHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
						$requestHiddenUpdateResults = $dbAdapter->query($requestHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						$select = $sql->update();
						$select->table('VM_RequestTrans');
						$select->set(array(
							'IndentApproveQty' => new Expression('IndentApproveQty +'.$this->bsf->isNullCheck($postParam['poQty_'.$transId],'number')),
							'TransferApproveQty' => new Expression('TransferApproveQty +'.$this->bsf->isNullCheck($postParam['transfer_'.$transId],'number')),
							//'HireApproveQty' => 'HireApproveQty +'.$postParam['quantity_'.$transId],
							$proType => new Expression('ProductionApproveQty +'.$this->bsf->isNullCheck($postParam['production_'.$transId],'number'))
						 ));
						$select->where(array('RequestTransId'=>$transId));
						$requestupdateStatement = $sql->getSqlStringForSqlObject($select);
						$requestUpdateResults = $dbAdapter->query($requestupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						//ReqDecTrans
						$requestInsert = $sql->insert('VM_ReqDecQtyTrans');
						$requestInsert->values(array(
                            "DecisionId"=>$requestDecId,
                            "ReqTransId"=>$transId,
						    "IndentQty"=>$this->bsf->isNullCheck($postParam['poQty_'.$transId], 'number'),
                            "TransferQty"=>$this->bsf->isNullCheck($postParam['transfer_'.$transId],'number'),
						//"HireQty"=>$postParam['remarks_'.$transId],
						$proTypecolname=>$postParam['production_'.$transId]));
						$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
						$requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						$requestDecTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

						//Filter ReqTransId=" + transId + "
						foreach($reqTransId['reqAHTransId'] as $reqAHTransId){
							//update RequestAnalTrans
							$select = $sql->update();
							$select->table('VM_RequestAnalTrans');
							$select->set(array(
								'IndentApproveQty' => new Expression('IndentApproveQty -'.$this->bsf->isNullCheck($postParam['poWbsQtyH_'.$reqAHTransId],'number')),
								'TransferApproveQty' => new Expression('TransferApproveQty -'.$this->bsf->isNullCheck($postParam['transferWbsH_'.$reqAHTransId],'number')),
								//'HireApproveQty' => 'HireApproveQty +'.$postParam['wbsQuantity_'.$reqAHTransId],
								$proType => new Expression('ProductionApproveQty -'.$this->bsf->isNullCheck($postParam['productionWbsH_'.$reqAHTransId],'number'))
							 ));
							$select->where(array('RequestAHTransId'=>$reqAHTransId));
							$requestAnalHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
							$requestAnalHiddenUpdateResults = $dbAdapter->query($requestAnalHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

							$select = $sql->update();
							$select->table('VM_RequestAnalTrans');
							$select->set(array(
								'IndentApproveQty' => new Expression('IndentApproveQty +'.$this->bsf->isNullCheck($postParam['poWbsQty_'.$reqAHTransId],'number')),
								'TransferApproveQty' => new Expression('TransferApproveQty +'.$this->bsf->isNullCheck($postParam['transferWbs_'.$reqAHTransId],'number')),
								//'HireApproveQty' => 'HireApproveQty +'.$postParam['wbsQuantity_'.$reqAHTransId],
								$proType => new Expression('ProductionApproveQty +'.$this->bsf->isNullCheck($postParam['productionWbs_'.$reqAHTransId],'number'))
							 ));
							$select->where(array('RequestAHTransId'=>$reqAHTransId));				
							$requestAnalupdateStatement = $sql->getSqlStringForSqlObject($select);
							$requestAnalUpdateResults = $dbAdapter->query($requestAnalupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


							//ReqDecAnalTrans
							$requestTransInsert = $sql->insert('VM_ReqDecQtyAnalTrans');
							$requestTransInsert->values(array(
                                "TransId"=>$requestDecTransId,
                                "DecisionId"=>$requestDecId,
							    "ReqAHTransId"=>$reqAHTransId,
                                "ReqTransId"=>$transId,
							    "IndentQty"=>$this->bsf->isNullCheck($postParam['poWbsQty_'.$reqAHTransId],'number'),
                                "TransferQty"=>$this->bsf->isNullCheck($postParam['transferWbs_'.$reqAHTransId],'number'),
							$proTypecolname=>$postParam['productionWbs_'.$reqAHTransId]
							//"HireQty"=>$postParam['wbsQuantity_'.$rid."_".$wbsData['WbsId']]
							));

							$requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
							$requestTransResults = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);					
						}
					}
				}					
				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'Request-Decision-Modify','E','Request-Decision',$requestDecId,0,0,'Vendor',$this->bsf->isNullCheck($postParam['VoucherNo'],'string'),$this->auth->getIdentity()->UserId,0,0);
				//$this->redirect()->toRoute('ats/default', array('controller' => 'decision','action' => 'register'));
				$this->redirect()->toRoute('ats/detailed', array('controller' => 'decision','action' => 'detailed','decisionid' => $requestDecId));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}

		$requestDecId = $this->params()->fromRoute('decisionid');
		$select1 = $sql->select(); 
		$select1->from(array("a"=>"VM_RequestDecision"))
			->where(array('DecisionId' => $requestDecId ));				
		$decisionStatement = $sql->getSqlStringForSqlObject($select1);
		$decisionResult = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$resSelect = $sql->select(); 
		$resSelect->from(array("a"=>"VM_RequestTrans"))
			->columns(array('ResourceId'))
			->join(array("a1"=>"VM_ReqDecQtyTrans"), "a.RequestTransId=a1.ReqTransId", array(), $resSelect::JOIN_INNER)
			->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resSelect::JOIN_INNER)
			->where(array('a1.DecisionId'=>$requestDecId));
		$resSelect->order("b.ResourceName");
		$resStatement = $sql->getSqlStringForSqlObject($resSelect);
		$resResult = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		if(count($decisionResult) == 0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'decision','action' => 'register'));
		}
		else{
			$select1 = $sql->select(); 
			$select1->from(array("a"=>"VM_ReqDecTrans"))
				->columns(array("RequestId","Sel"=>new Expression("1")),array('RequestDate'=>new Expression("Convert(varchar(10),b.RequestDate,105)"),'RequestNo',"Approve"=>new Expression("CASE WHEN b.Approve='Y' THEN 'Yes' 
							 WHEN b.Approve='P' THEN 'Partial' 
							 Else 'No' END")), array("CostCentreName"))
				->join(array("b"=>new Expression("VM_RequestRegister")), "a.RequestId=b.RequestId", array('RequestDate'=>new Expression("Convert(varchar(10),b.RequestDate,105)"),'RequestNo',"Approve"=>new Expression("CASE WHEN Approve='Y' THEN 'Yes' 
							 WHEN Approve='P' THEN 'Partial' 
							 Else 'No' END")), $select1::JOIN_INNER)
				->join(array("c"=>new Expression("WF_OperationalCostCentre")), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $select1::JOIN_LEFT)
				->where('a.DecisionId = '.$requestDecId);

			$Subselect2= $sql->select();
			$Subselect2->from("VM_ReqDecTrans")
				 ->columns(array("RequestId"))
				 ->where('DecisionId='. $requestDecId);

			$select2 = $sql->select(); 
			$select2->from(array("a"=>"VM_RequestRegister"))
				->columns(array('RequestId',"Sel"=>new Expression("1-1"), 'RequestDate'=>new Expression("Convert(varchar(10),a.RequestDate,105)"),'RequestNo',"Approve"=>new Expression("CASE WHEN a.Approve='Y' THEN 'Yes' 
						 WHEN a.Approve='P' THEN 'Partial' 
						 Else 'No' END")), array("CostCentreName"))
				->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array("CostCentreName"), $select2::JOIN_LEFT)				
				->where(array('a.RequestType'=>$decisionResult[0]['RequestType'],'a.DeleteFlag'=>0 ))
				->where->notIn('a.RequestId',$Subselect2);
			$select2->where("a.RequestDate <= '".Date('m-d-Y', strtotime($decisionResult[0]['DecDate']))."'");

			$select2->combine($select1,'Union ALL');			
			$pickListStmt = $sql->getSqlStringForSqlObject($select2);
			$allRequest = $dbAdapter->query($pickListStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			$resourceListSelect = $sql->select();
			$resourceListSelect->from(array("a"=>"VM_RequestTrans"))
				->columns(array('ResourceId'))
				->join(array("b"=>"VM_ReqDecQtyTrans"), "a.RequestTransId=b.ReqTransId", array(), $resourceListSelect::JOIN_INNER)
				->join(array("c"=>"Proj_Resource"), "a.ResourceId=c.ResourceId", array("Code","ResourceName"), $resourceListSelect::JOIN_INNER)
				->join(array("d"=>"Proj_UOM"), "c.UnitId=d.UnitId", array("UnitName"), $resourceListSelect::JOIN_LEFT)
				->where(array('b.DecisionId'=>$requestDecId))
				->group(array("a.ResourceId","c.Code","c.ResourceName","d.UnitName"));

			$mostresourceListStatement = $sql->getSqlStringForSqlObject($resourceListSelect);
			$resourceResult = $dbAdapter->query($mostresourceListStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$resp = array();
			foreach($resourceResult as $decData){
				$decData['request'] = array();
				//Trans
				$Subselect2= $sql->select();
				$Subselect2->from("VM_ReqDecTrans")
						 ->columns(array("RequestId"))
						 ->where('DecisionId='. $requestDecId);
				$select1 = $sql->select(); 
				$select1->from(array("a"=>"VM_RequestTrans"))
					->columns(array('RequestTransId',"Sel"=>new Expression("1"), 'ReqDate'=>new Expression("Convert(varchar(10),ReqDate,105)"), 'ResourceId','ItemId','Quantity',
					"BalQty"=>new Expression("(a.Quantity-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty))")
					 ),
					array("RequestNo","CostCentreId","RApprove"),array("CostCentreName"),array("ResourceName"),array("UnitName"),array("RequestId")
					,array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
					"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),				
					"HideIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"HideTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
					"HidePApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HideHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") )
					)
					->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("RequestNo","CostCentreId","RApprove"), $select1::JOIN_INNER)
					->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $select1::JOIN_LEFT)
					->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("ResourceName"), $select1::JOIN_INNER)
					->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
					->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
					->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
					"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),				
					"HideIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"),"HideTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
					"HidePApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),"HideHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ), $select1::JOIN_INNER);
					//->where->In('a.RequestId',$Subselect2);
					$select1->where(array('g.DecisionId'=>$requestDecId, "a.ResourceId"=>$decData['ResourceId']));

				$decisionStatement = $sql->getSqlStringForSqlObject($select1);
				$decisionTransResult = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($decisionTransResult as $data){				
					//WBSTrans
					$select1 = $sql->select(); 
					$select1->from(array("a"=>"VM_RequestAnalTrans"))
						->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',
						"Quantity"=>new Expression("CAST(a.ReqQty As Decimal(18,5))"),
						"BalQty"=>new Expression("CAST(a.ReqQty-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty) As Decimal(18,5))")),
						array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),
						array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
						"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
						"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
						"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),
						"HiddenIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
						"HiddenTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
						"HiddenPApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
						"HiddenHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ) )
						->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select1::JOIN_INNER)
						->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select1::JOIN_INNER)
						->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WbsName"=>"WBSName"), $select1::JOIN_INNER)
						->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
						->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array("IndentApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
						"TransferApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
						"ProductionApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
						"HireApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))"),
						"HiddenIApproveQty"=>new Expression("CAST(g.IndentQty As Decimal(18,5))"), 
						"HiddenTApproveQty"=>new Expression("CAST(g.TransferQty As Decimal(18,5))"), 
						"HiddenPApproveQty"=>new Expression("CAST(g.ProductionQty As Decimal(18,5))"),
						"HiddenHApproveQty"=>new Expression("CAST(g.HireQty As Decimal(18,5))") ), $select1::JOIN_INNER)
						->where->In('c.RequestId',$Subselect2);
					$select1->where(array('f.DecisionId'=>$requestDecId ,
									'c.RequestType'=>$decisionResult[0]['RequestType'],
									'a.ReqTransId'=>$data['RequestTransId'] ));

					$decisionStatement = $sql->getSqlStringForSqlObject($select1);
					$data['wbsResults'] = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					array_push($decData['request'], $data);
				}
				array_push($resp, $decData);
			}
		}

		//echo json_encode($resp);die;
		$this->_view->genType = $vNo["genType"];	
		$this->_view->requestDecId =$requestDecId;
		$this->_view->decisionResult = $decisionResult;
		$this->_view->resResult = $resResult;
		$this->_view->allRequest = $allRequest;
		$this->_view->resp = json_encode($resp);
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;		
	}

    public function deleteAction(){
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
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $status='';
                    $decId = $this->params()->fromPost('decId');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    //RequestAnalTrans Update
                    $reqDecAnal = $sql->select();
                    $reqDecAnal->from('VM_ReqDecQtyAnalTrans')
                        ->where("DecisionId=".$decId);
                    $reqDecAnalStatement = $sql->getSqlStringForSqlObject($reqDecAnal);
                    $reqAnal = $dbAdapter->query($reqDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($reqAnal)>0)
                    {
                        foreach($reqAnal as $rq)
                        {
                            $updateDecAnal=$sql->update();
                            $updateDecAnal->table('VM_RequestAnalTrans')
                                          ->set(array('IndentApproveQty' => new expression('IndentApproveQty -'.$rq['IndentQty']),'TransferApproveQty'=> new expression('TransferApproveQty -'.$rq['TransferQty']),'HireApproveQty'=> new expression('HireApproveQty -'.$rq['HireQty']),'ProductionApproveQty'=> new expression('ProductionApproveQty -'.$rq['ProductionQty']), ) )
                             ->where(array("RequestAHTransId"=>$rq['ReqAHTransId']));
                         $updateDecAnal = $sql->getSqlStringForSqlObject($updateDecAnal);
                         $updateDecAnalresult = $dbAdapter->query($updateDecAnal, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    //RequestTrans Update
                    $reqDecTran = $sql->select();
                    $reqDecTran->from('VM_ReqDecQtyTrans')
                               ->where("DecisionId=".$decId);
                    $reqDecTranStatement = $sql->getSqlStringForSqlObject($reqDecTran);
                    $reqTran = $dbAdapter->query($reqDecTranStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($reqTran)>0)
                    {
                        foreach($reqTran as $rt)
                        {
                            $updateDecTran=$sql->update();
                            $updateDecTran->table('VM_RequestTrans')
                                ->set(array('IndentApproveQty' => new expression('IndentApproveQty -'.$rt['IndentQty']),'TransferApproveQty' => new expression('TransferApproveQty -'.$rt['TransferQty']),'HireApproveQty' => new expression('HireApproveQty -'.$rt['HireQty']),'ProductionApproveQty' => new expression('ProductionApproveQty -'.$rt['ProductionQty']) ))
                                ->where(array("RequestTransId"=>$rt['ReqTransId']));
                            $updateDecTran = $sql->getSqlStringForSqlObject($updateDecTran);
                            $dbAdapter->query($updateDecTran, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $deleteDecAnal = $sql->delete();
                    $deleteDecAnal->from('VM_ReqDecQtyAnalTrans')
                        ->where('DecisionId='.$decId);
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteDecAnal);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $deleteDecQtyTran = $sql->delete();
                    $deleteDecQtyTran->from('VM_ReqDecQtyTrans')
                        ->where('DecisionId='.$decId);
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteDecQtyTran);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $deleteDecTran = $sql->delete();
                    $deleteDecTran->from('VM_ReqDecTrans')
                        ->where('DecisionId='.$decId);
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteDecTran);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $deleteDec = $sql->update();
                    $deleteDec->table('VM_RequestDecision')
                        ->set(array('DeleteFlag'=>1))
                        ->where(array("DecisionId"=>$decId));
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteDec);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $status='Deleted';
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

}
