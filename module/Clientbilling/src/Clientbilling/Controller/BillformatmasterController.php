<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Clientbilling\Controller;

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

class BillformatmasterController extends AbstractActionController
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

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Bill Format");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
            $this->_view->setTerminal(true);
			if ($request->isPost()) {
                $postParams = $request->getPost();
                $type = $this->bsf->isNullCheck($postParams['type'], 'string');

                switch($type) {
                    case "billformat":
                        $woId = $this->bsf->isNullCheck($postParams['woId'], 'number');
                        $select = $sql->select();
                        if ($woId == 0) {
                            $select->from(array('a' => 'CB_BillFormatTemplate'))
                                ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                                ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'));
                        } else {
                            $select->from(array('a' => 'CB_BillFormatTrans'))
                                ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                                ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'))
                                ->where(array("WorkOrderId=$woId"));
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response = $this->getResponse()->setContent(json_encode($result));
                        break;
                    case "billformattrans":
                        $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');

                        $select = $sql->select();
                        $select->from(array('a' => 'CB_BillFormatTrans'))
                            ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                            ->columns(array('BillFormatTransId','BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'))
                            ->where(array("CostCentreId=$CostCentreId"))
                            -> order('SortId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response = $this->getResponse()->setContent(json_encode($result));
                        break;
                    case "default":
                        $response = $this->getResponse()->setStatusCode(400)->setContent('Bad Request');
                        break;
                }
				return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $postData = $request->getPost();
                try {
                    $billformatrowid = $this->bsf->isNullCheck($postData['billformatrowid'], 'number');
                    $costCentreId = $this->bsf->isNullCheck($postData['costCentreId'], 'number');
                    if($costCentreId == 0 || $billformatrowid == 1)
                        $this->redirect()->toRoute('clientbilling/default', array('controller' => 'billformatmaster', 'action' => 'index'));

                    $connection->beginTransaction();

                    $deleteids = rtrim($this->bsf->isNullCheck($postData['billformatrowdeleteids'],'string'), ",");
                    if($deleteids !== '' && $deleteids != '0') {
                        $delete = $sql->delete();
                        $delete->from('CB_BillFormatTrans')
                            ->where("BillFormatTransId IN ($deleteids)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    for ($i = 1; $i <= $billformatrowid; $i++) {
                        $transId = $this->bsf->isNullCheck($postData['transId_' . $i], 'number');
                        $billformatid = $this->bsf->isNullCheck($postData['billformatid_' . $i], 'number');
                        $billformatsortid = $this->bsf->isNullCheck($postData['billformatsortid_' . $i], 'number');
                        $desc = $this->bsf->isNullCheck($postData['billformatdesc_' . $i], 'string');
                        $formula = $this->bsf->isNullCheck($postData['formula_' . $i], 'string');
                        $slno = $this->bsf->isNullCheck($postData['slno_' . $i], 'string');

                        $check_bold = isset($postData['textbold_' . $i]) ? 1 : 0;
                        $check_italic = isset($postData['textitalic_' . $i]) ? 1 : 0;
                        $check_underline = isset($postData['textunderline_' . $i]) ? 1 : 0;

                        if ($billformatid == 0 && $desc == '')
                            continue;

                        if($transId != 0) {
                            $update = $sql->update('CB_BillFormatTrans');
                            $update->set(array('CostCentreId' => $costCentreId, 'BillFormatId' => $billformatid, 'SortId' => $billformatsortid, 'Description' => $desc
                            , 'Formula' => $formula, 'Slno' => $slno, 'Bold' => $check_bold, 'Italic' => $check_italic, 'Underline' => $check_underline));
                            $update->where("BillFormatTransId=$transId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $insert = $sql->insert();
                            $insert->into('CB_BillFormatTrans');
                            $insert->Values(array('CostCentreId' => $costCentreId, 'BillFormatId' => $billformatid, 'SortId' => $billformatsortid, 'Description' => $desc
                            , 'Formula' => $formula, 'Slno' => $slno, 'Bold' => $check_bold, 'Italic' => $check_italic, 'Underline' => $check_underline));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute('clientbilling/default', array('controller' => 'billformatmaster', 'action' => 'index'));
                } catch(PDOException $e){
                    $connection->rollback();
                }
            } else {

            }

            // Bill Format Master
            $select = $sql->select();
            $select->from('CB_BillFormatMaster')
                ->columns(array("data" => 'BillFormatId', "rowname" => 'RowName', "value" => 'TypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_billformatmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where(array('WORegisterId <>0','Deactivate'=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}
}