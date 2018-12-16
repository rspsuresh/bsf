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
use MMS\View\Helper\MMSHelper;

class IndexController extends AbstractActionController
{
	public function __construct(){
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
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		$select = $sql->select();
		$select->from('Proj_ResourceType')
				->columns(array("data"=>"TypeId", "value"=>"TypeName"));
		$statement = $sql->getSqlStringForSqlObject($select);
		$results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->results = $results;

		return $this->_view;
    }

	public function resourceAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		$vNo = CommonHelper::getVoucherNo(201,date('Y/m/d') ,0,0, $dbAdapter,"");

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			if ($request->isPost()){
				//Write your Ajax post code here
				$resp =  array();
				$postData = $request->getPost();
                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                if($mode == 'firstStep'){
					if($postData['request_type'] == "Material"){
						$typeId = 2;
					}
					$select = $sql->select();
					$select->from(array('a' => 'Proj_Resource'))
							->columns(array(new Expression("CAST(a.ResourceId As Varchar)+'_'+CAST(isnull(d.BrandId,0) As Varchar) As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName ") ))
							->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
							->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'c.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
							->where("a.TypeId = $typeId and e.CostCentreId =".$postData['project_name'] );

                    $statement = $sql->getSqlStringForSqlObject($select);
					$resp['first'] =  $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


					//Most used Resource
					$mostSelectSub = $sql->select();
					$mostSelectSub->from(array("a"=>"Proj_Resource"))
								->columns(array("ResourceId", "Code", "ResourceName", "CNT"=>new Expression("COUNT(1)")))
								->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $mostSelectSub::JOIN_LEFT)
								->join(array("b"=>"VM_RequestTrans"), "a.ResourceId=b.ResourceId", array(), $mostSelectSub::JOIN_INNER)
                                ->join(array("d"=>"Proj_ProjectResource"),"a.ResourceId=d.ResourceId",array(),$mostSelectSub::JOIN_INNER)
                                ->join(array('e' => 'WF_OperationalCostCentre'),'d.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
								->group(array("a.ResourceId", "a.Code", "a.ResourceName", "c.ResourceGroupName"))
								->where("a.TypeId = ".$typeId." and e.CostCentreId=".$postData['project_name']);
					$mostSelect = $sql->select();
					$mostSelect->from(array("g"=>$mostSelectSub))
								->order("g.CNT desc");

					$mostStatement = $sql->getSqlStringForSqlObject($mostSelect);
					$resp['second'] = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					//For Last 1 months Resource
					$recentSelectSub = $sql->select();
					$recentSelectSub->from(array("a"=>"Proj_Resource"))
									->columns(array("ResourceId", "Code", "ResourceName", "CNT"=>new Expression("COUNT(1)")))
									->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $recentSelectSub::JOIN_LEFT)
									->join(array("b"=>"VM_RequestTrans"), "a.ResourceId=b.ResourceId", array(), $recentSelectSub::JOIN_INNER)
									->join(array("d"=>"VM_RequestRegister"), "b.RequestId=d.RequestId", array(), $recentSelectSub::JOIN_INNER)
                                    ->join(array("e"=>"Proj_ProjectResource"),"a.ResourceId=e.ResourceId",array(),$recentSelectSub::JOIN_INNER )
                                    ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER);
					$recentSelectSub->where("d.CreatedDate BETWEEN (Convert(nvarchar(12),DATEADD(MONTH, -1, GETDATE()), 113)) AND (Convert(nvarchar(12),DATEADD(dd, 1, GETDATE()), 113)) and a.TypeId = ".$typeId)
									->group(array("a.ResourceId", "a.Code", "a.ResourceName", "c.ResourceGroupName"));

						$recentSelect = $sql->select();
						$recentSelect->from(array("g"=>$recentSelectSub))
									->order("g.CNT desc");
					 $recentStatement = $sql->getSqlStringForSqlObject($recentSelect);
					$resp['third'] = $dbAdapter->query($recentStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

				}
				else if($mode == 'pickList'){

                    $postData = $request->getPost();
                    $resourceItem=explode('_',$this->bsf->isNullCheck($postData['rid'],'string'));
                    $resourceId = $resourceItem[0];
                    $itemId = $resourceItem[1];
					$select = $sql->select();
					$select->from(array('a' => 'Proj_Resource'))
							//->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,b.ResourceGroupName,b.ResourceGroupId,c.UnitName,c.UnitId")))
							->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName", "ResourceGroupId"), $select:: JOIN_LEFT)
							->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                            ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                            ->Join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
					        ->where("a.ResourceId=$resourceId And isnull(d.BrandId,0)=$itemId And f.CostCentreId=".$postData['ccid']);
                    $statement = $sql->getSqlStringForSqlObject($select);
					$resp['results'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$wbsSelect = $sql->select();
					$wbsSelect->from(array('a'=>'Proj_WBSMaster'))
							->columns(array("WbsId"=>"WBSId", "WbsName"=>"WBSName"))
							->join(array('b' => 'Proj_WBSMaster'), 'a.ParentID=b.WBSId', array("PLevel1"=>"WBSName"), $select:: JOIN_LEFT)
							->join(array('c' => 'Proj_WBSMaster'), 'b.ParentID=c.WBSId', array("PLevel2"=>"WBSName"), $select:: JOIN_LEFT)
							->join(array('d' => 'Proj_WBSMaster'), 'c.ParentID=d.WBSId', array("PLevel3"=>"WBSName"), $select:: JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'a.ProjectId=e.ProjectId')
							->where(array("a.LastLevel"=>"1","e.CostCentreId"=>$postData['ccid']));

					$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
					$resp['wbsResults'] = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
				else if($mode == 'editPickList'){
					$materialSelect = $sql->select();
					$materialSelect->from('Proj_ResourceType')
							->columns(array("data"=>"TypeId", "value"=>"TypeName"));
					$materialStatement = $sql->getSqlStringForSqlObject($materialSelect);
					$materialResults   = $dbAdapter->query($materialStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$selectVendor = $sql->select();
					$selectVendor->from(array("a"=>"VM_RequestRegister"));
					$selectVendor->columns(array("RequestNo", "RequestId", "RequestDate"=>new Expression("Convert(varchar(10),a.RequestDate,105)"), "Priority"))
								->join(array("b"=>"Proj_ResourceType"), "b.TypeId=a.RequestType", array("TypeId", "TypeName", "TypeCode"), $selectVendor::JOIN_LEFT)
								->join(array("c"=>'VM_RequestTrans'), "c.RequestId=a.RequestId", array("RequestTransId", "Quantity", "Remarks"), $selectVendor::JOIN_LEFT)
								->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitName", "UnitId"), $selectVendor::JOIN_LEFT)
								->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array("ResourceId", "Code", "ResourceName"), $selectVendor::JOIN_LEFT);
					$selectVendor->where(array("c.ResourceId"=>explode(',', $this->bsf->isNullCheck($postData['rid'],'number')), "a.RequestId"=> $this->bsf->isNullCheck($postData['request_id'],'number')));
					$statement = $sql->getSqlStringForSqlObject($selectVendor);
					$resp['results'] = $vendorResults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					foreach($vendorResults as $data){
						$selectAnal = $sql->select();
						$selectAnal->from(array("a"=>"VM_RequestAnalTrans"))
									->columns(array("ReqQty"), array("WbsId", "WbsName"))
									->join(array("f"=>"Proj_WBSMaster"), "f.WBSId=a.AnalysisId", array("WbsId"=>"WBSId", "WbsName"=>"WBSName"), $selectAnal::JOIN_LEFT);
						$selectAnal->where(array("a.ReqTransId"=>$data['RequestTransId'], "a.ResourceId" => explode(',', $this->bsf->isNullCheck($postData['rid'],'number'))));
						
						$statement1 = $sql->getSqlStringForSqlObject($selectAnal);
						$resp['wbsResults'] = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					}
				}
				else if($mode == 'voucherValid'){
					$select = $sql->select();		
					$select->from(array('a' => 'VM_RequestRegister'))
						->columns(array('RequestNo'))
						->where(array('a.RequestNo'=>trim($this->bsf->isNullCheck($postData['voucherno'],'string'))));
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();						
				}
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		}
		else if($request->isPost()){
			$postData = $request->getPost();
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				if($vNo['genType'])
                {
					$voucher = CommonHelper::getVoucherNo(201,date('Y/m/d', strtotime($postData['project_date'])) ,0,0, $dbAdapter,"I");
					$voucherNo = $voucher['voucherNo'];
				}
				else
                {
					$voucherNo = $postData['voucherNo'];
				}
				$registerInsert = $sql->insert('VM_RequestRegister');
				$registerInsert->values(array(
                    "RequestDate"=>date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['project_date'],'date'))),
                    "RequestType"=>$this->bsf->isNullCheck($postData['request_type'],'string'),
					"CostCentreId"=>$this->bsf->isNullCheck($postData['project_name'],'string'),
                    "RequestNo"=>$voucherNo,
                    "Approve"=>'N',
                    "Priority"=>$this->bsf->isNullCheck($postData['priority'],'string'),
                    "LSWithOutIOW"=>"1",
                    "CreatedDate"=>new Expression("getDate()"),
					"ModifiedDate"=>new Expression("getDate()"),
                    "Narration"=>$this->bsf->isNullCheck($postData['narration'],'string')
                    ));
				$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				$requestId = $dbAdapter->getDriver()->getLastGeneratedValue();
				/*Customize insert*/
				if($postData['customize'] == '1'){
					$custInsert = $sql->insert('VM_Customize');
					$custInsert->values(array("AllResource"=>(($postData['all_resource'])?'1':'0'), "SubgroupResource"=>(($postData['sub_group'])?'1':'0'),
											"SelectedWbs"=>(($postData['selected_wbs'])?'1':'0'), "AllDate"=>(($postData['all_remarks'])?'1':'0'),
											"AllRemarks"=>(($postData['all_date'])?'1':'0'), "RequestId"=>$requestId));

					$custStatement = $sql->getSqlStringForSqlObject($custInsert);
					$dbAdapter->query($custStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				$resource_id = explode(",", $postData['resourceId']);
				foreach($resource_id as $rid){
					$requestInsert = $sql->insert('VM_RequestTrans');
					$requestInsert->values(array(
                        "RequestId"=>$requestId,
                        "ResourceId"=>$rid,
					    "Quantity"=>$this->bsf->isNullCheck($postData['quantity_'.$rid],'number'),
                        "BalQty"=>$this->bsf->isNullCheck($postData['quantity_'.$rid],'number'),
                        "UnitId"=>$this->bsf->isNullCheck($postData['unitId_'.$rid],'number'),
					    "ReqDate"=>date('Y-m-d', strtotime($postData['requireddate_'.$rid])),
					    "Remarks"=>$this->bsf->isNullCheck($postData['remarks_'.$rid],'string'),
					    "Specification"=>$this->bsf->isNullCheck($postData['resspec_'.$rid],'string')
                        ));
					$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
					$requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

					$requestTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
					$wbsId = explode(",", $postData['all_wbsId']);
					foreach($wbsId as $wbsData) {
                        if ($postData['wbsQuantity_' . $rid . "_" . $wbsData] > 0)
                        {
                            $requestTransInsert = $sql->insert('VM_RequestAnalTrans');
                            $requestTransInsert->values(array(
                                "ReqTransId" => $requestTransId,
                                "AnalysisId" => $wbsData,
                                "ResourceId" => $rid,
                                "ReqQty" => $this->bsf->isNullCheck($postData['wbsQuantity_' . $rid . "_" . $wbsData],'number'),
                                "BalQty" =>$this->bsf->isNullCheck($postData['wbsQuantity_' . $rid . "_" . $wbsData], 'number'),
                                "UnitId" => $this->bsf->isNullCheck($postData['unitId_' . $rid],'number')
                            ));

                            $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                            $requestTransResults = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
					}
				}
				$connection->commit();
				//$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'resource'));
				$this->redirect()->toRoute('ats/request-detailed', array('controller' => 'index','action' => 'request-detailed','rid' => $requestId));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		$wbsSelect = $sql->select();
		$wbsSelect->from("Proj_WBSMaster")
				->columns(array("WbsId"=>"WBSId", "WbsName"=>"WBSName"))
				->where(array("LastLevel"=>"1"));

		$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
		$wbsResult = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
		$wbsSelect = $sql->select();
		$wbsSelect->from('Proj_ResourceType')
				->columns(array("data"=>"TypeId", "value"=>"TypeName"));
		$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
		$typeName = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$projSelect = $sql->select();
		$projSelect->from('WF_OperationalCostCentre')
				->columns(array("data"=>"CostCentreId", "value"=>"CostCentreName"));
		$projStatement = $sql->getSqlStringForSqlObject($projSelect);
		$proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$narrationSelect = $sql->select();
		$narrationSelect->from("WF_NarrationMaster")
				->columns(array("Description"=>"Description"))
				->where(array("TypeId"=>"201"));
		$narrationStatement = $sql->getSqlStringForSqlObject($narrationSelect);
		$narration = $dbAdapter->query($narrationStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->genType = $vNo["genType"];

		$this->_view->narration = $narration;

		if ($vNo["genType"] ==false){
            $this->_view->svNo = "";
        }
        else{
            $this->_view->svNo = $vNo["voucherNo"];
        }

		$this->_view->wbsResult = $wbsResult;
		$this->_view->projResults = $proResults;
		$this->_view->vNo = $vNo;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}
	public function displayRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;

        CommonHelper::CheckPowerUser($userId, $dbAdapter);
        if($viewRenderer->bPowerUser == false) {
            $rAns = CommonHelper::FindPermission($userId, 'Request-Modify', $dbAdapter);
        } else {
            $rAns = '';
        }
        $this->_view->rAns = $rAns;
        if($viewRenderer->bPowerUser == false) {
            $dAns = CommonHelper::FindPermission($userId, 'Request-Delete', $dbAdapter);
        } else {
            $dAns = '';
        }
        $this->_view->dAns = $dAns;

		$request = $this->getRequest();
		$response = $this->getResponse();
		if($request->isXmlHttpRequest()){
			if ($request->isPost()){
				
				$postParam = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'),'string');
                $unCheckedColumnNames = $this->bsf->isNullCheck($this->params()->fromPost('unCheckedColumnNames'),'string');
                $userId = $this->auth->getIdentity()->UserId;

                if($Type == 'updateColumn') {

                    $select = $sql->select();
                    $select->from('WF_GridColumnTrans')
                        ->where(array("FunctionName"=>'RequestRegister','UserId'=>$userId));
                    $statement = $sql->getSqlStringForSqlObject($select); 
                    $resCount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if($resCount != 0) {
                        //update
                        $update = $sql->update();
                        $update->table('WF_GridColumnTrans')
                            ->set(array('ColumnName'=>$unCheckedColumnNames))
                            ->where(array('FunctionName' =>'RequestRegister','UserId'=>$userId));
                        $stmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        //insert
                        $insert = $sql->insert();
                        $insert->into( 'WF_GridColumnTrans' )
                            ->values(array('FunctionName' => 'RequestRegister',
                                'UserId' => $userId,
                                'ColumnName' => $unCheckedColumnNames));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		}
		
		$userId = $this->auth->getIdentity()->UserId;
		$select = $sql->select();
		$select->from('WF_GridColumnTrans')
			->columns(array("ColumnName"))
			->where(array("FunctionName"=>'LeadRegister','UserId'=>$userId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$GridColumn = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->GridColumn = $GridColumn;
		
		//Pageload
		$selectActRequest = $sql->select();
		$selectActRequest->from(array("a"=>"VM_RequestRegister"));
		$selectActRequest->columns(array(new Expression("top 1 a.RequestNo,a.RequestId,a.CostCentreId as CostCentreId,
		            Convert(varchar(10),a.RequestDate,105) as RequestDate,RequestType as TypeName,
		            CASE WHEN a.Approve='Y' THEN 'Approved' Else 'Pending' END as ApproveReg")))
					->where(array('a.DeleteFlag'=>0))
					->order("a.CreatedDate Desc");
		$statement = $sql->getSqlStringForSqlObject($selectActRequest);
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		 
		$selectVendor = $sql->select();
		$selectVendor->from(array("a"=>"VM_RequestRegister"));
		$selectVendor->columns(array(new Expression("a.RequestId,a.RequestNo,a.CostCentreId as CostCentreId,
		                                    Convert(varchar(10),a.RequestDate,105) as RequestDate,RequestType as TypeName,
		                                    CASE WHEN a.Priority=1 THEN 'Low' WHEN a.Priority=2 THEN 'Medium' WHEN a.Priority=3 THEN 'High' END as priorityVal"),
                                            new Expression("CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No'
											END as ApproveReg")),array("CostCentreName"))
					->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $selectVendor::JOIN_LEFT)
					->where(array('a.DeleteFlag'=>0))
					->order("a.RequestId Desc");
		$statement = $sql->getSqlStringForSqlObject($selectVendor);
		$gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		 

		$this->_view->MaterialPer=CommonHelper::CalculatePercentageRequest(2,$dbAdapter);//Material
		$this->_view->LabourPer=CommonHelper::CalculatePercentageRequest(1,$dbAdapter);//Labour
		$this->_view->AssetPer=CommonHelper::CalculatePercentageRequest(3,$dbAdapter);//Asset
		$this->_view->ActivityPer=CommonHelper::CalculatePercentageRequest(4,$dbAdapter);//Activity

		$this->_view->RequestNo = $results['RequestNo'];
		$this->_view->RequestId = $results['RequestId'];
		$this->_view->RequestDate = $results['RequestDate'];
		$this->_view->TypeName = $results['TypeName'];
		$this->_view->Approve = $results['ApproveReg'];
		$this->_view->CostCentreId = $results['CostCentreId'];
		$this->_view->gridResult = $gridResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}	
	public function editResourceAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		$id = $this->params()->fromRoute('rid');
		/* if($id=="" || $id==null){
			$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'resource'));
		} */
		$vNo = CommonHelper::getVoucherNo(24,date('Y/m/d') ,0,0, $dbAdapter,"");
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){
				$postData = $request->getPost();
				$requestId = $postData['request_id'];
				if($postData['mode'] == 'firstStep'){
					$resp = array();
				}
				$response->setContent(json_encode($resp));
				return $response;				
			}
		}
		else if($request->isPost()){
			$postData = $request->getPost();
			$requestId = $this->bsf->isNullCheck($postData['requestId'],'number');
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$sql = new Sql($dbAdapter);
				//delete RequestTrans
				$subQuery   = $sql->select();
				$subQuery->from("VM_RequestTrans")
						->columns(array("RequestTransId"));
				$subQuery->where(array('RequestId'=>$requestId));
				
				$select = $sql->delete();
				$select->from('VM_RequestAnalTrans')
						->where->expression('ReqTransId IN ?',
								array($subQuery));


				$WBSTransStatement = $sql->getSqlStringForSqlObject($select);
				$register1 = $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//delete RequestTrans
				$select = $sql->delete();
				$select->from("VM_RequestTrans")
							->where(array('RequestId'=>$requestId));

				$ReqTransStatement = $sql->getSqlStringForSqlObject($select);
				$register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//update RequestRegister
				$select = $sql->update();
				$select->table('VM_RequestRegister');
				$select->set(array(
					'RequestDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['project_date'],date))),
					/* 'RequestType' => $postData['request_type'],
					'CostCentreId' => $postData['project_name'], */
					'RequestNo' => $this->bsf->isNullCheck($postData['voucherNo'],'string'),
					'Priority' => $this->bsf->isNullCheck($postData['priority'],'string'),
					'Narration' => $this->bsf->isNullCheck($postData['narration'],'string'),
					'LSWithOutIOW' => "1",
					'ModifiedDate' => new Expression("getDate()")
				 ));
				$select->where(array('RequestId'=>$requestId));

				$registerStatement = $sql->getSqlStringForSqlObject($select);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

					$resource_id = explode(",", $postData['resourceId']);
					foreach($resource_id as $rid){
						//Insert RequestTrans
						$requestInsert = $sql->insert('VM_RequestTrans');
						$requestInsert->values(array(
                            "RequestId"=>$requestId,
                            "ResourceId"=>$rid,
						    "Quantity"=>$this->bsf->isNullCheck($postData['quantity_'.$rid],'number'),
                            "BalQty"=>$this->bsf->isNullCheck($postData['quantity_'.$rid], 'number'),
                            "UnitId"=>$this->bsf->isNullCheck($postData['unitId_'.$rid],'number'),
						    "ReqDate"=>date('Y-m-d', strtotime($postData['requireddate_'.$rid])),
						    "Remarks"=>$this->bsf->isNullCheck($postData['remarks_'.$rid],'string'),
						    "Specification"=>$this->bsf->isNullCheck($postData['resspec_'.$rid],'string')
                            ));
						$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
						$requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						$requestTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

						$wbsId = explode(",", $postData['all_wbsId']);

						foreach($wbsId as $wbsData){

                            if($postData['wbsQuantity_'.$rid."_".$wbsData] != 0) {
                                //Insert RequestAnalTrans
                                $requestTransInsert = $sql->insert('VM_RequestAnalTrans');
                                $requestTransInsert->values(array(
                                    "ReqTransId" => $requestTransId,
                                    "AnalysisId" => $wbsData,
                                    "ResourceId" => $rid,
                                    "ReqQty" => $this->bsf->isNullCheck($postData['wbsQuantity_' . $rid . "_" . $wbsData],'number'),
                                    "UnitId" => $this->bsf->isNullCheck($postData['unitId_' . $rid],'number')
                                ));

                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                $requestTransResults = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
						}
					}
					$connection->commit();
					//$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));
					$this->redirect()->toRoute('ats/request-detailed', array('controller' => 'index','action' => 'request-detailed','rid' => $requestId));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}

		if(is_numeric($id)){
			$selectProject = $sql->select();
			$selectProject->from(array("a"=>"VM_RequestRegister"));
			$selectProject->columns(array("RequestNo", "RequestId", "RequestDate", "Priority", "Approve","Narration","RequestType"))
						->join(array("c"=>"WF_OperationalCostCentre"), "c.CostCentreId=a.CostCentreId", array("CostCentreId", "CostCentreName"), $selectProject::JOIN_LEFT)
						->join(array("d"=>"VM_Customize"), "d.RequestId=a.RequestId", array("CustomizeId", "AllResource", "SubgroupResource", "SelectedWbs", "AllRemarks", "AllDate"), $selectProject::JOIN_LEFT);
			
			$selectProject->where(array("a.RequestId"=>$id));
			$statement = $sql->getSqlStringForSqlObject($selectProject);
			$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			/*Wbs result*/
			$wbsSelect = $sql->select();
			$wbsSelect->from("Proj_WBSMaster")
					->columns(array("WbsId"=>"WBSId", "WbsName"=>"WBSName"))
					->where(array("LastLevel"=>"1"));

			$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
			$wbsResult = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			if(count($results) > 0){
				if($results[0]['Approve']=="Y"){
					echo"<script>alert('you cannot edit the Approved entry')</script>";die;
					$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));
				}
				
				$typeId = $results[0]['RequestType'];
				if($typeId == "Material" ){
					$typeId =2;
				}
				/*All resource Result*/
				$resourceSelect = $sql->select();
				$resourceSelect->from(array('a' =>"Proj_Resource"))
						->columns(array("ResourceId", "ResourceName", "Code"))
						->join(array('b' =>"VM_RequestTrans"), new Expression('a.ResourceId=b.ResourceId and b.RequestId='.$id), array("sel"=>new Expression("case When b.ResourceId <>0 Then 1 Else 0 END")), $resourceSelect::JOIN_LEFT)
						->join(array('c' =>"Proj_ResourceGroup"), 'a.ResourceGroupId=c.ResourceGroupId', array("ResourceGroupId","ResourceGroupName"), $resourceSelect::JOIN_LEFT)
						->where("a.TypeId = ".$typeId);
						
				$resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
				$allRes = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

				/*Most resource Result*/
				$mostSelectSub = $sql->select();
				$mostSelectSub->from(array("a"=>"Proj_Resource"))
							->columns(array("ResourceId", "Code", "ResourceName", "CNT"=>new Expression("COUNT(1)")))
							->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $mostSelectSub::JOIN_LEFT)
							->join(array("b"=>"VM_RequestTrans"), "a.ResourceId=b.ResourceId", array(), $mostSelectSub::JOIN_INNER)
							->group(array("a.ResourceId", "a.Code", "a.ResourceName", "c.ResourceGroupName"))
							->where("a.TypeId = ".$typeId);

				$mostSelect = $sql->select();
				$mostSelect->from(array("g"=>$mostSelectSub))
							->columns(array("*", "sel"=>new Expression("1-1")))
							->order("g.CNT desc");
				$mostStatement = $sql->getSqlStringForSqlObject($mostSelect);
				$mostRes = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

				/*Recent resource Result*/
				$recentSelectSub = $sql->select();
				$recentSelectSub->from(array("a"=>"Proj_Resource"))
								->columns(array("ResourceId", "Code", "ResourceName", "CNT"=>new Expression("COUNT(1)")))
								->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $recentSelectSub::JOIN_LEFT)
								->join(array("b"=>"VM_RequestTrans"), "a.ResourceId=b.ResourceId", array(), $recentSelectSub::JOIN_INNER)
								->join(array("d"=>"VM_RequestRegister"), "b.RequestId=d.RequestId", array(), $recentSelectSub::JOIN_INNER);
				$recentSelectSub->where("d.CreatedDate BETWEEN (Convert(nvarchar(12),DATEADD(MONTH, -1, GETDATE()), 113)) AND (Convert(nvarchar(12),DATEADD(dd, 1, GETDATE()), 113)) AND a.TypeId = ".$typeId)
								->group(array("a.ResourceId", "a.Code", "a.ResourceName", "c.ResourceGroupName"));


				$recentSelect = $sql->select();
				$recentSelect->from(array("g"=>$recentSelectSub))
							->columns(array("*", "sel"=>new Expression("1-1")))
							->order("g.CNT desc");
				$recentStatement = $sql->getSqlStringForSqlObject($recentSelect);
				$recentRes = $dbAdapter->query($recentStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


			
				$materialSelect = $sql->select();
				$materialSelect->from('Proj_ResourceType')
						->columns(array("data"=>"TypeId", "value"=>"TypeName"));
				$materialStatement = $sql->getSqlStringForSqlObject($materialSelect);
				$materialResults   = $dbAdapter->query($materialStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

				$selectVendor = $sql->select();
				$selectVendor->from(array("a"=>"VM_RequestRegister"));
				$selectVendor->columns(array("RequestDate", "RequestNo", "Priority","RequestType","priorityVal"=>new Expression("CASE WHEN a.Priority=1 THEN 'Low'
																		WHEN a.Priority=2 THEN 'Medium'
																		WHEN a.Priority=3 THEN 'High'
																END")))
							->join(array("c"=>'VM_RequestTrans'), "c.RequestId=a.RequestId", array("RequestTransId", "RequestId", "Quantity", "ReqDate", "Remarks", "DeleteFlag"), $selectVendor::JOIN_LEFT)
							->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitId", "UnitName", "UnitDescription"), $selectVendor::JOIN_LEFT)
							->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array("ResourceId", "Code", "ResourceName"), $selectVendor::JOIN_LEFT);
				$selectVendor->where(array("a.RequestId"=>$id));
                $statement = $sql->getSqlStringForSqlObject($selectVendor);
				$vendorResults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

				$resp = array();
				foreach($vendorResults as $data)
                {
                    $subQuery = $sql->select();
                    $subQuery->from(array("a" => "VM_RequestAnalTrans"))
                        ->join(array("f" => "Proj_WBSMaster"), "f.WBSId=a.AnalysisId", array(), $subQuery::JOIN_LEFT)
                    -> columns(array("WBSId"=>new Expression("f.WBSId")));

                    $subQuery->where(array("a.ReqTransId" => $data['RequestTransId']));

                    $selectAnal = $sql->select();
                    $selectAnal->from(array("a" => "VM_RequestAnalTrans"))
                        ->join(array("f" => "Proj_WBSMaster"), "f.WBSId=a.AnalysisId", array("WbsId" => "WBSId", "WbsName" => "WBSName"), $selectAnal::JOIN_LEFT);
                    $selectAnal->where(array("a.ReqTransId" => $data['RequestTransId']));

                    $wbsSelect = $sql->select();
                    $wbsSelect->from("Proj_WBSMaster")
                       ->columns(array("RequestAHTransId" => new Expression('1-1'),"ReqTransId"=> new Expression($data['RequestTransId']),"AnalysisId"=>"WBSId",
                           "ResourceId"=> new Expression($data['ResourceId']),"ItemId" => new Expression('1-1'),"ReqQty" => new Expression('1-1'),
                           "IndentApproveQty" => new Expression('1-1'),"TransferApproveQty" => new Expression('1-1'),"ProductionApproveQty" => new Expression('1-1'),
                           "BalQty" => new Expression('1-1'),"UnitId" => new Expression($data['UnitId']),"IndentQty" => new Expression('1-1'),
                           "TransferQty" => new Expression('1-1'),"CancelQty" => new Expression('1-1'),"TUnitId" => new Expression('1-1'),
                           "TQty" => new Expression('1-1'),"FFactor" => new Expression('1-1'),"TFactor" => new Expression('1-1'),"HireApproveQty" => new Expression('1-1'),
                           "WbsId"=>"WBSId","WbsName"=>"WBSName"))
                        ->where->expression('Proj_WBSMaster.WBSId Not IN ?', array($subQuery));

                   $wbsSelect->combine($selectAnal,'Union ALL');
                    $statement1 = $sql->getSqlStringForSqlObject($wbsSelect);
                    $data['second'] = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    array_push($resp, $data);
                }
			}
			else
            {
				$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'resource'));
			}
		}
		else{
			$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'resource'));
		}
		$narrationSelect = $sql->select();
		$narrationSelect->from("WF_NarrationMaster")
				->columns(array("Description"=>"Description"))
				->where(array("TypeId"=>"201"));
		$narrationStatement = $sql->getSqlStringForSqlObject($narrationSelect);
		$narration = $dbAdapter->query($narrationStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->narration = $narration;
		$this->_view->requestId = $id;
		$this->_view->wbsResult = $wbsResult;
		$this->_view->allRes = $allRes;
		$this->_view->mostRes = $mostRes;
		$this->_view->recentRes = $recentRes;
		$this->_view->genType = $vNo["genType"];
		$this->_view->projectResult = $results;	
		$this->_view->materialResults = $materialResults;
		$this->_view->response = json_encode($resp);
		$this->_view->wbsName = $resp[0]['second'];
		return $this->_view;
	}
    public function requestDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest())
        {
            if ($request->isPost())
            {
                $resp = array();
                //Write your Ajax post code here

                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        }
        $RequestId = $this->params()->fromRoute('requestId');
        //RequestAnalTrans
        $subQuery=$sql->select();
        $subQuery->from('VM_RequestTrans')
                 ->columns(array('RequestTransId'))
                 ->where("RequestId=$RequestId");

        $delete = $sql->delete();
        $delete->from('VM_RequestAnalTrans')
            ->where->expression('ReqTransId IN ?', array($subQuery));
        $DelStatement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $delete1 = $sql->delete();
        $delete1->from('VM_RequestIowTrans')
            ->where->expression('RequestTransId IN ?', array($subQuery));
        $DelStatement1 = $sql->getSqlStringForSqlObject($delete1);
        $dbAdapter->query($DelStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

        $delete2 = $sql->delete();
        $delete2->from('VM_RequestWbsTrans')
            ->where->expression('RequestTransId IN ?', array($subQuery));
        $delStatement2 = $sql->getSqlStringForSqlObject($delete2);
        $dbAdapter->query($delStatement2, $dbAdapter::QUERY_MODE_EXECUTE);



        //RequestTrans
        $deleteTrans = $sql->delete();
        $deleteTrans->from('VM_RequestTrans')
                    ->where("RequestId=$RequestId");
        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //Request
        $deleteReq = $sql ->update();
        $deleteReq -> table('VM_RequestRegister')
                   -> set(array('DeleteFlag'=>1))
                   -> where("RequestId=$RequestId");
        $DelStatement = $sql->getSqlStringForSqlObject($deleteReq);
        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
         $this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));
        return $this->_view;
    }
    public function requestDetailedAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$id = $this->params()->fromRoute('rid');
		$request = $this->getRequest();
		$response = $this->getResponse();

		if($request->isXmlHttpRequest()){
			$resp = array();
			if ($request->isPost()){
				
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
        $postParam = $request->getPost();
//        echo"<pre>";
//        print_r($postParam);
//        echo"</pre>";
//        die;
//        return;


		$select = $sql->select();
		$select->from('VM_RequestRegister')
			   ->columns(array('RequestId'))
			   ->where(array("RequestId"=>$id));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsReqVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsReqVen)==0){
			$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));
		}
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RequestRegister"));
		$selectCurRequest->columns(array(new Expression("a.RequestNo,a.CCReqNo,a.CReqNo,Convert(varchar(10),a.RequestDate,105) as RequestDate,CASE WHEN a.Approve='Y' THEN 'Approved'
																 Else 'Pending' END as ApproveReg,CASE WHEN a.Priority=1 THEN 'Low' WHEN a.Priority=2 THEN 'Medium' WHEN a.Priority=3 THEN 'High'
										END as priority,a.RequestType As TypeName")), array("CostCentreName"))
					->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $selectCurRequest::JOIN_LEFT);
					$selectCurRequest->where(array("a.RequestId"=>$id));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest);
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$selectVendor = $sql->select();
		$selectVendor->from(array("a"=>"VM_RequestRegister"));
		$selectVendor->columns(array(new Expression("Narration,Remarks,Convert(varchar(10),c.ReqDate,105) as ReqDateE,
							Case When isnull(i.BrandId,0) > 0 Then i.ItemCode Else g.Code End As Code,
		                       CASE WHEN a.Priority=1 THEN 'Low'
								WHEN a.Priority=2 THEN 'Medium'
								WHEN a.Priority=3 THEN 'High'
								END as priorityVal,
								Case When c.ItemId>0 Then i.BrandName Else g.ResourceName End As ResourceName")))
								->join(array("c"=>'VM_RequestTrans'), "c.RequestId=a.RequestId", array("Requesttransid","Quantity","Specification"), $selectVendor::JOIN_LEFT)
								->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitName"), $selectVendor::JOIN_LEFT)
								->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array(), $selectVendor::JOIN_LEFT)
								->join(array("i"=>"MMS_Brand"), "i.ResourceId=c.ResourceId and i.BrandId=c.itemid", array(), $selectVendor::JOIN_LEFT);
		$selectVendor->where(array("a.RequestId"=>$id));
		$statement = $sql->getSqlStringForSqlObject($selectVendor);
		$trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectAnal = $sql->select();
		$selectAnal->from(array("a"=>"VM_RequestAnalTrans"))
                   ->columns(array(new Expression("RequestAHTransId,ReqQty,ReqTransId As Requesttransid")))
					->join(array("c"=>"Proj_WBSMaster"), "c.WBSId=a.AnalysisId", array("WbsId"=>"WBSId", "WbsName"=>"WBSName"), $selectAnal::JOIN_LEFT)
					->join(array("b"=>"VM_RequestTrans"), "b.RequestTransId=a.ReqTransId", array(), $selectAnal::JOIN_LEFT);
		$selectAnal->where(array("b.RequestId"=>$id));
        $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
		$anal = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $ReqUsed = $sql->select();
        $ReqUsed->from('VM_ReqDecTrans')
            ->columns(array('RequestId'))
            ->where("RequestId=$id");
        $statement = $sql->getSqlStringForSqlObject($ReqUsed);
        $ReqUsedVal= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            $abc =$results['RequestNo'];
//        echo $abc; die;
        $this->_view->RequestNo = $results['RequestNo'];
        $this->_view->CCReqNo = $results['CCReqNo'];
        $this->_view->CReqNo = $results['CReqNo'];
		$this->_view->RequestDate = $results['RequestDate'];
		$this->_view->TypeName = $results['TypeName'];
		$this->_view->Approve = $results['ApproveReg'];
		$this->_view->CostCentreName = $results['CostCentreName'];
		$this->_view->priority = $results['priority'];
		$this->_view->trans = $trans;
		$this->_view->Narration = $trans[0]['Narration'];
		$this->_view->ReqUsedVal = $ReqUsedVal;
		$this->_view->anal = $anal;
		$this->_view->id = $id;

		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}
    public function sampleAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Request Entry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(201,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        $this->_view->genType = $vNo["genType"];

        $userId = $this->auth->getIdentity()->UserId;
        CommonHelper::CheckPowerUser($userId, $dbAdapter);
        if($viewRenderer->bPowerUser == false) {
            $bAns = CommonHelper::FindPermission($userId, 'Request-Create', $dbAdapter);
            if($bAns == false){
                $this->redirect()->toRoute("ats/default", array("controller" => "index","action" => "dashboard"));
            }
        }
        $nEst = CommonHelper::FindPermission($userId, 'Allow-Request-Qty-for-Non-Estimate', $dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $CostCentreId= $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string');
                $whereCond = array("a.CostCentreId"=>$CostCentreId);

                if($RequestType == 'Material') {
                    $RequestType=2;
                }
                else if($RequestType == 'Asset'){
                    $RequestType=3;
                } else if($RequestType == 'Activity'){
                    $RequestType=4;
                } else if($RequestType == 'IOW'){
                    $RequestType=5;
                } else if($RequestType == 'Service') {
                     $RequestType=6;
                } else if($RequestType == 'TurnKey') {
                    $RequestType=7;
                }

//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_Resource'))
//                    ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,0 As Include,'Project' As RFrom ") ))
//                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
//                    ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
//                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
//                    ->join(array('e' => 'WF_OperationalCostCentre'),'e.ProjectId=c.ProjectId')
//                    ->where("a.TypeId = $RequestType and e.CostCentreId =".$CostCentreId );

               if($RequestType == '2' || $RequestType == '3') {

                   $select = $sql->select();
                   $select->from(array('a' => 'Proj_ProjectResource'))
                       ->columns(array(new Expression("b.ResourceId,isnull(d.BrandId,0) ItemId,
                       Case When isnull(d.BrandId,0) > 0 Then d.ItemCode Else b.Code End As Code,
                       Case When isnull(d.BrandId,0)>0 Then d.BrandName Else b.ResourceName End As ResourceName,
                       0 As Include,'Project' As RFrom")))
                           ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                           ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select::JOIN_INNER)
                           ->join(array('d' => 'MMS_Brand'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                           ->join(array('e' => 'WF_OperationalCostCentre'), 'a.ProjectId=e.ProjectId', array(), $select::JOIN_INNER)
                           ->where("b.TypeId=$RequestType and e.CostCentreId=" . $CostCentreId . " ");

                if($nEst != 1) {
                    $selRa = $sql->select();
                    $selRa->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId As ResourceId,isnull(c.BrandId,0) ItemId,
                       Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code,
                       Case When isnull(c.BrandId,0)>0 Then c.BrandName Else a.ResourceName End As ResourceName,
                       0 As Include,'Library' As RFrom ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $selRa::JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId', array(), $select::JOIN_LEFT)
                        ->where("a.TypeId=$RequestType and a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentreId . ") ");
                    $select->combine($selRa, 'Union All');
                }
                   $statement = $sql->getSqlStringForSqlObject($select);
                   $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
               } else if($RequestType == '4'){

                    $select = $sql->select();
                    $select->from(array('A' => 'Proj_ProjectResource'))
                       ->columns(array(new Expression("A.ResourceId,
                            B.Code,(B.ResourceName + isnull((case when A.RateType='A' then '(Mechanical)' when A.RateType='M' then '(Manual)' end),'')) ResourceName,
                            C.UnitName,A.Rate,A.Qty,B.TypeId,C.UnitId,A.RateType,CAST(0 As Decimal(18,3)) As CurrentQty")))
                       ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                       ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                       ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                       ->where("b.TypeId=$RequestType and A.IncludeFlag=1 and D.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                else if($RequestType == '5') {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIow'))
                        ->columns(array(new Expression("a.ProjectIowId As ResourceId,b.RefSerialNo As Code,
                           b.Specification As ResourceName,d.UnitName As UnitName,0 As Include,'Project' As RFrom")))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId and a.ProjectId=b.ProjectId  ',array(),$select::JOIN_INNER)
                        ->join(array('c' => 'WF_OperationalCostCentre'),'a.ProjectId=c.ProjectId',array(),$select::JOIN_INNER)
                        ->join(array('d' => 'Proj_UOM'),'b.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
                        ->where("c.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                else if($RequestType == '6') {
                    $servicetypeid= $this->bsf->isNullCheck($postParams['ServiceType'],'number');
                    $select = $sql -> select();
                    $select->from(array('a' => 'Proj_ServiceMaster'))
                        ->columns(array(new Expression("a.ServiceId As ResourceId,a.ServiceCode As Code,
                           a.ServiceName As ResourceName,c.UnitName As UnitName,0 As Include,'Project' As RFrom")))
                        ->join(array('b' => 'Proj_ServiceTypeMaster'),'a.ServiceTypeId=b.ServiceTypeId',array(),$select::JOIN_INNER)
                        ->join(array('c'=>'Proj_UOM'),'a.UnitId=c.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('d'=>'Proj_OHService'),'a.ServiceId=d.ServiceId',array(),$select::JOIN_INNER)
                        ->join(array('e'=>'WF_OperationalCostCentre'),'d.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.ServiceTypeId=$servicetypeid and e.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                else if($RequestType == '7') {
                    $select = $sql -> select();
                    $select->from(array('a' => 'Proj_WbsMaster'))
                        ->columns(array(new Expression("a.WBSId As ResourceId,a.ParentText As Code,a.WBSName As ResourceName,
                                 '' As UnitName,0 As Include,'Project' As RFrom ")))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.LastLevel=1 and b.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('resources' => $requestResources)));
                return $response;
            }
        } else {
            // get cost centres
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            $selSer1 = $sql -> select();
            $selSer1->from('Proj_ServiceTypeMaster')
                ->columns(array(new Expression("ServiceTypeId,ServiceTypeName As ServiceTypeName")));

            $serStatement = $sql->getSqlStringForSqlObject($selSer1);
            $this->_view->arr_servicetype = $dbAdapter->query( $serStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function  entrySampleAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Request Entry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        //USER TASK
        $userId = $this->auth->getIdentity()->UserId;
        $bAns = false;
        CommonHelper::CheckPowerUser($userId, $dbAdapter);
        if($viewRenderer->bPowerUser == false) {
            $bAns = CommonHelper::FindPermission($userId, 'Request-Required-Date-Manually', $dbAdapter);
        }
        $ert = $this->bsf->isNullCheck($bAns, 'boolean');
        $this->_view->reqAdd = $this->bsf->isNullCheck($bAns, 'boolean');

        $cQty = CommonHelper::FindPermissionVariant($userId, 'Allow-Request-Qty-Greater-than-Estimate-Qty', $eVariant,$dbAdapter);
        $this->_view->variantQty = $eVariant;
        $this->_view->checkVarEQty = $cQty;


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                $CostCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentre'), 'number');

                $response = $this->getResponse();
                switch($Type) {
                    case 'getwbsdetails':
                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,
                            ParentText+'=>'+WbsName As WbsName,0 As Qty") ))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$select::JOIN_INNER)
                            ->where(array("a.LastLevel"=>"1","b.CostCentreId"=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //Get Wbs Estimate Details
                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("Cast(0 As Decimal(18,3))"), 'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CostCentre .' And ResourceId='. $ResourceId .' And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResourceId.' ) ');

                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId='. $ResourceId .' And
                                 b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId
                                 IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='. $ResourceId .' And c.CostCentreId='.$CostCentre .' And c.General=0
                                And a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId ='.$ResourceId.' and
                                c.CostCentreId='.$CostCentre.' and c.General=0 And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResourceId.' And c.CostCentreId='.$CostCentre.' And a.AnalysisId
                                IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId='. $ResourceId .' and c.ToCostCentreId='.$CostCentre.' And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel5->group(new Expression("a.ResourceId,a.AnalysisId"));

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId='. $ResourceId .' and c.FromCostCentreId='.$CostCentre.' And
                             a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .')');
                        $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='. $ResourceId.' and c.CostCentreId='.$CostCentre.' and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .')');
                        $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId='. $ResourceId .' and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId='. $ResourceId .' and
                                 a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId='. $ResourceId .'
                               and a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .') ');
                        $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from (array("a" => "VM_RequestAnalTrans" ))
                            ->columns(array(new Expression('a.ResourceId,a.AnalysisId As WBSId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As TotDCQty,CAST(0 As Decimal(18,3)) As TotBillQty,
                                CAST(0 As Decimal(18,3)) As TotRetQty,CAST(0 As Decimal(18,3)) As TotTranQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As DCQty,CAST(0 As Decimal(18,3)) As BillQty ')))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId and a.ResourceId=b.ResourceId',array(),$sel12::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel12::JOIN_INNER)
                            ->where('c.CostCentreId='.$CostCentre.' and a.ResourceId='. $ResourceId .' and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='. $ResourceId .' )');
                        $sel12->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel12->combine($sel11,"Union ALL");

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("G"=>$sel12))
                            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'IsProj'=>new Expression("(Select Count(WBSId) From Proj_ProjectDetails A
                                                       Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                                       Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
                                                       Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                                       Where A.IncludeFlag=1 and D.CostCentreId=$CostCentre and A.ResourceId=G.ResourceId And C.WBSId=G.WBSId)")));
                        $sel13->group(new Expression("G.ResourceId,G.WBSId"));
                        $statement = $sql->getSqlStringForSqlObject($sel13);
                        $arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                        $response->setStatusCode('200');
//                        $response->setContent(json_encode($arr_resource_iows));
//                        return $response;
//                        break;

                        $response->setStatusCode('200');
//                        $this->_view->setTerminal(true);
                        $this->_view->arr_resource_iows = $arr_resource_iows;
                        $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_iows,'estwbs' => $arr_wbsestimate)));
                        return $response;
                        break;
                    case 'getstockdetails':
                        $CCId = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $ResId = $this -> bsf ->isNullCheck($this->params()->fromPost('resourceid'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty' => new Expression("Cast(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDc' => new Expression("CAST(0 As Decimal(18,3))"),'TotBill' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRet' => new Expression("CAST(0 As Decimal(18,3))"),'TotTran' => new Expression("CAST(0 As Decimal(18,3))"),'IssueQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where (' b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' ');


                        $sel1 = $sql -> select();
                        $sel1 -> from (array("a" => "VM_RequestTrans" ))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                   CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,CAST(0 As Decimal(18,3)) As POQty,
                                   CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                   CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty ") ))
                            ->join(array('b' => "VM_RequestRegister"),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where ('a.ResourceId='. $ResId .' and b.CostCentreId='. $CCId .'');
                        $sel1->combine($sel,'Union All');


                        $sel2 = $sql->select();
                        $sel2->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty ")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel2::JOIN_INNER)
                            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And c.General=0');
                        $sel2->combine($sel1,'Union ALL');

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,ISNULL(SUM(A.Quantity-A.CancelQty),0) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty ") ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel3::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.'');
                        $sel3->combine($sel2,'Union All');

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3)) As POQty,
                                  CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty  ") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5->from(array("a" => "MMS_DCTrans"))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,
                                  CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty")))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And B.CostCentreId='.$CCId .' And B.General=0 ');
                        $sel5->combine($sel4,"Union ALL");

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty")))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('b.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.General=0 ');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty")))
                            ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And b.CostCentreId='.$CCId.'');
                        $sel7->combine($sel6,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                            ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.ToCostCentreId='.$CCId.' ');

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.FromCostCentreId='.$CCId.'');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("A"=>$sel9))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(SUM(TotTranQty) As Decimal(18,3)) As TotTran,Cast(0 As Decimal(18,3)) As IssueQty")));
                        $sel10 -> combine($sel7,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("a" => "MMS_IssueTrans "))
                            -> columns(array('IssueQty' => new Expression("-1*ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>"MMS_IssueRegister"),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel12::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=0 ');

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel13::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=1');
                        $sel13->combine($sel12,"Union ALL");

                        $sel14 = $sql -> select();
                        $sel14 -> from(array("A"=>$sel13))
                            ->columns(array(new Expression("CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran,CAST(SUM(IssueQty) As Decimal(18,3)) As IssueQty")));
                        $sel14 -> combine($sel10,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("G"=>$sel14))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                                'BalReqQty' => new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDc),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBill),0) As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(ISNULL(SUM(G.IssueQty),0) As Decimal(18,3))"),
                                'TransferQty'=>new Expression("CAST(ISNULL(SUM(G.TotTran),0) As Decimal(18,3))"),
                                'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRet),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0) As Decimal(18,3))")));

                        $statement = $sql->getSqlStringForSqlObject($sel11);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
                        return $response;
                        break;
                    case 'getwbsstockdetails':
                        $CCId = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $ResId = $this -> bsf ->isNullCheck($this->params()->fromPost('resourceid'),'number');
                        $WBSId = $this -> bsf ->isNullCheck($this->params()->fromPost('WBSId'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("Cast(0 As Decimal(18,3))"), 'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "WF_OperationalCostCentre"),"a.ProjectId=b.ProjectId",array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' And WbsId='.$WBSId.' ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And d.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And c.CostCentreId='.$CCId .' And c.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and c.General=0 And a.AnalysisId='.$WBSId.' ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And c.CostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.' ');
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.ToCostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.' ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.FromCostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.'');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and a.AnalysisId='.$WBSId.'');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from (array("a" => "VM_RequestAnalTrans" ))
                            ->columns(array(new Expression('CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As TotDCQty,CAST(0 As Decimal(18,3)) As TotBillQty,
                                CAST(0 As Decimal(18,3)) As TotRetQty,CAST(0 As Decimal(18,3)) As TotTranQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As DCQty,CAST(0 As Decimal(18,3)) As BillQty ')))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId and a.ResourceId=b.ResourceId',array(),$sel12::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel12::JOIN_INNER)
                            ->where('c.CostCentreId='.$CCId.' and a.ResourceId='. $ResId .' and a.AnalysisId='. $WBSId .' ');
                        $sel12->combine($sel11,"Union ALL");

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("G"=>$sel12))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                                'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRetQty),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));

                        $statement = $sql->getSqlStringForSqlObject($sel13);
                        $arr_stock_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock_wbs));
                        return $response;
                        break;
                    case 'getresestdetails':
                        $CostCentre = $this -> bsf ->isNullCheck($this->params()->fromPost('ccid'),'number');
                        $requestTransIds = $this -> bsf ->isNullCheck($this->params()->fromPost('resid'),'number');
                        $reqTransId = $this -> bsf ->isNullCheck($this->params()->fromPost('reqTransIds'),'string');

                        //Get EstimateQty,AvailableQty
                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty' => new Expression("Cast(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDc' => new Expression("CAST(0 As Decimal(18,3))"),'TotBill' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRet' => new Expression("CAST(0 As Decimal(18,3))"),'TotTran' => new Expression("CAST(0 As Decimal(18,3))")  ))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ("b.CostCentreId=$CostCentre And a.ResourceId IN ($requestTransIds) ");


                        $sel1 = $sql -> select();
                        $sel1 -> from (array("a" => "VM_RequestTrans" ))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                   CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,CAST(0 As Decimal(18,3)) As POQty,
                                   CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                   CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                            ->join(array('b' => "VM_RequestRegister"),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where ("a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre ");
                        $sel1->group(new Expression("a.ResourceId"));
                        $sel1->combine($sel,'Union All');


                        $sel2 = $sql->select();
                        $sel2->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel2::JOIN_INNER)
                            ->Where ("b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId IN ($requestTransIds) And b.CostCentreId=$CostCentre And c.General=0");
                        $sel2->group(new Expression("a.ResourceId"));
                        $sel2->combine($sel1,'Union ALL');

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,ISNULL(SUM(A.Quantity-A.CancelQty),0) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel3::JOIN_INNER)
                            ->where("a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre");
                        $sel3->group(new Expression("a.ResourceId"));
                        $sel3->combine($sel2,'Union All');

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3)) As POQty,
                                  CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran  ") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel4::JOIN_INNER)
                            ->where("a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId=$CostCentre and a.ResourceId IN ($requestTransIds) ");
                        $sel4->group(new Expression("a.ResourceId"));
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5->from(array("a" => "MMS_DCTrans"))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,
                                  CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel5::JOIN_INNER)
                            ->where("A.ResourceId IN ($requestTransIds) And B.CostCentreId=$CostCentre And B.General=0 ");
                        $sel5->group(new Expression("a.ResourceId"));
                        $sel5->combine($sel4,"Union ALL");

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where("b.ThruPO='Y' And a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre and b.General=0 ");
                        $sel6->group(new Expression("a.ResourceId"));
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                            ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where("a.ResourceId IN ($requestTransIds) And b.CostCentreId=$CostCentre");
                        $sel7->group(new Expression("a.ResourceId"));
                        $sel7->combine($sel6,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('ResourceId' => new Expression("a.ResourceId"), 'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                            ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel8::JOIN_INNER)
                            ->where("a.ResourceId IN ($requestTransIds) and b.ToCostCentreId=$CostCentre ");
                        $sel8->group(new Expression("a.ResourceId"));

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('ResourceId' => new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel9::JOIN_INNER)
                            ->where("a.ResourceId IN ($requestTransIds) and b.FromCostCentreId=$CostCentre");
                        $sel9->group(new Expression("a.ResourceId"));
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("A"=>$sel9))
                            ->columns(array(new Expression("ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(SUM(TotTranQty) As Decimal(18,3)) As TotTran")));
                        $sel10->group(new Expression("ResourceId"));
                        $sel10 -> combine($sel4,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("G"=>$sel10))
                            ->columns(array('ResourceId' =>new Expression("G.ResourceId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                                'BalReqQty' => new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDc),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBill),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0) As Decimal(18,3))"),
                                'IsProj'=>new Expression("(Select count(A.ResourceId) From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentre And A.ResourceId=G.ResourceId)")));
                      $sel11->group(new Expression("G.ResourceId"));

                        $statement = $sql->getSqlStringForSqlObject($sel11);
                        $arr_autoestdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_autoestdetails));
                        return $response;
                        break;
                    case 'getAct-wbsdetails':
                    $costCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'),'number');
                    $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'),'number');

                    $wbsSelect = $sql->select();
                    $wbsSelect->from(array('A'=>'Proj_ProjectDetails'))
                        ->columns(array(new Expression("A.ProjectIOWId As IowId,b.RefSerialNo As Code,b.Specification,E.ParentText + ' - ' + E.WBSName As WBSName,
                             Case When P.Qty <>0 Then (A.Qty/P.Qty)* D.Qty Else 0 End as Qty,
                                A.ResourceId,D.WBSId,CAST(0 As Decimal(18,3)) As CurrentQty,
                                CAST(0 As Decimal(18,3)) As HiddenQty") ))
                        ->join(array('B' => 'Proj_ProjectIOWMaster'),'A.ProjectIOWId=B.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                        ->join(array('D' => 'Proj_WBSTrans'),'A.ProjectIOWId=D.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                        ->join(array('P' => 'Proj_ProjectIOW'),'A.ProjectIOWId=P.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                        ->join(array('C' => 'Proj_UOM'),'B.UnitId=C.UnitID',array(),$wbsSelect::JOIN_LEFT)
                        ->join(array('E' => 'Proj_WBSMaster'),'D.WBSId=E.WBSId',array(),$wbsSelect::JOIN_LEFT)
                        ->join(array('F' => 'WF_OperationalCostCentre'),'A.ProjectId=F.ProjectId',array(),$wbsSelect::JOIN_INNER)
                        ->where(array("A.ResourceId=$resourceId and A.IncludeFlag=1 and F.CostCentreId=$costCentre"))
                        ->order("B.SortId Desc");
                    $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                    $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                    // WBS WISE -ESTIMATE

                    $select = $sql->select();
                    $select->from(array('A' => 'Proj_ProjectWBSResource'))
                        ->columns(array(new Expression("ISNULL(A.Qty,0) As EstimateQty,0 WOQty,0 RequestQty,0 BalReqQty,A.ResourceId,A.WbsId,B.ProjectIOWId as IowId")))
                        ->join(array('B' => 'Proj_WBSTrans'),'A.wbsid=B.wbsid and A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
                        ->join(array('C' => 'WF_OperationalCostCentre'),'A.ProjectId=C.ProjectId',array(),$select::JOIN_INNER)
                        ->where("C.CostCentreId=$costCentre and A.ResourceId = $resourceId");

                    $select1 = $sql->select();
                    $select1->from(array('A' => 'WPM_WOIOWTRans'))
                        ->columns(array(new Expression("0 EstimateQty,ISNULL(Sum(A.Qty),0) as WOQty,0 RequestQty,0 BalReqQty,
                                B.ResourceId,A.WbsId,A.IOWID as IowId")))
                        ->join(array('B' => 'WPM_WOTrans'),'A.WOTransId=B.WOTransID',array(),$select1::JOIN_LEFT)
                        ->join(array('C' => 'WPM_WORegister'),'B.WORegisterId=C.WORegisterId',array(),$select1::JOIN_LEFT)
                        ->where("C.LiveWO=0 and C.CostCentreId=$costCentre and B.ResourceId = $resourceId ")
                        ->group(array("A.IOWID","A.WbsId","B.ResourceId"));
                    $select1->combine($select,'Union All');

                    $select2 = $sql->select();
                    $select2->from(array('A' => 'VM_RequestIowTrans'))
                        ->columns(array(new Expression("0 EstimateQty,0 WOQty,0 As RequestQty,ISNULL(SUM(A.BalQty),0) BalReqQty,
                                B.ResourceId,A.WbsId,A.IowId as IowId")))
                        ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select2::JOIN_INNER)
                        ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select2::JOIN_INNER)
                        ->where("C.CostCentreId=$costCentre And B.ResourceId = $resourceId")
                        ->group(array("A.IowId","A.WbsId","B.ResourceId"));
                    $select2->combine($select1,'Union All');


                    $select3 = $sql -> select();
                    $select3 -> from (array('G' => $select2))
                        ->columns(array(new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                                                CAST(ISNULL(SUM(G.WOQty),0) As Decimal(18,3)) As WOQty,
                                                CAST(ISNULL(SUM(G.RequestQty),0) As Decimal(18,3)) As RequestQty,
                                                CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3)) As BalReqQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) > 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As AvailableQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) < 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As ExcessQty,
                                                G.ResourceId,G.WbsId,G.IowId as IowId")))
                        ->group(array("G.IowId","G.WbsId","G.ResourceId"));
                   $statement = $sql->getSqlStringForSqlObject($select3);
                    $result1_Act= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $response->setStatusCode('200');
                    $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_iows,'aWBS' => $result1_Act )));
                    return $response;
                    break;
                    case 'getIow-wbsdetails':
                        $costCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'),'number');
                        $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'),'number');

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('A'=>'Proj_WbsTrans'))
                            ->columns(array(new Expression("A.ProjectIOWId AS IowId,D.ParentText + ' - ' + B.Specification As WBSName,
                                A.ProjectIowId As ResourceId,D.WBSId,CAST(0 As Decimal(18,3)) As CurrentQty,
                                CAST(0 As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('B' => 'Proj_ProjectIOWMaster'),'A.ProjectIOWId=B.ProjectIOWId and a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('D' => 'Proj_WbsMaster'),'A.WbsId=D.WbsId and a.ProjectId=d.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('E' => 'WF_OperationalCostCentre'),'a.ProjectId=E.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("A.ProjectIowId=$resourceId and D.LastLevel=1 and E.CostCentreId=$costCentre"));
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        // WBS WISE -ESTIMATE
                        $sel = $sql -> select();
                        $sel->from(array("a" => "VM_RequestWbsTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                           ISNULL(SUM(ISNULL(a.Qty-a.CancelQty,0)),0) As RequestQty,ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalReqQty,
                           CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestTrans'),'a.RequestTransId=b.RequestTransId',array(),$sel::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel::JOIN_INNER)
                            ->where("c.RequestType='IOW' and c.CostCentreId=$costCentre and b.IowId=$resourceId ");
                        $sel->group(new Expression("b.IowId,a.WbsId"));

                        $sel1 = $sql -> select();
                        $sel1->from(array("a" => "WPM_WOWBSTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                          CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                          ISNULL(SUM(ISNULL(a.Qty,0)),0) As WOQty ")))
                            ->join(array('b' => 'WPM_WOTrans'),'a.WOTransId=b.WOTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => 'WPM_WORegister'),'b.WORegisterId=c.WORegisterId',array(),$sel1::JOIN_INNER)
                            ->where("c.LiveWO=0 and c.CostCentreId=$costCentre and b.IowId=$resourceId");
                        $sel1->group(new Expression("b.IowId,a.WbsId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "Proj_WbsTrans"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,a.WbsId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                           CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIowId=b.ProjectIowId and a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => 'Proj_WbsMaster'),'a.WbsId=c.WbsId and b.ProjectId=c.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("c.LastLevel=1 and d.CostCentreId=$costCentre and a.ProjectIowId=$resourceId");
                        $sel2->group(new Expression("a.ProjectIowId,a.WbsId"));
                        $sel2->combine($sel1,"Union All");

                        $sel3 = $sql -> select();
                        $sel3 ->from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,g.WbsId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                             CAST(ISNULL(SUM(g.RequestQty),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(g.BalReqQty),0) As Decimal(18,3)) As BalRequestQty,
                             CAST(ISNULL(SUM(g.WOQty),0) As Decimal(18,3)) As WOQty,
                             CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty ")));
                        $sel3->group(new Expression("g.IowId,g.WbsId"));


                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $result1_Act= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_iows,'aWBS' => $result1_Act )));
                        return $response;
                        break;
                    case 'getAct-estDetails':
                        $costCentre = $this->bsf->isNullCheck($this->params()->fromPost('ccid'),'number');
                        $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('resid'),'number');
                        $result_ResourceEst =array();
                        $result_Act =array();

                            if (!in_array($resourceId,$result_ResourceEst)) {
                                $result_ResourceEst[] = $resourceId;
                            }

                            $select = $sql->select();
                            $select->from(array('A' => 'Proj_ProjectResource'))
                                ->columns(array(new Expression("A.Qty As EstimateQty")))
                                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.ProjectId=B.ProjectId', array(), $select::JOIN_INNER)
                                ->where("B.CostCentreId=$costCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_aEst = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['EstimateQty']= $arr_aEst['EstimateQty'];

                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_WOTrans'))
                                ->columns(array(new Expression("ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                                ->join(array('B' => 'WPM_WORegister'), 'A.WORegisterId=B.WORegisterId', array(), $select1::JOIN_INNER)
                                ->where("B.CostCentreId=$costCentre and A.ResourceId IN ($resourceId) and B.LiveWO=0 and A.RateType = 'L'");
                            $statement = $sql->getSqlStringForSqlObject($select1);
                            $arr_aEst1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['WOQty']= $arr_aEst1['WOQty'];

                            $select2 = $sql->select();
                            $select2->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(A.Quantity-A.CancelQty,0)),0) as ReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select2::JOIN_INNER)
                                ->where("B.CostCentreId=$costCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select2);
                            $arr_aEst2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['ReqQty']= $arr_aEst2['ReqQty'];

                            $select3 = $sql->select();
                            $select3->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(((A.Quantity-A.CancelQty)-A.WOQty),0)),0) As BalReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select3::JOIN_INNER)
                                ->where("B.CostCentreId=$costCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $arr_aEst3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['BalReqQty']= $arr_aEst3['BalReqQty'];
                        $response->setStatusCode('200');
                        $response = $this->getResponse()->setContent(json_encode(array('Est' => $result_Act, 'arr_ActResId' => $result_ResourceEst )));
                        return $response;
                        break;
                    case 'getIow-estDetails':
                        $costCentre = $this->bsf->isNullCheck($this->params()->fromPost('ccid'),'number');
                        $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('resid'),'number');


                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_WOTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                            ->join(array('b' => 'WPM_WORegister'),'a.WORegisterId=b.WORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.LiveWO=0 And b.CostCentreId=$costCentre and a.IowId=$resourceId");
                        $sel -> group(new Expression("a.IowId"));


                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$costCentre and b.RequestType='IOW' and a.IowId=$resourceId");
                        $sel1 -> group(new Expression("a.Iowid"));
                        $sel1->combine($sel,"Union All");


                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_ProjectIow"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$costCentre and a.ProjectIowId=$resourceId");
                        $sel2->group(new Expression("a.ProjectIowId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.WOQty,0)),0) As Decimal(18,3)) As WOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.IowId"));
                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $arr_iowest = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_iowest));
                        return $response;
                        break;
                    case 'getService-estDetails':
                        $costCentre = $this->bsf->isNullCheck($this->params()->fromPost('ccid'),'number');
                        $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('resid'),'number');

                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_SOServiceTrans"))
                            ->columns(array(new Expression("a.ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As SOQty")))
                            ->join(array('b' => 'WPM_SORegister'),'a.SORegisterId=b.SORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.CostCentreId=$costCentre and a.ServiceId=$resourceId");
                        $sel -> group(new Expression("a.ServiceId"));

                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.ResourceId As ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$costCentre and b.RequestType='Service' and a.ResourceId=$resourceId");
                        $sel1 -> group(new Expression("a.ResourceId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_OHService"))
                            ->columns(array(new Expression("a.ServiceId,ISNULL(SUM(ISNULL(a.Amount,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$costCentre and a.ServiceId=$resourceId");
                        $sel2->group(new Expression("a.ServiceId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.ServiceId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.SOQty,0)),0) As Decimal(18,3)) As SOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.ServiceId"));

                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $arr_serviceest = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_serviceest));
                        return $response;
                        break;
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
//                echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                   return;

                if (!is_null($postData['frm_index'])) {
                    $RequestType = $this->bsf->isNullCheck($postData['RequestType'], 'string');
                    $CostCentre = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                    $ReqNo = $this->bsf->isNullCheck($this->params()->fromPost('ReqNo'), 'string');
                    $ReqDate = $this->params()->fromPost('ReqDate');
                    $priority=$this->bsf->isNullCheck($postData['priority'], 'number');
                    $CostCentreName=$this->bsf->isNullCheck($postData['CostCentreName'], 'string');
                    $requestTransIds = $this->bsf->isNullCheck($postData['requestTransIds'],'string');
                    $itemTransIds = $this->bsf->isNullCheck($postData['itemTransIds'], 'string');
                    $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');

                    if($itemTransIds == '')
                    {
                        $itemTransIds=0;
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CompanyId'))
                        ->where("CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$Comp['CompanyId'];


                    $CRNo = CommonHelper::getVoucherNo(201, date('Y/m/d'),  $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CRNo = $CRNo;
                    $RNo = $CRNo['voucherNo'];
                    $this->_view->RNo = $RNo;

                    $CCrno = CommonHelper::getVoucherNo(201, date('Y/m/d'),   0,$CostCentre, $dbAdapter, "");
                    $this->_view->CCrno = $CCrno;
                    $CCRNo = $CCrno['voucherNo'];
                    $this->_view->CCRNo = $CCRNo;
                    $this->_view->RequestType = $RequestType;
                    $this->_view->CostCentre = $CostCentre;
                    $this->_view->ReqNo=$ReqNo;
                    $this->_view->ReqDate=$ReqDate;
                    $this->_view->priority=$priority;
                    $this->_view->requestTransIds=$requestTransIds;
                    $this->_view->gridtype=$gridtype;
                    $this->_view->reqAdd = $bAns;

                    if($RequestType == "Material")
                    { $RequestType=2; }
                    else if($RequestType == "Asset")
                    { $RequestType=3; }
                    else if($RequestType == "Activity")
                    { $RequestType=4; }
                    else if($RequestType == "IOW")
                    { $RequestType=5; }
                    else if($RequestType == 'Service')
                    { $RequestType=6; }
                    else if($RequestType == 'TurnKey')
                    { $RequestType=7; }

                    $selCC = $sql->select();
                    $selCC->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreName'))
                        ->where("a.CostCentreId=".$CostCentre);
                    $statement = $sql->getSqlStringForSqlObject($selCC);
                    $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view-> CostCentreName=$ccname['CostCentreName'];

                    // get resource lists
                    if($RequestType == 2||$RequestType == 3){

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                           Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                           b.ResourceGroupName,b.ResourceGroupId,c.UnitName,c.UnitId,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty,
                           RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentre) Then 'Project' Else 'Library' End  ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName", "ResourceGroupId"), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                            //->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
                            ->where("a.TypeId=$RequestType  and (a.ResourceId IN ($requestTransIds) and
                            isnull(d.BrandId,0) IN ($itemTransIds))");
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a'=>'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,
                            ParentText+'=>'+WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("a.LastLevel"=>"1","b.CostCentreId"=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                           Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitName else c.UnitName End As UnitName,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,'Project' As RFrom,
                           CAST(0 As Decimal(18,3)) As Qty")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                            ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                            ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                            ->where("a.TypeId=$RequestType  and g.CostCentreId=$CostCentre  and (a.ResourceId NOT IN ($requestTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");

                        $selRa = $sql -> select();
                        $selRa -> from (array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId as data,1 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                           Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitName else c.UnitName End As UnitName,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,'Library' As RFrom,
                           CAST(0 As Decimal(18,3)) As Qty ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                            ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                            ->where("a.TypeId=$RequestType  and a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentre)
                           and (a.ResourceId NOT IN ($requestTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");
                        $select -> combine($selRa,"Union All");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsRes = $sql -> select();
                        $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                            ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                            ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                            ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                            ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($wbsRes);
                        $this->_view->arr_resource_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    }
                    else if($RequestType == 4){
                        $result1_Act = array();
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectResource'))
                            ->columns(array(new Expression("A.ResourceId,
                            B.Code,(B.ResourceName + isnull((case when A.RateType='A' then '(Mechanical)' when A.RateType='M' then '(Manual)' end),'')) ResourceName,
                            C.UnitName,A.Rate,A.Qty as HQty,B.TypeId,C.UnitId,A.RateType,CAST(0 As Decimal(18,3)) As CurrentQty,
                            CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("b.TypeId=$RequestType and A.IncludeFlag=1 and A.ResourceId IN ($requestTransIds)and
                                    D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('A'=>'Proj_ProjectDetails'))
                            ->columns(array(new Expression("A.ProjectIOWId AS IowId,b.RefSerialNo As Code,b.Specification, E.ParentText + ' - ' + E.WBSName As WBSName,
                                 Case When P.Qty <>0 Then (A.Qty/P.Qty)* D.Qty Else 0 End as Qty,
                                A.ResourceId,D.WBSId,CAST(0 As Decimal(18,3)) As CurrentQty,
                                CAST(0 As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('B' => 'Proj_ProjectIOWMaster'),'A.ProjectIOWId=B.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('D' => 'Proj_WBSTrans'),'A.ProjectIOWId=D.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('P' => 'Proj_ProjectIOW'),'A.ProjectIOWId=P.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'),'B.UnitId=C.UnitID',array(),$wbsSelect::JOIN_LEFT)
                            ->join(array('E' => 'Proj_WBSMaster'),'D.WBSId=E.WBSId',array(),$wbsSelect::JOIN_LEFT)
                            ->join(array('F' => 'WF_OperationalCostCentre'),'A.ProjectId=F.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("A.ResourceId in($requestTransIds) and A.IncludeFlag=1 and F.CostCentreId=$CostCentre"))
                            ->order("B.SortId Desc");
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows = $arr_resource_iows;

                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectResource'))
                            ->columns(array(new Expression("A.ResourceId AS Data,
                            B.Code,(B.ResourceName + isnull((case when A.RateType='A' then '(Mechanical)' when A.RateType='M' then '(Manual)' end),'')) value,
                            C.UnitName,A.Rate,A.Qty as HQty,B.TypeId,C.UnitId,A.RateType,CAST(0 As Decimal(18,3)) As CurrentQty,
                            CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("b.TypeId=$RequestType and A.IncludeFlag=1 and A.ResourceId Not IN ($requestTransIds) AND D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate-Activity
                        $arr=explode(",",$requestTransIds);

                        $result_Act = array();
                        $result_ResourceEst = array();

                        foreach($arr as $res) {
                            if (!in_array($res,$result_ResourceEst)) {
                                $result_ResourceEst[] = $res;
                            }

                            $select = $sql->select();
                            $select->from(array('A' => 'Proj_ProjectResource'))
                                ->columns(array(new Expression("A.Qty As EstimateQty")))
                                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.ProjectId=B.ProjectId', array(), $select::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($res)");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_aEst = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$res]['EstimateQty']= $arr_aEst['EstimateQty'];

                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_WOTrans'))
                                ->columns(array(new Expression("ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                                ->join(array('B' => 'WPM_WORegister'), 'A.WORegisterId=B.WORegisterId', array(), $select1::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($res) and B.LiveWO=0 and A.RateType = 'L'");
                            $statement = $sql->getSqlStringForSqlObject($select1);
                            $arr_aEst1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$res]['WOQty']= $arr_aEst1['WOQty'];

                            $select2 = $sql->select();
                            $select2->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(A.Quantity-A.CancelQty,0)),0) as ReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select2::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($res)");
                            $statement = $sql->getSqlStringForSqlObject($select2);
                            $arr_aEst2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$res]['ReqQty']= $arr_aEst2['ReqQty'];

                            $select3 = $sql->select();
                            $select3->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(((A.Quantity-A.CancelQty)-A.WOQty),0)),0) As BalReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select3::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($requestTransIds)");
                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $arr_aEst3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$res]['BalReqQty']= $arr_aEst3['BalReqQty'];

                        }
                        $this->_view->arr_actEst =$result_Act;
                        $this->_view->result_ResourceEst =$result_ResourceEst;

                        //OverAll Estimate-ActivitY END

//                        // WBS WISE -ESTIMATE
//                        $res1=implode(",",$arr);
//                        $result1_Act = array();
//                        $result1_ResourceEst = array();
//                        foreach($arr_resource_iows as $wId){
//                            $iowId =$wId['IowId'];
//                            $wbsId =$wId['WBSId'];
//
//                            if (!in_array($wbsId,$result1_ResourceEst)) {
//                                $result1_ResourceEst[] = $wbsId;
//                            }
//                           // print_r($result1_ResourceEst);
//                            $select = $sql->select();
//                            $select->from(array('A' => 'Proj_ProjectWBSResource'))
//                                ->columns(array(new Expression("A.WbsId AS WbsId, A.Qty As EstimateQty")))
//                                ->join(array('B' => 'WF_OperationalCostCentre'),'A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
//                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($res1) and A.WbsId in($wbsId) ");
//                            $statement = $sql->getSqlStringForSqlObject($select);
//                            $aEstimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                            if($aEstimate['EstimateQty'] > 0 || $aEstimate['EstimateQty'] != ''){
//                                $result1_Act[$wbsId]['EstimateQty']= $aEstimate['EstimateQty'];
//                                $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                $result1_Act[$wbsId]['RsId']= $res1;
//                                $result1_Act[$wbsId]['IOWID']= $iowId;
//                            } else {
//                                $result1_Act[$wbsId]['EstimateQty']= 0;
//                            }
//
//
//                            $select1 = $sql->select();
//                            $select1->from(array('A' => 'WPM_WOIOWTRans'))
//                                ->columns(array(new Expression("ISNULL(Sum(A.Qty),0) as WOQty,A.WbsId as WbsId,A.IOWID as IOWID")))
//                                ->join(array('B' => 'WPM_WOTrans'),'A.WOTransId=B.WOTransID',array(),$select1::JOIN_LEFT)
//                                ->join(array('C' => 'WPM_WORegister'),'B.WORegisterId=C.WORegisterId',array(),$select1::JOIN_LEFT)
//                                ->where("C.LiveWO=0 and A.IOWID = $iowId and A.WbsId = $wbsId AND C.CostCentreId=$CostCentre and
//                                 B.ResourceId IN ($res1)")
//                                ->group(array("A.IOWID","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select1);
//                            $aWO = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                                if($aWO['WOQty'] > 0 || $aWO['WOQty'] != ''){
//                                    $result1_Act[$wbsId]['WOQty']= $aWO['WOQty'];
//                                    $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                    $result1_Act[$wbsId]['IOWID']= $iowId;
//                                    $result1_Act[$wbsId]['RsId']= $res1;
//                                } else {
//                                    $result1_Act[$wbsId]['WOQty']= 0;
//                                }
//
//                            $select2 = $sql->select();
//                            $select2->from(array('A' => 'VM_RequestIowTrans'))
//                                ->columns(array(new Expression("ISNULL(SUM(A.Qty-A.CancelQty),0) As RequestQty,A.WbsId as WbsId,A.IowId as IOWID")))
//                                ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select2::JOIN_INNER)
//                                ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select2::JOIN_INNER)
//                                ->where("C.CostCentreId=$CostCentre and A.WbsId=$wbsId And A.IowId=$iowId and B.ResourceId IN ($res1)")
//                                ->group(array("A.IowId","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select2);
//                            $aReq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                                if($aReq['RequestQty'] > 0 || $aReq['RequestQty'] != ''){
//                                    $result1_Act[$wbsId]['RequestQty']= $aReq['RequestQty'];
//                                    $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                    $result1_Act[$wbsId]['IOWID']= $iowId;
//                                    $result1_Act[$wbsId]['RsId']= $res1;
//                                } else {
//                                    $result1_Act[$wbsId]['ReqQty']= 0;
//                                }
//
//
//                            $select3 = $sql->select();
//                            $select3->from(array('A' => 'VM_RequestIowTrans'))
//                                ->columns(array(new Expression("ISNULL(SUM((A.Qty-A.CancelQty)-A.WOQty),0) As BalReqQty,A.WbsId as WbsId,A.IowId as IOWID")))
//                                ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select3::JOIN_INNER)
//                                ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select3::JOIN_INNER)
//                                ->where("C.CostCentreId=$CostCentre and A.WbsId=$wbsId And A.IowId=$iowId and B.ResourceId IN ($res1)")
//                                ->group(array("A.IowId","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select3);
//                            $aBal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                                if($aBal['BalReqQty'] > 0 ||$aBal['BalReqQty'] != ''){
//                                    $result1_Act[$wbsId]['BalReqQty']= $aBal['BalReqQty'];
//                                    $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                    $result1_Act[$wbsId]['IOWID']= $iowId;
//                                    $result1_Act[$wbsId]['RsId']= $res1;
//                                } else {
//                                    $result1_Act[$wbsId]['BalReqQty']= 0;
//                                }
//
//                        }
//                        // print_r($result1_Act); die;
//                        $this->_view->arr_act1 =$result1_Act;
//                        $this->_view->result1_ResourceEst =$result1_ResourceEst;
//                        //END -WBS-WISE-ESTIMATE

                        foreach($arr_resource_iows as $wId){
                            $iowId =$wId['IowId'];
                            $wbsId =$wId['WBSId'];

                            $select = $sql->select();
                            $select->from(array('A' => 'Proj_ProjectWBSResource'))
                                ->columns(array(new Expression("ISNULL(A.Qty,0) As EstimateQty,0 WOQty,0 RequestQty,0 BalReqQty,A.ResourceId,A.WbsId,B.ProjectIOWId as IowId")))
                                ->join(array('B' => 'Proj_WBSTrans'),'A.wbsid=B.wbsid and A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
                                ->join(array('C' => 'WF_OperationalCostCentre'),'A.ProjectId=C.ProjectId',array(),$select::JOIN_INNER)
                                ->where("C.CostCentreId=$CostCentre and A.ResourceId IN ($requestTransIds)");

                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_WOIOWTRans'))
                                ->columns(array(new Expression("0 EstimateQty,ISNULL(Sum(A.Qty),0) as WOQty,0 RequestQty,0 BalReqQty,
                                B.ResourceId,A.WbsId,A.IOWID as IowId")))
                                ->join(array('B' => 'WPM_WOTrans'),'A.WOTransId=B.WOTransID',array(),$select1::JOIN_LEFT)
                                ->join(array('C' => 'WPM_WORegister'),'B.WORegisterId=C.WORegisterId',array(),$select1::JOIN_LEFT)
                                ->where("C.LiveWO=0 and C.CostCentreId=$CostCentre and B.ResourceId IN ($requestTransIds) ")
                                ->group(array("A.IOWID","A.WbsId","B.ResourceId"));
                            $select1->combine($select,'Union All');

                            $select2 = $sql->select();
                            $select2->from(array('A' => 'VM_RequestIowTrans'))
                                ->columns(array(new Expression("0 EstimateQty,0 WOQty,0 As RequestQty,ISNULL(SUM(A.BalQty),0) BalReqQty,
                                B.ResourceId,A.WbsId,A.IowId as IowId")))
                                ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select2::JOIN_INNER)
                                ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select2::JOIN_INNER)
                                ->where("C.CostCentreId=$CostCentre And B.ResourceId IN ($requestTransIds)")
                                ->group(array("A.IowId","A.WbsId","B.ResourceId"));
                            $select2->combine($select1,'Union All');


                            $select3 = $sql -> select();
                            $select3 -> from (array('G' => $select2))
                                ->columns(array(new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                                                CAST(ISNULL(SUM(G.WOQty),0) As Decimal(18,3)) As WOQty,
                                                CAST(ISNULL(SUM(G.RequestQty),0) As Decimal(18,3)) As RequestQty,
                                                CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3)) As BalReqQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) > 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As AvailableQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) < 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As ExcessQty,
                                                G.ResourceId,G.WbsId,G.IowId as IowId")))
                            ->group(array("G.IowId","G.WbsId","G.ResourceId"));
                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $result1_Act= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        }
                        //print_r($result1_Act);die;
                        $this->_view->arr_act1 =$result1_Act;
                    }
                    else if($RequestType == 5) {
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectIow'))
                            ->columns(array(new Expression("A.ProjectIowId As ResourceId,
                            B.RefSerialNo As Code,b.Specification As ResourceName,
                            C.UnitName,C.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'Proj_ProjectIowMaster'), 'A.ProjectIowId=B.ProjectIowId and a.ProjectId=b.ProjectId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("A.ProjectIowId IN ($requestTransIds) and D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('A'=>'Proj_WbsTrans'))
                            ->columns(array(new Expression("A.ProjectIOWId AS IowId,D.ParentText + ' - ' + B.Specification As WBSName,
                                A.ProjectIowId As ResourceId,D.WBSId,CAST(0 As Decimal(18,3)) As CurrentQty,
                                CAST(0 As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('B' => 'Proj_ProjectIOWMaster'),'A.ProjectIOWId=B.ProjectIOWId and a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('D' => 'Proj_WbsMaster'),'A.WbsId=D.WbsId and a.ProjectId=d.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('E' => 'WF_OperationalCostCentre'),'a.ProjectId=E.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("A.ProjectIowId in($requestTransIds) and D.LastLevel=1 and E.CostCentreId=$CostCentre"));

                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows = $arr_resource_iows;

                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectIow'))
                            ->columns(array(new Expression("A.ProjectIowId As Data,
                            B.RefSerialNo As Code,b.Specification As value,
                            C.UnitName,C.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'Proj_ProjectIowMaster'), 'A.ProjectIowId=B.ProjectIowId and a.ProjectId=b.ProjectId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("A.ProjectIowId NOT IN ($requestTransIds) and D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate - IOW
                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_WOTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                            ->join(array('b' => 'WPM_WORegister'),'a.WORegisterId=b.WORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.LiveWO=0 And b.CostCentreId=$CostCentre and a.IowId IN ($requestTransIds)");
                        $sel -> group(new Expression("a.IowId"));


                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and b.RequestType='IOW' and a.IowId IN ($requestTransIds)");
                        $sel1 -> group(new Expression("a.Iowid"));
                        $sel1->combine($sel,"Union All");


                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_ProjectIow"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ProjectIowId IN ($requestTransIds)");
                        $sel2->group(new Expression("a.ProjectIowId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.WOQty,0)),0) As Decimal(18,3)) As WOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.IowId"));

                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $this->_view->arr_iowestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        //End OverAll Estimate - IOW

                        //Get Wbs wise estimate - IOW
                        $sel = $sql -> select();
                        $sel->from(array("a" => "VM_RequestWbsTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                           ISNULL(SUM(ISNULL(a.Qty-a.CancelQty,0)),0) As RequestQty,ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalReqQty,
                           CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestTrans'),'a.RequestTransId=b.RequestTransId',array(),$sel::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel::JOIN_INNER)
                            ->where("c.RequestType='IOW' and c.CostCentreId=$CostCentre and b.IowId IN ($requestTransIds) ");
                        $sel->group(new Expression("b.IowId,a.WbsId"));

                        $sel1 = $sql -> select();
                        $sel1->from(array("a" => "WPM_WOWBSTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                          CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                          ISNULL(SUM(ISNULL(a.Qty,0)),0) As WOQty ")))
                            ->join(array('b' => 'WPM_WOTrans'),'a.WOTransId=b.WOTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => 'WPM_WORegister'),'b.WORegisterId=c.WORegisterId',array(),$sel1::JOIN_INNER)
                            ->where("c.LiveWO=0 and c.CostCentreId=$CostCentre and b.IowId IN ($requestTransIds)");
                        $sel1->group(new Expression("b.IowId,a.WbsId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "Proj_WbsTrans"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,a.WbsId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                           CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIowId=b.ProjectIowId and a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => 'Proj_WbsMaster'),'a.WbsId=c.WbsId and b.ProjectId=c.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("c.LastLevel=1 and d.CostCentreId=$CostCentre and a.ProjectIowId IN ($requestTransIds)");
                        $sel2->group(new Expression("a.ProjectIowId,a.WbsId"));
                        $sel2->combine($sel1,"Union All");

                        $sel3 = $sql -> select();
                        $sel3 ->from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,g.WbsId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                             CAST(ISNULL(SUM(g.RequestQty),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(g.BalReqQty),0) As Decimal(18,3)) As BalRequestQty,
                             CAST(ISNULL(SUM(g.WOQty),0) As Decimal(18,3)) As WOQty,
                             CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty ")));
                        $sel3->group(new Expression("g.IowId,g.WbsId"));


                        $statement = $sql->getSqlStringForSqlObject( $sel3 );
                        $this->_view->arr_iowwbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        // End Wbs wise estimate - IOW
                    }
                    else if($RequestType == 6)
                    {
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ServiceMaster'))
                            ->columns(array(new Expression("A.ServiceId As ResourceId,
                            A.ServiceCode As Code,a.ServiceName As ResourceName,
                            B.UnitName,B.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'Proj_UOM'), 'A.UnitId=B.UnitId', array(), $select::JOIN_LEFT)
                            ->where("A.ServiceId IN ($requestTransIds)");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ServiceMaster'))
                            ->columns(array(new Expression("A.ServiceId As Data,
                            A.ServiceCode As Code,a.ServiceName As value,
                            B.UnitName,B.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'Proj_UOM'), 'A.UnitId=B.UnitId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_OHService'),'A.ServiceId=C.ServiceId',array(),$select::JOIN_INNER)
                            ->join(array('D' => 'WF_OperationalCostCentre'),'C.ProjectId=D.ProjectId',array(),$select::JOIN_INNER)
                            ->where("A.ServiceId NOT IN ($requestTransIds) and D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate - IOW

                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_SOServiceTrans"))
                            ->columns(array(new Expression("a.ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As SOQty")))
                            ->join(array('b' => 'WPM_SORegister'),'a.SORegisterId=b.SORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ServiceId IN ($requestTransIds)");
                        $sel -> group(new Expression("a.ServiceId"));

                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.ResourceId As ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and b.RequestType='Service' and a.ResourceId IN ($requestTransIds)");
                        $sel1 -> group(new Expression("a.ResourceId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_OHService"))
                            ->columns(array(new Expression("a.ServiceId,ISNULL(SUM(ISNULL(a.Amount,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ServiceId IN ($requestTransIds)");
                        $sel2->group(new Expression("a.ServiceId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.ServiceId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.SOQty,0)),0) As Decimal(18,3)) As SOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.ServiceId"));

                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $this->_view->arr_iowestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        //End OverAll Estimate - IOW
                    }
                    else if($RequestType == 7) {
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_WbsMaster'))
                            ->columns(array(new Expression("A.WBSId As ResourceId,
                            A.ParentText As Code,a.WBSName As ResourceName,
                            '' As UnitName,0 As UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->where("A.LastLevel=1 and A.WBSId IN ($requestTransIds)");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_WbsMaster'))
                            ->columns(array(new Expression("A.WBSId As Data,
                            A.ParentText As Code,a.WBSName As value,
                            '' As UnitName,0 As UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'WF_OperationalCostCentre'),'A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
                            ->where("A.LastLevel=1 and A.WbsId NOT IN ($requestTransIds) and B.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    $subQuery = $sql->select();
                    $subQuery->from("VM_RequestTrans")
                        ->columns(array('ResourceId'))
                        ->where('RequestId IN ('.$requestTransIds.')')
                        ->group(new Expression('ResourceId'));

//                    $wbsSelect = $sql->select();
//                    $wbsSelect->from(array('a'=>'Proj_WBSMaster'))
//                        ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty") ))
//                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
//                        ->where(array("a.LastLevel"=>"1","b.CostCentreId"=>$CostCentre));
//                    $statement = $sql->getSqlStringForSqlObject($wbsSelect);
//                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_wbsestiow= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



//                    $select = $sql->select();
//                    $select->from(array('a' => 'Proj_Resource'))
//                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
//                        ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,c.UnitName,c.UnitId")))
//                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
//                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
//                        ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
//                       // ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
//                        ->where("a.TypeId=$RequestType  and (a.ResourceId NOT IN ($requestTransIds) and isnull(d.BrandId,0) NOT IN ($itemTransIds))");
//                    $statement = $sql->getSqlStringForSqlObject( $select );
//                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Get EstimateQty,AvailableQty
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'BalPOQty' => new Expression("Cast(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDc' => new Expression("CAST(0 As Decimal(18,3))"),'TotBill' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRet' => new Expression("CAST(0 As Decimal(18,3))"),'TotTran' => new Expression("CAST(0 As Decimal(18,3))")  ))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ("b.CostCentreId=$CostCentre And a.ResourceId IN ($requestTransIds) ");


                    $sel1 = $sql -> select();
                    $sel1 -> from (array("a" => "VM_RequestTrans" ))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                   CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,CAST(0 As Decimal(18,3)) As POQty,
                                   CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                   CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                        ->join(array('b' => "VM_RequestRegister"),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                        ->where ("a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre ");
                    $sel1->group(new Expression("a.ResourceId"));
                    $sel1->combine($sel,'Union All');

                    $sel2 = $sql->select();
                    $sel2->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel2::JOIN_INNER)
                        ->Where ("b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId IN ($requestTransIds) And b.CostCentreId=$CostCentre And c.General=0");
                    $sel2->group(new Expression("a.ResourceId"));
                    $sel2->combine($sel1,'Union ALL');

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,ISNULL(SUM(A.Quantity-A.CancelQty),0) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel3::JOIN_INNER)
                        ->where("a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre");
                    $sel3->group(new Expression("a.ResourceId"));
                    $sel3->combine($sel2,'Union All');

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3)) As POQty,
                                  CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran  ") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel4::JOIN_INNER)
                        ->where("a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId=$CostCentre and a.ResourceId IN ($requestTransIds) ");
                    $sel4->group(new Expression("a.ResourceId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5->from(array("a" => "MMS_DCTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,
                                  CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel5::JOIN_INNER)
                        ->where("A.ResourceId IN ($requestTransIds) And B.CostCentreId=$CostCentre And B.General=0 ");
                    $sel5->group(new Expression("a.ResourceId"));
                    $sel5->combine($sel4,"Union ALL");

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where("b.ThruPO='Y' And a.ResourceId IN ($requestTransIds) and b.CostCentreId=$CostCentre and b.General=0 ");
                    $sel6->group(new Expression("a.ResourceId"));
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where("a.ResourceId IN ($requestTransIds) And b.CostCentreId=$CostCentre");
                    $sel7->group(new Expression("a.ResourceId"));
                    $sel7->combine($sel6,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId' => new Expression("a.ResourceId"), 'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel8::JOIN_INNER)
                        ->where("a.ResourceId IN ($requestTransIds) and b.ToCostCentreId=$CostCentre ");
                    $sel8->group(new Expression("a.ResourceId"));

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId' => new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel9::JOIN_INNER)
                        ->where("a.ResourceId IN ($requestTransIds) and b.FromCostCentreId=$CostCentre");
                    $sel9->group(new Expression("a.ResourceId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("A"=>$sel9))
                        ->columns(array(new Expression("ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(SUM(TotTranQty) As Decimal(18,3)) As TotTran")));
                    $sel10->group(new Expression("ResourceId"));
                    $sel10 -> combine($sel7,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("G"=>$sel10))
                        ->columns(array('ResourceId' =>new Expression("G.ResourceId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                            'BalReqQty' => new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDc),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBill),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0) As Decimal(18,3))"),
                            'IsProj'=>new Expression("(Select Count(A.ResourceId) From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentre And A.ResourceId=G.ResourceId)")));
                    $sel11->group(new Expression("G.ResourceId"));

                    $statement = $sql->getSqlStringForSqlObject( $sel11 );
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Get Wbs Estimate Details
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("Cast(0 As Decimal(18,3))"), 'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentre .' And ResourceId IN ('. $requestTransIds .') And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId IN (' .$requestTransIds. ') And
                                 b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId
                                 IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('A.ResourceId IN ('.$requestTransIds.') And c.CostCentreId='.$CostCentre .' And c.General=0
                                And a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN ('.$requestTransIds.') and
                                c.CostCentreId='.$CostCentre.' and c.General=0 And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$requestTransIds.') And c.CostCentreId='.$CostCentre.' And a.AnalysisId
                                IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN ('. $requestTransIds .') and c.ToCostCentreId='.$CostCentre.' And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel5->group(new Expression("a.ResourceId,a.AnalysisId"));

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN ('. $requestTransIds .') and c.FromCostCentreId='.$CostCentre.' And
                             a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .'))');
                    $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                    $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN ('. $requestTransIds.') and c.CostCentreId='.$CostCentre.' and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .'))');
                    $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN ('. $requestTransIds .') and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId IN ('. $requestTransIds .') and
                                 a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId IN ('.$requestTransIds.')
                               and a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .')) ');
                    $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from (array("a" => "VM_RequestAnalTrans" ))
                        ->columns(array(new Expression('a.ResourceId,a.AnalysisId As WBSId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As TotDCQty,CAST(0 As Decimal(18,3)) As TotBillQty,
                                CAST(0 As Decimal(18,3)) As TotRetQty,CAST(0 As Decimal(18,3)) As TotTranQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As DCQty,CAST(0 As Decimal(18,3)) As BillQty ')))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId and a.ResourceId=b.ResourceId',array(),$sel12::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel12::JOIN_INNER)
                        ->where('c.CostCentreId='.$CostCentre.' and a.ResourceId IN ('. $requestTransIds .') and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('. $requestTransIds .') )');
                    $sel12->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel12->combine($sel11,"Union ALL");

                    $sel13 = $sql -> select();
                    $sel13 -> from(array("G"=>$sel12))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                            'IsProj'=>new Expression("(Select Count(WBSId) From Proj_ProjectDetails A
                                                       Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                                       Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
                                                       Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                                       Where A.IncludeFlag=1 and D.CostCentreId=$CostCentre and A.ResourceId=G.ResourceId And C.WBSId=G.WBSId)")));
                    $sel13->group(new Expression("G.ResourceId,G.WBSId"));
                    $statement = $sql->getSqlStringForSqlObject( $sel13 );
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //
                }
            }
            else {
                $requestId = $this->params()->fromRoute('requestId');
                $this->_view->requestId = $requestId;
                if (isset($requestId) && $requestId != '') {
                    // get request
                    $selReqReg = $sql->select();
                    $selReqReg->from(array("a" => "VM_RequestRegister"))
                        ->columns(array(new Expression("a.RequestType as RequestType,a.CostCentreId as CostCentreId,
                        a.RequestNo as RequestNo,CONVERT(varchar(10),a.RequestDate,105) as RequestDate ,a.Narration as Narration,
                        a.CCReqNo as CCReqNo,a.CReqNo as CReqNo,
                        a.Approve as Approve,a.GridType  as GridType")))
                        ->where("a.RequestId=$requestId");
                    $statement = $sql->getSqlStringForSqlObject($selReqReg);
                    $this->_view->reqregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $RequestType = $this->_view->reqregister['RequestType'];
                    $CostCentre = $this->_view->reqregister['CostCentreId'];
                    $ReqNo = $this->_view->reqregister['RequestNo'];
                    $ReqDate = $this->_view->reqregister['RequestDate'];
                    $Narration = $this->_view->reqregister['Narration'];
                    $CCRNo = $this->_view->reqregister['CCReqNo'];
                    $RNo = $this->_view->reqregister['CReqNo'];
                    $approve = $this->_view->reqregister['Approve'];
                    $gridtype = $this->_view->reqregister['GridType'];


                    $this->_view->RequestType = $RequestType;
                    $this->_view->CostCentre = $CostCentre;
                    $this->_view->ReqNo = $ReqNo;
                    $this->_view->ReqDate = $ReqDate;
                    $this->_view->Narration = $Narration;
                    $this->_view->CCRNo = $CCRNo;
                    $this->_view->RNo = $RNo;
                    $this->_view->approve = $approve;
                    $this->_view->gridtype = $gridtype;

                    if ($RequestType == "Material") {
                        $RequestType = 2;
                    } else if ($RequestType == "Asset") {
                        $RequestType = 3;
                    } else if($RequestType == "Activity"){
                        $RequestType = 4;
                    } else if($RequestType == 'IOW') {
                        $RequestType = 5;
                    } else if($RequestType == 'Service') {
                        $RequestType = 6;
                    } else if($RequestType == 'TurnKey') {
                        $RequestType = 7;
                    }


                    $selCC = $sql->select();
                    $selCC->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where("a.CostCentreId=" . $CostCentre);
                    $statement = $sql->getSqlStringForSqlObject($selCC);
                    $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->CostCentreName = $ccname['CostCentreName'];

                    if($RequestType == 2||$RequestType == 3) {
                        // get resource lists
                        $select = $sql->select();
                        $select->from(array('a' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("b.ResourceId,isnull(e.BrandId,0) As ItemId,
                                Case When isnull(e.BrandId,0)>0 Then e.ItemCode Else b.Code End As Code,
                                Case When isnull(e.BrandId,0)>0 Then e.BrandName Else b.ResourceName End As ResourceName,
                                c.ResourceGroupName,c.ResourceGroupId,d.UnitName,d.UnitId,
                                CONVERT(varchar(10),a.ReqDate,105) As ReqDate,
                                CAST(a.Quantity As Decimal(18,3)) As Qty,CAST(a.Quantity As Decimal(18,3)) As HiddenQty,a.Remarks,a.Specification,
                                RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentre . ") Then 'Project' Else 'Library' End  ")))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select:: JOIN_INNER)
                            ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_INNER)
                            ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array(), $select::JOIN_INNER)
                            ->join(array('e' => 'MMS_Brand'), 'b.ResourceId=e.ResourceId and e.BrandId = a.ItemId', array(), $select::JOIN_LEFT)
                            ->join(array('f' => 'VM_RequestRegister'), 'a.RequestId=f.RequestId', array(), $select::JOIN_INNER)
                            //->join(array('f' => 'Proj_ProjectResource'), 'b.ResourceId=f.ResourceId', array(), $select::JOIN_INNER)
                            ->where("b.TypeId=$RequestType and f.CostCentreId=" . $CostCentre . " and a.RequestId=" . $requestId . "");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        /* $subQuery = $sql->select();
                         $subQuery->from("VM_RequestTrans")
                             ->columns(array('ResourceId'))
                             ->where('RequestId IN (' . $requestTransIds . ')')
                             ->group(new Expression('ResourceId'));*/
                        $subWbs1 = $sql->select();
                        $subWbs1->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,CAST(0 As Decimal(18,6)) As Qty,CAST(0 As Decimal(18,6)) As HiddenQty")))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.ProjectId=b.ProjectId', array(), $subWbs1::JOIN_INNER)
                            ->where->expression('a.LastLevel=1 And b.CostCentreId=' . $CostCentre . ' And
                             a.WBSId NOT IN (Select AnalysisId From VM_RequestAnalTrans Where ReqTransId IN (Select RequestTransId From VM_RequestTrans Where RequestId=?))', $requestId);

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("c.ResourceId,C.ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,CAST(b.ReqQty As Decimal(18,6)) As Qty,CAST(b.ReqQty As Decimal(18,6)) As HiddenQty")))
                            ->join(array('b' => 'VM_RequestAnalTrans'), 'a.WbsId=b.AnalysisId', array(), $wbsSelect::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $wbsSelect::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'), 'a.ProjectId=d.ProjectId', array(), $wbsSelect::JOIN_INNER)
                            ->where(array("a.LastLevel" => "1", "d.CostCentreId" => $CostCentre, "c.RequestId" => $requestId));
                        $wbsSelect->combine($subWbs1, 'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selR = $sql->select();
                        $selR->from(array('a' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("a.ResourceId")))
                            ->where(array("a.RequestId" => $requestId));
                        $selI = $sql->select();
                        $selI->from(array('a' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("a.ItemId")))
                            ->where(array("a.RequestId" => $requestId));

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.ItemCode +' - ' + d.BrandName Else a.Code + ' - ' +  a.ResourceName End As value,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,'Project' As RFrom ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'Proj_UOM'), 'd.UnitId=f.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                            -> where("a.TypeId=$RequestType and g.CostCentreId=" . $CostCentre . " and (a.ResourceId NOT IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId)
                            Or isnull(d.BrandId,0) NOT IN (Select ItemId From VM_RequestTrans Where RequestId=$requestId))");

                        $selRa = $sql->select();
                        $selRa->from(array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) ItemId,Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then c.ItemCode + ' - ' + c.BrandName Else a.Code + ' - ' + a.ResourceName End As value,
                           Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,Case When isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId,'Library' As RFrom ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $selRa::JOIN_LEFT)
                            ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId', array(), $selRa::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $selRa::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array(), $selRa::JOIN_LEFT)
                            ->where("a.TypeId=$RequestType and a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentre . ")
                          and (a.ResourceId Not IN (select ResourceId From VM_RequestTrans Where RequestId=$requestId) or isnull(c.BrandId,0) NOT IN (select ItemId From VM_RequestTrans Where RequestId=$requestId) )");
                        $select->combine($selRa, 'Union All');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    else if($RequestType == 4){

                        $select = $sql->select();
                        $select->from(array('A' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("C.ResourceId,B.Code,
                                (B.ResourceName + isnull((case when C.RateType='A' then '(Mechanical)' when C.RateType='M' then '(Manual)' end),'')) ResourceName,
                                D.UnitName,C.Rate,B.TypeId,D.UnitId,C.RateType,A.Quantity as CurrentQty,A.Quantity as HiddenQty,a.Specification")))
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_ProjectResource'), 'A.ResourceId=C.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('D' => 'Proj_UOM'), 'D.UnitId=B.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('E' => 'VM_RequestRegister'), 'E.RequestId=A.RequestId', array(), $select::JOIN_INNER)
                            ->join(array('F' => 'WF_OperationalCostCentre'), 'F.ProjectId=C.ProjectId', array(), $select::JOIN_INNER)
                            ->where("b.TypeId=$RequestType and E.RequestId=$requestId and
                                    F.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('A'=>'Proj_ProjectDetails'))
                            ->columns(array(new Expression("A.ProjectIOWId AS IowId,b.RefSerialNo As Code,b.Specification, E.ParentText + ' - ' + E.WBSName As WBSName,
                            Case When P.Qty <>0 Then (A.Qty/P.Qty)* D.Qty Else 0 End as Qty,
                                A.ResourceId,D.WBSId,CAST(0 As Decimal(18,3)) As CurrentQty,
                                CAST(0 As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('B' => 'Proj_ProjectIOWMaster'),'A.ProjectIOWId=B.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('D' => 'Proj_WBSTrans'),'A.ProjectIOWId=D.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('P' => 'Proj_ProjectIOW'),'A.ProjectIOWId=P.ProjectIOWId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'),'B.UnitId=C.UnitID',array(),$wbsSelect::JOIN_LEFT)
                            ->join(array('E' => 'Proj_WBSMaster'),'D.WBSId=E.WBSId',array(),$wbsSelect::JOIN_LEFT)
                            ->join(array('F' => 'WF_OperationalCostCentre'),'A.ProjectId=F.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where->Expression('A.IncludeFlag=1 and F.CostCentreId=' .$CostCentre. 'and
                            E.WBSId NOT IN (Select WbsId From VM_RequestIowTrans Where RequestTransId IN
                            (Select RequestTransId From VM_RequestTrans Where RequestId=?))', $requestId );
                            //$wbsSelect->order("B.SortId Desc");

                        $wbsSelect1 = $sql->select();
                        $wbsSelect1->from(array('a' => 'Proj_WBSTrans'))
                            ->columns(array(new Expression("B.IowId,S.RefSerialNo As Code,S.Specification,D.ParentText + ' - ' + D.WBSName As WBSName,
                            CAST(0 As Decimal(18,3)) As Qty,
                            C.ResourceId, B.WBSId as WBSId,
                            CAST(B.Qty As Decimal(18,3)) As CurrentQty,
                            CAST(B.Qty As Decimal(18,3)) As HiddenQty")))
                            ->join(array('B' => 'VM_RequestIowTrans'), 'A.ProjectIOWId=B.IOWId and B.WbsId=A.WBSId', array(), $wbsSelect1::JOIN_INNER)
                            ->join(array('C' => 'VM_RequestTrans'), 'C.RequestTransId=B.RequestTransId', array(), $wbsSelect1::JOIN_INNER)
                            ->join(array('D' => 'Proj_WBSMaster'), 'D.ProjectId=A.ProjectId and b.WbsId=d.WbsId', array(), $wbsSelect1::JOIN_INNER)
                            ->join(array('E' => 'Proj_UOM'), 'E.UnitId=C.UnitID', array(), $wbsSelect1::JOIN_LEFT)
                            ->join(array('F' => 'WF_OperationalCostCentre'), 'F.ProjectId=A.ProjectId', array(), $wbsSelect1::JOIN_INNER)
                            ->join(array('S' => 'Proj_ProjectIOWMaster'), 'S.ProjectIOWId=B.IOWId', array(), $wbsSelect1::JOIN_INNER)
                            ->where(array("F.CostCentreId=$CostCentre AND C.RequestId =$requestId "));
                        $wbsSelect1->combine($wbsSelect, 'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect1);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows=$arr_resource_iows;

                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectResource'))
                            ->columns(array(new Expression("A.ResourceId AS Data,
                            B.Code,(B.ResourceName + isnull((case when A.RateType='A' then '(Mechanical)' when A.RateType='M' then '(Manual)' end),'')) value,
                            C.UnitName,A.Rate,A.Qty as HQty,B.TypeId,C.UnitId,A.RateType,CAST(0 As Decimal(18,3)) As CurrentQty,
                            CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("b.TypeId=$RequestType and A.IncludeFlag=1 and A.ResourceId Not IN (select ResourceId from VM_RequestTrans where requestid=$requestId) AND
                             D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate-Activity-edit

                        $selR = $sql->select();
                        $selR->from(array('a' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("a.ResourceId as ResourceId")))
                            ->where(array("a.RequestId" => $requestId));
                        $statement = $sql->getSqlStringForSqlObject($selR);
                        $arr = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $result_Act = array();
                        $result_ResourceEst = array();

                        foreach($arr as $res) {
                            if (!in_array($res,$result_ResourceEst)) {

                                $result_ResourceEst[] = $res['ResourceId'];
                                $resourceId = $res['ResourceId'];
                            }

                            $select = $sql->select();
                            $select->from(array('A' => 'Proj_ProjectResource'))
                                ->columns(array(new Expression("A.Qty As EstimateQty")))
                                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.ProjectId=B.ProjectId', array(), $select::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_aEst = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['EstimateQty']= $arr_aEst['EstimateQty'];

                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_WOTrans'))
                                ->columns(array(new Expression("ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                                ->join(array('B' => 'WPM_WORegister'), 'A.WORegisterId=B.WORegisterId', array(), $select1::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($resourceId) and B.LiveWO=0 and A.RateType = 'L'");
                            $statement = $sql->getSqlStringForSqlObject($select1);
                            $arr_aEst1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['WOQty']= $arr_aEst1['WOQty'];

                            $select2 = $sql->select();
                            $select2->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(A.Quantity-A.CancelQty,0)),0) as ReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select2::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select2);
                            $arr_aEst2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['ReqQty']= $arr_aEst2['ReqQty'];

                            $select3 = $sql->select();
                            $select3->from(array('A' => 'VM_RequestTrans'))
                                ->columns(array(new Expression("ISNULL(Sum(ISNULL(((A.Quantity-A.CancelQty)-A.WOQty),0)),0) As BalReqQty ")))
                                ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $select3::JOIN_INNER)
                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($resourceId)");
                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $arr_aEst3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $result_Act[$resourceId]['BalReqQty']= $arr_aEst3['BalReqQty'];

                        }
                        $this->_view->arr_actEst =$result_Act;
                        $this->_view->result_ResourceEst =$result_ResourceEst;

                        //OverAll Estimate-ActivitY END

                        // WBS WISE -ESTIMATE -edit
//                        $result1_Act = array();
//                        $result1_ResourceEst = array();
//
//                        foreach($arr_resource_iows as $arr_iow){
//                            $iowId=$arr_iow['IowId'];
//                            $wbsId=$arr_iow['WBSId'];
//                           // print_r($arr_iow);
//
//                            if (!in_array($wbsId,$result1_ResourceEst)) {
//                                $result1_ResourceEst[] = $wbsId;
//                            }
//
//                            $select = $sql->select();
//                            $select->from(array('A' => 'Proj_ProjectWBSResource'))
//                                ->columns(array(new Expression("A.WbsId AS WbsId, A.Qty As EstimateQty")))
//                                ->join(array('B' => 'WF_OperationalCostCentre'),'A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
//                                ->join(array('C' => 'VM_RequestTrans'),'A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
//                                ->where("B.CostCentreId=$CostCentre and A.ResourceId IN ($resourceId) and A.WbsId = $wbsId");
//                            $statement = $sql->getSqlStringForSqlObject($select);
//                            $aEstimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                            if($aEstimate['EstimateQty'] > 0 || $aEstimate['EstimateQty'] != ''){
//
//                                $result1_Act[$wbsId]['EstimateQty']= $aEstimate['EstimateQty'];
//                                $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                $result1_Act[$wbsId]['IOWID']= $iowId;
//                                $result1_Act[$wbsId]['RsId']= $resourceId;
//                            } else {
//                                $result1_Act[$wbsId]['EstimateQty']= 0;
//                            }
//
//                            $select1 = $sql->select();
//                            $select1->from(array('A' => 'WPM_WOIOWTRans'))
//                                ->columns(array(new Expression("ISNULL(Sum(A.Qty),0) as WOQty,A.WbsId as WbsId,A.IOWID as IOWID")))
//                                ->join(array('B' => 'WPM_WOTrans'),'A.WOTransId=B.WOTransID',array(),$select1::JOIN_LEFT)
//                                ->join(array('C' => 'WPM_WORegister'),'B.WORegisterId=C.WORegisterId',array(),$select1::JOIN_LEFT)
//                                ->where("C.LiveWO=0 and A.IOWID = $iowId and A.WbsId = $wbsId AND C.CostCentreId=$CostCentre and
//                                 B.ResourceId IN ($resourceId)")
//                                ->group(array("A.IOWID","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select1);
//                            $arr_wEst1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                            if($arr_wEst1['WOQty'] > 0 || $arr_wEst1['WOQty'] != ''){
//                                $result1_Act[$wbsId]['WOQty']= $arr_wEst1['WOQty'];
//                                $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                $result1_Act[$wbsId]['IOWID']= $iowId;
//                                $result1_Act[$wbsId]['RsId']= $resourceId;
//                            } else {
//                                $result1_Act[$wbsId]['WOQty']= 0;
//                            }
//
//
//                            $select2 = $sql->select();
//                            $select2->from(array('A' => 'VM_RequestIowTrans'))
//                                ->columns(array(new Expression("ISNULL(SUM(A.Qty-A.CancelQty),0) As RequestQty,A.WbsId as WbsId,A.IowId as IOWID")))
//                                ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select2::JOIN_INNER)
//                                ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select2::JOIN_INNER)
//                                ->where("C.CostCentreId=$CostCentre and A.WbsId= $wbsId And A.IowId=$iowId and  B.ResourceId IN ($resourceId)")
//                                ->group(array("A.IowId","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select2);
//                            $arr_wEst2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                            if($arr_wEst2['RequestQty'] > 0 || $arr_wEst2['RequestQty'] != ''){
//                                $result1_Act[$wbsId]['RequestQty']= $arr_wEst2['RequestQty'];
//                                $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                $result1_Act[$wbsId]['IOWID']= $iowId;
//                                $result1_Act[$wbsId]['RsId']= $resourceId;
//                            } else {
//                                $result1_Act[$wbsId]['RequestQty']= 0;
//                            }
//
//                            $select3 = $sql->select();
//                            $select3->from(array('A' => 'VM_RequestIowTrans'))
//                                ->columns(array(new Expression("ISNULL(SUM((A.Qty-A.CancelQty)-A.WOQty),0) As BalReqQty,A.WbsId as WbsId,A.IowId as IOWID")))
//                                ->join(array('B' => 'VM_RequestTrans'),'A.RequestTransId=B.RequestTransId',array(),$select3::JOIN_INNER)
//                                ->join(array('C' => 'VM_RequestRegister'),'B.RequestId=C.RequestId',array(),$select3::JOIN_INNER)
//                                ->where("C.CostCentreId=$CostCentre and A.WbsId= $wbsId And A.IowId=$iowId and B.ResourceId IN ($resourceId)")
//                                ->group(array("A.IowId","A.WbsId"));
//                            $statement = $sql->getSqlStringForSqlObject($select3);
//                            $arr_wEst3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//                            if($arr_wEst3['BalReqQty'] > 0 || $arr_wEst3['BalReqQty'] != ''){
//                                $result1_Act[$wbsId]['BalReqQty']= $arr_wEst3['BalReqQty'];
//                                $result1_Act[$wbsId]['WbsId']= $wbsId;
//                                $result1_Act[$wbsId]['IOWID']= $iowId;
//                                $result1_Act[$wbsId]['RsId']= $resourceId;
//                            } else {
//                                $result1_Act[$wbsId]['BalReqQty']=0;
//                            }
//                            //print_r($result1_Act);
//                        }
//                       // die;
//                      //print_r($result1_Act); die;
//                        $this->_view->arr_act1 =$result1_Act;
//                        $this->_view->result1_ResourceEst =$result1_ResourceEst;
                        //END -WBS-WISE-ESTIMATE

                        $wResourceId=implode(",",$result_ResourceEst);

                        $result1_Act = array();

                            $select = $sql->select();
                            $select->from(array('A' => 'Proj_ProjectWBSResource'))
                                ->columns(array(new Expression("ISNULL(A.Qty,0) As EstimateQty,0 WOQty,0 RequestQty,
                                0 BalReqQty,A.ResourceId,A.WbsId,B.ProjectIOWId as IowId")))
                                ->join(array('B' => 'Proj_WBSTrans'), 'A.wbsid=B.wbsid and A.ProjectId=B.ProjectId', array(), $select::JOIN_INNER)
                                ->join(array('C' => 'WF_OperationalCostCentre'), 'A.ProjectId=C.ProjectId', array(), $select::JOIN_INNER)
                                ->where("C.CostCentreId=$CostCentre and A.ResourceId in( $wResourceId)");

                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_WOIOWTRans'))
                                ->columns(array(new Expression("0 EstimateQty,ISNULL(Sum(A.Qty),0) as WOQty,0 RequestQty,0 BalReqQty,
                                B.ResourceId,A.WbsId,A.IOWID as IowId")))
                                ->join(array('B' => 'WPM_WOTrans'), 'A.WOTransId=B.WOTransID', array(), $select1::JOIN_LEFT)
                                ->join(array('C' => 'WPM_WORegister'), 'B.WORegisterId=C.WORegisterId', array(), $select1::JOIN_LEFT)
                                ->where("C.LiveWO=0 and C.CostCentreId=$CostCentre and B.ResourceId in( $wResourceId ) ")
                                ->group(array("A.IOWID", "A.WbsId", "B.ResourceId"));
                            $select1->combine($select, 'Union All');

                            $select2 = $sql->select();
                            $select2->from(array('A' => 'VM_RequestIowTrans'))
                                ->columns(array(new Expression("0 EstimateQty,0 WOQty,0 As RequestQty,ISNULL(SUM(A.BalQty),0) BalReqQty,
                                B.ResourceId,A.WbsId,A.IowId as IowId")))
                                ->join(array('B' => 'VM_RequestTrans'), 'A.RequestTransId=B.RequestTransId', array(), $select2::JOIN_INNER)
                                ->join(array('C' => 'VM_RequestRegister'), 'B.RequestId=C.RequestId', array(), $select2::JOIN_INNER)
                                ->where("C.CostCentreId=$CostCentre And B.ResourceId in( $wResourceId )")
                                ->group(array("A.IowId", "A.WbsId", "B.ResourceId"));
                            $select2->combine($select1, 'Union All');


                            $select3 = $sql->select();
                            $select3->from(array('G' => $select2))
                                ->columns(array(new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                                                CAST(ISNULL(SUM(G.WOQty),0) As Decimal(18,3)) As WOQty,
                                                CAST(ISNULL(SUM(G.RequestQty),0) As Decimal(18,3)) As RequestQty,
                                                CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3)) As BalReqQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) > 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As AvailableQty,
                                                Case When CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) < 0 Then
                                                CAST(ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.WOQty),0)+ISNULL(SUM(G.BalReqQty),0)) As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End As ExcessQty,
                                                G.ResourceId,G.WbsId,G.IowId as IowId")))
                                ->group(array("G.IowId", "G.WbsId", "G.ResourceId"));
                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $result1_Act = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->arr_act1 =$result1_Act;

                    }
                    else if ($RequestType == 5) {

                        $select = $sql->select();
                        $select->from(array('a' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("a.IowId As ResourceId,
                            c.RefSerialNo As Code,c.Specification As ResourceName,
                            d.UnitName,d.UnitId,Cast(a.Quantity As Decimal(18,3)) As CurrentQty,Cast(a.Quantity As Decimal(18,3)) As HiddenQty,'Project' As RFrom,a.Specification ")))
                            ->join(array('b' => 'Proj_ProjectIow'),'a.IowId=b.ProjectIowId',array(),$select::JOIN_INNER)
                            ->join(array('c' => 'Proj_ProjectIowMaster'), 'b.ProjectIowId=c.ProjectIowId and b.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'Proj_UOM'), 'c.UnitId=d.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'VM_RequestRegister'),'a.RequestId=e.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('f' => 'WF_OperationalCostCentre'), 'b.ProjectId=f.ProjectId and e.CostCentreId=f.CostCentreId', array(), $select::JOIN_INNER)
                            ->where("a.RequestId=$requestId and e.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a'=>'VM_RequestWbsTrans'))
                            ->columns(array(new Expression("b.IowId,f.ParentText + ' - ' + f.WBSName As WBSName,
                                b.IowId As ResourceId,a.WBSId,CAST(a.Qty As Decimal(18,3)) As CurrentQty,
                                CAST(a.Qty As Decimal(18,3)) As HiddenQty") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.RequestTransId=b.RequestTransId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('d' => 'Proj_WbsTrans'),'a.WbsId=d.WbsId and b.IowId=d.ProjectIowId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('e' => 'Proj_ProjectIOWMaster'),'d.ProjectIOWId=e.ProjectIOWId and d.ProjectId=e.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('f' => 'Proj_WbsMaster'),'d.WbsId=f.WbsId and d.ProjectId=f.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'d.ProjectId=g.ProjectId and c.CostCentreId=g.CostCentreId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("c.RequestId=$requestId and f.LastLevel=1 and c.CostCentreId=$CostCentre"));

                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows = $arr_resource_iows;

                        //auto-complete-edit
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ProjectIow'))
                            ->columns(array(new Expression("A.ProjectIowId As Data,
                            B.RefSerialNo As Code,b.Specification As value,
                            C.UnitName,C.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'Proj_ProjectIowMaster'), 'A.ProjectIowId=B.ProjectIowId and a.ProjectId=b.ProjectId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'), 'B.UnitId=C.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('D' => 'WF_OperationalCostCentre'), 'A.ProjectId=D.ProjectId', array(), $select::JOIN_INNER)
                            ->where("A.ProjectIowId NOT IN (Select IowId From VM_RequestTrans Where RequestId=$requestId) and D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate - IOW
                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_WOTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As WOQty")))
                            ->join(array('b' => 'WPM_WORegister'),'a.WORegisterId=b.WORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.LiveWO=0 And b.CostCentreId=$CostCentre and a.IowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel -> group(new Expression("a.IowId"));


                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.IowId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and b.RequestType='IOW' and a.IowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel1 -> group(new Expression("a.Iowid"));
                        $sel1->combine($sel,"Union All");


                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_ProjectIow"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As WOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ProjectIowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel2->group(new Expression("a.ProjectIowId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.WOQty,0)),0) As Decimal(18,3)) As WOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.IowId"));

                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $this->_view->arr_iowestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        //End OverAll Estimate - IOW

                        //Get Wbs wise estimate - IOW
                        $sel = $sql -> select();
                        $sel->from(array("a" => "VM_RequestWbsTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                           ISNULL(SUM(ISNULL(a.Qty-a.CancelQty,0)),0) As RequestQty,ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalReqQty,
                           CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'VM_RequestTrans'),'a.RequestTransId=b.RequestTransId',array(),$sel::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel::JOIN_INNER)
                            ->where("c.RequestType='IOW' and c.CostCentreId=$CostCentre and b.IowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId) ");
                        $sel->group(new Expression("b.IowId,a.WbsId"));

                        $sel1 = $sql -> select();
                        $sel1->from(array("a" => "WPM_WOWBSTrans"))
                            ->columns(array(new Expression("b.IowId,a.WbsId,CAST(0 As Decimal(18,3)) As EstimateQty,
                          CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                          ISNULL(SUM(ISNULL(a.Qty,0)),0) As WOQty ")))
                            ->join(array('b' => 'WPM_WOTrans'),'a.WOTransId=b.WOTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => 'WPM_WORegister'),'b.WORegisterId=c.WORegisterId',array(),$sel1::JOIN_INNER)
                            ->where("c.LiveWO=0 and c.CostCentreId=$CostCentre and b.IowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel1->group(new Expression("b.IowId,a.WbsId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "Proj_WbsTrans"))
                            ->columns(array(new Expression("a.ProjectIowId As IowId,a.WbsId,ISNULL(SUM(ISNULL(a.Qty,0)),0) As EstimateQty,
                           CAST(0 As Decimal(18,3)) As RequestQty,CAST(0 As Decimal(18,3)) As BalReqQty,CAST(0 As Decimal(18,3)) As WOQty ")))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIowId=b.ProjectIowId and a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => 'Proj_WbsMaster'),'a.WbsId=c.WbsId and b.ProjectId=c.ProjectId',array(),$sel2::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("c.LastLevel=1 and d.CostCentreId=$CostCentre and a.ProjectIowId IN (Select IowId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel2->group(new Expression("a.ProjectIowId,a.WbsId"));
                        $sel2->combine($sel1,"Union All");

                        $sel3 = $sql -> select();
                        $sel3 ->from(array("g" => $sel2))
                            ->columns(array(new Expression("g.IowId,g.WbsId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                             CAST(ISNULL(SUM(g.RequestQty),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(g.BalReqQty),0) As Decimal(18,3)) As BalRequestQty,
                             CAST(ISNULL(SUM(g.WOQty),0) As Decimal(18,3)) As WOQty,
                             CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.WOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalReqQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty ")));
                        $sel3->group(new Expression("g.IowId,g.WbsId"));


                        $statement = $sql->getSqlStringForSqlObject( $sel3 );
                        $this->_view->arr_iowwbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        // End Wbs wise estimate - IOW

                    }
                    else if ($RequestType == 6) {
                        $select = $sql->select();
                        $select->from(array('A' => 'VM_RequestTrans'))
                            ->columns(array(new Expression("A.ResourceId,
                            C.ServiceCode As Code,C.ServiceName As ResourceName,
                            D.UnitName,A.UnitId,Cast(A.Quantity As Decimal(18,3)) As Qty,Cast(A.Quantity As Decimal(18,3)) As HiddenQty,'Library' As RFrom,A.Specification ")))
                            ->join(array('B' => 'VM_RequestRegister'),'A.RequestId=B.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('C' => 'Proj_ServiceMaster'),'A.ResourceId=C.ServiceId',array(),$select::JOIN_INNER)
                            ->join(array('D' => 'Proj_UOM'), 'A.UnitId=D.UnitId', array(), $select::JOIN_LEFT)
                            ->where("A.RequestId=$requestId and B.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_ServiceMaster'))
                            ->columns(array(new Expression("A.ServiceId As Data,
                            A.ServiceCode As Code,a.ServiceName As value,
                            B.UnitName,B.UnitId,Cast(0 As Decimal(18,3)) As Qty,'Library' As RFrom ")))
                            ->join(array('B' => 'Proj_UOM'), 'A.UnitId=B.UnitId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_OHService'),'A.ServiceId=C.ServiceId',array(),$select::JOIN_INNER)
                            ->join(array('D' => 'WF_OperationalCostCentre'),'C.ProjectId=D.ProjectId',array(),$select::JOIN_INNER)
                            ->where("A.ServiceId NOT IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId ) and D.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //OverAll Estimate - IOW

                        $sel = $sql -> select();
                        $sel->from(array("a" => "WPM_SOServiceTrans"))
                            ->columns(array(new Expression("a.ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,Cast(0 As Decimal(18,3)) As RequestQty,
                            Cast(0 As Decimal(18,3)) As BalRequestQty,ISNULL(SUM(ISNULL(A.Qty,0)),0) As SOQty")))
                            ->join(array('b' => 'WPM_SORegister'),'a.SORegisterId=b.SORegisterId',array(),$sel::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ServiceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel -> group(new Expression("a.ServiceId"));

                        $sel1 = $sql -> select();
                        $sel1 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array(new Expression("a.ResourceId As ServiceId,Cast(0 As Decimal(18,3)) As EstimateQty,ISNULL(Sum(ISNULL(a.Quantity-a.CancelQty,0)),0) As RequestQty,
                           ISNULL(SUM(ISNULL(a.BalQty,0)),0) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty ")))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and b.RequestType='Service' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel1 -> group(new Expression("a.ResourceId"));
                        $sel1->combine($sel,"Union All");

                        $sel2 = $sql -> select();
                        $sel2 -> from(array("a" => "Proj_OHService"))
                            ->columns(array(new Expression("a.ServiceId,ISNULL(SUM(ISNULL(a.Amount,0)),0) As EstimateQty,
                          Cast(0 As Decimal(18,3)) As RequestQty,Cast(0 As Decimal(18,3)) As BalRequestQty,Cast(0 As Decimal(18,3)) As SOQty  ")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel2::JOIN_INNER)
                            ->where("b.CostCentreId=$CostCentre and a.ServiceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId)");
                        $sel2->group(new Expression("a.ServiceId"));
                        $sel2->combine($sel1,"Union All");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("g" => $sel2))
                            ->columns(array(new Expression("g.ServiceId,CAST(ISNULL(SUM(g.EstimateQty),0) As Decimal(18,3)) As EstimateQty,
                            CAST(ISNULL(SUM(ISNULL(g.RequestQty,0)),0) As Decimal(18,3)) As RequestQty,CAST(ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0) As Decimal(18,3)) As BalRequestQty,
                            CAST(ISNULL(SUM(ISNULL(g.SOQty,0)),0) As Decimal(18,3)) As SOQty,
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) As AvailableQty,
                            Case When CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) < 0 Then
                            CAST((ISNULL(SUM(ISNULL(g.EstimateQty,0)),0)-(ISNULL(SUM(ISNULL(g.SOQty,0)),0)+ISNULL(SUM(ISNULL(g.BalRequestQty,0)),0))) As Decimal(18,3)) Else Cast(0 As Decimal(18,3)) End As ExcessQty  ")));
                        $sel3->group(new Expression("g.ServiceId"));

                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $this->_view->arr_iowestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                         }
                    else if ($RequestType == 7) {

                        $select = $sql->select();
                        $select->from(array('A' => 'VM_RequestTurnKey'))
                            ->columns(array(new Expression("A.WBSId As ResourceId,
                            B.ParentText As Code,B.WBSName As ResourceName,
                            '' As UnitName,0 As UnitId,Cast(A.Percentage As Decimal(18,3)) As Qty,'Project' As RFrom,A.Specification As Specification ")))
                            ->join(array('B' => 'Proj_WbsMaster'),'A.WbsId=B.WbsId',array(),$select::JOIN_INNER)
                            ->where("B.LastLevel=1 and A.WBSId IN (Select WbsId From VM_RequestTurnKey Where RequestId=$requestId)");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //auto-complete-add
                        $select = $sql->select();
                        $select->from(array('A' => 'Proj_WbsMaster'))
                            ->columns(array(new Expression("A.WBSId As Data,
                            A.ParentText As Code,a.WBSName As value,
                            '' As UnitName,0 As UnitId,Cast(0 As Decimal(18,3)) As Qty,'Project' As RFrom ")))
                            ->join(array('B' => 'WF_OperationalCostCentre'),'A.ProjectId=B.ProjectId',array(),$select::JOIN_INNER)
                            ->where("A.LastLevel=1 and A.WbsId NOT IN (Select WbsId From VM_RequestTurnKey Where RequestId=$requestId) and B.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $wbsRes = $sql->select();
                    $wbsRes->from(array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $wbsRes::JOIN_INNER)
                        ->join(array('c' => 'Proj_WBSTrans'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery1 = $sql->select();
                    $subQuery1->from("VM_RequestTrans")
                        ->columns(array('ResourceId'))
                        ->where('RequestId IN (' . $requestId . ')');

                    $subQuery2 = $sql->select();
                    $subQuery2->from("VM_RequestTrans")
                        ->columns(array('ItemId'))
                        ->where('RequestId IN (' . $requestId . ')');

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                        ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then  d.BrandName Else  a.ResourceName End As ResourceName,
                            Case when isnull(d.BrandId,0)>0 Then c.UnitName Else f.UnitName End As UnitName,Case when isnull(d.BrandId,0)>0 Then c.UnitId Else f.UnitId End As UnitId ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_Uom'), 'd.UnitId=f.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.TypeId=$RequestType and g.CostCentreId=$CostCentre");
                    $select->where->expression('a.ResourceId Not IN ?', array($subQuery1));
                    $select->where->expression('isnull(d.BrandId,0) Not IN ?', array($subQuery2));

                    $selRa = $sql->select();
                    $selRa->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId As ResourceId,isnull(c.BrandId,0) ItemId,Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then c.BrandName Else a.ResourceName End As ResourceName,
                            Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,Case when isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $selRa::JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId', array(), $selRa::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $selRa::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array(), $selRa::JOIN_LEFT)
                        ->where("a.TypeId=$RequestType and a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentre . ")
                           and (a.ResourceId Not IN (select ResourceId From VM_RequestTrans Where RequestId=$requestId) or isnull(c.BrandId,0) NOT IN (select ItemId From VM_RequestTrans Where RequestId=$requestId) )");

                    $select->combine($selRa, 'Union All');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Get EstimateQty,AvailableQty
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty' => new Expression("Cast(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDc' => new Expression("CAST(0 As Decimal(18,3))"),'TotBill' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRet' => new Expression("CAST(0 As Decimal(18,3))"),'TotTran' => new Expression("CAST(0 As Decimal(18,3))")  ))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ("b.CostCentreId=$CostCentre And a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) ");


                    $sel1 = $sql -> select();
                    $sel1 -> from (array("a" => "VM_RequestTrans" ))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                   CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,CAST(0 As Decimal(18,3)) As POQty,
                                   CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                   CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                        ->join(array('b' => "VM_RequestRegister"),'a.RequestId=b.RequestId',array(),$sel1::JOIN_INNER)
                        ->where ("a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) and b.CostCentreId=$CostCentre ");
                    $sel1->group(new Expression("a.ResourceId"));
                    $sel1->combine($sel,'Union All');


                    $sel2 = $sql->select();
                    $sel2->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel2::JOIN_INNER)
                        ->Where ("b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) And b.CostCentreId=$CostCentre And c.General=0");
                    $sel2->group(new Expression("a.ResourceId"));
                    $sel2->combine($sel1,'Union ALL');

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,ISNULL(SUM(A.Quantity-A.CancelQty),0) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran ") ))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel3::JOIN_INNER)
                        ->where("a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) and b.CostCentreId=$CostCentre");
                    $sel3->group(new Expression("a.ResourceId"));
                    $sel3->combine($sel2,'Union All');

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3)) As POQty,
                                  CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran  ") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel4::JOIN_INNER)
                        ->where("a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId=$CostCentre and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) ");
                    $sel4->group(new Expression("a.ResourceId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5->from(array("a" => "MMS_DCTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,
                                  CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel5::JOIN_INNER)
                        ->where("A.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) And B.CostCentreId=$CostCentre And B.General=0 ");
                    $sel5->group(new Expression("a.ResourceId"));
                    $sel5->combine($sel4,"Union ALL");

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where("b.ThruPO='Y' And a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) and b.CostCentreId=$CostCentre and b.General=0 ");
                    $sel6->group(new Expression("a.ResourceId"));
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array(new Expression("a.ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3)) As TotRet,CAST(0 As Decimal(18,3)) As TotTran")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where("a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) And b.CostCentreId=$CostCentre");
                    $sel7->group(new Expression("a.ResourceId"));
                    $sel7->combine($sel6,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId' => new Expression("a.ResourceId"), 'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel8::JOIN_INNER)
                        ->where("a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) and b.ToCostCentreId=$CostCentre ");
                    $sel8->group(new Expression("a.ResourceId"));

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId' => new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel9::JOIN_INNER)
                        ->where("a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId=$requestId) and b.FromCostCentreId=$CostCentre");
                    $sel9->group(new Expression("a.ResourceId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("A"=>$sel9))
                        ->columns(array(new Expression("ResourceId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(0 As Decimal(18,3)) As BalReqQty,
                                  CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                  CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As TotDc,CAST(0 As Decimal(18,3)) As TotBill,
                                  CAST(0 As Decimal(18,3)) As TotRet,CAST(SUM(TotTranQty) As Decimal(18,3)) As TotTran")));
                    $sel10->group(new Expression("ResourceId"));
                    $sel10 -> combine($sel7,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("G"=>$sel10))
                        ->columns(array('ResourceId' =>new Expression("G.ResourceId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0))) As Decimal(18,3)) Else 0 End"),
                            'BalReqQty' => new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDc),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBill),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDC),0)+ISNULL(SUM(G.TotBill),0)-ISNULL(SUM(G.TotRet),0)+ISNULL(SUM(G.TotTran),0) As Decimal(18,3))"),
                            'IsProj'=>new Expression("(Select Count(A.ResourceId) From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentre And A.ResourceId=G.ResourceId)")));
                    $sel11->group(new Expression("G.ResourceId"));

                    $statement = $sql->getSqlStringForSqlObject( $sel11 );
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Get Wbs Estimate Details
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'BalReqQty' => new Expression("Cast(0 As Decimal(18,3))"), 'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where (' b.CostCentreId=' . $CostCentre .' And ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') And
                                 b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId
                                 IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('A.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') And c.CostCentreId='.$CostCentre .' And c.General=0
                                And a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and
                                c.CostCentreId='.$CostCentre.' and c.General=0 And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') And c.CostCentreId='.$CostCentre.' And a.AnalysisId
                                IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and c.ToCostCentreId='.$CostCentre.' And
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel5->group(new Expression("a.ResourceId,a.AnalysisId"));

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and c.FromCostCentreId='.$CostCentre.' And
                             a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .'))');
                    $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.WBSId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                    $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and c.CostCentreId='.$CostCentre.' and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .'))');
                    $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and
                                a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'), 'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and
                                 a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId' => new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')
                               and a.AnalysisId IN (Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .')) ');
                    $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from (array("a" => "VM_RequestAnalTrans" ))
                        ->columns(array(new Expression('a.ResourceId,a.AnalysisId As WBSId,CAST(0 As Decimal(18,3)) As EstimateQty,CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3)) As BalReqQty,
                                CAST(0 As Decimal(18,3)) As BalPOQty,CAST(0 As Decimal(18,3)) As TotDCQty,CAST(0 As Decimal(18,3)) As TotBillQty,
                                CAST(0 As Decimal(18,3)) As TotRetQty,CAST(0 As Decimal(18,3)) As TotTranQty,CAST(0 As Decimal(18,3)) As ReqQty,
                                CAST(0 As Decimal(18,3)) As POQty,CAST(0 As Decimal(18,3)) As DCQty,CAST(0 As Decimal(18,3)) As BillQty ')))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId and a.ResourceId=b.ResourceId',array(),$sel12::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel12::JOIN_INNER)
                        ->where('c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_RequestTrans Where RequestId='. $requestId .') )');
                    $sel12->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel12->combine($sel11,"Union ALL");

                    $sel13 = $sql -> select();
                    $sel13 -> from(array("G"=>$sel12))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalReqQty),0)+ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                            'IsProj'=>new Expression("(Select Count(WBSId) From Proj_ProjectDetails A
                                                       Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                                       Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
                                                       Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                                       Where A.IncludeFlag=1 and D.CostCentreId=$CostCentre and A.ResourceId=G.ResourceId And C.WBSId=G.WBSId)")    ));
                    $sel13->group(new Expression("G.ResourceId,G.WBSId"));
                    $statement = $sql->getSqlStringForSqlObject( $sel13 );
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //
                }

            }
//            //Common function
//            CommonHelper::getVoucherNo(201, date('Y/m/d'), 0, 0, $dbAdapter, "I");
//            // general
//            $aVNo = CommonHelper::getVoucherNo(201, date('Y/m/d'), 0, 0, $dbAdapter, "");
//            $this->_view->genType = $aVNo["genType"];
//            if (!$aVNo["genType"]) {
//                $this->_view->woNo = "";
//            }
//            else{
//                $this->_view->woNo = $aVNo["voucherNo"];
//            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function sampleentAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(201,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $CostCenterId= $this->bsf->isNullCheck($postParams['CostCenterId'],'number');
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string');
                $WorkType= $this->bsf->isNullCheck($postParams['WorkType'],'string');

                $whereCond = array("a.CostCentreId"=>$CostCenterId);
                if($RequestType == 'Material') {
                    $RequestType=2;
                }
                else if($RequestType == 'Asset'){
                    $RequestType=3;
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName ") ))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                    ->join(array('e' => 'WF_OperationalCostCentre'),'c.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                    ->where("a.TypeId = $RequestType and e.CostCentreId=$CostCenterId ");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('resources' => $requestResources)));
                return $response;
            }
        } else {
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $postData = $request->getPost();

//                echo"<pre>";
//                print_r($postData);
//                echo"</pre>";
//                die;
//                return;
                    $requestId =$postData['RequestId'];
                    $this->_view->requestId=$requestId;
                    $RequestType = $this->bsf->isNullCheck($postData['RequestType'], 'string');


                    if ($this->bsf->isNullCheck($requestId, 'number') > 0) {
                        $Approve="E";
                        $Role="Request-Modify";
                    }else{
                        $Approve="N";
                        $Role="Request-Create";
                    }


                    if (isset($postData['RequestId']) && $postData['RequestId'] != 0) {
                        $voucherNo = $postData['voucherNo'];
                        $CostCenterId = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                        $getCompany = $sql -> select();
                        $getCompany->from("WF_OperationalCostCentre")
                            ->columns(array("CompanyId"));
                        $getCompany->where(array('CostCentreId'=>$CostCenterId));
                        $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                        $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');

                        //CompanyId
                        $CCrno = CommonHelper::getVoucherNo(201, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                        $this->_view->CCrno = $CCrno;
                        //CostCenterId
                        $CRNo = CommonHelper::getVoucherNo(201, date('Y/m/d'), 0, $CostCenterId, $dbAdapter, "");
                        $this->_view->CRNo = $CRNo;

                        $connection = $dbAdapter->getDriver()->getConnection();
                        $connection->beginTransaction();
                        try{

                            $getCompany = $sql -> select();
                            $getCompany->from("WF_OperationalCostCentre")
                                ->columns(array("CompanyId"));
                            $getCompany->where(array('CostCentreId'=>$CostCenterId));
                            $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                            $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');

                            $subQuery   = $sql->select();
                            $subQuery->from("VM_RequestTrans")
                                ->columns(array("RequestTransId"));
                            $subQuery->where(array('RequestId'=>$requestId));

                            $select = $sql->delete();
                            $select->from('VM_RequestAnalTrans')
                                ->where->expression('ReqTransId IN ?',
                                    array($subQuery));
                            $WBSTransStatement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select1 = $sql->delete();
                            $select1->from('VM_RequestIowTrans')
                                ->where->expression('RequestTransId IN ?',
                                    array($subQuery));
                            $WBSTransStatement = $sql->getSqlStringForSqlObject($select1);
                            $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $selReqWbs = $sql->delete();
                            $selReqWbs->from('VM_RequestWbsTrans')
                                ->where->expression('RequestTransId IN ?',
                                    array($subQuery));
                            $rewwbsStatement = $sql->getSqlStringForSqlObject($selReqWbs);
                            $dbAdapter->query($rewwbsStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $selReqTkey = $sql -> delete();
                            $selReqTkey->from('VM_RequestTurnKey')
                                ->where(array('RequestId'=>$requestId));
                            $tkeyStatement = $sql->getSqlStringForSqlObject($selReqTkey);
                            $dbAdapter->query($tkeyStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //delete RequestTrans
                            $select = $sql->delete();
                            $select->from("VM_RequestTrans")
                                ->where(array('RequestId'=>$requestId));

                            $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
                            $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //update RequestRegister
                            $update = $sql->update();
                            $update->table('VM_RequestRegister');
                            $update->set(array(
                                'RequestDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ReqDate'],date))),
                                'RequestNo' => $this->bsf->isNullCheck($postData['RequestNo'],'string'),
                                'Narration' => $this->bsf->isNullCheck($postData['Narration'],'string'),
                                'ModifiedDate' => new Expression("getDate()")
                            ));
                            $update->where(array('RequestId'=>$requestId));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $resTotal = $postData['rowid'];
                            for ($i = 1; $i < $resTotal; $i++) {
								if($this->bsf->isNullCheck($postData['qty_' . $i],'number') > 0) {
                                    if($RequestType == 'IOW') {
                                        $requestInsert = $sql->insert('VM_RequestTrans');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "IowId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ResourceId" => 0,
                                            "Quantity" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "BalQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                            "ReqDate" => date('Y-m-d', strtotime($postData['reqdate_' . $i])),
                                            "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
                                    else if($RequestType == 'TurnKey') {
                                        $requestInsert = $sql->insert('VM_RequestTurnKey');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "WbsId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "Percentage" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
                                    else {
                                        $requestInsert = $sql->insert('VM_RequestTrans');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                            "Quantity" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "BalQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                            "ReqDate" => date('Y-m-d', strtotime($postData['reqdate_' . $i])),
                                            "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
									$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
									$dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
									$requestTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

									$wbsTotal = $postData['iow_' . $i . '_rowid'];
									for ($j = 1; $j <= $wbsTotal; $j++) {
										if ($this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number') > 0) {

                                            if($RequestType == 'Activity'){
                                                $requestITransInsert = $sql->insert('VM_RequestIowTrans');
                                                $requestITransInsert->values(array(
                                                    "RequestTransId" => $requestTransId,
                                                    "WbsId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "IowId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number'),
                                                    "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number')
                                                ));
                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestITransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                            else if($RequestType == 'IOW'){
                                                $requestITransInsert = $sql->insert('VM_RequestWbsTrans');
                                                $requestITransInsert->values(array(
                                                    "RequestTransId" => $requestTransId,
                                                    "WbsId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number')
                                                ));
                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestITransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                            else{
                                                $requestTransInsert = $sql->insert('VM_RequestAnalTrans');
                                                $requestTransInsert->values(array(
                                                    "ReqTransId" => $requestTransId,
                                                    "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                    "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                    "ReqQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number')
                                                ));

                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
										}
									}
								}
                            }
                            $connection->commit();
                            CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Request',$requestId,$CostCenterId,$CompanyId,'Vendor',$this->bsf->isNullCheck($postData['RequestNo'],'string'),$this->auth->getIdentity()->UserId,0,0);
                            $this->redirect()->toRoute('ats/request-detailed', array('controller' => 'index', 'action' => 'display-register'));
                        }
                        catch (PDOException $e) {
                            $connection->rollback();
                            print "Error!: " . $e->getMessage() . "</br>";
                        }
                    } else {

                        $RequestDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ReqDate'], 'string')));
                        $CostCenterId = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                        $RequestType = $this->bsf->isNullCheck($postData['RequestType'], 'string');
                        $CCRNo=$this->bsf->isNullCheck($postData['CCReqNo'],'string');
                        $RNo=$this->bsf->isNullCheck($postData['CReqNo'],'string');
                        $CreatedDate = date('Y-m-d');
                        $ModifiedDate = date('Y-m-d');
                        $Narration = $this->bsf->isNullCheck($postData['Narration'], 'string');
                        $Priority=$this->bsf->isNullCheck($postData['Priority'],'number');
                        $gridtype=$this->bsf->isNullCheck($postData['gridtype'],'number');


                        //Get CompanyId
                        $getCompany = $sql -> select();
                        $getCompany->from("WF_OperationalCostCentre")
                            ->columns(array("CompanyId"));
                        $getCompany->where(array('CostCentreId'=>$CostCenterId));
                        $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                        $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');
                        //

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(201, date('Y/m/d', strtotime($postData['ReqDate'])), 0, 0, $dbAdapter, "I");
                            $voucherNo = $voucher['voucherNo'];
                        } else {
                            $voucherNo = $postData['RequestNo'];
                        }
                        $RequestNo = $voucherNo;

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(201, date('Y/m/d', strtotime($postData['ReqDate'])), 0, $CostCenterId, $dbAdapter, "I");
                            $CCRNo = $voucher['voucherNo'];
                        } else {
                            $CCRNo = $CCRNo;
                        }

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(201, date('Y/m/d', strtotime($postData['ReqDate'])), $CompanyId, 0, $dbAdapter, "I");
                            $RNo = $voucher['voucherNo'];
                        } else {
                            $RNo = $RNo;
                        }

                        $connection = $dbAdapter->getDriver()->getConnection();
                        $connection->beginTransaction();
                        try {
                            //Get CompanyId
                            $getCompany = $sql -> select();
                            $getCompany->from("WF_OperationalCostCentre")
                                ->columns(array("CompanyId"));
                            $getCompany->where(array('CostCentreId'=>$CostCenterId));
                            $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                            $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');
                            //

                            $registerInsert = $sql->insert('VM_RequestRegister');
                            $registerInsert->values(array(
                                "RequestDate" => $RequestDate,
                                "RequestType" => $RequestType,
                                "CostCentreId" => $CostCenterId,
                                "RequestNo" => $RequestNo,
                                "CCReqNo" => $CCRNo,
                                "CReqNo" => $RNo,
                                "Approve" => 'N',
                                "CreatedDate" => $CreatedDate,
                                "ModifiedDate" => $ModifiedDate,
                                "Narration" => $Narration,
                                "Priority" => $Priority,
                                "GridType" => $gridtype
                            ));
                            $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                            $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $requestId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $resTotal = $postData['rowid'];

                            for ($i = 1; $i < $resTotal; $i++) {
								if($this->bsf->isNullCheck($postData['qty_' . $i],'number') > 0) {
                                    if($RequestType == 'IOW') {
                                        $requestInsert = $sql->insert('VM_RequestTrans');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "IowId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ResourceId" => 0,
                                            "Quantity" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "BalQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                            "ReqDate" => date('Y-m-d', strtotime($postData['reqdate_' . $i])),
                                            "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
                                    else if($RequestType == 'TurnKey') {
                                        $requestInsert = $sql->insert('VM_RequestTurnKey');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "WbsId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "Percentage" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
                                    else {
                                        $requestInsert = $sql->insert('VM_RequestTrans');
                                        $requestInsert->values(array(
                                            "RequestId" => $requestId,
                                            "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                            "Quantity" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "BalQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                            "ReqDate" => date('Y-m-d', strtotime($postData['reqdate_' . $i])),
                                            "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                            "Specification" => $this->bsf->isNullCheck($postData['resspec_' . $i], 'string')
                                        ));
                                    }
									$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
									$dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
									$requestTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

									$wbsTotal = $postData['iow_' . $i . '_rowid'];
									for ($j = 1; $j <= $wbsTotal; $j++) {
										if ($this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number') > 0) {

                                            if($RequestType == 'Activity'){
                                                $requestITransInsert = $sql->insert('VM_RequestIowTrans');
                                                $requestITransInsert->values(array(
                                                    "RequestTransId" => $requestTransId,
                                                    "WbsId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    //"ResourceId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_resid_' . $j], 'number'),
                                                    "IowId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number'),
                                                    "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number')
                                                ));
                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestITransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                            else if($RequestType == 'IOW'){
                                                $requestITransInsert = $sql->insert('VM_RequestWbsTrans');
                                                $requestITransInsert->values(array(
                                                    "RequestTransId" => $requestTransId,
                                                    "WbsId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number')
                                                ));
                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestITransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                            else {
                                                $requestTransInsert = $sql->insert('VM_RequestAnalTrans');
                                                $requestTransInsert->values(array(
                                                    "ReqTransId" => $requestTransId,
                                                    "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                    "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                    "ReqQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number')
                                                ));
                                                $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                                $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
										}
									}
								}
                            }
                            $connection->commit();
                            CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Request',$requestId,$CostCenterId,$CompanyId,'Vendor',$this->bsf->isNullCheck($postData['RequestNo'],'string'),$this->auth->getIdentity()->UserId,0,0);
                           // $this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));


                           $this->redirect()->toRoute('ats/request-detailed', array('controller' => 'index', 'action' => 'display-register'));
                        } catch (PDOException $e) {
                            $connection->rollback();
                            print "Error!: " . $e->getMessage() . "</br>";
                        }
                    }
                }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

	public function requestCancelAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
        $response = $this->getResponse();

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$data = $request->getPost();
				if($data['mode']=="select"){			
					$select = $sql->select();
					$select->from(array("RT"=>"VM_RequestTrans"))
							->columns(array(
							'Code'=> new Expression('R.Code'),
							'Resource'=> new Expression('R.ResourceName'),
							'ReqQty'=> new Expression('RT.Quantity'),
							'IndentQty'=> new Expression('RT.IndentQty'),
							'TransferQty'=> new Expression('RT.TransferQty'),
							'BalanceQty'=> new Expression('Cast(RT.BalQty As Decimal(18,5))'),
							'CancelQty'=> new Expression('Cast(RT.BalQty As Decimal(18,5))'),
							'Unit'=> new Expression('U.UnitName'),
							'RequestId'=> new Expression('RT.RequestId'),
							'RequestTransId'=> new Expression('RT.RequestTransId'),
							'UnitId'=> new Expression('RT.UnitId'),
							'ResourceId'=> new Expression('RT.ResourceId'),
							'HiddenQty'=> new Expression('RT.BalQty'),
							'CancelRemarks'=> new Expression('RT.CancelRemarks')))
							->join(array('U' => 'Proj_UOM'), 'RT.UnitId=U.UnitID', array(), $select::JOIN_INNER)
							->join(array('R' => 'Proj_Resource'), 'R.ResourceID =RT.ResourceId', array(), $select::JOIN_INNER)
							->join(array('RR' => 'VM_RequestRegister'), 'RT.RequestId=RR.RequestId', array(), $select::JOIN_INNER)
						->where(array("RT.BalQty>0 AND RR.Approve='Y'","RR.RequestId"=>$data['RequestId']));		
					$statement = $sql->getSqlStringForSqlObject( $select );
					$result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray(); 
					$result=json_encode($result);
				}					
			$this->_view->setTerminal(true);
			$response->setContent($result);
			return $response;
			}
		}else {
            $request = $this->getRequest();
			$select = $sql->select();
            $select->from(array('RT' => 'VM_RequestTrans'))
					->columns(array(
					'RequestId'=> new Expression('RR.RequestId'),
					'RequestNo'=> new Expression('RR.RequestNo')))
					->join(array('RR' => 'VM_RequestRegister'), 'RT.RequestId=RR.RequestId', array(), $select::JOIN_INNER)
				->where(array("RT.BalQty>0 AND RR.Approve='Y'"));		
            $reqStatement = $sql->getSqlStringForSqlObject($select); 
            $arr_req = $dbAdapter->query( $reqStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			foreach($arr_req as $reqno){
				$RequestId = $reqno['RequestId']; 
				$RequestNo = $reqno['RequestNo'];
			}
			$this->_view->arr_req=$arr_req;
			
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {
                    $resTotal = $postData['blockrowid'];
                    for ($i = 1; $i <= $resTotal; $i++) {
						$transId = $this->bsf->isNullCheck($postData['requesttransid_'.$i], 'string'); 
						$remarks = $this->bsf->isNullCheck($postData['cancelremarks_'.$i], 'string'); 
			
                        $update = $sql->update();
						$update->table('VM_RequestAnalTrans');
						$update->set(array(
							"CancelQty"		=> new Expression("BalQty"),
							'BalQty' 		=> 0,
						));
						$update->where(array('ReqTransId'=>$transId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						$update1 = $sql->update();
						$update1->table('VM_RequestTrans');
						$update1->set(array(
							'CancelQty'		=> new Expression("BalQty"),
							'BalQty' 		=> 0,
							'CancelRemarks' => $remarks,
						));
						$update1->where(array('RequestTransId'=>$transId));
						$statement = $sql->getSqlStringForSqlObject($update1); 
						$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
					$this->redirect()->toRoute('ats/default', array('controller' => 'index', 'action' => 'request-cancel'));
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'request-cancel-create','N','request-cancel',$RequestId,0,0, 'VMS',$RequestNo,$this->auth->getIdentity()->UserId,0,0);
					
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } 
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
        }
	}
	public function requestAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
		$viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
		$dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
		$sql = new Sql( $dbAdapter );
		$subscriberId = $this->auth->getIdentity()->UserId;

		if($this->getRequest()->isPost()) {
			$response = $this->getResponse();
			if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
				// CSRF attack
				if($this->getRequest()->isXmlHttpRequest())    {
					// AJAX
					$response->setStatusCode(401)
						->setContent('CSRF attack');
					return $response;
				} else {
					// Normal
					$this->redirect()->toRoute("cb/default", array("controller" => "index","action" => "login"));
					return;
				}
			}
		} else {
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$request = $this->getRequest();

		if ($request->isPost()) {
			$this->redirect()->toRoute("index/display-register", array("controller" => "index","action" => "display-register"));
		}

		$dir = 'public/design/requestregister/'. $subscriberId;
		$filePath = $dir.'/v1_template.phtml';
		
		$dirfooter = 'public/designfooter/requestregister/'. $subscriberId;
		$filePath1 = $dirfooter.'/v1_template.phtml';
		
		$ReqId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
		if($ReqId == 0)

			$this->redirect()->toRoute("index/display-register", array("controller" => "index","action" => "display-register"));

		if (!file_exists($filePath)) {
			$filePath = 'public/design/requestregister/template.phtml';
		}
		if (!file_exists($filePath1)) {
			$filePath1 = 'public/designfooter/requestregister/footertemplate.phtml';
		}

		$template = file_get_contents($filePath);
		$this->_view->template = $template;
		
		$footertemplate = file_get_contents($filePath1);
		$this->_view->footertemplate = $footertemplate;

		$selectVendor = $sql->select();
		$selectVendor->from(array("a"=>"VM_RequestRegister"));
		$selectVendor->columns(array(new Expression("a.Narration,a.RequestId,CCReqNo,CReqNo,a.RequestNo,Convert(varchar(10),a.RequestDate,105) as RequestDate,RequestType as RequestType,CASE WHEN a.Priority=1 THEN 'Low'
																WHEN a.Priority=2 THEN 'Medium'
																WHEN a.Priority=3 THEN 'High'
														END as Approve"),new Expression("CASE WHEN a.Approve='Y' THEN 'Yes'
																WHEN a.Approve='P' THEN 'Partial'
																Else 'No'
														END as ApproveReg")),array("CostCentreName"))		
					->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $selectVendor::JOIN_LEFT)
					->join(array("d"=>"WF_CostCentre"), "c.CostCentreId=d.CostCentreId", array("Address"), $selectVendor::JOIN_LEFT)
					->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $selectVendor::JOIN_LEFT)
					->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $selectVendor::JOIN_LEFT)
					->join(array("g"=>"WF_CountryMaster"), "e.CountryId=g.CountryId", array("CountryName"), $selectVendor::JOIN_LEFT)
					->join(array("h"=>"WF_CompanyMaster"), "c.CompanyId=h.CompanyId", array("LogoPath","CompanyName"), $selectVendor::JOIN_LEFT)
					->where("a.DeleteFlag='0' and a.RequestId=$ReqId")
					->order("a.RequestDate Desc");
		$statement = $sql->getSqlStringForSqlObject($selectVendor);
		$gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();	
		$this->_view->reqregister=$gridResult;

		$selectVendor = $sql->select();
		$selectVendor->from(array("a"=>"VM_RequestRegister"));
		$selectVendor->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.RequestId Order by A.RequestId asc)) as SNo,Remarks,
								Case When isnull(i.BrandId,0) > 0 Then i.ItemCode Else g.Code End As Code,
								Case When c.ItemId>0 Then i.BrandName Else g.ResourceName End As ResourceName,
								Convert(varchar(10),c.ReqDate,105) as ReqDate,CASE WHEN a.Priority=1 THEN 'Low'
								WHEN a.Priority=2 THEN 'Medium'
								WHEN a.Priority=3 THEN 'High'
								END as priorityVal")))
								->join(array("c"=>'VM_RequestTrans'), "c.RequestId=a.RequestId", array("Requesttransid","Quantity"), $selectVendor::JOIN_LEFT)
								->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitName"), $selectVendor::JOIN_LEFT)
								->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array(), $selectVendor::JOIN_LEFT)
								->join(array("i"=>"MMS_Brand"), "i.ResourceId=c.ResourceId and i.BrandId=c.itemid", array(), $selectVendor::JOIN_LEFT);
		$selectVendor->where(array("a.RequestId"=>$ReqId));	
		$statement = $sql->getSqlStringForSqlObject($selectVendor);
		$this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}

	public function requestRegisterDetailAction(){
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

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
                $postParams = $request->getPost();
                $ccId = $postParams['costcentreid'];
                $reqId = $postParams['reqId'];

                $select = $sql->select();
                $select->from(array("b" => "VM_RequestTrans"))
                    ->columns(array(new Expression("CAST(b.Quantity As Decimal(18,3)) As RequestQty,
                            CAST(b.CancelQty As Decimal(18,3)) As CancelQty,
                            CAST(b.IndentApproveQty + b.TransferApproveQty + b.ProductionApproveQty As Decimal(18,3)) As DecisionQty,
                            CAST(b.TransferQty As Decimal(18,3)) As TransferQty,
                            CAST(b.BalQty As Decimal(18,3)) As BalQty,
                            f.UnitName As UnitName,
                            Case When b.ItemId>0 Then '(' + e.ItemCode + ')' + ' ' + e.BrandName Else '(' + c.Code + ')' + ' ' + c.ResourceName End As ResourceName,
                           d.RequestId as RequestId,b.RequestTransId as RequestTransId,b.ResourceId,b.ItemId")))
                    ->join(array("c"=>"proj_resource"), "b.resourceId=c.resourceId", array(), $select::JOIN_INNER)
                    ->join(array("e"=>"MMS_Brand"), "e.BrandId=b.ItemId and e.resourceId=b.resourceId", array(), $select::JOIN_LEFT)
                    ->join(array("d"=>"VM_RequestRegister"), "d.RequestId=b.RequestId", array(), $select::JOIN_INNER)
                    ->join(array("f" => "Proj_UOM"), 'b.UnitId=f.UnitId', array(), $select::JOIN_LEFT)
                    ->where(array("d.RequestId = $reqId and d.CostCentreId =  $ccId") );
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->reqDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
			}
		}
	}

	public function dashboardAction(){
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
}