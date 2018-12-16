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

class ReportController extends AbstractActionController
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

    public function projectwisesalesrptAction(){
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
        $sql = new Sql( $dbAdapter );

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                $asonDate= date('Y-m-d', strtotime($PostDataStr))." 23:59:59";
                $select = $sql->select();
                switch($RType) {
                    case 'getLoad':

                        $subQuery = $sql->select();
                        $subQuery->from("KF_UnitMaster")
                            ->columns(array('ProjectId'))
                            ->where("DeleteFlag='0' and CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("Proj_ProjectMaster")
                            ->columns(array('ProjectId','ProjectName'))
                            ->where->expression('ProjectId IN ?', array($subQuery));
                             $select->order("ProjectId Desc");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $arrUnitLists= array();
                        $i=0;
                        $ParId=0;
                        foreach($projList as &$projLists) {
                            $i=$i+1;
                            $ParId=0;

                            $ProjectId=$projLists['ProjectId'];
                            //Count unit
                            $select = $sql->select();
                            $select->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and CreatedDate<= '$asonDate'");

                            $selectUnsoldunit = $sql->select();
                            $selectUnsoldunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='U' and CreatedDate<= '$asonDate'");
                            $selectUnsoldunit->combine($select,'Union ALL');

                            $selectSoldunit = $sql->select();
                            $selectSoldunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='S' and CreatedDate<= '$asonDate'");
                            $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                            $selectBlockunit = $sql->select();
                            $selectBlockunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='B' and CreatedDate<= '$asonDate'");
                            $selectBlockunit->combine($selectSoldunit,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectBlockunit))
                                ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            //Area
                            $selectSoldarea = $sql->select();
                            $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                                ->join(array("c"=>"Crm_PostSaleDiscountRegister"),new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                                ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                                ->group(new Expression('c.PostSaleDiscountId '));

                            $selectUnSoldarea = $sql->select();
                            $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                                ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                            $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectUnSoldarea))
                                ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unitAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $j=$i;
                            $avgRate=0;
                            $totAmt=$unitAreadet['SoldAmt'] + $unitAreadet['UnsoldAmt'];
                            $totArea=$unitAreadet['SoldArea'] + $unitAreadet['UnsoldArea'];
                            if($totAmt!=0 || $totArea!=0 )
                            {
                                $avgRate=$totAmt/$totArea;
                            }

                            //Block
                            $subQuery = $sql->select();
                            $subQuery->from("KF_UnitMaster")
                                ->columns(array('BlockId'))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and CreatedDate<= '$asonDate'");

                            $select = $sql->select();
                            $select->from("KF_BlockMaster")
                                ->columns(array('BlockId','BlockName'))
                                ->where->expression('BlockId IN ?', array($subQuery));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($blockList as &$blockLists) {
                                $BlockId=$blockLists['BlockId'];
                                $i=$i+1;
                                $k=$i;

                                //Count unit
                                $select = $sql->select();
                                $select->from("KF_UnitMaster")
                                    ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and CreatedDate<= '$asonDate'");

                                $selectUnsoldunit = $sql->select();
                                $selectUnsoldunit->from("KF_UnitMaster")
                                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='U' and CreatedDate<= '$asonDate'");
                                $selectUnsoldunit->combine($select,'Union ALL');

                                $selectSoldunit = $sql->select();
                                $selectSoldunit->from("KF_UnitMaster")
                                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='S' and CreatedDate<= '$asonDate'");
                                $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                                $selectBlockunit = $sql->select();
                                $selectBlockunit->from("KF_UnitMaster")
                                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='B' and CreatedDate<= '$asonDate'");
                                $selectBlockunit->combine($selectSoldunit,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectBlockunit))
                                    ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $blockdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                //Area
                                $selectSoldarea = $sql->select();
                                $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                                    ->join(array("c"=>"Crm_PostSaleDiscountRegister"), new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                                    ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                                    ->group(new Expression('c.PostSaleDiscountId '));

                                $selectUnSoldarea = $sql->select();
                                $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                                    ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                                    ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                                $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectUnSoldarea))
                                    ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $blockAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $blockavgRate=0;
                                $blocktotAmt=$blockAreadet['SoldAmt'] + $blockAreadet['UnsoldAmt'];
                                $blocktotArea=$blockAreadet['SoldArea'] + $blockAreadet['UnsoldArea'];
                                if($blocktotAmt!=0 || $blocktotArea!=0 )
                                {
                                    $blockavgRate=$blocktotAmt/$blocktotArea;
                                }
                                //FLoor
                                $subQuery = $sql->select();
                                $subQuery->from("KF_UnitMaster")
                                    ->columns(array('FloorId'))
                                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and CreatedDate<= '$asonDate'");

                                $select = $sql->select();
                                $select->from("KF_FloorMaster")
                                    ->columns(array('FloorId','FloorName'))
                                    ->where->expression('FloorId IN ?', array($subQuery));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach($floorList as &$floorLists) {
                                    $i=$i+1;
                                    $l=$i;
                                    $FloorId=$floorLists['FloorId'];

                                    //Count unit
                                    $select = $sql->select();
                                    $select->from("KF_UnitMaster")
                                        ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and CreatedDate<= '$asonDate'");

                                    $selectUnsoldunit = $sql->select();
                                    $selectUnsoldunit->from("KF_UnitMaster")
                                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='U' and CreatedDate<= '$asonDate'");
                                    $selectUnsoldunit->combine($select,'Union ALL');

                                    $selectSoldunit = $sql->select();
                                    $selectSoldunit->from("KF_UnitMaster")
                                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='S' and CreatedDate<= '$asonDate'");
                                    $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                                    $selectBlockunit = $sql->select();
                                    $selectBlockunit->from("KF_UnitMaster")
                                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='B' and CreatedDate<= '$asonDate'");
                                    $selectBlockunit->combine($selectSoldunit,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$selectBlockunit))
                                        ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                    $floordet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    //Area
                                    $selectSoldarea = $sql->select();
                                    $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                                        ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                                        ->join(array("c"=>"Crm_PostSaleDiscountRegister"), new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                                        ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                                        ->group(new Expression('c.PostSaleDiscountId '));

                                    $selectUnSoldarea = $sql->select();
                                    $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                                        ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                                        ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                                    $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$selectUnSoldarea))
                                        ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                    $floorAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    $flooravgRate=0;
                                    $floortotAmt=$floorAreadet['SoldAmt'] + $floorAreadet['UnsoldAmt'];
                                    $floortotArea=$floorAreadet['SoldArea'] + $floorAreadet['UnsoldArea'];
                                    if($floortotAmt!=0 || $floortotArea!=0 )
                                    {
                                        $flooravgRate=$floortotAmt/$floortotArea;
                                    }

                                    //UnitType
                                    $subQuery = $sql->select();
                                    $subQuery->from("KF_UnitMaster")
                                        ->columns(array('UnitTypeId'))
                                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and CreatedDate<= '$asonDate'");

                                    $select = $sql->select();
                                    $select->from("KF_UnitTypeMaster")
                                        ->columns(array('UnitTypeId','UnitTypeName'))
                                        ->where->expression('UnitTypeId IN ?', array($subQuery));
                                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                    $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    foreach($unittypeList as &$unittypeLists) {
                                        $i=$i+1;
                                        $m=$i;
                                        $UnitTypeId=$unittypeLists['UnitTypeId'];

                                        //Count unit
                                        $select = $sql->select();
                                        $select->from("KF_UnitMaster")
                                            ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and CreatedDate<= '$asonDate'");

                                        $selectUnsoldunit = $sql->select();
                                        $selectUnsoldunit->from("KF_UnitMaster")
                                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='U' and CreatedDate<= '$asonDate'");
                                        $selectUnsoldunit->combine($select,'Union ALL');

                                        $selectSoldunit = $sql->select();
                                        $selectSoldunit->from("KF_UnitMaster")
                                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='S' and CreatedDate<= '$asonDate'");
                                        $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                                        $selectBlockunit = $sql->select();
                                        $selectBlockunit->from("KF_UnitMaster")
                                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='B' and CreatedDate<= '$asonDate'");
                                        $selectBlockunit->combine($selectSoldunit,'Union ALL');

                                        $select3 = $sql->select();
                                        $select3->from(array("g"=>$selectBlockunit))
                                            ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                        $unittypedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                        //Area
                                        $selectSoldarea = $sql->select();
                                        $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                                            ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                                            ->join(array("c"=>"Crm_PostSaleDiscountRegister"), new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                                            ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId > 0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                                        ->group(new Expression('c.PostSaleDiscountId '));


                                        $selectUnSoldarea = $sql->select();
                                        $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                                            ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                                            ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                                        $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                                        $select3 = $sql->select();
                                        $select3->from(array("g"=>$selectUnSoldarea))
                                            ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                        $unittypeAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        $unittypeavgRate=0;
                                        $unittypetotAmt=$unittypeAreadet['SoldAmt'] + $unittypeAreadet['UnsoldAmt'];
                                        $unittypetotArea=$unittypeAreadet['SoldArea'] + $unittypeAreadet['UnsoldArea'];
                                        if($unittypetotAmt!=0 || $unittypetotArea!=0 )
                                        {
                                            $unittypeavgRate=$unittypetotAmt/$unittypetotArea;
                                        }

                                        $dumArr=array();
                                        $dumArr = array(
                                            'Id' => $m,
                                            'ParentId' => $l,
                                            'Description' => $unittypeLists['UnitTypeName'],
                                            'NoofFlat' => $unittypedet['NoofFlat'],
                                            'SoldFlat' => $unittypedet['SoldFlat'],
                                            'UnsoldFlat' => $unittypedet['UnsoldFlat'],
                                            'BlockFlat' => $unittypedet['BlockFlat'],
                                            'SoldArea' => $unittypeAreadet['SoldArea'],
                                            'UnsoldArea' => $unittypeAreadet['UnsoldArea'],
                                            'TotalArea' => $unittypeAreadet['SoldArea'] + $unittypeAreadet['UnsoldArea'],
                                            'SoldAmt' => $unittypeAreadet['SoldAmt'],
                                            'UnsoldAmt' => $unittypeAreadet['UnsoldAmt'],
                                            'TotalNetAmt' => $unittypeAreadet['SoldAmt'] + $unittypeAreadet['UnsoldAmt'],
                                            'AvgRate' => $unittypeavgRate
                                        );
                                        $arrUnitLists[] =$dumArr;

                                    }

                                    $dumArr=array();
                                    $dumArr = array(
                                        'Id' => $l,
                                        'ParentId' => $k,
                                        'Description' => $floorLists['FloorName'],
                                        'NoofFlat' => $floordet['NoofFlat'],
                                        'SoldFlat' => $floordet['SoldFlat'],
                                        'UnsoldFlat' => $floordet['UnsoldFlat'],
                                        'BlockFlat' => $floordet['BlockFlat'],
                                        'SoldArea' => $floorAreadet['SoldArea'],
                                        'UnsoldArea' => $floorAreadet['UnsoldArea'],
                                        'TotalArea' => $floorAreadet['SoldArea'] + $floorAreadet['UnsoldArea'],
                                        'SoldAmt' => $floorAreadet['SoldAmt'],
                                        'UnsoldAmt' => $floorAreadet['UnsoldAmt'],
                                        'TotalNetAmt' => $floorAreadet['SoldAmt'] + $floorAreadet['UnsoldAmt'],
                                        'AvgRate' => $flooravgRate,
                                        'expanded' => 'false'
                                    );
                                    $arrUnitLists[] =$dumArr;

                                }

                                $dumArr=array();
                                $dumArr = array(
                                    'Id' => $k,
                                    'ParentId' => $j,
                                    'Description' => $blockLists['BlockName'],
                                    'NoofFlat' => $blockdet['NoofFlat'],
                                    'SoldFlat' => $blockdet['SoldFlat'],
                                    'UnsoldFlat' => $blockdet['UnsoldFlat'],
                                    'BlockFlat' => $blockdet['BlockFlat'],
                                    'SoldArea' => $blockAreadet['SoldArea'],
                                    'UnsoldArea' => $blockAreadet['UnsoldArea'],
                                    'TotalArea' => $blockAreadet['SoldArea'] + $blockAreadet['UnsoldArea'],
                                    'SoldAmt' => $blockAreadet['SoldAmt'],
                                    'UnsoldAmt' => $blockAreadet['UnsoldAmt'],
                                    'TotalNetAmt' => $blockAreadet['SoldAmt'] + $blockAreadet['UnsoldAmt'],
                                    'AvgRate' => $blockavgRate,
                                    'expanded' => 'false'
                                );
                                $arrUnitLists[] =$dumArr;

                            }

                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $j,
                                'ParentId' => $ParId,
                                'Description' => $projLists['ProjectName'],
                                'NoofFlat' => $unitdet['NoofFlat'],
                                'SoldFlat' => $unitdet['SoldFlat'],
                                'UnsoldFlat' => $unitdet['UnsoldFlat'],
                                'BlockFlat' => $unitdet['BlockFlat'],
                                'SoldArea' => $unitAreadet['SoldArea'],
                                'UnsoldArea' => $unitAreadet['UnsoldArea'],
                                'TotalArea' => $unitAreadet['SoldArea'] + $unitAreadet['UnsoldArea'],
                                'SoldAmt' => $unitAreadet['SoldAmt'],
                                'UnsoldAmt' => $unitAreadet['UnsoldAmt'],
                                'TotalNetAmt' => $unitAreadet['SoldAmt'] + $unitAreadet['UnsoldAmt'],
                                'AvgRate' => $avgRate,
                                'expanded' => 'false'
                            );
                            $arrUnitLists[] =$dumArr;
                        }

                        $data = json_encode($arrUnitLists);
                        break;
                }
                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            // $asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
            $asonDate= date('Y-m-d', strtotime(Date('Y-m-d')))." 23:59:59";

            $subQuery = $sql->select();
            $subQuery->from("KF_UnitMaster")
                ->columns(array('ProjectId'))
                ->where("DeleteFlag='0' and CreatedDate<= '$asonDate'");

            $select = $sql->select();
            $select->from("Proj_ProjectMaster")
                ->columns(array('ProjectId','ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
              $select->order("ProjectId Desc");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            $i=0;
            $ParId=0;
            foreach($projList as &$projLists) {
                $i=$i+1;
                $ParId=0;

                $ProjectId=$projLists['ProjectId'];
                //Count unit
                $select = $sql->select();
                $select->from("KF_UnitMaster")
                    ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and CreatedDate<= '$asonDate'");

                $selectUnsoldunit = $sql->select();
                $selectUnsoldunit->from("KF_UnitMaster")
                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='U' and CreatedDate<= '$asonDate'");
                $selectUnsoldunit->combine($select,'Union ALL');

                $selectSoldunit = $sql->select();
                $selectSoldunit->from("KF_UnitMaster")
                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='S' and CreatedDate<= '$asonDate'");
                $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                $selectBlockunit = $sql->select();
                $selectBlockunit->from("KF_UnitMaster")
                    ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and Status='B' and CreatedDate<= '$asonDate'");
                $selectBlockunit->combine($selectSoldunit,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$selectBlockunit))
                    ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //Area
                $selectSoldarea = $sql->select();
                $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                    ->join(array("c"=>"Crm_PostSaleDiscountRegister"), new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                    ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                    ->group(new Expression('c.PostSaleDiscountId '));

                $selectUnSoldarea = $sql->select();
                $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                    ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                $select3 = $sql->select();
                $select3->from(array("g"=>$selectUnSoldarea))
                    ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $unitAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $j=$i;
                $avgRate=0;
                $totAmt=$unitAreadet['SoldAmt'] + $unitAreadet['UnsoldAmt'];
                $totArea=$unitAreadet['SoldArea'] + $unitAreadet['UnsoldArea'];
                if($totAmt!=0 || $totArea!=0 )
                {
                    $avgRate=$totAmt/$totArea;
                }

                //Block
                $subQuery = $sql->select();
                $subQuery->from("KF_UnitMaster")
                    ->columns(array('BlockId'))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId and CreatedDate<= '$asonDate'");

                $select = $sql->select();
                $select->from("KF_BlockMaster")
                    ->columns(array('BlockId','BlockName'))
                    ->where->expression('BlockId IN ?', array($subQuery));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($blockList as &$blockLists) {
                    $BlockId=$blockLists['BlockId'];
                    $i=$i+1;
                    $k=$i;

                    //Count unit
                    $select = $sql->select();
                    $select->from("KF_UnitMaster")
                        ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and CreatedDate<= '$asonDate'");

                    $selectUnsoldunit = $sql->select();
                    $selectUnsoldunit->from("KF_UnitMaster")
                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='U' and CreatedDate<= '$asonDate'");
                    $selectUnsoldunit->combine($select,'Union ALL');

                    $selectSoldunit = $sql->select();
                    $selectSoldunit->from("KF_UnitMaster")
                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='S' and CreatedDate<= '$asonDate'");
                    $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                    $selectBlockunit = $sql->select();
                    $selectBlockunit->from("KF_UnitMaster")
                        ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and Status='B' and CreatedDate<= '$asonDate'");
                    $selectBlockunit->combine($selectSoldunit,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectBlockunit))
                        ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $blockdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    //Area
                    $selectSoldarea = $sql->select();
                    $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                        ->join(array("c"=>"Crm_PostSaleDiscountRegister"),new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                        ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                        ->group(new Expression('c.PostSaleDiscountId '));

                    $selectUnSoldarea = $sql->select();
                    $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                        ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                    $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectUnSoldarea))
                        ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $blockAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $blockavgRate=0;
                    $blocktotAmt=$blockAreadet['SoldAmt'] + $blockAreadet['UnsoldAmt'];
                    $blocktotArea=$blockAreadet['SoldArea'] + $blockAreadet['UnsoldArea'];
                    if($blocktotAmt!=0 || $blocktotArea!=0 )
                    {
                        $blockavgRate=$blocktotAmt/$blocktotArea;
                    }
                    //FLoor
                    $subQuery = $sql->select();
                    $subQuery->from("KF_UnitMaster")
                        ->columns(array('FloorId'))
                        ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and CreatedDate<= '$asonDate'");

                    $select = $sql->select();
                    $select->from("KF_FloorMaster")
                        ->columns(array('FloorId','FloorName'))
                        ->where->expression('FloorId IN ?', array($subQuery));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($floorList as &$floorLists) {
                        $i=$i+1;
                        $l=$i;
                        $FloorId=$floorLists['FloorId'];

                        //Count unit
                        $select = $sql->select();
                        $select->from("KF_UnitMaster")
                            ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and CreatedDate<= '$asonDate'");

                        $selectUnsoldunit = $sql->select();
                        $selectUnsoldunit->from("KF_UnitMaster")
                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='U' and CreatedDate<= '$asonDate'");
                        $selectUnsoldunit->combine($select,'Union ALL');

                        $selectSoldunit = $sql->select();
                        $selectSoldunit->from("KF_UnitMaster")
                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='S' and CreatedDate<= '$asonDate'");
                        $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                        $selectBlockunit = $sql->select();
                        $selectBlockunit->from("KF_UnitMaster")
                            ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and Status='B' and CreatedDate<= '$asonDate'");
                        $selectBlockunit->combine($selectSoldunit,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$selectBlockunit))
                            ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $floordet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        //Area
                        $selectSoldarea = $sql->select();
                        $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                            ->join(array("c"=>"Crm_PostSaleDiscountRegister"),new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                            ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId>0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                            ->group(new Expression('c.PostSaleDiscountId '));

                        $selectUnSoldarea = $sql->select();
                        $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                            ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                        $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                        $select3 = $sql->select();
                        $select3->from(array("g"=>$selectUnSoldarea))
                            ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $floorAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $flooravgRate=0;
                        $floortotAmt=$floorAreadet['SoldAmt'] + $floorAreadet['UnsoldAmt'];
                        $floortotArea=$floorAreadet['SoldArea'] + $floorAreadet['UnsoldArea'];
                        if($floortotAmt!=0 || $floortotArea!=0 )
                        {
                            $flooravgRate=$floortotAmt/$floortotArea;
                        }

                        //UnitType
                        $subQuery = $sql->select();
                        $subQuery->from("KF_UnitMaster")
                            ->columns(array('UnitTypeId'))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("KF_UnitTypeMaster")
                            ->columns(array('UnitTypeId','UnitTypeName'))
                            ->where->expression('UnitTypeId IN ?', array($subQuery));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($unittypeList as &$unittypeLists) {
                            $i=$i+1;
                            $m=$i;
                            $UnitTypeId=$unittypeLists['UnitTypeId'];

                            //Count unit
                            $select = $sql->select();
                            $select->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("count(UnitId)"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1")))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and CreatedDate<= '$asonDate'");

                            $selectUnsoldunit = $sql->select();
                            $selectUnsoldunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("count(UnitId)"), 'SoldFlat' => new Expression("1-1"), 'BlockFlat' => new Expression("1-1") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='U' and CreatedDate<= '$asonDate'");
                            $selectUnsoldunit->combine($select,'Union ALL');

                            $selectSoldunit = $sql->select();
                            $selectSoldunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("count(UnitId)"), 'BlockFlat' => new Expression("1-1") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='S' and CreatedDate<= '$asonDate'");
                            $selectSoldunit->combine($selectUnsoldunit,'Union ALL');

                            $selectBlockunit = $sql->select();
                            $selectBlockunit->from("KF_UnitMaster")
                                ->columns(array('NoofFlat' => new Expression("1-1"), 'UnsoldFlat' => new Expression("1-1"), 'SoldFlat' => new Expression("1-1"),'BlockFlat' => new Expression("count(UnitId)") ))
                                ->where("DeleteFlag='0' and ProjectId=$ProjectId and BlockId=$BlockId and FloorId=$FloorId and UnitTypeId=$UnitTypeId and Status='B' and CreatedDate<= '$asonDate'");
                            $selectBlockunit->combine($selectSoldunit,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectBlockunit))
                                ->columns(array("NoofFlat"=>new Expression("Sum(g.NoofFlat)"),"UnsoldFlat"=>new Expression("Sum(g.UnsoldFlat)"),"SoldFlat"=>new Expression("Sum(g.SoldFlat)"),"BlockFlat"=>new Expression("Sum(g.BlockFlat)") ));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unittypedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            //Area
                            $selectSoldarea = $sql->select();
                            $selectSoldarea->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectSoldarea::JOIN_INNER)
                                ->join(array("c"=>"Crm_PostSaleDiscountRegister"), new Expression("b.BookingId=c.BookingId and c.DistFlag=0"), array(), $selectSoldarea::JOIN_LEFT)
                                ->columns(array('SoldArea' => new Expression("Sum(a.UnitArea)"), 'UnsoldArea' => new Expression("1-1"), 'SoldAmt' => new Expression("case when c.PostSaleDiscountId > 0 then SUM(c.BaseAmount) else SUM(b.BaseAmount ) end"), 'UnsoldAmt' => new Expression("1-1") ))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.Status='S' and a.CreatedDate<= '$asonDate'")
                                ->group(new Expression('c.PostSaleDiscountId '));

                            $selectUnSoldarea = $sql->select();
                            $selectUnSoldarea->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectUnSoldarea::JOIN_INNER)
                                ->columns(array('SoldArea' => new Expression("1-1"), 'UnsoldArea' => new Expression("Sum(a.UnitArea)"), 'SoldAmt' => new Expression("1-1"), 'UnsoldAmt' => new Expression("Sum(b.BaseAmt)") ))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.Status='U' and a.CreatedDate<= '$asonDate'");
                            $selectUnSoldarea->combine($selectSoldarea,'Union ALL');
                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectUnSoldarea))
                                ->columns(array("SoldArea"=>new Expression("Sum(g.SoldArea)"),"UnsoldArea"=>new Expression("Sum(g.UnsoldArea)") ,"SoldAmt"=>new Expression("Sum(g.SoldAmt)") ,"UnsoldAmt"=>new Expression("Sum(g.UnsoldAmt)") ));

                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unittypeAreadet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $unittypeavgRate=0;
                            $unittypetotAmt=$unittypeAreadet['SoldAmt'] + $unittypeAreadet['UnsoldAmt'];
                            $unittypetotArea=$unittypeAreadet['SoldArea'] + $unittypeAreadet['UnsoldArea'];
                            if($unittypetotAmt!=0 || $unittypetotArea!=0 )
                            {
                                $unittypeavgRate=$unittypetotAmt/$unittypetotArea;
                            }

                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $m,
                                'ParentId' => $l,
                                'Description' => $unittypeLists['UnitTypeName'],
                                'NoofFlat' => $unittypedet['NoofFlat'],
                                'SoldFlat' => $unittypedet['SoldFlat'],
                                'UnsoldFlat' => $unittypedet['UnsoldFlat'],
                                'BlockFlat' => $unittypedet['BlockFlat'],
                                'SoldArea' => $unittypeAreadet['SoldArea'],
                                'UnsoldArea' => $unittypeAreadet['UnsoldArea'],
                                'TotalArea' => $unittypeAreadet['SoldArea'] + $unittypeAreadet['UnsoldArea'],
                                'SoldAmt' => $unittypeAreadet['SoldAmt'],
                                'UnsoldAmt' => $unittypeAreadet['UnsoldAmt'],
                                'TotalNetAmt' => $unittypeAreadet['SoldAmt'] + $unittypeAreadet['UnsoldAmt'],
                                'AvgRate' => $unittypeavgRate
                            );
                            $arrUnitLists[] =$dumArr;

                        }

                        $dumArr=array();
                        $dumArr = array(
                            'Id' => $l,
                            'ParentId' => $k,
                            'Description' => $floorLists['FloorName'],
                            'NoofFlat' => $floordet['NoofFlat'],
                            'SoldFlat' => $floordet['SoldFlat'],
                            'UnsoldFlat' => $floordet['UnsoldFlat'],
                            'BlockFlat' => $floordet['BlockFlat'],
                            'SoldArea' => $floorAreadet['SoldArea'],
                            'UnsoldArea' => $floorAreadet['UnsoldArea'],
                            'TotalArea' => $floorAreadet['SoldArea'] + $floorAreadet['UnsoldArea'],
                            'SoldAmt' => $floorAreadet['SoldAmt'],
                            'UnsoldAmt' => $floorAreadet['UnsoldAmt'],
                            'TotalNetAmt' => $floorAreadet['SoldAmt'] + $floorAreadet['UnsoldAmt'],
                            'AvgRate' => $flooravgRate,
                            'expanded' => 'false'
                        );
                        $arrUnitLists[] =$dumArr;

                    }

                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'Description' => $blockLists['BlockName'],
                        'NoofFlat' => $blockdet['NoofFlat'],
                        'SoldFlat' => $blockdet['SoldFlat'],
                        'UnsoldFlat' => $blockdet['UnsoldFlat'],
                        'BlockFlat' => $blockdet['BlockFlat'],
                        'SoldArea' => $blockAreadet['SoldArea'],
                        'UnsoldArea' => $blockAreadet['UnsoldArea'],
                        'TotalArea' => $blockAreadet['SoldArea'] + $blockAreadet['UnsoldArea'],
                        'SoldAmt' => $blockAreadet['SoldAmt'],
                        'UnsoldAmt' => $blockAreadet['UnsoldAmt'],
                        'TotalNetAmt' => $blockAreadet['SoldAmt'] + $blockAreadet['UnsoldAmt'],
                        'AvgRate' => $blockavgRate,
                        'expanded' => 'false'
                    );
                    $arrUnitLists[] =$dumArr;

                }

                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'Description' => $projLists['ProjectName'],
                    'NoofFlat' => $unitdet['NoofFlat'],
                    'SoldFlat' => $unitdet['SoldFlat'],
                    'UnsoldFlat' => $unitdet['UnsoldFlat'],
                    'BlockFlat' => $unitdet['BlockFlat'],
                    'SoldArea' => $unitAreadet['SoldArea'],
                    'UnsoldArea' => $unitAreadet['UnsoldArea'],
                    'TotalArea' => $unitAreadet['SoldArea'] + $unitAreadet['UnsoldArea'],
                    'SoldAmt' => $unitAreadet['SoldAmt'],
                    'UnsoldAmt' => $unitAreadet['UnsoldAmt'],
                    'TotalNetAmt' => $unitAreadet['SoldAmt'] + $unitAreadet['UnsoldAmt'],
                    'AvgRate' => $avgRate,
                    'expanded' => 'false'
                );
                $arrUnitLists[] =$dumArr;
            }


            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;

            $this->_view->reportId  = 1;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function receivablestatementrptAction(){
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
        $sql = new Sql( $dbAdapter );

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );

                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'date1' ], 'string' );
                $PostDataStr1 = $this->bsf->isNullCheck( $postData[ 'date2' ], 'string' );
                $fromDate= date('Y-m-d', strtotime($PostDataStr));
                $toDate= date('Y-m-d', strtotime($PostDataStr1));
                $select = $sql->select();
                switch($RType) {
                    case 'getBlock':
                        $ProjectId=0;
                        $ProjectId = $this->bsf->isNullCheck( $postData[ 'projectId' ], 'number' );
                        $arrUnitLists= array();
                        //Block
                        $datedet = array();
                        if($fromDate<=$toDate) {
                            $select = $sql->select();
                            $select->from("")
                                ->columns(array('Monthcount'=> new Expression("DATEDIFF(MONTH,'$fromDate','$toDate') + 1")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $mothCOuntList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $cont=$mothCOuntList[0]['Monthcount'];

                            for($i=0; $i<$cont; $i++) {
                                $tDate = date('Y-m-d', strtotime("+". $i ." month", strtotime($fromDate)));
                                $tMonth = date('M', strtotime($tDate));
                                $tMonthNo = date('m', strtotime($tDate));
                                $tYear = date('Y', strtotime($tDate));

                                $monthText = $tMonth . ', '. $tYear;

                                $dumArr=array();
                                $dumArr = array(
                                    'Year' => $tYear,
                                    'Month' => $tMonthNo,
                                    'MonthDesc' => $tMonth
                                );
                                $datedet[] =$dumArr;
                            }
                        }


                        //Block
                        $subQuery = $sql->select();
                        $subQuery->from("KF_UnitMaster")
                            ->columns(array('BlockId'))
                            ->where("DeleteFlag='0' and ProjectId=$ProjectId ");

                        $select = $sql->select();
                        $select->from("KF_BlockMaster")
                            ->columns(array('BlockId','BlockName'))
                            ->where->expression('BlockId IN ?', array($subQuery));
                        $select->order(new Expression('BlockName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($blockList as &$blockLists) {
                            $BlockId=$blockLists['BlockId'];

                            foreach($datedet as &$datedets) {
                                $Month=$datedets['Month'];
                                $Year=$datedets['Year'];

                                //Payment sch start
                                $selectPayAmt = $sql->select();
                                $selectPayAmt->from(array("a"=>"KF_UnitMaster"))
                                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectPayAmt::JOIN_INNER)
                                   // ->join(array("c"=>"Crm_PaymentScheduleUnitTrans"), new Expression("b.BookingId=c.BookingId") , array(), $selectPayAmt::JOIN_LEFT)
                                    ->join(array("e"=>"Crm_PostSaleDiscountRegister"), new Expression("a.UnitId=e.UnitId") , array(), $selectPayAmt::JOIN_LEFT)
                                    //->join(array("d"=>"Crm_PSDPaymentScheduleUnitTrans"), new Expression("d.PostSaleDiscountId=e.PostSaleDiscountId  and d.DistFlag=0") , array(), $selectPayAmt::JOIN_LEFT)
                                    ->columns(array('RecvAmount' => new Expression("case when e.PostSaleDiscountId > 0 and e.NetAmount<>0 then e.NetAmount when e.PostSaleDiscountId <= 0 and b.NetAmount<>0 then b.NetAmount else  0 END"),  'RecdAmount' => new Expression("1-1") ));
                                $selectPayAmt->where("b.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId");
                                $selectPayAmt->where("MONTH(b.BookingDate)=$Month and Year(b.BookingDate)=$Year ");
                                //Payment sch End

                                $selectProgAmt = $sql->select();
                                $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                    ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId ");
                                $selectProgAmt->where("MONTH(a.BillDate)=$Month and Year(a.BillDate)=$Year ");
                                $selectProgAmt->combine($selectPayAmt,'Union ALL');

                                $selectreceiptAmt = $sql->select();
                                $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                    ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId ");
                                $selectreceiptAmt->where("MONTH(b.ReceiptDate)=$Month and Year(b.ReceiptDate)=$Year  ");
                                $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectreceiptAmt))
                                    ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $dumArr=array();
                                $dumArr = array(
                                    'Description' => $blockLists['BlockName']. " - " .$datedets['MonthDesc']." ".$Year,
                                    'Billed' => $unitdet['RecvAmount'],
                                    'Received' => $unitdet['RecdAmount'],

                                );
                                $arrUnitLists[] =$dumArr;
                            }
                        }
                        $data = json_encode($arrUnitLists);
                        break;
                }
                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //$asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";

            $datedet = array();
            if($fromDate<=$toDate) {
                $select = $sql->select();
                $select->from("")
                    ->columns(array('Monthcount'=> new Expression("DATEDIFF(MONTH,'$fromDate','$toDate') + 1")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $mothCOuntList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $cont=$mothCOuntList[0]['Monthcount'];

                for($i=0; $i<$cont; $i++) {
                    $tDate = date('Y-m-d', strtotime("+". $i ." month", strtotime($fromDate)));
                    $tMonth = date('M', strtotime($tDate));
                    $tMonthNo = date('m', strtotime($tDate));
                    $tYear = date('Y', strtotime($tDate));

                    $monthText = $tMonth . ', '. $tYear;

                    $dumArr=array();
                    $dumArr = array(
                        'Year' => $tYear,
                        'Month' => $tMonthNo,
                        'MonthDesc' => $tMonth
                    );
                    $datedet[] =$dumArr;
                }
            }
            /*echo '<pre>';
			print_r($datedet);
			echo '</pre>';
			die;*/

            $subQuery = $sql->select();
            $subQuery->from("KF_UnitMaster")
                ->columns(array('ProjectId'))
                ->where("DeleteFlag='0'");

            $select = $sql->select();
            $select->from("Proj_ProjectMaster")
                ->columns(array('ProjectId','ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
                $select->order("ProjectId Desc");
            $select->order(new Expression('ProjectName'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /*$selectProgDate = $sql->select();
			$selectProgDate->from("Crm_ProgressBillTrans")
				->columns(array('date' => new Expression("billDate")  ));
			$selectProgDate->group(new Expression('billDate'));	
		
			$selectReceiptDate = $sql->select(); 
			$selectReceiptDate->from(array("a"=>"Crm_ReceiptAdjustment"))
					->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=a.ReceiptId", array(), $selectReceiptDate::JOIN_INNER)
					->columns(array('date' => new Expression("b.ReceiptDate") ));
			$selectReceiptDate->group(new Expression('b.ReceiptDate'));					
			$selectReceiptDate->combine($selectProgDate,'Union ALL');
				
			$select3 = $sql->select();
			$select3->from(array("g"=>$selectReceiptDate))
					->columns(array("Year"=>new Expression("Year(g.date)"),"Month"=>new Expression("MONTH(g.date)"),"MonthDesc"=>new Expression("CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10))") ));
			$select3->group(new Expression('Year(g.date),MONTH(g.date),CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10)) '));
			$select3->order(new Expression('Year(g.date),MONTH(g.date)'));
			$statement = $statement = $sql->getSqlStringForSqlObject($select3);
			$datedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			*/

            $arrUnitLists= array();
            $arrUnitLists1= array();
            $i=0;
            $ParId=0;
            foreach($projList as &$projLists) {
                $i=$i+1;
                $ParId=0;
                $ProjectId=$projLists['ProjectId'];
                $j=$i;

                //Block
                $subQuery = $sql->select();
                $subQuery->from("KF_UnitMaster")
                    ->columns(array('BlockId'))
                    ->where("DeleteFlag='0' and ProjectId=$ProjectId ");

                $select = $sql->select();
                $select->from("KF_BlockMaster")
                    ->columns(array('BlockId','BlockName'))
                    ->where->expression('BlockId IN ?', array($subQuery));
                $select->order(new Expression('BlockName'));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($blockList as &$blockLists) {
                    $BlockId=$blockLists['BlockId'];
                    $i=$i+1;
                    $k=$i;

                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'Description' => $blockLists['BlockName'],
                        'RecvAmount' => 0,
                        'RecdAmount' => 0,
                        'ProjectId' => $ProjectId,
                        'BlockId' => $BlockId
                    );
                    $totblockRecvAmount=0;
                    $totblockRecdAmount=0;
                    foreach($datedet as &$datedets) {
                        $Month=$datedets['Month'];
                        $Year=$datedets['Year'];


                        /*
						select a.UnitId,a.AdvAmount as AdvAmount
						,c.Amount as PayAmount,CASE WHEN c.Amount<>0 THEN c.Amount ELSE a.AdvAmount END Amount1 
						from Crm_UnitBooking a
						INNER JOIN KF_UnitMaster b on a.UnitId=b.UnitId
						LEFT JOIN Crm_PaymentScheduleUnitTrans c on a.UnitId=c.UnitId and c.StageType='A'
						WHere a.DeleteFlag=0 and a.UnitId=24 
						*/
                        //Payment sch start
                        $selectPayAmt = $sql->select();
                        $selectPayAmt->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectPayAmt::JOIN_INNER)
                           // ->join(array("c"=>"Crm_PaymentScheduleUnitTrans"), new Expression("b.BookingId=c.BookingId") , array(), $selectPayAmt::JOIN_LEFT)
                            ->join(array("e"=>"Crm_PostSaleDiscountRegister"), new Expression("a.UnitId=e.UnitId") , array(), $selectPayAmt::JOIN_LEFT)
                           // ->join(array("d"=>"Crm_PSDPaymentScheduleUnitTrans"), new Expression("d.PostSaleDiscountId=e.PostSaleDiscountId  and d.DistFlag=0") , array(), $selectPayAmt::JOIN_LEFT)
                            ->columns(array('RecvAmount' => new Expression("case when e.PostSaleDiscountId > 0 and e.NetAmount<>0 then e.NetAmount when e.PostSaleDiscountId <= 0 and b.NetAmount<>0 then b.NetAmount else  0 END"),  'RecdAmount' => new Expression("1-1") ));
                        $selectPayAmt->where("b.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId ");
                        $selectPayAmt->where("MONTH(b.BookingDate)=$Month and Year(b.BookingDate)=$Year ");
                        //Payment sch End

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                        $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId ");
                        $selectProgAmt->where("MONTH(a.BillDate)=$Month and Year(a.BillDate)=$Year ");
                        $selectProgAmt->combine($selectPayAmt,'Union ALL');

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                            ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                        $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId ");
                        $selectreceiptAmt->where("MONTH(b.ReceiptDate)=$Month and Year(b.ReceiptDate)=$Year  ");
                        $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$selectreceiptAmt))
                            ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dumArr['RecvAmount_'.$Month.$Year] = $unitdet['RecvAmount'];
                        $dumArr['RecdAmount_'.$Month.$Year] = $unitdet['RecdAmount'];
                       // $dumArr['Amount_'.$Month.$Year] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                        if($unitdet['RecdAmount'] > $unitdet['RecvAmount'])
                        {
                            $dumArr['Amount_'.$Month.$Year]=0;
                        }
                        else{
                            $dumArr['Amount_'.$Month.$Year]= $unitdet['RecvAmount'] - $unitdet['RecdAmount'];
                        }
                        $totblockRecvAmount =$totblockRecvAmount+$unitdet['RecvAmount'];
                        $totblockRecdAmount =$totblockRecdAmount+$unitdet['RecdAmount'];
                    }
                    $dumArr['RecvAmount'] = $totblockRecvAmount;
                    $dumArr['RecdAmount'] = $totblockRecdAmount;
                   // $dumArr['Amount'] = $totblockRecvAmount - $totblockRecdAmount;

                    if($totblockRecdAmount >=$totblockRecvAmount  ) {
                        $dumArr['Amount']=0;
                    }else{
                        $dumArr['Amount'] = $totblockRecvAmount - $totblockRecdAmount;
                    }


                    if($totblockRecvAmount!=0) {
                        if($totblockRecdAmount!=0){
                        $dumArr['ReceivedPer'] = ($totblockRecdAmount / $totblockRecvAmount) * 100;}

                    if($dumArr['Amount']!=0){
                        $dumArr['ReceivablePer'] = ($dumArr['Amount']/$totblockRecvAmount)*100;
                    }
                    }
                    else{
                        $dumArr['ReceivedPer']=0;
                        $dumArr['ReceivablePer']=0;
                    }
                    $arrUnitLists[] =$dumArr;

                }

                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'Description' => $projLists['ProjectName'],
                    'RecvAmount' => 0,
                    'RecdAmount' => 0,
                    'ProjectId' => $ProjectId,
                    'BlockId' => 0,
                    'expanded' => 'false'
                );
                $totRecvAmount=0;
                $totRecdAmount=0;
                foreach($datedet as &$datedets) {
                    $Month=$datedets['Month'];
                    $Year=$datedets['Year'];

                    //Payment sch start
                    $selectPayAmt = $sql->select();
                    $selectPayAmt->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $selectPayAmt::JOIN_INNER)
                       // ->join(array("c"=>"Crm_PaymentScheduleUnitTrans"), new Expression("b.BookingId=c.BookingId") , array(), $selectPayAmt::JOIN_LEFT)
                        ->join(array("e"=>"Crm_PostSaleDiscountRegister"), new Expression("a.UnitId=e.UnitId") , array(), $selectPayAmt::JOIN_LEFT)
                       // ->join(array("d"=>"Crm_PSDPaymentScheduleUnitTrans"), new Expression("d.PostSaleDiscountId=e.PostSaleDiscountId  and d.DistFlag=0") , array(), $selectPayAmt::JOIN_LEFT)
                        ->columns(array('RecvAmount' => new Expression("case when e.PostSaleDiscountId > 0 and e.NetAmount<>0 then e.NetAmount when e.PostSaleDiscountId <= 0 and b.NetAmount<>0 then b.NetAmount else  0 END"),  'RecdAmount' => new Expression("1-1") ));
                    $selectPayAmt->where("b.DeleteFlag='0' and a.ProjectId=$ProjectId ");
                    $selectPayAmt->where("MONTH(b.BookingDate)=$Month and Year(b.BookingDate)=$Year ");
                    //Payment sch End

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                    $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId ");
                    $selectProgAmt->where("MONTH(a.BillDate)=$Month and Year(a.BillDate)=$Year ");
                    $selectProgAmt->combine($selectPayAmt,'Union ALL');

                    $selectreceiptAmt = $sql->select();
                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                    $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId ");
                    $selectreceiptAmt->where("MONTH(b.ReceiptDate)=$Month and Year(b.ReceiptDate)=$Year  ");
                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectreceiptAmt))
                        ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['RecvAmount_'.$Month.$Year] = $unitdet['RecvAmount'];
                    $dumArr['RecdAmount_'.$Month.$Year] = $unitdet['RecdAmount'];

                    if($unitdet['RecdAmount'] > $unitdet['RecvAmount']){
                        $dumArr['Amount_'.$Month.$Year] =0;
                    }
                    else{
                        $dumArr['Amount_'.$Month.$Year] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];
                    }

                    $totRecvAmount =$totRecvAmount+$unitdet['RecvAmount'];
                    $totRecdAmount =$totRecdAmount+$unitdet['RecdAmount'];

                    $dumArr1=array();
                    $dumArr1 = array(
                        //'UserId' => $execLists['UserId'],
                        'Description' => $projLists['ProjectName']. " - " .$datedets['MonthDesc']." ".$Year,
                        'Billed' => $unitdet['RecvAmount'],
                        'Received' => $unitdet['RecdAmount']
                    );
                    $arrUnitLists1[] =$dumArr1;
                }
                $dumArr['RecvAmount'] = $totRecvAmount;
                $dumArr['RecdAmount'] = $totRecdAmount;
                if($totRecdAmount>$totRecvAmount){
                    $dumArr['Amount'] =0;
                }
                else{
                    $dumArr['Amount'] = $totRecvAmount - $totRecdAmount;
                }


                if($totRecvAmount!=0) {
                    if ($totRecdAmount != 0)
                        $dumArr['ReceivedPer'] = ($totRecdAmount / $totRecvAmount) * 100;
                }
                if($dumArr['Amount']!=0){
                    $dumArr['ReceivablePer'] = ($dumArr['Amount']/$totRecvAmount)*100;
                }else{
                    $dumArr['ReceivedPer']=0;
                    $dumArr['ReceivablePer']=0;
                }

                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->datedet = $datedet;
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->arrUnitLists1 = $arrUnitLists1;
            $this->_view->reportId  = 6;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function buyerwisereceivablerptAction(){
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
        $sql = new Sql( $dbAdapter );

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

            } else {

                $projectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectId'),'number');
                $sCompletionId = $this->bsf->isNullCheck($this->params()->fromRoute('stageCompletionId'),'number');
                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitBooking"))
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("e" => "Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array(), $select::JOIN_INNER)
                    ->columns(array('ProjectId' => new Expression("b.ProjectId"), 'ProjectName' => new Expression("e.ProjectName")))
                    ->where("a.DeleteFlag=0");
                $select ->order('b.ProjectId desc');
                $select->group(new Expression('b.ProjectId,e.ProjectName '));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->projectList = $projectList;
                $this->_view->projectId = $projectId;
                $this->_view->sCompletionId = $sCompletionId;

                $select = $sql->select();
                $select->from('KF_StageCompletion')
                    ->columns(array('*'))
                    ->where("DeleteFlag='0'");
                if ($projectId != 0) {
                    $select->where("ProjectId=$projectId");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->stageCompletionList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitBooking"))
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                    ->join(array("c" => "Crm_Leads"), "a.LeadId=c.LeadId", array('LeadName'), $select::JOIN_INNER)
                    ->join(array("e" => "Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                    ->columns(array('LeadId'))
                    ->where("a.DeleteFlag=0");
                if ($projectId != 0) {
                    $select->where("b.ProjectId=$projectId");
                }
                if($sCompletionId != 0) {
                    $select ->join(array("f" => "KF_StageCompletion"), "b.ProjectId=f.ProjectId", array('StageCompletionNo'), $select::JOIN_LEFT);
                    $select->where("f.StageCompletionId=$sCompletionId");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $selectProgDate = $sql->select();
                $selectProgDate->from(array("a" => "Crm_ProgressBillTrans"))
                    ->columns(array('date' => new Expression("a.billDate")));
                if ($projectId != 0) {
                    $selectProgDate->join(array("c" => "KF_UnitMaster"), "a.unitId=c.UnitId", array(), $selectProgDate::JOIN_INNER);
                    $selectProgDate->where("c.ProjectId=$projectId");
                }
                $selectProgDate->group(new Expression('billDate'));

                $selectReceiptDate = $sql->select();
                $selectReceiptDate->from(array("a" => "Crm_ReceiptAdjustment"))
                    ->columns(array('date' => new Expression("b.ReceiptDate")))
                    ->join(array("b" => "Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectReceiptDate::JOIN_INNER);

                if ($projectId != 0) {
                    $selectReceiptDate->join(array("c" => "KF_UnitMaster"), "b.unitId=c.UnitId", array(), $selectReceiptDate::JOIN_INNER);
                    $selectReceiptDate->where("c.ProjectId=$projectId");
                }
                $selectReceiptDate->group(new Expression('b.ReceiptDate'));
                $selectReceiptDate->combine($selectProgDate, 'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g" => $selectReceiptDate))
                    ->columns(array("Year" => new Expression("Year(g.date)"), "Month" => new Expression("MONTH(g.date)"), "MonthDesc" => new Expression("CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10))")));
                $select3->group(new Expression('Year(g.date),MONTH(g.date),CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10)) '));
                $select3->order(new Expression('Year(g.date),MONTH(g.date)'));
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $datedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $arrUnitLists = array();

                foreach ($unitList as &$unitLists) {
                    $UnitId = $unitLists['UnitId'];
                    $ProjectId = $unitLists['ProjectId'];

                    $dumArr = array();
                    $dumArr = array(
                        'LeadId' => $unitLists['LeadId'],
                        'BuyerName' => $unitLists['LeadName'],
                        'UnitId' => $unitLists['UnitId'],
                        'UnitNo' => $unitLists['UnitNo'],
                        'ProjectName' => $unitLists['ProjectName'],
                        'RecvAmount' => 0,
                        'RecdAmount' => 0
                    );
                    $totRecvAmount = 0;
                    $totRecdAmount = 0;
                    foreach ($datedet as &$datedets) {
                        $Month = $datedets['Month'];
                        $Year = $datedets['Year'];

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a" => "Crm_ProgressBillTrans"))
                            ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("c" => "Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("Sum(a.Amount+a.QualAmount)"), 'RecdAmount' => new Expression("1-1")));
                        $selectProgAmt->where("c.DeleteFlag='0' and a.UnitId=$UnitId and b.ProjectId=$ProjectId and a.CancelId=0");
                        if($sCompletionId != 0) {
                            $selectProgAmt  ->join(array("d" => "KF_StageCompletion"), new expression("c.StageCompletionId=d.StageCompletionId"), array(), $selectProgAmt::JOIN_INNER);
                            $selectProgAmt->where("d.StageCompletionId=$sCompletionId");
                        }
                        $selectProgAmt->where("MONTH(a.BillDate)=$Month and Year(a.BillDate)=$Year ");

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a" => "Crm_ReceiptAdjustment"))
                            //->join(array("b" => "Crm_ReceiptRegister"), new Expression("a.ReceiptId=b.ReceiptId and b.CancelId=0"), array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("c" => "KF_UnitMaster"), "a.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)")));
                        $selectreceiptAmt->where("a.UnitId=$UnitId and c.ProjectId=$ProjectId ");
                        $selectreceiptAmt->where("MONTH(a.ReceiptDate)=$Month and Year(a.ReceiptDate)=$Year  ");
                        if($sCompletionId != 0) {
                            $selectreceiptAmt  ->join(array("d" => "KF_StageCompletion"), new expression("a.StageId=d.StageId and a.StageType=d.StageType"), array(), $selectreceiptAmt::JOIN_INNER);
                            $selectreceiptAmt->where("d.StageCompletionId=$sCompletionId");
                        }
                      $selectreceiptAmt->combine($selectProgAmt, 'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g" => $selectreceiptAmt))
                            ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")));
                   $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dumArr['RecvAmount_' . $Month . $Year] = $unitdet['RecvAmount'];
                        $dumArr['RecdAmount_' . $Month . $Year] = $unitdet['RecdAmount'];
                        if($unitdet['RecdAmount'] > $unitdet['RecvAmount'] ){
                            $dumArr['Amount_' . $Month . $Year] =0;
                        }
                        else{

                            $dumArr['Amount_' . $Month . $Year] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];
                        }

                        $totRecvAmount =  $unitdet['RecvAmount'];
                        $totRecdAmount =  $unitdet['RecdAmount'];
                    }
                    $dumArr['RecvAmount'] = $totRecvAmount;
                    $dumArr['RecdAmount'] = $totRecdAmount;
                    if($totRecdAmount > $totRecvAmount){
                        $dumArr['Amount']=0;
                    }else{
                    $dumArr['Amount'] = $totRecvAmount - $totRecdAmount;}
                    $arrUnitLists[] = $dumArr;
                }

//                echo '<pre>';
//                print_r($arrUnitLists);
//                echo '</pre>';
//                die;
                $this->_view->datedet = $datedet;
                $this->_view->arrUnitLists = $arrUnitLists;
                $this->_view->reportId  = 12;


                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                return $this->_view;
            }
        }
    }

    public function projectwisereceivablerptAction(){
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
        $sql = new Sql( $dbAdapter );
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                $asonDate= date('Y-m-d', strtotime($PostDataStr))." 23:59:59";
                $select = $sql->select();
                switch($RType) {
                    case 'getLoad':

                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                            ->columns(array('ProjectId'))
                            ->where("a.DeleteFlag='0' and a.CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("Proj_ProjectMaster")
                            ->columns(array('ProjectId','ProjectName'))
                            ->where->expression('ProjectId IN ?', array($subQuery));
                        $select->order(new Expression('ProjectName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $arrUnitLists= array();
                        $i=0;
                        $ParId=0;
                        foreach($projList as &$projLists) {
                            $i=$i+1;
                            $ParId=0;

                            $ProjectId=$projLists['ProjectId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_UnitBooking"),  new Expression("b.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(c.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.CreatedDate<= '$asonDate' ");
                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $j=$i;
                            $receivablePer=0;
                            $totAmt=$unitdet['Amount'] - $unitdet['Total'];
                            /*if($totAmt!=0 || $unitdet['Amount']!=0 )
                {
                    $receivablePer=$totAmt/$unitdet['Amount'];
                }*/
                            if($unitdet['Amount']!=0 ) {
                                $receivablePer = (($unitdet['Amount'] - $unitdet['RecdAmount']) / $unitdet['Amount'])*100;
                            }

                            //Block
                            $subQuery = $sql->select();
                            $subQuery->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                                ->columns(array('BlockId'))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.CreatedDate<= '$asonDate'");

                            $select = $sql->select();
                            $select->from("KF_BlockMaster")
                                ->columns(array('BlockId','BlockName'))
                                ->where->expression('BlockId IN ?', array($subQuery));
                            $select->order(new Expression('BlockName'));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($blockList as &$blockLists) {
                                $BlockId=$blockLists['BlockId'];
                                $i=$i+1;
                                $k=$i;

                                $selectUnitAmt = $sql->select();
                                $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                    ->join(array("c"=>"Crm_UnitBooking"),  new Expression("b.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("Sum(c.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                                $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.CreatedDate<= '$asonDate' ");
                                $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                                $selectProgAmt = $sql->select();
                                $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId  and a.BillDate<= '$asonDate' ");
                                $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                                $selectreceiptAmt = $sql->select();
                                $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                    ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId  and b.ReceiptDate<= '$asonDate' ");
                                $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectreceiptAmt))
                                    ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                    , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $blockdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $blockreceivablePer=0;
                                $totBlockAmt=$blockdet['Amount'] - $blockdet['Total'];
                                if($blockdet['Amount']!=0 ) {
                                    $blockreceivablePer = (($blockdet['Amount'] - $blockdet['RecdAmount']) / $blockdet['Amount'])*100;
                                }
                                //FLoor
                                $subQuery = $sql->select();
                                $subQuery->from(array("a"=>"KF_UnitMaster"))
                                    ->join(array("b"=>"Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                                    ->columns(array('FloorId'))
                                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.CreatedDate<= '$asonDate'");

                                $select = $sql->select();
                                $select->from("KF_FloorMaster")
                                    ->columns(array('FloorId','FloorName'))
                                    ->where->expression('FloorId IN ?', array($subQuery));
                                $select->order(new Expression('FloorName'));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach($floorList as &$floorLists) {
                                    $i=$i+1;
                                    $l=$i;
                                    $FloorId=$floorLists['FloorId'];

                                    $selectUnitAmt = $sql->select();
                                    $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                        ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                        ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                                    $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.CreatedDate<= '$asonDate' ");
                                    $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                                    $selectProgAmt = $sql->select();
                                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                        ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                        ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                        ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                    $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and a.BillDate<= '$asonDate' ");
                                    $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                                    $selectreceiptAmt = $sql->select();
                                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                        ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                        ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                        ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                    $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and b.ReceiptDate<= '$asonDate' ");
                                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$selectreceiptAmt))
                                        ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                        , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                    $floordet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    $floorreceivablePer=0;
                                    $totFloorAmt=$floordet['Amount'] - $floordet['Total'];
                                    if($floordet['Amount']!=0 ) {
                                        $floorreceivablePer = (($floordet['Amount'] - $floordet['RecdAmount']) / $floordet['Amount'])*100;
                                    }

                                    //UnitType
                                    $subQuery = $sql->select();
                                    $subQuery->from(array("a"=>"KF_UnitMaster"))
                                        ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                                        ->columns(array('UnitTypeId'))
                                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.CreatedDate<= '$asonDate'");

                                    $select = $sql->select();
                                    $select->from("KF_UnitTypeMaster")
                                        ->columns(array('UnitTypeId','UnitTypeName'))
                                        ->where->expression('UnitTypeId IN ?', array($subQuery));
                                    $select->order(new Expression('UnitTypeName'));
                                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                    $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    foreach($unittypeList as &$unittypeLists) {
                                        $i=$i+1;
                                        $m=$i;
                                        $UnitTypeId=$unittypeLists['UnitTypeId'];

                                        $selectUnitAmt = $sql->select();
                                        $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                            ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                                        $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.CreatedDate<= '$asonDate' ");
                                        $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                                        $selectProgAmt = $sql->select();
                                        $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                            ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                            ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                        $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and a.BillDate<= '$asonDate' ");
                                        $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                                        $selectreceiptAmt = $sql->select();
                                        $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                            ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                            ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                            ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                        $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and b.ReceiptDate<= '$asonDate' ");
                                        $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                        $select3 = $sql->select();
                                        $select3->from(array("g"=>$selectreceiptAmt))
                                            ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                            , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                        $unittypedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        $unittypereceivablePer=0;
                                        $totUnittypeAmt=$unittypedet['Amount'] - $unittypedet['Total'];
                                        if($unittypedet['Amount']!=0 ) {
                                            $unittypereceivablePer = (($unittypedet['Amount'] - $unittypedet['RecdAmount']) / $unittypedet['Amount'])*100;
                                        }

                                        //UnitNo----
                                        $select = $sql->select();
                                        $select->from(array("a"=>"KF_UnitMaster"))
                                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                                            ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                                            ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName ")))
                                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.CreatedDate<= '$asonDate'");
                                        $select->order(new Expression('a.UnitNo'));
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        foreach($unitNoList as &$unitNoLists) {
                                            $i=$i+1;
                                            $n=$i;
                                            $UnitId=$unitNoLists['UnitId'];

                                            $selectUnitAmt = $sql->select();
                                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                                ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and b.CreatedDate<= '$asonDate' ");
                                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                                            $selectProgAmt = $sql->select();
                                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and a.BillDate<= '$asonDate' ");
                                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                                            $selectreceiptAmt = $sql->select();
                                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId" , array(), $selectreceiptAmt::JOIN_INNER)
                                                ->join(array("e"=>"Crm_UnitBooking"), new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and c.UnitId=$UnitId and b.ReceiptDate<= '$asonDate' ");
                                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                            $select3 = $sql->select();
                                            $select3->from(array("g"=>$selectreceiptAmt))
                                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                            $unitNodet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                            $unitNoreceivablePer=0;
                                            $totunitNoAmt=$unitNodet['Amount'] - $unitNodet['Total'];
                                            if($unitNodet['Amount']!=0 ) {
                                                $unitNoreceivablePer = (($unitNodet['Amount'] - $unitNodet['RecdAmount']) / $unitNodet['Amount'])*100;
                                            }
                                            $rec = 100-$unitNoreceivablePer;
                                            $dumArr=array();
                                            $dumArr = array(
                                                'Id' => $n,
                                                'ParentId' => $m,
                                                'Description' => $unitNoLists['UnitNo'],
                                                'Amount' => $unitNodet['Amount'],
                                                'RecvAmount' => $unitNodet['RecvAmount'],
                                                'RecdAmount' => $unitNodet['RecdAmount'],
                                                'Due' => $unitNodet['Due'],
                                                'Total' => $unitNodet['Total'],
                                                'ReceivablePer' => $rec,
                                                'Receivable' =>$unitNoreceivablePer,
                                                'ProjectId' => $ProjectId,
                                                'BlockId' => $BlockId,
                                                'FloorId' => $FloorId,
                                                'UnittypeId' => $UnitTypeId,
                                                'UnitId' => $UnitId
                                            );
                                            $arrUnitLists[] =$dumArr;

                                        }
                                        $reci = 100-  $unittypereceivablePer;
                                        $dumArr=array();
                                        $dumArr = array(
                                            'Id' => $m,
                                            'ParentId' => $l,
                                            'Description' => $unittypeLists['UnitTypeName'],
                                            'Amount' => $unittypedet['Amount'],
                                            'RecvAmount' => $unittypedet['RecvAmount'],
                                            'RecdAmount' => $unittypedet['RecdAmount'],
                                            'Due' => $unittypedet['Due'],
                                            'Total' => $unittypedet['Total'],
                                            'ReceivablePer' => $reci,
                                            'Receivable' =>$unittypereceivablePer,
                                            'ProjectId' => $ProjectId,
                                            'BlockId' => $BlockId,
                                            'FloorId' => $FloorId,
                                            'UnittypeId' => $UnitTypeId,
                                            'UnitId' => 0,
                                            'expanded' => 'false'
                                        );
                                        $arrUnitLists[] =$dumArr;

                                    }
                                    $reciv =100-  $floorreceivablePer;
                                    $dumArr=array();
                                    $dumArr = array(
                                        'Id' => $l,
                                        'ParentId' => $k,
                                        'Description' => $floorLists['FloorName'],
                                        'Amount' => $floordet['Amount'],
                                        'RecvAmount' => $floordet['RecvAmount'],
                                        'RecdAmount' => $floordet['RecdAmount'],
                                        'Due' => $floordet['Due'],
                                        'Total' => $floordet['Total'],
                                        'ReceivablePer' => $reciv,
                                        'Receivable' =>$floorreceivablePer,
                                        'ProjectId' => $ProjectId,
                                        'BlockId' => $BlockId,
                                        'FloorId' => $FloorId,
                                        'UnittypeId' => 0,
                                        'UnitId' => 0,
                                        'expanded' => 'false'
                                    );
                                    $arrUnitLists[] =$dumArr;

                                }
                                $reciva =100- $blockreceivablePer;
                                $dumArr=array();
                                $dumArr = array(
                                    'Id' => $k,
                                    'ParentId' => $j,
                                    'Description' => $blockLists['BlockName'],
                                    'Amount' => $blockdet['Amount'],
                                    'RecvAmount' => $blockdet['RecvAmount'],
                                    'RecdAmount' => $blockdet['RecdAmount'],
                                    'Due' => $blockdet['Due'],
                                    'Total' => $blockdet['Total'],
                                    'ReceivablePer' =>$reciva,
                                    'Receivable' => $blockreceivablePer,
                                    'ProjectId' => $ProjectId,
                                    'BlockId' => $BlockId,
                                    'FloorId' => 0,
                                    'UnittypeId' => 0,
                                    'UnitId' => 0,
                                    'expanded' => 'false'
                                );
                                $arrUnitLists[] =$dumArr;

                            }
                            $recivab = 100 - $receivablePer;
                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $j,
                                'ParentId' => $ParId,
                                'Description' => $projLists['ProjectName'],
                                'Amount' => $unitdet['Amount'],
                                'RecvAmount' => $unitdet['RecvAmount'],
                                'RecdAmount' => $unitdet['RecdAmount'],
                                'Due' => $unitdet['Due'],
                                'Total' => $unitdet['Total'],
                                'ReceivablePer' => $recivab,
                                'Receivable' =>$receivablePer,
                                'ProjectId' => $ProjectId,
                                'BlockId' => 0,
                                'FloorId' => 0,
                                'UnittypeId' => 0,
                                'UnitId' => 0,
                                'expanded' => 'false'
                            );
                            $arrUnitLists[] =$dumArr;
                        }


                        $data = json_encode($arrUnitLists);
                        break;
                    case 'getBlock':
                        $ProjectId=0;
                        $ProjectId = $this->bsf->isNullCheck( $postData[ 'projectId' ], 'number' );
                        $arrUnitLists= array();
                        //Block
                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                            ->columns(array('BlockId'))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("KF_BlockMaster")
                            ->columns(array('BlockId','BlockName'))
                            ->where->expression('BlockId IN ?', array($subQuery));
                        $select->order(new Expression('BlockName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($blockList as &$blockLists) {
                            $BlockId=$blockLists['BlockId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_UnitBooking"),  new Expression("b.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(c.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.CreatedDate<= '$asonDate' ");
                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId  and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId  and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $blockdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                            $dumArr=array();
                            $dumArr = array(
                                'Description' => $blockLists['BlockName'],
                                'Billed' => $blockdet['RecvAmount'],
                                'Received' => $blockdet['RecdAmount']
                            );
                            $arrUnitLists[] =$dumArr;

                        }
                        $data = json_encode($arrUnitLists);
                        break;
                    case 'getFloor':
                        $ProjectId=0;
                        $BlockId = 0;
                        $ProjectId = $this->bsf->isNullCheck( $postData[ 'projectId' ], 'number' );
                        $BlockId = $this->bsf->isNullCheck( $postData[ 'blockId' ], 'number' );
                        $arrUnitLists= array();
                        //FLoor
                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                            ->columns(array('FloorId'))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("KF_FloorMaster")
                            ->columns(array('FloorId','FloorName'))
                            ->where->expression('FloorId IN ?', array($subQuery));
                        $select->order(new Expression('FloorName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($floorList as &$floorLists) {
                            $FloorId=$floorLists['FloorId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.CreatedDate<= '$asonDate' ");
                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $floordet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $dumArr=array();
                            $dumArr = array(
                                'Description' => $floorLists['FloorName'],
                                'Billed' => $floordet['RecvAmount'],
                                'Received' => $floordet['RecdAmount']
                            );
                            $arrUnitLists[] =$dumArr;

                        }

                        $data = json_encode($arrUnitLists);
                        break;
                    case 'getUnitType':
                        $ProjectId=0;
                        $BlockId =0;
                        $FloorId = 0;
                        $ProjectId = $this->bsf->isNullCheck( $postData[ 'projectId' ], 'number' );
                        $BlockId = $this->bsf->isNullCheck( $postData[ 'blockId' ], 'number' );
                        $FloorId = $this->bsf->isNullCheck( $postData[ 'floorId' ], 'number' );
                        $arrUnitLists= array();

                        //UnitType
                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                            ->columns(array('UnitTypeId'))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("KF_UnitTypeMaster")
                            ->columns(array('UnitTypeId','UnitTypeName'))
                            ->where->expression('UnitTypeId IN ?', array($subQuery));
                        $select->order(new Expression('UnitTypeName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($unittypeList as &$unittypeLists) {
                            $UnitTypeId=$unittypeLists['UnitTypeId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.CreatedDate<= '$asonDate' ");
                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unittypedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $dumArr=array();
                            $dumArr = array(
                                'Description' => $unittypeLists['UnitTypeName'],
                                'Billed' => $unittypedet['RecvAmount'],
                                'Received' => $unittypedet['RecdAmount']
                            );
                            $arrUnitLists[] =$dumArr;

                        }
                        $data = json_encode($arrUnitLists);
                        break;
                    case 'getUnit':
                        $ProjectId=0;
                        $BlockId =0;
                        $FloorId = 0;
                        $UnitTypeId =0;
                        $ProjectId = $this->bsf->isNullCheck( $postData[ 'projectId' ], 'number' );
                        $BlockId = $this->bsf->isNullCheck( $postData[ 'blockId' ], 'number' );
                        $FloorId = $this->bsf->isNullCheck( $postData[ 'floorId' ], 'number' );
                        $UnitTypeId = $this->bsf->isNullCheck( $postData[ 'unittypeId' ], 'number' );
                        $arrUnitLists= array();
                        //UnitNo----
                        $select = $sql->select();
                        $select->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                            ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                            ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName ")))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.CreatedDate<= '$asonDate'");
                        $select->order(new Expression('a.UnitNo'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($unitNoList as &$unitNoLists) {
                            $UnitId=$unitNoLists['UnitId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and b.CreatedDate<= '$asonDate' ");
                            $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId" , array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and c.UnitId=$UnitId and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unitNodet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $dumArr=array();
                            $dumArr = array(
                                'Description' => $unitNoLists['UnitNo'],
                                'Billed' => $unitNodet['RecvAmount'],
                                'Received' => $unitNodet['RecdAmount']
                            );
                            $arrUnitLists[] =$dumArr;

                        }

                        $data = json_encode($arrUnitLists);
                        break;
                }
                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            $asonDate= date('Y-m-d', strtotime(Date('Y-m-d')))." 23:59:59";
            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"KF_UnitMaster"))
                ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                ->columns(array('ProjectId'))
                ->where("a.DeleteFlag='0' and a.CreatedDate<= '$asonDate'");

            $select = $sql->select();
            $select->from("Proj_ProjectMaster")
                ->columns(array('ProjectId','ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
            $select->order(new Expression('ProjectName'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            $arrUnitLists1= array();
            $i=0;
            $ParId=0;
            foreach($projList as &$projLists) {
                $i=$i+1;
                $ParId=0;

                $ProjectId=$projLists['ProjectId'];

                $selectUnitAmt = $sql->select();
                $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                    ->join(array("c"=>"Crm_UnitBooking"),  new Expression("b.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                    ->columns(array('Amount' => new Expression("Sum(c.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.CreatedDate<= '$asonDate' ");
                // $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                $selectProgAmt = $sql->select();
                $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                    ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and a.BillDate<= '$asonDate' ");
                $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                $selectreceiptAmt = $sql->select();
                $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                    ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                    ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and b.ReceiptDate<= '$asonDate' ");
                $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$selectreceiptAmt))
                    ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                    , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $j=$i;
                $receivablePer=0;
                $totAmt=$unitdet['Amount'] - $unitdet['Total'];
                /*if($totAmt!=0 || $unitdet['Amount']!=0 )
                {
                    $receivablePer=$totAmt/$unitdet['Amount'];
                }*/
                if($unitdet['Amount']!=0 ) {
                    $receivablePer = (($unitdet['Amount'] - $unitdet['RecdAmount']) / $unitdet['Amount'])*100;
                }

                //Block
                $subQuery = $sql->select();
                $subQuery->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                    ->columns(array('BlockId'))
                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.CreatedDate<= '$asonDate'");

                $select = $sql->select();
                $select->from("KF_BlockMaster")
                    ->columns(array('BlockId','BlockName'))
                    ->where->expression('BlockId IN ?', array($subQuery));
                $select->order(new Expression('BlockName'));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($blockList as &$blockLists) {
                    $BlockId=$blockLists['BlockId'];
                    $i=$i+1;
                    $k=$i;

                    $selectUnitAmt = $sql->select();
                    $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_UnitBooking"),  new Expression("b.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                        ->columns(array('Amount' => new Expression("Sum(c.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                    $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.CreatedDate<= '$asonDate' ");
                    $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                    $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId  and a.BillDate<= '$asonDate' ");
                    $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                    $selectreceiptAmt = $sql->select();
                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                        ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                    $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId  and b.ReceiptDate<= '$asonDate' ");
                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectreceiptAmt))
                        ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                        , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $blockdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $blockreceivablePer=0;
                    $totBlockAmt=$blockdet['Amount'] - $blockdet['Total'];
                    /* if($totBlockAmt!=0 || $blockdet['Amount']!=0 )
                     {
                         $blockreceivablePer=$totBlockAmt/$blockdet['Amount'];
                     }*/
                    if($blockdet['Amount']!=0 ) {
                        $blockreceivablePer = (($blockdet['Amount'] - $blockdet['RecdAmount']) / $blockdet['Amount'])*100;
                    }
                    //FLoor
                    $subQuery = $sql->select();
                    $subQuery->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                        ->columns(array('FloorId'))
                        ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.CreatedDate<= '$asonDate'");

                    $select = $sql->select();
                    $select->from("KF_FloorMaster")
                        ->columns(array('FloorId','FloorName'))
                        ->where->expression('FloorId IN ?', array($subQuery));
                    $select->order(new Expression('FloorName'));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($floorList as &$floorLists) {
                        $i=$i+1;
                        $l=$i;
                        $FloorId=$floorLists['FloorId'];

                        $selectUnitAmt = $sql->select();
                        $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                            ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                        $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.CreatedDate<= '$asonDate' ");
                        // $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                        $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and a.BillDate<= '$asonDate' ");
                        $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                            ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                        $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and b.ReceiptDate<= '$asonDate' ");
                        $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$selectreceiptAmt))
                            ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                            , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $floordet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $floorreceivablePer=0;
                        $totFloorAmt=$floordet['Amount'] - $floordet['Total'];
                        /* if($totFloorAmt!=0 || $floordet['Amount']!=0 )
                         {
                             $floorreceivablePer=$totFloorAmt/$floordet['Amount'];
                         }*/
                        if( $floordet['Amount']!=0 ) {
                            $floorreceivablePer = (($floordet['Amount'] - $floordet['RecdAmount'])  / $floordet['Amount'])*100;
                        }

                        //UnitType
                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                            ->columns(array('UnitTypeId'))
                            ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.CreatedDate<= '$asonDate'");

                        $select = $sql->select();
                        $select->from("KF_UnitTypeMaster")
                            ->columns(array('UnitTypeId','UnitTypeName'))
                            ->where->expression('UnitTypeId IN ?', array($subQuery));
                        $select->order(new Expression('UnitTypeName'));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($unittypeList as &$unittypeLists) {
                            $i=$i+1;
                            $m=$i;
                            $UnitTypeId=$unittypeLists['UnitTypeId'];

                            $selectUnitAmt = $sql->select();
                            $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                            $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.CreatedDate<= '$asonDate' ");
                            // $statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and a.BillDate<= '$asonDate' ");
                            $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and b.ReceiptDate<= '$asonDate' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unittypedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $unittypereceivablePer=0;
                            $totUnittypeAmt=$unittypedet['Amount'] - $unittypedet['Total'];
                            /* if($totUnittypeAmt!=0 || $unittypedet['Amount']!=0 )
                             {
                                 $unittypereceivablePer=$totUnittypeAmt/$unittypedet['Amount'];
                             }*/
                            if( $unittypedet['Amount']!=0 ) {
                                $unittypereceivablePer = (($unittypedet['Amount'] - $unittypedet['RecdAmount']) / $unittypedet['Amount'])*100;
                            }

                            //UnitNo----
                            $select = $sql->select();
                            $select->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                                ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                                ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName ")))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId and a.CreatedDate<= '$asonDate'");
                            $select->order(new Expression('a.UnitNo'));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($unitNoList as &$unitNoLists) {
                                $i=$i+1;
                                $n=$i;
                                $UnitId=$unitNoLists['UnitId'];

                                $selectUnitAmt = $sql->select();
                                $selectUnitAmt->from(array("a"=>"Crm_UnitDetails"))
                                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectUnitAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("Sum(e.NetAmount)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ));
                                $selectUnitAmt->where("a.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and b.CreatedDate<= '$asonDate' ");
                                //$statement = $statement = $sql->getSqlStringForSqlObject($selectUnitAmt);

                                $selectProgAmt = $sql->select();
                                $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                    ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("b.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                $selectProgAmt->where("c.DeleteFlag='0' and b.ProjectId=$ProjectId and b.BlockId=$BlockId and b.FloorId=$FloorId and b.UnitTypeId=$UnitTypeId and b.UnitId=$UnitId and a.BillDate<= '$asonDate' ");
                                $selectProgAmt->combine($selectUnitAmt,'Union ALL');

                                $selectreceiptAmt = $sql->select();
                                $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                                    ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"),  new Expression("c.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                    ->columns(array('Amount' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                $selectreceiptAmt->where("b.DeleteFlag='0' and c.ProjectId=$ProjectId and c.BlockId=$BlockId and c.FloorId=$FloorId and c.UnitTypeId=$UnitTypeId and c.UnitId=$UnitId and b.ReceiptDate<= '$asonDate' ");
                                $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectreceiptAmt))
                                    ->columns(array('Amount' => new Expression("Sum(g.Amount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)")
                                    , 'Due' => new Expression("Sum(g.RecvAmount-g.RecdAmount)"), 'Total' => new Expression("Sum(g.Amount-g.RecdAmount)")));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $unitNodet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $unitNoreceivablePer=0;
                                $totunitNoAmt=$unitNodet['Amount'] - $unitNodet['Total'];
                                /*if($totunitNoAmt!=0 || $unitNodet['Amount']!=0 )
                                {
                                    $unitNoreceivablePer=$totunitNoAmt/$unitNodet['Amount'];
                                }*/
                                if($unitNodet['Amount'] !=0  ) {
                                    $unitNoreceivablePer = (($unitNodet['Amount'] - $unitNodet['RecdAmount'])  / $unitNodet['Amount'])*100;
                                }
                                $rec = 100-$unitNoreceivablePer;
                                $dumArr=array();
                                $dumArr = array(
                                    'Id' => $n,
                                    'ParentId' => $m,
                                    'Description' => $unitNoLists['UnitNo'],
                                    'Amount' => $unitNodet['Amount'],
                                    'RecvAmount' => $unitNodet['RecvAmount'],
                                    'RecdAmount' => $unitNodet['RecdAmount'],
                                    'Due' => $unitNodet['Due'],
                                    'Total' => $unitNodet['Total'],
                                    'ReceivablePer' => $rec,
                                    'Receivable' =>$unitNoreceivablePer,
                                    'ProjectId' => $ProjectId,
                                    'BlockId' => $BlockId,
                                    'FloorId' => $FloorId,
                                    'UnittypeId' => $UnitTypeId,
                                    'UnitId' => $UnitId

                                );
                                $arrUnitLists[] =$dumArr;

                            }
                            $reci = 100-  $unittypereceivablePer;
                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $m,
                                'ParentId' => $l,
                                'Description' => $unittypeLists['UnitTypeName'],
                                'Amount' => $unittypedet['Amount'],
                                'RecvAmount' => $unittypedet['RecvAmount'],
                                'RecdAmount' => $unittypedet['RecdAmount'],
                                'Due' => $unittypedet['Due'],
                                'Total' => $unittypedet['Total'],
                                'ReceivablePer' => $reci,
                                'Receivable' =>$unittypereceivablePer,
                                'ProjectId' => $ProjectId,
                                'BlockId' => $BlockId,
                                'FloorId' => $FloorId,
                                'UnittypeId' => $UnitTypeId,
                                'UnitId' => 0,
                                'expanded' => 'false'
                            );
                            $arrUnitLists[] =$dumArr;

                        }
                        $reciv =100-  $floorreceivablePer;
                        $dumArr=array();
                        $dumArr = array(
                            'Id' => $l,
                            'ParentId' => $k,
                            'Description' => $floorLists['FloorName'],
                            'Amount' => $floordet['Amount'],
                            'RecvAmount' => $floordet['RecvAmount'],
                            'RecdAmount' => $floordet['RecdAmount'],
                            'Due' => $floordet['Due'],
                            'Total' => $floordet['Total'],
                            'ReceivablePer' => $reciv,
                            'Receivable' =>$floorreceivablePer,
                            'ProjectId' => $ProjectId,
                            'BlockId' => $BlockId,
                            'FloorId' => $FloorId,
                            'UnittypeId' => 0,
                            'UnitId' => 0,
                            'expanded' => 'false'
                        );
                        $arrUnitLists[] =$dumArr;

                    }
                    $reciva =100- $blockreceivablePer;
                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'Description' => $blockLists['BlockName'],
                        'Amount' => $blockdet['Amount'],
                        'RecvAmount' => $blockdet['RecvAmount'],
                        'RecdAmount' => $blockdet['RecdAmount'],
                        'Due' => $blockdet['Due'],
                        'Total' => $blockdet['Total'],
                        'ReceivablePer' => $reciva,
                        'Receivable' =>$blockreceivablePer,
                        'ProjectId' => $ProjectId,
                        'BlockId' => $BlockId,
                        'FloorId' => 0,
                        'UnittypeId' => 0,
                        'UnitId' => 0,
                        'expanded' => 'false'
                    );
                    $arrUnitLists[] =$dumArr;

                }
                $recivab =100 - $receivablePer;
                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'Description' => $projLists['ProjectName'],
                    'Amount' => $unitdet['Amount'],
                    'RecvAmount' => $unitdet['RecvAmount'],
                    'RecdAmount' => $unitdet['RecdAmount'],
                    'Due' => $unitdet['Due'],
                    'Total' => $unitdet['Total'],
                    'ReceivablePer' => $recivab,
                    'Receivable' =>$receivablePer,
                    'ProjectId' => $ProjectId,
                    'BlockId' => 0,
                    'FloorId' => 0,
                    'UnittypeId' => 0,
                    'UnitId' => 0,
                    'expanded' => 'false'
                );
                $arrUnitLists[] =$dumArr;

                $dumArr1=array();
                $dumArr1 = array(
                    //'UserId' => $execLists['UserId'],
                    'Description' => $projLists['ProjectName'],
                    'Billed' => $unitdet['RecvAmount'],
                    'Received' => $unitdet['RecdAmount']
                );
                $arrUnitLists1[] =$dumArr1;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->arrUnitLists1 = $arrUnitLists1;

            $this->_view->reportId  = 11;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function stagewisereceivablerptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $projectId = $this->params()->fromRoute('projectId');
            $paySchId = $this->params()->fromRoute('paySchId');
            if($projectId=="") { $projectId=0;}
            if($paySchId=="") { $paySchId=0;}

            $select = $sql->select();
            $select->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array(), $select::JOIN_INNER)
                ->columns(array('ProjectId' => new Expression("b.ProjectId"), 'ProjectName' => new Expression("e.ProjectName")))
                ->where("a.DeleteFlag='0'")
                ->order('b.ProjectId desc');
            $select->group(new Expression('b.ProjectId,e.ProjectName '));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a"=>"Crm_PaymentSchedule"))
                ->columns(array('PaymentScheduleId' , 'PaymentSchedule' ))
                ->where("a.DeleteFlag='0' and a.ProjectId=$projectId ");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectSchList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectList = $projectList;
            $this->_view->projectSchList = $projectSchList;
            $this->_view->projectId = $projectId;
            $this->_view->paySchId = $paySchId;

            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"KF_UnitMaster"))
                ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $subQuery::JOIN_INNER)
                ->columns(array('ProjectId'))
                ->where("b.DeleteFlag='0' and a.ProjectId=$projectId");

            $select = $sql->select();
            $select->from("Proj_ProjectMaster")
                ->columns(array('ProjectId','ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
            $select->order('ProjectId desc');
          $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //
            $selectStage = $sql->select();
            $selectStage->from(array("a"=>"Crm_PaymentScheduleDetail"))
                ->join(array("b"=>"KF_StageMaster"), "a.StageId=b.StageId", array(), $selectStage::JOIN_INNER)
                ->columns(array('StageId','StageType','Description' => new Expression("b.StageName")))
                ->where("a.PaymentScheduleId=$paySchId and a.StageType='S'");
            $selectStage->group(new Expression('a.StageId,a.StageType,b.StageName'));

            $selectDesc = $sql->select();
            $selectDesc->from(array("a"=>"Crm_PaymentScheduleDetail"))
                ->join(array("b"=>"Crm_DescriptionMaster"), "a.StageId=b.DescriptionId", array(), $selectDesc::JOIN_INNER)
                ->columns(array('StageId','StageType','Description' => new Expression("b.DescriptionName")))
                ->where("a.PaymentScheduleId=$paySchId and a.StageType='D'");
            $selectDesc->group(new Expression('a.StageId,a.StageType,b.DescriptionName'));
            $selectDesc->combine($selectStage,'Union ALL');

            $selectAdvance = $sql->select();
            $selectAdvance->from(array("a"=>"Crm_PaymentScheduleDetail"))
                ->join(array("b"=>"Crm_BookingAdvanceMaster"), "a.StageId=b.BookingAdvanceId", array(), $selectAdvance::JOIN_INNER)
                ->columns(array('StageId','StageType','Description' => new Expression("b.BookingAdvanceName")))
                ->where("a.PaymentScheduleId=$paySchId and a.StageType='A'");
            $selectAdvance->group(new Expression('a.StageId,a.StageType,b.BookingAdvanceName'));
            $selectAdvance->combine($selectDesc,'Union ALL');

            $selectOtherCost = $sql->select();
            $selectOtherCost->from(array("a"=>"Crm_PaymentScheduleDetail"))
                ->join(array("b"=>"Crm_OtherCostMaster"), "a.StageId=b.OtherCostId", array(), $selectOtherCost::JOIN_INNER)
                ->columns(array('StageId','StageType','Description' => new Expression("b.OtherCostName")))
                ->where("a.PaymentScheduleId=$paySchId and a.StageType='O'");
            $selectOtherCost->group(new Expression('a.StageId,a.StageType,b.OtherCostName'));
            $selectOtherCost->combine($selectAdvance,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$selectOtherCost))
                ->columns(array("StageId","StageType","Description" ))
                ->order('StageId asc');
            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
            $stagedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            $i=0;
            $ParId=0;
            foreach($projList as &$projLists) {
                $i=$i+1;
                $ParId=0;
                $ProjectId=$projLists['ProjectId'];
                $j=$i;

                //Block
                $subQuery = $sql->select();
                $subQuery->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $subQuery::JOIN_INNER)
                    ->columns(array('BlockId'))
                    ->where("b.DeleteFlag='0' and a.ProjectId=$projectId");

                $select = $sql->select();
                $select->from("KF_BlockMaster")
                    ->columns(array('BlockId','BlockName'))
                    ->where->expression('BlockId IN ?', array($subQuery));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $blockList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($blockList as &$blockLists) {
                    $BlockId=$blockLists['BlockId'];
                    $i=$i+1;
                    $k=$i;

                    //Floor
                    $subQuery = $sql->select();
                    $subQuery->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $subQuery::JOIN_INNER)
                        ->columns(array('FloorId'))
                        ->where("b.DeleteFlag='0' and a.ProjectId=$projectId and a.BlockId=$BlockId");

                    $select = $sql->select();
                    $select->from("KF_FloorMaster")
                        ->columns(array('FloorId','FloorName'))
                        ->where->expression('FloorId IN ?', array($subQuery));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $floorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($floorList as &$floorLists) {
                        $FloorId=$floorLists['FloorId'];
                        $i=$i+1;
                        $l=$i;

                        //UnitType
                        $subQuery = $sql->select();
                        $subQuery->from(array("a"=>"KF_UnitMaster"))
                            ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $subQuery::JOIN_INNER)
                            ->columns(array('UnitTypeId'))
                            ->where("b.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId");

                        $select = $sql->select();
                        $select->from("KF_UnitTypeMaster")
                            ->columns(array('UnitTypeId','UnitTypeName'))
                            ->where->expression('UnitTypeId IN ?', array($subQuery));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $unittypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($unittypeList as &$unittypeLists) {
                            $UnitTypeId=$unittypeLists['UnitTypeId'];
                            $i=$i+1;
                            $m=$i;

                            //UnitNo
                            $select = $sql->select();
                            $select->from(array("a"=>"KF_UnitMaster"))
                                ->join(array("b"=>"Crm_UnitBooking"),  new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                                ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                                ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName ")))
                                ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.BlockId=$BlockId and a.FloorId=$FloorId and a.UnitTypeId=$UnitTypeId");
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($unitNoList as &$unitNoLists) {
                                $UnitId=$unitNoLists['UnitId'];
                                $i=$i+1;
                                $n=$i;

                                //UnitNo			
                                $dumArr=array();
                                $dumArr = array(
                                    'Id' => $n,
                                    'ParentId' => $m,
                                    'Description' => $unitNoLists['UnitNo'],
                                    'RecvAmount' => 0,
                                    'RecdAmount' => 0,
                                    'BalAmount' => 0
                                );
                                $totunitNoRecvAmount=0;
                                $totunitNoRecdAmount=0;
                                foreach($stagedet as &$stagedets) {
                                    $StageId=$stagedets['StageId'];
                                    $StageType=$stagedets['StageType'];

                                    $selectProgAmt = $sql->select();
                                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                        ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                        ->join(array("c"=>"Crm_UnitBooking"), new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                        ->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                                        ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                        ->columns(array('RecvAmount' => new Expression("Sum(a.Amount+a.QualAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                    $selectProgAmt->where("a1.DeleteFlag='0' and b.PaymentScheduleId=$paySchId and d.ProjectId=$ProjectId and d.BlockId=$BlockId and d.FloorId=$FloorId and d.UnitTypeId=$UnitTypeId and d.UnitId=$UnitId and a.StageId=$StageId and a.StageType='$StageType' ");

                                    $selectreceiptAmt = $sql->select();
                                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
//                                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
//                                        ->join(array("c"=>"Crm_ProgressBillTrans"), "a.ProgressBillTransId=c.ProgressBillTransId", array(), $selectreceiptAmt::JOIN_INNER)
                                        ->join(array("e"=>"Crm_UnitBooking"), new Expression("a.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                        ->join(array("d"=>"Crm_PaymentScheduleDetail"), "e.PaymentScheduleId=d.PaymentScheduleId and a.StageId=d.StageId and a.StageType=d.StageType", array(), $selectreceiptAmt::JOIN_INNER)
                                        ->join(array("f"=>"KF_UnitMaster"), "e.UnitId=f.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                        ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                    $selectreceiptAmt->where( "d.PaymentScheduleId=$paySchId and f.ProjectId=$ProjectId and f.BlockId=$BlockId and f.FloorId=$FloorId and f.UnitTypeId=$UnitTypeId and f.UnitId=$UnitId and a.StageId=$StageId and a.StageType='$StageType' ");
                                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$selectreceiptAmt))
                                        ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                                   $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    $dumArr['RecvAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'];
                                    $dumArr['RecdAmount_'.$StageId.$StageType] = $unitdet['RecdAmount'];
                                    $dumArr['BalAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                                    $totunitNoRecvAmount =$totunitNoRecvAmount+$unitdet['RecvAmount'];
                                    $totunitNoRecdAmount =$totunitNoRecdAmount+$unitdet['RecdAmount'];
                                }
                                $totunitNoPer=0;
                                if($totunitNoRecvAmount - $totunitNoRecdAmount!=0 && $totunitNoRecvAmount!=0 ){
                                    $totunitNoPer= (($totunitNoRecvAmount - $totunitNoRecdAmount)/$totunitNoRecvAmount) * 100;
                                }
                                $dumArr['RecvAmount'] = $totunitNoRecvAmount;
                                $dumArr['RecdAmount'] = $totunitNoRecdAmount;
                                $dumArr['BalAmount'] = $totunitNoRecvAmount - $totunitNoRecdAmount;
                                $dumArr['BalPer'] = $totunitNoPer;
                                $arrUnitLists[] =$dumArr;
                            }

                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $m,
                                'ParentId' => $l,
                                'Description' => $unittypeLists['UnitTypeName'],
                                'RecvAmount' => 0,
                                'RecdAmount' => 0,
                                'BalAmount' => 0,
                                'expanded' => 'false'
                            );
                            $totunittypeRecvAmount=0;
                            $totunittypeRecdAmount=0;
                            foreach($stagedet as &$stagedets) {
                                $StageId=$stagedets['StageId'];
                                $StageType=$stagedets['StageType'];

                                $selectProgAmt = $sql->select();
                                $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                    ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("c"=>"Crm_UnitBooking"), new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                                    ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                    ->columns(array('RecvAmount' => new Expression("Sum(a.Amount+a.QualAmount)"), 'RecdAmount' => new Expression("1-1") ));
                                $selectProgAmt->where("a1.DeleteFlag='0' and b.PaymentScheduleId=$paySchId and d.ProjectId=$ProjectId and d.BlockId=$BlockId and d.FloorId=$FloorId and d.UnitTypeId=$UnitTypeId and a.StageId=$StageId and a.StageType='$StageType' ");

                                $selectreceiptAmt = $sql->select();
                                $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
//                                    ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
//                                    ->join(array("c"=>"Crm_ProgressBillTrans"), "a.ProgressBillTransId=c.ProgressBillTransId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("e"=>"Crm_UnitBooking"), new Expression("a.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("d"=>"Crm_PaymentScheduleDetail"), "e.PaymentScheduleId=d.PaymentScheduleId and a.StageId=d.StageId and a.StageType=d.StageType", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->join(array("f"=>"KF_UnitMaster"), "e.UnitId=f.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                    ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                                $selectreceiptAmt->where(" d.PaymentScheduleId=$paySchId and f.ProjectId=$ProjectId and f.BlockId=$BlockId and f.FloorId=$FloorId and f.UnitTypeId=$UnitTypeId and a.StageId=$StageId and a.StageType='$StageType' ");
                                $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$selectreceiptAmt))
                                    ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $dumArr['RecvAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'];
                                $dumArr['RecdAmount_'.$StageId.$StageType] = $unitdet['RecdAmount'];
                                $dumArr['BalAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                                $totunittypeRecvAmount =$totunittypeRecvAmount+$unitdet['RecvAmount'];
                                $totunittypeRecdAmount =$totunittypeRecdAmount+$unitdet['RecdAmount'];
                            }
                            $totunittypePer=0;
                            if($totunittypeRecvAmount - $totunittypeRecdAmount!=0 || $totunittypeRecvAmount!=0 ){
                                $totunittypePer= (($totunittypeRecvAmount - $totunittypeRecdAmount)/$totunittypeRecvAmount) * 100;
                            }
                            $dumArr['RecvAmount'] = $totunittypeRecvAmount;
                            $dumArr['RecdAmount'] = $totunittypeRecdAmount;
                            $dumArr['BalAmount'] = $totunittypeRecvAmount - $totunittypeRecdAmount;
                            $dumArr['BalPer'] = $totunittypePer;
                            $arrUnitLists[] =$dumArr;
                        }

                        $dumArr=array();
                        $dumArr = array(
                            'Id' => $l,
                            'ParentId' => $k,
                            'Description' => $floorLists['FloorName'],
                            'RecvAmount' => 0,
                            'RecdAmount' => 0,
                            'BalAmount' => 0,
                            'expanded' => 'false'
                        );
                        $totfloorRecvAmount=0;
                        $totfloorRecdAmount=0;
                        foreach($stagedet as &$stagedets) {
                            $StageId=$stagedets['StageId'];
                            $StageType=$stagedets['StageType'];

                            $selectProgAmt = $sql->select();
                            $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                                ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("c"=>"Crm_UnitBooking"), new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                                ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                                ->columns(array('RecvAmount' => new Expression("Sum(a.Amount+a.QualAmount)"), 'RecdAmount' => new Expression("1-1") ));
                            $selectProgAmt->where("a1.DeleteFlag='0' and b.PaymentScheduleId=$paySchId and d.ProjectId=$ProjectId and d.BlockId=$BlockId and d.FloorId=$FloorId and a.StageId=$StageId and a.StageType='$StageType' ");

                            $selectreceiptAmt = $sql->select();
                            $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
//                                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
//                                ->join(array("c"=>"Crm_ProgressBillTrans"), "a.ProgressBillTransId=c.ProgressBillTransId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("e"=>"Crm_UnitBooking"), new Expression("a.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("d"=>"Crm_PaymentScheduleDetail"), "e.PaymentScheduleId=d.PaymentScheduleId and a.StageId=d.StageId and a.StageType=d.StageType", array(), $selectreceiptAmt::JOIN_INNER)
                                ->join(array("f"=>"KF_UnitMaster"), "e.UnitId=f.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                                ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                            $selectreceiptAmt->where(" d.PaymentScheduleId=$paySchId and f.ProjectId=$ProjectId and f.BlockId=$BlockId and f.FloorId=$FloorId and a.StageId=$StageId and a.StageType='$StageType' ");
                            $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array("g"=>$selectreceiptAmt))
                                ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                            $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $dumArr['RecvAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'];
                            $dumArr['RecdAmount_'.$StageId.$StageType] = $unitdet['RecdAmount'];
                            $dumArr['BalAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                            $totfloorRecvAmount = $totfloorRecvAmount + $unitdet['RecvAmount'];
                            $totfloorRecdAmount = $totfloorRecdAmount + $unitdet['RecdAmount'];
                        }
                        $totfloorPer=0;
                        if($totfloorRecvAmount - $totfloorRecdAmount!=0 || $totfloorRecvAmount!=0 ){
                            $totfloorPer= (($totfloorRecvAmount - $totfloorRecdAmount)/$totfloorRecvAmount) * 100;
                        }
                        $dumArr['RecvAmount'] = $totfloorRecvAmount;
                        $dumArr['RecdAmount'] = $totfloorRecdAmount;
                        $dumArr['BalAmount'] = $totfloorRecvAmount - $totfloorRecdAmount;
                        $dumArr['BalPer'] = $totfloorPer;
                        $arrUnitLists[] =$dumArr;
                    }

                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'Description' => $blockLists['BlockName'],
                        'RecvAmount' => 0,
                        'RecdAmount' => 0,
                        'BalAmount' => 0,
                        'expanded' => 'false'
                    );
                    $totblockRecvAmount=0;
                    $totblockRecdAmount=0;
                    foreach($stagedet as &$stagedets) {
                        $StageId=$stagedets['StageId'];
                        $StageType=$stagedets['StageType'];

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("c"=>"Crm_UnitBooking"), new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                        $selectProgAmt->where("a1.DeleteFlag='0' and b.PaymentScheduleId=$paySchId and d.ProjectId=$ProjectId and d.BlockId=$BlockId and a.StageId=$StageId and a.StageType='$StageType' ");

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
//                            ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
//                            ->join(array("c"=>"Crm_ProgressBillTrans"), "a.ProgressBillTransId=c.ProgressBillTransId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("e"=>"Crm_UnitBooking"),new Expression("a.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("d"=>"Crm_PaymentScheduleDetail"), "e.PaymentScheduleId=d.PaymentScheduleId and a.StageId=d.StageId and a.StageType=d.StageType", array(), $selectreceiptAmt::JOIN_INNER)
                            ->join(array("f"=>"KF_UnitMaster"), "e.UnitId=f.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                        $selectreceiptAmt->where(" d.PaymentScheduleId=$paySchId and f.ProjectId=$ProjectId and f.BlockId=$BlockId and a.StageId=$StageId and a.StageType='$StageType' ");
                        $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$selectreceiptAmt))
                            ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dumArr['RecvAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'];
                        $dumArr['RecdAmount_'.$StageId.$StageType] = $unitdet['RecdAmount'];
                        $dumArr['BalAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                        $totblockRecvAmount = $totblockRecvAmount + $unitdet['RecvAmount'];
                        $totblockRecdAmount = $totblockRecdAmount + $unitdet['RecdAmount'];
                    }
                    $totblockPer=0;
                    if($totblockRecvAmount - $totblockRecdAmount!=0 || $totblockRecvAmount!=0 ){
                        $totblockPer= (($totblockRecvAmount - $totblockRecdAmount)/$totblockRecvAmount) * 100;
                    }
                    $dumArr['RecvAmount'] = $totblockRecvAmount;
                    $dumArr['RecdAmount'] = $totblockRecdAmount;
                    $dumArr['BalAmount'] = $totblockRecvAmount - $totblockRecdAmount;
                    $dumArr['BalPer'] = $totblockPer;
                    $arrUnitLists[] =$dumArr;

                }

                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'Description' => $projLists['ProjectName'],
                    'RecvAmount' => 0,
                    'RecdAmount' => 0,
                    'BalAmount' => 0,
                    'expanded' => 'false'
                );
                $totRecvAmount=0;
                $totRecdAmount=0;
                foreach($stagedet as &$stagedets) {
                    $StageId=$stagedets['StageId'];
                    $StageType=$stagedets['StageType'];

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_UnitBooking"), new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                    $selectProgAmt->where("a1.DeleteFlag='0' and b.PaymentScheduleId=$paySchId and d.ProjectId=$ProjectId and a.StageId=$StageId and a.StageType='$StageType' ");

                    $selectreceiptAmt = $sql->select();
                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
//                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
//                        ->join(array("c"=>"Crm_ProgressBillTrans"), "a.ProgressBillTransId=c.ProgressBillTransId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("e"=>"Crm_UnitBooking"), new Expression("a.UnitId=e.UnitId and e.DeleteFlag=0"), array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("d"=>"Crm_PaymentScheduleDetail"), "e.PaymentScheduleId=d.PaymentScheduleId and a.StageId=d.StageId and a.StageType=d.StageType", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("f"=>"KF_UnitMaster"), "e.UnitId=f.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                    $selectreceiptAmt->where(" d.PaymentScheduleId=$paySchId and f.ProjectId=$ProjectId and a.StageId=$StageId and a.StageType='$StageType' ");
                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectreceiptAmt))
                        ->columns(array('RecvAmount' => new Expression("Sum(isnull(g.RecvAmount,0))"), 'RecdAmount' => new Expression("Sum(isnull(g.RecdAmount,0))") ));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['RecvAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'];
                    $dumArr['RecdAmount_'.$StageId.$StageType] = $unitdet['RecdAmount'];
                    $dumArr['BalAmount_'.$StageId.$StageType] = $unitdet['RecvAmount'] - $unitdet['RecdAmount'];

                    $totRecvAmount = $totRecvAmount + $unitdet['RecvAmount'];
                    $totRecdAmount = $totRecdAmount + $unitdet['RecdAmount'];
                }
                $totPer=0;
                if($totRecvAmount - $totRecdAmount!=0 || $totRecvAmount!=0 ){
                    $totPer= (($totRecvAmount - $totRecdAmount)/$totRecvAmount) * 100;
                }

                $dumArr['RecvAmount'] = $totRecvAmount;
                $dumArr['RecdAmount'] = $totRecdAmount;
                $dumArr['BalAmount'] = $totRecvAmount - $totRecdAmount;
                $dumArr['BalPer'] = $totPer;
                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->stagedet = $stagedet;
            $this->_view->arrUnitLists = $arrUnitLists;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->_view->reportId  = 2;
            return $this->_view;
        }
    }

    public function loanduerptAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $projectId = $this->params()->fromRoute('projectId');
            $select = $sql->select();
            $select->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array(), $select::JOIN_INNER)
                ->columns(array('ProjectId' => new Expression("b.ProjectId"), 'ProjectName' => new Expression("e.ProjectName")))
                ->where("a.DeleteFlag='0'");
            $select->group(new Expression('b.ProjectId,e.ProjectName '));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->projectList = $projectList;
            $this->_view->projectId = $projectId;

            $select = $sql->select();
            $select->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitId','UnitNo','ProjectId'), $select::JOIN_INNER)
                ->join(array("c"=>"Crm_Leads"), "a.LeadId=c.LeadId", array('LeadName'), $select::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                ->columns(array('LeadId'))
                ->where("a.DeleteFlag='0'");
            if($projectId!=0) {
                $select->where("b.ProjectId=$projectId");
            }
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $selectProgDate = $sql->select();
            $selectProgDate->from("Crm_ProgressBillTrans")
                ->columns(array('date' => new Expression("billDate")  ));
            $selectProgDate->group(new Expression('billDate'));

            $selectReceiptDate = $sql->select();
            $selectReceiptDate->from(array("a"=>"Crm_ReceiptAdjustment"))
                ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=a.ReceiptId", array(), $selectReceiptDate::JOIN_INNER)
                ->columns(array('date' => new Expression("b.ReceiptDate") ));
            $selectReceiptDate->group(new Expression('b.ReceiptDate'));
            $selectReceiptDate->combine($selectProgDate,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$selectReceiptDate))
                ->columns(array("Year"=>new Expression("Year(g.date)"),"Month"=>new Expression("MONTH(g.date)"),"MonthDesc"=>new Expression("CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10))") ));
            $select3->group(new Expression('Year(g.date),MONTH(g.date),CAST(LEFT(DATENAME(MONTH,g.date),3)  AS VARCHAR(10)) '));
            $select3->order(new Expression('Year(g.date),MONTH(g.date)'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
            $datedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();

            foreach($unitList as &$unitLists) {
                $UnitId=$unitLists['UnitId'];
                $ProjectId=$unitLists['ProjectId'];

                $dumArr=array();
                $dumArr = array(
                    'LeadId' => $unitLists['LeadId'],
                    'BuyerName' => $unitLists['LeadName'],
                    'UnitId' => $unitLists['UnitId'],
                    'UnitNo' => $unitLists['UnitNo'],
                    'ProjectName' => $unitLists['ProjectName'],
                    'RecvAmount' => 0,
                    'RecdAmount' => 0
                );
                $totRecvAmount=0;
                $totRecdAmount=0;
                foreach($datedet as &$datedets) {
                    $Month=$datedets['Month'];
                    $Year=$datedets['Year'];

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1") ));
                    $selectProgAmt->where("c.DeleteFlag='0' and a.UnitId=$UnitId and b.ProjectId=$ProjectId ");
                    $selectProgAmt->where("MONTH(a.BillDate)=$Month and Year(a.BillDate)=$Year ");

                    $selectreceiptAmt = $sql->select();
                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->columns(array('RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ));
                    $selectreceiptAmt->where("b.DeleteFlag='0' and b.UnitId=$UnitId and c.ProjectId=$ProjectId ");
                    $selectreceiptAmt->where("MONTH(b.ReceiptDate)=$Month and Year(b.ReceiptDate)=$Year  ");
                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectreceiptAmt))
                        ->columns(array('RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['RecvAmount_'.$Month.$Year] = $unitdet['RecvAmount'];
                    $dumArr['RecdAmount_'.$Month.$Year] = $unitdet['RecdAmount'];

                    $totRecvAmount =$totRecvAmount+$unitdet['RecvAmount'];
                    $totRecdAmount =$totRecdAmount+$unitdet['RecdAmount'];
                }
                $dumArr['RecvAmount'] = $totRecvAmount;
                $dumArr['RecdAmount'] = $totRecdAmount;
                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->datedet = $datedet;
            $this->_view->arrUnitLists = $arrUnitLists;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function ageingrptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $projectId = $this->params()->fromRoute('projectId');

            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";
            //$asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
            $select = $sql->select();
            $select->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array(), $select::JOIN_INNER)
                ->columns(array('ProjectId' => new Expression("b.ProjectId"), 'ProjectName' => new Expression("e.ProjectName")))
                ->where("a.DeleteFlag='0'");
            $select ->order('b.ProjectId desc');
            $select->group(new Expression('b.ProjectId,e.ProjectName '));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectList = $projectList;
            $this->_view->projectId = $projectId;

            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"KF_UnitMaster"))
                ->join(array("b"=>"Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $subQuery::JOIN_INNER)
                ->columns(array('ProjectId'));
            if($fromDate !=0){
                $subQuery->where("a.DeleteFlag='0' and a.CreatedDate>='$fromDate' and a.CreatedDate<= '$toDate'");
            } else{
                $subQuery->where("a.DeleteFlag='0' and a.CreatedDate<= '$toDate'");
            }
            if($projectId!= 0){
                $subQuery->where("a.ProjectId=$projectId");
            }

            $select = $sql->select();
            $select->from("Proj_ProjectMaster")
                ->columns(array('ProjectId','ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
            $select->order('ProjectId desc');
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select3 = $sql->select();
            $select3->from(array("a"=>"Crm_AgeingPeriodMaster"))
                ->columns(array('AgeId' ,'AgeDesc' ,'FromDays' ,'ToDays' ));
            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
            $agedet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            $i=0;
            $ParId=0;
            foreach($projList as &$projLists) {
                $i=$i+1;
                $ParId=0;
                $ProjectId=$projLists['ProjectId'];
                $j=$i;

                //Unit
                $select = $sql->select();
                $select->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                    ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                    ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName ")))
                    ->where("a.DeleteFlag='0' and a.ProjectId=$ProjectId and a.CreatedDate>='$fromDate' and a.CreatedDate<= '$toDate'");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach($unitNoList as &$unitNoLists) {
                    $UnitId=$unitNoLists['UnitId'];
                    $i=$i+1;
                    $k=$i;


                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'UnitId' => $UnitId,
                        'Description' => $unitNoLists['UnitNo'],
                        'expanded' => 'false'
                    );
                    foreach($agedet as &$agedets) {
                        $AgeId=$agedets['AgeId'];
                        $FromDays=$agedets['FromDays'];
                        $ToDays=$agedets['ToDays'];
                        $FromDaysAdd=(-1) * $FromDays;
                        $ToDaysAdd=(-1) * $ToDays;

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("c"=>"Crm_UnitBooking"),new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                            //->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                            ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('Amount' => new Expression("isnull(Sum(a.NetAmount-a.PaidAmount),0)") ));
                        $selectProgAmt->where("a1.DeleteFlag='0' and d.ProjectId=$ProjectId and a.CancelId=0 and d.UnitId=$UnitId ");
                        $selectProgAmt->where("a.BillDate between (Convert(nvarchar(12),DATEADD(day, $ToDaysAdd, '$fromDate'), 113)) and (Convert(nvarchar(12), DATEADD(day, $FromDaysAdd,'$toDate'), 113)) ");
                        $selectProgAmt->where(" (a.NetAmount-a.PaidAmount) >0 ");
                        $statement = $statement = $sql->getSqlStringForSqlObject($selectProgAmt);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dumArr['Amount_'.$AgeId] = $unitdet['Amount'];
                    }
                    $arrUnitLists[] =$dumArr;
                }

                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'UnitId' => $ParId,
                    'Description' => $projLists['ProjectName'],
                    'expanded' => 'false'
                );

                foreach($agedet as &$agedets) {
                    $AgeId=$agedets['AgeId'];
                    $FromDays=$agedets['FromDays'];
                    $ToDays=$agedets['ToDays'];
                    $FromDaysAdd=(-1) * $FromDays;
                    $ToDaysAdd=(-1) * $ToDays;

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_UnitBooking"),new Expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array(), $selectProgAmt::JOIN_INNER)
                        //->join(array("b"=>"Crm_PaymentScheduleDetail"), "c.PaymentScheduleId=b.PaymentScheduleId and a.StageId=b.StageId and a.StageType=b.StageType", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("d"=>"KF_UnitMaster"), "c.UnitId=d.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('Amount' => new Expression("isnull(Sum(a.NetAmount-a.PaidAmount),0)") ));
                    $selectProgAmt->where("a1.DeleteFlag='0' and d.ProjectId=$ProjectId and a.CancelId=0");
                    $selectProgAmt->where("a.BillDate between (Convert(nvarchar(12),DATEADD(day, $ToDaysAdd, '$fromDate'), 113)) and (Convert(nvarchar(12), DATEADD(day, $FromDaysAdd,'$toDate'), 113)) ");
                    $selectProgAmt->where(" (a.NetAmount-a.PaidAmount) >0 ");
                    $statement = $statement = $sql->getSqlStringForSqlObject($selectProgAmt);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['Amount_'.$AgeId] = $unitdet['Amount'];

                }
                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->agedet = $agedet;
            $this->_view->reportId  = 7;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function getunitdetailsAction(){
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
                $postParams = $request->getPost();
                $unitId = $postParams['unitId'];
                $sql = new Sql($dbAdapter);

                $selectStage = $sql->select();
                $selectStage->from(array("a"=>"KF_StageMaster"))
                    ->columns(array('StageId','StageType' => new Expression("'S'"),'Description' => new Expression("a.StageName")))
                    ->where("a.DeleteFlag=0");

                $selectDesc = $sql->select();
                $selectDesc->from(array("a"=>"Crm_DescriptionMaster"))
                    ->columns(array('StageId'=> new Expression("a.DescriptionId"),'StageType' => new Expression("'D'"),'Description' => new Expression("a.DescriptionName")))
                    ->where("a.DeleteFlag=0");
                $selectDesc->combine($selectStage,'Union ALL');

                $selectAdvance = $sql->select();
                $selectAdvance->from(array("a"=>"Crm_BookingAdvanceMaster"))
                    ->columns(array('StageId'=> new Expression("a.BookingAdvanceId"),'StageType' => new Expression("'A'"),'Description' => new Expression("a.BookingAdvanceName")))
                    ->where("a.DeleteFlag=0");
                $selectAdvance->combine($selectDesc,'Union ALL');

                $selectOtherCost = $sql->select();
                $selectOtherCost->from(array("a"=>"Crm_OtherCostMaster"))
                    ->columns(array('StageId'=> new Expression("a.OtherCostId"),'StageType' => new Expression("'O'"),'Description' => new Expression("a.OtherCostName")))
                    ->where("a.DeleteFlag=0");
                $selectOtherCost->combine($selectAdvance,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$selectOtherCost))
                    ->join(array("a"=>"Crm_ProgressBillTrans"), "g.StageId=a.StageId and g.StageType=a.StageType", array(), $select3::JOIN_INNER)
                    ->join(array("a1"=>"Crm_ProgressBill"), "a.ProgressBillId=a1.ProgressBillId", array(), $select3::JOIN_INNER)
                    ->columns(array('SchDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"),'DueDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
                    ,'Description'=> new Expression("g.Description"),'Amount'=> new Expression("a.Amount"),'PaidAmount'=> new Expression("a.PaidAmount"),'BalAmount'=> new Expression("(a.Amount-a.PaidAmount)") ));
                $select3->where("a.UnitId=$unitId and a1.DeleteFlag=0");
                $statement = $sql->getSqlStringForSqlObject($select3);
                $this->_view->unitProgressDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function salesteamperformancerptAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $year = $this->params()->fromRoute('year');
            $periodTypeId = $this->params()->fromRoute('type');
            $mode = $this->params()->fromRoute('mode');
            if($year==0 || $year==""){
                $year=date('Y');
            }
            if($periodTypeId==0 || $periodTypeId==""){
                $periodTypeId = 1;
            }

            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"Crm_TargetTrans"))
                ->join(array("b"=>"Crm_TargetRegister"), "a.TargetId=b.TargetId", array(), $subQuery::JOIN_INNER)
                ->columns(array('ExecutiveId'))
                ->where("b.DeleteFlag='0'");

            $select = $sql->select();
            $select->from("WF_Users")
                ->columns(array('UserId','EmployeeName'))
                ->where->expression('UserId IN ?', array($subQuery));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $execList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->periodTypeId = $periodTypeId;
            $this->_view->year = $year;
            //
///
            $targetFrom="1-04-".$year;
            $iCount=0;
            if ($periodTypeId==1) {
                $nxtmonth=1;
                $iCount=12;
            } else if ($periodTypeId==2) {
                $nxtmonth=2;
                $iCount=6;
            } else if ($periodTypeId==3) {
                $nxtmonth=3;
                $iCount=4;
            } else if ($periodTypeId==4) {
                $nxtmonth=6;
                $iCount=2;
            } else if ($periodTypeId==5) {
                $nxtmonth=12;
                $iCount=1;
            }
            $MonthDesc="";
            $yearDesc=0;
            $MonthInt=0;
            $arrMonthLists= array();
            for($counter = 0;$counter<$iCount;$counter++){
                $addMonth=$nxtmonth-1;
                if($nxtmonth==1)
                {
                    $curMonth = date('Y-m-d', strtotime("+$counter month", strtotime($targetFrom)));
                    $fMonth = date('M', strtotime($curMonth));
                    $fMonthNo = date('m', strtotime($curMonth));
                    $fMonthNo = ltrim($fMonthNo, '0');
                    $fYear = date('Y', strtotime($curMonth));

                    $uptoDate = date('Y-m-d', strtotime("+1 month", strtotime($curMonth)));
                    $tMonthNo = date('m', strtotime($uptoDate));
                    $tMonthNo = ltrim($tMonthNo, '0');
                    $tYear = date('Y', strtotime($uptoDate));

                    //echo $curMonth. "---";
                    //echo $tMonth.$tMonthNo.$tYear. "---";
                    $dumArr2=array();
                    $dumArr2 = array(
                        'MonthDesc' => $fMonth." ".$fYear,
                        'Month' => $fMonthNo,
                        'Year' => $fYear,
                        'TMonth' => $fMonthNo,
                        'TYear' => $fYear,
                        'LFromDate' => $fYear."-".$fMonthNo."-01",
                        'LToDate' => $tYear."-".$tMonthNo."-01"
                    );
                    $arrMonthLists[] =$dumArr2;
                } else {
                    if($counter==0) {
                        $curMonth = date('Y-m-d', strtotime("+$counter month", strtotime($targetFrom)));
                    }
                    //echo $curMonth. "  to ";
                    $fMonth = date('M', strtotime($curMonth));
                    $fMonthNo = date('m', strtotime($curMonth));
                    $fMonthNo = ltrim($fMonthNo, '0');
                    $fYear = date('Y', strtotime($curMonth));
                    $pairMonth = date('Y-m-d', strtotime("+$addMonth month", strtotime($curMonth)));
                    //echo $pairMonth. "---";
                    $tMonth = date('M', strtotime($pairMonth));
                    $tMonthNo = date('m', strtotime($pairMonth));
                    $tMonthNo = ltrim($tMonthNo, '0');
                    $tYear = date('Y', strtotime($pairMonth));
                    $uptoDate = date('Y-m-d', strtotime("+1 month", strtotime($pairMonth)));
                    $uMonthNo = date('m', strtotime($uptoDate));
                    $uMonthNo = ltrim($uMonthNo, '0');
                    $uYear = date('Y', strtotime($uptoDate));

                    $curMonth = date('Y-m-d', strtotime("+1 month", strtotime($pairMonth)));

                    $dumArr2=array();
                    $dumArr2 = array(
                        'MonthDesc' => $fMonth." ".$fYear ." to ".$tMonth." ".$tYear,
                        'Month' => $fMonthNo,
                        'Year' => $fYear,
                        'TMonth' => $tMonthNo,
                        'TYear' => $tYear,
                        'LFromDate' => $fYear."-".$fMonthNo."-01",
                        'LToDate' => $uYear."-".$uMonthNo."-01"
                    );
                    $arrMonthLists[] =$dumArr2;
                }
            }
            /*echo '<pre>';
			print_r($arrMonthLists);
			echo '</pre>';
			die;
			*/
///
            /*
			$MonthDesc="";
			$yearDesc=0;
			$MonthInt=0;
			$arrMonthLists= array();
			for ($x = 1; $x <= 12; $x++) {
				if($x==1){
					$MonthDesc = "Apr";
					$MonthInt=4;
					$yearDesc = $year;
				} else if($x==2){
					$MonthDesc="May";
					$MonthInt=5;
					$yearDesc = $year;
				} else if($x==3){
					$MonthDesc="Jun";
					$MonthInt=6;
					$yearDesc = $year;
				} else if($x==4){
					$MonthDesc="Jul";
					$MonthInt=7;
					$yearDesc = $year;
				} else if($x==5){
					$MonthDesc="Aug";
					$MonthInt=8;
					$yearDesc = $year;
				} else if($x==6){
					$MonthDesc="Sep";
					$MonthInt=9;
					$yearDesc = $year;
				} else if($x==7){
					$MonthDesc="Oct";
					$MonthInt=10;
					$yearDesc = $year;
				} else if($x==8){
					$MonthDesc="Nov";
					$MonthInt=11;
					$yearDesc = $year;
				} else if($x==9){
					$MonthDesc="Dec";
					$MonthInt=12;
					$yearDesc = $year;
				} else if($x==10){
					$MonthDesc="Jan";
					$MonthInt=1;
					$yearDesc = $year+1;
				} else if($x==11){
					$MonthDesc="Feb";
					$MonthInt=2;
					$yearDesc = $year+1;
				} else if($x==12){
					$MonthDesc="Mar";
					$MonthInt=3;
					$yearDesc = $year+1;
				}
				$dumArr1=array();
				$dumArr1 = array(
					'MonthDesc' => $MonthDesc,
					'Month' => $MonthInt,
					'Year' => $yearDesc
				);
				$arrMonthLists[] =$dumArr1;
			}
			*/

            $arrUnitLists= array();
            $arrUnitLists1= array();
            foreach($execList as &$execLists) {
                $UserId = $execLists['UserId'];

                $dumArr = array();
                $dumArr = array(
                    'UserId' => $execLists['UserId'],
                    'ExecutiveName' => $execLists['EmployeeName'],
                    'TargetAmount' => 0,
                    'ActualAmount' => 0,
                    'Variance' => 0
                );

                $totTargetAmount = 0;
                $totActualAmount = 0;
                $totVarianceAmount = 0;
                $totTargetAmounttest = 0;
                foreach ($arrMonthLists as &$arrMonthList) {
                    $Month = $arrMonthList['Month'];
                    $Year = $arrMonthList['Year'];
                    $toMonth = $arrMonthList['TMonth'];
                    $toYear = $arrMonthList['TYear'];
                    $LFromDate = $arrMonthList['LFromDate'];
                    $LToDate = $arrMonthList['LToDate'];
                    if ($mode == 'Unit') {

                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a" => "Crm_TargetTrans"))
                            ->join(array("b" => "Crm_TargetRegister"), "a.TargetId=b.TargetId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('TargetAmount' => new Expression("sum(a.TUnits)"), 'ActualAmount' => new Expression("1-1")));
                        // $selectProgAmt->where("b.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.FMonth='$Month' and a.FYear='$Year' and a.TMonth='$toMonth' and a.TYear='$toYear' ");
                        $selectProgAmt->where("b.DeleteFlag='0' and a.ExecutiveId='$UserId' and ((a.FMonth>='$Month' And a.FYear>='$Year') And (a.TMonth<='$toMonth' And a.TYear<='$toYear')) ");

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a" => "Crm_UnitBooking"))
                            ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('TargetAmount' => new Expression("1-1"), 'ActualAmount' => new Expression("Count(a.UnitId)")));
                        //$selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and MONTH(a.BookingDate) = '$Month' AND YEAR(a.BookingDate) = $Year ");
                        //$selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and (MONTH(a.BookingDate) BETWEEN '$Month' AND '$toMonth') and (YEAR(a.BookingDate) BETWEEN '$Year' AND '$toYear' )");
                        $selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.BookingDate >= '$LFromDate' and a.BookingDate < '$LToDate'");
                        $selectreceiptAmt->combine($selectProgAmt, 'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g" => $selectreceiptAmt))
                            ->columns(array('TargetAmount' => new Expression("Sum(g.TargetAmount)"), 'ActualAmount' => new Expression("Sum(g.ActualAmount)")));
                        $statement = $statement = $sql->getSqlStringForSqlObject($selectProgAmt);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $mode = 'Unit';



                        $dumArr['TargetAmount_' . $Month . $Year] = $this->bsf->isNullCheck($unitdet['TargetAmount'], 'number');
                        $dumArr['ActualAmount_' . $Month . $Year] =  $this->bsf->isNullCheck($unitdet['ActualAmount'], 'number');
                        $diffAmt = $this->bsf->isNullCheck($unitdet['TargetAmount'], 'number') -  $this->bsf->isNullCheck($unitdet['ActualAmount'], 'number');
                        $dumArr['VarianceAmount_' . $Month . $Year] = $diffAmt;


                        $totTargetAmount = $totTargetAmount + $this->bsf->isNullCheck($unitdet['TargetAmount'], 'number');
                        $totActualAmount = $totActualAmount +  $this->bsf->isNullCheck($unitdet['ActualAmount'], 'number');

                        $dumArr1=array();
                        $dumArr1 = array(
                            //'UserId' => $execLists['UserId'],
                            //$arrMonthList['Month'];
                            'Description' => $execLists['EmployeeName']. " - " .$arrMonthList['MonthDesc']." ".$Year,
                            'Target' => $unitdet['TargetAmount'],
                            'Actual' => $unitdet['ActualAmount']
                        );
                        $arrUnitLists1[] =$dumArr1;
                    } else {
                        $selectProgAmt = $sql->select();
                        $selectProgAmt->from(array("a" => "Crm_TargetTrans"))
                            ->join(array("b" => "Crm_TargetRegister"), "a.TargetId=b.TargetId", array(), $selectProgAmt::JOIN_INNER)
                            ->columns(array('TargetAmount' => new Expression("Sum(a.TValue)"), 'ActualAmount' => new Expression("1-1")));
                        //$selectProgAmt->where("b.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.FMonth='$Month' and a.FYear='$Year' and a.TMonth='$toMonth' and a.TYear='$toYear' ");
                        $selectProgAmt->where("b.DeleteFlag='0' and a.ExecutiveId='$UserId' and ((a.FMonth>='$Month' And a.FYear>='$Year') And (a.TMonth<='$toMonth' And a.TYear<='$toYear')) ");

                        $selectreceiptAmt = $sql->select();
                        $selectreceiptAmt->from(array("a" => "Crm_UnitBooking"))
                            ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                            ->columns(array('TargetAmount' => new Expression("1-1"), 'ActualAmount' => new Expression("Sum(a.NetAmount)")));
                        //$selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and MONTH(a.BookingDate) = '$Month' AND YEAR(a.BookingDate) = $Year ");
                        //$selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and (MONTH(a.BookingDate) BETWEEN '$Month' AND '$toMonth') and (YEAR(a.BookingDate) BETWEEN '$Year' AND '$toYear' )");
                        $selectreceiptAmt->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.BookingDate >= '$LFromDate' and a.BookingDate < '$LToDate'");
                        $selectreceiptAmt->combine($selectProgAmt, 'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g" => $selectreceiptAmt))
                            ->columns(array('TargetAmount' => new Expression("Sum(g.TargetAmount)"), 'ActualAmount' => new Expression("Sum(g.ActualAmount)")));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $mode = 'Amount';

                        $dumArr['TargetAmount_' . $Month . $Year] = $this->bsf->isNullCheck($unitdet['TargetAmount'], 'number');
                        $dumArr['ActualAmount_' . $Month . $Year] = $this->bsf->isNullCheck($unitdet['ActualAmount'], 'number');

                        $diffAmt = $unitdet['TargetAmount'] - $unitdet['ActualAmount'];
                        $varPercentage = 0;
                        if ($diffAmt != 0  && $diffAmt > 0 && $unitdet['TargetAmount'] != 0) {
                            $varPercentage = (($diffAmt / $unitdet['TargetAmount']) * 100);
                        }
                        $dumArr['VarianceAmount_' . $Month . $Year] = $varPercentage;

                        //mode AMOUNT
                        $totTargetAmount = $totTargetAmount + $unitdet['TargetAmount'];
                        $totActualAmount = $totActualAmount + $unitdet['ActualAmount'];

                        $dumArr1=array();
                        $dumArr1 = array(
                            //'UserId' => $execLists['UserId'],
                            //$arrMonthList['Month'];
                            'Description' => $execLists['EmployeeName']. " - " .$arrMonthList['MonthDesc']." ".$Year,
                            'Target' => $unitdet['TargetAmount'],
                            'Actual' => $unitdet['ActualAmount']
                        );
                        $arrUnitLists1[] =$dumArr1;
                    }


                }
                $totVarianceAmount = $totTargetAmount - $totActualAmount;
                $totVarPercentage = 0;
                if ($totVarianceAmount != 0 && $totVarianceAmount > 0 && $totTargetAmount != 0) {
                    $totVarPercentage = (($totVarianceAmount / $totTargetAmount) * 100);
                }
                $dumArr['TargetAmount'] = $totTargetAmount;
                $dumArr['ActualAmount'] = $totActualAmount;
                $dumArr['Variance'] = $totVarPercentage;
                $arrUnitLists[] = $dumArr;

            }


            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';die;*/
            $this->_view->arrMonthLists = $arrMonthLists;
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->arrUnitLists1 = $arrUnitLists1;
            $this->_view->mode = $mode;
            $this->_view->reportId  = 17;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function reportlistAction(){
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
        //$sql = new Sql( $dbAdapter );

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
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function executiveanalysisrptAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";

            $asonDate=$toDate;


            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"Crm_LeadFollowup"))
                //->join(array("b"=>"Crm_TargetRegister"), "a.TargetId=b.TargetId", array(), $subQuery::JOIN_INNER)
                ->columns(array('ExecutiveId'));
            if($fromDate !=0){
                $subQuery->where("a.DeleteFlag='0' and a.FollowUpDate>='$fromDate' and a.FollowUpDate<= '$toDate'");
            } else{
                $subQuery->where("a.DeleteFlag='0' and a.FollowUpDate<= '$asonDate'");
            }

            $select = $sql->select();
            $select->from("WF_Users")
                ->columns(array('UserId','EmployeeName'))
                ->where->expression('UserId IN ?', array($subQuery));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $execList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            foreach($execList as &$execLists) {
                $UserId=$execLists['UserId'];

                $dumArr=array();
                $dumArr = array(
                    'UserId' => $execLists['UserId'],
                    'ExecutiveName' => $execLists['EmployeeName'],
                    'TotalLeads' => 0,
                    'NoofSiteVisit' => 0,
                    'ClientFollowed' => 0,
                    'Finalizations' => 0,
                    'SaleValue' => 0
                );
                //TotalLeads
                $selectTotalLeads = $sql->select();
                $selectTotalLeads->from(array("a"=>"Crm_Leads"))
                    ->columns(array('TotalLeads' => new Expression("Count(LeadId)"), 'NoofSiteVisit' => new Expression("1-1"), 'Finalizations' => new Expression("1-1"), 'ClientFollowed' => new Expression("1-1"), 'SaleValue' => new Expression("1-1") ));
                if($fromDate !=0){
                    $selectTotalLeads->where("a.DeleteFlag='0' and a.LeadDate>='$fromDate' and a.LeadDate<= '$toDate' and ExecutiveId='$UserId'");
                } else{
                    $selectTotalLeads->where("a.DeleteFlag='0' and a.LeadDate<= '$asonDate' and ExecutiveId='$UserId'");
                }

                //NoofSiteVisit
                $selectNoofSiteVisit = $sql->select();
                $selectNoofSiteVisit->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('TotalLeads' => new Expression("1-1"), 'NoofSiteVisit' => new Expression("Count(EntryId)"), 'Finalizations' => new Expression("1-1"), 'ClientFollowed' => new Expression("1-1"), 'SaleValue' => new Expression("1-1") ));
                if($fromDate !=0){
                    $selectNoofSiteVisit->where("a.DeleteFlag='0' and CallTypeId=5 and a.ExecutiveId='$UserId' and a.FollowUpDate>='$fromDate' and a.FollowUpDate<= '$toDate'");
                } else{
                    $selectNoofSiteVisit->where("a.DeleteFlag='0' and CallTypeId=5 and a.ExecutiveId='$UserId' and a.FollowUpDate<= '$asonDate' ");
                }
                $selectNoofSiteVisit->combine($selectTotalLeads,'Union ALL');
                //ClientFollowed
                $selectClientFollowed = $sql->select();
                $selectClientFollowed->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('TotalLeads' => new Expression("1-1"), 'NoofSiteVisit' => new Expression("1-1"), 'Finalizations' => new Expression("1-1"), 'ClientFollowed' => new Expression("Count(EntryId)"), 'SaleValue' => new Expression("1-1") ));
                if($fromDate !=0){
                    $selectClientFollowed->where("a.DeleteFlag='0' and CallTypeId=1 and a.ExecutiveId='$UserId' and a.FollowUpDate>='$fromDate' and a.FollowUpDate<= '$toDate'");
                } else{
                    $selectClientFollowed->where("a.DeleteFlag='0' and CallTypeId=1 and a.ExecutiveId='$UserId' and a.FollowUpDate<= '$asonDate' ");
                }
                $selectClientFollowed->combine($selectNoofSiteVisit,'Union ALL');
                //Finalizations
                $selectFinalizations = $sql->select();
                $selectFinalizations->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('TotalLeads' => new Expression("1-1"),'NoofSiteVisit' => new Expression("1-1"), 'Finalizations' => new Expression("Count(EntryId)"), 'ClientFollowed' => new Expression("1-1"), 'SaleValue' => new Expression("1-1") ));
                if($fromDate !=0){
                    $selectFinalizations->where("a.DeleteFlag='0' and CallTypeId=4 and a.ExecutiveId='$UserId' and a.FollowUpDate>='$fromDate' and a.FollowUpDate<= '$toDate'");
                } else{
                    $selectFinalizations->where("a.DeleteFlag='0' and CallTypeId=4 and a.ExecutiveId='$UserId' and a.FollowUpDate<= '$asonDate' ");
                }
                $selectFinalizations->combine($selectClientFollowed,'Union ALL');
                //SaleValue
                $selectSaleValue = $sql->select();
                $selectSaleValue->from(array("a"=>"Crm_UnitBooking"))
                    ->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectSaleValue::JOIN_INNER)
                    ->columns(array('TotalLeads' => new Expression("1-1"),'NoofSiteVisit' => new Expression("1-1"), 'Finalizations' => new Expression("1-1"), 'ClientFollowed' => new Expression("1-1"), 'SaleValue' => new Expression("isnull(Sum(a.NetAmount),0)") ));
                if($fromDate !=0){
                    $selectSaleValue->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId'  and a.BookingDate>='$fromDate' and a.BookingDate<= '$toDate'");
                } else{
                    $selectSaleValue->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.BookingDate<= '$asonDate' ");
                }

                $selectSaleValue->combine($selectFinalizations,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$selectSaleValue))
                    ->columns(array('TotalLeads' => new Expression("Sum(g.TotalLeads)"), 'NoofSiteVisit' => new Expression("Sum(g.NoofSiteVisit)"), 'Finalizations' => new Expression("Sum(g.Finalizations)"), 'ClientFollowed' => new Expression("Sum(g.ClientFollowed)"), 'SaleValue' => new Expression("Sum(g.SaleValue)") ));
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $dumArr['TotalLeads'] = $unitdet['TotalLeads'];
                $dumArr['NoofSiteVisit'] = $unitdet['NoofSiteVisit'];
                $dumArr['ClientFollowed'] = $unitdet['ClientFollowed'];
                $dumArr['Finalizations'] = $unitdet['Finalizations'];
                $dumArr['SaleValue'] = $unitdet['SaleValue'];

                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->reportId  = 3;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function dailycampaignanalysisAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $this->_view->dat = $this->params()->fromRoute('Date');

            $asonDate= date('Y-m-d', strtotime(Date('Y-m-d')))." 23:59:59";
            if($this->_view->dat!=""){
                $asonDate= date('Y-m-d', strtotime($this->_view->dat))." 23:59:59";
            }

            //$asonDate="2016-02-09";
            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"Crm_CampaignRegister"))
                ->columns(array('OpportunityId'))
                ->where("a.DeleteFlag='0' and a.CampaignDate= '$asonDate'");

            $select = $sql->select();
            $select->from("Crm_OpportunityMaster")
                ->columns(array('OpportunityId','OpportunityName'))
                ->where->expression('OpportunityId IN ?', array($subQuery));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $opportunityList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $selectCamp = $sql->select();
            $selectCamp->from(array("a"=>"Crm_CampaignRegister"))
                ->join(array("b"=>"Crm_CampaignProjectTrans"), "a.CampaignId=b.CampaignId", array(), $selectCamp::JOIN_INNER)
                ->join(array("c"=>"Proj_ProjectMaster"), "b.ProjectId=c.ProjectId", array(), $selectCamp::JOIN_LEFT)
                ->columns(array('CampaignId','CampaignName','ProjectId' => new Expression("b.ProjectId"),'ProjectName' => new Expression("c.ProjectName") ))
                ->where("a.DeleteFlag='0' and a.CampaignDate= '$asonDate'");
            $selectCamp->order(new Expression("b.ProjectId,a.CampaignId asc"));
            $statement = $statement = $sql->getSqlStringForSqlObject($selectCamp);
            $CampList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            foreach($CampList as &$CampLists) {
                $ProjectId=$CampLists['ProjectId'];
                $CampaignId=$CampLists['CampaignId'];

                $dumArr=array();
                $dumArr = array(
                    'ProjectId' => $CampLists['ProjectId'],
                    'ProjectName' => $CampLists['ProjectName'],
                    'CampaignId' => $CampLists['CampaignId'],
                    'CampaignName' => $CampLists['CampaignName']
                );

                foreach($opportunityList as &$opportunityLists) {
                    $OpportunityId=$opportunityLists['OpportunityId'];
                    $OpportunityName=$opportunityLists['OpportunityName'];

                    $selectcountOpportunity = $sql->select();
                    $selectcountOpportunity->from(array("a"=>"Crm_CampaignRegister"))
                        ->join(array("b"=>"Crm_CampaignProjectTrans"), "a.CampaignId=b.CampaignId", array(), $selectcountOpportunity::JOIN_INNER)
                        ->columns(array('OpportunityId'=> new Expression("isnull(count(a.OpportunityId),0)") ))
                        ->where("a.DeleteFlag='0' and a.CampaignId='$CampaignId' and a.OpportunityId='$OpportunityId' and b.ProjectId='$ProjectId' and a.CampaignDate= '$asonDate'");
                    $statement = $statement = $sql->getSqlStringForSqlObject($selectcountOpportunity);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr[$OpportunityName.'_'.$OpportunityId] = $unitdet['OpportunityId'];
                }
                $arrUnitLists[] =$dumArr;
            }

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->arropportunityList = $opportunityList;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function marketinganalysisrptAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";


            $arrUnitLists= array();
            //foreach($execList as &$execLists) {
            //$UserId=$execLists['UserId'];

            $dumArr=array();
            $dumArr = array(
                'RowId' => 1,
                'NoofLeads' => 0,
                'NoofSiteVisit' => 0,
                'NoofConversation' => 0,
                'NoofCancellation' => 0,
                'Netbooking' => 0,
                'LeadstoSiteVisitRatio' => 0,
                'LeadstoConversationRatio' => 0,
                'SiteVisittoConversationRatio' => 0
            );
            //TotalLeads
            $selectTotalLeads = $sql->select();
            $selectTotalLeads->from(array("a"=>"Crm_Leads"))
                ->columns(array('NoofLeads' => new Expression("Count(LeadId)"), 'NoofSiteVisit' => new Expression("1-1"), 'NoofConversation' => new Expression("1-1"), 'NoofCancellation' => new Expression("1-1"), 'Netbooking' => new Expression("1-1") ));
            $selectTotalLeads->where("a.DeleteFlag='0' and a.LeadDate<= '$toDate' and a.LeadDate>= '$fromDate' ");
            //NoofSiteVisit
            $selectNoofSiteVisit = $sql->select();
            $selectNoofSiteVisit->from(array("a"=>"Crm_LeadFollowup"))
                ->columns(array('NoofLeads' => new Expression("1-1"), 'NoofSiteVisit' => new Expression("Count(EntryId)"), 'NoofConversation' => new Expression("1-1"), 'NoofCancellation' => new Expression("1-1"), 'Netbooking' => new Expression("1-1") ));
            $selectNoofSiteVisit->where("a.DeleteFlag='0'and  a.CallTypeId=5 and a.FollowUpDate<= '$toDate' and a.FollowUpDate>= '$fromDate' ");
            $selectNoofSiteVisit->combine($selectTotalLeads,'Union ALL');
            //NoofConversation
            $selectClientFollowed = $sql->select();
            $selectClientFollowed->from(array("a"=>"Crm_LeadFollowup"))
                ->columns(array('NoofLeads' => new Expression("1-1"), 'NoofSiteVisit' => new Expression("1-1"),  'NoofConversation' => new Expression("Count(DISTINCT a.LeadId)"), 'NoofCancellation' => new Expression("1-1"), 'Netbooking' => new Expression("1-1") ));
            $selectClientFollowed->where("a.DeleteFlag='0' and a.NatureId=2 and a.CallTypeId=4 and a.FollowUpDate<= '$toDate' and a.FollowUpDate>= '$fromDate' ");
            $selectClientFollowed->combine($selectNoofSiteVisit,'Union ALL');
            //NoofCancellation
            $selectFinalizations = $sql->select();
            $selectFinalizations->from(array("a"=>"Crm_UnitCancellation"))
                // ->join(array("b"=>"Crm_UnitBooking"), "a.BookingId=b.BookingId", array(), $selectFinalizations::JOIN_INNER)
                ->columns(array('NoofLeads' => new Expression("1-1"),'NoofSiteVisit' => new Expression("1-1"),'NoofConversation' => new Expression("1-1"),'NoofCancellation' => new Expression("Count(DISTINCT a.UnitId)") , 'Netbooking' => new Expression("1-1") ));
            $selectFinalizations->where("a.RefDate<= '$toDate' and a.RefDate>= '$fromDate' ");
            $selectFinalizations->combine($selectClientFollowed,'Union ALL');
            //Netbooking
            $selectSaleValue = $sql->select();
            $selectSaleValue->from(array("a"=>"Crm_UnitBooking"))
                //->join(array("b"=>"Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $selectSaleValue::JOIN_INNER)
                ->columns(array('NoofLeads' => new Expression("1-1"),'NoofSiteVisit' => new Expression("1-1"), 'NoofConversation' => new Expression("1-1"), 'NoofCancellation' => new Expression("1-1"), 'Netbooking' => new Expression("Count(DISTINCT a.UnitId)") ));
            $selectSaleValue->where(" a.BookingDate<= '$toDate' and a.BookingDate>= '$fromDate' ");
            $selectSaleValue->combine($selectFinalizations,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$selectSaleValue))
                ->columns(array('NoofLeads' => new Expression("Sum(g.NoofLeads)"), 'NoofSiteVisit' => new Expression("Sum(g.NoofSiteVisit)"), 'NoofConversation' => new Expression("Sum(g.NoofConversation)"), 'NoofCancellation' => new Expression("Sum(g.NoofCancellation)"), 'Netbooking' => new Expression("Sum(g.Netbooking)") ));
            $statement = $statement = $sql->getSqlStringForSqlObject($select3);
            $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $dumArr['NoofLeads'] = $unitdet['NoofLeads'];
            $dumArr['NoofSiteVisit'] = $unitdet['NoofSiteVisit'];
            $dumArr['NoofConversation'] = $unitdet['NoofConversation'];
            $dumArr['NoofCancellation'] = $unitdet['NoofCancellation'];
            $dumArr['Netbooking'] = $unitdet['Netbooking'];

            $leadstoSiteVisitRatio =0;
            $leadstoConversationRatio =0;
            $siteVisittoConversationRatio =0;
            if($unitdet['NoofLeads']!=0)
            {
                $leadstoSiteVisitRatio = ($unitdet['NoofSiteVisit']*100)/$unitdet['NoofLeads'];
                $leadstoConversationRatio = ($unitdet['NoofConversation']*100)/$unitdet['NoofLeads'];
            }

            if($unitdet['NoofSiteVisit']!=0)
            {
                $siteVisittoConversationRatio = ($unitdet['NoofConversation']*100)/$unitdet['NoofSiteVisit'];
            }
            $dumArr['LeadstoSiteVisitRatio'] = $leadstoSiteVisitRatio;
            $dumArr['LeadstoConversationRatio'] = $leadstoConversationRatio;
            $dumArr['SiteVisittoConversationRatio'] = $siteVisittoConversationRatio;

            $arrUnitLists[] =$dumArr;
            //}

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->reportId  = 8;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function customerinforptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $selectCamp = $sql->select();
            $selectCamp->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectCamp::JOIN_INNER)
                ->join(array("a1"=>"Crm_Leads"), "a.LeadId=a1.LeadId", array(), $selectCamp::JOIN_INNER)
                ->join(array("b1"=>"Crm_LeadAddress"), new Expression("a1.LeadId=b1.LeadId and b1.AddressType='P'"), array(), $selectCamp::JOIN_LEFT)
                ->join(array("d"=>"WF_CityMaster"), "b1.CityId=d.CityId", array('CityName'), $selectCamp::JOIN_LEFT)
                ->join(array("e"=>"WF_StateMaster"), "b1.StateId=e.StateID", array('StateName'), $selectCamp::JOIN_LEFT)
                ->join(array("f"=>"WF_CountryMaster"), "b1.CountryId=f.CountryId", array('CountryName'), $selectCamp::JOIN_LEFT)
                ->columns(array('UnitId','UnitNo' => new Expression("b.UnitNo"),'LeadId' => new Expression("a1.LeadId"),'LeadName' => new Expression("a1.LeadName")
                ,'Address1' => new Expression("b1.Address1"),'Address2' => new Expression("b1.Address2"),'Locality' => new Expression("b1.Locality")
                ,'PinCode' => new Expression("b1.PinCode"),'Email' => new Expression("b1.Email")
                ,'Fax' => new Expression("b1.Fax"),'PanNo' => new Expression("b1.PanNo") ))
                ->where("a.DeleteFlag='0'");
            //$selectCamp->order(new Expression("b.ProjectId,a.CampaignId asc"));
            $statement = $sql->getSqlStringForSqlObject($selectCamp);
            $CampList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrUnitLists = $CampList;
            $this->_view->reportId  = 13;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function unittransferhistoryAction(){
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
        $sql = new Sql( $dbAdapter );

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

            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";

            $selectTrans = $sql->select();
            $selectTrans->from(array("a"=>"Crm_UnitTransfer"))
                ->columns(array('RefDate'=>new Expression("Convert(Varchar(10),a.RefDate,103)"),'RefNo'=>new Expression("a.RefNo"),
                    'Buyer'=>new Expression("b.LeadName"),'FromProject'=>new Expression("g.ProjectName"),'FromFlat'=>new Expression("c.UnitNo"),
                    'ToProject'=>new Expression("h.ProjectName"),'ToFlat'=>new Expression("d.UnitNo"),'FromBlock'=>new Expression("i.BlockName"),
                    'FromFloor'=>new Expression("j.FloorName"),'ToBlock'=>new Expression("k.BlockName"),'ToFloor'=>new Expression("l.FloorName") ))
                ->join(array("b"=>"Crm_Leads"),"a.LeadId=b.LeadId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("c"=>"KF_UnitMaster"),"a.OldUnitId=c.UnitId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("d"=>"KF_UnitMaster"),"a.NewUnitId=d.UnitId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("e"=>"Crm_UnitBooking"),"a.OldBookingId=e.BookingId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("f"=>"Crm_UnitBooking"),"a.BookingId=f.BookingId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("g"=>"Proj_ProjectMaster"),"c.ProjectId=g.ProjectId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("h"=>"Proj_ProjectMaster"),"d.ProjectId=h.ProjectId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("i"=>"KF_BlockMaster"),"c.BlockId=i.BlockId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("j"=>"KF_FloorMaster"),"c.FloorId=j.FloorId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("k"=>"KF_BlockMaster"),"d.BlockId=k.BlockId",array(),$selectTrans::JOIN_LEFT)
                ->join(array("l"=>"KF_FloorMaster"),"d.FloorId=l.FloorId",array(),$selectTrans::JOIN_LEFT)
                ->where("a.RefDate Between ('$fromDate') And ('$toDate')");
            $statement = $statement = $sql->getSqlStringForSqlObject($selectTrans);
            $UnitTransferList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrUnitTransfer = $UnitTransferList;
            $this->_view->reportId  = 5;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function customerclasificationrptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $selectCamp = $sql->select();
            $selectCamp->from(array("a"=>"Crm_UnitBooking"));
            $selectCamp->columns(array('count' => new Expression("Distinct d.ProfessionId,d.Description,count(d.ProfessionId)")))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectCamp::JOIN_INNER)
                ->join(array("c"=>"Crm_LeadPOAInfo"), "a.LeadId=c.LeadId", array(), $selectCamp::JOIN_INNER)
                ->join(array("d"=>"Crm_ProfessionMaster"), "c.ProfessionId=d.ProfessionId", array(), $selectCamp::JOIN_INNER);
            $selectCamp->where("a.DeleteFlag='0' and b.Status='S'");
            $selectCamp->group(new Expression('d.ProfessionId,d.Description '));
            $statement = $statement = $sql->getSqlStringForSqlObject($selectCamp);
            $CampList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrUnitLists = $CampList;
            $this->_view->reportId  = 18;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function payabletobrokerrptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            //$asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"Crm_UnitBooking"))
                ->columns(array('BrokerId' => new Expression("a.BrokerId")))
                ->where("a.DeleteFlag='0'");

            $select = $sql->select();
            $select->from("Crm_BrokerMaster")
                ->columns(array('BrokerId','BrokerName'))
                ->where("DeleteFlag='0'");
            $select->where->expression('BrokerId IN ?', array($subQuery));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $brokerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            $i=0;
            $ParId=0;
            foreach($brokerList as &$brokerLists) {
                $i=$i+1;
                $ParId=0;

                $BrokerId=$brokerLists['BrokerId'];
                $selectBrokerAmt = $sql->select();
                $selectBrokerAmt->from(array("a"=>"Crm_UnitBooking"))
                    ->columns(array('Amount' => new Expression("Sum(a.Commission)"), 'PaidAmount' => new Expression("Sum(a.CommissionPaid)"), 'DueAmount' => new Expression("Sum(a.Commission - a.CommissionPaid)") ));
                $selectBrokerAmt->where("a.DeleteFlag='0' and a.BrokerId=$BrokerId ");
                $statement = $statement = $sql->getSqlStringForSqlObject($selectBrokerAmt);
                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $j=$i;

                //Project
                $subQuery = $sql->select();
                $subQuery->from(array("a"=>"KF_UnitMaster"))
                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $subQuery::JOIN_INNER)
                    ->columns(array('ProjectId' => new Expression("a.ProjectId")))
                    ->where("b.DeleteFlag='0' and b.BrokerId=$BrokerId");

                $select = $sql->select();
                $select->from("Proj_ProjectMaster")
                    ->columns(array('ProjectId','ProjectName'))
                    ->where->expression('ProjectId IN ?', array($subQuery));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($projectList as &$projectLists) {
                    $ProjectId=$projectLists['ProjectId'];
                    $i=$i+1;
                    $k=$i;

                    $selectProjAmt = $sql->select();
                    $selectProjAmt->from(array("a"=>"Crm_UnitBooking"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProjAmt::JOIN_INNER)
                        ->columns(array('Amount' => new Expression("Sum(a.Commission)"), 'PaidAmount' => new Expression("Sum(a.CommissionPaid)"), 'DueAmount' => new Expression("Sum(a.Commission - a.CommissionPaid)")  ));
                    $selectProjAmt->where("a.DeleteFlag='0' and a.BrokerId=$BrokerId and b.ProjectId=$ProjectId ");
                    $statement = $statement = $sql->getSqlStringForSqlObject($selectProjAmt);
                    $Projectdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //UnitNo
                    $select = $sql->select();
                    $select->from(array("a"=>"KF_UnitMaster"))
                        ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                        ->columns(array('UnitId','UnitNo' => new Expression("a.UnitNo+ ' - ' +c.LeadName "),'Amount' => new Expression("b.Commission"), 'PaidAmount' => new Expression("b.CommissionPaid"), 'DueAmount' => new Expression("b.Commission - b.CommissionPaid") ))
                        ->where("a.DeleteFlag='0' and b.BrokerId=$BrokerId and a.ProjectId=$ProjectId ");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $unitNoList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($unitNoList as &$unitNoLists) {
                        $i=$i+1;
                        $l=$i;

                        $dumArr=array();
                        $dumArr = array(
                            'Id' => $l,
                            'ParentId' => $k,
                            'Description' => $unitNoLists['UnitNo'],
                            'Amount' => $unitNoLists['Amount'],
                            'PaidAmount' => $unitNoLists['PaidAmount'],
                            'DueAmount' => $unitNoLists['DueAmount'],
                            'expanded' => 'true'
                        );
                        $arrUnitLists[] =$dumArr;

                    }

                    $dumArr=array();
                    $dumArr = array(
                        'Id' => $k,
                        'ParentId' => $j,
                        'Description' => $projectLists['ProjectName'],
                        'Amount' => $Projectdet['Amount'],
                        'PaidAmount' => $Projectdet['PaidAmount'],
                        'DueAmount' => $Projectdet['DueAmount'],
                        'expanded' => 'false'
                    );
                    $arrUnitLists[] =$dumArr;
                }

                $dumArr=array();
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'Description' => $brokerLists['BrokerName'],
                    'Amount' => $unitdet['Amount'],
                    'PaidAmount' => $unitdet['PaidAmount'],
                    'DueAmount' => $unitdet['DueAmount'],
                    'expanded' => 'false'
                );
                $arrUnitLists[] =$dumArr;
            }
            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->reportId  = 14;

            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function rentreceivablerptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $selectCamp = $sql->select();
            $selectCamp->from(array("a"=>"PM_RentalRegister"))
                ->join(array("c"=>"PM_RentalTenantTrans"), "a.RentalRegisterId=c.RentalRegisterId", array(), $selectCamp::JOIN_INNER)
                ->join(array("d"=>"KF_UnitMaster"), "a.UnitId=d.UnitId", array(), $selectCamp::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "d.ProjectId=e.ProjectId", array(), $selectCamp::JOIN_INNER)
                ->join(array("b"=>"PM_RentalPaymentScheduleTrans"), "a.RentalRegisterId=b.RentalRegisterId", array(), $selectCamp::JOIN_INNER)
                ->columns(array('UnitId','ProjectId' => new Expression("d.ProjectId"),'ProjectName' => new Expression("e.ProjectName"),'UnitNo' => new Expression("d.UnitNo")
                ,'LeaserName' => new Expression("c.LeaserName"),'Amount' => new Expression("sum(b.ScheduleAmount)"),'Received' => new Expression("sum(b.PaidAmount)"),'Balance' => new Expression("sum(b.ScheduleAmount - b.PaidAmount)") ))
                ->where("a.DeleteFlag='0' ");
            $selectCamp->group(new Expression("d.ProjectId,e.ProjectName,a.UnitId,d.UnitNo,c.LeaserName"));
            $statement = $statement = $sql->getSqlStringForSqlObject($selectCamp);
            $arrUnitLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrUnitLists = $arrUnitLists;

            $this->_view->reportId  = 4;
            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function maintenancereceivablerptAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";


            $selectCamp = $sql->select();
            $selectCamp->from(array("a"=>"PM_MaintenanceBillRegister"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectCamp::JOIN_INNER)
                ->join(array("c"=>"Proj_ProjectMaster"), "b.ProjectId=c.ProjectId", array(), $selectCamp::JOIN_INNER)
                ->columns(array('UnitId','ProjectId' => new Expression("b.ProjectId"),'ProjectName' => new Expression("c.ProjectName"),'UnitNo' => new Expression("b.UnitNo")
                ,'Amount' => new Expression("sum(a.NetAmount)"),'Received' => new Expression("sum(a.PaidAmount)"),'Balance' => new Expression("sum(a.NetAmount - a.PaidAmount)") ));
            $selectCamp->where("a.RefDate<= '$toDate' and a.RefDate>= '$fromDate' ");
            $selectCamp->group(new Expression("b.ProjectId,c.ProjectName,a.UnitId,b.UnitNo"));
            $statement = $statement = $sql->getSqlStringForSqlObject($selectCamp);
            $arrUnitLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrUnitLists = $arrUnitLists;

            $this->_view->reportId  = 9;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function campaignbudgetvsexpenserptAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $projectId = $this->params()->fromRoute('projectId');
            if($projectId=="") { $projectId=0;}

            $select = $sql->select();
            $select->from(array("a"=>"Proj_ProjectMaster"))
                ->columns(array('ProjectId', 'ProjectName'))
                ->where("a.DeleteFlag='0'");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->projectList = $projectList;
            $this->_view->projectId = $projectId;

            $totTurnaroundCost = 0;
            $select = $sql->select();
            $select->from(array("a"=>"KF_TurnaroundSchedule"))
                ->join(array("b"=>"Proj_ProjectMaster"), "a.KickoffId=b.KickoffId", array(), $select::JOIN_INNER)
                ->columns(array('Amount' => new Expression("isnull(Sum(a.Amount),0)")))
                ->where("a.CostTypeId=10");
            if($projectId!=0) {
                $select->where("b.ProjectId=$projectId");
            }
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $budgetCostList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($budgetCostList as &$budgetCostLists) {
                $totTurnaroundCost=$budgetCostLists['Amount'];
            }
            $this->_view->totTurnaroundCost = $totTurnaroundCost;

            $select = $sql->select();
            $select->from(array("a"=>"Crm_CampaignRegister"))
                ->join(array("b"=>"Crm_OpportunityMaster"), "a.OpportunityId=b.OpportunityId", array(), $select::JOIN_INNER)
                ->join(array("c"=>"Crm_CampaignProjectTrans"), "a.CampaignId=c.CampaignId", array(), $select::JOIN_INNER)
                ->columns(array('OpportunityName' => new Expression("b.OpportunityName"), 'Amount' => new Expression("isnull(Sum(c.Amount),0)")))
                ->where("a.DeleteFlag='0'");
            if($projectId!=0) {
                $select->where("c.ProjectId=$projectId");
            }
            $select->group(new Expression('b.OpportunityName'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $campaignList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->campaignList = $campaignList;

            $totCampaignCost = 0;
            $select = $sql->select();
            $select->from(array("a"=>"Crm_CampaignRegister"))
                ->join(array("b"=>"Crm_OpportunityMaster"), "a.OpportunityId=b.OpportunityId", array(), $select::JOIN_INNER)
                ->join(array("c"=>"Crm_CampaignProjectTrans"), "a.CampaignId=c.CampaignId", array(), $select::JOIN_INNER)
                ->columns(array('Amount' => new Expression("isnull(Sum(c.Amount),0)")))
                ->where("a.DeleteFlag='0'");
            if($projectId!=0) {
                $select->where("c.ProjectId=$projectId");
            }
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $campaignAmtList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($campaignAmtList as &$campaignAmtLists) {
                $totCampaignCost = $campaignAmtLists['Amount'];
            }
            $this->_view->totCampaignCost = $totCampaignCost;
            $balCampaignCost = 0;

            $balCampaignCost = $totTurnaroundCost - $totCampaignCost;
            if ($balCampaignCost <0) $balCampaignCost = 0;
            $this->_view->balCampaignCost = $balCampaignCost;

            $temparr = array();

            $temparr[0]['BudgetSpent'] = "BudgetAmount";
            $temparr[0]['Amount'] = $totTurnaroundCost;

            $temparr[1]['BudgetSpent'] = "CampaignExpense";
            $temparr[1]['Amount'] = $totCampaignCost;

            $temparr[2]['BudgetSpent'] = "BalanceAmount";
            $temparr[2]['Amount'] = $balCampaignCost;

            if($temparr[0]['Amount']== '0' && $temparr[1]['Amount']== '0') {
                $temparr=[];
            }
            $this->_view->arrCostList = $temparr;


            $arrbal = array();
            if ($totTurnaroundCost !=0) {
                $dSpend = ($totCampaignCost/$totTurnaroundCost)*100;
                if ($dSpend >100) $dSpend=100;
                $arrbal[0]['Type'] = "Spent";
                $arrbal[0]['Amount'] = $dSpend;

                $arrbal[1]['Type'] = "Balance";
                $arrbal[1]['Amount'] = 100-$dSpend;
            } else {
                $arrbal[0]['Type'] = "Spent";
                $arrbal[0]['Amount'] = 0;

                $arrbal[1]['Type'] = "Balance";
                $arrbal[1]['Amount'] = 0;
            }
            $this->_view->arrbal = $arrbal;
            $this->_view->reportId  = 19;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function projectstatusAction(){
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
        $sql = new Sql( $dbAdapter );
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

            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";

            $projectId = $this->params()->fromRoute('projectId');

            $select = $sql->select();
            $select->from(array("a"=>"Crm_UnitBooking"))
                ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                ->join(array("e"=>"Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array(), $select::JOIN_INNER)
                ->columns(array('ProjectId' => new Expression("b.ProjectId"), 'ProjectName' => new Expression("e.ProjectName")))
                ->where("a.DeleteFlag='0'");
            $select->group(new Expression('b.ProjectId,e.ProjectName '));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectList = $projectList;
            $this->_view->projectId = $projectId;

            $datedet = array();
            if($fromDate<=$toDate) {
                $select = $sql->select();
                $select->from("")
                    ->columns(array('Monthcount'=> new Expression("DATEDIFF(MONTH,'$fromDate','$toDate') + 1")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $mothCOuntList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $cont=$mothCOuntList[0]['Monthcount'];

                for($i=0; $i<$cont; $i++) {
                    $tDate = date('Y-m-d', strtotime("+". $i ." month", strtotime($fromDate)));
                    $Month = date('M', strtotime($tDate));
                    $MonthNo = date('m', strtotime($tDate));
                    $Year = date('Y', strtotime($tDate));
                    $monthText = $Month . ', '. $Year;

                    $dumArr=array();
                    $dumArr = array(
                        'Year' => $Year,
                        'Month' => $MonthNo,
                        'Description' => $monthText
                    );

                    $selectUnitSale = $sql->select();
                    $selectUnitSale->from(array("a"=>"Crm_UnitBooking"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitSale::JOIN_INNER)
                        ->columns(array('Totalsale' => new Expression("count(a.BookingId)"),'Totalcancellation' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ,'AdvAmount' => new Expression("1-1") ));
                    $selectUnitSale->where("a.DeleteFlag='0'");
                    if($projectId!=0) {
                        $selectUnitSale->where("b.ProjectId=$projectId");
                    }
                    $selectUnitSale->where("MONTH(a.BookingDate)=$MonthNo and Year(a.BookingDate)=$Year ");

                    $selectUnitCancel = $sql->select();
                    $selectUnitCancel->from(array("a"=>"Crm_UnitCancellation"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectUnitCancel::JOIN_INNER)
                        ->columns(array('Totalsale' => new Expression("1-1"),'Totalcancellation' => new Expression("count(a.CancellationId)"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1") ,'AdvAmount' => new Expression("1-1") ));
                    if($projectId!=0) {
                        $selectUnitCancel->where("b.ProjectId=$projectId");
                    }
                    $selectUnitCancel->where("MONTH(a.RefDate)=$MonthNo and Year(a.RefDate)=$Year ");
                    $selectUnitCancel->combine($selectUnitSale,'Union ALL');

                    $selectProgAmt = $sql->select();
                    $selectProgAmt->from(array("a"=>"Crm_ProgressBillTrans"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectProgAmt::JOIN_INNER)
                        ->join(array("c"=>"Crm_ProgressBill"), "a.ProgressBillId=c.ProgressBillId", array(), $selectProgAmt::JOIN_INNER)
                        ->columns(array('Totalsale' => new Expression("1-1"),'Totalcancellation' => new Expression("1-1"),'RecvAmount' => new Expression("Sum(a.NetAmount)"), 'RecdAmount' => new Expression("1-1")  ,'AdvAmount' => new Expression("1-1")));
                    $selectProgAmt->where("c.DeleteFlag='0'");
                    $selectProgAmt->where("a.CancelId='0'");
                    if($projectId!=0) {
                        $selectProgAmt->where("b.ProjectId=$projectId");
                    }
                    $selectProgAmt->where("MONTH(a.BillDate)=$MonthNo and Year(a.BillDate)=$Year ");
                    $selectProgAmt->combine($selectUnitCancel,'Union ALL');

                    $selectreceiptAmt = $sql->select();
                    $selectreceiptAmt->from(array("a"=>"Crm_ReceiptAdjustment"))
                        ->join(array("b"=>"Crm_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->join(array("c"=>"KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $selectreceiptAmt::JOIN_INNER)
                        ->columns(array('Totalsale' => new Expression("1-1"),'Totalcancellation' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("Sum(a.NetAmount)") ,'AdvAmount' => new Expression("1-1")));
                    $selectreceiptAmt->where("b.DeleteFlag='0'");
                    $selectreceiptAmt->where("b.CancelId='0'");
                    if($projectId!=0) {
                        $selectreceiptAmt->where("c.ProjectId=$projectId");
                    }
                    $selectreceiptAmt->where("MONTH(b.ReceiptDate)=$MonthNo and Year(b.ReceiptDate)=$Year  ");
                    $selectreceiptAmt->combine($selectProgAmt,'Union ALL');


                    $selectP1 = $sql->select();
                    $selectP1->from(array("a"=>"Crm_ReceiptRegister"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $selectP1::JOIN_INNER)
                        //  ->join(array("c"=>"Crm_ReceiptAdjustment"), "a.ReceiptId=c.ReceiptId", array(), $selectP1::JOIN_LEFT)
                        ->columns(array('Totalsale' => new Expression("1-1"),'Totalcancellation' => new Expression("1-1"),'RecvAmount' => new Expression("1-1"), 'RecdAmount' => new Expression("1-1")  ,'AdvAmount' => new Expression("Sum(a.Amount)")));
                    // $selectP1->where("a.DeleteFlag='0'");
                    $selectP1->where("a.ReceiptAgainst='A'","a.DeleteFlag='0'","a.CancelId='0");
                    if($projectId!=0) {
                        $selectP1->where("b.ProjectId=$projectId");
                    }
                    $selectP1->where("MONTH(a.ReceiptDate)=$MonthNo and Year(a.ReceiptDate)=$Year  ");
                    $selectP1->combine($selectreceiptAmt,'Union ALL');



                    $select3 = $sql->select();
                    $select3->from(array("g"=>$selectP1))
                        ->columns(array('Totalsale' => new Expression("Sum(g.Totalsale)"),'Totalcancellation' => new Expression("Sum(g.Totalcancellation)"),'AdvAmount' => new Expression("Sum(g.AdvAmount)"),'RecvAmount' => new Expression("Sum(g.RecvAmount)"), 'RecdAmount' => new Expression("Sum(g.RecdAmount)") ));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                    $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['Totalsale']=$unitdet['Totalsale'];
                    $dumArr['Totalcancellation']=$unitdet['Totalcancellation'];
                    $dumArr['Netsales']=$unitdet['Totalsale'] - $unitdet['Totalcancellation'];
                    $dumArr['Receivable']=$unitdet['RecvAmount'];
                    $dumArr['AdvAmount']=$unitdet['AdvAmount'];
                    $dumArr['Received']=$unitdet['RecdAmount']+$unitdet['AdvAmount'];
                    $dumArr['Balance']=$unitdet['RecvAmount'] - $unitdet['RecdAmount'];
                    $arrUnitLists[] =$dumArr;
                }
            }

            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->reportId  = 16;
            /*echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function unitcancelhistoryAction(){
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
        $sql = new Sql( $dbAdapter );

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

            } else {

                $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

                $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
                $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

                switch ($filterWise) {
                    case 0:
                        $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                        $this->_view->todat = $this->params()->fromRoute('toDate');
                        break;
                    case 1:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                        break;
                    case 2:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                        break;
                    case 3:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                        break;
                    case 4:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                        break;
                    case 5:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                        break;
                    case 6:
                        $this->_view->todat= date('Y-m-d')." 23:59:59";
                        $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                        break;
                    default:
                        $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                        $this->_view->todat = $this->params()->fromRoute('toDate');
                }
                if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                    $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
                }

                if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                    if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                        $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                    } else {
                        $toDate= date('Y-m-d', strtotime($this->_view->todat));
                    }

                }

                $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
                $this->_view->todat=date('d-m-Y', strtotime($toDate));
                $this->_view->filterWise=$filterWise;
                $toDate=$toDate." 23:59:59";

                $selectTrans = $sql->select();
                $selectTrans->from(array("a"=>"Crm_UnitCancellation"))
                    ->columns(array('RefDate'=>new Expression("Convert(Varchar(10),a.RefDate,103)"),'RefNo', 'UnitId','CancellationId', 'BookingId', 'LeadId', 'PaidAmt','CancellationAmt', 'OtherDetectionAmt',
                        'PayableAmt','Remarks','CreatedDate'=>new Expression("Convert(Varchar(10),a.CreatedDate,103)"),'Approve'=>new Expression("a.Approve") ))
                    ->join(array("b"=>"Crm_Leads"),"a.LeadId=b.LeadId",array("LeadName"),$selectTrans::JOIN_LEFT)
                    ->join(array("k"=>"Crm_UnitBooking"),"a.BookingId=k.BookingId",array("BookingNo"),$selectTrans::JOIN_LEFT)
                    ->join(array("j"=>"Crm_LeadFollowUp"),"j.EntryId=a.FollowUpId",array('ExecutiveId'),$selectTrans::JOIN_LEFT)
                    ->join(array("h"=>"Wf_Users"),"j.ExecutiveId=h.UserId",array("EmployeeName"),$selectTrans::JOIN_LEFT)
                    ->join(array("c"=>"KF_UnitMaster"),"a.UnitId=c.UnitId",array("UnitNo"),$selectTrans::JOIN_LEFT)
                    ->join(array("g"=>"Proj_ProjectMaster"),"c.ProjectId=g.ProjectId",array(),$selectTrans::JOIN_LEFT);
                $selectTrans->where->greaterThanOrEqualTo('RefDate', $fromDate)
                    ->lessThanOrEqualTo('RefDate', $toDate);
                $statement = $sql->getSqlStringForSqlObject($selectTrans);
                $this->_view->arrUnitTransfer = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->reportId  = 10;

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function blockHistoryAction() {

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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here
                    $data="";
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($data));
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {
                    $blockType = $this->bsf->isNullCheck($this->params()->fromRoute('blockType'), 'number' );

                    $select = $sql->select();
                    $select->from(array('c' => 'Crm_UnitBlock'))
                        ->columns(array('BlockId',
                            'BlockNo','NetAmt'=>'NetAmount','BookingDate' => new Expression('Convert(varchar(11), BookingDate,101)'),
                            'Rate'=>'BRate', 'LeadId','ValidUpto' => new Expression('Convert(varchar(11), ValidUpto,103)')))
                        ->join(array('a' => 'KF_UnitMaster'), 'c.UnitId=a.UnitId', array('UnitName' =>'UnitNo','UnitId'))
                        ->join(array('e' => 'Proj_ProjectMaster'), 'e.ProjectId=a.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array('b' => 'Crm_Leads'), 'b.LeadId=c.LeadId', array('LeadName'), $select::JOIN_LEFT);
                    if($blockType==0) {
                        $select->where(array('c.DeleteFlag' => 0));
                    } else {
                        $select->where(array('c.DeleteFlag' => 1));
                    }

                    $select->order('c.BlockId desc');
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrBookings = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonBookings = json_encode($arrBookings);
                    $this->_view->blockType=$blockType;
                    $this->_view->reportId  = 15;

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function prebookHistoryAction() {

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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $preBookType = $this->bsf->isNullCheck($this->params()->fromRoute('preBookType'), 'number' );
                    $select = $sql->select();
                    $select->from(array('c' => 'Crm_UnitPreBooking'))
                        ->columns(array('PreBookingId',
                            'BookingNo','BookingDate' => new Expression('Convert(varchar(11), BookingDate,101)'),
                            'PRate', 'NetAmount','LeadId','ValidUpTo' => new Expression('Convert(varchar(11), ValidUpTo,103)')))
                        ->join(array('a' => 'KF_UnitMaster'), 'c.UnitId=a.UnitId', array('UnitNo','UnitId'), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectMaster'), 'e.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array('b' => 'Crm_Leads'), 'b.LeadId=c.LeadId', array('LeadName'), $select::JOIN_LEFT);
//                        ->join(array('d' => 'Crm_UnitDetails'), 'd.UnitId=c.UnitId', array('NetAmt','OtherCostAmt'), $select::JOIN_LEFT)
                    if($preBookType==0) {
                        $select->where(array('c.DeleteFlag' => 0));
                    } else {
                        $select->where(array('c.DeleteFlag' => 1));
                    }
                    $select->order('c.PreBookingId desc');
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPreBookings = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->jsonPreBookings = json_encode($arrPreBookings);

                    $this->_view->preBookType=$preBookType;
                    $this->_view->reportId  = 20;


                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function executiveNextFollowupAction(){
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
        $sql = new Sql( $dbAdapter );
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
            $filterWise = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

            $fromDate=date('Y-m-d', strtotime(date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(date('d-m-Y')));

            switch ($filterWise) {
                case 0:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
                    break;
                case 1:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 days'));

                    break;
                case 2:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-7 days'));

                    break;
                case 3:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-30 days'));

                    break;
                case 4:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-3 months'));;

                    break;
                case 5:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-6 months'));;

                    break;
                case 6:
                    $this->_view->todat= date('Y-m-d')." 23:59:59";
                    $this->_view->fromdat = date('Y-m-d', strtotime(date('Y-m-d') . '-12 months'));;

                    break;
                default:
                    $this->_view->fromdat = $this->params()->fromRoute('fromDate');
                    $this->_view->todat = $this->params()->fromRoute('toDate');
            }
            if($this->_view->fromdat!="" && strtotime($this->_view->fromdat)!=false){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }

            if($this->_view->todat!="" && strtotime($this->_view->todat)!=false && strtotime($this->_view->fromdat)){
                if(strtotime($this->_view->fromdat) > strtotime($this->_view->todat)) {
                    $toDate= date('Y-m-d', strtotime($this->_view->fromdat));

                } else {
                    $toDate= date('Y-m-d', strtotime($this->_view->todat));
                }

            }

            $this->_view->fromdat= date('d-m-Y', strtotime($fromDate));
            $this->_view->todat=date('d-m-Y', strtotime($toDate));
            $this->_view->filterWise=$filterWise;
            $toDate=$toDate." 23:59:59";


            $subQuery = $sql->select();
            $subQuery->from(array("a"=>"Crm_LeadFollowup"))
                //->join(array("b"=>"Crm_TargetRegister"), "a.TargetId=b.TargetId", array(), $subQuery::JOIN_INNER)
                ->columns(array('ExecutiveId'));
            if($fromDate !=""){
                $subQuery->where("a.DeleteFlag='0' and a.Completed = '0' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
            }

            $select = $sql->select();
            $select->from("WF_Users")
                ->columns(array('UserId','EmployeeName'))
                ->where->expression('UserId IN ?', array($subQuery));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $execList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists= array();
            foreach($execList as &$execLists) {
                $UserId=$execLists['UserId'];

                $dumArr = array(
                    'UserId' => $execLists['UserId'],
                    'ExecutiveName' => $execLists['EmployeeName'],
                    'ClientFollowed' => 0,
                    'Blocked' => 0,
                    'Finalization' => 0,
                    'NoOfSiteVisit' => 0,
                    'ClientPlaceVisit' => 0,
                    'AddressCollection' => 0,
                    'Proposal' => 0,
                    'PreBooking' => 0
                );

                //ClientFollowed
                $selectClientFollowed = $sql->select();
                $selectClientFollowed->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("Count(EntryId)"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"),  'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectClientFollowed->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=1 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }

                //Blocked
                $selectBlocked = $sql->select();
                $selectBlocked->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("Count(EntryId)"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"),  'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectBlocked->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=2 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectBlocked->combine($selectClientFollowed,'Union ALL');


                //Finalization
                $selectFinalization = $sql->select();
                $selectFinalization->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("Count(EntryId)"),'NoOfSiteVisit' => new Expression("1-1"),  'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectFinalization->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=4 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectFinalization->combine($selectBlocked,'Union ALL');

                //NoOfSiteVisit
                $selectNoOfSiteVisit = $sql->select();
                $selectNoOfSiteVisit->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' =>  new Expression("Count(EntryId)"),  'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectNoOfSiteVisit->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=5 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectNoOfSiteVisit->combine($selectFinalization,'Union ALL');

                //ClientPlaceVisit
                $selectClientPlaceVisit = $sql->select();
                $selectClientPlaceVisit->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"), 'ClientPlaceVisit' => new Expression("Count(EntryId)"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectClientPlaceVisit->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=6 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectClientPlaceVisit->combine($selectNoOfSiteVisit,'Union ALL');

                //AddressCollection
                $selectAddressCollection = $sql->select();
                $selectAddressCollection->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"), 'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("Count(EntryId)"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectAddressCollection->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=7 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectAddressCollection->combine($selectClientPlaceVisit,'Union ALL');

                //Proposal
                $selectProposal = $sql->select();
                $selectProposal->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"), 'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("Count(EntryId)"),'PreBooking' => new Expression("1-1") ));
                if($fromDate !=""){
                    $selectProposal->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=8 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectProposal->combine($selectAddressCollection,'Union ALL');

                //PreBooking
                $selectPreBooking = $sql->select();
                $selectPreBooking->from(array("a"=>"Crm_LeadFollowup"))
                    ->columns(array('ClientFollowed' => new Expression("1-1"),'Blocked'=>new Expression("1-1"),'Finalization' => new Expression("1-1"),'NoOfSiteVisit' => new Expression("1-1"), 'ClientPlaceVisit' => new Expression("1-1"),'AddressCollection' => new Expression("1-1"),'Proposal' => new Expression("1-1"),'PreBooking' => new Expression("Count(EntryId)") ));
                if($fromDate !=""){
                    $selectPreBooking->where("a.DeleteFlag='0' and a.Completed = '0' and NextFollowUpTypeId=9 and a.ExecutiveId='$UserId' and a.NextCallDate>='$fromDate' and a.NextCallDate<= '$toDate'");
                }
                $selectPreBooking->combine($selectProposal,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$selectPreBooking))
                    ->columns(array('ClientFollowed' => new Expression("Sum(g.ClientFollowed)"),'Blocked'=>new Expression("Sum(g.Blocked)"),'Finalization' => new Expression("Sum(g.Finalization)"),'NoOfSiteVisit' => new Expression("Sum(g.NoOfSiteVisit)"), 'ClientPlaceVisit' => new Expression("Sum(g.ClientPlaceVisit)"),'AddressCollection' => new Expression("Sum(g.AddressCollection)"),'Proposal' => new Expression("Sum(g.Proposal)"),'PreBooking' => new Expression("Sum(g.PreBooking)")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $dumArr['ClientFollowed'] = $unitdet['ClientFollowed'];
                $dumArr['Blocked'] = $unitdet['Blocked'];
                $dumArr['Finalization'] = $unitdet['Finalization'];
                $dumArr['NoOfSiteVisit'] = $unitdet['NoOfSiteVisit'];
                $dumArr['ClientPlaceVisit'] = $unitdet['ClientPlaceVisit'];
                $dumArr['AddressCollection'] = $unitdet['AddressCollection'];
                $dumArr['Proposal'] = $unitdet['Proposal'];
                $dumArr['PreBooking'] = $unitdet['PreBooking'];

                $arrUnitLists[] =$dumArr;
            }

            $this->_view->arrUnitLists = $arrUnitLists;
            $this->_view->reportId  = 21;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }


    public function unitDiscountAction(){
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
        $sql = new Sql( $dbAdapter );
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
            try {
                $projectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectId'), 'number' );
                $this->_view->projectId=$projectId;

                $select = $sql->select();
                $select->from("Proj_ProjectMaster")
                    ->columns(array('ProjectId','ProjectName'))
                    ->order('ProjectId Desc');
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
                    ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('DefaultGross'=>new Expression("b.GrossAmount")), $select::JOIN_LEFT)
                    ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('BOther'=>'OtherCostAmount','BBase'=>'BaseAmount'), $select::JOIN_LEFT)
                    ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','gross'=>'GrossAmount'), $select::JOIN_LEFT)
                    ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                    ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
                    ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->join(array("l"=>"Proj_ProjectMaster"), "l.ProjectId=a.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                    ->where(array("a.Status"=>"S"));
                if($projectId!=0) {
                    $select->where(array("a.ProjectId"=>$projectId));
                }
                $select->order("o.PostSaleDiscountId desc");
                $stmt = $sql->getSqlStringForSqlObject($select);
                $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
                    ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                    ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
                    ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
                    ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
                    ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
                    ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                    ->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                    ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName','LeadId' ), $select::JOIN_LEFT)
                    ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                    ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
                    ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
                    ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
                    ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->join(array("l"=>"Proj_ProjectMaster"), "l.ProjectId=a.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                    ->join(array('ff' => 'Crm_UnitProposal'), 'b.UnitId=ff.UnitId',array ("ProDiscountType"=>'DiscountType','ProposalId',"ProDiscount"=>'Discount'), $select::JOIN_LEFT)
                   ->order("o.PostSaleDiscountId desc");
                if($projectId!=0) {
                    $select->where(array("a.ProjectId"=>$projectId));
                }
                $stmt = $sql->getSqlStringForSqlObject($select);
                $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                if(!isset($unitInfo) || count($unitInfo)==0) {
                    throw new \Exception('Unit not found!');
                }

                $unitArray=array();
                $gross='-';
                $discount='-';

                foreach($unitInfo as $unitInfo) {
                    $unitInfo['DefaultGross']='-';
                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId,a.UnitTypeId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId"=>$unitInfo['UnitId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $unitInfo['UnitId']=$unitInfo['UnitId'];
                    $unitInfo['UnitNo']=$unitInfo['UnitNo'];
                    $unitInfo['DefaultGross']=$unitIn['GrossAmount'];
                    if($unitInfo['Status'] == 'B') {
                        $gross=$unitInfo['Blockgross'];
                        $discount=$unitInfo['BlockDiscount'];
                    }
                    else if($unitInfo['Status'] == 'P') {
                        $gross=$unitInfo['Pregross'];
                        $discount=$unitInfo['PreDiscount'];
                    }
                    else if($unitInfo['Status'] == 'U') {
                        $gross=$unitIn['GrossAmount'];
                        $discount=$unitIn['Discount'];
                    }
                    else if($unitInfo['Status'] == 'R') {
                        $gross=$unitInfo['gross'];
                        $discount=$unitInfo['PostDiscount'];
                    }

                    if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                        $gross= $unitInfo['gross'];+ $unitInfo['qual'];
                        $discount= $unitInfo['PostDiscount'];
                    }
                    else if($unitInfo['count'] == 1) {
                        $gross= $unitInfo['BBase']+ $unitInfo['BOther'];
                        $discount= $unitInfo['BDiscount'];
                    }

                  //  $discount = floatval($unit['DefaultGross'])-floatval($gross);
                    $unitArray[]=array('UnitId'=>$unitInfo['UnitId'],'UnitName'=>$unitInfo['UnitNo'],'Gross'=>$gross,'BGross'=>$unitInfo['DefaultGross'],'Discount'=>$discount);
                }

                $this->_view->unitArray=$unitArray;
            } catch(\Exception $ex) {
                $this->_view->err = $ex->getMessage();
            }
            $this->_view->reportId=22;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }


}