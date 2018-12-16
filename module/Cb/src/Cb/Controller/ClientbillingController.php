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

use PHPExcel;
use PHPExcel_IOFactory;
use Application\View\Helper\CommonHelper;
use DOMPDF;

class ClientbillingController extends AbstractActionController
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

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $userId = $this->auth->getIdentity()->CbUserId;

		$request = $this->getRequest();
		if ( $request->isPost() ) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			$postData = $request->getPost();
			$files = $request->getFiles();
			try {
				$BillId = $this->bsf->isNullCheck($postData['BillId'],'number');
				$BillType = $this->bsf->isNullCheck($postData['BillType'],'string');
				if($this->bsf->isNullCheck($postData['mode'],'string') == 'edit') { // Edit fns
					// update Bill Master
					$isSubCer = $this->bsf->isNullCheck($postData['isSubCer'],'number');
                    $Remarks = $this->bsf->isNullCheck($postData['remarks'],'string');
					$TotalCurAmount = $this->bsf->isNullCheck($postData['TotalCurAmount'],'number');

                    $Date = NULL;
                    if($isSubCer == '1') {
                        $Date = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date'],'string')));
                    }

					// check for db CRUD fns
					$strCer = "";
					if($BillType == 'C') {
						$strCer = "Cer";
					}

                    $select = $sql->select();
                    $select->from("CB_BillMaster")
                        ->where(array("BillId" => $BillId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $oldBillData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

					// check for bill trans type
					$update = $sql->update();
					if($BillType == 'S') {
						$update->table('CB_BillMaster')
							->set( array('SubmittedRemarks' => $Remarks , 'SubmittedDate' => $Date
								   , 'IsSubmittedBill' => $isSubCer,'SubmitAmount' => $TotalCurAmount));
					} else {
						$update->table('CB_BillMaster')
							->set( array('CertifiedRemarks' => $Remarks , 'CertifiedDate' => $Date
								   , 'IsCertifiedBill' => $isSubCer,'CertifyAmount' => $TotalCurAmount ));
					}
					$update->where(array('BillId' => $BillId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
					// get bill details
					$select = $sql->select();
					$select->from("CB_BillMaster")
						->columns(array('WORegisterId'))
						->where(array("BillId" => $BillId));
					$statement = $sql->getSqlStringForSqlObject($select);
					$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

					// insert new vendor(s)
					$NewVendorRowId = $this->bsf->isNullCheck($postData['NewVendorRowId'],'number');
					$NewVendorIds = array();
					for ($v = 1; $v <= $NewVendorRowId; $v++) {
						$vendorId = $this->bsf->isNullCheck($postData['NewVendorId_' . $v], 'string');
						$vendorName = $this->bsf->isNullCheck($postData['NewVendorName_' . $v], 'string');
						$address = $this->bsf->isNullCheck($postData['NewVendorAddress_' . $v], 'string');
						$cityName = $this->bsf->isNullCheck($postData['NewVendorCity_' . $v], 'string');
						$stateName = $this->bsf->isNullCheck($postData['NewVendorState_' . $v], 'string');
						$countryName = $this->bsf->isNullCheck($postData['NewVendorCountry_' . $v], 'string');
						$email = $this->bsf->isNullCheck($postData['NewVendorEmail_' . $v], 'string');
						$mobile = $this->bsf->isNullCheck($postData['NewVendorMobile_' . $v], 'string');
						$cityId = $viewRenderer->commonHelper()->getCityId($cityName, $stateName, $countryName, $dbAdapter);

						$insert = $sql->insert();
						$insert->into('CB_VendorMaster')
							->Values(array('vendorName' => $vendorName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email, 'Mobile' => $mobile
                                     , 'SubscriberId' => $subscriberId));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$generatedVendorId = $dbAdapter->getDriver()->getLastGeneratedValue();
						$NewVendorIds[$vendorId] = $generatedVendorId;
					}

					// insert new NonAgtItem(s)
					$NewNonAgtRowId = $this->bsf->isNullCheck($postData['newnonagtrowid'],'number');
					$NewNonAgtIds = array();
					for ($v = 1; $v <= $NewNonAgtRowId; $v++) {
						$nonagtId = $this->bsf->isNullCheck($postData['newnonagtid_' . $v], 'string');
						$nonslno = $this->bsf->isNullCheck($postData['newnonagtslno_' . $v], 'string');
						$nonspec = $this->bsf->isNullCheck($postData['newnonagtspec_' . $v], 'string');
//                            $nonwgid = $this->bsf->isNullCheck($postData['newnonagtwgid_' . $v], 'number');
						$nonunitid= $this->bsf->isNullCheck($postData['newnonagtunitid_' . $v], 'number');
						$nonrate= $this->bsf->isNullCheck($postData['newnonagtrate_' . $v], 'number');

						$insert = $sql->insert();
						$insert->into('CB_NonAgtItemMaster')
							->Values(array('SlNo' => $nonslno, 'Specification' => $nonspec, 'UnitId' => $nonunitid
									 , 'WorkGroupId' => '0', 'Rate' => $nonrate,'WORegisterId'=>$billinfo['WORegisterId']
									 , 'SubscriberId' => $subscriberId));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$generatedNonAgtId = $dbAdapter->getDriver()->getLastGeneratedValue();
						$NewNonAgtIds[$nonagtId] = $generatedNonAgtId;
					}

					$entryrowid = $this->bsf->isNullCheck($postData['entryrowid'],'number');
					for ($n = 1; $n <= $entryrowid; $n++) {
						$BillFormatId = $this->bsf->isNullCheck( $postData[ 'BillFormatId_' . $n ], 'number' );
						$CumAmount = $this->bsf->isNullCheck( $postData[ 'CumAmount_' . $n ], 'number' );
						$PrevAmount = $this->bsf->isNullCheck( $postData[ 'PrevAmount_' . $n ], 'number' );
						$CurAmount = $this->bsf->isNullCheck( $postData[ 'CurAmount_' . $n ], 'number' );
						$BillAbsId = $this->bsf->isNullCheck( $postData[ 'BillAbsId_' . $n ], 'number' );
						$Formula = $this->bsf->isNullCheck($postData['Formula_' . $n],'string');

						$update = $sql->update();
						$update->table('CB_BillAbstract')
							->set( array( 'BillId' => $BillId, 'BillFormatId' => $BillFormatId, $strCer.'CumAmount' => $CumAmount
								   , $strCer.'PrevAmount' => $PrevAmount, $strCer.'CurAmount' => $CurAmount, 'Formula' => $Formula ) )
							->where(array('BillAbsId' => $BillAbsId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

						switch($BillFormatId) {
							case '1': // Agreement
								$boqrowid = $this->bsf->isNullCheck($postData['boqrowid'],'number');

								// delete boqs
								$boqrowdeleteids = rtrim($this->bsf->isNullCheck($postData['boqrowdeleteids'],'string'), ",");
								if($boqrowdeleteids !== '') {
									$delete = $sql->delete();
									$delete->from('CB_BillBOQ')
										->where("BillBOQId IN ($boqrowdeleteids)");
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								// insert, update
								for ($i = 1; $i <= $boqrowid; $i++) {
									$BillBOQId = $this->bsf->isNullCheck($postData['BillBOQId_'. $i],'number');
									$UpdateBOQRow = $this->bsf->isNullCheck($postData['UpdateBOQRow_'. $i],'number');

									$WOBOQId = $this->bsf->isNullCheck( $postData[ 'WOBOQId_' . $i ], 'number' );
									$qty = $this->bsf->isNullCheck( $postData[ 'Qty_' . $i ], 'number' );
									$rate = $this->bsf->isNullCheck( $postData[ 'Rate_' . $i ], 'number' );
									$amt = $this->bsf->isNullCheck( $postData[ 'Amount_' . $i ], 'number' );
									$measurement = $this->bsf->isNullCheck( $postData[ 'Measurement_' . $i ], 'string' );
									$cellname = $this->bsf->isNullCheck( $postData[ 'CellName_' . $i ], 'string' );
									$SelectedColumns = $this->bsf->isNullCheck( $postData[ 'SelectedColumns_' . $i ], 'string' );

									if ( $WOBOQId == 0 || ($qty == 0 && $measurement == '') || ($UpdateBOQRow != 1 && $BillBOQId != 0))
										continue;

									if($UpdateBOQRow == 0 && $BillBOQId == 0) { // New Row
										$insert = $sql->insert();
										$insert->into( 'CB_BillBOQ' );
										$insert->Values( array( 'BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'WOBOQId' => $WOBOQId, $strCer.'Rate' => $rate
														, $strCer.'CurQty' => $qty, $strCer.'CurAmount' => $amt, $strCer.'CumQty' => $qty, $strCer.'CumAmount' => $amt ) );
										$statement = $sql->getSqlStringForSqlObject( $insert );
                                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									} else if ($UpdateBOQRow == 1 && $BillBOQId != 0) { // Update Row
										$update = $sql->update();
										$update->table('CB_BillBOQ')
											->set( array( 'BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'WOBOQId' => $WOBOQId, $strCer.'Rate' => $rate
														, $strCer.'CurQty' => $qty, $strCer.'CurAmount' => $amt, $strCer.'CumQty' => new Expression($strCer.'PrevQty +'.$qty)
														, $strCer.'CumAmount' => new Expression($strCer.'PrevAmount +'.$amt) ) )
											->where(array('BillBOQId' => $BillBOQId));
										$statement = $sql->getSqlStringForSqlObject($update);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
									}

									if($measurement != '') {
										$delete = $sql->delete();
										$delete->from('CB_BillMeasurement')
											->where("BillBOQId=$BillBOQId");
										$statement = $sql->getSqlStringForSqlObject($delete);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

										$insert = $sql->insert();
										$insert->into( 'CB_BillMeasurement' );
										$insert->Values( array( 'BillBOQId' => $BillBOQId, $strCer.'Measurement' => $measurement, $strCer.'CellName' => $cellname, $strCer.'SelectedColumns' => $SelectedColumns) );
										$statement = $sql->getSqlStringForSqlObject( $insert );
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									}
								}
								break;
							case '2': // Non-Agreement
								$naboqrowid = $this->bsf->isNullCheck($postData['naboqrowid'],'number');
								// delete boqs
								$naboqrowdeleteids = rtrim($this->bsf->isNullCheck($postData['naboqrowdeleteids'],'string'), ",");
								if($naboqrowdeleteids !== '') {
									$delete = $sql->delete();
									$delete->from('CB_BillBOQ')
										->where("BillBOQId IN ($naboqrowdeleteids)");
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								// insert, update
								for ($i = 1; $i <= $naboqrowid; $i++) {
									$BillBOQId = $this->bsf->isNullCheck($postData['NABillBOQId_'. $i],'number');
									$UpdateBOQRow = $this->bsf->isNullCheck($postData['NAUpdateBOQRow_'. $i],'number');

									$qty = $this->bsf->isNullCheck($postData['NAQty_' . $i],'number');
									$rate = $this->bsf->isNullCheck($postData['NARate_' . $i],'number');
									$amt = $this->bsf->isNullCheck($postData['NAAmount_' . $i],'number');
									$measurement = $this->bsf->isNullCheck( $postData[ 'NAMeasurement_' . $i ], 'string' );
									$cellname = $this->bsf->isNullCheck( $postData[ 'NACellName_' . $i ], 'string' );
									$SelectedColumns = $this->bsf->isNullCheck( $postData[ 'NASelectedColumns_' . $i ], 'string' );
									$NonAgtId = $postData['NABOQId_'.$i];

									if ($NonAgtId == '' || ($qty == 0 && $measurement == '') || $rate==0 || ($UpdateBOQRow != 1 && $BillBOQId != 0))
										continue;

									if(substr($NonAgtId, 0, 3) == 'New')
										$nonboqid = $NewNonAgtIds[ $NonAgtId ];
									else
										$nonboqid  = $this->bsf->isNullCheck($NonAgtId,'number');

									if($UpdateBOQRow == 0 && $BillBOQId == 0) { // New Row
										$insert = $sql->insert();
										$insert->into('CB_BillBOQ');
										$insert->Values(array('BillAbsId' => $BillAbsId,'NonBOQId'=> $nonboqid, 'BillFormatId' => $BillFormatId
															,$strCer.'Rate' => $rate,$strCer.'CurQty'=> $qty,$strCer.'CurAmount'=> $amt, $strCer.'CumQty' => $qty
															, $strCer.'CumAmount' => $amt));
										$statement = $sql->getSqlStringForSqlObject($insert);
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									} else if ($UpdateBOQRow == 1 && $BillBOQId != 0) { // Update Row
										$update = $sql->update();
										$update->table('CB_BillBOQ')
											->set(array('BillAbsId' => $BillAbsId,'NonBOQId'=> $nonboqid,'BillFormatId' => $BillFormatId
												  , $strCer.'Rate' => $rate, $strCer.'CurQty'=> $qty, $strCer.'CurAmount'=> $amt, $strCer.'CumQty' => new Expression($strCer.'PrevQty +'.$qty)
												  , $strCer.'CumAmount' => new Expression($strCer.'PrevAmount +'.$amt)))
											->where(array('BillBOQId' => $BillBOQId));
										$statement = $sql->getSqlStringForSqlObject($update);
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									}

									if($measurement != '') {
										$delete = $sql->delete();
										$delete->from('CB_BillMeasurement')
											->where("BillBOQId=$BillBOQId");
										$statement = $sql->getSqlStringForSqlObject($delete);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

										$insert = $sql->insert();
										$insert->into( 'CB_BillMeasurement' );
										$insert->Values( array( 'BillBOQId' => $BillBOQId, $strCer.'Measurement' => $measurement, $strCer.'CellName' => $cellname, $strCer.'SelectedColumns' => $SelectedColumns) );
										$statement = $sql->getSqlStringForSqlObject( $insert );
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									}
								}
								break;
							case '3': //Material Advance
								$materialadvdeleteids = rtrim($this->bsf->isNullCheck($postData['materialadvdeleteids'],'string'), ",");
								if($materialadvdeleteids !== '') {
									$subQuery = $sql->select();
									$subQuery->from("CB_BillMaterialAdvance")
										->columns(array('MTransId'))
										->where("MTransId IN ($materialadvdeleteids)");

									// select urls
									$select = $sql->select();
									$select->from("CB_BillMaterialBillTrans")
										->columns(array('URL'))
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $statement = $sql->getSqlStringForSqlObject($select);
									$urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

									// delete all bill trans
									$delete = $sql->delete();
									$delete->from('CB_BillMaterialBillTrans')
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

									// unbind files
									foreach($urls as $url) {
										if($url['URL'] != '' || !is_null($url['URL'])) {
											unlink('public' . $url['URL']);
										}
									}

									// delete all material advance
									$delete = $sql->delete();
									$delete->from('CB_BillMaterialAdvance')
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								$materialadvbilldeleteids = rtrim($this->bsf->isNullCheck($postData['materialadvbilldeleteids'],'string'), ",");
								if($materialadvbilldeleteids !== '') {
									// select urls
									$select = $sql->select();
									$select->from("CB_BillMaterialBillTrans")
										->columns(array('URL'))
										->where("MBillTransId IN ($materialadvbilldeleteids)");
									$statement = $statement = $sql->getSqlStringForSqlObject($select);
									$urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

									$delete = $sql->delete();
									$delete->from('CB_BillMaterialBillTrans')
										->where("MBillTransId IN ($materialadvbilldeleteids)");
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

									// unbind files
									foreach($urls as $url) {
										if($url['URL'] != '' || !is_null($url['URL'])) {
											unlink('public' . $url['URL']);
										}
									}
								}

								$materialadvrowid = $this->bsf->isNullCheck($postData['materialadvrowid'],'number');
								for ($i = 1; $i <= $materialadvrowid; $i++) {
									$MaterialId = $this->bsf->isNullCheck($postData['MaterialId_'. $i],'number');
									$qty = $this->bsf->isNullCheck($postData['MQty_' . $i],'number');
									$rate = $this->bsf->isNullCheck($postData['MRate_' . $i],'number');
									$amt = $this->bsf->isNullCheck($postData['MAmount_' . $i],'number');
									$AdvPeramt = $this->bsf->isNullCheck($postData['MAdvancePer_' . $i],'number');
									$Advamt = $this->bsf->isNullCheck($postData['MAdvAmount_' . $i],'number');
									$PurQty = $this->bsf->isNullCheck($postData['MaterialTotalPurQty_' . $i],'number');
									$ConQty = $this->bsf->isNullCheck($postData['MaterialTotalConQty_' . $i],'number');
									$MAdvUpdateRow = $this->bsf->isNullCheck($postData['MAdvUpdateRow_'.$i],'number');
									$MTransId = $this->bsf->isNullCheck($postData['MAdvTransId_'.$i],'number');

									if ($MaterialId == 0 || $qty==0) continue;

									if($MAdvUpdateRow == 0 && $MTransId == 0) { // New Row
										$insert = $sql->insert();
										$insert->into('CB_BillMaterialAdvance');
										$insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt
														,'AdvPercent'=> $AdvPeramt,'AdvAmount'=> $Advamt,'PurchaseQty'=> $PurQty,'ConsumeQty'=> $ConQty, 'TransType' => $BillType));
										$statement = $sql->getSqlStringForSqlObject($insert);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
										$MTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
									} else if ($MAdvUpdateRow == 1 && $MTransId != 0) { // Update Row
										$update = $sql->update();
										$update->table('CB_BillMaterialAdvance')
											->set(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt
												  ,'AdvPercent'=> $AdvPeramt,'AdvAmount'=> $Advamt,'PurchaseQty'=> $PurQty,'ConsumeQty'=> $ConQty))
											->where(array('MTransId' => $MTransId));
										$statement = $sql->getSqlStringForSqlObject($update);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
									}

									$materialadvbillrowid = $this->bsf->isNullCheck($postData['materialadvbillrowid_'.$i],'number');
									for ($j = 1; $j <= $materialadvbillrowid; $j++) {
										$BillDate = $this->bsf->isNullCheck($postData['MBill_'.$i.'_BillDate_' . $j],'date');
										$BillNo = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillNo_' . $j],'string');
										$qty = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillQty_' . $j],'number');
										$rate = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillRate_' . $j],'number');
										$amt = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillAmount_' . $j],'number');
										$UpdateBillDeducRow = $this->bsf->isNullCheck($postData['MBill_'.$i.'_UpdateMBillRow_' . $j],'number');
										$PBillTransId = $this->bsf->isNullCheck($postData['MBill_'.$i.'_PBillTransId_' . $j],'number');
										$url = $this->bsf->isNullCheck($postData['MBill_'.$i.'_DocFile_' . $j], 'string');

										// check for vendorId
										$VendorId = $postData['MBill_'.$i.'_MVendorId_' . $j];
										if(substr($VendorId, 0, 3) == 'New')
											$VendorId = $NewVendorIds[ $VendorId ];
										else
											$VendorId = $this->bsf->isNullCheck($VendorId,'number');

										if ($BillDate == null || $BillNo == '' || $VendorId == 0 || $qty == 0 || $rate == 0 || $amt == 0) continue;

										if($files['MBill_'.$i.'_DocFile_' . $j]['name']){
											$dir = 'public/uploads/cb/clientbilling/'.$BillId.'/';
											$filename = $this->bsf->uploadFile($dir, $files['MBill_'.$i.'_DocFile_' . $j]);

											if($filename) {
												// update valid files only
												$url = '/uploads/cb/clientbilling/'.$BillId.'/' . $filename;
											}
										}

										if($UpdateBillDeducRow == 0 && $PBillTransId == 0) { // New Row
											$insert = $sql->insert();
											$insert->into('CB_BillMaterialBillTrans');
											$insert->Values(array('MTransId' => $MTransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
															,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url));
											$statement = $sql->getSqlStringForSqlObject($insert);
										} else if ($UpdateBillDeducRow == 1 && $PBillTransId != 0) { // Update Row
											$update = $sql->update();
											$update->table('CB_BillMaterialBillTrans')
												->set(array('MTransId' => $MTransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
													  ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url))
												->where(array('MBillTransId' => $PBillTransId));
											$statement = $sql->getSqlStringForSqlObject($update);
										}
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									}
								}
								break;
							case '18': // Price Escalation
								$priceescalationdeleteids = rtrim($this->bsf->isNullCheck($postData['priceescalationdeleteids'],'string'), ",");
								if($priceescalationdeleteids !== '') {
									$subQuery = $sql->select();
									$subQuery->from("CB_BillPriceEscalation")
										->columns(array('MTransId'))
										->where("MTransId IN ($priceescalationdeleteids)");

									// select urls
									$select = $sql->select();
									$select->from("CB_BillPriceEscalationBillTrans")
										->columns(array('URL'))
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $statement = $sql->getSqlStringForSqlObject($select);
									$urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

									// delete all bill trans
									$delete = $sql->delete();
									$delete->from('CB_BillPriceEscalationBillTrans')
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

									// unbind files
									foreach($urls as $url) {
										if($url['URL'] != '' || !is_null($url['URL'])) {
											unlink('public' . $url['URL']);
										}
									}

									// delete all material advance
									$delete = $sql->delete();
									$delete->from('CB_BillPriceEscalation')
										->where->expression('MTransId IN ?', array($subQuery));
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								$priceescalationbilldeleteids = rtrim($this->bsf->isNullCheck($postData['priceescalationbilldeleteids'],'string'), ",");
								if($priceescalationbilldeleteids !== '') {
									// select urls
									$select = $sql->select();
									$select->from("CB_BillPriceEscalationBillTrans")
										->columns(array('URL'))
										->where("PBillTransId IN ($priceescalationbilldeleteids)");
									$statement = $statement = $sql->getSqlStringForSqlObject($select);
									$urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

									$delete = $sql->delete();
									$delete->from('CB_BillPriceEscalationBillTrans')
										->where("PBillTransId IN ($priceescalationbilldeleteids)");
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

									// unbind files
									foreach($urls as $url) {
										if($url['URL'] != '' || !is_null($url['URL'])) {
											unlink('public' . $url['URL']);
										}
									}
								}

								$priceescalationrowid = $this->bsf->isNullCheck($postData['priceescalationrowid'],'number');
								for ($i = 1; $i <= $priceescalationrowid; $i++) {
									$MaterialId = $this->bsf->isNullCheck($postData['EMaterialId_'. $i],'number');
									$qty = $this->bsf->isNullCheck($postData['EMQty_' . $i],'number');
									$brate = $this->bsf->isNullCheck($postData['EMRate_' . $i],'number');
									$escPer = $this->bsf->isNullCheck($postData['EMEscPercent_' . $i],'number');
									$advPer = $this->bsf->isNullCheck($postData['EMAdvancePer_' . $i],'number');
									$amt = $this->bsf->isNullCheck($postData['EMAmount_' . $i],'number');
									$rateCondition = $this->bsf->isNullCheck($postData['ERateCondition_' . $i],'string');
									$EUpdateRow = $this->bsf->isNullCheck($postData['EUpdateRow_'.$i],'number');
									$ETransId = $this->bsf->isNullCheck($postData['ETransId_'.$i],'number');

									if ($MaterialId == 0 || $qty==0) continue;

									if($EUpdateRow == 0 && $ETransId == 0) { // New Row
										$insert = $sql->insert();
										$insert->into('CB_BillPriceEscalation');
										$insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Qty'=> $qty
														,'BaseRate'=> $brate,'EscalationPer'=> $escPer,'ActualRate'=> $advPer,'Amount'=> $amt, 'RateCondition' => $rateCondition, 'TransType' => $BillType));
										$statement = $sql->getSqlStringForSqlObject($insert);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
										$ETransId = $dbAdapter->getDriver()->getLastGeneratedValue();
									} else if ($EUpdateRow == 1 && $ETransId != 0) { // Update Row
										$update = $sql->update();
										$update->table('CB_BillPriceEscalation')
											->set(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Qty'=> $qty
												  ,'BaseRate'=> $brate,'EscalationPer'=> $escPer,'ActualRate'=> $advPer,'Amount'=> $amt, 'RateCondition' => $rateCondition))
											->where(array('MTransId' => $ETransId));
										$statement = $sql->getSqlStringForSqlObject($update);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
									}

									$embillrowid = $this->bsf->isNullCheck($postData['embillrowid_'.$i],'number');
									for ($j = 1; $j <= $embillrowid; $j++) {
										$BillDate = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillDate_' . $j],'date');
										$BillNo = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillNo_' . $j],'string');
										$qty = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillQty_' . $j],'number');
										$rate = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillRate_' . $j],'number');
										$amt = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillAmount_' . $j],'number');
										$UpdateBillDeducRow = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_UpdateMBillRow_' . $j],'number');
										$PBillTransId = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillTransId_' . $j],'number');
										$url = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_DocFile_' . $j], 'string');

										// check for vendorId
										$VendorId = $postData['EMBill_'.$i.'_PVendorId_' . $j];
										if(substr($VendorId, 0, 3) == 'New')
											$VendorId = $NewVendorIds[ $VendorId ];
										else
											$VendorId = $this->bsf->isNullCheck($VendorId,'number');

										if ($BillDate == null || $BillNo == '' || $VendorId == 0 || $qty == 0 || $rate == 0 || $amt == 0) continue;

										if($files['EMBill_'.$i.'_DocFile_' . $j]['name']){
											$dir = 'public/uploads/cb/clientbilling/'.$BillId.'/';
											$filename = $this->bsf->uploadFile($dir, $files['EMBill_'.$i.'_DocFile_' . $j]);

											if($filename) {
												// update valid files only
												$url = '/uploads/cb/clientbilling/'.$BillId.'/' . $filename;
											}
										}

										if($UpdateBillDeducRow == 0 && $PBillTransId == 0) { // New Row
											$insert = $sql->insert();
											$insert->into('CB_BillPriceEscalationBillTrans');
											$insert->Values(array('MTransId' => $ETransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
															,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url));
											$statement = $sql->getSqlStringForSqlObject($insert);
											$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
										} else if ($UpdateBillDeducRow == 1 && $PBillTransId != 0) { // Update Row
											$update = $sql->update();
											$update->table('CB_BillPriceEscalationBillTrans')
												->set(array('MTransId' => $ETransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
													  ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url))
												->where(array('PBillTransId' => $PBillTransId));
											$statement = $sql->getSqlStringForSqlObject($update);
											$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
										}

									}
								}
								break;
							case '5': // MobAdvance Recovery
								$delete = $sql->delete();
								$delete->from('CB_BillAdvanceRecovery')
									->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>5));
								$statement = $sql->getSqlStringForSqlObject($delete);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								$mobadvrecoveryrowid = $this->bsf->isNullCheck($postData['mobadvrecoveryrowid'],'number');
								for ($i = 1; $i <= $mobadvrecoveryrowid; $i++) {
									$AdvRecoveryReceiptId = $this->bsf->isNullCheck($postData['mobAdvRecoveryReceiptId_'. $i],'number');
									$curAmt = $this->bsf->isNullCheck($postData['mobAdvRecoveryCurrent_' . $i],'number');

									if ($curAmt == 0 || ($AdvRecoveryReceiptId == 0)) continue;

									$insert = $sql->insert();
									$insert->into('CB_BillAdvanceRecovery');
									$insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId, 'ReceiptId' => $AdvRecoveryReceiptId
													,'BillFormatId' => 5, $strCer.'Amount'=> $curAmt ));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
								break;
							case '6': // Advance Recovery
								$delete = $sql->delete();
								$delete->from('CB_BillAdvanceRecovery')
									->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>6));
								$statement = $sql->getSqlStringForSqlObject($delete);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								$advrecoveryrowid = $this->bsf->isNullCheck($postData['advrecoveryrowid'],'number');
								for ($i = 1; $i <= $advrecoveryrowid; $i++) {
									$AdvRecoveryReceiptId = $this->bsf->isNullCheck($postData['AdvRecoveryReceiptId_'. $i],'number');
									$curAmt = $this->bsf->isNullCheck($postData['AdvRecoveryCurrent_' . $i],'number');

									if ($curAmt == 0) continue;

									$insert = $sql->insert();
									$insert->into('CB_BillAdvanceRecovery');
									$insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId, 'ReceiptId' => $AdvRecoveryReceiptId
													,'BillFormatId' => 6, $strCer.'Amount'=> $curAmt ));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
						break;
							case '21': // Material Advance Recovery
								$delete = $sql->delete();
								$delete->from('CB_BillAdvanceRecovery')
									->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>21));
								$statement = $sql->getSqlStringForSqlObject($delete);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								$madvrecoveryrowid = $this->bsf->isNullCheck($postData['madvrecoveryrowid'],'number');
								for ($i = 1; $i <= $madvrecoveryrowid; $i++) {
									//$AdvRecoveryBillId = $this->bsf->isNullCheck($postData['MAdvRecoveryBillId_'. $i],'number');
									$curAmt = $this->bsf->isNullCheck($postData['MAdvRecoveryCurrent_' . $i],'number');

									if ($curAmt == 0) continue;

									$insert = $sql->insert();
									$insert->into('CB_BillAdvanceRecovery');
									$insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId,'BillFormatId' => 21, $strCer.'Amount'=> $curAmt ));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
						break;
							case '8': // Material Recovery
								$delete = $sql->delete();
								$delete->from('CB_BillMaterialRecovery')
									->where(array("BillAbsId" => $BillAbsId));
								$statement = $sql->getSqlStringForSqlObject($delete);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								$mrecoveryrowid = $this->bsf->isNullCheck($postData['mrecoveryrowid'],'number');
								for ($i = 1; $i <= $mrecoveryrowid; $i++) {
									$RMaterialId = $this->bsf->isNullCheck($postData['RMaterialId_'. $i],'number');
									$qty = $this->bsf->isNullCheck($postData['RMQty_' . $i],'number');
									$rate = $this->bsf->isNullCheck($postData['RMRate_' . $i],'number');
									$amt = $this->bsf->isNullCheck($postData['RMAmount_' . $i],'number');

									if ($RMaterialId == 0 || $qty==0) continue;

									$insert = $sql->insert();
									$insert->into('CB_BillMaterialRecovery');
									$insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $RMaterialId
													,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt, 'TransType' => $BillType ));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
								break;
							case '7': // Bill Deduction
								// delete vendor bills
								$billdeductiondeleteids = rtrim($this->bsf->isNullCheck($postData['billdeductiondeleteids'],'string'), ",");
								if($billdeductiondeleteids !== '') {
									// select urls
									$select = $sql->select();
									$select->from("CB_BillVendorBill")
										->columns(array('URL'))
										->where("TransId IN ($billdeductiondeleteids)");
									$statement = $statement = $sql->getSqlStringForSqlObject($select);
									$urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

									$delete = $sql->delete();
									$delete->from('CB_BillVendorBill')
										->where("TransId IN ($billdeductiondeleteids)");
									$statement = $sql->getSqlStringForSqlObject($delete);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

									// unbind files
									foreach($urls as $url) {
										if($url['URL'] != '' || !is_null($url['URL'])) {
											unlink('public' . $url['URL']);
										}
									}
								}

								$billdeductionrowid = $this->bsf->isNullCheck($postData['billdeductionrowid'],'number');
								for ($i = 1; $i <= $billdeductionrowid; $i++) {
									$DBillDate = $this->bsf->isNullCheck($postData['DBillDate_'. $i],'date');
									$DBillNo = $this->bsf->isNullCheck($postData['DBillNo_'. $i],'string');
									$amt = $this->bsf->isNullCheck($postData['DAmount_' . $i],'number');
									$UpdateBillDeducRow = $this->bsf->isNullCheck($postData['UpdateDBillRow_' . $i],'number');
									$TransId = $this->bsf->isNullCheck($postData['DBillTransId_' . $i],'number');
									$url = $this->bsf->isNullCheck($postData['DDocFile_' . $i], 'string');

                                    // check for vendorId
                                    $DVendorId = $postData['DVendorId_'. $i];
                                    if(substr($DVendorId, 0, 3) == 'New')
                                        $DVendorId = $NewVendorIds[ $DVendorId ];
                                    else
                                        $DVendorId = $this->bsf->isNullCheck($DVendorId,'number');

									if ($DVendorId == 0 || $DBillDate== null || $DBillNo == '' || $amt == 0) continue;

									if($files['DDocFile_' . $i]['name']){
										$dir = 'public/uploads/cb/clientbilling/'.$BillId.'/';
										$filename = $this->bsf->uploadFile($dir, $files['DDocFile_' . $i]);

										if($filename) {
											// update valid files only
											$url = '/uploads/cb/clientbilling/'.$BillId.'/' . $filename;
										}
									}

									if($UpdateBillDeducRow == 0 && $TransId == 0) { // New Row
										$insert = $sql->insert();
										$insert->into('CB_BillVendorBill');
										$insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'VendorId' => $DVendorId
														, 'BillDate' => date('Y-m-d', strtotime($DBillDate)) ,'BillNo' => $DBillNo,'Amount'=> $amt, 'URL' => $url, 'TransType' => $BillType ));
										$statement = $sql->getSqlStringForSqlObject($insert);
										$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
									} else if ($UpdateBillDeducRow == 1 && $TransId != 0) { // Update Row
										$update = $sql->update();
										$update->table('CB_BillVendorBill')
											->set( array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'VendorId' => $DVendorId
												   , 'BillDate' => date('Y-m-d', strtotime($DBillDate)) ,'BillNo' => $DBillNo,'Amount'=> $amt, 'URL' => $url ))
											->where(array('TransId' => $TransId));
										$statement = $sql->getSqlStringForSqlObject($update);
										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
									}
								}
								break;
							case '19': // Free Supply Material
								$delete = $sql->delete();
								$delete->from('CB_BillFreeSupplyMaterial')
									->where(array("BillAbsId" => $BillAbsId));
								$statement = $sql->getSqlStringForSqlObject($delete);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								$fsmaterialrowid = $this->bsf->isNullCheck($postData['fsmaterialrowid'],'number');
								for ($i = 1; $i <= $fsmaterialrowid; $i++) {
									$FSMaterialId = $this->bsf->isNullCheck($postData['FSMaterialId_'. $i],'number');
									$qty = $this->bsf->isNullCheck($postData['FSMQty_' . $i],'number');
									$rate = $this->bsf->isNullCheck($postData['FSMRate_' . $i],'number');
									$amt = $this->bsf->isNullCheck($postData['FSMAmount_' . $i],'number');

									if ($FSMaterialId == 0 || $qty==0) continue;

									$insert = $sql->insert();
									$insert->into('CB_BillFreeSupplyMaterial');
									$insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $FSMaterialId
													,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt, 'TransType' => $BillType ));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
								break;
						}
					}
					
					//Save Role
					$MBillNo = $this->bsf->isNullCheck($postData['MBillNo'],'string');
					if($BillType == 'S' ) {
						if(isset($postData['isSubCer'])){
							CommonHelper::insertCBLog('Client-Bill-Submit-Approve', $BillId, $MBillNo, $dbAdapter);
						} else{
							CommonHelper::insertCBLog('Client-Bill-Submit-Edit', $BillId, $MBillNo, $dbAdapter);
						}
					} elseif($BillType == 'C') {
						if(isset($postData['isSubCer'])){
							CommonHelper::insertCBLog('Client-Bill-Certify-Approve', $BillId, $MBillNo, $dbAdapter);
						} else {
							CommonHelper::insertCBLog('Client-Bill-Certify-Edit', $BillId, $MBillNo, $dbAdapter);
						}
					}

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
                    $select->from(array('a' => "CB_BillMaster"))
                        ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo', 'WODate'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'CB_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'CB_ProjectMaster'), 'b.ProjectId=d.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
                        ->where(array("a.BillId" => $BillId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $bill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($BillType == 'S' && $oldBillData['IsSubmittedBill'] != $bill['IsSubmittedBill']){
                        $mailData = array(
                            array(
                                'name' => 'ORDERID',
                                'content' => $bill['WONo']
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => date('d-m-Y', strtotime($bill['WODate']))
                            ),
                            array(
                                'name' => 'PROJECTNAME',
                                'content' => $bill['ProjectName']
                            ),
                            array(
                                'name' => 'CLIENTNAME',
                                'content' => $bill['ClientName']
                            ),
                            array(
                                'name' => 'BILLNUMBER',
                                'content' => $bill['BillNo']
                            ),
                            array(
                                'name' => 'BILLDATE',
                                'content' => date('d-m-Y', strtotime($bill['BillDate']))
                            ),
                            array(
                                'name' => 'AMOUNT',
                                'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['SubmitAmount'],2,true)
                            )
                        );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        if($subscriber && $subscriber['Email'] != '') {
                            $viewRenderer->MandrilSendMail()->sendMailTo( $subscriber[ 'Email' ], $config['general']['mandrilEmail'], 'Bill Submitted', 'cb_billsubmitted', $mailData );
                        }
                        if($user && $user['Email'] != '' && ($subscriber && $subscriber['Email'] != $user['Email'])) {
                            $viewRenderer->MandrilSendMail()->sendMailTo( $user[ 'Email' ], $config['general']['mandrilEmail'], 'Bill Submitted', 'cb_billsubmitted', $mailData );
                        }
                    } elseif($BillType == 'C' && $oldBillData['IsCertifiedBill'] != $bill['IsCertifiedBill']){
                        $mailData = array(
                            array(
                                'name' => 'ORDERID',
                                'content' => $bill['WONo']
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => date('d-m-Y', strtotime($bill['WODate']))
                            ),
                            array(
                                'name' => 'PROJECTNAME',
                                'content' => $bill['ProjectName']
                            ),
                            array(
                                'name' => 'CLIENTNAME',
                                'content' => $bill['ClientName']
                            ),
                            array(
                                'name' => 'BILLNUMBER',
                                'content' => $bill['BillNo']
                            ),
                            array(
                                'name' => 'BILLDATE',
                                'content' => date('d-m-Y', strtotime($bill['BillDate']))
                            ),
                            array(
                                'name' => 'SUBMITTEDAMOUNT',
                                'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['SubmitAmount'],2,true)
                            ),
                            array(
                                'name' => 'CERTIFIEDAMOUNT',
                                'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['CertifyAmount'],2,true)
                            )
                        );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();

                        if($subscriber && $subscriber['Email'] != '') {
                            $viewRenderer->MandrilSendMail()->sendMailTo($subscriber['Email'], $config['general']['mandrilEmail'], 'Bill Certified', 'cb_billcertified', $mailData );
                        }
                        if($user && $user['Email'] != '' && ($subscriber && $subscriber['Email'] != $user['Email'])) {
                            $viewRenderer->MandrilSendMail()->sendMailTo($user['Email'],$config['general']['mandrilEmail'], 'Bill Certified', 'cb_billcertified', $mailData );
                        }
                    }
				}

				$connection->commit();
				$this->redirect()->toRoute('cb/clientbilling', array('controller' => 'clientbilling', 'action' => 'billselection'));
			} catch ( PDOException $e ) {
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		} else {
			$editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
			$mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

			// check for bill type
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'billselection' ) );

			// check for bill id and subscriber id
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'billselection' ) );

			if ($editid != 0) {
				// Bill Info
				$select = $sql->select();
				$select->from(array('a' => "CB_BillMaster"))
					->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId', 'BsfWORegisterId'), $select:: JOIN_LEFT)
					->join(array('c' => 'CB_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
					->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
								  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
						,array('WONo','WODate','WorkOrderId'))
					->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
				$statement = $sql->getSqlStringForSqlObject($select);
				$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

				if ($billinfo) {
					$billinfo['TransType'] = $type;
					$WOId = $billinfo['WorkOrderId'];
					$billType = $billinfo['BillType'];
					if($billType=="R" || $billType=="F" || $billType=="S" )
						$billType = array('R', 'S', 'F');
					else
						$billType = array($billinfo['BillType']);

					/* BillFormatTransId Update */
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_BillAbstract' ) )
						->columns(array('BillFormatId','BillAbsId','Formula'))
//                            ->where( "a.BillId=$editid");
						->where( "a.BillId=$editid AND a.BillFormatTransId=0 AND a.BillFormatId<>0");
					$statement = $sql->getSqlStringForSqlObject( $select );
					$BillAbsupdate = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($BillAbsupdate as &$bilAbsFTupdate) {
						$billFormatId= $bilAbsFTupdate['BillFormatId'];
						$billAbsId= $bilAbsFTupdate['BillAbsId'];
						$billFormula= $bilAbsFTupdate['Formula'];

						$select = $sql->select();
						$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
							->columns(array('BillFormatTransId'))
							->where( "a.WorkOrderId=$WOId AND a.BillFormatId=$billFormatId");
						$statement = $sql->getSqlStringForSqlObject( $select );
						$BillAbsupdateList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						
						foreach($BillAbsupdateList as &$billAbsupdateList) {
							$update = $sql->update();
							$update->table('CB_BillAbstract')
								->set( array('BillFormatTransId' => $billAbsupdateList['BillFormatTransId'] ))
								->where(array('billAbsId' => $billAbsId));
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					/* BillFormatTransId Update */
					$sCer = "";
					if($type == 'C') {
						$sCer = "Cer";
					}
					
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
						->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
						->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount' =>$sCer.'CumAmount','PrevAmount' =>$sCer.'PrevAmount','CurAmount' =>$sCer.'CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
						->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header','Bold', 'Italic', 'Underline'))
						->where( "a.WorkOrderId=$WOId AND c.BillId=$editid")
						->order('a.SortId');
					$statement = $sql->getSqlStringForSqlObject( $select );
					$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					foreach($BillFormat as &$Format) {
						$billFormatId= $Format['BillFormatId'];
						$billAbsId= $Format['BillAbsId'];
						switch($billFormatId) {
							case '1': // Agreement
								/*$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillBOQ' ) )
									->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");*/

								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillBOQ' ) )
									->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select::JOIN_LEFT )
									->join( array( 'b1' => 'CB_WOBOQ' ), 'b.WBSId=b1.WOBOQId', array( 'Header','HeaderType', 'TotalQty' => 'Qty'), $select::JOIN_LEFT )
									->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount', 'CumQty' => $sCer. 'CumQty', 'BalQty' => new Expression("b.Qty-a.CumQty")))
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='W' AND b.WBSId<>0");

								$select2 = $sql->select();
								$select2->from( array( 'a' => 'CB_BillBOQ' ) )
									->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select2::JOIN_LEFT )
									->join( array( 'b1' => 'CB_WOBOQ' ), 'b.ParentId=b1.WOBOQId', array( 'Header','HeaderType', 'TotalQty' => 'Qty'), $select2::JOIN_LEFT )
									->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount', 'CumQty' => $sCer. 'CumQty', 'BalQty' => new Expression("b.Qty-a.CumQty")))
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='P' AND b.ParentId<>0");
								$select2->combine($select,'Union ALL');
								
								$select1 = $sql->select();
								$select1->from( array( 'a' => 'CB_BillBOQ' ) )
									->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId','Header','HeaderType', 'TotalQty' => 'Qty'), $select1::JOIN_LEFT )
									->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount', 'CumQty' => $sCer. 'CumQty', 'BalQty' => new Expression("b.Qty-a.CumQty")))
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b.HeaderType='' AND b.ParentId=0 AND b.WBSId=0");
								$select1->combine($select2,'Union ALL');

								$select3 = $sql->select();
								$select3->from(array("g"=>$select1))
									->columns(array('*'))
									->join( array( 'c' => 'Proj_UOM' ), 'g.unit=c.UnitId', array( 'UnitId','UnitName'), $select3::JOIN_LEFT )
									->join( array( 'd' => 'CB_BillMeasurement' ), 'g.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select3::JOIN_LEFT )
									->order('g.SortId');
								$statement = $sql->getSqlStringForSqlObject( $select3 );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '2': // Non-Agreement
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillBOQ' ) )
									->join( array( 'b' => 'CB_NonAgtItemMaster' ), 'a.NonBOQId=b.NonBOQId', array( 'SlNo','Specification','NonBOQId'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement','CellName', 'SelectedColumns'), $select::JOIN_LEFT )
									->columns(array('BillBOQId', 'NonBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '3': //Material Advance
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialAdvance' ) )
									->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

								foreach($Format['AddRow'] as &$advance) {
									$MTransId = $advance['MTransId'];
									$select = $sql->select();
									$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
										->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
										->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
										->where( "a.MTransId=$MTransId");
									$statement = $sql->getSqlStringForSqlObject( $select );
									$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								}
								break;
							case '18': // Price Escalation
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
									->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

								foreach($Format['AddRow'] as &$advance) {
									$MTransId = $advance['MTransId'];
									$select = $sql->select();
									$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
										->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
										->columns(array('PBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
										->where( "a.MTransId=$MTransId");
									$statement = $sql->getSqlStringForSqlObject( $select );
									$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								}
								break;
							case '5': // MobAdvRecovery
								// Advance Recovery (Receipt & Material Advance)
								$select = $sql->select();
								$select->from( array('a' => 'CB_ReceiptRegister' ))
									->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
									->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'M'));
									
								$select2 = $sql->select(); 
								$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
										->where(array('b.BillId' => $editid, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select2->combine($select,'Union ALL');
								
								$select21 = $sql->select(); 
								$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
										->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select21->where("b.BillId<>$editid");
								$select21->combine($select2,'Union ALL');
								
								$select3 = $sql->select();
								$select3->from(array("g"=>$select21))
										->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
										array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
										->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
								$select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
								$select3->order('g.ReceiptId');
								$statement = $sql->getSqlStringForSqlObject($select3);
								$Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '6': // Advance Recovery                                   
								//Advance Recovery Receipt
								$select = $sql->select();
								$select->from( array('a' => 'CB_ReceiptRegister' ))
									->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
									->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'A'));
									
								$select2 = $sql->select(); 
								$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
										->where(array('b.BillId' => $editid, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select2->where("b.ReceiptId<>0");
								$select2->combine($select,'Union ALL');
								
								$select21 = $sql->select(); 
								$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
										->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select21->where("b.BillId<>$editid AND b.ReceiptId<>0");
								$select21->combine($select2,'Union ALL');
								
								$select3 = $sql->select();
								$select3->from(array("g"=>$select21))
										->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
										array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
										->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
								$select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
								$select3->order('g.ReceiptId');
								$statement = $sql->getSqlStringForSqlObject($select3);
								$Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '21': // Material Advance Recovery
								//Advance Recovery BillAbstract FormatTypeId=3
								/**/
								$select = $sql->select();
								$select->from( array('a' => 'CB_BillAbstract' ))
									->columns(array( 'BillId', 'BillFormatId' => new Expression("21"), 'Amount' => new Expression("a.".$sCer."CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
									->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
									->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
									->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
								$select->where("a.CurAmount<>0 ");
								
								$selectsub = $sql->select();
								$selectsub->from(array("g1"=>$select))
										->columns(array('BillAbsId' => new Expression("h.BillAbsId"), '*'))
										->join(array('h' => 'CB_BillAbstract'), 'g1.BillId=h.BillId and g1.BillFormatId=h.BillFormatId', array(), $selectsub::JOIN_INNER);
								/**/
								$select21 = $sql->select(); 
								$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
										->where(array('b.BillId' => $editid, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
								$select21->combine($selectsub,'Union ALL');
								
								$select2 = $sql->select(); 
								$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
										->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
										->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
										->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
								$select2->where("b.BillId<>$editid");
								$select2->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
								$select2->combine($select21,'Union ALL');
								 
								$select3 = $sql->select();
								$select3->from(array("g"=>$select2))
										->columns(array("BillAbsId","BillId","BillFormatId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
										array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ))
										->join(array('c' => 'CB_BillMaster'), 'g.BillId=c.BillId', array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ), $select3::JOIN_INNER);
								$select3->group(new Expression('g.BillAbsId,g.BillId,g.BillFormatId,c.BillNo,c.BillDate'));
								$select3->order('g.BillId');
								$statement = $sql->getSqlStringForSqlObject($select3);
								$Format['BillAbstract'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '8': // Material Recovery
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialRecovery' ) )
									->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '7': // Bill Deduction
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillVendorBill' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
									->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
							case '19': // Free Supply Material
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
									->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
									->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
									->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								break;
						}
					}
					$this->_view->BillFormat = $BillFormat;

					$select = $sql->select();
					$select->from('CB_ReceiptRegister')
						->columns( array('ReceiptNo', "ReceiptDate" =>  new Expression("FORMAT(ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst", "ReceiptMode", "Amount" ))
						->where( "WORegisterId='$WOId' AND DeleteFlag=0" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->ReceiptDetails = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WOBOQ' ) )
						->join( array( 'b' => 'Proj_UOM' ), 'a.UnitId=b.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
						->columns( array("id" => 'WOBOQId', "TransType", "value" => new Expression( "AgtNo + ' ' + Specification" ), "UnitId", "Rate" , 'HeaderType', 'TotalQty' => 'Qty',
								   'data'=> new Expression("Case When a.HeaderType='G' then 'Workgroup' When a.HeaderType='P' then 'Parent' When a.HeaderType='W' then 'WBS' else 'Item' End"), 'Header', 'ParentId', 'WBSId'), array('UnitName'))
						->where( "a.WORegisterId='$WOId'" )
						->order('a.SortId ASC');
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->boq_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					$select = $sql->select();
					$select->from( array( 'a' => 'CB_NonAgtItemMaster' ) )
						->join( array( 'b' => 'Proj_UOM' ), 'a.UnitId=b.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
						->columns( array( "data" => 'NonBOQId', "value" => new Expression( "SlNo + ' ' + Specification" ), "UnitId", "Rate"),array('UnitName'))
						->where( "a.WORegisterId='$WOId' AND a.SubscriberId='$subscriberId'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->nonboq_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					// Material Advance - materials list
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WOMaterialAdvance' ) )
						->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'value' => 'MaterialName', 'UnitId' ), $select::JOIN_LEFT )
						->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
						->columns( array( "data" => 'MaterialId',"AdvPercent"))
						->where( "a.WORegisterId='$WOId'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->materialadv_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					// Material Recovery - materials list
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WOExcludeMaterial' ) )
						->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'value' => 'MaterialName', 'UnitId' ), $select::JOIN_LEFT )
						->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
						->columns( array( "data" => 'MaterialId', 'Rate') )
						->where( "a.WORegisterId='$WOId' AND SType ='C'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->recoverymaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					// Price Escalation - materials list
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WOMaterialBaseRate' ) )
						->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'value' => 'MaterialName', 'UnitId' ), $select::JOIN_LEFT )
						->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
						->columns( array( "data" => 'MaterialId', "Rate", "EscalationPer","RateCondition","ActualRate"))
						->where( "a.WORegisterId='$WOId'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->priceescmaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					// Free Supply Material - materials list
					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WOExcludeMaterial' ) )
						->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'value' => 'MaterialName', 'UnitId' ), $select::JOIN_LEFT )
						->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
						->columns( array( "data" => 'MaterialId', 'Rate') )
						->where( "a.WORegisterId='$WOId' AND SType ='F'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->freesupplymaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

					$select = $sql->select();
					$select->from( array( 'a' => 'CB_WorkGroupMaster' ) )
						->columns( array( "data" => 'WorkGroupId', "value" => 'WorkGroupName'))
						->where( "a.SubscriberId='$subscriberId'" );
					$statement = $sql->getSqlStringForSqlObject( $select );
					$this->_view->workgroup_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				}
				$this->_view->billinfo = $billinfo;
			}

			// vendors
			$select = $sql->select();
			$select->from('CB_VendorMaster' )
				->columns(array("data"=>'VendorId',"value"=> "VendorName"))
				->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->vendors = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

			//Units
			$select = $sql->select();
			$select->from('Proj_UOM')
				->columns(array("data"=>'UnitId', "value"=>'UnitName'));
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			// Excel Templates
			$select = $sql->select();
			$select->from('CB_TemplateMaster')
				->columns(array('TemplateId','TemplateName','Description'))
				->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->exceltemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			$this->_view->billid = $editid;
			$this->_view->mode = $mode;
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}

    public function checkboqfoundAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $woboqid = $this->bsf->isNullCheck($this->params()->fromPost('woboqid'), 'string');
                $billid = $this->bsf->isNullCheck($this->params()->fromPost('billid'), 'string');
                $woid = $this->bsf->isNullCheck($this->params()->fromPost('woid'), 'string');

                $select = $sql->select();
                $select->from(array('a' =>'CB_BillAbstract'))
                    ->join(array('b' => 'CB_BillBOQ'), 'a.BillAbsId = b.BillAbsId', array('WOBOQId'), $select::JOIN_INNER)
                    ->join(array('c' => 'CB_BillMaster'), 'a.BillId = c.BillId', array(), $select::JOIN_INNER)
                    ->columns( array())
                    ->where( "b.WOBOQId = $woboqid AND a.BillId < $billid AND c.WORegisterId = $woid AND (b.CurQty <> 0 or b.CerCurQty <> 0) AND c.SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

    public function uploadboqdataAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $uploadedFile = $request->getFiles();

                if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                    $response->setContent('Invalid File Format');
                    $response->setStatusCode(400);
                    return $response;
                }

                $file_csv = "public/uploads/cb/tmp/" . md5(time()) .".csv";
                $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                $data = array();
                $file = fopen($file_csv, "r");

                $icount = 0;
                $bValid =true;

                while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                    if ($icount == 0) {
                        foreach ($xlData as $j => $value) {
                            if (trim($value) == "Specification")
                                $col_1 = $j;
                            if (trim($value) == "Unit")
                                $col_2 = $j;
                            if (trim($value) == "Qty")
                                $col_3 = $j;
                            if (trim($value) == "Rate")
                                $col_4 = $j;
                        }
                    } else {
                        if (!isset($col_1) || !isset($col_2) || !isset($col_3) || !isset($col_4)) { $bValid =false; break;}

                        $select = $sql->select();

                        $select->from('Proj_UOM')
                            ->columns(array('UnitId', 'UnitName'))
                            ->where(array("UnitName='$xlData[$col_2]'"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $row = $results->current();

                        $data[] = array('Valid'=>$bValid,'Spec'=>$xlData[$col_1],
                            'UnitId' => $row['UnitId'], 'Unit' => $row['UnitName'], 'Qty' => $xlData[$col_3],
                            'Rate' => $xlData[$col_4]);
                    }
                    $icount = $icount + 1;
                }

                if ($bValid==false){$data[] = array('Valid'=>$bValid);}

                // delete csv file
                fclose($file);
                unlink($file_csv);

                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    function _convertXLStoCSV($infile, $outfile) {
        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);
    }

    function _validateUploadFile($file) {
        $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
        $mime_types = array('application/octet-stream','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain','application/csv', 'text/comma-separated-values', 'application/excel');
        $exts = array('csv', 'xls', 'xlsx');

        if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
            return false;

        return true;
    }

    public function registerAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Billing Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $select = $sql->select();
        $select->from( array('a' => 'CB_BillMaster' ))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('ProjectId'), $select::JOIN_LEFT)
            ->join(array('e' => 'CB_ClientMaster'), 'b.ClientId=e.ClientId', array('ClientName'), $select::JOIN_LEFT)
            ->join(array('d' => 'CB_ProjectMaster'), 'b.ProjectId=d.ProjectId', array('ProjectName','ProjectDescription'), $select::JOIN_LEFT)
            ->join(array('c' => 'CB_ReceiptAdustment'), 'a.BillId=c.BillId', array( 'PaymentReceived' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
            ->columns( array( 'BillId','BillNo','BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')")
                       , 'WORegisterId', 'SubmitAmount', 'CertifyAmount','IsSubmittedBill', 'IsCertifiedBill','Submitted', 'Certified') )
            ->where(array('a.DeleteFlag' => '0', 'b.SubscriberId' => $subscriberId))
            ->group(new Expression('a.BillId,a.BillNo,a.BillDate,e.ClientName,a.WORegisterId,b.ProjectId,a.SubmitAmount,a.CertifyAmount,a.IsSubmittedBill,a.IsCertifiedBill,a.Submitted,a.Certified,d.ProjectName,d.ProjectDescription'))
            ->order('a.BillId ASC');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from( array('a' => 'CB_BillMaster' ))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(), $select::JOIN_LEFT)
            ->columns(array('projects' =>new Expression("Count(Distinct(b.ProjectId))")))
            ->where("a.DeleteFlag='0' AND b.LiveWO ='0' AND a.SubscriberId = '$subscriberId'");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->projectcount = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from('CB_WORegister')
            ->columns(array('OrderAmt' =>new Expression("Sum(OrderAmount)")))
            ->where("DeleteFlag='0' AND LiveWO ='0' AND SubscriberId = '$subscriberId'");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->ordervalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from('CB_BillMaster')
            ->columns(array('submitAmount' =>new Expression("Sum(SubmitAmount)")))
            ->where("DeleteFlag='0' AND SubscriberId = '$subscriberId'");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->submitvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from('CB_BillMaster')
            ->columns(array('certifyAmount' =>new Expression("Sum(CertifyAmount)")))
            ->where("DeleteFlag='0' AND SubscriberId = '$subscriberId'");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->certifyvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_BillMaster'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('ClientId'),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_ClientMaster'), 'b.ClientId=c.ClientId',array('ClientName'), $select:: JOIN_LEFT)
            ->columns(array('Amount' =>new Expression("sum(a.SubmitAmount)")),array('ClientName'))
            ->where("a.DeleteFlag='0' AND b.SubscriberId = '$subscriberId'")
            ->group(array('c.ClientName','b.ClientId'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->submitamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from(array('a' => 'CB_BillMaster'))
            ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),3) + '-' + ltrim(str(Year(BillDate)))"),'SubmitAmount' =>new Expression("sum(SubmitAmount)"),'CertifyAmount' =>new Expression("sum(CertifyAmount)")))
            ->where("a.DeleteFlag='0' AND SubscriberId = '$subscriberId'")
            ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),3),Year(BillDate)'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->monsubVscer = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());


        $select = $sql->select();
        $select->from(array('a' => 'CB_BillMaster'))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('ClientId'),$select:: JOIN_LEFT)
            ->join(array('c' => 'CB_ClientMaster'), 'b.ClientId=c.ClientId',array('ClientName'), $select:: JOIN_LEFT)
            ->columns(array('SubmitAmount' =>new Expression("sum(SubmitAmount)"),'CertifyAmount' =>new Expression("sum(CertifyAmount)")))
            ->where("a.DeleteFlag='0' AND b.SubscriberId = '$subscriberId'")
            ->group(array('c.ClientName','b.ClientId'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->clientsubVscer = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}

    public function billselectionAction(){

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

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $data = 'N';
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'number' );
                switch($RType) {
                    case 'billno':
                        $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                        $select = $sql->select();
                        $select->from( 'CB_BillMaster' )
                            ->columns( array( 'BillId' ) )
                            ->where( "BillNo='$PostDataStr' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if (sizeof($results) !=0 )
                            $data ='Y';
                        break;
					case 'billAddchk':
						$Bill_Id = $this->bsf->isNullCheck( $postData[ 'Bill_Id' ], 'number' );
						$WorkOrderId = $this->bsf->isNullCheck( $postData[ 'WorkOrderId' ], 'number' );
						$BillType = $this->bsf->isNullCheck( $postData[ 'BillType' ], 'string' );$select = $sql->select();

                        // check for bill format in workorder
                        $select = $sql->select();
                        $select->from( 'CB_BillFormatTrans' )
                            ->columns( array( 'BillFormatTransId' ) )
                            ->where( "WorkOrderId='$WorkOrderId'" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if (sizeof($billformats) < 1 ) {
                            $response->setStatusCode(403);
                            $response->setContent("No bill format(s) found in selected workorder!");
                            return $response;
                        }

                        $select = $sql->select();
                        $select->from( 'CB_BillMaster' )
                            ->columns( array( 'BillId' ) )
                            ->where( "WORegisterId='$WorkOrderId' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );

						if($BillType=="S"){
							$select->where( " IsSubmittedBill<>1 " );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if (sizeof($results) !=0 )
                                $data ='Y';
						} else {
							$select->where( "BillId='$Bill_Id' AND IsSubmittedBill<>1" );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if (sizeof($results) !=0 ){
                                $data ='S';//submit bill not approve
                            } else {
                                $select = $sql->select();
                                $select->from( 'CB_BillMaster' )
                                    ->columns( array( 'BillId' ) )
                                    ->where( "WORegisterId='$WorkOrderId' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );
                                $select->where( "BillId<'$Bill_Id' AND IsCertifiedBill<>1" );
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if (sizeof($results) !=0 )
                                    $data ='C';//Prev bill Certify not approve
                            }
                        }
                        break;
                    case 'getWONo':
                        $select = $sql->select();
                        $select->from( 'CB_WORegister' )
                            ->columns( array( 'data' => 'WorkOrderId', 'value' => 'WONo', 'WODate' => new Expression("FORMAT(WODate, 'dd-MM-yyyy')"), 'StartDate' => new Expression("FORMAT(StartDate, 'dd-MM-yyyy')") ))
                            ->where( "ProjectId='$PostDataStr' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $data = json_encode($results);
                        break;
                    case 'getBillNo':
                        $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                        $select = $sql->select();
                        $select->from( 'CB_BillMaster' )
                            ->columns( array( 'data' => 'BillId', 'value' => 'BillNo', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')") ) )
                            ->where( "WORegisterId='$PostDataStr' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $data = json_encode($results);
                        break;
                }
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ( $request->isPost() ) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                $postData = $request->getPost();
                try {
                    $BillId = $this->bsf->isNullCheck($postData['BillId'],'number');
					$BillType = $this->bsf->isNullCheck($postData['BillType'],'string');
					$PWorkOrderId = $this->bsf->isNullCheck($postData['PWorkOrderId'],'string');

					// New Bill
					$MBillNo = $this->bsf->isNullCheck($postData['MBillNo'],'string');
					$MBillDate = $this->bsf->isNullCheck($postData['MBillDate'],'date');
					$BillEntryType = $this->bsf->isNullCheck($postData['MBillEntryType'],'string');
					$MFromDate = $this->bsf->isNullCheck($postData['MFromDate'],'date');
					$MToDate = $this->bsf->isNullCheck($postData['MToDate'],'date');
                    $Date = date('Y-m-d');
					
					// check for bill trans type
					if($BillType == 'S') {
						$IsSubmittedBill = 1;

						$SubmittedDate = date('Y-m-d', strtotime($Date));
						$SubmittedRemarks = '';

						$IsCertifiedBill = 0;
						$CertifiedDate = null;
						$CertifiedRemarks = '';
					} else {
						$IsSubmittedBill = 0;

						$SubmittedDate = null;
						$SubmittedRemarks = '';

						$IsCertifiedBill = 1;

						$CertifiedDate = date('Y-m-d', strtotime($Date));
						$CertifiedRemarks = '';
					}
					if($BillId==0){
						$insert = $sql->insert();
						$insert->into( 'CB_BillMaster' );
						$insert->Values( array( 'BillNo' => $MBillNo, 'BillDate' => date('Y-m-d', strtotime($MBillDate))
										 , 'WORegisterId' => $PWorkOrderId, 'FromDate' => date('Y-m-d', strtotime($MFromDate)), 'ToDate' => date('Y-m-d', strtotime($MToDate))
										 , 'BillType' => $BillEntryType
										 , 'Submitted' => $IsSubmittedBill, 'Certified' => $IsCertifiedBill,'SubmittedRemarks' => $SubmittedRemarks
										 , 'SubmittedDate' => $SubmittedDate, 'CertifiedDate' => $CertifiedDate, 'CertifiedRemarks' => $CertifiedRemarks
                                         , 'SubscriberId' => $subscriberId) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$BillId = $dbAdapter->getDriver()->getLastGeneratedValue();
						//Submit Adjustment
						if($IsSubmittedBill == 1){
							$this->UpdateBillCumulativedet($BillId, $PWorkOrderId, $BillEntryType, $dbAdapter);						
							$this->GetBilldet($BillId, $PWorkOrderId, $BillEntryType, $dbAdapter);
							$this->LoadSubmit_Certify_Billdet($BillId, $PWorkOrderId, $dbAdapter);
						}
						CommonHelper::insertCBLog('Client-Bill-Add', $BillId, $MBillNo, $dbAdapter);
					} else {
						$update = $sql->update();
						$update->table('CB_BillMaster');
						$update->set( array( 'Certified' => $IsCertifiedBill ) );
						$update->where(array('BillId' => $BillId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //billformat refresh Start
                        $subselect = $sql->select();
                        $subselect->from( array( 'a' => 'CB_BillAbstract' ) )
                            ->columns(array('BillFormatId'))
                            ->where( "a.BillId=$BillId");

                        $select2 = $sql->select();
                        $select2->from( array( 'a' => 'CB_BillFormatTrans' ) )
                            ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array('BillFormatId'), $select2::JOIN_LEFT )
                            ->columns(array('Formula'))
                            ->where( "a.WorkOrderId=$PWorkOrderId")
                            ->where->notIn('a.BillFormatId',$subselect);

                        $statement = $sql->getSqlStringForSqlObject( $select2 );
                        $billsNonAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsNonAbstracts as $billsNonAbstract) {
                            $BillFormatId = $billsNonAbstract[ 'BillFormatId' ];
                            $Formula = $billsNonAbstract[ 'Formula' ];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillAbstract' );
                            $insert->Values( array( 'BillId' => $BillId, 'BillFormatId' => $BillFormatId, 'Formula' => $Formula ) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                        //billformat refresh End

                        //Certify Adjustment
						if($IsCertifiedBill == 1){
							$this->UpdateBillCumulativedet($BillId, $PWorkOrderId, $BillEntryType, $dbAdapter);
							$this->GetSubmittedtoCertifyBilldet($BillId ,$PWorkOrderId, $BillEntryType, $dbAdapter);
						}
						CommonHelper::insertCBLog('Client-Bill-Edit', $BillId, $MBillNo, $dbAdapter);
					}
					
                    $connection->commit();
					if($BillId != 0){
						$this->redirect()->toRoute('cb/default', array('controller' => 'clientbilling', 'action' => 'index', 'id' => $BillId, 'mode' => 'edit', 'type' => $BillType));
					}
                } catch ( PDOException $e ) {
                    $connection->rollback();
                }
            } else {
                $editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
                $mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );
                $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

                $select = $sql->select();
                $select->from( array('a' => 'CB_BillMaster' ))
                    ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(), $select::JOIN_LEFT)
                    ->columns( array( 'BillId') )
                    ->where(array('a.DeleteFlag' => '0', 'b.SubscriberId' => $subscriberId));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'CB_BillMaster' ))
                    ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(), $select::JOIN_LEFT)
                    ->columns( array( 'BillId') )
                    ->where(array('a.DeleteFlag' => '0', 'b.SubscriberId' => $subscriberId, 'IsCertifiedBill' => '1' , 'IsSubmittedBill' => '1'));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $cbills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $session_pref = new Container('subscriber_pref');
                // if all bills are certified, msg will be displayed
                if(count($cbills) >= $session_pref->NoOfBillCount) {
                    $this->_view->showPlanUpgradeMsg = true;
                }

                // if bills limit is completed, but bills not certified
                if(count($bills) >= $session_pref->NoOfBillCount) {
                    $this->_view->allowBillEntry = false;
                    $this->_view->NoOfBillCount = $session_pref->NoOfBillCount;
                }

                if ( $editid != 0 ) {
                    // Bill Info
                    $select = $sql->select();
                    $select->from( array( 'a' => "CB_BillMaster" ) )
                        ->join( array( 'b' => 'CB_WORegister' ), 'a.WORegisterId=b.WorkOrderId', array( 'WONo', 'WODate' => new Expression( "FORMAT(b.WODate, 'dd-MM-yyyy')" ), 'WorkOrderId' ), $select:: JOIN_LEFT )
                        ->join( array( 'c' => 'CB_ProjectMaster' ), 'b.ProjectId=c.ProjectId', array( 'ProjectId', 'ProjectName' ), $select:: JOIN_LEFT )
                        ->columns( array( 'BillNo', 'BillType', 'BillDate' => new Expression( "FORMAT(a.BillDate, 'dd-MM-yyyy')" ), 'Submitted', 'Certified' ), array( 'WONo', 'WODate', 'WorkOrderId' ), array( 'ProjectId', 'ProjectName' ) )
                        ->where( "a.BillId=$editid" );
                    $statement = $statement = $sql->getSqlStringForSqlObject( $select );
                    $billinfo = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if ( $type == "C" && $billinfo[ 'Certified' ] == 1 ) {

                    }
                    else if ( $type == "S" && $billinfo[ 'Submitted' ] == 1 ) {

                    }
                    else {
                        $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
                    }

                    $this->_view->billinfo = $billinfo;
                }

                // Projects
                $select = $sql->select();
                $select->from( 'CB_ProjectMaster' )
                    ->columns( array( 'data' => 'ProjectId', 'value' => 'ProjectName' ) )
                    ->where( "DeleteFlag=0 AND SubscriberId=$subscriberId" );

                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->projects = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $this->_view->billid = $editid;
                $this->_view->mode = $mode;
                $this->_view->type = $type;

                // csrf Key
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
            return $this->_view;
        }
    }


    public function deletebillAction(){
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
                $sql = new Sql($dbAdapter);
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $BillId = $this->bsf->isNullCheck($this->params()->fromPost('BillId'), 'number');
					$BillType = $this->bsf->isNullCheck($this->params()->fromPost('BillType'), 'string');
					
                    $WORegisterId = $this->params()->fromPost('WORegisterId');
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from( 'CB_BillMaster' )
                        ->columns( array( 'BillNo', 'IsSubmittedBill', 'IsCertifiedBill' ) )
                        ->where( array( "BillId" => $BillId ) );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $Bill = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    //check for bill certificated and submitted
                    if ( !$Bill ) {
                        $response->setStatusCode( '403' );
                        $response->setContent( 'Not able to delete this bill!' );
                        return $response;
                    }

                    if ( $BillType == 'C' && $Bill[ 'IsCertifiedBill' ] == '1' ) {
                        $response->setStatusCode( '403' );
                        $response->setContent( 'Not able to delete this bill, since certify bill is approved!' );
                        return $response;
                    }
                    elseif ( $BillType == 'S' && $Bill[ 'IsSubmittedBill' ] == '1' ) {
                        $response->setStatusCode( '403' );
                        $response->setContent( 'Not able to delete this bill, since submit bill is approved!' );
                        return $response;
                    }

                    switch($Type) {
                        case 'check':
                            // check for receipt
                            $select = $sql->select();
                            $select->from( array( 'a' => 'CB_ReceiptAdustment' ) )
                                ->join( array( 'b' => 'CB_ReceiptRegister' ), 'a.ReceiptId=b.ReceiptId', array(), $select::JOIN_INNER )
                                ->columns( array( 'ReceiptId' ) )
                                ->where( "a.BillId=$BillId AND a.Amount>0 AND b.DeleteFlag=0 AND b.WORegisterId=$WORegisterId" );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $receipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if ( count( $receipts ) ) {
                                $response->setStatusCode( '403' );
                                $response->setContent( 'Not able to delete this bill, since there were receipts entries!' );
                                return $response;
                            }

                            $response->setStatusCode('200');
                            $status = 'Not used';
                            break;
                        case 'update':
                            $Remarks =  $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');

                            $select = $sql->select();
                            $select->from('CB_BillMaster')
                                ->columns(array('BillNo'))
                                ->where(array("BillId" => $BillId));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $billNo =$bills->BillNo;

                            if($Bill['IsSubmittedBill'] == '0' && $Bill['IsCertifiedBill'] == '0') {
                                $update = $sql->update();
                                $update->table('CB_BillMaster')
                                    ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                    ->where(array('BillId' => $BillId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } elseif ($BillType == 'C' && $Bill['IsCertifiedBill'] == '0') {
                                $update = $sql->update();
                                $update->table('CB_BillMaster')
                                    ->set(array('Certified' => '0', 'CertifyAmount' => '0'))
                                    ->where(array('BillId' => $BillId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            CommonHelper::insertCBLog('Client-Bill-Delete',$BillId,$billNo,$dbAdapter);
                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function checkvendorfoundAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $vendorName = $this->bsf->isNullCheck($this->params()->fromPost('VendorName'), 'string');
                $select = $sql->select();
                $select->from('CB_VendorMaster')
                    ->columns( array( 'VendorId'))
                    ->where( "VendorName='$vendorName' AND DeleteFlag=0 AND SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

    public function checknonagtitemAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $Spec = $this->bsf->isNullCheck($this->params()->fromPost('Spec'), 'string');
                $select = $sql->select();
                $select->from('CB_NonAgtItemMaster')
                    ->columns( array( 'NonBOQId'))
                    ->where( "Specification='$Spec' AND DeleteFlag=0 AND SubscriberId='$subscriberId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

    public function getexceltemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $TemplateId = $this->bsf->isNullCheck($this->params()->fromPost('TemplateId'), 'number');
                $select = $sql->select();
                $select->from('CB_TemplateMaster')
                    ->columns( array('Description','CellName', 'SelectedColumns'))
                    ->where( "TemplateId='$TemplateId' AND SubscriberId='$subscriberId'" );
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($results)
                    return $this->getResponse()->setContent(json_encode($results));

               return $this->getResponse()->setStatus(201)->setContent('Not Found');
            }
        }
    }
    /*
     * Rebuild Func
     */
	function LoadprevbillAbstactdet($BillId, $WORegisterId, $submitType, $dbAdapter)
    {
		$sql = new Sql($dbAdapter);
		
		/*Select BillId,BillTransId,OrderNo,BillType,Mobilization,Material from BillTrans 
		Where CostCentreId = " + lCostCentreId + " and BillId=" + argBillId + " Order by BillId*/
		$select = $sql->select();
		$select->from('CB_BillMaster')
			->columns( array( 'BillId','OrderNo','BillType') )
			->where(array('DeleteFlag'=>'0', "BillId" => $BillId, "WORegisterId" => $WORegisterId));
		$select->order('BillId');
		$statement = $sql->getSqlStringForSqlObject( $select );
		$bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		foreach($bills as $bill) {
			 /*Select BillAbsId,BillId,BillFormatId From CB_BillAbstract " +
				   "Where WORegisterId in ( " + argCostID + " ) and " +
				   "BillId=" + argBillId + " Order by BillId";*/
			$billType= $bill['BillType'];
			if($billType=="R" || $billType=="F" || $billType=="S" ){
				$billType = array('R', 'S', 'F');
			} else {
				$billType = array($bill['BillType']);
			}
			
			$select = $sql->select();
			$select->from('CB_BillAbstract')
				->columns( array( 'BillAbsId','BillId','BillFormatId') )
				->where(array("BillId" => $BillId));
			$select->order('BillId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
				if($submitType=="S") //Submit
				{
					foreach($billAbstracts as $billAbstract) {
					/*Update BillAbstract Set CumulativeValue=0 " +
									"Where BillTransId = " + lTransId + "*/
					$billAbsId= $billAbstract['BillAbsId'];
					$billFormatId= $billAbstract['BillFormatId'];

					$update = $sql->update();
					$update->table('CB_BillAbstract')
						->set(array('CumAmount' => '0','PrevAmount' => '0'))
						->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					 /*sSql = "Select BillTransId,PrevBillTransId,BillType from BillCumulativeTrans 
					 Where billTransid in (select billTransid from Billtrans where BillId=" + lBillId + " 
					 and CostcentreId=" + lCostCentreId + " and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")";*/
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
								
					$select = $sql->select();
					$select->from('CB_BillCumulativeTrans')
						->columns( array( 'BillId','PrevBillId','BillType') )
						->where->expression('BillId IN ?', array($subQuery));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					   
					foreach($billCums as $billCum) {
						$prevBillId= $billCum['PrevBillId'];
						$cumType= $billCum['BillType'];
						
						$subQuery = $sql->select();
						$subQuery->from("CB_BillMaster")
							->columns(array('BillId'))
							->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
						
						$select = $sql->select();
						$select->from('CB_BillAbstract')
							->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
							->where->expression('BillId IN ?', array($subQuery));
						$select->where(array( "BillFormatId" => $billFormatId));
						$statement = $sql->getSqlStringForSqlObject( $select );
						$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						$billAmount=0;
						$billCerAmount=0;
						foreach($billCumAMounts as $billCumAMount) {
							$billAmount=$billCumAMount['Amount'];
							$billCerAmount=$billCumAMount['CerAmount'];	
						}
						
						$update = $sql->update();
						if($cumType=="C"){
							/*sSql = "Update BillAbstract Set CumulativeValue=CumulativeValue+(Select isnull(Sum(CurrentValue),0) from BillAbstract " +
								 "Where TypeID = " + lTypeId + " and CostCentreId = " + lCostCentreId + " and BillID in " +
								 "(Select BillID from BillTrans where BillTransId = " + iPrevBillTraId + " and CostCentreId= " + lCostCentreId + " and BillType='" + sBillType + "' and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")) " +
								 "Where BillTransId = " + lTransId + " ";*/
							$update->table('CB_BillAbstract')
								->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)  ));
						} else {
							$update->table('CB_BillAbstract')
								->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount) ));
						}
						$update->where(array('BillAbsId' => $billAbsId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);							
					}
					
					/*"Update BillAbstract Set CumulativeValue=CumulativeValue+
					(Select isnull(Sum(CurrentValue),0) from BillAbstract " +
				   "Where TypeID = " + lTypeId + " and CostCentreId = " + lCostCentreId + " and BillID in " +
				   "(Select BillID from BillTrans where BillTransId = " + iCurBillTraId + " and CostCentreId= " + lCostCentreId + " and
				   BillType='" + sBillType + "' and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")) " +
				   "Where BillTransId = " + lTransId + " ";*/
				   
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
					
					$select = $sql->select();
					$select->from('CB_BillAbstract')
						->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)")) )
						->where->expression('BillId IN ?', array($subQuery));
					$select->where(array("BillFormatId" => $billFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billAmount=0;
						foreach($billCumAMounts as $billCumAMount) {
							$billAmount=$billCumAMount['Amount'];
						}
					
					$update = $sql->update();
					$update->table('CB_BillAbstract')
						->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount) ));
					$update->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
			} else { //Certify
				foreach($billAbstracts as $billAbstract) {
					$billAbsId= $billAbstract['BillAbsId'];
					$billFormatId= $billAbstract['BillFormatId'];

					$update = $sql->update();
					$update->table('CB_BillAbstract')
						->set(array('CerCumAmount' => '0','CerPrevAmount' => '0'))
						->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
					$subQuery->where(array("BillType" => $billType));
					
					$select = $sql->select();
					$select->from('CB_BillAbstract')
						->columns( array( 'Amount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
						->where->expression('BillId IN ?', array($subQuery));
					$select->where(array("BillFormatId" => $billFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billAmount=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billAmount=$billCumAMount['Amount'];
					}
					
					$update = $sql->update();
					$update->table('CB_BillAbstract')
						->set(array('CerCumAmount' => $billAmount ));
					$update->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$update = $sql->update();
					$update->table('CB_BillAbstract')
						->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount") ));
					$update->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
			}		
		}
	}
    /*
     * Rebuild Func
     */
	function Loadprevbilldet($BillId, $WORegisterId, $submitType, $dbAdapter)
    {
		//$BillId =2;
		//$WORegisterId = 1;
		//$submitType="S";
		$sql = new Sql($dbAdapter);

		$select = $sql->select();
		$select->from('CB_BillMaster')
			->columns( array( 'BillId','OrderNo','BillType') )
			->where(array('DeleteFlag'=>'0', "BillId" => $BillId, "WORegisterId" => $WORegisterId));
		$select->order('BillId');
		$statement = $sql->getSqlStringForSqlObject( $select );
		$bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		foreach($bills as $bill) {
			
			$billType= $bill['BillType'];
			if($billType=="R" || $billType=="F" || $billType=="S" ){
				$billType = array('R', 'S', 'F');
			} else {
				$billType = array($bill['BillType']);
			}
			
			$subQuery = $sql->select();
			$subQuery->from("CB_BillMaster")
				->columns(array('BillId'))
				->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
						
			$subQuery1 = $sql->select();
			$subQuery1->from("CB_BillAbstract")
				->columns(array('BillAbsId'))
				->where->expression('BillId IN ?', array($subQuery));
				
			$select = $sql->select();
			$select->from('CB_BillBOQ')
				->columns( array( 'BillBOQId','BillAbsId','BillFormatId','WOBOQId','NonBOQId','Rate','CerRate','PartRate','NonBOQId') )
				->where->expression('BillAbsId IN ?', array($subQuery1));
			$select->order('BillAbsId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			if($submitType=="S") //Submit
			{
				foreach($billIOWs as $billIOW) {
					$billBOQId = $billIOW['BillBOQId'];
					$billAbsId = $billIOW['BillAbsId'];
					$billFormatId = $billIOW['BillFormatId'];
					$wOBOQId = $billIOW['WOBOQId'];
					$nonBOQId = $billIOW['NonBOQId'];
					$rate = $billIOW['Rate'];
					$cerrate = $billIOW['CerRate'];
					
					$update = $sql->update();
					$update->table('CB_BillBOQ')
						->set(array('CumAmount' => '0','CumQty' => '0', 'PrevAmount' => '0','PrevQty' => '0'))
						->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));		
					$select = $sql->select();
					$select->from('CB_BillCumulativeTrans')
						->columns( array( 'BillId','PrevBillId','BillType') )
						->where->expression('BillId IN ?', array($subQuery));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					   
					foreach($billCums as $billCum) {
						$prevBillId= $billCum['PrevBillId'];
						$cumType= $billCum['BillType'];
						
						$subQuery = $sql->select();
						$subQuery->from("CB_BillMaster")
							->columns(array('BillId'))
							->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
						
						$select = $sql->select();
						$select->from('CB_BillAbstract')
							->columns( array( 'BillAbsId' ) )
							->where->expression('BillId IN ?', array($subQuery));
						$select->where(array( "BillFormatId" => $billFormatId));
						
						
						$select1 = $sql->select();
						$select1->from('CB_BillBOQ')
							->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'Qty' => new Expression("isnull(Sum(CurQty),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
							->where->expression('BillAbsId IN ?', array($select));
						$select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
						$statement = $sql->getSqlStringForSqlObject( $select1 );
						$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						$billAmount=0;
						$billCerAmount=0;
						$billQty=0;
						$billCerQty=0;
						foreach($billCumAMounts as $billCumAMount) {
							$billAmount=$billCumAMount['Amount'];
							$billCerAmount=$billCumAMount['CerAmount'];	
							$billQty=$billCumAMount['Qty'];
							$billCerQty=$billCumAMount['CerQty'];
						}
						
						$update = $sql->update();
						if($cumType=="C"){
							$update->table('CB_BillBOQ')
								->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)
									,'CumQty' => new Expression('CumQty +'.$billCerQty), 'PrevQty' => new Expression('PrevQty +'.$billCerQty) ));
						} else {
							$update->table('CB_BillBOQ')
								->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount)
										,'CumQty' => new Expression('CumQty +'.$billQty), 'PrevQty' => new Expression('PrevQty +'.$billQty) ));
						}
						$update->where(array('BillBOQId' => $billBOQId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);							
					}
					  
					/*$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
					*/
					$select = $sql->select();
					$select->from('CB_BillBOQ')
						->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"), 'Qty' => new Expression("isnull(Sum(CurQty),0)")) );
					$select->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillAbsId" => $billAbsId , "BillFormatId" => $billFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billAmount=0;
					$billQty=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billAmount=$billCumAMount['Amount'];
						$billQty=$billCumAMount['Qty'];
					}
					
					$update = $sql->update();
					$update->table('CB_BillBOQ')
						->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'CumQty' => new Expression('CumQty +'.$billQty) ));
					$update->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
			} else { //Certify
				foreach($billIOWs as $billIOW) {

					$billBOQId = $billIOW['BillBOQId'];
					$billAbsId = $billIOW['BillAbsId'];
					$billFormatId = $billIOW['BillFormatId'];
					$wOBOQId = $billIOW['WOBOQId'];
					$nonBOQId = $billIOW['NonBOQId'];
					$rate = $billIOW['Rate'];
					$cerrate = $billIOW['CerRate'];
					
					$update = $sql->update();
					$update->table('CB_BillBOQ')
						->set(array('CerCumAmount' => '0','CerCumQty' => '0', 'CerPrevAmount' => '0','CerPrevQty' => '0'))
						->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					//
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
					$subQuery->where(array("BillType" => $billType));
						 
					$select = $sql->select();
					$select->from('CB_BillAbstract')
						->columns( array( 'BillAbsId' ) )
						->where->expression('BillId IN ?', array($subQuery));
					$select->where(array( "BillFormatId" => $billFormatId));
					
					
					$select1 = $sql->select();
					$select1->from('CB_BillBOQ')
						->columns( array( 'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
						->where->expression('BillAbsId IN ?', array($select));
					$select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
					$statement = $sql->getSqlStringForSqlObject( $select1 );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billCerAmount=0;
					$billCerQty=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billCerAmount=$billCumAMount['CerAmount'];	
						$billCerQty=$billCumAMount['CerQty'];
					}
					
					$update = $sql->update();
					$update->table('CB_BillBOQ')
						->set(array('CerCumAmount' => $billCerAmount, 'CerCumQty' => $billCerQty ));
					$update->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$update = $sql->update();
					$update->table('CB_BillBOQ')
						->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount"), 'CerPrevQty' => new Expression("CerCumQty-CerCurQty") ));
					$update->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
			}			
		}
	}
    /*
     * Rebuild Func
     */
	function LoadSubmit_Certify_Billdet($curBillId, $WORegisterId, $dbAdapter) {
		$sql = new Sql($dbAdapter);
		
		$select = $sql->select();
		$select->from('CB_BillMaster')
			->columns( array( 'BillId','OrderNo','BillType') );
		if($curBillId <> 0){
			$select->where(array('DeleteFlag'=>'0','BillId'=> $curBillId , "WORegisterId" => $WORegisterId));
		} else {
			$select->where(array('DeleteFlag'=>'0', "WORegisterId" => $WORegisterId));
		}	
		$select->order('BillId');
		$statement = $sql->getSqlStringForSqlObject( $select );
		$bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		foreach($bills as $bill) {
			$BillId = $bill['BillId'];
			$billType = $bill['BillType'];
			if($billType=="R" || $billType=="F" || $billType=="S" ){
				$billType = array('R', 'S', 'F');
			} else {
				$billType = array($bill['BillType']);
			}
			//BillAbstract
			$select = $sql->select();
			$select->from('CB_BillAbstract')
				->columns( array( 'BillAbsId','BillId','BillFormatId') )
				->where(array("BillId" => $BillId));
			$select->order('BillId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			foreach($billAbstracts as $billAbstract) {
				$billAbsId= $billAbstract['BillAbsId'];
				$billFormatId= $billAbstract['BillFormatId'];

				$update = $sql->update();
				$update->table('CB_BillAbstract')
					->set(array('CumAmount' => '0','PrevAmount' => '0', 'CerCumAmount' => '0','CerPrevAmount' => '0'))
					->where(array('BillAbsId' => $billAbsId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				//Submit
				$subQuery = $sql->select();
				$subQuery->from("CB_BillMaster")
					->columns(array('BillId'))
					->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
							
				$select = $sql->select();
				$select->from('CB_BillCumulativeTrans')
					->columns( array( 'BillId','PrevBillId','BillType') )
					->where->expression('BillId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				   
				foreach($billCums as $billCum) {
					$prevBillId= $billCum['PrevBillId'];
					$cumType= $billCum['BillType'];
					
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
					
					$select = $sql->select();
					$select->from('CB_BillAbstract')
						->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
						->where->expression('BillId IN ?', array($subQuery));
					$select->where(array( "BillFormatId" => $billFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billAmount=0;
					$billCerAmount=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billAmount=$billCumAMount['Amount'];
						$billCerAmount=$billCumAMount['CerAmount'];	
					}
					
					$update = $sql->update();
					if($cumType=="C"){
						$update->table('CB_BillAbstract')
							->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)  ));
					} else {
						$update->table('CB_BillAbstract')
							->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount) ));
					}
					$update->where(array('BillAbsId' => $billAbsId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);							
				}
				
				$subQuery = $sql->select();
				$subQuery->from("CB_BillMaster")
					->columns(array('BillId'))
					->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
				
				$select = $sql->select();
				$select->from('CB_BillAbstract')
					->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)")) )
					->where->expression('BillId IN ?', array($subQuery));
				$select->where(array("BillFormatId" => $billFormatId));
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$billAmount=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billAmount=$billCumAMount['Amount'];
					}
				
				$update = $sql->update();
				$update->table('CB_BillAbstract')
					->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount) ));
				$update->where(array('BillAbsId' => $billAbsId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);	
				
				//Certify
				$subQuery = $sql->select();
				$subQuery->from("CB_BillMaster")
					->columns(array('BillId'))
					->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
				$subQuery->where(array("BillType" => $billType));
				
				$select = $sql->select();
				$select->from('CB_BillAbstract')
					->columns( array( 'Amount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
					->where->expression('BillId IN ?', array($subQuery));
				$select->where(array("BillFormatId" => $billFormatId));
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$billAmount=0;
				foreach($billCumAMounts as $billCumAMount) {
					$billAmount=$billCumAMount['Amount'];
				}
				
				$update = $sql->update();
				$update->table('CB_BillAbstract')
					->set(array('CerCumAmount' => $billAmount ));
				$update->where(array('BillAbsId' => $billAbsId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$update = $sql->update();
				$update->table('CB_BillAbstract')
					->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount") ));
				$update->where(array('BillAbsId' => $billAbsId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}

			//BillIOW
			$subQuery = $sql->select();
			$subQuery->from("CB_BillMaster")
				->columns(array('BillId'))
				->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
						
			$subQuery1 = $sql->select();
			$subQuery1->from("CB_BillAbstract")
				->columns(array('BillAbsId'))
				->where->expression('BillId IN ?', array($subQuery));
				
			$select = $sql->select();
			$select->from('CB_BillBOQ')
				->columns( array( 'BillBOQId','BillAbsId','BillFormatId','WOBOQId','NonBOQId','Rate','CerRate','PartRate') )
				->where->expression('BillAbsId IN ?', array($subQuery1));
			$select->order('BillAbsId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			foreach($billIOWs as $billIOW) {
				$billBOQId = $billIOW['BillBOQId'];
				$billAbsId = $billIOW['BillAbsId'];
				$billFormatId = $billIOW['BillFormatId'];
				$wOBOQId = $billIOW['WOBOQId'];
				$nonBOQId = $billIOW['NonBOQId'];
				$rate = $billIOW['Rate'];
				$cerrate = $billIOW['CerRate'];
				
				$update = $sql->update();
				$update->table('CB_BillBOQ')
					->set(array('CumAmount' => '0','CumQty' => '0', 'PrevAmount' => '0','PrevQty' => '0'
					,'CerCumAmount' => '0','CerCumQty' => '0', 'CerPrevAmount' => '0','CerPrevQty' => '0'))
					->where(array('BillBOQId' => $billBOQId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				//Submit
				$subQuery = $sql->select();
				$subQuery->from("CB_BillMaster")
					->columns(array('BillId'))
					->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));		
				$select = $sql->select();
				$select->from('CB_BillCumulativeTrans')
					->columns( array( 'BillId','PrevBillId','BillType') )
					->where->expression('BillId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				   
				foreach($billCums as $billCum) {
					$prevBillId= $billCum['PrevBillId'];
					$cumType= $billCum['BillType'];
					
					$subQuery = $sql->select();
					$subQuery->from("CB_BillMaster")
						->columns(array('BillId'))
						->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
					
					$select = $sql->select();
					$select->from('CB_BillAbstract')
						->columns( array( 'BillAbsId' ) )
						->where->expression('BillId IN ?', array($subQuery));
					$select->where(array( "BillFormatId" => $billFormatId));
					
					
					$select1 = $sql->select();
					$select1->from('CB_BillBOQ')
						->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'Qty' => new Expression("isnull(Sum(CurQty),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
						->where->expression('BillAbsId IN ?', array($select));
					$select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
					$statement = $sql->getSqlStringForSqlObject( $select1 );
					$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					$billAmount=0;
					$billCerAmount=0;
					$billQty=0;
					$billCerQty=0;
					foreach($billCumAMounts as $billCumAMount) {
						$billAmount=$billCumAMount['Amount'];
						$billCerAmount=$billCumAMount['CerAmount'];	
						$billQty=$billCumAMount['Qty'];
						$billCerQty=$billCumAMount['CerQty'];
					}
					
					$update = $sql->update();
					if($cumType=="C"){
						$update->table('CB_BillBOQ')
							->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)
								,'CumQty' => new Expression('CumQty +'.$billCerQty), 'PrevQty' => new Expression('PrevQty +'.$billCerQty) ));
					} else {
						$update->table('CB_BillBOQ')
							->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount)
									,'CumQty' => new Expression('CumQty +'.$billQty), 'PrevQty' => new Expression('PrevQty +'.$billQty) ));
					}
					$update->where(array('BillBOQId' => $billBOQId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);							
				}

				$select = $sql->select();
				$select->from('CB_BillBOQ')
					->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"), 'Qty' => new Expression("isnull(Sum(CurQty),0)")) );
				$select->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillAbsId" => $billAbsId , "BillFormatId" => $billFormatId));
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$billAmount=0;
				$billQty=0;
				foreach($billCumAMounts as $billCumAMount) {
					$billAmount=$billCumAMount['Amount'];
					$billQty=$billCumAMount['Qty'];
				}
				
				$update = $sql->update();
				$update->table('CB_BillBOQ')
					->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'CumQty' => new Expression('CumQty +'.$billQty) ));
				$update->where(array('BillBOQId' => $billBOQId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				//Certify
				$subQuery = $sql->select();
				$subQuery->from("CB_BillMaster")
					->columns(array('BillId'))
					->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
				$subQuery->where(array("BillType" => $billType));
					 
				$select = $sql->select();
				$select->from('CB_BillAbstract')
					->columns( array( 'BillAbsId' ) )
					->where->expression('BillId IN ?', array($subQuery));
				$select->where(array( "BillFormatId" => $billFormatId));
				
				
				$select1 = $sql->select();
				$select1->from('CB_BillBOQ')
					->columns( array( 'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
					->where->expression('BillAbsId IN ?', array($select));
				$select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
				$statement = $sql->getSqlStringForSqlObject( $select1 );
				$billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$billCerAmount=0;
				$billCerQty=0;
				foreach($billCumAMounts as $billCumAMount) {
					$billCerAmount=$billCumAMount['CerAmount'];	
					$billCerQty=$billCumAMount['CerQty'];
				}
				
				$update = $sql->update();
				$update->table('CB_BillBOQ')
					->set(array('CerCumAmount' => $billCerAmount, 'CerCumQty' => $billCerQty ));
				$update->where(array('BillBOQId' => $billBOQId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$update = $sql->update();
				$update->table('CB_BillBOQ')
					->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount"), 'CerPrevQty' => new Expression("CerCumQty-CerCurQty") ));
				$update->where(array('BillBOQId' => $billBOQId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
			
		}
			
			
	}
	
	public function loadprevbilldetAction(){
       if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$BillId =1012;
		$WORegisterId = 1;
		$submitType="C";
		$sql = new Sql($dbAdapter);
		
		$subQuery = $sql->select();
		$subQuery->from("CB_BillMaster")
			->columns(array('BillId' => new Expression("isnull(max(BillId),0)") ))
			->where(array("WORegisterId" => $WORegisterId));

		$select = $sql->select();
		$select->from( array('a' => 'CB_BillCumulativeTrans' ))
            ->join(array('b' => 'CB_BillMaster'), 'a.PrevBillId=B.BillId', array(), $select::JOIN_LEFT)
            ->join(array('c' => 'CB_BillAbstract'), 'b.BillId=c.BillId', array("BillFormatId"), $select::JOIN_LEFT)
            ->columns(array( 'Amount' => new Expression("isnull(Case When A.BillType='C' then c.CerCurAmount else c.CurAmount End ,0)") ))
            ->where(array('b.DeleteFlag' => '0'));
		$select->where->expression('a.BillId IN ?', array($subQuery));

		$select2 = $sql->select();
		$select2->from(array("b"=>"CB_BillMaster"))
				->columns(array( 'Amount' => new Expression("isnull(Case When b.IsCertifiedBill=1 then c.CerCurAmount else c.CurAmount End ,0)") ))
				->join(array('c' => 'CB_BillAbstract'), 'b.BillId=c.BillId', array("BillFormatId"), $select2::JOIN_INNER);
		$select2->where(array('b.DeleteFlag' => '0'));
		$select2->where->expression('b.BillId IN ?', array($subQuery));
		$select2->combine($select,'Union ALL');

		$select3 = $sql->select();
		$select3->from(array("g"=>$select2))
				->columns(array("Amount"=>new Expression("Sum(g.Amount)") ), array('*'))
				->join(array('a' => 'CB_BillFormatTrans'), 'g.BillFormatId=a.BillFormatId', array('*'), $select3::JOIN_INNER)
				->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array("FormatTypeId"), $select3::JOIN_LEFT);
		$select3->where(array('a.WorkOrderId' => $WORegisterId));
		$select3->group(new Expression('a.BillFormatId,a.RowName,a.Slno,a.TypeName,a.Description,a.Sign
		,a.Header,a.WorkOrderId,a.Formula,a.Bold,a.Italic,a.Underline,a.SortId,b.FormatTypeId'));
		$select3->order('a.BillFormatId');

		$billType=array('R', 'S', 'F');
		//Mobilization Adv Recovery (5)
		$select = $sql->select();
		$select->from( array('a' => 'CB_ReceiptRegister' ))
			->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
			->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => '1' ,'a.ReceiptAgainst' => 'M'));
			
		$select2 = $sql->select(); 
		$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
				->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
				->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
				->where(array('b.BillId' => '1', 'b.BillFormatId' => '5' , 'c.WORegisterId' => '1', 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
		$select2->combine($select,'Union ALL');
		
		$select21 = $sql->select(); 
		$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
				->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
				->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
				->where(array('b.BillId' => '0', 'b.BillFormatId' => '5' , 'c.WORegisterId' => '1', 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
		$select21->where("b.BillId<>1");
		$select21->combine($select2,'Union ALL');
		
		$select3 = $sql->select();
		$select3->from(array("g"=>$select21))
				->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount)"), "Balance"=>new Expression("Sum(CurAmount)") ),
				array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
				->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
		$select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
		$select3->order('g.ReceiptId');
		//$statement = $sql->getSqlStringForSqlObject($select3);
		//$mobilizationAdv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		//Adv Recovery (6)
		



		$select = $sql->select();
		$select->from( array('a' => 'Crm_DescriptionMaster' ))
			->columns(array( 'Id' => new Expression("a.DescriptionId"), 'Name'  => new Expression("a.DescriptionName"), 'Type' => new Expression("'D'") ));
		
		$select21 = $sql->select(); 
		$select21->from(array("a"=>"KF_StageMaster"))
				->columns(array( 'Id' => new Expression("a.StageId"), 'Name'  => new Expression("a.StageName"), 'Type' => new Expression("'S'") ));
		$select21->combine($select,'Union ALL');
		
		$select22 = $sql->select(); 
		$select22->from(array("a"=>"Crm_OtherCostMaster"))
				->columns(array( 'Id' => new Expression("a.OtherCostId"), 'Name'  => new Expression("a.OtherCostName"), 'Type' => new Expression("'O'") ));
		$select22->combine($select21,'Union ALL');
		 
		$select3 = $sql->select();
		$select3->from(array("g"=>$select22))
				->columns(array("Id","Name","Type" ));
		$select3->order('g.Name');
		
		/*$subQuery = $sql->select();
		$subQuery->from("CB_BillAbstract")
			->columns(array('BillAbsId'))
			->where(array("BillId" => $BillId));
			
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillMaterialAdvance' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' => new Expression("'C'") ));
		$select->where(array("a.BillFormatId" => 3, "a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillMaterialAdvance' );
		$insert->columns(array('BillAbsId', 'BillFormatId','MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
		$insert->Values( $select );	
		$curvBillAbsId=1012;
		$prevBillAbsId=1006;
		$BillFormatId=18;*/
				
		$statement = $sql->getSqlStringForSqlObject( $select3 );
		$billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		  
		
		//$this->LoadprevbillAbstactdet($BillId ,$WORegisterId, $submitType, $dbAdapter);
		//$this->Loadprevbilldet($BillId ,$WORegisterId, $submitType, $dbAdapter);
		//$this->LoadSubmit_Certify_Billdet(2, $WORegisterId, $dbAdapter);
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);		
		return $this->_view;
    }
	
	function UpdateBillCumulativedet($BillId, $WORegisterId, $submitType, $dbAdapter) {
		$sql = new Sql($dbAdapter);
		//Start BillCumulativeTrans
		$delete = $sql->delete();
		$delete->from('CB_BillCumulativeTrans')
			->where("BillId =$BillId");
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		if($submitType=="R" || $submitType=="F" || $submitType=="S" || $submitType==""){
			$billTypechk = array('R', 'S', 'F');
		} else {
			$billTypechk = array($submitType);
		}

		$select = $sql->select();
		$select->from( array( 'a' => 'CB_BillMaster' ) )
			->columns(array('BillId','IsSubmittedBill','IsCertifiedBill'))
			->where( "a.DeleteFlag=0 AND a.WORegisterId=$WORegisterId AND a.BillID < $BillId ");
		$select->where(array("BillType" => $billTypechk));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$billFlows = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

		foreach($billFlows as $billFlow) {
			$prevbillType="S";
			$prevbillId= $billFlow['BillId'];
			//$prevSub_billflag= $billFlow['IsSubmittedBill'];
			$prevCer_billflag= $billFlow['IsCertifiedBill'];
			if($prevCer_billflag==1){
				$prevbillType="C";
			}
		
			$insert = $sql->insert();
			$insert->into( 'CB_BillCumulativeTrans' );
			$insert->Values( array( 'BillId' => $BillId, 'PrevBillId' => $prevbillId, 'BillType' => $prevbillType ) );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		}
		//End BillCumulativeTrans
		
	}
	
	function GetBilldet($BillId, $WORegisterId, $submitType, $dbAdapter) {
		$sql = new Sql($dbAdapter);
		
		if($submitType=="R" || $submitType=="F" || $submitType=="S" ){
			$billType = array('R', 'S', 'F');
		} else {
			$billType = array($submitType);
		}
		
		//BillAbstract - Prev Bill
		$subQuery = $sql->select();
		$subQuery->from("CB_BillMaster")
			->columns(array('BillId' => new Expression("isnull(max(BillId),0)") ))
			->where(array("DeleteFlag" => '0', "WORegisterId" => $WORegisterId, "BillType" => $billType));
		$subQuery->where("BillId<>$BillId");
		$statement = $sql->getSqlStringForSqlObject($subQuery);
		$prevbillinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$PrevBillId=$prevbillinfo['BillId'];
		
		if($PrevBillId!=0){

			$select = $sql->select();
			$select->from( array('a' => 'CB_BillAbstract' ))
				->columns(array( 'BillAbsId', 'BillFormatId' ,'BillFormatTransId', 'Formula' ));
			$select->where(array("a.BillId" => $PrevBillId));
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			foreach($billsAbstracts as $billsAbstract) {
				$prevBillAbsId= $billsAbstract['BillAbsId'];
				$BillFormatId= $billsAbstract['BillFormatId'];
				$BillFormatTraId= $billsAbstract['BillFormatTransId'];
//				$Formula= $billsAbstract['Formula'];

                // To get formula from workorder
                $select = $sql->select();
                $select->from( array('a' => 'CB_BillFormatTrans' ))
                    ->columns(array( 'Formula' ));
                $select->where(array("a.WorkOrderId" => $WORegisterId, "a.BillFormatId" => $BillFormatId, "a.BillFormatTransId" => $BillFormatTraId));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billFormatTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

				$insert = $sql->insert();
				$insert->into( 'CB_BillAbstract' );
				$insert->Values( array( 'BillId' => $BillId, 'BillFormatId' => $BillFormatId, 'BillFormatTransId' => $BillFormatTraId, 'Formula' => $billFormatTrans['Formula']) );
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				$curvBillAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				if($BillFormatId==1 || $BillFormatId==2){ //BillIOW
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillBOQ' ))
						->columns(array( 'BillFormatId', 'WOBOQId', 'NonBOQId', 'SlNo', 'Spec', 'UnitId', 'Rate', 'PartRate', 'PartPercent'
						, 'FullRate', 'CerRate', 'CerPartPercent', 'CerFullRate' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsIOWs as $billsIOW) {
						$IOWBillFormatId= $billsIOW['BillFormatId'];
						$WOBOQId= $billsIOW['WOBOQId'];
						$NonBOQId= $billsIOW['NonBOQId'];
						$SlNo= $billsIOW['SlNo'];
						$Spec= $billsIOW['Spec'];
						$UnitId= $billsIOW['UnitId'];
						$Rate= $billsIOW['Rate'];
						$PartRate= $billsIOW['PartRate'];
						$PartPercent= $billsIOW['PartPercent'];
						$FullRate= $billsIOW['FullRate'];
						$CerRate= $billsIOW['CerRate'];
						$CerPartPercent= $billsIOW['CerPartPercent'];
						$CerFullRate= $billsIOW['CerFullRate'];
							
						$insert = $sql->insert();
						$insert->into( 'CB_BillBOQ' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $IOWBillFormatId, 'WOBOQId' => $WOBOQId, 'NonBOQId' => $NonBOQId
						, 'SlNo' => $SlNo, 'Spec' => $Spec, 'UnitId' => $UnitId, 'Rate' => $Rate, 'PartRate' => $PartRate, 'PartPercent' => $PartPercent, 'FullRate' => $FullRate
						, 'CerRate' => $CerRate, 'CerPartPercent' => $CerPartPercent, 'CerFullRate' => $CerFullRate) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}
				} else if($BillFormatId==3){ //MaterialAdvance
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillMaterialAdvance' ))
						->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"),'MTransId','BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId, "a.TransType" => 'S'));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsMatAdvs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsMatAdvs as $billsMatAdv) {
						$MatadvBillFormatId= $billsMatAdv['BillFormatId'];
						$MTransPrevId= $billsMatAdv['MTransId'];
						$MaterialId= $billsMatAdv['MaterialId'];
						$Qty= $billsMatAdv['Qty'];
						$Rate= $billsMatAdv['Rate'];
						$Amount= $billsMatAdv['Amount'];
						$AdvPercent= $billsMatAdv['AdvPercent'];
						$AdvAmount= $billsMatAdv['AdvAmount'];
						$PurchaseQty= $billsMatAdv['PurchaseQty'];
						$ConsumeQty= $billsMatAdv['ConsumeQty'];
						$TransType= $billsMatAdv['TransType'];
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillMaterialAdvance' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $MatadvBillFormatId, 'MaterialId' => $MaterialId, 'AdvPercent' => $AdvPercent,
						//'Qty' => $Qty , 'Rate' => $Rate, 'Amount' => $Amount, 'AdvAmount' => $AdvAmount, 'PurchaseQty' => $PurchaseQty, 'ConsumeQty' => $ConsumeQty,
						'TransType' => $TransType) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$mTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
						
						$select = $sql->select();
						$select->from( array('a' => 'CB_BillMaterialBillTrans' ))
							->columns(array( 'BillDate','BillNo','VendorId','Rate'));
						$select->where(array("a.MTransId" => $MTransPrevId));
						$statement = $sql->getSqlStringForSqlObject( $select );
						$billsMatAdvstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						foreach($billsMatAdvstrans as $billsMatAdvstran) {
							$insert = $sql->insert();
							$insert->into( 'CB_BillMaterialBillTrans' );
							$insert->Values( array( 'MTransId' => $mTransId, 'BillDate' => $billsMatAdvstran['BillDate'], 'BillNo' => $billsMatAdvstran['BillNo']
							, 'VendorId' => $billsMatAdvstran['VendorId'], 'Rate' => $billsMatAdvstran['Rate'] ) );
							$statement = $sql->getSqlStringForSqlObject( $insert );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						}
						
					}
					
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillMaterialAdvance' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillMaterialAdvance' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );*/
					
				} else if($BillFormatId==18){ //Price Escalation
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillPriceEscalation' ))
						->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType', 'RateCondition', 'ORate'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsPriceEscs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsPriceEscs as $billsPriceEsc) {
						$PrEscBillFormatId= $billsPriceEsc['BillFormatId'];
						$MaterialId= $billsPriceEsc['MaterialId'];
						$Qty= $billsPriceEsc['Qty'];
						$BaseRate= $billsPriceEsc['BaseRate'];
						$EscalationPer= $billsPriceEsc['EscalationPer'];
						$ActualRate= $billsPriceEsc['ActualRate'];
						$Amount= $billsPriceEsc['Amount'];
						$TransType= $billsPriceEsc['TransType'];
						$RateCondition= $billsPriceEsc['RateCondition'];
						$ORate= $billsPriceEsc['ORate'];
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillPriceEscalation' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $PrEscBillFormatId, 'MaterialId' => $MaterialId, 'Qty' => $Qty
						, 'BaseRate' => $BaseRate, 'EscalationPer' => $EscalationPer, 'ActualRate' => $ActualRate, 'Amount' => $Amount, 'TransType' => $TransType, 
						'RateCondition' => $RateCondition, 'ORate' => $ORate) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}*/
					
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillPriceEscalation' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'BaseRate', 'EscalationPer', 'ActualRate', 'TransType', 'RateCondition', 'ORate' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillPriceEscalation' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'BaseRate', 'EscalationPer', 'ActualRate', 'TransType', 'RateCondition', 'ORate'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
				} else if($BillFormatId==5 || $BillFormatId==6){ //MobAdvance Recovery or Advance Recovery
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillAdvanceRecovery' ))
						->columns(array( 'BillId', 'ReceiptId', 'BillFormatId', 'Amount', 'CerAmount'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsMobRecs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsMobRecs as $billsMobRec) {
						$MobRecBillId= $billsMobRec['BillId'];
						$MobRecReceiptId= $billsMobRec['ReceiptId'];
						$MobRecBillFormatId= $billsMobRec['BillFormatId'];
						$Amount= $billsMobRec['Amount'];
						$CerAmount= $billsMobRec['CerAmount'];						
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillAdvanceRecovery' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillId' => $MobRecBillId, 'ReceiptId' => $MobRecReceiptId, 'BillFormatId' => $MobRecBillFormatId
						, 'Amount' => $Amount, 'CerAmount' => $CerAmount) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}*/
					
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillAdvanceRecovery' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'BillId' =>new Expression("'$BillId'"), 'ReceiptId' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillAdvanceRecovery' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'BillId', 'ReceiptId'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
				} else if($BillFormatId==8){ //Material Recovery
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillMaterialRecovery' ))
						->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsMatRecs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsMatRecs as $billsMatRec) {
						$MatRecBillFormatId= $billsMatRec['BillFormatId'];
						$MaterialId= $billsMatRec['MaterialId'];
						$Qty= $billsMatRec['Qty'];
						$Rate= $billsMatRec['Rate'];
						$Amount= $billsMatRec['Amount'];
						$TransType= $billsMatRec['TransType'];						
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillMaterialRecovery' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $MatRecBillFormatId, 'MaterialId' => $MaterialId, 'Qty' => $Qty
						, 'Rate' => $Rate, 'Amount' => $Amount, 'TransType' => $TransType) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}*/
					
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillMaterialRecovery' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'TransType' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillMaterialRecovery' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'TransType'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
				} else if($BillFormatId==7){ //Bill Deduction
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillVendorBill' ))
						->columns(array( 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsDedus = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsDedus as $billsDedu) {
						$DeductionBillFormatId= $billsDedu['BillFormatId'];
						$BillDate= date('Y-m-d', strtotime($billsDedu['BillDate']));
						$BillNo= $billsDedu['BillNo'];
						$VendorId= $billsDedu['VendorId'];
						$Amount= $billsDedu['Amount'];
						$TransType= $billsDedu['TransType'];						
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillVendorBill' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $DeductionBillFormatId, 'BillDate' => $BillDate, 'BillNo' => $BillNo
						, 'VendorId' => $VendorId, 'Amount' => $Amount, 'TransType' => $TransType) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}
					*/
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillVendorBill' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillVendorBill' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				} else if($BillFormatId==19){ //Free Supply Material
					/*$select = $sql->select();
					$select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
						->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
					$statement = $sql->getSqlStringForSqlObject( $select );
					$billsFreeSups = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
					foreach($billsFreeSups as $billsFreeSup) {
						$FreesupplyBillFormatId= $billsFreeSup['BillFormatId'];
						$MaterialId= $billsFreeSup['MaterialId'];
						$Qty= $billsFreeSup['Qty'];
						$Rate= $billsFreeSup['Rate'];
						$Amount= $billsFreeSup['Amount'];
						$TransType= $billsFreeSup['TransType'];						
						
						$insert = $sql->insert();
						$insert->into( 'CB_BillFreeSupplyMaterial' );
						$insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $FreesupplyBillFormatId, 'MaterialId' => $MaterialId
						, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount, 'TransType' => $TransType) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}*/
					
					$select = $sql->select();
					$select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
								->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'TransType' ));
					$select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
							
					$insert = $sql->insert();
					$insert->into( 'CB_BillFreeSupplyMaterial' );
					$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'TransType'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				}	
			}
		} else {
		    //Insert New BillAbstract
			$select = $sql->select();
			$select->from( array('a' => 'CB_BillFormatTrans' ))
				->columns(array( 'BillId' =>new Expression("'$BillId'"), 'BillFormatId'=>new Expression("isnull(a.BillFormatId,0)"), 'BillFormatTransId', 'Formula'))
				->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT);
			$select->where(array("a.WorkOrderId" => $WORegisterId));
			//$select->order('a.SortId');
			
			$insert = $sql->insert();
			$insert->into( 'CB_BillAbstract' );
			$insert->columns(array('BillId', 'BillFormatId', 'BillFormatTransId', 'Formula'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		}
	}

	function GetSubmittedtoCertifyBilldet($BillId, $WORegisterId, $submitType, $dbAdapter) {
		$sql = new Sql($dbAdapter);

		//BillMaster
		$update = $sql->update();
		$update->table('CB_BillMaster')
			->set(array('CertifyAmount' => new Expression('SubmitAmount') ));
		$update->where(array('BillId' => $BillId));
		$statement = $sql->getSqlStringForSqlObject($update);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		//BillAbstract
		$update = $sql->update();
		$update->table('CB_BillAbstract')
			->set(array('CerCurAmount' => new Expression('CurAmount') ));
		$update->where(array('BillId' => $BillId));
		$statement = $sql->getSqlStringForSqlObject($update);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
		//Bill IOW
		$subQuery = $sql->select();
		$subQuery->from("CB_BillAbstract")
			->columns(array('BillAbsId'))
			->where(array("BillId" => $BillId));
			
		$update = $sql->update();
		$update->table('CB_BillBOQ')
			->set(array('CerCurQty' => new Expression('CurQty'), 'CerCurAmount' => new Expression('CurAmount')
					, 'CerRate' => new Expression('Rate'), 'CerPartPercent' => new Expression('PartPercent'), 'CerFullRate' => new Expression('FullRate') ));
		$update->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($update);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		//MaterialAdvance Bulk Insert
		$delete = $sql->delete();
		$delete->from('CB_BillMaterialAdvance')
			->where(array("BillFormatId" => 3, "TransType" =>'C'));
		$delete->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillMaterialAdvance' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' => new Expression("'C'") ));
		$select->where(array("a.BillFormatId" => 3, "a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillMaterialAdvance' );
		$insert->columns(array('BillAbsId', 'BillFormatId','MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
		$insert->Values( $select );
		$statement = $sql->getSqlStringForSqlObject( $insert );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//Price Esclation Bulk Insert
		$delete = $sql->delete();
		$delete->from('CB_BillPriceEscalation')
			->where(array("TransType" =>'C'));
		$delete->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillPriceEscalation' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType' => new Expression("'C'"), 'RateCondition', 'ORate' ));
		$select->where(array("a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillPriceEscalation' );
		$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType', 'RateCondition', 'ORate'));
		$insert->Values( $select );
		$statement = $sql->getSqlStringForSqlObject( $insert );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//MobAdvance Recovery or Advance Recovery
		$update = $sql->update();
		$update->table('CB_BillAdvanceRecovery')
			->set(array('CerAmount' => new Expression('Amount') ));
		$update->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject( $update );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//Material Recovery Bulk Insert
		$delete = $sql->delete();
		$delete->from('CB_BillMaterialRecovery')
			->where(array("TransType" =>'C'));
		$delete->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillMaterialRecovery' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType' => new Expression("'C'") ));
		$select->where(array("a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillMaterialRecovery' );
		$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
		$insert->Values( $select );
		$statement = $sql->getSqlStringForSqlObject( $insert );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//Bill Deduction Bulk Insert
		$delete = $sql->delete();
		$delete->from('CB_BillVendorBill')
			->where(array("TransType" =>'C'));
		$delete->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillVendorBill' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType' => new Expression("'C'") ));
		$select->where(array("a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillVendorBill' );
		$insert->columns(array('BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
		$insert->Values( $select );
		$statement = $sql->getSqlStringForSqlObject( $insert );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//Free Supply Material Bulk Insert
		$delete = $sql->delete();
		$delete->from('CB_BillFreeSupplyMaterial')
			->where(array("TransType" =>'C'));
		$delete->where->expression('BillAbsId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($delete);
		$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		$select = $sql->select();
		$select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
					->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType' => new Expression("'C'") ));
		$select->where(array("a.TransType" =>'S'));
		$select->where->expression('a.BillAbsId IN ?', array($subQuery));
				
		$insert = $sql->insert();
		$insert->into( 'CB_BillFreeSupplyMaterial' );
		$insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
		$insert->Values( $select );
		$statement = $sql->getSqlStringForSqlObject( $insert );
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		$this->LoadprevbillAbstactdet($BillId ,$WORegisterId, "C", $dbAdapter);
		$this->Loadprevbilldet($BillId ,$WORegisterId, "C", $dbAdapter);				
	}
    
	public function reportabstractAction(){
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
	
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 '<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.
			'<style type="text/css">'.
			'.drsElement { position: absolute; border: 1px solid #333; }'.
			'.drsMoveHandle { height: 20px; background-color: #CCC; border-bottom: 1px solid #666; cursor: move;}'.
			'.dragresize { position: absolute; width: 5px; height: 5px; font-size: 1px; background: #EEE; border: 1px solid #333; }'.
			'.dragresize-tl {top: -8px; left: -8px; cursor: nw-resize; }'.
			'.dragresize-tm { top: -8px; left: 50%; margin-left: -4px; cursor: n-resize;}'.
			'.dragresize-tr { top: -8px; right: -8px; cursor: ne-resize;}'.
			'.dragresize-ml {top: 50%;margin-top: -4px;left: -8px;cursor: w-resize;}'.
			'.dragresize-mr {top: 50%;margin-top: -4px;right: -8px;cursor: e-resize;}'.
			'.dragresize-bl {bottom: -8px;left: -8px;cursor: sw-resize;}'.
			'.dragresize-bm {bottom: -8px;left: 50%;margin-left: -4px;cursor: s-resize;}'.
			'.dragresize-br {bottom: -8px;right: -8px;cursor: se-resize;}'.
			'.text-bold, .text-bold *{ font-weight: bold !important; }'.
			'.text-italic, .text-italic *{ font-style: italic !important;}'.
			'.text-underline, .text-underline *{ text-decoration: underline !important;}'.
			'.style-left, .style-left * {text-align: left;}'.
			'.style-center, .style-center * {text-align: center;}'.
			'.style-right, .style-right * {text-align: right;}'.
			'.style-justify, .style-justify * {text-align: justify;}'.
			'#styleOptions{ display:  none; }'.
			'</style>'.

			'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.
			'</head>'.
			'<body>'. $content. '</body>';
			'</html>';

			$dompdf = new DOMPDF();		
			//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
			$dompdf->load_html($pdfhtml);
			//$dompdf->set_paper('letter', 'landscape');
			$dompdf->set_paper("A4");
			//$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
			$canvas = $dompdf->get_canvas();
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");
			
			/*try
			{   
				require_once("/vendor/pdfcrowd.php");
				// create an API client instance
				$client = new \Pdfcrowd("Irfan123", "f543051812bb52ad506b478ed7e083f5");

				// convert a web page and store the generated PDF into a $pdf variable
				//$pdf = $client->convertURI('http://troolee.github.io/gridstack.js/demo/knockout.html');
				//C:\Users\Admin\Downloads\dragresize\dragresize
				$pdf = $client->convertFile("E:/xampp/htdocs/bsf_v1.0/public/testdemo.html");
				//$pdf = $client->convertHtml("$pdfhtml");
				// set HTTP response headers
				header("Content-Type: application/pdf");
				header("Cache-Control: max-age=0");
				header("Accept-Ranges: none");
				header("Content-Disposition: attachment; filename=\"google_com.pdf\"");

				// send the generated PDF 
				echo $pdf;
			}
			catch(PdfcrowdException $why)
			{
				echo "Pdfcrowd Error: " . $why;
			}*/

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/

			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billinfo = $billinfo;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			$this->_view->typeEntry = $typeEntry;
			
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount','PrevAmount','CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount'=> new Expression("a.CerCumAmount")
					,'PrevAmount'=> new Expression("a.CerPrevAmount"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function reportabstractiowAction(){
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
	
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
			'<style type="text/css">'.
			'.drsElement { position: absolute; border: 1px solid #333; }'.
			'.drsMoveHandle { height: 20px; background-color: #CCC; border-bottom: 1px solid #666; cursor: move;}'.
			'.dragresize { position: absolute; width: 5px; height: 5px; font-size: 1px; background: #EEE; border: 1px solid #333; }'.
			'.dragresize-tl {top: -8px; left: -8px; cursor: nw-resize; }'.
			'.dragresize-tm { top: -8px; left: 50%; margin-left: -4px; cursor: n-resize;}'.
			'.dragresize-tr { top: -8px; right: -8px; cursor: ne-resize;}'.
			'.dragresize-ml {top: 50%;margin-top: -4px;left: -8px;cursor: w-resize;}'.
			'.dragresize-mr {top: 50%;margin-top: -4px;right: -8px;cursor: e-resize;}'.
			'.dragresize-bl {bottom: -8px;left: -8px;cursor: sw-resize;}'.
			'.dragresize-bm {bottom: -8px;left: 50%;margin-left: -4px;cursor: s-resize;}'.
			'.dragresize-br {bottom: -8px;right: -8px;cursor: se-resize;}'.
			'.text-bold, .text-bold *{ font-weight: bold !important; }'.
			'.text-italic, .text-italic *{ font-style: italic !important;}'.
			'.text-underline, .text-underline *{ text-decoration: underline !important;}'.
			'.style-left, .style-left * {text-align: left;}'.
			'.style-center, .style-center * {text-align: center;}'.
			'.style-right, .style-right * {text-align: right;}'.
			'.style-justify, .style-justify * {text-align: justify;}'.
			'#styleOptions{ display:  none; }'.
			'</style>'.

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
			'</html>';

			$dompdf = new DOMPDF();		
			//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
			$dompdf->load_html($pdfhtml);
			//$dompdf->set_paper('letter', 'landscape');
			$dompdf->set_paper("A4");
			//$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
			$canvas = $dompdf->get_canvas();
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
				
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")
				),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billinfo = $billinfo;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			$this->_view->typeEntry = $typeEntry;
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount','PrevAmount','CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount'=> new Expression("a.CerCumAmount")
					,'PrevAmount'=> new Expression("a.CerPrevAmount"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
			
			$select3 = $sql->select();
			$select1 = $sql->select();
			if($type == "S"){
				$select1->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo','Specification'),array('BillId') );
				$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
				
				$select2 = $sql->select();
				$select2->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
				$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
				
				$select2->combine($select1,'Union ALL');
							
				$select3 = $sql->select();
				$select3->from(array("g"=>$select2))
						->columns(array("BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));							
				
			} else {
				$select1->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
					'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo','Specification'),array('BillId') );
				$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
				
				$select2 = $sql->select();
				$select2->from(array('a' => "CB_BillBOQ"))
				->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
					'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					, array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
				$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
				
				$select2->combine($select1,'Union ALL');
							
				$select3 = $sql->select();
				$select3->from(array("g"=>$select2))
						->columns(array("BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));
			}
			$statement = $sql->getSqlStringForSqlObject( $select3 );
			$billsAbstIOws = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbstIOws = $billsAbstIOws;
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function reportabstractoverallAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
			'<style type="text/css">'.
			'.drsElement { position: absolute; border: 1px solid #333; }'.
			'.drsMoveHandle { height: 20px; background-color: #CCC; border-bottom: 1px solid #666; cursor: move;}'.
			'.dragresize { position: absolute; width: 5px; height: 5px; font-size: 1px; background: #EEE; border: 1px solid #333; }'.
			'.dragresize-tl {top: -8px; left: -8px; cursor: nw-resize; }'.
			'.dragresize-tm { top: -8px; left: 50%; margin-left: -4px; cursor: n-resize;}'.
			'.dragresize-tr { top: -8px; right: -8px; cursor: ne-resize;}'.
			'.dragresize-ml {top: 50%;margin-top: -4px;left: -8px;cursor: w-resize;}'.
			'.dragresize-mr {top: 50%;margin-top: -4px;right: -8px;cursor: e-resize;}'.
			'.dragresize-bl {bottom: -8px;left: -8px;cursor: sw-resize;}'.
			'.dragresize-bm {bottom: -8px;left: 50%;margin-left: -4px;cursor: s-resize;}'.
			'.dragresize-br {bottom: -8px;right: -8px;cursor: se-resize;}'.
			'.text-bold, .text-bold *{ font-weight: bold !important; }'.
			'.text-italic, .text-italic *{ font-style: italic !important;}'.
			'.text-underline, .text-underline *{ text-decoration: underline !important;}'.
			'.style-left, .style-left * {text-align: left;}'.
			'.style-center, .style-center * {text-align: center;}'.
			'.style-right, .style-right * {text-align: right;}'.
			'.style-justify, .style-justify * {text-align: justify;}'.
			'#styleOptions{ display:  none; }'.
			'</style>'.

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content . '</body>';
			'</html>';

			$dompdf = new DOMPDF();		
			//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
			$dompdf->load_html($pdfhtml);
			//$dompdf->set_paper('letter', 'landscape');
			$dompdf->set_paper("A4");
			//$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
			$canvas = $dompdf->get_canvas();
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));
						
			/*// load the html content
			$dompdf->load_html($pdfhtml);
			$dompdf->render();
			$canvas = $dompdf->get_canvas();
			//$font = Font_Metrics::get_font("helvetica", "bold");
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));
			$dompdf->stream("sample.pdf",array("Attachment"=>0));*/
			$dompdf->stream("Report.pdf");	

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billHeader = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billHeader = $billHeader;
			
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			$this->_view->typeEntry = $typeEntry;
			 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);

				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header','Bold' ,'Italic' ,'Underline'))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId")
					->order('a.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				foreach($BillFormat as &$Format) {
					$billFormatId= $Format['BillFormatId'];
					$billAbsId= $Format['BillAbsId'];
					switch($billFormatId) {
						case '1': // Agreement
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '2': // Non-Agreement
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_NonAgtItemMaster' ), 'a.NonBOQId=b.NonBOQId', array( 'SlNo','Specification','NonBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement','CellName', 'SelectedColumns'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '3': //Material Advance
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillMaterialAdvance' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('PBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '5': // MobAdvRecovery
							// Advance Recovery (Receipt & Material Advance)
							$select = $sql->select();
							$select->from( array('a' => 'CB_ReceiptRegister' ))
								->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'M'));
								
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId");
							$select21->combine($select2,'Union ALL');
							
							$select3 = $sql->select();
							$select3->from(array("g"=>$select21))
									->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
									array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
									->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
							$select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
							$select3->order('g.ReceiptId');
							$statement = $sql->getSqlStringForSqlObject($select3);
							$Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '6': // Advance Recovery                                   
							//Advance Recovery Receipt
							$select = $sql->select();
							$select->from( array('a' => 'CB_ReceiptRegister' ))
								->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'A'));
								
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.ReceiptId<>0");
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId AND b.ReceiptId<>0");
							$select21->combine($select2,'Union ALL');
							
							$select3 = $sql->select();
							$select3->from(array("g"=>$select21))
									->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
									array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
									->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
							$select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
							$select3->order('g.ReceiptId');
							$statement = $sql->getSqlStringForSqlObject($select3);
							$Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							
							//Advance Recovery BillAbstract FormatTypeId=3
							$select = $sql->select();
							$select->from( array('a' => 'CB_BillAbstract' ))
								->columns(array( 'BillAbsId', 'BillId', 'BillFormatId' => new Expression("6"), 'Amount' => new Expression("a.CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
								->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
								->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
							$select->where("a.CurAmount<>0 ");
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.Amount) ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select21->combine($select,'Union ALL');
							
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.BillId<>$billId");
							$select2->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select2->combine($select21,'Union ALL');
							 
							$select3 = $sql->select();
							$select3->from(array("g"=>$select2))
									->columns(array("BillAbsId","BillId","BillFormatId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
									array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ))
									->join(array('c' => 'CB_BillMaster'), 'g.BillId=c.BillId', array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ), $select3::JOIN_INNER);
							$select3->group(new Expression('g.BillAbsId,g.BillId,g.BillFormatId,c.BillNo,c.BillDate'));
							$select3->order('g.BillId');
							$statement = $sql->getSqlStringForSqlObject($select3);
							$Format['BillAbstract'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '8': // Material Recovery
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillMaterialRecovery' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '7': // Bill Deduction
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillVendorBill' ) )
								->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
								->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '19': // Free Supply Material
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
			$this->_view->billinfo = $billinfo;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function billreportlistAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Billing Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		$request = $this->getRequest();
		if ($request->isPost()) {
		
		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			$this->_view->billId = $billId;
			$this->_view->type = $type;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
}