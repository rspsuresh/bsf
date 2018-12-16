<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Mms\Controller;

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

use Application\View\Helper\Qualifier;

use PHPExcel;
use PHPExcel_IOFactory;
use Application\View\Helper\CommonHelper;

use DOMPDF;
class MasterController extends AbstractActionController
{
    public function __construct(){
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function openingStockAction() {

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $projectId = $this->params()->fromRoute('projectId');
        if ($projectId == "") {
            $projectId = 0;
        }
        $sql = new Sql($dbAdapter);

        $where = "";
        if (isset($projectId)) {

            $where = " where projectId =" . $projectId;
            $select = $sql->select();
            $select->from('MMS_Stock')
                ->where(array("CostCentreId" => $projectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }
        //to select project

        $projSelect = $sql->select();
        $projSelect->from('WF_OperationalCostCentre')
            ->columns(array('CostCentreId', 'CostCentreName'));
        $projStatement = $sql->getSqlStringForSqlObject($projSelect);
        $this->_view->arr_costcenter = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "WF_OperationalCostCentre"))
            ->columns(array('CostCentreName'))
            ->where(array("CostCentreId" => $projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->CostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a" => "Proj_ProjectMaster"))
            ->columns(array('ProjectId', 'ProjectName'));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resourceproj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "Proj_ProjectMaster"))
            ->columns(array('ProjectName'))
            ->where(array("ProjectId" => $projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->proj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        //grid query//
        $select = $sql->select();
        $select->from(array("a" => "Proj_Resource"))
            ->columns(array("ResourceId", "UnitId",
                'ItemCode' => new Expression("(ISNULL(b.ItemId,0))"),
                'Code' => new Expression("CASE WHEN ISNULL(b.ItemId, 0)> 0 THEN d.ItemCode ELSE a.Code END"),
                'Resource' => new Expression("CAST(CASE When ISNULL(b.ItemId,0) > 0 Then d.BrandName Else a.ResourceName End AS VARCHAR(255))"),
                'Unit' => new Expression("Case When ISNULL(b.ItemId,0) > 0 Then e.UnitName Else c.UnitName End"),
                'Rate' => new Expression("CAST(ISNULL(b.ORate,0) AS Decimal(18,3))"),
                'OpeningStock' => new Expression("CAST(ISNULL(b.OpeningStock,0)  AS Decimal(18,3))"),
                'MinStock' => new Expression("CAST(ISNULL(b.MinStock,0)  AS Decimal(18,3))"),
                'MaxStock' => new Expression("CAST(ISNULL(b.MaxStock,0)  AS Decimal(18,3))"),
                'ReOrder' => new Expression("CAST(ISNULL(b.ReOrder,0)  AS Decimal(18,3))"),
                'LeadTime' => new Expression("CAST(ISNULL(b.LeadTime,0) As Decimal(18,0))"),
                'Variance' => new Expression("CAST(ISNULL(b.Variance,0) As Decimal(18,5))"),
                'RateVariance' => new Expression("CAST(ISNULL(b.RateVariance,0) As Decimal(18,5))"),
                'StockId' => new Expression("ISNULL(b.StockId,0)")))
            ->join(array("b" => "MMS_Stock"), "a.ResourceId=b.ResourceId", array(), $select::JOIN_LEFT)
            ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
            ->join(array("d" => "MMS_Brand"), "b.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
            ->join(array("e" => "Proj_UOM"), "d.UnitId=e.UnitId", array(), $select::JOIN_LEFT)
            ->where(array("b.ResourceId NOT IN(Select ResourceId From MMS_Brand Where b.ResourceId=ResourceId And b.ItemId=0)", "a.TypeId IN (2 ,3)"))
            ->order('a.ResourceId desc');
        $select->where(array("b.CostCentreId" => $projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->openingstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->project = $projectId;

        $select = $sql->select();
        $select->from(array("a" => "MMS_WareHouse"))
            ->columns(array(new Expression("a.WareHouseName, CAST(0 As Decimal(18,3)) As OpeningStock")))
            ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
            ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array('WareHouseId' => 'TransId', 'Description'), $select::JOIN_INNER)
            ->where(array("b.CostCentreId = $projectId And c.LastLevel=1"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->warhousdtl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;

                $stockId = $this->bsf->isNullCheck($postParams['StockId'], 'string');
                $select = $sql->select();
                $select->from(array("a" => "MMS_Stock"))
                    ->columns(array('StockId'))
                    ->where(array("a.StockId" => $stockId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $stockResource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (count($stockResource) > 0) {
                    if($this->bsf->isNullCheck($postParams['ostock'], 'number') > 0) {
                        $update = $sql->update();
                        $update->table('MMS_Stock');
                        $update->set(array(
                            'ResourceId' => $this->bsf->isNullCheck($postParams['ResourceId'], 'number'),
                            'UnitId' => $this->bsf->isNullCheck($postParams['UnitId'], 'number'),
                            'OpeningStock' => $this->bsf->isNullCheck($postParams['ostock'], 'number'),
                            'MinStock' => $this->bsf->isNullCheck($postParams['minstock'], 'number'),
                            'MaxStock' => $this->bsf->isNullCheck($postParams['maxstock'], 'number'),
                            'LeadTime' => $this->bsf->isNullCheck($postParams['LeadTime'], 'number'),
                            'ORate' => $this->bsf->isNullCheck($postParams['Rate'], 'number'),
                            'ReOrder' => $this->bsf->isNullCheck($postParams['reorder'], 'number')
                        ));
                        $update->where(array('StockId' => $stockId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else {
                    if($this->bsf->isNullCheck($postParams['ostock'], 'number') > 0) {
                        $insert = $sql->insert('MMS_Stock');
                        $newData = array(
                            'ResourceId' => $postParams['id'],
                            'UnitId' => $this->bsf->isNullCheck($postParams['UnitId'], 'number'),
                            'OpeningStock' => $this->bsf->isNullCheck($postParams['OpeningStock'], 'number'),
                            'MinStock' => $this->bsf->isNullCheck($postParams['MinStock'], 'number'),
                            'MaxStock' => $this->bsf->isNullCheck($postParams['MaxStock'], 'number'),
                            'LeadTime' => $this->bsf->isNullCheck($postParams['LeadTime'], 'number'),
                            'ORate' => $this->bsf->isNullCheck($postParams['Rate'], 'number'),
                            'ReOrder' => $this->bsf->isNullCheck($postParams['data']['ReOrder'], 'number')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }


               // $sTrans = $postParams['formData'];
                $rowId = $this->bsf->isNullCheck($postParams['RowId'],'number');
                $rCount=$this->bsf->isNullCheck($postParams['iow_'.$rowId.'_rowid'],'number');

                for($i=1; $i<=$rCount; $i++) {

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_StockTrans"))
                        ->columns(array('StockId'))
                        ->where(array("a.StockId" => $stockId,"WareHouseId" => $postParams['iow_'.$rowId.'_WareHouseId_'.$i] ));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $stockResTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    if(count($stockResTrans) > 0){
                        if($this->bsf->isNullCheck($postParams['iow_'.$rowId.'_OpeningStock_' .$i],'number') > 0) {
                            $update = $sql->update();
                            $update->table('MMS_StockTrans');
                            $update->set(array(
                                "OpeningStock" => $postParams['iow_'.$rowId.'_OpeningStock_'.$i]
                            ));
                            $update->where(array('StockId' => $stockId, "WareHouseId" => $postParams['iow_'.$rowId.'_WareHouseId_'.$i]));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        if($this->bsf->isNullCheck($postParams['iow_'.$rowId.'_OpeningStock_' .$i],'number') > 0) {
                            $wareInsert = $sql->insert('MMS_StockTrans');
                            $wareInsert->values(array('StockId' => $stockId,
                                "WareHouseId" => $postParams['iow_'.$rowId.'_WareHouseId_'.$i],
                                "OpeningStock" => $postParams['iow_'.$rowId.'_OpeningStock_'.$i]
                            ));
                            $wareStatement = $sql->getSqlStringForSqlObject($wareInsert);
                            $dbAdapter->query($wareStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                $results = "SUCCESS";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($results);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $postParams = $request->getPost();
//                echo"<pre>";
//                 print_r($postParams);
//                  echo"</pre>";
//                 die;
//                   return;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }
  public function resourceItemAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$brandId = $this->bsf->isNullCheck($this->params()->fromRoute('brandId'),'number');

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $select = $sql->select();
                $select->from(array("a" => "Proj_Resource"))
                    ->columns(array('*'))
                    ->join(array('b' => "Proj_UOM"), 'a.UnitId=b.UnitId', array("UnitName"), $select::JOIN_LEFT)
                    ->join(array('c' => "Proj_ResourceGroup"), 'a.ResourceGroupId=c.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                    ->where(array("ResourceId" => $postData['resourceId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				$code=$result['Code'];
				
				$select = $sql->select();
				$select->from(array("a" => "MMS_ResourceItemCodeSetUp"))
					->columns(array('*'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->item = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				
				$Resource = $this->_view->item['Resource'];
				$CodeType = $this->_view->item['CodeType'];
				$Prefix = $this->_view->item['Prefix'];
				$Suffix = $this->_view->item['Suffix'];
				$Width = $this->_view->item['Width'];
				$MaxNo = $this->_view->item['MaxNo'];
				$this->_view->CodeType = $CodeType;
				
				
				$iVNo = $MaxNo + 1;
				$iVNo = strlen($iVNo);
				$iLen = $Width - $iVNo;
				
				$sPre = "";
				$inc = 0 ;
				for ($i = 1; $i < $iLen; $i++)
				{
					$sPre = $sPre .$inc;
				}
				$sCode = $Prefix . $sPre .$iVNo. $Suffix;
				if($Resource == 1){	
					$sCode = $code . "-" . $sCode;
				}
				$this->_view->sCode=$sCode;
				
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('result'=>$result,'sCode' => $sCode,'CodeType'=>$CodeType)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
				$postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
//				 echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;

                $resId = $this->bsf->isNullCheck($postParams['resourceId'], 'number');
                $itemCode = $this->bsf->isNullCheck($postParams['txtItemCode'], 'string');
                $itemName = $this->bsf->isNullCheck($postParams["txtItemName"], 'string');
                $unitId = $this->bsf->isNullCheck($postParams["cboUnit"], 'number');
                $rate = $this->bsf->isNullCheck($postParams["txtRate"], 'number');
                $length = $this->bsf->isNullCheck($postParams["length"], 'number');
                $breadth = $this->bsf->isNullCheck($postParams["breadth"], 'number');
                $depth = $this->bsf->isNullCheck($postParams["depth"], 'number');
                $total = $this->bsf->isNullCheck($postParams["total"], 'number');
                $CodeType = $this->bsf->isNullCheck($postParams["CodeType"], 'number');

				$resTotal = $postParams['RowCount'];
				foreach (range(1, $resTotal) as $count) {
					if($postParams['resource'] != 0 && $postParams['itemcode_' . $count] != "" && $postParams['itemname_' . $count] != "" && $postParams['rate_' . $count] != "") {
						$setUpInsert = $sql->insert('MMS_Brand');
						$setUpInsert->values(array(
							"ResourceId" =>$postParams['resource'],
							"ItemCode" => $this->bsf->isNullCheck($postParams['itemcode_' . $count], 'string'),
							"BrandName" => $this->bsf->isNullCheck($postParams['itemname_' . $count], 'string'),
							"UnitId" => $this->bsf->isNullCheck($postParams['unitname_' . $count], 'number'),
							"Rate" => $this->bsf->isNullCheck($postParams['rate_' . $count], 'number'),
							"QRate" => $this->bsf->isNullCheck($postParams['rate_' . $count], 'number'),
							"length" => $this->bsf->isNullCheck($postParams['length_' . $count], 'number'),
							"Breadth" => $this->bsf->isNullCheck($postParams['breadth_' . $count], 'number'),
							"Depth" => $this->bsf->isNullCheck($postParams['depth_' . $count], 'number'),
							"LBDTotal" => $this->bsf->isNullCheck($postParams['total_' . $count], 'number'),
						));
						$setUpStatement = $sql->getSqlStringForSqlObject($setUpInsert);
						$dbAdapter->query($setUpStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
                if (isset($brandId) && ($brandId) != null) {
                    $update = $sql->update("MMS_Brand");
                    $updateData = array(
                        'ResourceId' => $resId,
                        'ItemCode' => $itemCode,
                        'BrandName' => $itemName,
                        'UnitId' => $unitId,
                        'Rate' => $rate,
                        'QRate' => $rate,
                        'length' => $length,
                        'Breadth' => $breadth,
                        'Depth' => $depth,
                        'LBDTotal' => $total
                    );
                    $update->set($updateData)
                        ->where("BrandId=$brandId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
					if($postParams['resourceId'] != "" || $postParams['txtItemCode'] != "" || $postParams['txtItemName'] != "" || $postParams['txtRate'] != "" || $postParams['cboUnit'] != "") {
						$insert = $sql->insert('MMS_Brand');
						$newData = array(
							'ResourceId' => $resId,
							'ItemCode' => $itemCode,
							'BrandName' => $itemName,
							'UnitId' => $unitId,
							'Rate' => $rate,
							'QRate' => $rate,
							'length' => $length,
							'Breadth' => $breadth,
							'Depth' => $depth,
							'LBDTotal' => $total
						);
						$insert->values($newData);
					    $statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						if($CodeType == 1){
							$update = $sql->update("MMS_ResourceItemCodeSetUp");
							$updateData = array('MaxNo' => 1);
							$update->set($updateData);
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
						
					}
                }
                $connection->commit();

                if (($postParams['type'] == '2')) {
                    $this->redirect()->toRoute('mms/resource-item', array('controller' => 'master', 'action' => 'resource-item-register'));
                } else if(($postParams['type'] == '1')){
					$this->redirect()->toRoute('mms/resource-item', array('controller' => 'master', 'action' => 'resource-item'));
                }else{
					$this->redirect()->toRoute('mms/resource-item-register', array('controller' => 'master', 'action' => 'resource-item-register'));
				}
            }
		}
		//try block example
		$selectResource = $sql->select();
		$selectResource->from("proj_resource")
			->columns(array("data" => new Expression("Code+'/'+ResourceName"), "value" => "ResourceId",
                "MeasureType" => "MeasureType"))
            ->where("typeid in (2,3)");
		$selResourceStatement = $sql->getSqlStringForSqlObject($selectResource);
		$this->_view->resourceList = $dbAdapter->query($selResourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectResource = $sql->select();
		$selectResource->from("proj_resource")
			->columns(array("*"))
			->where("typeid in (2,3)");
		$selResourceStatement = $sql->getSqlStringForSqlObject($selectResource);
		$this->_view->resourceItem = $dbAdapter->query($selResourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectUnit = $sql->select();
		$selectUnit->from("Proj_UOM")
			->columns(array("data" => "UnitName", "value" => "UnitId"));
		$selectUnitStatement = $sql->getSqlStringForSqlObject($selectUnit);
		$this->_view->unitList = $dbAdapter->query($selectUnitStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
		$select->from(array("a" => "MMS_ResourceItemCodeSetUp"))
			->columns(array('*'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->Codeitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$CodeType = $this->_view->Codeitem['CodeType'];
		$this->_view->CodeType = $CodeType;
		//edit
		if (isset($brandId) && $brandId != 0) {
			$select = $sql->select();
			$select->from(array("a" => "MMS_Brand"))
				->columns(array("ItemCode" => "ItemCode", "ResourceId" => "ResourceId", "ItemName" => "BrandName", "Rate" => "Rate", "IsEmpIssuable" => "IsEmpIssuable", "Length" => "Length", "Breadth" => "Breadth", "Depth" => "Depth", "LBDTotal" => "LBDTotal"))
				->join(array('b' => "Proj_UOM"), 'a.UnitId=b.UnitId', array("UnitId" => "UnitId", "UnitName" => "UnitName"), $select::JOIN_LEFT)
				->join(array('c' => "Proj_Resource"), 'a.ResourceId=c.ResourceId', array("MeasureType" => "MeasureType", "Code"), $select::JOIN_LEFT)
				->join(array('d' => "Proj_ResourceGroup"), 'c.ResourceGroupId=d.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
				->where(array("a.BrandId" => $brandId));
			$selStatement = $sql->getSqlStringForSqlObject($select);
			$this->_view->brandDetail = $dbAdapter->query($selStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		}
		$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		$this->_view->brandId = $brandId;
		
       //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function resourceRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $projectId = $this->params()->fromRoute('projectId');
		if($projectId == ""){
			$projectId =0;
		}
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result = "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {

				$projSelect = $sql->select();
				$projSelect->from('WF_OperationalCostCentre')
					->columns(array('CostCentreId', 'CostCentreName'));
				$projStatement = $sql->getSqlStringForSqlObject($projSelect);
				$this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				$select = $sql->select();
				$select->from(array("a" => "WF_OperationalCostCentre"))
					->columns(array('CostCentreName'))
					->where(array("CostCentreId" => $projectId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->CostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceproj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectName'))
                    ->where(array("ProjectId" => $projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->proj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectResource"))
                    ->columns(array('Quantity' => new Expression("CAST(a.Qty AS Decimal(18,5))")))
                    ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array("ResourceId", "Code", 'resource' => "ResourceName"), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_UOM"), "b.UnitId=c.UnitId", array('Unit' => "UnitName"), $select::JOIN_INNER)
                    ->join(array("d" => "MMS_Brand"), "b.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
                    ->where(array("b.ResourceId NOT IN(Select ResourceId From MMS_Brand)", "b.TypeId IN (2 ,3)"))
                    ->order('a.ResourceId desc');
                $select->where(array("a.ProjectId" => $projectId));
				$statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcereg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->project = $projectId;

            }
            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }

    public function priorityAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $projectId = $this->params()->fromRoute('projectId');
		if($projectId == ""){
			$projectId =0;
		}
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                //Write your Ajax post code here
                $postParams = $request->getPost();
                if ($postParams['selectedIndex'] == 'High') {
                    $val = '1';
                } else if ($postParams['selectedIndex'] == 'Medium') {
                    $val = '2';
                } else if ($postParams['selectedIndex'] == 'Low') {
                    $val = '3';
                } else {
                    $val = '0';
                }
                $Select = $sql->select();
                $Select->from('MMS_ResourcePriority')
                    ->columns(array('ResourceId'))
                    ->where(array('ResourceId' => $this->bsf->isNullCheck($postParams['dat'], 'number')));
                $Statement = $sql->getSqlStringForSqlObject($Select);
                $sel_resourceid = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                if(count($sel_resourceid) > 0){

                    $update = $sql->update();
                    $update->table('MMS_ResourcePriority');
                    $update->set(array(
                        'PriorityId' => $val,
                    ));
                    $update->where(array('ResourceId' => $this->bsf->isNullCheck($postParams['dat'], 'number')));
                  $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }else{
                    $insert = $sql->insert('MMS_ResourcePriority');
                    $insert->values(array(
                        'PriorityId' => $val,
                        'ResourceId'=> $this->bsf->isNullCheck($postParams['dat'], 'number'),
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $connection->commit();


            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {

				$projSelect = $sql->select();
				$projSelect->from('WF_OperationalCostCentre')
					->columns(array('CostCentreId', 'CostCentreName'));
				$projStatement = $sql->getSqlStringForSqlObject($projSelect);
				$this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				$select = $sql->select();
				$select->from(array("a" => "WF_OperationalCostCentre"))
					->columns(array('CostCentreName'))
					->where(array("CostCentreId" => $projectId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->CostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceproj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectName'))
                    ->where(array("ProjectId" => $projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->proj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("RV" => "Proj_Resource"))
                    ->columns(array('ResourceId', 'Code', 'Resource' => 'ResourceName',
                    'Priority' => new Expression("Case When RP.PriorityId='1' then 'High' when RP.PriorityId='2' then 'Medium' When RP.PriorityId='3' then 'Low' else 'None' end")))
                    ->join(array("RP" => "MMS_ResourcePriority"), "RV.ResourceId=RP.ResourceId",
                        array(), $select::JOIN_LEFT)
                    ->where("RV.TypeId In (2,3)")
                    ->order("RV.ResourceName");
               $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->priority = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->project = $projectId;
            }
            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }

    public function gateListAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $projectId = $this->params()->fromRoute('projectId');
		if($projectId == ""){
			$projectId =0;
		}
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();

                $gId = $this->bsf->isNullCheck($postParams['gateId'], 'number');
                $mode = $this->bsf->isNullCheck($postParams['mode'], 'string');
                $result='';

                if($mode == 'delete'){

                    $select1 = $sql->select();
                    $select1->from(array("a"=>"MMS_GatePass"))
                        ->columns(array("GateId"))
                        ->where(array("a.GateId"=>$gId));
                    $statement = $sql->getSqlStringForSqlObject( $select1 );
                    $gateData = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    if(count($gateData) == 0){
                        $del = $sql->delete();
                        $del->from('MMS_GateMaster')
                            ->where(array("GateId"=>$gId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $result= 'SUCCESS';
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();


                $delete = $sql->delete();
                $delete->from('MMS_GateMaster')
                    ->where(array('CostCentreId' => $projectId));
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                $rowCount = $postParams['RowCount'];
                foreach (range(1, $rowCount) as $count) {
                    if ($postParams['gatename_' . $count] != "" || $postParams['security_' . $count] != "" || $postParams['gateId_' . $count] != "") {
                        //Print_r($postParams['security_'.$count]);die;
                        $insert = $sql->insert('MMS_GateMaster');
                        $insert->values(array(
                            'GateName' => $this->bsf->isNullCheck($postParams['gatename_' . $count], 'string'),
                            'SecurityAgency' => $this->bsf->isNullCheck($postParams['security_' . $count], 'string'),
                            'CostCentreId' => $projectId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $connection->commit();
                $this->redirect()->toRoute('mms/gate-list', array('controller' => 'master', 'action' => 'gate-list', 'projectId' => $projectId));

            } else {

				$projSelect = $sql->select();
				$projSelect->from('WF_OperationalCostCentre')
					->columns(array('CostCentreId', 'CostCentreName'));
				$projStatement = $sql->getSqlStringForSqlObject($projSelect);
				$this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				$select = $sql->select();
				$select->from(array("a" => "WF_OperationalCostCentre"))
					->columns(array('CostCentreName'))
					->where(array("CostCentreId" => $projectId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->CostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceproj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_ProjectMaster"))
                    ->columns(array('ProjectName'))
                    ->where(array("ProjectId" => $projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->proj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array("a" => "MMS_GateMaster"))
                    ->columns(array('GateId', 'GateName', 'SecurityAgency'))
                    ->order('GateName');
                $select->where(array("a.CostCentreId" => $projectId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->gate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->project = $projectId;

            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }


    public function resourceItemRegisterAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$response = $this->getResponse();
        if ($this->getRequest()->isXmlHttpRequest()) {
			$resp = array();
            $request = $this->getRequest();
            if ($request->isPost()) {
				$mode = $this->bsf->isNullCheck($this->params()->fromPost('mode'), 'string');
				$chkAdjustment = $this->bsf->isNullCheck($this->params()->fromPost('chkAdjustment'), 'string');
				$rdio = $this->bsf->isNullCheck($this->params()->fromPost('rdio'), 'number');
				$Prefix = $this->bsf->isNullCheck($this->params()->fromPost('Prefix'), 'string');
				$Suffix = $this->bsf->isNullCheck($this->params()->fromPost('Suffix'), 'string');
				$Width = $this->bsf->isNullCheck($this->params()->fromPost('Width'), 'number');
				
                if ($mode == 'register') {
					if($chkAdjustment == 'on')
					{
						$Adjustment=1;
					}
					else
					{
						$Adjustment=0;
					}
				

					$del = $sql->delete();
					$del->from('MMS_ResourceItemCodeSetUp');
					$statement = $sql->getSqlStringForSqlObject($del);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$itemInsert = $sql->insert('MMS_ResourceItemCodeSetUp');
					$itemInsert->values(array(
						"Resource" => $Adjustment,
						"CodeType" => $rdio,
						"Prefix" => $Prefix,
						"Suffix" =>$Suffix,
						"Width" => $Width,
						));
					$itemStatement = $sql->getSqlStringForSqlObject($itemInsert);
					$dbAdapter->query($itemStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$select = $sql->select();
					$select->from(array("a" => "MMS_ResourceItemCodeSetUp"))
						->columns(array('*'));
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp ['data']= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					
                }
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
				$postParams = $request->getPost();
            } else {
                $select = $sql->select();
                $select->from(array("a" => "MMS_Brand"))
                    ->columns(array('*'))
                    ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array("ResourceName"), $select::JOIN_LEFT)
                    ->order("a.BrandId desc")
                    ->where(array('a.DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$select = $sql->select();
                $select->from(array("a" => "MMS_ResourceItemCodeSetUp"))
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->item = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				$Resource = $this->_view->item['Resource'];
				$CodeType = $this->_view->item['CodeType'];
				$Prefix = $this->_view->item['Prefix'];
				$Suffix = $this->_view->item['Suffix'];
				$Width = $this->_view->item['Width'];
				$MaxNo = $this->_view->item['MaxNo'];
				

				$this->_view->Resources =  $Resource;
				$this->_view->CodeType =  $CodeType;
				$this->_view->Prefix =  $Prefix;
				$this->_view->Suffix = $Suffix;
				$this->_view->Width =  $Width;
            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }

    public function resourceItemDeleteAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $BrandId = $this->bsf->isNullCheck($this->params()->fromPost('BrandId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('MMS_Brand')
                        ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('BrandId' => $BrandId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

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


    public function gateentryAction(){

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vNo = CommonHelper::getVoucherNo(304,date('Y/m/d') ,0,0, $dbAdapter,"");
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                //Write your Ajax post code here
                if ($postParams['mode'] == 'Vehicle') {
                    $CCId = $postParams["CostCentreId"];
                    $VehicleSelect = $sql->select();
                    $VehicleSelect->from('Vendor_VehicleMaster')
                        ->columns(array(new Expression("VehicleId as data,VehicleRegNo as value, VehicleName")))
                        ->where(array("VendorId" => $postParams['SupplierId']));
                    $VehicleStatement = $sql->getSqlStringForSqlObject($VehicleSelect);
                    $result = $dbAdapter->query($VehicleStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $gatepo = $sql  -> select();
                    $gatepo->from(array("a" => "MMS_PORegister"))
                        ->columns(array(new Expression("Distinct a.PORegisterId As data,a.PONo As value")))
                        ->join(array("b" => "MMS_POTrans"),"a.PORegisterId=b.PORegisterId",array(),$gatepo::JOIN_INNER)
                        ->join(array("c" => "MMS_POProjTrans"),"b.POTransid=c.POTransId",array(),$gatepo::JOIN_INNER)
                        ->where('c.CostCentreId='.$CCId.' and a.VendorId='.$postParams['SupplierId'].' and a.Approve='."'Y'".'');
                    $gatepoStatement = $sql->getSqlStringForSqlObject($gatepo);
                    $gateporesult = $dbAdapter->query($gatepoStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $suppadd = $sql -> select();
                    $suppadd->from(array("a" => "Vendor_Contact" ))
                         ->columns(array(new Expression("Top 1 CAddress As Address,ContactNo1 As Mobile ")))
                         ->where('a.VendorId='. $postParams['SupplierId'] .'');
                     $suppaddStatement = $sql->getSqlStringForSqlObject($suppadd);
                    $suppaddresult = $dbAdapter->query($suppaddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode(array('vehicle' => $result, 'gatepo' => $gateporesult,'suppadd' => $suppaddresult )));
                    return $response;

                }
                if ($postParams['mode'] == 'Gate') {
                    $GateSelect = $sql->select();
                    $GateSelect->from('MMS_GateMaster')
                        ->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
                        ->where(array("CostCentreId" => $postParams['PId']));
                    $GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
                    $re = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$select = $sql->select();
					$select->from(array('a' => 'WF_OperationalCostCentre'))
						->columns(array('CompanyId'))
						->where(array("CostCentreId"=>$postParams['PId']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$CompanyId=$Comp['CompanyId'];

					$rs=CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, $postParams['PId'], $dbAdapter, "");
					$rd=CommonHelper::getVoucherNo(304, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
					$result=array($re,$rs,$rd);

                }
                if ($postParams['mode'] == 'Vehicle1') {
                    $VehicleSelect = $sql->select();
                    $VehicleSelect->from('Vendor_VehicleMaster')
                        ->columns(array("VehicleId", "VehicleRegNo", "VehicleName"))
                        ->where(array("VehicleId" => $postParams['VId']));
                    $VehicleStatement = $sql->getSqlStringForSqlObject($VehicleSelect);
                    $result = $dbAdapter->query($VehicleStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                if ($postParams['mode'] == 'Gate1') {
                    $GateSelect = $sql->select();
                    $GateSelect->from('MMS_GateMaster')
                        ->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
                        ->where(array("GateId" => $postParams['GId']));
                    $GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
                    $result = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                if ($postParams['mode'] == 'Grid') {
                    $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,0 As Include ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
						->join(array('e' => 'WF_OperationalCostCentre'),'c.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where(" e.CostCentreId =" . $CostCentreId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$result=array('resources' => $requestResources);
                }
				if ($postParams['mode'] == 'Entry') {
					$regDetails = $sql->select();
					$regDetails	->from(array("a" => "MMS_GatePass"))
							->columns(array(
						'GatePassNo'=> new Expression('a.GatePassNo'),
						'CCGatePassNo'=> new Expression('a.CCGatePassNo'),
						'CGatePassNo'=> new Expression('a.CGatePassNo'),
						'VehicleRegNo'=> new Expression('d.VehicleRegNo'),
						'VehicleName'=> new Expression('d.Vehiclename'),
                        'VehicleId'=> new Expression('d.VehicleId'),
						'CostCentreName'=> new Expression('b.CostCentreName'),
						'SupplierName' => new Expression('c.VendorName'),
                        'TimeIn' => new Expression('Convert(Varchar(8),a.TimeIn,108)'),
                        'TimeOut' => new Expression('Convert(Varchar(8),getdate(),108)'),
                        'GDate' => new Expression('a.GDate'),
                        'PoNo' => new Expression('a.PORegisterId'),
                        'Remarks' => new Expression('a.Remarks'),
                        'DriverName' => new Expression('a.DriverName'),
                        'gateId' => new Expression('a.GateRegId'),
                        'GridType' => new Expression('a.GridType')
                            ))
							->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array('CostCentreId'), $regDetails::JOIN_INNER)
							->join(array("c" => "Vendor_Master"), "a.SupplierId=c.VendorId", array('VendorId'), $regDetails::JOIN_INNER)
							->join(array("d" => "Vendor_VehicleMaster"), "a.VehicleId=d.VehicleId", array('VehicleId'), $regDetails::JOIN_INNER)
							->where(array('a.GateRegId' => $postParams['RId']));
					$regStatement = $sql->getSqlStringForSqlObject($regDetails);
					$result = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				}

                $this->_view->setTerminal(true);
                $response->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }

            $date = date("Y/m/d");
            $subQuery = $sql->select();
            $subQuery->from('Vendor_RegTrans')
               ->columns(array("VendorId"))
               ->where(array("StatusType" => 'S', "STDate >= '$date'"));


            $SupplierSelect = $sql->select();
            $SupplierSelect->from(array('VC' => 'Vendor_Contact'))
				->columns(array(
					"SupplierId" => New Expression("VC.VendorId"),
					"SupplierName" => New Expression("VendorName"),
					"Address" => New Expression("CAddress"),
					"Mobile1" => New Expression("Mobile1"),
					"Mobile2" => New Expression("Mobile2"),
					"rn"=> New Expression('Row_number() OVER (Partition BY VM.VendorID ORDER BY VendorName ASC)')))
                ->join(array('VM' => 'Vendor_Master'), 'VC.VendorID=VM.VendorID', array(), $SupplierSelect:: JOIN_INNER)
                ->where->expression("VM.Supply=1 And VM.Approve='Y' And VM.SBlock=0 And VM.CBlock=0 And VM.HBlock=0 And
                          VM.VendorId Not IN ?", array($subQuery));
            $SupplierStatement = $sql->getSqlStringForSqlObject($SupplierSelect);
            $SupplierResults = $dbAdapter->query($SupplierStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			$select2 = $sql->select();
			$select2->from(array('G' =>$SupplierSelect))
					->columns(array(
						"SupplierId" => New Expression("G.SupplierId"),
						"SupplierName" => New Expression("G.SupplierName"),
						"Address" => New Expression("G.Address"),
						"Mobile1" => New Expression("G.Mobile1"),
						"Mobile2" => New Expression("G.Mobile2"),
						"rn" => New Expression("G.rn")
						))
					->order("G.SupplierName ASC")
					->where(array('G.rn =1'));
			$statement = $sql->getSqlStringForSqlObject($select2);
			$Results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            $date = date("Y/m/d");
//            $subQuery = $sql->select();
//            $subQuery->from('Vendor_RegTrans')
//                ->columns(array("VendorId"))
//                ->where(array("StatusType" => 'S', "STDate >= '$date'"));
//
//            $SupplierSelect = $sql->select();
//            $SupplierSelect->from(array('VC' => 'Vendor_Contact'))
//                ->columns(array('SupplierId' => New Expression("VC.VendorId"), "SupplierName" => New Expression("VendorName"), "Address" => New Expression("CAddress"), "Mobile1", "Mobile2"))
//                ->join(array('VM' => 'Vendor_Master'), 'VC.VendorID=VM.VendorID', array(), $SupplierSelect:: JOIN_INNER)
//                ->where->expression("VM.Supply=1 And VM.Approve='Y' And VM.SBlock=0 And VM.CBlock=0 And VM.HBlock=0 And
//                            VM.VendorId Not IN ?", array($subQuery));
//            $SupplierStatement = $sql->getSqlStringForSqlObject($SupplierSelect);
//            $SupplierResults = $dbAdapter->query($SupplierStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array("CostCentreId", "CostCentreName"));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $venvehicle = $sql->select();
            $venvehicle->from('Vendor_VehicleMaster')
                ->columns(array(new Expression("VehicleId As data,VehicleRegNo As value")))
                ->where(array("VendorId" => 1));
            $vehstatement = $sql->getSqlStringForSqlObject($venvehicle);
            $this->_view->arr_venvehicle= $dbAdapter->query($vehstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			$gatepassSelect = $sql->select();
            $gatepassSelect->from('MMS_GatePass')
                ->columns(array('GatePassNo','GateRegId'))
				->where(array('Type' => 1));
            $gatepass = $sql->getSqlStringForSqlObject($gatepassSelect);
            $this->_view->gatepassnumber = $dbAdapter->query($gatepass, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->update = $proResults;
            $this->_view->SupplierResults1 = $Results;
            $this->_view->genType = $vNo["genType"];
            if ($vNo["genType"] ==false){
                $this->_view->svNo = "";
            }
            else{
                $this->_view->svNo = $vNo["voucherNo"];
            }
            $this->_view->vNo = $vNo;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function gateentryEditAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$RegisterId=$this->params()->fromRoute('GateRegId');
		$response = $this->getResponse();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();


				if ($postParams['mode'] == 'Gate1') {
                    $GateSelect = $sql->select();
                    $GateSelect->from('MMS_GateMaster')
                        ->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
                        ->where(array("GateId" => $postParams['GId']));
                    $GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
                    $result = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
			return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()){
                $postData = $request->getPost();
                    $Type =  $this->bsf->isNullCheck($postData['Type'],'number');

					$this->_view->Type = $Type;
					if($Type==2){


						$CostCentre=  $this->bsf->isNullCheck($postData['Project'],'number');
						$SupplierId= $this->bsf->isNullCheck($postData['SupplierName'],'number');
						$TimeIn =  $this->bsf->isNullCheck($postData['TimeIn'],'date');
						$TimeOut =  $this->bsf->isNullCheck($postData['TimeOut'],'date');
						$GDate =  $this->bsf->isNullCheck($postData['GDate'],'date');
						$GateId =  $this->bsf->isNullCheck($postData['GateName'],'number');
						$SecurityName =  $this->bsf->isNullCheck($postData['SecurityName'],'string');
						$VehicleRegNo =  $this->bsf->isNullCheck($postData['VehicleRegNo'],'string');
						$VehicleType =  $this->bsf->isNullCheck($postData['VehicleType'],'string');
						$DriverName = $this->bsf->isNullCheck( $postData['DriverName'],'string');
						$CGateEntryNo =  $this->bsf->isNullCheck($postData['CGateEntryNo'],'string');
						$CCGateEntryNo =  $this->bsf->isNullCheck($postData['CCGateEntryNo'],'string');
						$GatePassNo = $this->bsf->isNullCheck($this->params()->fromPost('GatePassNo'), 'string');
						$GatePassNO = $this->bsf->isNullCheck($this->params()->fromPost('GatePassNO'), 'string');
                        $gridtype = $this->bsf->isNullCheck($postData['gridtype'],'number');
                        $VehicleId=0;

						$this->_view->CostCentre = $CostCentre;
						$this->_view->SupplierId=$SupplierId;
						$this->_view->VehicleType=$VehicleType;
						$this->_view->VehicleId=$VehicleId;
                        $this->_view->VehicleRegNo=$VehicleRegNo;
						$this->_view->GateId=$GateId;
						$this->_view->SecurityName=$SecurityName;
						$this->_view->CGateEntryNo=$CGateEntryNo;
						//echo $CGateEntryNo; die;
						$this->_view->TimeIn=$TimeIn;
						$this->_view->TimeOut=$TimeOut;
						$this->_view->GDate=$GDate;
						$this->_view->CCGateEntryNo=$CCGateEntryNo;
						$this->_view->DriverName=$DriverName;
						$this->_view->GatePassNo=$GatePassNo;
						$this->_view->GatePassNO=$GatePassNO;
                        $this->_view->gridtype=$gridtype;


                            //Vehicle Check & Insert New Warehouse
                            $selVehCheck = $sql -> select();
                            $selVehCheck->from(array('a' => 'Vendor_VehicleMaster'))
                                ->columns(array('VehicleId','VehicleRegNo'))
                                ->where("VendorId=$SupplierId And VehicleRegNo='$VehicleRegNo'");
                            $statement = $sql->getSqlStringForSqlObject($selVehCheck);
                            $vehchkResult= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if(count($vehchkResult) > 0)
                            {
                                foreach ($vehchkResult as $vh)
                                {
                                    $VehicleId = $vh["VehicleId"];
                                }
                            }
                            else
                            {
                                $vehInsert = $sql->insert('Vendor_VehicleMaster');
                                $vehInsert->values(array("VehicleRegNo"=>$VehicleRegNo,
                                    "VendorId"=>$SupplierId,"VehicleName"=>$VehicleType));
                                $vehStatement = $sql->getSqlStringForSqlObject($vehInsert);
                                $registerResults = $dbAdapter->query($vehStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $VehicleId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                            $this->_view->VehicleId=$VehicleId;

                            //

							$selCC = $sql->select();
							$selCC->from(array('a' => 'WF_OperationalCostCentre'))
								->columns(array('CostCentreName'))
								->where("a.CostCentreId=".$CostCentre);
							$statement = $sql->getSqlStringForSqlObject($selCC);
							$ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->ProjectName=$ccname['CostCentreName'];


							$GateSelect = $sql->select();
							$GateSelect->from('MMS_GateMaster')
								->columns(array( "GateName", "GateId"))
								->where(array("GateId" => $GateId));
							$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
							$Gname = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->GateName=$Gname['GateName'];
							$this->_view->GateName2=$Gname['GateId'];

							$GateSelect = $sql->select();
							$GateSelect->from('MMS_GateMaster')
								->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
								->where(array("CostCentreId" =>$CostCentre ));
							$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
							$this->_view->GateName1 = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();


							$VehicleSelect = $sql->select();
							$VehicleSelect->from('Vendor_VehicleMaster')
								->columns(array("VehicleRegNo"))
								->where(array("VehicleId" => $VehicleId));
							$VehicleStatement = $sql->getSqlStringForSqlObject($VehicleSelect);
							$VehicleNo= $dbAdapter->query($VehicleStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->VehicleRegNo=$VehicleNo['VehicleRegNo'];

							$date = date("Y/m/d");
							$subQuery = $sql->select();
							$subQuery->from('Vendor_RegTrans')
									 ->columns(array("VendorId"))
									 ->where(array("StatusType" => 'S', "STDate >= '$date'"));

							$SupplierSelect = $sql->select();
							$SupplierSelect->from(array('VC' => 'Vendor_Contact'))
										   ->columns(array("SupplierName" => New Expression("VendorName")))
										   ->join(array('VM' => 'Vendor_Master'), 'VC.VendorID=VM.VendorID', array(), $SupplierSelect:: JOIN_INNER)
										   ->where->expression("VM.Supply=1 And VM.Approve='Y' And VM.SBlock=0 And VM.CBlock=0 And VM.HBlock=0 And
											  VM.VendorId Not IN ?", array($subQuery));
							$SupplierStatement = $sql->getSqlStringForSqlObject($SupplierSelect);
							$Sname = $dbAdapter->query($SupplierStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->SupplierName=$Sname['SupplierName'];


						$select = $sql->select();
						$select	->from(array('a' => 'MMS_GatePassTrans'))
								->columns(array(new Expression("a.GateTransId,a.ResourceId ,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else e.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else e.ResourceName End As ResourceName,c.UnitName,c.UnitId,CAST(a.Qty As Decimal(18,6)) As GateInQty")))
									->join(array('c' => 'Proj_UOM'), 'a.Unit_Id=c.UnitId', array(), $select:: JOIN_LEFT)
									->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId And d.BrandId = a.ItemId' ,array(),$select::JOIN_LEFT )
									->join(array('e' => 'Proj_Resource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
									->join(array('f' => 'MMS_GatePass'),'a.GateRegId=f.GateRegId',array(),$select::JOIN_LEFT)
								->where(array('f.GateRegId'=>$GatePassNO));
						 $statement = $sql->getSqlStringForSqlObject( $select );
						$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					}else{

						$CostCentre=  $this->bsf->isNullCheck($postData['Project'],'number');
						$GatePassNo = $this->bsf->isNullCheck($this->params()->fromPost('GatePassNo'), 'string');
						$SupplierId= $this->bsf->isNullCheck($postData['SupplierName'],'number');
						$VehicleRegNo =  $this->bsf->isNullCheck($postData['VehicleRegNo'],'string');
						$VehicleType =  $this->bsf->isNullCheck($postData['VehicleType'],'string');
						$GateId =  $this->bsf->isNullCheck($postData['GateName'],'number');
						$SecurityName =  $this->bsf->isNullCheck($postData['SecurityName'],'string');
						$CGateEntryNo =  $this->bsf->isNullCheck($postData['CGateEntryNo'],'string');
						$TimeIn =  $this->bsf->isNullCheck($postData['TimeIn'],'date');
						$TimeOut =  $this->bsf->isNullCheck($postData['TimeOut'],'date');

						$GDate =  $this->bsf->isNullCheck($postData['GDate'],'date');
						$CCGateEntryNo =  $this->bsf->isNullCheck($postData['CCGateEntryNo'],'string');
						$PoNo =  $this->bsf->isNullCheck($postData['PoNo'],'number');

						$Address =  $this->bsf->isNullCheck($postData['Address'],'string');
						$Remarks =  $this->bsf->isNullCheck($postData['Remarks'],'string');
                        $gridtype = $this->bsf->isNullCheck($postData['gridtype'],'number');

						$ContactNo =  $this->bsf->isNullCheck($postData['ContactNo'],'number');
						$DriverName = $this->bsf->isNullCheck( $postData['DriverName'],'string');

						$requestTransIds = 0;
						$itemTransIds = 0;
                        if($requestTransIds == ""){
                            $requestTransIds = 0;
                        }
                        else
                        {
                            $requestTransIds =implode(',',$postData['requestTransIds']);
                        }
                        if($itemTransIds == ""){
                            $itemTransIds = 0;
                        }
                        else{
                            $itemTransIds = implode(',',$postData['itemTransIds']);
                        }
                        $VehicleId=0;
						$this->_view->CostCentre = $CostCentre;
						$this->_view->SupplierId=$SupplierId;
						$this->_view->VehicleType=$VehicleType;
						$this->_view->VehicleRegNo=$VehicleRegNo;
						$this->_view->GateId=$GateId;
						$this->_view->SecurityName=$SecurityName;
						$this->_view->CGateEntryNo=$CGateEntryNo;
						$this->_view->TimeIn=$TimeIn;
						$this->_view->TimeOut=$TimeOut;
						$this->_view->GDate=$GDate;
						$this->_view->CCGateEntryNo=$CCGateEntryNo;
						$this->_view->PoNo=$PoNo;
						$this->_view->Address=$Address;
						$this->_view->Remarks=$Remarks;
						$this->_view->ContactNo=$ContactNo;
						$this->_view->DriverName=$DriverName;
						$this->_view->GatePassNo=$GatePassNo;
                        $this->_view->gridtype=$gridtype;

                        if (!is_null($postData['frm_index'])) {

                            //Vehicle Check & Insert New Warehouse
                            $selVehCheck = $sql -> select();
                            $selVehCheck->from(array('a' => 'Vendor_VehicleMaster'))
                                ->columns(array('VehicleId','VehicleRegNo'))
                                ->where("VendorId=$SupplierId And VehicleRegNo='$VehicleRegNo'");
                            $statement = $sql->getSqlStringForSqlObject($selVehCheck);
                            $vehchkResult= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                           if(count($vehchkResult) > 0)
                           {
                               foreach ($vehchkResult as $vh)
                               {
                                   $VehicleId = $vh["VehicleId"];
                               }
                           }
                           else
                           {
                               $vehInsert = $sql->insert('Vendor_VehicleMaster');
                               $vehInsert->values(array("VehicleRegNo"=>$VehicleRegNo,
                                   "VendorId"=>$SupplierId,"VehicleName"=>$VehicleType));
                               $vehStatement = $sql->getSqlStringForSqlObject($vehInsert);
                               $registerResults = $dbAdapter->query($vehStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                               $VehicleId = $dbAdapter->getDriver()->getLastGeneratedValue();
                           }
                            $this->_view->VehicleId=$VehicleId;

                            //

							$selCC = $sql->select();
							$selCC->from(array('a' => 'WF_OperationalCostCentre'))
								->columns(array('CostCentreName'))
								->where("a.CostCentreId=".$CostCentre);
							$statement = $sql->getSqlStringForSqlObject($selCC);
							$ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->ProjectName=$ccname['CostCentreName'];


							$GateSelect = $sql->select();
							$GateSelect->from('MMS_GateMaster')
								->columns(array( "GateName", "GateId"))
								->where(array("GateId" => $GateId));
							$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
							$Gname = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->GateName=$Gname['GateName'];
							$this->_view->GateName2=$Gname['GateId'];

							$GateSelect = $sql->select();
							$GateSelect->from('MMS_GateMaster')
								->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
								->where(array("CostCentreId" =>$CostCentre ));
							$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
							$this->_view->GateName1 = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();


							$VehicleSelect = $sql->select();
							$VehicleSelect->from('Vendor_VehicleMaster')
								->columns(array("VehicleRegNo"))
								->where(array("VehicleId" => $VehicleId));
							$VehicleStatement = $sql->getSqlStringForSqlObject($VehicleSelect);
							$VehicleNo= $dbAdapter->query($VehicleStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->VehicleRegNo=$VehicleNo['VehicleRegNo'];

							$date = date("Y/m/d");
							$subQuery = $sql->select();
							$subQuery->from('Vendor_RegTrans')
									 ->columns(array("VendorId"))
									 ->where(array("StatusType" => 'S', "STDate >= '$date'"));

							$SupplierSelect = $sql->select();
							$SupplierSelect->from(array('VC' => 'Vendor_Contact'))
										   ->columns(array("SupplierName" => New Expression("VendorName")))
										   ->join(array('VM' => 'Vendor_Master'), 'VC.VendorID=VM.VendorID', array(), $SupplierSelect:: JOIN_INNER)
										   ->where->expression("VM.Supply=1 And VM.Approve='Y' And VM.SBlock=0 And VM.CBlock=0 And VM.HBlock=0 And
											  VM.VendorId Not IN ?", array($subQuery));
							$SupplierStatement = $sql->getSqlStringForSqlObject($SupplierSelect);
							$Sname = $dbAdapter->query($SupplierStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							$this->_view->SupplierName=$Sname['SupplierName'];

							// get resource lists
							$select = $sql->select();
							$select->from(array('a' => 'Proj_Resource'))
								->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,c.UnitName,c.UnitId")))
								->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
								->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
								->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
								->where("f.CostCentreId=".$CostCentre." and (a.ResourceId IN ($requestTransIds) and isnull(d.BrandId,0) IN ($itemTransIds))");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

							$subQuery = $sql->select();
							$subQuery->from("VM_RequestTrans")
								->columns(array('ResourceId'))
								->where('RequestId IN ('.$requestTransIds.')')
								->group(new Expression('ResourceId'));


							$select = $sql->select();
							$select->from(array('a' => 'Proj_Resource'))
								->columns(array(new Expression("a.ResourceId as data,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,c.UnitName,c.UnitId")))
								->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
								->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
								->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
								->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
								->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
								->where(" f.CostCentreId=".$CostCentre." and (a.ResourceId NOT IN ($requestTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");
							$statement = $sql->getSqlStringForSqlObject($select);
							$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

							$select = $sql->select();
							$select->from(array('a' => 'Proj_Resource'))
								->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,c.UnitName,c.UnitId")))
								->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
								->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
								->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
								->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
								->where("f.CostCentreId=".$CostCentre." and (a.ResourceId NOT IN ($requestTransIds) and isnull(d.BrandId,0) NOT IN ($itemTransIds))");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						}
					}
				} else {
					if($RegisterId != 0){
						$regDetails = $sql->select();
						$regDetails	->from(array("a" => "MMS_GatePass"))
									->columns(array('Type',
						'GDate' => new Expression('a.GDate'),
						'SecurityName' => new Expression('a.SecurityName'),
						'CostCentreId' => new Expression('a.CostCentreId'),
						'CGatePassNo' => new Expression('a.CGatePassNo'),
						'CCGatePassNo' => new Expression('a.CCGatePassNo'),
						'VehicleRegNo'=> new Expression('d.VehicleRegNo'),
						'VehicleName'=> new Expression('d.Vehiclename'),
						'TimeIn'=> new Expression('a.TimeIn'),
						'Timeout'=> new Expression('a.Timeout'),
						'Drivername'=> new Expression('a.Drivername'),
						'GateName'=> new Expression('e.GateName'),
						'CostCentreName'=> new Expression('b.CostCentreName'),
						'GatePassNo'=> new Expression('a.GatePassNo'),
						'SupplierName' => new Expression('c.VendorName'),
                        'Remarks' => new Expression('a.Remarks'),
                        'Approve' => new Expression("Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End "),
                        'GridType' => new Expression('a.GridType')

                                    ))
									->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array('CostCentreId'), $regDetails::JOIN_INNER)
									->join(array("c" => "Vendor_Master"), "a.SupplierId=c.VendorId", array(), $regDetails::JOIN_INNER)
									->join(array("d" => "Vendor_VehicleMaster"), "a.VehicleId=d.VehicleId", array(), $regDetails::JOIN_INNER)
									->join(array("e" => "MMS_GateMaster"), "a.GateId=e.GateId", array(), $regDetails::JOIN_INNER)
									->join(array("f" => "MMS_GatePassTrans"), "a.GateRegId=f.GateRegId", array('ResourceId','ItemId'), $regDetails::JOIN_LEFT)
									->where(array('a.GateRegId' => $RegisterId));
                        $regStatement = $sql->getSqlStringForSqlObject($regDetails);
						$regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
						$this->_view->Type=$regResult['Type'];
						$this->_view->VehicleRegNo = $regResult['VehicleRegNo'];
						$this->_view->VehicleType = $regResult['VehicleName'];
						$this->_view->CostCentre = $regResult['CostCentreId'];
						$this->_view->TimeIn = $regResult['TimeIn'];
						$this->_view->TimeOut = $regResult['Timeout'];
						$this->_view->GatePassNo = $regResult['GatePassNo'];
						$this->_view->DriverName = $regResult['Drivername'];
						$this->_view->GDate = $regResult['GDate'];
						$this->_view->GateName = $regResult['GateName'];
						$this->_view->SupplierName = $regResult['SupplierName'];
						$this->_view->SecurityName = $regResult['SecurityName'];
						$this->_view->Approve = $regResult['Approve'];
						$this->_view->CGateEntryNo = $regResult['CGatePassNo'];
						$this->_view->CCGateEntryNo = $regResult['CCGatePassNo'];
						$this->_view->ProjectName = $regResult['CostCentreName'];
                        $this->_view->Notes = $regResult['Remarks'];
                        $this->_view->gridtype = $regResult['GridType'];
                        $this->_view->GateregId = $RegisterId;
						$CostCentreId = $regResult['CostCentreId'];


						$select = $sql->select();
						$select	->from(array('a' => 'MMS_GatePassTrans'))
								->columns(array(new Expression("a.GateTransId, a.ResourceId ,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else e.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else e.ResourceName End As ResourceName,c.UnitName,
								          c.UnitId,Case when f.Type=1 Then a.Qty Else a.ReturnQty End Qty,d.BrandId")))
								->join(array('c' => 'Proj_UOM'), 'a.Unit_Id=c.UnitId', array(), $select:: JOIN_LEFT)
								->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId And d.BrandId = a.ItemId' ,array(),$select::JOIN_LEFT )
								->join(array('e' => 'Proj_Resource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('f' => 'MMS_GatePass'),'a.GateRegId=f.GateRegId',array(),$select::JOIN_INNER)
								->where(array('f.GateRegId'=>$RegisterId));
						$statement = $sql->getSqlStringForSqlObject( $select );
						$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

						$select = $sql->select();
						$select->from(array('a' => 'Proj_Resource'))
							->columns(array(new Expression("a.ResourceId as data,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,c.UnitName,c.UnitId")))
								->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
								->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
								->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
								->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
							->where(" f.CostCentreId=".$CostCentreId." and (a.ResourceId NOT IN (Select ResourceId From MMS_GatePassTrans Where GateRegId=$RegisterId) Or
							        isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_GatePassTrans Where GateRegId=$RegisterId))");
					 	$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

						$GateSelect = $sql->select();
						$GateSelect->from('MMS_GateMaster')
							->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
							->where(array("CostCentreId" =>$CostCentreId ));
						$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
						$this->_view->GateName1 = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


					}
				}

            }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		$this->_view->gateId = $RegisterId;
        return $this->_view;
    }

	 public function gatepassentAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $vNo = CommonHelper::getVoucherNo(304,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $CostCenterId= $this->bsf->isNullCheck($postParams['CostCenterId'],'number');
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string');
                $WorkType= $this->bsf->isNullCheck($postParams['WorkType'],'string');
                $whereCond = array("a.CostCentreId"=>$CostCenterId);
                if($RequestType == 'Material' && $RequestType != '') {
                    $RequestType=2;
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName ") ))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
					->join(array('e' => 'WF_OperationalCostCentre'),'c.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                    ->where("a.TypeId = $RequestType and e.CostCentreId =".$CostCenterId );
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
                    $requestId =$postData['RequestId'];
                    $voucherno='';
                    $Approve="";
                    $Role="";

                    if ($this->bsf->isNullCheck($requestId, 'number') > 0) {
                        $Approve="E";
                        $Role="Gate-Pass-Modify";
                    }else{
                        $Approve="N";
                        $Role="Gate-Pass-Modify";
                    }
                    $this->_view->requestId=$requestId;
                    {
					    $CostCentre= $postData['CostCentreId'];
						$Type = $postData['Type'];
						$GatePassNo = $this->bsf->isNullCheck($this->params()->fromPost('GatePassNo'), 'string');
						$SupplierId= $postData['SupplierId'];
						$VehicleId = $postData['VehicleId'];
						$VehicleType = $postData['VehicleType'];
						$GateId = $postData['GateName'];
						$SecurityName = $postData['SecurityName'];
						$CGateEntryNo = $postData['CGatePassNo'];
						$TimeIn = $postData['TimeIn'];
						$TimeOut = $postData['TimeOut'];
						$GDate = $postData['GDate'];
						$CCGateEntryNo = $postData['CCGatePassNo'];
						$PoNo = $postData['PoNo'];
						$Address = $postData['Address'];
						$Remarks = $postData['Notes'];
						$ContactNo = $postData['ContactNo'];
						$DriverName = $postData['DriverName'];
					 	$GateregId = $postData['GateregId'];
                        $gridtype = $postData['gridtype'];


                        $requestId = $dbAdapter->getDriver()->getLastGeneratedValue();

						$select = $sql->select();
						$select->from(array('a' => 'WF_OperationalCostCentre'))
							->columns(array('CompanyId'))
							->where("CostCentreId=$CostCentre");
						$statement = $sql->getSqlStringForSqlObject($select);
						$Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
						$CompanyId=$Comp['CompanyId'];

						//CostCentre
						$CCGate = CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
						$this->_view->CCGate = $CCGate;

						//CompanyId
						$CGate = CommonHelper::getVoucherNo(304, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
						$this->_view->CGate = $CGate;

                        $connection = $dbAdapter->getDriver()->getConnection();
                        $connection->beginTransaction();
                        try {

							if($GateregId != 0){

                                if($Type == 1) {
                                    $del = $sql->delete();
                                    $del->from('MMS_GatePassTrans')
                                        ->where(array("GateRegId" => $GateregId));
                                    $statement = $sql->getSqlStringForSqlObject($del);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $resTotal = $postData['rowid'];
                                    for ($i = 1; $i < $resTotal; $i++) {
                                        if ($this->bsf->isNullCheck($postData['qty_' . $i], 'number') > 0) {
                                            $requestInsert = $sql->insert('MMS_GatePassTrans');
                                            $requestInsert->values(array(
                                                "GateRegId" => $GateregId,
                                                "CostCentreId" => $CostCentre,
                                                "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                "Qty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
                                                "Unit_Id" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                                "Unit_Name" => $this->bsf->isNullCheck($postData['unitname_' . $i], 'string')
                                            ));
                                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                            $requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                    $tempTimeIn = date('Y-M-d') . ' ' . $TimeIn . '.000';
                                    $tempTimeOut = date('Y-M-d') . ' ' . $TimeOut . '.000';

                                    //$GDate=date('Y-M-d',strtotime($postData['GDate']));
                                    $GDate = date('Y-M-d', strtotime($postData['GDate']));

                                    $update = $sql->update();
                                    $update->table('MMS_GatePass');
                                    $update->set(array(
                                        "GatePassNo" => $GatePassNo,
                                        "CCGatePassNo" => $CCGateEntryNo,
                                        "CGatePassNo" => $CGateEntryNo,
                                        "TimeIn" => $tempTimeIn,
                                        "TimeOut" => $tempTimeOut,
                                        "GDate" => $GDate,
                                        "DriverName" => $DriverName,
                                        "GateId" => $GateId,
                                        "Remarks" => $Remarks,
                                        "SecurityName" => $SecurityName));
                                    $update->where(array('GateregId' => $GateregId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                                else if($Type == 2)
                                {
                                    $tempTimeOut = date('Y-M-d') .' '. $TimeOut .'.000';

                                    $update = $sql -> update();
                                    $update->table('MMS_GatePass');
                                    $update->set(array(
                                        "TimeOut" => $tempTimeOut,
                                        "Type" => 2,
                                        "GateId" => $GateId,
                                        "SecurityName" => $SecurityName,
                                        "Remarks" => $Remarks
                                    ));
                                    $update->where(array('GateRegId'=>$GateregId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $resTotal = $postData['rowid'];

                                    for ($i = 1; $i < $resTotal; $i++) {
                                        if ($this->bsf->isNullCheck($postData['qty_' . $i], 'number') > 0) {
                                            $gatetransid = $this->bsf->isNullCheck($postData['gatetransid_' . $i], 'number');

                                            $gtransUpdate = $sql -> update();
                                            $gtransUpdate ->table ('MMS_GatePassTrans');
                                            $gtransUpdate->set(array(
                                               "ReturnQty" =>$this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                            ));
                                            $gtransUpdate->where(array('GateTransId'=>$gatetransid));
                                            $statement = $sql->getSqlStringForSqlObject($gtransUpdate);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }

							}else{
                                if ($vNo['genType']) {
                                    $voucher = CommonHelper::getVoucherNo(304, date('Y/m/d', strtotime($postData['GDate'])), 0, 0, $dbAdapter, "I");
                                    $voucherno = $voucher['voucherNo'];
                                } else {
                                    $voucherno = $GatePassNo;
                                }

								if ($CCGate['genType']==1) {
									$voucher = CommonHelper::getVoucherNo(304, date('Y/m/d', strtotime($postData['GDate'])), 0, $CostCentre, $dbAdapter, "I");
									$CCGateEntryNo = $voucher['voucherNo'];
								} else {
									$CCGateEntryNo = $CCGateEntryNo;
								}

								if ($CGate['genType']==1) {
									$voucher = CommonHelper::getVoucherNo(304, date('Y/m/d', strtotime($postData['GDate'])), $CompanyId, 0, $dbAdapter, "I");
									$CGateEntryNo = $voucher['voucherNo'];
								} else {
									$CGateEntryNo = $CGateEntryNo;
								}
                                //Vehicle Check

                                //
								$tempTimeIn = date('Y-M-d') .' '. $TimeIn .'.000';
								$tempTimeOut = date('Y-M-d') .' '. $TimeOut .'.000';
								$GDate=date('Y-M-d',strtotime($postData['GDate']));
								$registerInsert = $sql->insert('MMS_GatePass');
								$registerInsert->values(array(
									"Type" => $Type,
									"CostCentreId" => $CostCentre,
									"Approve" => 'N',
									"VehicleId" => $VehicleId,
									"SupplierId" => $SupplierId,
									"GateId" => $GateId,
									"CCGatePassNo" => $CCGateEntryNo,
									"CGatePassNo" => $CGateEntryNo,
									"GDate" => $GDate,
									"TimeIn" => $tempTimeIn,
									"TimeOut" => $tempTimeOut,
									"SecurityName" => $SecurityName,
									"DriverName" => $DriverName,
									"GatePassNo" => $voucherno,
									"Remarks" => $Remarks,
                                    "PORegisterId" => $PoNo,
                                    "GridType" => $gridtype
								));
								$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
								$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
								$GateRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

								$resTotal = $postData['rowid'];
								for ($i = 1; $i < $resTotal; $i++) {
									if($this->bsf->isNullCheck($postData['qty_' . $i], 'number')>0){
										$requestInsert = $sql->insert('MMS_GatePassTrans');
										$requestInsert->values(array(
											"GateRegId" => $GateRegId,
											"CostCentreId" => $CostCentre,
											"ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
											"ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
											"Qty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),
											"Unit_Id" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
											"Unit_Name" => $this->bsf->isNullCheck($postData['unitname_' . $i], 'string')
										));
										$requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
										$requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
									}
								}
							}
                            $connection->commit();
                           // CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Transfer-Receipt',$tvRegId,$tCostCentreId,$CompanyId, 'MMS',$TVNo,$this->auth->getIdentity()->UserId,0,0);
                            $this->redirect()->toRoute('mms/default', array('controller' => 'master', 'action' => 'gatepassregister'));
                           // $this->redirect()->toRoute('mms/resource-item', array('controller' => 'master', 'action' => 'resource-item'));
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

	public function gatepassregisterAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'first'){
					$regSelect = $sql->select();
					$regSelect->from(array("a"=>"MMS_GatePass"))
                                ->columns(array(new Expression("a.GateRegId,a.GatePassNo,a.CCGatePassNo,a.CGatePassNo,Convert(Varchar(10), a.GDate,103) As GDate,b.VendorName as SupplierName,Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
								->join(array("b"=>"Vendor_Master"), "a.SupplierId=b.VendorId", array(), $regSelect::JOIN_LEFT)
							->where(array('a.DeleteFlag'=>0));
                    $regSelect->order(new Expression("a.GateRegId DESC"));
				$regStatement = $sql->getSqlStringForSqlObject($regSelect);
				$resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
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

	public function displayregisterAction(){

		if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest()){
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

	    $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $RegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('GateRegId'),'number');

        $request = $this->getRequest();
        $response = $this->getResponse();


		if($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
            }

        $this->_view->setTerminal(true);
        $response->setContent(json_encode($resp));
        return $response;
        } else if ($request->isPost()) {

        }
		
		
		
		$regDetails = $sql->select();
		$regDetails	->from(array("a" => "MMS_GatePass"))
					->columns(array('Type',
		'GDate' => new Expression('Convert(Varchar(10), a.GDate,103)'),
		'SecurityName' => new Expression('a.SecurityName'),
		'CostCentreId' => new Expression('a.CostCentreId'),
		'CGatePassNo' => new Expression('a.CGatePassNo'),
		'CCGatePassNo' => new Expression('a.CCGatePassNo'),
		'VehicleRegNo'=> new Expression('d.VehicleRegNo'),
		'VehicleName'=> new Expression('d.Vehiclename'),
		'TimeIn'=> new Expression('Convert(Varchar(8),a.TimeIn,108)'),
		'Timeout'=> new Expression('Convert(Varchar(8),a.Timeout,108)'),
		'Drivername'=> new Expression('a.Drivername'),
		'GateName'=> new Expression('e.GateName'),
		'CostCentreName'=> new Expression('b.CostCentreName'),
		'GatePassNo'=> new Expression('a.GatePassNo'),
		'SupplierName' => new Expression('c.VendorName'),
		'Remarks' => new Expression('a.Remarks'),
		'Approve' => new Expression("Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End "),
		'GridType' => new Expression('a.GridType')

					))
					->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array('CostCentreId'), $regDetails::JOIN_INNER)
					->join(array("c" => "Vendor_Master"), "a.SupplierId=c.VendorId", array(), $regDetails::JOIN_INNER)
					->join(array("d" => "Vendor_VehicleMaster"), "a.VehicleId=d.VehicleId", array(), $regDetails::JOIN_INNER)
					->join(array("e" => "MMS_GateMaster"), "a.GateId=e.GateId", array(), $regDetails::JOIN_INNER)
					->join(array("f" => "MMS_GatePassTrans"), "a.GateRegId=f.GateRegId", array('ResourceId','ItemId'), $regDetails::JOIN_LEFT)
					->where(array('a.GateRegId' => $RegisterId));
		$regStatement = $sql->getSqlStringForSqlObject($regDetails);
		$regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->Type=$regResult['Type'];
		$this->_view->VehicleRegNo = $regResult['VehicleRegNo'];
		$this->_view->VehicleType = $regResult['VehicleName'];
		$this->_view->CostCentre = $regResult['CostCentreId'];
		$this->_view->TimeIn = $regResult['TimeIn'];
		$this->_view->TimeOut = $regResult['Timeout'];
		$this->_view->GatePassNo = $regResult['GatePassNo'];
		$this->_view->DriverName = $regResult['Drivername'];
		$this->_view->GDate = $regResult['GDate'];
		$this->_view->GateName = $regResult['GateName'];
		$this->_view->SupplierName = $regResult['SupplierName'];
		$this->_view->SecurityName = $regResult['SecurityName'];
		$this->_view->Approve = $regResult['Approve'];
		$this->_view->CGateEntryNo = $regResult['CGatePassNo'];
		$this->_view->CCGateEntryNo = $regResult['CCGatePassNo'];
		$this->_view->ProjectName = $regResult['CostCentreName'];
		$this->_view->Notes = $regResult['Remarks'];
		$this->_view->gridtype = $regResult['GridType'];
		$this->_view->GateregId = $RegisterId;
		$CostCentreId = $regResult['CostCentreId'];


		$select = $sql->select();
		$select	->from(array('a' => 'MMS_GatePassTrans'))
				->columns(array(new Expression("a.GateTransId, a.ResourceId ,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else e.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else e.ResourceName End As ResourceName,c.UnitName,
						  c.UnitId,Case when f.Type=1 Then a.Qty Else a.ReturnQty End Qty,d.BrandId")))
				->join(array('c' => 'Proj_UOM'), 'a.Unit_Id=c.UnitId', array(), $select:: JOIN_LEFT)
				->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId And d.BrandId = a.ItemId' ,array(),$select::JOIN_LEFT )
				->join(array('e' => 'Proj_Resource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
				->join(array('f' => 'MMS_GatePass'),'a.GateRegId=f.GateRegId',array(),$select::JOIN_INNER)
				->where(array('f.GateRegId'=>$RegisterId));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array('a' => 'Proj_Resource'))
			->columns(array(new Expression("a.ResourceId as data,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,c.UnitName,c.UnitId")))
				->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
				->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
				->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
				->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
				->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
			->where(" f.CostCentreId=".$CostCentreId." and (a.ResourceId NOT IN (Select ResourceId From MMS_GatePassTrans Where GateRegId=$RegisterId) Or
					isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_GatePassTrans Where GateRegId=$RegisterId))");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$GateSelect = $sql->select();
		$GateSelect->from('MMS_GateMaster')
			->columns(array("GateId", "GateName", "GPType", "GPPrefix", "SecurityAgency"))
			->where(array("CostCentreId" =>$CostCentreId ));
		$GateStatement = $sql->getSqlStringForSqlObject($GateSelect);
		$this->_view->GateName1 = $dbAdapter->query($GateStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		// $regDetails = $sql->select();
        // $regDetails->from(array("a" => "MMS_GatePass"))
					// ->columns(array(
			// 'GDate' => new Expression('Convert(Varchar(10), a.GDate,103)'),
			// 'VehicleRegNo'=> new Expression('d.VehicleRegNo'),
			// 'VehicleName'=> new Expression('d.Vehiclename'),
			// 'GateName'=> new Expression('e.GateName'),
			// 'CostCentreName'=> new Expression('b.CostCentreName'),
			// 'SupplierName' => new Expression('c.VendorName')))
					// ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array('CostCentreId'), $regDetails::JOIN_INNER)
					// ->join(array("c" => "Vendor_Master"), "a.SupplierId=c.VendorId", array(), $regDetails::JOIN_INNER)
					// ->join(array("d" => "Vendor_VehicleMaster"), "a.VehicleId=d.VehicleId", array(), $regDetails::JOIN_INNER)
					// ->join(array("e" => "MMS_GateMaster"), "a.GateId=e.GateId", array(), $regDetails::JOIN_INNER)
					// ->join(array("f" => "MMS_GatePassTrans"), "a.GateRegId=f.GateRegId", array('ResourceId','ItemId'), $regDetails::JOIN_INNER)
					// ->where(array('a.GateRegId' => $id ));
        // $regStatement = $sql->getSqlStringForSqlObject($regDetails);
        // $regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        // $this->_view->VehicleRegNo = $regResult['VehicleRegNo'];
        // $this->_view->VehicleName = $regResult['VehicleName'];
        // $this->_view->GDate = $regResult['GDate'];
        // $this->_view->GateName = $regResult['GateName'];
        // $this->_view->SupplierName = $regResult['SupplierName'];
        // $this->_view->CostCentreName = $regResult['CostCentreName'];
        // $this->_view->CostCentreId = $regResult['CostCentreId'];
		// $CostCentreId = $regResult['CostCentreId'];
		// $ResourceId = $regResult['ResourceId'];
		// $ItemId = $regResult['ItemId'];
        // $this->_view->id = $id;

		// $select = $sql->select();
        // $select	->from(array('a' => 'MMS_GatePassTrans'))
                // ->columns(array(new Expression("a.GateTransId,a.ResourceId ,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else e.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else e.ResourceName End As ResourceName,c.UnitName,c.UnitId,a.Qty,d.BrandId")))
					// ->join(array('c' => 'Proj_UOM'), 'a.Unit_Id=c.UnitId', array(), $select:: JOIN_LEFT)
					// ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId And d.BrandId = a.ItemId' ,array(),$select::JOIN_LEFT )
					// ->join(array('e' => 'Proj_Resource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
					// ->join(array('f' => 'MMS_GatePass'),'a.GateRegId=f.GateRegId',array(),$select::JOIN_LEFT)
                // ->where(array('f.GateRegId'=>$id));
        // $statement = $sql->getSqlStringForSqlObject( $select );
        // $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
	}
    public function warehouseassignAction(){

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {

                //Write your Ajax post code here
                $this->_view->setTerminal(true);

                $postData = $request->getPost();
                $result =  "";
                $CostCenterId = $this->bsf->isNullCheck($postData['CostCenterId'], 'number');
                $WareHouseId = $this->bsf->isNullCheck($postData['WareHouseId'], 'number');

                    $del = $sql->delete();
                    $del->from('mms_ccwarehouse')
                        ->where(array("WareHouseId" => $WareHouseId, "CostCentreId" => $CostCenterId));
                    $statement = $sql->getSqlStringForSqlObject($del);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                if($postData['check'] == '1'){

                    $insert = $sql->insert('mms_ccwarehouse');
                    $newData = array(
                        'CostCentreId' =>$CostCenterId,
                        'WareHouseId' =>$WareHouseId
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            else {
                $costSelect = $sql->select();
                $costSelect->from(array("a" => "mms_warehouse"))
                    ->columns(array("WareHouseNo","WareHouseName","WareHouseId"));
                $costStatement = $sql->getSqlStringForSqlObject($costSelect);
                $this->_view->cost = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //costcentre
                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CostCentreId', 'CostCentreName'))
                    ->where('Deactivate=0');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $sel = $sql->select();
                $sel->from(array("a" => "mms_ccwarehouse"))
                    ->columns(array("WareHouseId","CostCentreId"));
                $selStatement = $sql->getSqlStringForSqlObject($sel);
                $this->_view->arr_warehouse = $dbAdapter->query($selStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function stockAgeAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest()){
            $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();

            $status='';
            $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
            $AgeId= $this->bsf->isNullCheck($postParams['AgeId'],'number');

            $delStockSet = $sql->delete();
            $delStockSet->from('MMS_stockagesetup')
                ->where(array("AgeId" => $AgeId));
            $delStockStatement = $sql->getSqlStringForSqlObject($delStockSet);
            $res= $dbAdapter->query($delStockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            $response->setStatusCode('200');
            $this->_view->setTerminal(true);
            $status='Deleted';
            $response->setContent($status);
            return $response;

        }
    } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {   $delSetup = $sql->delete();
                    $delSetup->from('MMS_StockAgeSetup');
                    $delSetupStatement = $sql->getSqlStringForSqlObject($delSetup);
                    $dbAdapter->query($delSetupStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $resTotal = $postParams['rowid'];
                    for ($i = 1; $i < $resTotal; $i++) {
                        if($postParams['Desc_' . $i] != "" || $postParams['fromDate_' . $i]!= "" ||  $postParams['toDate_' . $i] != "") {
                            $setUpInsert = $sql->insert('MMS_StockAgeSetup');
                            $setUpInsert->values(array(
                                "AgeDesc" => $this->bsf->isNullCheck($postParams['Desc_' . $i], 'string'),
                                "FromDays" => $this->bsf->isNullCheck($postParams['fromDate_' . $i], 'number'),
                                "ToDays" => $this->bsf->isNullCheck($postParams['toDate_' . $i], 'number'),
                            ));
                            $setUpStatement = $sql->getSqlStringForSqlObject($setUpInsert);
                            $dbAdapter->query($setUpStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $AgeId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }
                    }
                    $this->_view->AgeId = $AgeId;
                    $connection->commit();
                    $this->redirect()->toRoute('mms/default', array('controller' => 'master', 'action' => 'stock-age'));
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                $select = $sql->select();
                $select->from(array("a" => "MMS_StockAgeSetup"))
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setupResult = $gridResult;


                return $this->_view;
            }
        }
    }

	public function abcAnalysisAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
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
				$postParams = $request->getPost();
				//Write your Normal form post code here
					$connection = $dbAdapter->getDriver()->getConnection();
					$connection->beginTransaction();
					try {
						$AFrom = $this->bsf->isNullCheck($postParams['AFrom'], 'number');
						$ATo= $this->bsf->isNullCheck($postParams['ATo'], 'number');
						$BFrom = $this->bsf->isNullCheck($postParams['BFrom'], 'number');
						$BTo= $this->bsf->isNullCheck($postParams['BTo'], 'number');
						$CFrom = $this->bsf->isNullCheck($postParams['CFrom'], 'number');
						$CTo = $this->bsf->isNullCheck($postParams['CTo'], 'number');

							$del = $sql->delete();
							$del->from('MMS_ABcAnalysisMaster');
							$statement = $sql->getSqlStringForSqlObject($del);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

							$abcInsert = $sql->insert('MMS_ABcAnalysisMaster');
							$abcInsert->values(array(
								"AFrom" => $AFrom,
								"ATo" => $ATo,
								"BFrom" => $BFrom,
								"BTo" => $BTo,
								"CFrom" => $CFrom,
								"CTo" => $CTo,
							));
							$abcStatement = $sql->getSqlStringForSqlObject($abcInsert);
							$dbAdapter->query($abcStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						$connection->commit();
						$this->redirect()->toRoute('mms/resource-item', array('controller' => 'master', 'action' => 'abc-analysis'));
					} catch(PDOException $e){
						$connection->rollback();
						print "Error!: " . $e->getMessage() . "</br>";
					}
				}
			//begin trans try block example ends

			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

			return $this->_view;
		}
	}
	public function deletegateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())    {
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
        $gateregid = $this->params()->fromRoute('GateRegId');

        //echo $dcid; die;

        if($this->getRequest()->isXmlHttpRequest())    {
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
            $postParams = $request->getPost();

            if ($request->isPost()) {
                //Write your Normal form post code here
            }


            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $updReqReg=$sql->update();
                $updReqReg->table('MMS_GatePass');
                $updReqReg->set(array(
                    'DeleteFlag'=> 1
                ));
                $updReqReg->where(array('GateRegId'=>$gateregid));
                $statementregupdate = $sql->getSqlStringForSqlObject($updReqReg);
                $dbAdapter->query($statementregupdate, $dbAdapter::QUERY_MODE_EXECUTE);


                $connection->commit();
                $this->redirect()->toRoute('mms/default', array('controller' => 'master','action' => 'gatepassregister'));
            }
            catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //Common function
            //$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            //$this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'register'));
            //return $this->_view;
        }
    }
	public function resourceviewAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())    {
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

        if($this->getRequest()->isXmlHttpRequest())    {
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
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->projectlists= $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
        }
    }
	function _validateUploadFile($file)
    {
        $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
        $mime_types = array('application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel');
        $exts = array('csv', 'xls', 'xlsx');
        if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
            return false;

        return true;
    }
	function _convertXLStoCSV($infile, $outfile)
    {
        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);
        $objPHPExcel->setActiveSheetIndex(0);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);
    }
	public function getResourceFieldDataAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                try {
                    $mode = $this->bsf->isNullCheck($this->params()->fromPost('mode'), 'string' );

                    if($mode=="title") { 
                        //Write your Ajax post code here
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();

                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/mms/resourceitem/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/mms/resourceitem/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                foreach ($xlData as $j => $value) {
                                    if($value!="") {
                                        $data[] = array('Field' => $value);
                                    }
                                }
                            } else {
                                break;
                            }
                            $icount = $icount + 1;
                        }


                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else if($mode=="body") { 
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();
                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/mms/resourceitem/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/mms/resourceitem/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        $RType = $postData['arrHeader'];
                        $bValid = true;

                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                if(isset($xlData)) {
                                    foreach ($xlData as $j => $value) {
                                        if (trim($value) != "") {
                                            $bFound = false;
                                            $sField = "";
                                            foreach (json_decode($RType) as $k) {
                                                if (trim($value) == trim($k->efield)) {
                                                    $sField = $k->field;
                                                    $bFound = true;
                                                    break;
                                                }
                                            }
                                            if ($bFound == true) {
                                                if (trim($sField) == "Code") {
                                                    $col_1 = intval($j);
                                                }
                                                if (trim($sField) == "Resource") {
                                                    $col_2 = intval($j);
                                                }
                                                if (trim($sField) == "ItemCode") {
                                                    $col_3 = intval($j);
                                                }
                                                if (trim($sField) == "ItemDescription") {
                                                    $col_4 = intval($j);
                                                }
                                                if (trim($sField) == "Unit") {
                                                    $col_5 = intval($j);
                                                }
                                                if (trim($sField) == "Rate") {
                                                    $col_6 = intval($j);
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {

                                $Code="";
                                $Resource="";
                                $ItemCode="";
                                $ItemDescription="";
                                $Unit="";
                                $Rate="";
                                //$ProjectName="";
                                if (isset($col_1) && !is_null($col_1) && trim($col_1)!="" && isset($xlData[$col_1])) {
                                    $Code =$this->bsf->isNullCheck(trim($xlData[$col_1]),'string');
                                }
                                if (isset($col_2) && !is_null($col_2) && trim($col_2)!="" && isset($xlData[$col_2])) {
                                    $Resource =$this->bsf->isNullCheck(trim($xlData[$col_2]),'string');
                                }
                                if (isset($col_3) && !is_null($col_3) && trim($col_3)!="" && isset($xlData[$col_3])) {
                                    $ItemCode = $this->bsf->isNullCheck(trim($xlData[$col_3]),'string');
                                }
                                if (isset($col_4) && !is_null($col_4) && trim($col_4)!="" && isset($xlData[$col_4])) {
                                    $ItemDescription = $this->bsf->isNullCheck(trim($xlData[$col_4]),'string');
                                }
                                if (isset($col_5) && !is_null($col_5) && trim($col_5)!="" && isset($xlData[$col_5])) {
                                    $Unit = $this->bsf->isNullCheck(trim($xlData[$col_5]),'string');
                                }
                                if (isset($col_6) && !is_null($col_6) && trim($col_6)!="" && isset($xlData[$col_6])) {
                                    $Rate = $this->bsf->isNullCheck(trim($xlData[$col_6]),'string');
                                }
                                if($Code!="" || $Resource!="" || $ItemCode!="" || $ItemDescription!="" || $Unit!="" || $Rate!="") {
                                    $data[] = array('Valid' => $bValid, 'Code' => $Code, 'Resource' => $Resource, 'ItemCode' => $ItemCode, 'ItemDescription' => $ItemDescription, 'Unit' => $Unit,
                                        'Rate' => $Rate);
                                }
                            }
                            $icount++;
                        }

                        if ($bValid == false) {
                            $data[] = array('Valid' => $bValid);
                        }
                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else {
                        $postData = $request->getPost();
                        $rowCount = $postData['rowCount'];
                        $data = array();
                        for ($i = 0; $i <= $rowCount; $i++) {

                            $code = $this->bsf->isNullCheck(trim($postData['excelcode_' . $i]), 'string');
                            $resource = $this->bsf->isNullCheck(trim($postData['excelresource_' . $i]), 'string');
                            $itemCode = $this->bsf->isNullCheck(trim($postData['excelitemcode_' . $i]), 'string');
                            $itemDescription = $this->bsf->isNullCheck(trim($postData['excelitemdescription_' . $i]), 'string');
                            $unit = $this->bsf->isNullCheck(trim($postData['excelunit_' . $i]), 'string');
                            $rate= $this->bsf->isNullCheck(trim($postData['excelrate_' . $i]), 'string');
                            if($code=="" && $resource=="" && $itemCode=="" && $itemDescription == "" && $unit == "" && $rate=="") {
                                continue;
                            }
                            $error=0;
							if ($code == "") {
                                $codeArray = array($code, 1);
                                $resourceArray = array($resource, 0);
                                $itemCodeArray = array($itemCode, 0);
                                $itemDescriptionArray = array($itemDescription, 0);
                                $unitArray = array($unit, 0);
                                $rateArray = array($rate, 0);
								$error = 1;
                            } else {
                                $codeArray = array($code, 0);
                            }
							if ($resource == "") {
                                $resourceArray = array($resource, 1);
                                $itemCodeArray = array($itemCode, 0);
                                $itemDescriptionArray = array($itemDescription, 0);
                                $unitArray = array($unit, 0);
                                $rateArray = array($rate, 0);
								$error = 1;
                            } else {
                                $resourceArray = array($resource, 0);
                            }
                            if ($itemCode == "") {
                                $itemCodeArray = array($itemCode, 1);
                                $itemDescriptionArray = array($itemDescription, 0);
                                $unitArray = array($unit, 0);
                                $rateArray = array($rate, 0);
								$error = 1;
                            } else {
                                $itemCodeArray = array($itemCode, 0);
                            }
                            if ($itemDescription == "") {
                                $itemDescriptionArray = array($itemDescription, 1);
                                $unitArray = array($unit, 0);
                                $rateArray = array($rate, 0);
                                $error = 1;
                            } else {
                                $itemDescriptionArray = array($itemDescription, 0);
                            }
							if ($unit == "") {
                                $unitArray = array($unit, 1);
								$rateArray = array($rate, 1);
                                $error = 1;
                            } else {
                                $unitArray = array($unit, 0);
                            }
                            if ($rate == "") {
								$rateArray = array($rate, 1);
                                $unitArray = array($unit, 0);
                                $error = 1;
                            } else {
                                $rateArray = array($rate, 0);
                            }

                            if ($error == 0) {

                                if($code!="" && $resource!="") {
                                    $select = $sql->select();
                                    $select->from('Proj_Resource')
                                        ->columns(array('Code', 'ResourceId','ResourceName'))
                                        ->where(array('Code' => $code, 'DeleteFlag' => 0));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $codeCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($codeCheck)>0) {
                                        $resourceId= $codeCheck[0]['ResourceId'];

                                    } else {
                                        $resourceId=0;
                                    }
                                } else {
                                    $resourceId=0;
                                }
								
								if($unit!="") {
                                    $select = $sql->select();
                                    $select->from('Proj_UOM')
                                        ->columns(array('UnitName', 'UnitId'))
                                        ->where(array('UnitName' => $unit));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $unitCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($unitCheck)>0) {
                                        $unitId= $unitCheck[0]['UnitId'];

                                    } else {
                                        $unitId=0;
                                    }
                                } else {
                                    $unitId=0;
                                }
								
								$insert = $sql->insert('MMS_Brand');
								$newData = array(
									'ResourceId' => $resourceId,
									'ItemCode' => $itemCode,
									'BrandName' => $itemDescription,
									'UnitID' => $unitId,
									'Rate' => $rate
								);
								$insert->values($newData);
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								
                            } else {
                                $data[] = array('Code' => $codeArray, 'Resource' => $resourceArray, 'ItemCode' => $itemCodeArray, 'ItemDescription' => $itemDescriptionArray, 'Unit' => $unitArray,
                                    'Rate' => $rateArray);
                            }
                        }

                    }
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($data));

                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        }
    }
}
