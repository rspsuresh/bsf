<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Project\Controller;

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

class FollowupController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();

        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function followupAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        //$FollowUpId = $this->bsf->isNullCheck($this->params()->fromRoute('followupid'), 'number');
        //$nEnquiryId = 0;
        $nEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
        $nEnquiryName = "";

        $request = $this->getRequest();
        if($request->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $enquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_EnquiryFollowup'))
                        ->join(array('b' => 'Proj_ContractCallTypeMaster'), 'a.CallTypeId=b.CallTypeId', array('CallTypeName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WF_Users'), 'a.CreatedUserId=c.UserId', array('UserName','UserLogo'), $select::JOIN_LEFT)
                        ->columns(array('Remarks','CreatedDate'))
                        ->where("a.TenderEnquiryId=$enquiryId");
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $history = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $response = $this->getResponse();
                    if(!count($history)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $html = '';
                        foreach($history as $value) {
                            $html .= '<div class="itemdiv dialogdiv">
									<div class="user"><img src="'.$viewRenderer->basePath() . '/' . $value['UserLogo'] . '" /></div>
									<div class="body">
										<div class="time"><i class="fa fa-calendar"></i> '.date('d/m/Y', strtotime($value['CreatedDate'])).' &nbsp;&nbsp; <i class="fa fa-clock-o"></i> '.date('h:i A', strtotime($value['CreatedDate'])).'</div>
										<div class="name"><a href="#">'.$value['CallTypeName'].' &nbsp;<i class="fa fa-link lco"></i></a><strong>'. $value['UserName'] . '</strong></div>
										<div class="text">'.$value['Remarks'].'</div>
									</div>
								</div>';
                        }
                        $response->setContent($html);
                    }

                    return $response;
                } catch(PDOException $e){
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }
        else if ($request->isPost()) {

            $postData = $request->getPost();
            $formfrom = $this->bsf->isNullCheck($postData['formfrom'], 'string');
            $nEnquiryId = $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
            $nEnquiryName = $this->bsf->isNullCheck($postData['EnquiryName'], 'string');

            if ($formfrom != "title") {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $files = $request->getFiles();
                    $ContractId = 0;
//				echo '<pre>'; print_r($postData); die;


                    $NextCallDate = NULL;
                    if ($postData['NextCallDate']) {
                        $NextCallDate = date('Y-m-d', strtotime($postData['NextCallDate']));
                    }
                    if ($postData['RefDate']) {
                        $RefDate = date('Y-m-d', strtotime($postData['RefDate']));
                    }
                    if ($postData['checklistDate']) {
                        $checklistDate = date('Y-m-d', strtotime($postData['checklistDate']));
                    }

//				Ref Date ,Next Enquiry call type , next call remarks columns included
                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryFollowup');
                    $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                    , 'CallNatureId' => $this->bsf->isNullCheck($postData['CallNatureId'], 'number')
                    , 'CallTypeId' => $this->bsf->isNullCheck($postData['EnquiryCallType'], 'number')
                    , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                    , 'NextCallRequired' => $this->bsf->isNullCheck($postData['NextCallRequired'], 'number')
                    , 'NextCallDate' => $NextCallDate
                    , 'CreatedUserId' => $this->bsf->isNullCheck($this->auth->getIdentity()->UserId, 'number')
                    , 'CreatedDate' => date('Y-m-d H:i:s')
                    , 'RefDate' => $RefDate
                    , 'NextCallTypeId' => $this->bsf->isNullCheck($postData['NextEnquiryCallType'], 'number')
                    , 'NextCallRemarks' => $this->bsf->isNullCheck($postData['NextCallRemarks'], 'string')
                    , 'Reason' => $this->bsf->isNullCheck($postData['Reason'], 'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    if ($postData['EnquiryCallType'] == 8) {
                        //Assign - Checklist
                        $insert = $sql->insert();
                        $insert->into('Proj_ContractChecklistTrans');
                        $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                        , 'FollowupId' => $this->bsf->isNullCheck($enquiryFollowupId, 'number')
//                , 'RefDate' => $this->bsf->isNullCheck(date($postData['RefDate']),'date')
                        , 'RefDate' => $RefDate
                        , 'ChecklistId' => $this->bsf->isNullCheck($postData['TenderChecklist'], 'number')
                        , 'UserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                        , 'ChecklistDate' => $checklistDate
                        , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        , 'CreatedDate' => date('Y-m-d H:i:s')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ContractChecklistTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    if ($postData['EnquiryCallType'] == 9) {
                        // Checklist - Action
                        $subquery = $sql->select();
                        $subquery->from("Proj_ContractChecklistTrans")
                            ->columns(array('ContractChecklistTransId'))
                            ->where(array('ChecklistId' => $postData['TenderActionChecklist']));
                        $statement = $sql->getSqlStringForSqlObject($subquery);
                        $resContractList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (count($resContractList) > 0) {
                            $ContractId = $resContractList[0]['ContractChecklistTransId'];
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_ContractChecklistHistory');
                        $insert->Values(array('ContractChecklistTransId' => $this->bsf->isNullCheck($ContractId, 'number')
                        , 'FollowupId' => $this->bsf->isNullCheck($enquiryFollowupId, 'number')
                        , 'UserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                        , 'Status' => $this->bsf->isNullCheck($postData['Progress'], 'string')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ContractChecklistTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    if ($postData['EnquiryCallType'] == 16 || $postData['EnquiryCallType'] == 17) {
                        //Request For Information  - 16
                        $update = $sql->update();
                        $update->table('Proj_EnquiryFollowup');
                        $update->set(array(
                            'Subject' => $this->bsf->isNullCheck($postData['RequestSubject'], 'string'),
                            'Note' => $this->bsf->isNullCheck($postData['RequestNote'], 'string')
                        ))
                            ->where(array('EnquiryFollowupId' => $enquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // attachments
                        foreach($files['RequestDocuments'] as $file) {
                            if (!$file['name'])
                                continue;

                            $dir = 'public/uploads/project/followup/requests/' . $enquiryFollowupId . '/';
                            $filename = $this->bsf->uploadFile($dir, $file);

                            // update valid files only
                            if (!$filename)
                                continue;

                            $url = '/uploads/project/followup/requests/' . $enquiryFollowupId . '/' . $filename;

                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryFollowupFiles');
                            $insert->Values(array('EnquiryFollowupId' => $enquiryFollowupId, 'URL' => $url));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
                    $sessionEnquiryFollowup->enquiryId = $enquiryFollowupId;

                    if ($postData['AttachDocChk'] == 1) {
                        if ($files['AttachDoc']['name']) {
                            $dir = 'public/uploads/project/followup/';
                            if (!is_dir($dir))
                                mkdir($dir, 0755, true);

                            $docExt = pathinfo($files['AttachDoc']['name'], PATHINFO_EXTENSION);
                            $path = $dir . $enquiryFollowupId . '.' . $docExt;
                            move_uploaded_file($files['AttachDoc']['tmp_name'], $path);

                            $updateDocExt = $sql->update();
                            $updateDocExt->table('Proj_EnquiryFollowup');
                            $updateDocExt->set(array(
                                'AttachDoc' => $this->bsf->isNullCheck($docExt, 'string')
                            ))
                                ->where(array('EnquiryFollowupId' => $enquiryFollowupId));
                            $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                            $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

//				$redirect = '';
//				if($postData['EnquiryCallType']==1) {
//					$redirect = 'site-investigation';
//				} else if($postData['EnquiryCallType']==11) {
//					$redirect = 'technical-specification';
//				} else if($postData['EnquiryCallType']==2) {
//					$redirect = 'document-purchase';
//				}

                    $connection->commit();
//				$this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => $redirect));
                    $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }

        /*if ($FollowUpId != 0) {
            $select = $sql->select();
            $select->from(array('a' => 'Proj_EnquiryFollowup'))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_CallNatureMaster'), 'a.CallNatureId=c.CallNatureId', array('CallNatureName'), $select::JOIN_LEFT)
                ->where("a.EnquiryFollowUpId=$FollowUpId");

            $statement = $sql->getSqlStringForSqlObject($select);
            $arr_follow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->arr_follow = $arr_follow;

            if (!empty($arr_follow)) {
                $nEnquiryId = $arr_follow['TenderEnquiryId'];
                $nEnquiryName = $arr_follow['NameOfWork'];
            }
        }*/

        $quoteApprove="";
        $select = $sql->select();
        $select->from('Proj_TenderQuotationRegister')
            ->columns(array('Approve'))
            ->where("TenderEnquiryId=$nEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $quoteReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
        if (!empty($quoteReg)) $quoteApprove = $quoteReg['Approve'];

        $woApprove="";
        $select = $sql->select();
        $select->from('Proj_WORegister')
            ->columns(array('Approve'))
            ->where("TenderEnquiryId=$nEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $woReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
        if (!empty($woReg)) $woApprove = $woReg['Approve'];

        $this->_view->quoteApprove = $quoteApprove;
        $this->_view->woApprove = $woApprove;

        $select = $sql->select();
        $select->from(array('a' => 'Proj_TenderEnquiry'))
            ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Proj_WoRegister'), 'a.WORegisterId=c.WORegisterId', array('WONo','WODate'), $select::JOIN_LEFT)
            ->join(array('d' => 'WF_OperationalCostCentre'), 'a.WORegisterId=d.WORegisterId', array('CostCentreName'), $select::JOIN_LEFT)
            ->columns(array('TenderEnquiryId','RefNo','RefDate','NameOfWork','ClientId','ProposalCost','ProjectDuration','Duration','EnquiryType','QuoteType','QType'=>new Expression("Case When QuoteType='Q' then 'Quantity Amend' else 'New Items' end"),
                'DocumentPurchase','Quoted','Submitted','BidWin','OrderReceived','WorkStarted','TechnicalSpecificationId','EnquirySiteInvestigationId'))
            ->where("a.TenderEnquiryId=$nEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $nEnquiryName = $this->_view->enquiry['NameOfWork'];

        $tEnqId = $this->_view->enquiry['TenderEnquiryId'];
        $clientId = $this->_view->enquiry['ClientId'];

        $sessionWork = new Container('sessionWork');
        $sessionWork->tEnqId = $tEnqId ;
        $sessionWork->clientId = $clientId;

        //Enquiry Names
        $select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->columns(array('data' => 'TenderEnquiryId', 'value' => 'NameOfWork'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiryNames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Nautre of enquirys
        $select = $sql->select();
        $select->from('Proj_CallNatureMaster')
            ->columns(array('data' => 'CallNatureId', 'value' => 'CallNatureName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->natureEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Autocomplete Reason
        $select = $sql->select();
        $select->from('Proj_EnquiryFollowup')
            ->columns(array(new Expression("DISTINCT Reason as value")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->Reason = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'Proj_EnquiryFollowup'))
            ->join(array('b' => 'Proj_TenderEnquiry'), 'a.EnquiryFollowupId=b.TenderEnquiryId', array('NameOfWork'), $select::JOIN_LEFT)
            ->join(array('c' => 'Proj_CallNatureMaster'), 'a.CallNatureId=c.CallNatureId', array('CallNatureName'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->history = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from('Proj_ContractCallTypeMaster')
            ->columns(array('CallTypeId', 'CallTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiryCallType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //$this->_view->enquiryCallType = $this->bsf->getEnquiryCallType();

        //Tender Assign Checklist from Proj_CheckListMaster
        $subquery = $sql->select();
        $subquery -> from("Proj_ContractChecklistTrans")
            ->columns(array('ChecklistId'));

        $select = $sql->select();
        $select->from('Proj_CheckListMaster')
            ->where(array('TypeId'=> '5'));
        $select->where->expression('CheckListId NOT IN ?', array($subquery));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->AssignTenderList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Tender Action Checklist from Proj_CheckListMaster
        $subquery = $sql->select();
        $subquery -> from("Proj_ContractChecklistTrans")
            ->columns(array('ChecklistId'));

        $select = $sql->select();
        $select->from('Proj_CheckListMaster')
            ->where(array('TypeId'=> '4'));
        $select->where->expression('CheckListId IN ?', array($subquery));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ActionTenderList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Username from WF_Users
        $select = $sql->select();
        $select->from('WF_Users');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->UserName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Process Trans
        $select = $sql->select();
        $select->from(array('a' => "Proj_TenderProcessTrans"))
            ->columns(array('TenderProcessId', 'Status','Flag'))
            ->join(array('b' => 'Proj_TenderProcessMaster'), 'a.TenderProcessId = b.TenderProcessId', array('ProcessName'))
            ->where('a.TenderEnquiryId=' . $nEnquiryId)
            ->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->processSteps = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->EnquiryId = $nEnquiryId;
        $this->_view->EnquiryName = $nEnquiryName;
//        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
    }

    public function siteInvestigationAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

//		$sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
//		$enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $enquiryFollowupId = 0;
        $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
        $EnquiryCallTypeId = 3;
        $EnquirySiteInvestigationId = $this->bsf->isNullCheck($this->params()->fromRoute('EnquirySiteInvestigationId'),'number');


        if (($EnquirySiteInvestigationId) ==0) {
            $select = $sql->select();
            $select->from('Proj_EnquirySiteInvestigation')
                ->columns(array('EnquirySiteInvestigationId'))
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $eSiteInvestigation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($eSiteInvestigation)) {
                $EnquirySiteInvestigationId = $this->bsf->isNullCheck($eSiteInvestigation['EnquirySiteInvestigationId'],'number');
            }
        }

        if($EnquirySiteInvestigationId != 0) {
            //Load For Edit Page
            //Enquiry Site Investigation
            $select = $sql->select();
            $select->from('Proj_EnquirySiteInvestigation')
                ->where(array('EnquirySiteInvestigationId'=>$EnquirySiteInvestigationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquirySiteInvestigation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            //Proj_EnquiryLocalMaterialAvailability
            $select = $sql->select();
            $select->from('Proj_EnquiryLocalMaterialAvailability')
                ->where(array('EnquirySiteInvestigationId'=>$EnquirySiteInvestigationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryLocalMaterialAvailability = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Proj_EnquiryLabourAvailability
            $select = $sql->select();
            $select->from('Proj_EnquiryLabourAvailability')
                ->where(array('EnquirySiteInvestigationId'=>$EnquirySiteInvestigationId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryLabourAvailability = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->current();

            //Proj_EnquiryLabourType
            $subquery = $sql->select();
            $subquery -> from("Proj_EnquiryLabourAvailability")
                ->columns(array('EnquiryLabourAvailabilityId'))
                ->where(array('EnquirySiteInvestigationId' =>$EnquirySiteInvestigationId));
            $statement = $sql->getSqlStringForSqlObject( $subquery );
            $resLabourId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            if(count($resLabourId) >0 ) {
                $resLabourId= $resLabourId[0]['EnquiryLabourAvailabilityId'];
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_EnquiryLabourType'))
                ->join(array('b' => 'Proj_Resource'), 'a.LabourTypeId=b.ResourceId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->where(array('a.EnquiryLabourAvailabilityId'=>$resLabourId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryLabourType = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Image and video file types
            $select = $sql->select();
            $select->from('Proj_EnquirySiteInvestigationFiles')
                ->where("SiteInvestId=$EnquirySiteInvestigationId AND FileType='image'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $images = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($images) > 0) {
                $this->_view->images = $images;
            }

            $select = $sql->select();
            $select->from('Proj_EnquirySiteInvestigationFiles')
                ->where("SiteInvestId=$EnquirySiteInvestigationId AND FileType='video'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $videos = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if($videos != FALSE && count($videos) > 0) {
                $this->_view->videos = $videos;
            }

            $select = $sql->select();
            $select->from('Proj_SiteDocumentTrans')
                ->where('SiteInvestId=' . $EnquirySiteInvestigationId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->siteDocumentTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                if($EnquirySiteInvestigationId != 0) {
                    //Update
                    $postData = $request->getPost();
                    $files = $request->getFiles();
                    //					echo '<pre>'; print_r($postData); die;

                    //					$enquiryFollowupId = $postData['EnquiryFollowupId'];
                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquirySiteInvestigation")
                        ->where(array('EnquirySiteInvestigationId' =>$EnquirySiteInvestigationId));
                    $statement = $sql->getSqlStringForSqlObject( $subquery );
                    $resFollowupId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(count($resFollowupId) >0 ) {
                        $resFollowupId= $resFollowupId[0]['EnquiryFollowupId'];
                    }

                    $update = $sql->update();
                    $update->table('Proj_EnquiryFollowup');
                    $update->set(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                    , 'CallTypeId' => $this->bsf->isNullCheck($EnquiryCallTypeId,'number')))
                        ->where(array('EnquiryFollowupId'=>$resFollowupId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_EnquirySiteInvestigation');
                    $update->set(array('EnquiryFollowupId' =>$resFollowupId
                    , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'], 'string')
                    , 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'], 'string')
                    , 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                    , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number')
                    , 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number')
                    , 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number')
                    , 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number')
                    , 'AreaForMaterialStorage' => $this->bsf->isNullCheck($postData['AreaForMaterialStorage'], 'number')
                    , 'AreaForMaterialStorageUnitId' => $this->bsf->isNullCheck($postData['AreaForMaterialStorageUnitId'], 'number')
                    , 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'], 'number')
                    , 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'], 'number')
                    , 'LabourShed' => $this->bsf->isNullCheck($postData['LabourShed'], 'number')
                    , 'MaterialStorage' => $this->bsf->isNullCheck($postData['MaterialStorage'], 'number')
                    , 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'], 'string')
                    , 'BusStop' => $this->bsf->isNullCheck($postData['BusStop'], 'string')
                    , 'Hospital' => $this->bsf->isNullCheck($postData['Hospital'], 'string')
                    , 'Airport' => $this->bsf->isNullCheck($postData['Airport'], 'string')
                    , 'RailwayStation' => $this->bsf->isNullCheck($postData['RailwayStation'], 'string')
                    , 'PoliceStation' => $this->bsf->isNullCheck($postData['PoliceStation'], 'string')
                    , 'FireStation' => $this->bsf->isNullCheck($postData['FireStation'], 'string')
                    , 'Hotel' => $this->bsf->isNullCheck($postData['Hotel'], 'string')
                        //, 'DocumentType' => $this->bsf->isNullCheck($postData['DocumentType'], 'string')
                        //, 'DocumentDescription' => $this->bsf->isNullCheck($postData['DocumentDescription'], 'string')
                    ))
                        ->where(array('EnquirySiteInvestigationId' =>$EnquirySiteInvestigationId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*if ($files['siteInvestDocument']['name']) {
                        $dir = 'public/uploads/project/followup/';
                        if (!is_dir($dir))
                            mkdir($dir, 0755, true);

                        $docExt = pathinfo($files['siteInvestDocument']['name'], PATHINFO_EXTENSION);
                        $path = $dir . $EnquirySiteInvestigationId . '.' . $docExt;
                        move_uploaded_file($files['siteInvestDocument']['tmp_name'], $path);

                        $updateDocExt = $sql->update();
                        $updateDocExt->table('Proj_EnquirySiteInvestigation');
                        $updateDocExt->set(array(
                            'DocExt' => $this->bsf->isNullCheck($docExt, 'string')
                        ))
                            ->where(array('EnquirySiteInvestigationId' => $EnquirySiteInvestigationId));
                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }*/

                    /* $subquery = $sql->select();
                     $subquery -> from("Proj_EnquiryLocalMaterialAvailability")
                         ->where(array('EnquirySiteInvestigationId' =>$EnquirySiteInvestigationId));
                     $statement = $sql->getSqlStringForSqlObject( $subquery );
                     $resLocalMaterialAvailability = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                     $materialCount = $postData['MaterialCount'];
                     for ($i = 1; $i <= $materialCount; $i++) {
 //                        $m = $i-1;
                         if ($this->bsf->isNullCheck($postData['Material_' . $i], 'string') != "") {
                             $update = $sql->update();
                             $update->table('Proj_EnquiryLocalMaterialAvailability');
                             $update->set(array('Material' => $this->bsf->isNullCheck($postData['Material_' . $i], 'string')
                             , 'MaterialId' => $this->bsf->isNullCheck($postData['MaterialId_' . $i], 'number')
                             , 'AvailabilityDistance' => $this->bsf->isNullCheck($postData['AvailabilityDistance_' . $i], 'string')
                             , 'VendorName' => $this->bsf->isNullCheck($postData['VendorName_' . $i], 'string')
                             , 'AddressAndContactNumber' => $this->bsf->isNullCheck($postData['AddressAndContactNumber_' . $i], 'string')
                             , 'AvailabilityLevel' => $this->bsf->isNullCheck($postData['AvailabilityLevel_' . $i], 'string')
                             , 'Rate' => $this->bsf->isNullCheck($postData['Rate_' . $i], 'number')))
                                 ->where(array('EnquiryLocalMaterialAvailabilityId' =>$resLocalMaterialAvailability['EnquiryLocalMaterialAvailabilityId']));
                             $statement = $sql->getSqlStringForSqlObject($update);
                             $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                         }
                     }*/
                    //Delete and insert for material multiple rows
                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryLocalMaterialAvailability')
                        ->where("EnquirySiteInvestigationId=$EnquirySiteInvestigationId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $materialCount = $postData['MaterialCount'];
                    for ($i = 1; $i <= $materialCount; $i++) {
                        if ($this->bsf->isNullCheck($postData['Material_' . $i], 'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryLocalMaterialAvailability');
                            $insert->Values(array('EnquirySiteInvestigationId' => $this->bsf->isNullCheck($EnquirySiteInvestigationId, 'number')
                            , 'Material' => $this->bsf->isNullCheck($postData['Material_' . $i], 'string')
                            , 'MaterialId' => $this->bsf->isNullCheck($postData['MaterialId_' . $i], 'number')
                            , 'AvailabilityDistance' => $this->bsf->isNullCheck($postData['AvailabilityDistance_' . $i], 'string')
                            , 'AvailabilityDistanceUnitId' => $this->bsf->isNullCheck($postData['AvailabilityDistanceUnitId_' . $i], 'number')
                            , 'VendorName' => $this->bsf->isNullCheck($postData['VendorName_' . $i], 'string')
                            , 'Address' => $this->bsf->isNullCheck($postData['AddressAndContactNumber_' . $i], 'string')
                            , 'ContactNumber' => $this->bsf->isNullCheck($postData['ContactNumber_' . $i], 'string')
//                            , 'AvailabilityLevel' => $this->bsf->isNullCheck($postData['AvailabilityLevel_' . $i], 'string')
                            , 'Rate' => $this->bsf->isNullCheck($postData['Rate_' . $i], 'number')
                            , 'RateUnitId' => $this->bsf->isNullCheck($postData['RateUnitId_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }


                    $update = $sql->update();
                    $update->table('Proj_EnquiryLabourAvailability');
                    $update->set(array('UnionProblem' => $this->bsf->isNullCheck($postData['UnionProblem'], 'number')
                    , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')))
                        ->where(array('EnquirySiteInvestigationId' =>$EnquirySiteInvestigationId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        $enquiryLabourAvailabilityId = $dbAdapter->getDriver()->getLastGeneratedValue();$resLabourId

                    /*  $subquery = $sql->select();
                      $subquery -> from("Proj_EnquiryLabourType")
                          ->where(array('EnquiryLabourAvailabilityId' =>$resLabourId));
                      $statement = $sql->getSqlStringForSqlObject( $subquery );
                      $resLabourTypeId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                      $labourTypeCount = $postData['LabourTypeCount'];
                      for ($i = 1; $i <= $labourTypeCount; $i++) {
                          $m = $i-1;
                          if ($this->bsf->isNullCheck($postData['LabourType_' . $i], 'string') != "") {
                              $update = $sql->update();
                              $update->table('Proj_EnquiryLabourType');
                              $update->set(array('LabourType' => $this->bsf->isNullCheck($postData['LabourType_' . $i], 'string')
                              , 'Rate' => $this->bsf->isNullCheck($postData['LaRate_' . $i], 'number')))
                                  ->where(array('EnquiryLabourTypeId' =>$resLabourTypeId[$m]['EnquiryLabourTypeId']));
                              $statement = $sql->getSqlStringForSqlObject($update);
                              $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                          }
                      }*/
                    //delete and insert labour type row
                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryLabourType')
                        ->where("EnquiryLabourAvailabilityId=$EnquirySiteInvestigationId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $labourTypeCount = $postData['LabourTypeCount'];
                    for ($i = 1; $i <= $labourTypeCount; $i++) {
                        if ($this->bsf->isNullCheck($postData['LabourType_' . $i], 'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryLabourType');
                            $insert->Values(array('EnquiryLabourAvailabilityId' => $this->bsf->isNullCheck($EnquirySiteInvestigationId, 'number')
                            , 'LabourTypeId' => $this->bsf->isNullCheck($postData['LabourTypeId_' . $i], 'number')
                            , 'LabourType' => $this->bsf->isNullCheck($postData['LabourType_' . $i], 'string')
                            , 'Rate' => $this->bsf->isNullCheck($postData['LaRate_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Delete and insert Document
                    $delete = $sql->delete();
                    $delete->from('Proj_SiteDocumentTrans')
                        ->where("SiteInvestId=$enquiryId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $documentRowId = $this->bsf->isNullCheck($postData['sitedocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $type = $this->bsf->isNullCheck($postData['DocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['DocDesc_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['siteDocFile_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        if($url == '') {
                            if ($files['siteDocFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/site-investigation/' . $enquiryId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['siteDocFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/site-investigation/' . $enquiryId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_SiteDocumentTrans');
                        $insert->Values(array('SiteInvestId' => $enquiryId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/followup/siteinvestigation/' . $EnquirySiteInvestigationId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/followup/siteinvestigation/' . $EnquirySiteInvestigationId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_EnquirySiteInvestigationFiles');
                        $insert->Values(array('SiteInvestId' => $EnquirySiteInvestigationId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else {
                    //Insert
                    $postData = $request->getPost();
                    $files = $request->getFiles();
                    //					echo '<pre>'; print_r($postData); die;

                    //					$enquiryFollowupId = $postData['EnquiryFollowupId'];
                    //Insert in Followup and then in Site-Investigation
                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryFollowup');
                    $insert->Values(array('TenderEnquiryId' => $enquiryId
                    , 'CallTypeId' => $this->bsf->isNullCheck($EnquiryCallTypeId, 'number')
                    , 'CreatedDate' => date('Y-m-d H:i:s')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $insert = $sql->insert();
                    $insert->into('Proj_EnquirySiteInvestigation');
                    $insert->Values(array('EnquiryFollowupId' => $this->bsf->isNullCheck($enquiryFollowupId, 'number'),'TenderEnquiryId' => $enquiryId
                    , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'], 'string')
                    , 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'], 'string')
                    , 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                    , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number')
                    , 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number')
                    , 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number')
                    , 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number')
                    , 'AreaForMaterialStorage' => $this->bsf->isNullCheck($postData['AreaForMaterialStorage'], 'number')
                    , 'AreaForMaterialStorageUnitId' => $this->bsf->isNullCheck($postData['AreaForMaterialStorageUnitId'], 'number')
                    , 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'], 'number')
                    , 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'], 'number')
                    , 'LabourShed' => $this->bsf->isNullCheck($postData['LabourShed'], 'number')
                    , 'MaterialStorage' => $this->bsf->isNullCheck($postData['MaterialStorage'], 'number')
                    , 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'], 'string')
                    , 'BusStop' => $this->bsf->isNullCheck($postData['BusStop'], 'string')
                    , 'Hospital' => $this->bsf->isNullCheck($postData['Hospital'], 'string')
                    , 'Airport' => $this->bsf->isNullCheck($postData['Airport'], 'string')
                    , 'RailwayStation' => $this->bsf->isNullCheck($postData['RailwayStation'], 'string')
                    , 'PoliceStation' => $this->bsf->isNullCheck($postData['PoliceStation'], 'string')
                    , 'FireStation' => $this->bsf->isNullCheck($postData['FireStation'], 'string')
                    , 'Hotel' => $this->bsf->isNullCheck($postData['Hotel'], 'string')
                    , 'DocumentType' => $this->bsf->isNullCheck($postData['DocumentType'], 'string')
                    , 'DocumentDescription' => $this->bsf->isNullCheck($postData['DocumentDescription'], 'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $siteInvestId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    /*$update = $sql->update();
                    $update->table('Proj_TenderEnquiry')
                        ->set(array('EnquirySiteInvestigationId' => $siteInvestId))
                        ->where(array('TenderEnquiryId' => $enquiryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                    $select = $sql->select();
                    $select->from('Proj_TenderProcessMaster')
                        ->columns(array('TenderProcessId'))
                        ->where(array('ProcessName' => 'Site Investigation'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $processId = $processName['TenderProcessId'];

                    $update = $sql->update();
                    $update->table('Proj_TenderProcessTrans')
                        ->set(array('Flag' => 1))
                        ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*if ($files['siteInvestDocument']['name']) {
                        $dir = 'public/uploads/project/followup/';
                        if (!is_dir($dir))
                            mkdir($dir, 0755, true);

                        $docExt = pathinfo($files['siteInvestDocument']['name'], PATHINFO_EXTENSION);
                        $path = $dir . $siteInvestId . '.' . $docExt;
                        move_uploaded_file($files['siteInvestDocument']['tmp_name'], $path);

                        $updateDocExt = $sql->update();
                        $updateDocExt->table('Proj_EnquirySiteInvestigation');
                        $updateDocExt->set(array(
                            'DocExt' => $this->bsf->isNullCheck($docExt, 'string')
                        ))
                            ->where(array('EnquirySiteInvestigationId' => $siteInvestId));
                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }*/

                    $materialCount = $postData['MaterialCount'];
                    for ($i = 1; $i <= $materialCount; $i++) {
                        if ($this->bsf->isNullCheck($postData['Material_' . $i], 'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryLocalMaterialAvailability');
                            $insert->Values(array('EnquirySiteInvestigationId' => $this->bsf->isNullCheck($siteInvestId, 'number')
                            , 'Material' => $this->bsf->isNullCheck($postData['Material_' . $i], 'string')
                            , 'MaterialId' => $this->bsf->isNullCheck($postData['MaterialId_' . $i], 'number')
                            , 'AvailabilityDistance' => $this->bsf->isNullCheck($postData['AvailabilityDistance_' . $i], 'string')
                            , 'AvailabilityDistanceUnitId' => $this->bsf->isNullCheck($postData['AvailabilityDistanceUnitId_' . $i], 'number')
                            , 'VendorName' => $this->bsf->isNullCheck($postData['VendorName_' . $i], 'string')
                            , 'Address' => $this->bsf->isNullCheck($postData['AddressAndContactNumber_' . $i], 'string')
                            , 'ContactNumber' => $this->bsf->isNullCheck($postData['ContactNumber_' . $i], 'string')
//                            , 'AvailabilityLevel' => $this->bsf->isNullCheck($postData['AvailabilityLevel_' . $i], 'string')
                            , 'Rate' => $this->bsf->isNullCheck($postData['Rate_' . $i], 'number')
                            , 'RateUnitId' => $this->bsf->isNullCheck($postData['RateUnitId' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryLabourAvailability');
                    $insert->Values(array('EnquirySiteInvestigationId' => $this->bsf->isNullCheck($siteInvestId, 'number')
                    , 'UnionProblem' => $this->bsf->isNullCheck($postData['UnionProblem'], 'number')
                    , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryLabourAvailabilityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $labourTypeCount = $postData['LabourTypeCount'];
                    for ($i = 1; $i <= $labourTypeCount; $i++) {
                        if ($this->bsf->isNullCheck($postData['LabourType_' . $i], 'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryLabourType');
                            $insert->Values(array('EnquiryLabourAvailabilityId' => $this->bsf->isNullCheck($enquiryLabourAvailabilityId, 'number')
                            , 'LabourTypeId' => $this->bsf->isNullCheck($postData['LabourTypeId_' . $i], 'number')
                            , 'LabourType' => $this->bsf->isNullCheck($postData['LabourType_' . $i], 'string')
                            , 'Rate' => $this->bsf->isNullCheck($postData['LaRate_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['sitedocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $type = $this->bsf->isNullCheck($postData['DocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['DocDesc_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        $url = '';

                        if ($files['siteDocFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/site-investigation/' . $enquiryId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['siteDocFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/site-investigation/' . $enquiryId . '/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_SiteDocumentTrans');
                        $insert->Values(array('SiteInvestId ' => $siteInvestId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/followup/siteinvestigation/' . $siteInvestId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/followup/siteinvestigation/' . $siteInvestId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4','wmv','mpg');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_EnquirySiteInvestigationFiles');
                        $insert->Values(array('SiteInvestId' => $siteInvestId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                }
                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
            } catch(PDOException $e){
                $connection->rollback();
            }
        }

        //Zone List
        $select = $sql->select();
        $select->from('Proj_ZoneMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->zoneTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Area Unit List
        $select = $sql->select();
        $select->from('Proj_UOM')
            ->where('TypeId=2');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //All unit for rate
        $select = $sql->select();
        $select->from('Proj_UOM');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->allUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Distance Unit
        $select = $sql->select();
        $select->from('Proj_UOM')
            ->where('TypeId=1');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitTypesKm = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Enquiry Name as Name of Work
        $select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->columns(array("NameOfWork"))
            ->where(array('TenderEnquiryId' => $enquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $NameOfWork = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $enquiryName = "";
        if (!empty($NameOfWork)) $enquiryName = $NameOfWork["NameOfWork"];
        $this->_view->EnquiryName = $enquiryName;

        $this->_view->enquiryFollowupId = $enquiryFollowupId;
        $this->_view->enquiryId = $enquiryId;

        //Autocomplete table
        //Material
        $select = $sql->select();
        $select->from('Proj_Resource')
            ->columns(array("data"=>'ResourceId',"value"=> new Expression("Code + ' ' +ResourceName")))
            ->where(array("DeleteFlag" => '0','TypeId'=> '2'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resMaterial = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Labour
        $select = $sql->select();
        $select->from(array('a' => 'Proj_Resource'))
            ->columns(array("data"=>'ResourceId',"value"=> new Expression("Code + ' ' +ResourceName")))
            ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
            ->where(array("a.DeleteFlag" => '0','a.TypeId'=> '1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resLabour = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Soil Type autocomplete
        $select = $sql->select();
        $select->from('Proj_SoilTypeMaster')
            ->columns(array(new Expression("DISTINCT SoilType as value")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->SoilType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        /*$select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->columns(array("NameOfWork"))
            ->where(array('TenderEnquiryId'=>$enquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();*/

        return $this->_view;
    }

    public function technicalSpecificationAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

//		$sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
//		$enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        $enquiryFollowupId = 0;
        $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
        $EnquiryCallTypeId = 4;
        $TechnicalSpecificationId = $this->params()->fromRoute('TechnicalSpecificationId');

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        //Proj_EnquiryTechnicalSpecification
        $select = $sql->select();
        $select->from('Proj_EnquiryTechnicalSpecification')
            ->where(array('TenderEnquiryId'=>$enquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->EnquiryTechnicalSpecification = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $TechnicalSpecificationId = $this->_view->EnquiryTechnicalSpecification['TechnicalSpecificationId'];

        if($this->bsf->isNullCheck($TechnicalSpecificationId,'number') != 0) {
            //Load For Edit Page
            //Proj_EnquiryResourceRate
            $select = $sql->select();
            $select->from(array('a'=>'Proj_EnquiryResourceRate'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array("ResourceName" => new Expression("Code + ' ' + ResourceName")), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName"), $select::JOIN_LEFT)
                ->where(array('TechnicalSpecificationId'=>$TechnicalSpecificationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryResourceRate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Proj_EnquiryCoefficient
            $select = $sql->select();
            $select->from(array('a'=>'Proj_EnquiryCoefficient'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array("ResourceName" => new Expression("Code + ' ' + ResourceName")), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName"), $select::JOIN_LEFT)
                ->where(array('TechnicalSpecificationId'=>$TechnicalSpecificationId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryCoefficient = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Proj_EnquiryMakeBrand
            $select = $sql->select();
            $select->from('Proj_EnquiryMakeBrand')
                ->where(array('TechnicalSpecificationId'=>$TechnicalSpecificationId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryMakeBrand = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Proj_EnquiryShortNotes
            $select = $sql->select();
            $select->from('Proj_EnquiryShortNotes')
                ->where(array('TechnicalSpecificationId'=>$TechnicalSpecificationId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryShortNotes = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Proj_EnquiryCheckList
            $select = $sql->select();
            $select->from('Proj_EnquiryCheckList')
                ->where(array('TechnicalSpecificationId'=>$TechnicalSpecificationId));
            $resstatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EnquiryCheckList = $dbAdapter->query($resstatement , $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                if($this->bsf->isNullCheck($TechnicalSpecificationId,'number') != 0) {
                    //Update
                    $postData = $request->getPost();

//					echo '<pre>'; print_r($postData); die;
//					$enquiryFollowupId = $postData['EnquiryFollowupId'];

                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryTechnicalSpecification")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject( $subquery );
                    $resFollowupId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(count($resFollowupId) >0 ) {
                        $resFollowupId= $resFollowupId[0]['EnquiryFollowupId'];
                    }

                    $update = $sql->update();
                    $update->table('Proj_EnquiryFollowup');
                    $update->set(array('TenderEnquiryId' => $enquiryId
                    , 'CallTypeId' => $this->bsf->isNullCheck($EnquiryCallTypeId, 'number')))
                        ->where(array('EnquiryFollowupId'=>$resFollowupId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $update = $sql->update();
                    $update->table('Proj_EnquiryTechnicalSpecification');
                    $update->set(array('EnquiryFollowupId' => $resFollowupId
                    , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'],'string')
                    , 'Notes' => $this->bsf->isNullCheck($postData['Notes'],'string')))
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //$techSpecId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    /*$subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryResourceRate")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($subquery);
                    $resEnquiryResourceRate = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $resourceCount = $postData['ResourceCount'];
                    for($i=1;$i<=$resourceCount;$i++) {
                        $m = $i-1;
                        if($this->bsf->isNullCheck($postData['ResourceName_'.$i],'string') != "") {
                            $update = $sql->update();
                            $update->table('Proj_EnquiryResourceRate');
                            $update->set(array('ResourceId' => $this->bsf->isNullCheck($postData['ResourceId_'.$i],'string')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['UnitId_'.$i],'number')
                            , 'Rate' => $this->bsf->isNullCheck($postData['Rate_'.$i],'number')))
                                ->where(array('EnquiryResourceRateId' =>$resEnquiryResourceRate[$m]['EnquiryResourceRateId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryCoefficient")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($subquery);
                    $resEnquiryCoefficient = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $coefficientCount = $postData['CoefficientCount'];
                    for($i=1;$i<=$coefficientCount;$i++) {
                        $m = $i-1;
                        if($this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string') != "") {
                            $update = $sql->update();
                            $update->table('Proj_EnquiryCoefficient');
                            $update->set(array('SerialNo' => $this->bsf->isNullCheck($postData['SerialNo_'.$i],'string')
                            , 'ItemOfWork' => $this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string')
                            , 'ResourceId' => $this->bsf->isNullCheck($postData['ResourcesIOWId_'.$i],'number')
                            , 'Coefficient' => $this->bsf->isNullCheck($postData['Coefficient_'.$i],'number')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['CoUnitId_'.$i],'number')))
                                ->where(array('EnquiryCoefficientId' =>$resEnquiryCoefficient[$m]['EnquiryCoefficientId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryMakeBrand")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($subquery);
                    $resEnquiryMakeBrand = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $makeBrandCount = $postData['MakeBrandCount'];
                    for($i=1;$i<=$makeBrandCount;$i++) {
                        $m = $i-1;
                        if($this->bsf->isNullCheck($postData['MaterialName_'.$i],'string') != "") {
                            $update = $sql->update();
                            $update->table('Proj_EnquiryMakeBrand');
                            $update->set(array('MaterialName' => $this->bsf->isNullCheck($postData['MaterialName_'.$i],'string')
                            , 'Make' => $this->bsf->isNullCheck($postData['Make_'.$i],'string')))
                                ->where(array('EnquiryMakeBrandId' =>$resEnquiryMakeBrand[$m]['EnquiryMakeBrandId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryShortNotes")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($subquery);
                    $resEnquiryShortNotes = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $shortNotesCount = $postData['ShortNotesCount'];
                    for($i=1;$i<=$shortNotesCount;$i++) {
                        $m = $i-1;
                        if($this->bsf->isNullCheck($postData['Title_'.$i],'string') != "") {
                            $update = $sql->update();
                            $update->table('Proj_EnquiryShortNotes');
                            $update->set(array('Title' => $this->bsf->isNullCheck($postData['Title_'.$i],'string')
                            , 'Specifications' => $this->bsf->isNullCheck($postData['Specifications_'.$i],'string')))
                                ->where(array('EnquiryShortNotesId' =>$resEnquiryShortNotes[$m]['EnquiryShortNotesId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $subquery = $sql->select();
                    $subquery -> from("Proj_EnquiryCheckList")
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($subquery);
                    $resEnquiryCheckList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $checkListCount = count($postData['CheckList']);
                    for($i=0;$i<$checkListCount;$i++) {
                        $m = $i-1;
                        if($this->bsf->isNullCheck($postData['CheckList'][$i],'number') != "") {
                            $update = $sql->update();
                            $update->table('Proj_EnquiryCheckList');
                            $update->set(array('CheckListId' => $this->bsf->isNullCheck($postData['CheckList'][$i],'number')))
                                ->where(array('EnquiryCheckListId' =>$resEnquiryCheckList[$m]['EnquiryCheckListId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }*/

                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryResourceRate')
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $resourceCount = $postData['ResourceCount'];
                    for($i=1;$i<=$resourceCount;$i++) {
                        if($this->bsf->isNullCheck($postData['ResourceName_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryResourceRate');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($TechnicalSpecificationId,'number')
                            , 'ResourceId' => $this->bsf->isNullCheck($postData['ResourceId_'.$i],'string')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['UnitId_'.$i],'number')
                            , 'Rate' => $this->bsf->isNullCheck($postData['Rate_'.$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryCoefficient')
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $coefficientCount = $postData['CoefficientCount'];
                    for($i=1;$i<=$coefficientCount;$i++) {
                        if($this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCoefficient');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($TechnicalSpecificationId,'number')
                            , 'SerialNo' => $this->bsf->isNullCheck($postData['SerialNo_'.$i],'string')
                            , 'ItemOfWork' => $this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string')
                            , 'ResourceId' => $this->bsf->isNullCheck($postData['ResourcesIOWId_'.$i],'number')
                            , 'Coefficient' => $this->bsf->isNullCheck($postData['Coefficient_'.$i],'number')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['CoUnitId_'.$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryMakeBrand')
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $makeBrandCount = $postData['MakeBrandCount'];
                    for($i=1;$i<=$makeBrandCount;$i++) {
                        if($this->bsf->isNullCheck($postData['MaterialName_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryMakeBrand');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($TechnicalSpecificationId,'number')
                            , 'MaterialName' => $this->bsf->isNullCheck($postData['MaterialName_'.$i],'string')
                            , 'Make' => $this->bsf->isNullCheck($postData['Make_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryShortNotes')
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $shortNotesCount = $postData['ShortNotesCount'];
                    for($i=1;$i<=$shortNotesCount;$i++) {
                        if($this->bsf->isNullCheck($postData['Title_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryShortNotes');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($TechnicalSpecificationId,'number')
                            , 'Title' => $this->bsf->isNullCheck($postData['Title_'.$i],'string')
                            , 'Specifications' => $this->bsf->isNullCheck($postData['Specifications_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_EnquiryCheckList')
                        ->where(array('TechnicalSpecificationId' =>$TechnicalSpecificationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $checkListCount = count($postData['CheckList']);
                    for($i=0;$i<$checkListCount;$i++) {
                        if($this->bsf->isNullCheck($postData['CheckList'][$i],'number') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCheckList');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($TechnicalSpecificationId,'number')
                            , 'CheckListId' => $this->bsf->isNullCheck($postData['CheckList'][$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $newCheckListCount = $postData['CheckListCount'];
                    for($i=1;$i<=$newCheckListCount;$i++) {
                        if($this->bsf->isNullCheck($postData['NewCheckList_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $this->bsf->isNullCheck($postData['NewCheckList_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $checkListId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCheckList');
                            $insert->Values(array('TechnicalSpecificationId' => $TechnicalSpecificationId
                            , 'CheckListId' => $this->bsf->isNullCheck($checkListId,'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                } else {
                    //Insert
                    $postData = $request->getPost();

//					echo '<pre>'; print_r($postData); die;
//					$enquiryFollowupId = $postData['EnquiryFollowupId'];

                    //Insert in Proj_EnquiryFollowup and then remaining
                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryFollowup');
                    $insert->Values(array('TenderEnquiryId' => $enquiryId
                    , 'CallTypeId' => $this->bsf->isNullCheck($EnquiryCallTypeId, 'number')
                    , 'CreatedDate' => date('Y-m-d H:i:s')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryTechnicalSpecification');
                    $insert->Values(array('EnquiryFollowupId' => $this->bsf->isNullCheck($enquiryFollowupId,'number'),'TenderEnquiryId' => $enquiryId
                    , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'],'string')
                    , 'Notes' => $this->bsf->isNullCheck($postData['Notes'],'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $techSpecId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    /*$update = $sql->update();
                    $update->table('Proj_TenderEnquiry')
                        ->set(array('TechnicalSpecificationId' => $techSpecId))
                        ->where(array('TenderEnquiryId' => $enquiryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                    $select = $sql->select();
                    $select->from('Proj_TenderProcessMaster')
                        ->columns(array('TenderProcessId'))
                        ->where(array('ProcessName' => 'Technical Specification'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $processId = $processName['TenderProcessId'];

                    $update = $sql->update();
                    $update->table('Proj_TenderProcessTrans')
                        ->set(array('Flag' => 1))
                        ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $resourceCount = $postData['ResourceCount'];
                    for($i=1;$i<=$resourceCount;$i++) {
                        if($this->bsf->isNullCheck($postData['ResourceName_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryResourceRate');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'ResourceId' => $this->bsf->isNullCheck($postData['ResourceId_'.$i],'string')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['UnitId_'.$i],'number')
                            , 'Rate' => $this->bsf->isNullCheck($postData['Rate_'.$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $coefficientCount = $postData['CoefficientCount'];
                    for($i=1;$i<=$coefficientCount;$i++) {
                        if($this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCoefficient');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'SerialNo' => $this->bsf->isNullCheck($postData['SerialNo_'.$i],'string')
                            , 'ItemOfWork' => $this->bsf->isNullCheck($postData['ItemOfWork_'.$i],'string')
                            , 'ResourceId' => $this->bsf->isNullCheck($postData['ResourcesIOWId_'.$i],'number')
                            , 'Coefficient' => $this->bsf->isNullCheck($postData['Coefficient_'.$i],'number')
                            , 'UnitId' => $this->bsf->isNullCheck($postData['CoUnitId_'.$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $makeBrandCount = $postData['MakeBrandCount'];
                    for($i=1;$i<=$makeBrandCount;$i++) {
                        if($this->bsf->isNullCheck($postData['MaterialName_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryMakeBrand');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'MaterialName' => $this->bsf->isNullCheck($postData['MaterialName_'.$i],'string')
                            , 'Make' => $this->bsf->isNullCheck($postData['Make_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $shortNotesCount = $postData['ShortNotesCount'];
                    for($i=1;$i<=$shortNotesCount;$i++) {
                        if($this->bsf->isNullCheck($postData['Title_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryShortNotes');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'Title' => $this->bsf->isNullCheck($postData['Title_'.$i],'string')
                            , 'Specifications' => $this->bsf->isNullCheck($postData['Specifications_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $checkListCount = count($postData['CheckList']);
                    for($i=0;$i<$checkListCount;$i++) {
                        if($this->bsf->isNullCheck($postData['CheckList'][$i],'number') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCheckList');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'CheckListId' => $this->bsf->isNullCheck($postData['CheckList'][$i],'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $newCheckListCount = $postData['CheckListCount'];
                    for($i=1;$i<=$newCheckListCount;$i++) {
                        if($this->bsf->isNullCheck($postData['NewCheckList_'.$i],'string') != "") {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $this->bsf->isNullCheck($postData['NewCheckList_'.$i],'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $checkListId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $insert = $sql->insert();
                            $insert->into('Proj_EnquiryCheckList');
                            $insert->Values(array('TechnicalSpecificationId' => $this->bsf->isNullCheck($techSpecId,'number')
                            , 'CheckListId' => $this->bsf->isNullCheck($checkListId,'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $select = $sql->select();
        $select->from('Proj_CheckListMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->checkLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Enquiry Name as Name of Work
        $select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->where(array('TenderEnquiryId' => $enquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->NameOfWork = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        $enquiryName = "";
        if (!empty($NameOfWork)) $enquiryName = $NameOfWork["NameOfWork"];
        $this->_view->EnquiryName = $enquiryName;

        $this->_view->enquiryFollowupId = $enquiryFollowupId;
        $this->_view->enquiryId = $enquiryId;


        //autocomplete tables
//        $select = $sql->select();
//        $select->from('Proj_Resource')
//            ->columns(array("data"=>'ResourceId',"value"=> new Expression("Code + ' ' +ResourceName")))
//            ->where(array("DeleteFlag" => '0'));
//        $statement = $sql->getSqlStringForSqlObject($select);
//        $this->_view->reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a'=>'Proj_Resource'))
            ->columns(array("data"=>'ResourceId',"value"=> new Expression("Code + ' ' +ResourceName"),"Rate"=>'Rate'))
//            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array("ResourceName" => new Expression("Code + ' ' + ResourceName")), $select::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName","UnitId"), $select::JOIN_INNER)
            ->where(array("DeleteFlag" => '0'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $select = $sql->select();
        $select->from('Proj_Resource')
            ->columns(array("data"=>'ResourceId',"value"=> new Expression("Code + ' ' +ResourceName")))
            ->where(array("DeleteFlag" => '0','TypeId'=> '2'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resMaterial = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_IOWMaster')
            ->columns(array("data"=>'IOWId',"value"=> new Expression("SerialNo + ' ' +Specification ")))
            ->where(array("DeleteFlag" => '0'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resiowlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_UOM')
            ->columns(array("data" => 'UnitId', "value" => 'UnitName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        return $this->_view;
//		}
//        else {
//			$this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
//		}
    }

    public function documentPurchaseAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $enquiryId = $this->params()->fromRoute('enquiryId');
        $EnquiryCallTypeId = $this->params()->fromRoute('EnquiryCallTypeId');
        $Date =  date('d-m-Y');
        $EditEnquiryFollowupId = $this->bsf->isNullCheck($this->params()->fromRoute('EnquiryFollowupId'),'number');
        if($EditEnquiryFollowupId == 0) {
            $select = $sql->select();
            $select->from('Proj_EnquiryFollowup')
                ->columns(array('EnquiryFollowupId'))
                ->where(array("TenderEnquiryId=$enquiryId and CallTypeId=$EnquiryCallTypeId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $EnquiryFollowupId = $resContractMeeting = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $EditEnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId['EnquiryFollowupId'], 'number');
        }
        if($EditEnquiryFollowupId != 0) {
            $select = $sql->select();
            $select->from(array('a'=>'Proj_EnquiryDocumentPurchase'))
                ->join(array('b' => 'Proj_ContractDocumentTypeMaster'), 'a.DocumentTypeId=b.DocumentTypeId', array('DocumentTypeName'), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array('UserName'), $select::JOIN_LEFT)
                ->where(array('EnquiryFollowupId' =>$EditEnquiryFollowupId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->EditDocumentPurchase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from(array('a'=>'Proj_DocumentPurchaseTrans'))
                ->join(array('b' => 'Proj_ContractDocumentTypeMaster'), 'a.DocumentTypeId=b.DocumentTypeId', array('DocumentTypeName'), $select::JOIN_LEFT)
                ->where(array('EnquiryFollowupId' =>$EditEnquiryFollowupId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->DocumentPurchaseTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        }
        //if($enquiryFollowupId != '') {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();
                $enquiryFollowupId = $this->bsf->isNullCheck($postData['EnquiryFollowupId'],'number');
//					echo '<pre>'; print_r($postData); die;

//					$enquiryFollowupId = $postData['EnquiryFollowupId'];

//					$PurchaseDate = NULL;
                if($postData['PurchaseDate']) {
                    $PurchaseDate = date('Y-m-d', strtotime($postData['PurchaseDate']));
                }
                if($EditEnquiryFollowupId != 0) {

                    $select = $sql->delete();
                    $select->from("Proj_DocumentPurchaseTrans")
                        ->where(array('EnquiryFollowupId'=>$EditEnquiryFollowupId));
                    $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
                    $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_EnquiryDocumentPurchase');
                    $update->set(array(
//                            'DocumentTypeId' => $this->bsf->isNullCheck($postData['DocumentTypeId'],'number'),
                        'DocumentName' => $this->bsf->isNullCheck($postData['DocumentName'],'string')
                    , 'DocumentCost' => $this->bsf->isNullCheck($postData['DocumentCost'],'number')
                    , 'PurchaseDate' => $PurchaseDate
                    , 'UserId' => $this->bsf->isNullCheck($postData['HandOverId'],'number')
                    , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'],'string')))
                        ->where(array('EnquiryFollowupId'=>$EditEnquiryFollowupId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        $enquiryDocumentPurchaseId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $DocumentTypeId=$postData['DocumentType'];

                    if(count($DocumentTypeId) > 0){

                        foreach ($DocumentTypeId as $value){
                            $select = $sql->insert('Proj_DocumentPurchaseTrans');
                            $newData = array(
                                'EnquiryFollowupId' => $EditEnquiryFollowupId,
                                'DocumentTypeId'=> $value,
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }}
                } else {
                    $RefDate = date('Y-m-d', strtotime($Date));

                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryFollowup');
                    $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                    , 'CallTypeId' => $this->bsf->isNullCheck($EnquiryCallTypeId,'number')
                    , 'CreatedDate' => date('Y-m-d H:i:s')
                    , 'RefDate' => $RefDate));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $insert = $sql->insert();
                    $insert->into('Proj_EnquiryDocumentPurchase');
                    $insert->Values(array('EnquiryFollowupId' => $this->bsf->isNullCheck($enquiryFollowupId,'number')
//                        , 'DocumentTypeId' => $this->bsf->isNullCheck($postData['DocumentTypeId'],'number')
                    , 'DocumentName' => $this->bsf->isNullCheck($postData['DocumentName'],'string')
                    , 'DocumentCost' => $this->bsf->isNullCheck($postData['DocumentCost'],'number')
                    , 'PurchaseDate' => date('Y-m-d', strtotime($PurchaseDate))
                    , 'UserId' => $this->bsf->isNullCheck($postData['HandOverId'],'number')
                    , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'],'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $enquiryDocumentPurchaseId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $DocumentTypeId=$postData['DocumentType'];

                    if(count($DocumentTypeId) > 0){

                        foreach ($DocumentTypeId as $value){
                            $select = $sql->insert('Proj_DocumentPurchaseTrans');
                            $newData = array(
                                'EnquiryFollowupId' => $enquiryFollowupId,
                                'DocumentTypeId'=> $value,
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }}
                    /*$update = $sql->update();
                    $update->table('Proj_TenderEnquiry');
                    $update->set(array('DocumentPurchase' => $enquiryDocumentPurchaseId))
                        ->where(array('TenderEnquiryId'=>$enquiryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                    $select = $sql->select();
                    $select->from('Proj_TenderProcessMaster')
                        ->columns(array('TenderProcessId'))
                        ->where(array('ProcessName' => 'Document-Purchase'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $processId = $processName['TenderProcessId'];

                    $update = $sql->update();
                    $update->table('Proj_TenderProcessTrans')
                        ->set(array('Flag' => 1))
                        ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
        $this->_view->enquiryId = $enquiryId;
        $this->_view->EnquiryCallTypeId = $EnquiryCallTypeId;
        $this->_view->Date= $Date;

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        //Auto Complete table
        //Document Type
        $select = $sql->select();
        $select->from('Proj_ContractDocumentTypeMaster')
            ->columns(array("data"=>'DocumentTypeId',"value"=> new Expression("DocumentTypeName")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resDocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Username / HandOvert to
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array("data"=>'UserId',"value"=> new Expression("UserName")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resUsers = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->columns(array("NameOfWork"))
            ->where(array('TenderEnquiryId'=>$enquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $NameOfWork = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $enquiryName ="";
        if (!empty($NameOfWork)) $enquiryName=$NameOfWork['NameOfWork'];
        $this->_view->EnquiryName =$enquiryName;

        return $this->_view;
    }

    public function documentsAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

//					echo '<pre>'; print_r($postData); die;

                $enquiryFollowupId = $postData['EnquiryFollowupId'];


                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
        /*} else {
            $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
        }*/
    }

    public function quotationSubmitAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

//					'<pre>'; print_r($postData); die;

                $enquiryFollowupId = $postData['EnquiryFollowupId'];


                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
        /*} else {
            $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
        }*/
    }

    public function bidWinAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

                echo '<pre>'; print_r($postData); die;

                $enquiryFollowupId = $postData['EnquiryFollowupId'];


                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
        /*} else {
            $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
        }*/
    }

    public function assignChecklistAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

                echo '<pre>'; print_r($postData); die;

                $enquiryFollowupId = $postData['EnquiryFollowupId'];


                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
        /*} else {
            $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
        }*/
    }

    public function checklistActionAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionEnquiryFollowup = new Container('sessionEnquiryFollowup');
        $enquiryFollowupId = $sessionEnquiryFollowup->enquiryId;

        //if($enquiryFollowupId != '') {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

//					echo '<pre>'; print_r($postData); die;

                $enquiryFollowupId = $postData['EnquiryFollowupId'];


                $connection->commit();
                $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $this->_view->enquiryFollowupId = $enquiryFollowupId;

        return $this->_view;
        /*} else {
            $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup'));
        }*/
    }

    public function registerAction(){
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

        $select = $sql->select();
        $select->from('Proj_ContractCallTypeMaster')
            ->columns(array('CallTypeId', 'CallTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiryCallType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

//        $this->_view->enquiryCallType = $this->bsf->getEnquiryCallType();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'),'string');
                if($Type == 'Load') {
                    //Write your Ajax post code here
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_EnquiryFollowup'))
                        ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_EnquiryDocumentPurchase'), 'a.EnquiryFollowupId=c.EnquiryFollowupId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_ContractCallTypeMaster'), 'a.CallTypeId=d.CallTypeId', array('CallTypeName'), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag'=> '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $results = json_encode($results);
                    $response = $this->getResponse()->setStatusCode(200)->setContent($results);
                    return $response;
                } else {
                    $postParam = $request->getPost();
//                //Delete FollowUp

                    $postParam = $request->getPost();
                    $update = $sql->update();
                    $update->table('Proj_EnquiryFollowup');
                    $update->set(array('DeleteFlag' => '1'));
                    $update->where(array('EnquiryFollowupId'=>$postParam['id']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $results = json_encode($results);
                    $response = $this->getResponse()->setContent($results);
                    return $response;
                }
//				$this->_view->setTerminal(true);
//				$response = $this->getResponse()->setContent($result);

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Post Code

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