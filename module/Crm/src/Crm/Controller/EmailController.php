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

class EmailController extends AbstractActionController
{
    public function __construct()
    {
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function emailTemplateAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                $result="";
                if ($mode == 'tempType') {
                    $tempType = $this->bsf->isNullCheck($postData['tempTypeVal'], 'number');
                    $mergeTags = $this->bsf->getEmailMergeTags($tempType);
                    $result = json_encode($mergeTags);
                } else if($mode=="check") {
                    $tempId = $this->bsf->isNullCheck($postData['tempId'], 'number');
                    $tempTypeId = $this->bsf->isNullCheck($postData['tempTypeId'], 'number');
                    if($tempId!=0) {
                        $select = $sql->select();
                        $select->from('WF_EmailTemplate')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array("TemplateName" => $postData['tempName'],"DeleteFlag"=>0,"TemplateTypeId"=>$tempTypeId));
                        $select->where("EmailTemplateId <> '".$tempId."'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result = json_encode($result);
                    } else {
                        $select = $sql->select();
                        $select->from('WF_EmailTemplate')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array("TemplateName" => $postData['tempName'],"DeleteFlag"=>0,"TemplateTypeId"=>$tempTypeId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result = json_encode($result);
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $defaultTemplate = $this->bsf->isNullCheck($postData['default_template'], 'number');
                    $templateTypeId = $this->bsf->isNullCheck($postData['templateType'], 'number');
                    $mode = $this->bsf->isNullCheck($postData['mode'], 'number');
                    if($mode==0){
                        if($defaultTemplate==1){
                            $update = $sql->update();
                            $update->table('WF_EmailTemplate');
                            $update->set(array('DefaultTemplate' => 0));
                            $update->where(array("TemplateTypeId"=>$templateTypeId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('WF_EmailTemplate');
                            $insert->Values(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                            , 'TemplateTypeId' => $this->bsf->isNullCheck($postData['templateType'], 'number')
                            , 'TemplateContent' => htmlentities($this->bsf->isNullCheck($postData['templateContent'], 'string'))
                            , 'DefaultTemplate' => $defaultTemplate
                            ,'CreatedDate'=>date('Y-m-d H:i:s')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->select();
                            $select->from('WF_EmailTypeMaster')
                                ->columns(array('EmailTypeId', 'EmailTemplateName'))
                            ->where(array("EmailTypeId"=>$templateTypeId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $templateName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $res = $viewRenderer->MandrilSendMail()->UpdateTemplate( $templateName['EmailTemplateName'], $postData['templateContent']);


                        } else {
                            $select = $sql->select();
                            $select->from("WF_EmailTemplate")
                                ->columns(array('DefaultTemplate'))
                                ->where(array("TemplateTypeId"=>$templateTypeId,"DefaultTemplate"=>1));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $temCount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if(count($temCount)==0) {
                                $insert = $sql->insert();
                                $insert->into('WF_EmailTemplate');
                                $insert->Values(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                                , 'TemplateTypeId' => $this->bsf->isNullCheck($postData['templateType'], 'number')
                                , 'TemplateContent' => htmlentities($this->bsf->isNullCheck($postData['templateContent'], 'string'))
                                , 'DefaultTemplate' => 1
                                ,'CreatedDate'=>date('Y-m-d H:i:s')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $select = $sql->select();
                                $select->from('WF_EmailTypeMaster')
                                    ->columns(array('EmailTypeId', 'EmailTemplateName'))
                                    ->where(array("EmailTypeId"=>$templateTypeId));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $templateName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $res = $viewRenderer->MandrilSendMail()->UpdateTemplate( $templateName['EmailTemplateName'], $postData['templateContent']);

                            } else {
                                $insert = $sql->insert();
                                $insert->into('WF_EmailTemplate');
                                $insert->Values(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                                , 'TemplateTypeId' => $this->bsf->isNullCheck($postData['templateType'], 'number')
                                , 'TemplateContent' => htmlentities($this->bsf->isNullCheck($postData['templateContent'], 'string'))
                                , 'DefaultTemplate' => $defaultTemplate
                                ,'CreatedDate'=>date('Y-m-d H:i:s')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    } else {
                        if($defaultTemplate==1){
                            $update = $sql->update();
                            $update->table('WF_EmailTemplate');
                            $update->set(array('DefaultTemplate' => 0));
                            $update->where(array("TemplateTypeId"=>$templateTypeId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $update = $sql->update();
                            $update->table('WF_EmailTemplate');
                            $update->set(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                            , 'TemplateContent' => htmlentities($this->bsf->isNullCheck($postData['templateContent'], 'string'))
                            , 'DefaultTemplate' => $defaultTemplate
                            ,'ModifiedDate'=>date('Y-m-d H:i:s')));
                            $update->where(array("EmailTemplateId"=>$mode));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->select();
                            $select->from('WF_EmailTypeMaster')
                                ->columns(array('EmailTypeId', 'EmailTemplateName'))
                                ->where(array("EmailTypeId"=>$templateTypeId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $templateName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $res = $viewRenderer->MandrilSendMail()->UpdateTemplate( $templateName['EmailTemplateName'], $postData['templateContent']);


                        } else {
                            $update = $sql->update();
                            $update->table('WF_EmailTemplate');
                            $update->set(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                            , 'TemplateContent' => htmlentities($this->bsf->isNullCheck($postData['templateContent'], 'string'))
                            , 'DefaultTemplate' => $defaultTemplate
                            ,'ModifiedDate'=>date('Y-m-d H:i:s')));
                            $update->where(array("EmailTemplateId"=>$mode));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    $this->redirect()->toRoute('crm/default', array('controller' => 'email', 'action' => 'email-template-register'));

                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                try {
                    $emailTempId = $this->bsf->isNullCheck($this->params()->fromRoute('emailTempId'), 'number');

                    if($emailTempId!=0) {
                        $select = $sql->select();
                        $select->from('WF_EmailTemplate')
                            ->columns(array('*'))
                            ->where(array("EmailTemplateId"=> $emailTempId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $emailTempDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->mergeTags = $this->bsf->getEmailMergeTags($emailTempDetail['TemplateTypeId']);
                        $this->_view->emailTempDetail=$emailTempDetail;
                    }

                    $select = $sql->select();
                    $select->from('WF_EmailTypeMaster')
                        ->columns(array('EmailTypeId', 'EmailTypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->templateTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Common function
                    $this->_view->mode=$emailTempId;
                    $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                    return $this->_view;
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }
        }
    }

	public function emailTemplateRegisterAction(){
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
            $response = $this->getResponse();
			if ($request->isPost()) {
                try {
                    $postData = $request->getPost();

                    $templateTypeId = $this->bsf->isNullCheck($postData['EmailTemplateId'], 'number');
                    $Remarks = $this->bsf->isNullCheck($postData['Remarks'], 'number');

                    $update = $sql->update();
                    $update->table( 'WF_EmailTemplate' )
                        ->set( array( 'DeleteFlag' => 1
                        ,'ModifiedDate'=>date('Y-m-d H:i:s')
                        ,'Remarks'=>$Remarks))
                        ->where( array( 'EmailTemplateId' =>$templateTypeId ) );
                    $statement = $sql->getSqlStringForSqlObject( $update );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    //Write your Ajax post code here
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent('success');
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
                return $response;
			} else {
            // GET request
            $response->setStatusCode(405);
            $response->setContent('Method not allowed!');
        }
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			} else {
                try {
                    $select = $sql->select();
                    $select->from(array('a'=>'WF_EmailTemplate'))
                        ->join(array('b' => 'WF_EmailTypeMaster' ), 'a.TemplateTypeId=b.EmailTypeId', array('EmailTypeName'), $select::JOIN_LEFT )
                        ->columns(array('*'))
                        ->where(array("a.DeleteFlag"=> 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->emailTempDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Common function
                    $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                    return $this->_view;
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }
		}
	}
}