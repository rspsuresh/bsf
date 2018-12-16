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

class BankController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function bankinfoAction(){
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
        $BranchId = $this->bsf->isNullCheck($this->params()->fromRoute('branchId'), 'number');
//        if(!$this->getRequest()->isXmlHttpRequest() && $BranchId == 0 && !$request->isPost()) {
//            $this->redirect()->toRoute('crm/default', array('controller' => 'bank','action' => 'bankinfo'));
//        }

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();

                switch($Type) {
                    case 'selectState':

                    $CityId = $this->bsf->isNullCheck($this->params()->fromPost('cityid'), 'number');
                    $select = $sql -> select();
                    $select -> from (array('a'=>'WF_CityMaster'))
                        ->columns(array('StateName'=>new Expression('b.StateName'),'CountryName'=>new Expression('c.CountryName')))
                        ->join(array('b'=>'WF_StateMaster'),'a.StateId=b.StateId',array(),$select::JOIN_INNER)
                        ->join(array('c'=>'WF_CountryMaster'),'b.CountryId=c.CountryId',array(),$select::JOIN_INNER)
                        ->where('a.CityId='.$CityId.'');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $stateList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $response->setStatusCode('200');
                    $this->_view->setTerminal(true);
                    $response->setContent(json_encode($stateList));
                    return $response;
                    break;
                 case 'selectBank':

                    $BranchId=$this->bsf->isNullCheck($this->params()->fromPost('branchid'),'number');
                    $BankName= $this->bsf->isNullCheck($this->params()->fromPost('bankname'),'string');
                     $selBName = $sql -> select();
                     $selBName -> from (array('a'=>'Crm_BankMaster'))
                         ->columns(array('BankName'=>new Expression('a.BankName')))
                         ->where(array('BankName'=>$BankName));
                     $statement = $sql->getSqlStringForSqlObject($selBName);
                     $bankNList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $bnCount=count($bankNList);
                    if($bnCount > 0){
                        $response->setStatusCode('400');
                        return $response;
                        break;
                    }
                    else {
                        if($this->bsf->isNullCheck($BankName,'string'))
                        if ($BranchId > 0) {
                            $bankUpdate = $sql->update();
                            $bankUpdate->table('Crm_BankMaster');
                            $bankUpdate->set(array('BankName' => $BankName));
                            $bankUpdate->where(array('BankId' => $BranchId));
                            $updBankStatement = $sql->getSqlStringForSqlObject($bankUpdate);
                            $dbAdapter->query($updBankStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        } else {
                            $bankInsert = $sql->insert('Crm_BankMaster');
                            $bankInsert->values(array("BankName" => $BankName));
                            $insertBankStatement = $sql->getSqlStringForSqlObject($bankInsert);
                            $dbAdapter->query($insertBankStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $BranchId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }
                        $response->setStatusCode('200');
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($BranchId));

                        return $response;
                        break;
                    }
                 case 'deleteBank':

                     $BankId=$this->bsf->isNullCheck($this->params()->fromPost('bankid'),'number');
                     $selBank = $sql -> select(array("a"=>"Crm_BankDetails"))
                            ->columns(array('BankId'=>new Expression('a.BankId')))
                            ->where(array('BankId'=>$BankId));
                     $statement = $sql->getSqlStringForSqlObject($selBank);
                     $bankList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                     $bCount=count($bankList);

                     if($bCount > 0)
                     {
                         $response->setStatusCode('400');
                         return $response;
                         break;
                     }
                     else{
                         $delBank = $sql->delete();
                         $delBank->from("Crm_BankMaster")
                             ->where(array('BankId'=>$BankId));
                        $delBankStatement = $sql->getSqlStringForSqlObject($delBank);
                         $dbAdapter->query($delBankStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                         $response->setStatusCode('200');
                         $this->_view->setTerminal(true);

                         return $response;
                         break;
                     }
                  case 'refreshBank':

                      $select = $sql -> select();
                      $select ->from (array('a' => 'Crm_BankMaster'))
                          ->columns(array('data'=>new Expression('a.BankId') ,'value'=>new Expression('a.BankName')))
                          ->where('Deactivate=0');
                      $statement = $sql->getSqlStringForSqlObject( $select );
                      $bankList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                      $response->setStatusCode('200');
                      $this->_view->setTerminal(true);
                      $response->setContent(json_encode($bankList));
                      return $response;
                      break;
                }

				//Write your Ajax post code here

			}
            else
            {

            }
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postParams = $request->getPost();
				//Write your Normal form post code here
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if(isset($BranchId) && $BranchId!='') {
                        $delBranch = $sql -> delete();
                        $delBranch->from('Crm_BankDetails')
                            ->where(array("BranchId"=>$BranchId));
                        $delStatement = $sql->getSqlStringForSqlObject($delBranch);
                        $dbAdapter->query($delStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $CityId = $this->bsf->isNullCheck($postParams['city'], 'number');
                    $selState = $sql -> select();
                    $selState->from(array("a"=>"WF_CityMaster"))
                        ->columns(array('StateId','CountryId'))
                        ->where('CityId='.$CityId.'');
                    $statement = $sql->getSqlStringForSqlObject($selState);
                    $stateid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $bankInsert = $sql -> insert('Crm_BankDetails');
                    $bankInsert->values(array("BankId"=>$postParams['bankname'],"BranchName"=>$postParams["branch"],
                    "Address1"=>$postParams['address1'],"Address2"=>$postParams['address2'],'CityId'=>$postParams['city'],
                    "StateId"=>$stateid['StateId'],"CountryId"=>$stateid['CountryId'],"PinCode"=>$postParams['pincode'],
                    "Mobile"=>$postParams['mobile'],"Phone"=>$postParams['phone'],"Fax"=>$postParams['fax'],
                    "ContactPerson"=>$postParams['contactperson'],"IFSCCode"=>$postParams['ifsccode'],
                    "MICRCode"=>$postParams['micrcode']));
                    $bankStatement = $sql->getSqlStringForSqlObject($bankInsert);
                    $dbAdapter->query($bankStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                $connection->commit();
                $this->redirect()->toRoute('crm/default', array('controller' => 'bank', 'action' => 'bankregister'));
			}
			//begin trans try block example ends
			
			//Common function
            else
            {
                $postData = $request->getPost();
                if(isset($BranchId) && $BranchId!='') {
                    $regSelect = $sql->select();
                    $regSelect->from(array("a"=>"Crm_BankDetails"))
                        ->columns(array('BranchId'=>new Expression('a.BranchId'),'BankId'=>new Expression('a.BankId'),'Bank'=>new Expression('b.BankName'),
                            'Branch'=>new Expression('a.BranchName'),'CityId'=>new Expression('a.CityId'),
                            'Address1'=>new Expression('a.Address1'),'Address2'=>new Expression('a.Address2'),'City'=>new Expression('c.CityName'),
                            'PinCode'=>new Expression('a.PinCode'),'State'=>new Expression('d.StateName'),
                            'Phone'=>new Expression('a.Phone'),'Fax'=>new Expression('a.Fax'),
                            'Country'=>new Expression('e.CountryName'),'IFSCCode'=>new Expression('a.IFSCCode'),
                            'MICRCode'=>new Expression('a.MICRCode'),
                            'Mobile'=>new Expression('a.Mobile'),'ContactPerson'=>new Expression('a.ContactPerson')))
                        ->join(array("b"=>"Crm_BankMaster"), "a.BankId=b.BankId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("c"=>"WF_CityMaster"),"a.CityId=c.CityId",array(),$regSelect::JOIN_LEFT)
                        ->join(array("d"=>"WF_StateMaster"),"c.StateId=d.StateId",array(),$regSelect::JOIN_LEFT)
                        ->join(array("e"=>"WF_CountryMaster"),"d.CountryId=e.CountryId",array(),$regSelect::JOIN_LEFT)
                    ->where('a.BranchId='.$BranchId.'');
                    $statement = $sql->getSqlStringForSqlObject( $regSelect );
                    $this->_view->bankdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->bankid = $this->_view->bankdetails['BankId'];
                    $this->_view->branchname = $this->_view->bankdetails['Branch'];
                    $this->_view->address1 = $this->_view->bankdetails['Address1'];
                    $this->_view->address2 = $this->_view->bankdetails['Address2'];
                    $this->_view->cityid = $this->_view->bankdetails['CityId'];
                    $this->_view->state = $this->_view->bankdetails['State'];
                    $this->_view->country = $this->_view->bankdetails['Country'];
                    $this->_view->pincode = $this->_view->bankdetails['PinCode'];
                    $this->_view->mobile = $this->_view->bankdetails['Mobile'];
                    $this->_view->phone = $this->_view->bankdetails['Phone'];
                    $this->_view->fax = $this->_view->bankdetails['Fax'];
                    $this->_view->contactperson = $this->_view->bankdetails['ContactPerson'];
                    $this->_view->ifsccode = $this->_view->bankdetails['IFSCCode'];
                    $this->_view->micrcode = $this->_view->bankdetails['MICRCode'];
                }
            }

//Common function
            $select = $sql -> select();
            $select ->from (array('a' => 'Crm_BankMaster'))
                ->columns(array('BankId','BankName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_bank = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql -> select();
            $select ->from (array('a' => 'WF_CityMaster'))
                ->columns(array('CityId','CityName'));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_city = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql -> select();
            $select ->from (array('a' => 'Crm_BankMaster'))
                ->columns(array('BankId','BankName'));
           $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_bankmaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
			

		}
	}

    public function bankregisterAction(){
        if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest()){
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
            $resp = array();
            if($request->isPost()){
                $postParam = $request->getPost();
                if($postParam['mode'] == 'first'){
                    $regSelect = $sql->select();
                    $regSelect->from(array("a"=>"Crm_BankDetails"))
                        ->columns(array('BranchId'=>new Expression('a.BranchId'),'Bank'=>new Expression('b.BankName'),
                            'Branch'=>new Expression('a.BranchName'),
                            'Address'=>new Expression('a.Address1'),'City'=>new Expression('c.CityName'),
                            'State'=>new Expression('d.StateName'),'Country'=>new Expression('e.CountryName'),
                            'Mobile'=>new Expression('a.Mobile'),'ContactPerson'=>new Expression('a.ContactPerson')))
                        ->join(array("b"=>"Crm_BankMaster"), "a.BankId=b.BankId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("c"=>"WF_CityMaster"),"a.CityId=c.CityId",array(),$regSelect::JOIN_LEFT)
                        ->join(array("d"=>"WF_StateMaster"),"c.StateId=d.StateId",array(),$regSelect::JOIN_LEFT)
                        ->join(array("e"=>"WF_CountryMaster"),"d.CountryId=e.CountryId",array(),$regSelect::JOIN_LEFT)
                        ->where(array('a.DeleteFlag'=>0));
                     $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if($request->isPost()){

        }
        return $this->_view;
    }
    public function DeleteAction(){
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
                    $BankId = $this->bsf->isNullCheck($this->params()->fromPost('BankId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');


                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                        $update = $sql->update();
                        $update->table('Crm_BankDetails')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeletedRemarks' => $Remarks))
                            ->where(array('BankId' => $BankId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Bank-Delete','D','BankInfo',$BankId,0, 0, 'CRM', '',$userId, 0 ,0);


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
}