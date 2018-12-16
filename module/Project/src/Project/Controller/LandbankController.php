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

class LandbankController extends AbstractActionController
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();

        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function enquiryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || LandBank Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $enqId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
                if($enqId == 0) {
                    $PropertyName = $this->bsf->isNullCheck($request->getPost('Propname'), 'string');
                    $type = $this->bsf->isNullCheck($request->getPost('type'), 'string');
                    if($type == 'Property') {
                        $select = $sql->select();
                        $select->from('Proj_LandEnquiry')
                            ->where(array("PropertyName"=>$PropertyName));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_Prop = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($arr_Prop) != 0) {
                            $Property = 'N';
                        } else {
                            $Property = 'Y';
                        }

                        $response = $this->getResponse();
                        $response->setContent(json_encode($Property));
                        return $response;
                    }
                }
            }
        } else {

            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
//                echo '<pre>'; print_r($postData); die;
                    $iEnquiryId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');

                    $files = $request->getFiles();

                    // property image file upload
                    $imgUrl = '';
                    if ($files['propImage']['name'] && !isset($postData['propImage'])) {

                        $dir = 'public/uploads/project/landenquiry/propimages/';
                        $filename = $this->bsf->uploadFile($dir, $files['propImage']);

                        if ($filename) {
                            // update valid files only
                            $imgUrl = '/uploads/project/landenquiry/propimages/' . $filename;
                        }
                    } else if(isset($postData['propImage'])) {
                        $imgUrl = $postData['propImage'];
                    }

                    if ($iEnquiryId!=0) {

                       // echo "if";die;
                        $update = $sql->update();
                        $update->table('Proj_LandEnquiry');
                        $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                        , 'PropertyName' => $this->bsf->isNullCheck($postData['PropertyName'], 'string'), 'SourceId' => $this->bsf->isNullCheck($postData['SourceId'], 'number')
                        , 'LandCost' => $this->bsf->isNullCheck($postData['LandCost'], 'number'), 'SourceName' => $this->bsf->isNullCheck($postData['SourceName'], 'string')
                        , 'TotalArea' => $this->bsf->isNullCheck($postData['TotalArea'], 'number'), 'TotalAreaUnitId' => $this->bsf->isNullCheck($postData['TotalAreaUnitId'], 'number')
                        , 'PropertyLocation' => $this->bsf->isNullCheck($postData['PropertyLocation'], 'string'), 'SaleTypeId' => $this->bsf->isNullCheck($postData['SaleTypeId'], 'number')
                        , 'Email' => $this->bsf->isNullCheck($postData['Email'], 'string'), 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                        , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number'), 'RoadFacingDirection' => $this->bsf->isNullCheck($postData['RoadFacingDirection'], 'string')
                        , 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'], 'string'), 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'], 'string')
                        , 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number'), 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number')
                        , 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number'), 'PropertyAddress' => $this->bsf->isNullCheck($postData['PropertyAddress'], 'string')
                        , 'NLandMark' => $this->bsf->isNullCheck($postData['NLandMark'], 'string'), 'NRailwayStation' => $this->bsf->isNullCheck($postData['NRailwayStation'], 'string')
                        , 'NHospital' => $this->bsf->isNullCheck($postData['NHospital'], 'string'), 'NFireStation' => $this->bsf->isNullCheck($postData['NFireStation'], 'string')
                        , 'NAirport' => $this->bsf->isNullCheck($postData['NAirport'], 'string'), 'NPoliceStation' => $this->bsf->isNullCheck($postData['NPoliceStation'], 'string')
                        , 'NBusStop' => $this->bsf->isNullCheck($postData['NBusStop'], 'string'), 'NHotel' => $this->bsf->isNullCheck($postData['NHotel'], 'string')
                        , 'CityName' => $this->bsf->isNullCheck($postData['CityName'], 'string')
                        , 'PinCode' => $this->bsf->isNullCheck($postData['PinCode'], 'string')
                        , 'NGrocery' => $this->bsf->isNullCheck($postData['NGrocerystore'], 'string')
                        , 'NSchool' => $this->bsf->isNullCheck($postData['NSchool'], 'string')
                        , 'NBank' => $this->bsf->isNullCheck($postData['NBank'], 'string')
                        , 'LandMark1' => $this->bsf->isNullCheck($postData['Landmark1'], 'string')
                        , 'LandMark2' => $this->bsf->isNullCheck($postData['Landmark2'], 'string')
                        , 'LandMark3' => $this->bsf->isNullCheck($postData['Landmark3'], 'string')
                        , 'BusDistance' => $this->bsf->isNullCheck($postData['BusDistance'], 'number')
                        , 'RailDistance' => $this->bsf->isNullCheck($postData['RailDistance'], 'number')
                        , 'HospitalDistance' => $this->bsf->isNullCheck($postData['HospitalDistance'], 'number')
                        , 'AirportDistance' => $this->bsf->isNullCheck($postData['AirportDistance'], 'number')
                        , 'HotelDistance' => $this->bsf->isNullCheck($postData['HotelDistance'], 'number')
                        , 'FireDistance' => $this->bsf->isNullCheck($postData['FireDistance'], 'number')
                        , 'PoliceDistance' => $this->bsf->isNullCheck($postData['PoliceDistance'], 'number')
                        , 'GroceryDistance' => $this->bsf->isNullCheck($postData['GroceryDistance'], 'number')
                        , 'SchoolDistance' => $this->bsf->isNullCheck($postData['SchoolDistance'], 'number')
                        , 'BankDistance' => $this->bsf->isNullCheck($postData['BankDistance'], 'number')
                        , 'BankUnitId' => $this->bsf->isNullCheck($postData['BankUnitId'], 'number')
                        , 'GroceryUnitId' => $this->bsf->isNullCheck($postData['GroceryUnitId'], 'number')
                        , 'PoliceUnitId' => $this->bsf->isNullCheck($postData['PoliceUnitId'], 'number')
                        , 'FireUnitId' => $this->bsf->isNullCheck($postData['FireUnitId'], 'number')
                        , 'HotelUnitId' => $this->bsf->isNullCheck($postData['HotelUnitId'], 'number')
                        , 'AirportUnitId' => $this->bsf->isNullCheck($postData['AirportUnitId'], 'number')
                        , 'HospitalUnitId' => $this->bsf->isNullCheck($postData['HospitalUnitId'], 'number')
                        , 'RailUnitId' => $this->bsf->isNullCheck($postData['RailUnitId'], 'number')
                        , 'BusUnitId' => $this->bsf->isNullCheck($postData['BusUnitId'], 'number')
                        , 'SchoolUnitId' => $this->bsf->isNullCheck($postData['SchoolUnitId'], 'number')
                        , 'CityId' => $this->bsf->isNullCheck($postData['CityId'], 'number')
                        , 'Notes' => $this->bsf->isNullCheck($postData['Notes'], 'string')
                        , 'Latitude' => $this->bsf->isNullCheck($postData['us3-lat'], 'number')
                        , 'Longitude' => $this->bsf->isNullCheck($postData['us3-lon'], 'number'),'Radius' => $this->bsf->isNullCheck($postData['us3-radius'], 'number')
                        , 'Location' => $this->bsf->isNullCheck($postData['us3-address'], 'string')
                        , 'BrokerId' => $this->bsf->isNullCheck($postData['BrokerId'], 'number') ,'PropImageURL' => $imgUrl));
                        $update->where(array('EnquiryId'=>$iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery1 = $sql->select();
                        $subQuery1->from(array('a' => 'Proj_LandOwnerDetail'))
                            ->columns(array('OwnerId'))
                            ->where("a.EnquiryId=$iEnquiryId");

                        $delete = $sql->delete();
                        $delete->from('Proj_LandCoOwnerDetail')
                            ->where->expression('OwnerId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_LandOwnerDetail')
                            ->where("EnquiryId=$iEnquiryId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // road details
                        $roadrowid = $this->bsf->isNullCheck($postData['roadrowid'], 'number');
                        for ($i = 1; $i <= $roadrowid; $i++) {
                            $RoadDetailId = $this->bsf->isNullCheck($postData['RoadDetailId_' . $i], 'number');
                            $RoadName = $this->bsf->isNullCheck($postData['RoadName_' . $i], 'string');
                            $AbutRoadWidth = $this->bsf->isNullCheck($postData['AbutRoadWidth_' . $i], 'number');
                            $AbutRoadWidthUnitId = $this->bsf->isNullCheck($postData['AbutRoadWidthUnitId_' . $i], 'number');
                            $ApproThroPass = $this->bsf->isNullCheck($postData['ApproThroPass_' . $i], 'number');
                            $PropFrontage = $this->bsf->isNullCheck($postData['PropFrontage_' . $i], 'number');
                            $PropFrontageUnitId = $this->bsf->isNullCheck($postData['PropFrontageUnitId_' . $i], 'number');
                            $WidthPassage = $this->bsf->isNullCheck($postData['WidthPassage_' . $i], 'number');
                            $WidthPassageUnitId = $this->bsf->isNullCheck($postData['WidthPassageUnitId_' . $i], 'number');
                            $LengthPassage = $this->bsf->isNullCheck($postData['LengthPassage_' . $i], 'number');
                            $LengthPassageUnitId = $this->bsf->isNullCheck($postData['LengthPassageUnitId_' . $i], 'number');
                            $RWidthRule250 = $this->bsf->isNullCheck($postData['RWidthRule250_' . $i], 'number');
                            $RWidthRule500 = $this->bsf->isNullCheck($postData['RWidthRule500_' . $i], 'number');
                            $RoadDirection = $this->bsf->isNullCheck($postData['RoadDirection_' . $i], 'string');
                            $RoadLevel = $this->bsf->isNullCheck($postData['RoadLevel_' . $i], 'number');
                            $RoadLevelUnitId = $this->bsf->isNullCheck($postData['RoadLevelUnitId_' . $i], 'number');
                            $RoadWidening = $this->bsf->isNullCheck($postData['RoadWidening_' . $i], 'number');

                            if($RoadDetailId == 0) {
                                $insert = $sql->insert();
                                $insert->into('Proj_LandRoadDetail');
                                $insert->Values(array('EnquiryId'=>$iEnquiryId,'RoadName' => $RoadName, 'AbutRoadWidth' => $AbutRoadWidth,
                                    'AbutRoadWidthUnitId' => $AbutRoadWidthUnitId, 'ApproThroPass' => $ApproThroPass, 'PropFrontage' => $PropFrontage,
                                    'PropFrontageUnitId' => $PropFrontageUnitId, 'WidthPassage' => $WidthPassage, 'WidthPassageUnitId' => $WidthPassageUnitId,
                                    'LengthPassage' => $LengthPassage, 'LengthPassageUnitId' => $LengthPassageUnitId, 'RWidthRule250' => $RWidthRule250,
                                    'RWidthRule500' => $RWidthRule500, 'RoadDirection' => $RoadDirection, 'RoadLevel' => $RoadLevel,
                                    'RoadLevelUnitId' => $RoadLevelUnitId, 'RoadWidening' => $RoadWidening, 'SortId' => $i
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                $update = $sql->update();
                                $update->table('Proj_LandRoadDetail');
                                $update->set(array('EnquiryId'=>$iEnquiryId,'RoadName' => $RoadName, 'AbutRoadWidth' => $AbutRoadWidth,
                                    'AbutRoadWidthUnitId' => $AbutRoadWidthUnitId, 'ApproThroPass' => $ApproThroPass, 'PropFrontage' => $PropFrontage,
                                    'PropFrontageUnitId' => $PropFrontageUnitId, 'WidthPassage' => $WidthPassage, 'WidthPassageUnitId' => $WidthPassageUnitId,
                                    'LengthPassage' => $LengthPassage, 'LengthPassageUnitId' => $LengthPassageUnitId, 'RWidthRule250' => $RWidthRule250,
                                    'RWidthRule500' => $RWidthRule500, 'RoadDirection' => $RoadDirection, 'RoadLevel' => $RoadLevel,
                                    'RoadLevelUnitId' => $RoadLevelUnitId, 'RoadWidening' => $RoadWidening, 'SortId' => $i
                                ));
                                $update->where(array('LandRoadDetailId'=>$RoadDetailId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $OwnerId = $this->bsf->isNullCheck($postData['OwnerId'], 'number');
                        for ($i = 1; $i <= $OwnerId; $i++) {
                            $surveyno = $this->bsf->isNullCheck($postData['surveyno_' . $i], 'string');
                            $ownername = $this->bsf->isNullCheck($postData['ownername_' . $i], 'string');
                            $landarea = $this->bsf->isNullCheck($postData['landarea_' . $i], 'number');
                            $NRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_NRI'], 'number');
                            $ownerAddress = $this->bsf->isNullCheck($postData['owneraddress_' . $i], 'string');

                            if ($surveyno == "" || $ownername == "" || $landarea == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_LandOwnerDetail');
                            $insert->Values(array(
                                'EnquiryId' => $iEnquiryId,
                                'NRI' => $NRI,
                                'OwnerAddress' => $ownerAddress,
                                'SurveyNo' => $surveyno, 'OwnerName' => $ownername, 'LandArea' => $landarea, 'LandAreaUnitId' => $this->bsf->isNullCheck($postData['landareaunitid_' . $i], 'number'),
                                'FatherName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_fathername'], 'string'), 'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_dob'], 'string'))),
                                'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_contactno'], 'string'), 'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_passportno'], 'string'),
                                'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_panno'], 'string'), 'PurchaseTypeId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_purchasetype'], 'number'),
                                'PattaNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattano'], 'string'), 'PattaName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattaname'], 'string'),
                                'Area' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_area'], 'number'), 'AreaUnitId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_areaunitid'], 'number'),
                                'EastSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_eastdetail'], 'string'), 'WestSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_westdetail'], 'string'),
                                'NorthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_northdetail'], 'string'), 'SouthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_southdetail'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $detailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $ownerInfoId = $this->bsf->isNullCheck($postData['coownerid_' . $i], 'number');
                            for ($j = 1; $j <= $ownerInfoId; $j++) {
                                $coownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coownername_' . $j], 'string');
                                $fathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_fathername_' . $j], 'string');
                                $CNRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_NRI_' . $j], 'number');
                                if ($coownername == "" || $fathername == "")
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('Proj_LandCoOwnerDetail');
                                $insert->Values(array('OwnerId' => $detailId,
                                    'CoOwnerName' => $coownername,
                                    'FatherName' => $fathername,
                                    'CNRI' => $CNRI,
                                    'CoOwnerAddress' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coowneraddress_' . $j], 'string'),
                                    'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_dob_' . $j], 'string'))),
                                    'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_contactno_' . $j], 'number'),
                                    'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_passportno_' . $j], 'string'),
                                    'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_panno_' . $j], 'string'),
                                    'RelationshipWithOwner' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_relationship_' . $j], 'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));

                    } else {
                        $aVNo = CommonHelper::getVoucherNo(102, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == false)
                            $RefNo = $postData['RefNo'];
                        else
                            $RefNo = $aVNo["voucherNo"];


                        $insert = $sql->insert();
                        $insert->into('Proj_LandEnquiry');
                        $insert->Values(array('RefNo' => $this->bsf->isNullCheck($RefNo, 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                        , 'PropertyName' => $this->bsf->isNullCheck($postData['PropertyName'], 'string'), 'SourceId' => $this->bsf->isNullCheck($postData['SourceId'], 'number')
                        , 'LandCost' => $this->bsf->isNullCheck($postData['LandCost'], 'number'), 'SourceName' => $this->bsf->isNullCheck($postData['SourceName'], 'string')
                        , 'TotalArea' => $this->bsf->isNullCheck($postData['TotalArea'], 'number'), 'TotalAreaUnitId' => $this->bsf->isNullCheck($postData['TotalAreaUnitId'], 'number')
                        , 'PropertyLocation' => $this->bsf->isNullCheck($postData['PropertyLocation'], 'string'), 'SaleTypeId' => $this->bsf->isNullCheck($postData['SaleTypeId'], 'number')
                        , 'Email' => $this->bsf->isNullCheck($postData['Email'], 'string'), 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                        , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number'), 'RoadFacingDirection' => $this->bsf->isNullCheck($postData['RoadFacingDirection'], 'string')
                        , 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'], 'string'), 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'], 'string')
                        , 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number'), 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number')
                        , 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number'), 'PropertyAddress' => $this->bsf->isNullCheck($postData['PropertyAddress'], 'string')
                        , 'NLandMark' => $this->bsf->isNullCheck($postData['NLandMark'], 'string'), 'NRailwayStation' => $this->bsf->isNullCheck($postData['NRailwayStation'], 'string')
                        , 'NHospital' => $this->bsf->isNullCheck($postData['NHospital'], 'string'), 'NFireStation' => $this->bsf->isNullCheck($postData['NFireStation'], 'string')
                        , 'NAirport' => $this->bsf->isNullCheck($postData['NAirport'], 'string'), 'NPoliceStation' => $this->bsf->isNullCheck($postData['NPoliceStation'], 'string')
                        , 'NBusStop' => $this->bsf->isNullCheck($postData['NBusStop'], 'string'), 'NHotel' => $this->bsf->isNullCheck($postData['NHotel'], 'string')
                        , 'NGrocery' => $this->bsf->isNullCheck($postData['NGrocerystore'], 'string'), 'NHotel' => $this->bsf->isNullCheck($postData['NHotel'], 'string')
                        , 'NSchool' => $this->bsf->isNullCheck($postData['NSchool'], 'string'), 'NHotel' => $this->bsf->isNullCheck($postData['NHotel'], 'string')
                        , 'NBank' => $this->bsf->isNullCheck($postData['NBank'], 'string'), 'NHotel' => $this->bsf->isNullCheck($postData['NHotel'], 'string')
                        , 'CityName' => $this->bsf->isNullCheck($postData['CityName'], 'string')
                        , 'PinCode' => $this->bsf->isNullCheck($postData['PinCode'], 'string')
                        , 'CityId' => $this->bsf->isNullCheck($postData['CityId'], 'number')
                        , 'LandMark1' => $this->bsf->isNullCheck($postData['Landmark1'], 'string')
                        , 'LandMark2' => $this->bsf->isNullCheck($postData['Landmark2'], 'string')
                        , 'LandMark3' => $this->bsf->isNullCheck($postData['Landmark3'], 'string')
                        , 'BusDistance' => $this->bsf->isNullCheck($postData['BusDistance'], 'number')
                        , 'RailDistance' => $this->bsf->isNullCheck($postData['RailDistance'], 'number')
                        , 'HospitalDistance' => $this->bsf->isNullCheck($postData['HospitalDistance'], 'number')
                        , 'AirportDistance' => $this->bsf->isNullCheck($postData['AirportDistance'], 'number')
                        , 'HotelDistance' => $this->bsf->isNullCheck($postData['HotelDistance'], 'number')
                        , 'FireDistance' => $this->bsf->isNullCheck($postData['FireDistance'], 'number')
                        , 'PoliceDistance' => $this->bsf->isNullCheck($postData['PoliceDistance'], 'number')
                        , 'GroceryDistance' => $this->bsf->isNullCheck($postData['GroceryDistance'], 'number')
                        , 'SchoolDistance' => $this->bsf->isNullCheck($postData['SchoolDistance'], 'number')
                        , 'BankDistance' => $this->bsf->isNullCheck($postData['BankDistance'], 'number')
                        , 'BankUnitId' => $this->bsf->isNullCheck($postData['BankUnitId'], 'number')
                        , 'GroceryUnitId' => $this->bsf->isNullCheck($postData['GroceryUnitId'], 'number')
                        , 'PoliceUnitId' => $this->bsf->isNullCheck($postData['PoliceUnitId'], 'number')
                        , 'FireUnitId' => $this->bsf->isNullCheck($postData['FireUnitId'], 'number')
                        , 'HotelUnitId' => $this->bsf->isNullCheck($postData['HotelUnitId'], 'number')
                        , 'AirportUnitId' => $this->bsf->isNullCheck($postData['AirportUnitId'], 'number')
                        , 'HospitalUnitId' => $this->bsf->isNullCheck($postData['HospitalUnitId'], 'number')
                        , 'RailUnitId' => $this->bsf->isNullCheck($postData['RailUnitId'], 'number')
                        , 'BusUnitId' => $this->bsf->isNullCheck($postData['BusUnitId'], 'number')
                        , 'SchoolUnitId' => $this->bsf->isNullCheck($postData['SchoolUnitId'], 'number')
                        , 'Latitude' => $this->bsf->isNullCheck($postData['us3-lat'], 'number')
                        , 'Longitude' => $this->bsf->isNullCheck($postData['us3-lon'], 'number')
                        ,'Radius' => $this->bsf->isNullCheck($postData['us3-radius'], 'number')
                        , 'Location' => $this->bsf->isNullCheck($postData['us3-address'], 'string')
                        , 'BrokerId' => $this->bsf->isNullCheck($postData['BrokerId'], 'number') ,'PropImageURL' => $imgUrl));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $enquiryId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        // road details
                        $roadrowid = $this->bsf->isNullCheck($postData['roadrowid'], 'number');
                        for ($i = 1; $i <= $roadrowid; $i++) {
                            $RoadName = $this->bsf->isNullCheck($postData['RoadName_' . $i], 'string');
                            $AbutRoadWidth = $this->bsf->isNullCheck($postData['AbutRoadWidth_' . $i], 'number');
                            $AbutRoadWidthUnitId = $this->bsf->isNullCheck($postData['AbutRoadWidthUnitId_' . $i], 'number');
                            $ApproThroPass = $this->bsf->isNullCheck($postData['ApproThroPass_' . $i], 'number');
                            $PropFrontage = $this->bsf->isNullCheck($postData['PropFrontage_' . $i], 'number');
                            $PropFrontageUnitId = $this->bsf->isNullCheck($postData['PropFrontageUnitId_' . $i], 'number');
                            $WidthPassage = $this->bsf->isNullCheck($postData['WidthPassage_' . $i], 'number');
                            $WidthPassageUnitId = $this->bsf->isNullCheck($postData['WidthPassageUnitId_' . $i], 'number');
                            $LengthPassage = $this->bsf->isNullCheck($postData['LengthPassage_' . $i], 'number');
                            $LengthPassageUnitId = $this->bsf->isNullCheck($postData['LengthPassageUnitId_' . $i], 'number');
                            $RWidthRule250 = $this->bsf->isNullCheck($postData['RWidthRule250_' . $i], 'number');
                            $RWidthRule500 = $this->bsf->isNullCheck($postData['RWidthRule500_' . $i], 'number');
                            $RoadDirection = $this->bsf->isNullCheck($postData['RoadDirection_' . $i], 'string');
                            $RoadLevel = $this->bsf->isNullCheck($postData['RoadLevel_' . $i], 'number');
                            $RoadLevelUnitId = $this->bsf->isNullCheck($postData['RoadLevelUnitId_' . $i], 'number');
                            $RoadWidening = $this->bsf->isNullCheck($postData['RoadWidening_' . $i], 'number');

                            $insert = $sql->insert();
                            $insert->into('Proj_LandRoadDetail');
                            $insert->Values(array('EnquiryId' => $enquiryId,'RoadName' => $RoadName, 'AbutRoadWidth' => $AbutRoadWidth,
                                'AbutRoadWidthUnitId' => $AbutRoadWidthUnitId,'ApproThroPass' => $ApproThroPass,'PropFrontage' => $PropFrontage,
                                'PropFrontageUnitId' => $PropFrontageUnitId,'WidthPassage' => $WidthPassage,'WidthPassageUnitId' => $WidthPassageUnitId,
                                'LengthPassage' => $LengthPassage,'LengthPassageUnitId' => $LengthPassageUnitId,'RWidthRule250' => $RWidthRule250,
                                'RWidthRule500' => $RWidthRule500,'RoadDirection' => $RoadDirection,'RoadLevel' => $RoadLevel,
                                'RoadLevelUnitId' => $RoadLevelUnitId,'RoadWidening' => $RoadWidening, 'SortId' => $i
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $OwnerId = $this->bsf->isNullCheck($postData['OwnerId'], 'number');
                        for ($i = 1; $i <= $OwnerId; $i++) {
                            $surveyno = $this->bsf->isNullCheck($postData['surveyno_' . $i], 'string');
                            $ownername = $this->bsf->isNullCheck($postData['ownername_' . $i], 'string');
                            $landarea = $this->bsf->isNullCheck($postData['landarea_' . $i], 'number');
                            $ownerAddress = $this->bsf->isNullCheck($postData['owneraddress_' . $i], 'string');
                            $NRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_NRI'], 'number');

                            if ($surveyno == "" || $ownername == "" || $landarea == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_LandOwnerDetail');
                            $insert->Values(array('EnquiryId' => $enquiryId,
                                'SurveyNo' => $surveyno,
                                'OwnerName' => $ownername,
                                'OwnerAddress' => $ownerAddress,
                                'NRI' => $NRI,
                                'LandArea' => $landarea, 'LandAreaUnitId' => $this->bsf->isNullCheck($postData['landareaunitid_' . $i], 'number'),
                                'FatherName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_fathername'], 'string'),
                                'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_dob'], 'string'))),
                                'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_contactno'], 'string'), 'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_passportno'], 'string'),
                                'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_panno'], 'string'), 'PurchaseTypeId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_purchasetype'], 'number'),
                                'PattaNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattano'], 'string'), 'PattaName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattaname'], 'string'),
                                'Area' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_area'], 'number'), 'AreaUnitId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_areaunitid'], 'number'),
                                'EastSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_eastdetail'], 'string'),
                                'WestSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_westdetail'], 'string'),
                                'NorthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_northdetail'], 'string'),
                                'SouthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_southdetail'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $detailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $ownerInfoId = $this->bsf->isNullCheck($postData['coownerid_' . $i], 'number');
                            for ($j = 1; $j <= $ownerInfoId; $j++) {
                                $coownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coownername_' . $j], 'string');
                                $fathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_fathername_' . $j], 'string');
                                $CNRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_NRI_' . $j], 'number');
                                if ($coownername == "" || $fathername == "")
                                    continue;
                                $coOwnerAddress=$this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coowneraddress_' . $j], 'string');
                                $insert = $sql->insert();
                                $insert->into('Proj_LandCoOwnerDetail');
                                $insert->Values(array('OwnerId' => $detailId,
                                    'CoOwnerName' => $coownername,
                                    'FatherName' => $fathername,
                                    'CNRI' => $CNRI,
                                    'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_dob_' . $j], 'string'))),
                                    'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_contactno_' . $j], 'number'),
                                    'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_passportno_' . $j], 'string'),
                                    'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_panno_' . $j], 'string'),
                                    'CoOwnerAddress' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coowneraddress_' . $j], 'string'),
                                    'RelationshipWithOwner' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_relationship_' . $j], 'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                } catch (PDOException $e) {
                    $connection->rollback();

                }
            }
        }


        $aVNo = CommonHelper::getVoucherNo(102, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];

        // Zone List
        $select = $sql->select();
        $select->from('Proj_ZoneMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->zonetypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // Source List
        $select = $sql->select();
        $select->from('Proj_SourceMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->sourcelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // Sale Type List
        $select = $sql->select();
        $select->from('Proj_SaleTypeMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->saletypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // Area Unit List
        $select = $sql->select();
        $select->from('Proj_UOM')
            ->where('TypeId=2');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Unit for Kilometer
        $select = $sql->select();
        $select->from('Proj_UOM')
            ->where('TypeId=1');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unittypesKm = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // City List
        $select = $sql->select();
        $select->from('WF_CityMaster')
            ->columns(array('data'=>'CityId', 'value'=>'CityName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->citylists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // Broker List
        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('data'=>'VendorId', 'value'=>'VendorName'))
            ->where(array('ServiceTypeId'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->Brokerlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $dLatitude = 13.006404248489623;
        $dLongitude = 80.25683902275387;
        $dRadius= 300;



        $enquiryId = $this->params()->fromRoute('enquiryId');
        if (isset($enquiryId) && $enquiryId != 0) {
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->where(array("EnquiryId" => $enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $landEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->landEnquiry = $landEnquiry;

            $iEnquiryId = 0;
            if (!empty($landEnquiry)) {
                $iEnquiryId = $landEnquiry['EnquiryId'];
                $dLatitude =  $landEnquiry['Latitude'];
                $dLongitude =  $landEnquiry['Longitude'];
                $dRadius =  $landEnquiry['Radius'];
            }

            if ($dLatitude ==0) $dLatitude = 13.006404248489623;
            if ($dLongitude ==0) $dLongitude = 80.25683902275387;
            if ($dRadius ==0) $dRadius= 300;

            $select = $sql->select();
            $select->from('Proj_LandRoadDetail')
                ->where('EnquiryId=' . $enquiryId)
                ->order('SortId');
            $statement = $sql->getSqlStringForSqlObject($select);

            $this->_view->landRoadDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_LandOwnerDetail')
                ->where('EnquiryId=' . $enquiryId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $landOwnerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->landOwnerDetail=$landOwnerDetail;

            $subQuery = $sql->select();
            $subQuery->from("Proj_LandOwnerDetail")
                ->columns(array("OwnerId"));
            $subQuery->where(array('EnquiryId' => $enquiryId));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandCoOwnerDetail'))
                ->where->expression('OwnerId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->landCoOwnerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        $this->_view->latitude = $dLatitude;
        $this->_view->longitude = $dLongitude;
        $this->_view->radius = $dRadius;

        $this->_view->enquiryId = (isset($enquiryId) && $enquiryId != 0) ? $enquiryId : 0;


        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function initialfeasibilityAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Lank Bank Initial Feasibility");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $Date =date('d-m-Y');
        $this->_view->Date = $Date;
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();
                $files = $request->getFiles();
                $iFeasibilityId = $this->bsf->isNullCheck($postData['FeasibilityId'], 'number');
//                echo '<pre>'; print_r($postData); die;

                if ($iFeasibilityId==0) {
                    $aVNo = CommonHelper::getVoucherNo(104, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == false)
                        $RefNo = $postData['RefNo'];
                    else
                        $RefNo = $aVNo["voucherNo"];
//                    echo '<pre>'; print_r($postData); die;
                    $insert = $sql->insert();
                    $insert->into('Proj_LandInitialFeasibility');
                    $insert->Values(array('RefNo' => $RefNo, 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                    , 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'string'), 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'], 'string')
                    , 'SoilReportAuthority' => $this->bsf->isNullCheck($postData['SoilReportAuthority'], 'string'), 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'], 'string')
                    , 'SoilTestReport' => $this->bsf->isNullCheck($postData['SoilTestReport'], 'number'), 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                    , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number'), 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number')
                    , 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number'), 'FlightPath' => $this->bsf->isNullCheck($postData['FlightPath'], 'string')
                    , 'DistanceFromAirport' => $this->bsf->isNullCheck($postData['DistanceFromAirport'], 'number'), 'DistanceFromAirportUnitId' => $this->bsf->isNullCheck($postData['DistanceFromAirportUnitId'], 'number')
                    , 'ProjectCategory' => $this->bsf->isNullCheck($postData['ProjectCategory'], 'number'), 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number')
                    , 'GroundWater' => $this->bsf->isNullCheck($postData['GroundWater'], 'string'), 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'], 'string')
                    , 'GroundElevation' => $this->bsf->isNullCheck($postData['GroundElevation'], 'string'), 'GroundElevationUnitId' => $this->bsf->isNullCheck($postData['GroundElevationUnitId'], 'number')
                    , 'Drainage' => $this->bsf->isNullCheck($postData['Drainage'], 'number'), 'Electricity' => $this->bsf->isNullCheck($postData['Electricity'], 'number')
                    , 'MaterialStorageArea' => $this->bsf->isNullCheck($postData['MaterialStorageArea'], 'number'), 'LabourShedArea' => $this->bsf->isNullCheck($postData['LabourShedArea'], 'number')
                    , 'SanctioningAuth' => $this->bsf->isNullCheck($postData['SanctioningAuth'], 'number')
                    , 'Guideline' => $this->bsf->isNullCheck($postData['Guideline'], 'number')
                    , 'GuidelineUnitId' => $this->bsf->isNullCheck($postData['GuidelineUnitId'], 'number')
                    , 'Floors' => $this->bsf->isNullCheck($postData['Floors'], 'number'), 'FSI' => $this->bsf->isNullCheck($postData['FSI'], 'number')
                    , 'PremiumFSI' => $this->bsf->isNullCheck($postData['PremiumFSI'], 'number')
                    , 'FSIAchieve' => $this->bsf->isNullCheck($postData['FSIAchieve'], 'string')
                    , 'LoadingFSI' => $this->bsf->isNullCheck($postData['LoadingFSI'], 'number')
                    , 'AllowableArea' => $this->bsf->isNullCheck($postData['AllowableArea'], 'number')
                    , 'GovtSewage' => $this->bsf->isNullCheck($postData['GovtSewage'], 'string')
                    , 'FloodMarks' => $this->bsf->isNullCheck($postData['FloodMarks'], 'string')
                    , 'HighTensionWires' => $this->bsf->isNullCheck($postData['HighTensionWires'], 'string')
                    , 'RailwayTrack' => $this->bsf->isNullCheck($postData['RailwayTrack'], 'string')
                    , 'GarbageDumps' => $this->bsf->isNullCheck($postData['GarbageDumps'], 'string')
                    , 'ChannelOrTank' => $this->bsf->isNullCheck($postData['ChannelOrTank'], 'string')
                    , 'BurialOrCrematorium' => $this->bsf->isNullCheck($postData['BurialOrCrematorium'], 'string')
                    , 'SchoolOrCollege' => $this->bsf->isNullCheck($postData['SchoolOrCollege'], 'string')
                    , 'ReligiousEstablish' => $this->bsf->isNullCheck($postData['ReligiousEstablish'], 'string')
                    , 'TrafficAbutt' => $this->bsf->isNullCheck($postData['TrafficAbutt'], 'string')
                    , 'AuraIssues' => $this->bsf->isNullCheck($postData['AuraIssues'], 'string')
                    , 'ExpandableArea' => $this->bsf->isNullCheck($postData['ExpandableArea'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $feasibilityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/landbank/initialfeasibility/' . $feasibilityId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/landbank/initialfeasibility/' . $feasibilityId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4','wmv','mpg');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_LandInitialFeasibilityFiles');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Owner Details
                    $OwnerId = $this->bsf->isNullCheck($postData['OwnerId'], 'number');
                    for ($i = 1; $i <= $OwnerId; $i++) {
                        $surveyno = $this->bsf->isNullCheck($postData['surveyno_' . $i], 'string');
                        $ownername = $this->bsf->isNullCheck($postData['ownername_' . $i], 'string');
                        $landarea = $this->bsf->isNullCheck($postData['landarea_' . $i], 'number');
                        $ownerAddress = $this->bsf->isNullCheck($postData['owneraddress_' . $i], 'string');
                        $NRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_NRI'], 'number');

                        if ($surveyno == "" || $ownername == "" || $landarea == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_InitialOwnerDetail');
                        $insert->Values(array('FeasibilityId' => $feasibilityId
                        , 'SurveyNo' => $surveyno, 'OwnerName' => $ownername,
                            'LandArea' => $landarea,
                            'OwnerAddress' => $ownerAddress,
                            'NRI' => $NRI,
                            'LandAreaUnitId' => $this->bsf->isNullCheck($postData['landareaunitid_' . $i], 'number')
                        , 'FatherName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_fathername'], 'string')
                        , 'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_dob'], 'string')))
                        , 'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_contactno'], 'string')
                        , 'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_passportno'], 'string')
                        , 'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_panno'], 'string')
                        , 'PurchaseTypeId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_purchasetype'], 'number')
                        , 'PattaNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattano'], 'string')
                        , 'PattaName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattaname'], 'string')
                        , 'Area' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_area'], 'number')
                        , 'AreaUnitId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_areaunitid'], 'number')
                        , 'EastSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_eastdetail'], 'string')
                        , 'WestSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_westdetail'], 'string')
                        , 'NorthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_northdetail'], 'string')
                        , 'SouthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_southdetail'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $detailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $ownerInfoId = $this->bsf->isNullCheck($postData['coownerid_' . $i], 'number');
                        for ($j = 1; $j <= $ownerInfoId; $j++) {
                            $coownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coownername_' . $j], 'string');
                            $fathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_fathername_' . $j], 'string');
                            $CNRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_NRI_' . $j], 'number');
                            if ($coownername == "" || $fathername == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_InitialCoOwnerDetail');
                            $insert->Values(array('OwnerId' => $detailId,
                                'CoOwnerName' => $coownername,
                                'FatherName' => $fathername,
                                'CoOwnerAddress' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coowneraddress_' . $j], 'string'),
                                'CNRI' => $CNRI,
                                'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_dob_' . $j], 'string'))),
                                'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_contactno_' . $j], 'number'),
                                'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_passportno_' . $j], 'string'),
                                'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_panno_' . $j], 'string'),
                                'RelationshipWithOwner' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_relationship_' . $j], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $prevOwnerInfoId = $this->bsf->isNullCheck($postData['prevownerid_' . $i], 'number');
                        for ($k = 1; $k <= $prevOwnerInfoId; $k++) {
                            $poownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_prevownername_' . $k], 'string');
                            $pofathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_fathername_' . $k], 'string');
                            if ($poownername == "" || $pofathername == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_InitialPrevOwnerDetail');
                            $insert->Values(array('OwnerId' => $detailId,
                                'PrevOwnerName' => $poownername, 'PrevFatherName' => $pofathername,
                                'PrevDOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_dob_' . $k], 'string'))),
                                'PrevContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_contactno_' . $k], 'number'),
                                'PrevPassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_passportno_' . $k], 'string'),
                                'PrevPanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_panno_' . $k], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    // Soil Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['soildocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $type = $this->bsf->isNullCheck($postData['soilDocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['soilDocDesc_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        $url = '';
                        if ($files['soilDocFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/initialfeasibility/' . $feasibilityId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['soilDocFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/initialfeasibility/' . $feasibilityId . '/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_SoilDocument');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Joint Ventures
                    // Share Type
                    $shareTypeRowId = $this->bsf->isNullCheck($postData['sharetyperowid'], 'number');
                    for ($i = 1; $i <= $shareTypeRowId; $i++) {
                        $shareTypeId = $this->bsf->isNullCheck($postData['shareTypeId_' . $i], 'number');
                        $shareArea = $this->bsf->isNullCheck($postData['shareArea_' . $i], 'number');
                        $shareAreaUnitId = $this->bsf->isNullCheck($postData['shareAreaUnitId_' . $i], 'number');
                        $sharePercentage = $this->bsf->isNullCheck($postData['sharePercentage_' . $i], 'number');
                        $shareAmount = $this->bsf->isNullCheck($postData['shareAmount_' . $i], 'number');

                        if ($shareTypeId == 0 || $shareArea == 0 || $sharePercentage == 0 || $shareAmount == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_JVShareType');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Type' => $shareTypeId, 'Area' => $shareArea
                        , 'AreaUnitId' => $shareAreaUnitId, 'Percentage' => $sharePercentage, 'Amount' => $shareAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['joinventuredocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $type = $this->bsf->isNullCheck($postData['joinVentureDocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['joinVentureDocDesc_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        $url = '';
                        if ($files['joinVentureDocFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/landbank/initialfeasibility/' . $feasibilityId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['joinVentureDocFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/landbank/initialfeasibility/' . $feasibilityId . '/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_JVDocument');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Market Trend
                    // Competitor Rate
                    $competitorRowId = $this->bsf->isNullCheck($postData['competitorrowid'], 'number');
                    for ($i = 1; $i <= $competitorRowId; $i++) {
                        $name = $this->bsf->isNullCheck($postData['competitorname_' . $i], 'string');
                        $typeId = $this->bsf->isNullCheck($postData['competitorprojecttypeid_' . $i], 'number');
                        $rate = $this->bsf->isNullCheck($postData['competitorrate_' . $i], 'number');

                        if ($name == "" || $typeId == 0 || $rate == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_MarketTrendCompetitorRate');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Name' => $name, 'ProjectTypeId' => $typeId, 'Rate' => $rate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Average Rate
                    $avgRowId = $this->bsf->isNullCheck($postData['avgrowid'], 'number');
                    for ($i = 1; $i <= $avgRowId; $i++) {
                        $date = $this->bsf->isNullCheck($postData['avgdate_' . $i], 'string');
                        $typeId = $this->bsf->isNullCheck($postData['avgprojecttypeid_' . $i], 'number');
                        $rate = $this->bsf->isNullCheck($postData['avgrate_' . $i], 'number');

                        if ($date == "" || $typeId == 0 || $rate == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_MarketTrendAverageRate');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Date' => date('Y-m-d', strtotime($date)), 'ProjectTypeId' => $typeId, 'Rate' => $rate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('IFeasibilityId' => $feasibilityId))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // checklist
                    $newCheckLists = array();
                    $chkRowId = $this->bsf->isNullCheck($postData['chk-rowid'], 'number');
                    for ($i = 1; $i <= $chkRowId; $i++) {
                        $id = $this->bsf->isNullCheck($postData['id_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '1'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }



                        $insert = $sql->insert();
                        $insert->into('Proj_LandBankChecklistTrans');
                        $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));

//                        $insert = $sql->insert();
//                        $insert->into('Proj_LBInitialFeasibilityCheckListTrans');
//                        $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $feasibilityId
//                                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));


                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // vend checklist
                    $jvChkRowId = $this->bsf->isNullCheck($postData['jvchk-rowid'], 'number');
                    for ($i = 1; $i <= $jvChkRowId; $i++) {
                        $id = $this->bsf->isNullCheck($postData['id_jv_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_jv_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_jv_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_jv_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_jv_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '2'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }


                        $insert = $sql->insert();
                        $insert->into('Proj_LandBankChecklistTrans');
                        $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//
//                        $insert = $sql->insert();
//                        $insert->into('Proj_LBInitialFeasibilityCheckListTrans');
//                        $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $feasibilityId
//                                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $id=$this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                } else {
                    $this->bsf->isNullCheck($postData['Guideline'], 'number');
                    $update = $sql->update();
                    $update->table('Proj_LandInitialFeasibility');
                    $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate'])), 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'], 'string')
                    , 'SoilReportAuthority' => $this->bsf->isNullCheck($postData['SoilReportAuthority'], 'string'), 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'], 'string')
                    , 'SoilTestReport' => $this->bsf->isNullCheck($postData['SoilTestReport'], 'number'), 'FrontageArea' => $this->bsf->isNullCheck($postData['FrontageArea'], 'number')
                    , 'FrontageAreaUnitId' => $this->bsf->isNullCheck($postData['FrontageAreaUnitId'], 'number'), 'RoadWidth' => $this->bsf->isNullCheck($postData['RoadWidth'], 'number')
                    , 'RoadWidthUnitId' => $this->bsf->isNullCheck($postData['RoadWidthUnitId'], 'number'), 'FlightPath' => $this->bsf->isNullCheck($postData['FlightPath'], 'string')
                    , 'DistanceFromAirport' => $this->bsf->isNullCheck($postData['DistanceFromAirport'], 'number'), 'DistanceFromAirportUnitId' => $this->bsf->isNullCheck($postData['DistanceFromAirportUnitId'], 'number')
                    , 'ProjectCategory' => $this->bsf->isNullCheck($postData['ProjectCategory'], 'number'), 'ZoneTypeId' => $this->bsf->isNullCheck($postData['ZoneTypeId'], 'number')
                    , 'GroundWater' => $this->bsf->isNullCheck($postData['GroundWater'], 'string'), 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'], 'string')
                    , 'Drainage' => $this->bsf->isNullCheck($postData['Drainage'], 'number'), 'Electricity' => $this->bsf->isNullCheck($postData['Electricity'], 'number')
                    , 'MaterialStorageArea' => $this->bsf->isNullCheck($postData['MaterialStorageArea'], 'number'), 'LabourShedArea' => $this->bsf->isNullCheck($postData['LabourShedArea'], 'number')
                    , 'SanctioningAuth' => $this->bsf->isNullCheck($postData['SanctioningAuth'], 'number')
                    , 'Guideline' => $this->bsf->isNullCheck($postData['Guideline'], 'number')
                    , 'GuidelineUnitId' => $this->bsf->isNullCheck($postData['GuidelineUnitId'], 'number')
                    , 'Floors' => $this->bsf->isNullCheck($postData['Floors'], 'number'), 'FSI' => $this->bsf->isNullCheck($postData['FSI'], 'number')
                    , 'PremiumFSI' => $this->bsf->isNullCheck($postData['PremiumFSI'], 'number')
                    , 'FSIAchieve' => $this->bsf->isNullCheck($postData['FSIAchieve'], 'string')
                    , 'LoadingFSI' => $this->bsf->isNullCheck($postData['LoadingFSI'], 'number')
                    , 'AllowableArea' => $this->bsf->isNullCheck($postData['AllowableArea'], 'number')
                    , 'GovtSewage' => $this->bsf->isNullCheck($postData['GovtSewage'], 'string')
                    , 'FloodMarks' => $this->bsf->isNullCheck($postData['FloodMarks'], 'string')
                    , 'HighTensionWires' => $this->bsf->isNullCheck($postData['HighTensionWires'], 'string')
                    , 'RailwayTrack' => $this->bsf->isNullCheck($postData['RailwayTrack'], 'string')
                    , 'GarbageDumps' => $this->bsf->isNullCheck($postData['GarbageDumps'], 'string')
                    , 'ChannelOrTank' => $this->bsf->isNullCheck($postData['ChannelOrTank'], 'string')
                    , 'BurialOrCrematorium' => $this->bsf->isNullCheck($postData['BurialOrCrematorium'], 'string')
                    , 'SchoolOrCollege' => $this->bsf->isNullCheck($postData['SchoolOrCollege'], 'string')
                    , 'ReligiousEstablish' => $this->bsf->isNullCheck($postData['ReligiousEstablish'], 'string')
                    , 'TrafficAbutt' => $this->bsf->isNullCheck($postData['TrafficAbutt'], 'string')
                    , 'AuraIssues' => $this->bsf->isNullCheck($postData['AuraIssues'], 'string')
                    , 'ExpandableArea' => $this->bsf->isNullCheck($postData['ExpandableArea'], 'number')));
                    $update->where(array('FeasibilityId'=>$iFeasibilityId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/landbank/initialfeasibility/' . $iFeasibilityId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/landbank/initialfeasibility/' . $iFeasibilityId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_LandInitialFeasibilityFiles');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $subQuery1 = $sql->select();
                    $subQuery1->from(array('a' => 'Proj_InitialOwnerDetail'))
                        ->columns(array('OwnerId'))
                        ->where("a.FeasibilityId=$iFeasibilityId");

                    $delete = $sql->delete();
                    $delete->from('Proj_InitialCoOwnerDetail')
                        ->where->expression('OwnerId IN ?', array($subQuery1));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_InitialPrevOwnerDetail')
                        ->where->expression('OwnerId IN ?', array($subQuery1));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_InitialOwnerDetail')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Owner Details
                    $OwnerId = $this->bsf->isNullCheck($postData['OwnerId'], 'number');
                    for ($i = 1; $i <= $OwnerId; $i++) {
                        $surveyno = $this->bsf->isNullCheck($postData['surveyno_' . $i], 'string');
                        $ownername = $this->bsf->isNullCheck($postData['ownername_' . $i], 'string');
                        $landarea = $this->bsf->isNullCheck($postData['landarea_' . $i], 'number');
                        $ownerAddress = $this->bsf->isNullCheck($postData['owneraddress_' . $i], 'string');
                        $NRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_NRI'], 'number');
                        if ($surveyno == "" || $ownername == "" || $landarea == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_InitialOwnerDetail');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId
                        , 'SurveyNo' => $surveyno, 'OwnerName' => $ownername
                        , 'LandArea' => $landarea
                        , 'OwnerAddress' => $ownerAddress
                        , 'NRI' => $NRI
                        , 'LandAreaUnitId' => $this->bsf->isNullCheck($postData['landareaunitid_' . $i], 'number')
                        , 'FatherName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_fathername'], 'string')
                        , 'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_dob'], 'string')))
                        , 'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_contactno'], 'string')
                        , 'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_passportno'], 'string')
                        , 'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_panno'], 'string')
                        , 'PurchaseTypeId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_purchasetype'], 'number')
                        , 'PattaNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattano'], 'string')
                        , 'PattaName' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_pattaname'], 'string')
                        , 'Area' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_area'], 'number')
                        , 'AreaUnitId' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_areaunitid'], 'number')
                        , 'EastSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_eastdetail'], 'string')
                        , 'WestSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_westdetail'], 'string')
                        , 'NorthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_northdetail'], 'string')
                        , 'SouthSideDetail' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_southdetail'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $detailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $ownerInfoId = $this->bsf->isNullCheck($postData['coownerid_' . $i], 'number');
                        for ($j = 1; $j <= $ownerInfoId; $j++) {
                            $coownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coownername_' . $j], 'string');
                            $fathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_fathername_' . $j], 'string');
                            $CNRI = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_NRI_' . $j], 'number');
                            if ($coownername == "" || $fathername == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_InitialCoOwnerDetail');
                            $insert->Values(array('OwnerId' => $detailId,
                                'CoOwnerName' => $coownername,
                                'FatherName' => $fathername,
                                'CNRI' => $CNRI,
                                'CoOwnerAddress' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_coowneraddress_' . $j], 'string'),
                                'DOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_dob_' . $j], 'string'))),
                                'ContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_contactno_' . $j], 'number'),
                                'PassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_passportno_' . $j], 'string'),
                                'PanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_panno_' . $j], 'string'),
                                'RelationshipWithOwner' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_co_relationship_' . $j], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $prevOwnerInfoId = $this->bsf->isNullCheck($postData['prevownerid_' . $i], 'number');
                        for ($k = 1; $k <= $prevOwnerInfoId; $k++) {
                            $poownername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_prevownername_' . $k], 'string');
                            $pofathername = $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_fathername_' . $k], 'string');
                            if ($poownername == "" || $pofathername == "")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_InitialPrevOwnerDetail');
                            $insert->Values(array('OwnerId' => $detailId,
                                'PrevOwnerName' => $poownername, 'PrevFatherName' => $pofathername,
                                'PrevDOB' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_dob_' . $k], 'string'))),
                                'PrevContactNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_contactno_' . $k], 'number'),
                                'PrevPassportNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_passportno_' . $k], 'string'),
                                'PrevPanNo' => $this->bsf->isNullCheck($postData['workinfo_' . $i . '_po_panno_' . $k], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_SoilDocument')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Soil Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['soildocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
//                                      echo '<pre>'; print_r($postData); die;
                        $type = $this->bsf->isNullCheck($postData['soilDocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['soilDocDesc_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['soilDocFile_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        if($url == '') {
                            if ($files['soilDocFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/initialfeasibility/' . $iFeasibilityId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['soilDocFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/initialfeasibility/' . $iFeasibilityId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_SoilDocument');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Type' => $type,
                            'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_JVShareType')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Joint Ventures
                    // Share Type
                    $shareTypeRowId = $this->bsf->isNullCheck($postData['sharetyperowid'], 'number');
                    for ($i = 1; $i <= $shareTypeRowId; $i++) {
                        $shareTypeId = $this->bsf->isNullCheck($postData['shareTypeId_' . $i], 'number');
                        $shareArea = $this->bsf->isNullCheck($postData['shareArea_' . $i], 'number');
                        $shareAreaUnitId = $this->bsf->isNullCheck($postData['shareAreaUnitId_' . $i], 'number');
                        $sharePercentage = $this->bsf->isNullCheck($postData['sharePercentage_' . $i], 'number');
                        $shareAmount = $this->bsf->isNullCheck($postData['shareAmount_' . $i], 'number');

                        if ($shareTypeId == 0 || $shareArea == 0 || $sharePercentage == 0 || $shareAmount == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_JVShareType');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Type' => $shareTypeId, 'Area' => $shareArea
                        , 'AreaUnitId' => $shareAreaUnitId, 'Percentage' => $sharePercentage, 'Amount' => $shareAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_JVDocument')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['joinventuredocumentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $type = $this->bsf->isNullCheck($postData['joinVentureDocType_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['joinVentureDocDesc_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['joinVentureDocFile_' . $i], 'string');

                        if ($type == "" || $desc == "")
                            continue;

                        if($url == '') {
                            if ($files['joinVentureDocFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/landbank/initialfeasibility/' . $iFeasibilityId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['joinVentureDocFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/landbank/initialfeasibility/' . $iFeasibilityId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_JVDocument');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Type' => $type, 'Description' => $desc,
                            'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_MarketTrendCompetitorRate')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Market Trend
                    // Competitor Rate
                    $competitorRowId = $this->bsf->isNullCheck($postData['competitorrowid'], 'number');
                    for ($i = 1; $i <= $competitorRowId; $i++) {
                        $name = $this->bsf->isNullCheck($postData['competitorname_' . $i], 'string');
                        $typeId = $this->bsf->isNullCheck($postData['competitorprojecttypeid_' . $i], 'number');
                        $rate = $this->bsf->isNullCheck($postData['competitorrate_' . $i], 'number');

                        if ($name == "" || $typeId == 0 || $rate == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_MarketTrendCompetitorRate');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Name' => $name, 'ProjectTypeId' => $typeId, 'Rate' => $rate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_MarketTrendAverageRate')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Average Rate
                    $avgRowId = $this->bsf->isNullCheck($postData['avgrowid'], 'number');
                    for ($i = 1; $i <= $avgRowId; $i++) {
                        $date = $this->bsf->isNullCheck($postData['avgdate_' . $i], 'string');
                        $typeId = $this->bsf->isNullCheck($postData['avgprojecttypeid_' . $i], 'number');
                        $rate = $this->bsf->isNullCheck($postData['avgrate_' . $i], 'number');

                        if ($date == "" || $typeId == 0 || $rate == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_MarketTrendAverageRate');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Date' => date('Y-m-d', strtotime($date)), 'ProjectTypeId' => $typeId, 'Rate' => $rate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('IFeasibilityId' => $iFeasibilityId))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // checklist
                    $newCheckLists = array();
                    $chkRowId = $this->bsf->isNullCheck($postData['chk-rowid'], 'number');
                    for ($i = 1; $i <= $chkRowId; $i++) {
                        $transid = $this->bsf->isNullCheck($postData['transid_' . $i], 'string');
                        $updaterow = $this->bsf->isNullCheck($postData['updaterow_' . $i], 'string');
                        $id = $this->bsf->isNullCheck($postData['id_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '1'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }

                        if($transid == 0 && $updaterow == 0) {

                            $insert = $sql->insert();
                            $insert->into('Proj_LandBankChecklistTrans');
                            $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                            , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//                            $insert = $sql->insert();
//                            $insert->into('Proj_LBInitialFeasibilityCheckListTrans');
//                            $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $iFeasibilityId
//                                            , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if($transid != 0 && $updaterow == 1){

                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistTrans');
                            $update->set(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                            , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                            $update->where(array('CheckListTransId'=>$transid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                            $update = $sql->update();
//                            $update->table('Proj_LBInitialFeasibilityCheckListTrans');
//                            $update->set(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $iFeasibilityId
//                                         , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                            $update->where(array('CheckListTransId'=>$transid));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    // vend checklist
                    $jvChkRowId = $this->bsf->isNullCheck($postData['jvchk-rowid'], 'number');
                    for ($i = 1; $i <= $jvChkRowId; $i++) {
                        $transid = $this->bsf->isNullCheck($postData['transid_jv_' . $i], 'string');
                        $updaterow = $this->bsf->isNullCheck($postData['updaterow_jv_' . $i], 'string');
                        $id = $this->bsf->isNullCheck($postData['id_jv_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_jv_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_jv_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_jv_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_jv_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '2'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }

                        if($transid == 0 && $updaterow == 0) {
                            $insert = $sql->insert();
                            $insert->into( 'Proj_LandBankChecklistTrans');
                            $insert->Values( array( 'CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                            , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

//                            $insert = $sql->insert();
//                            $insert->into( 'Proj_LBInitialFeasibilityCheckListTrans' );
//                            $insert->Values( array( 'CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $iFeasibilityId
//                                             , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
//                            $statement = $sql->getSqlStringForSqlObject( $insert );
//                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        } else if($transid != 0 && $updaterow == 1){


                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistTrans');
                            $update->set( array( 'CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                            , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
                            $update->where(array('CheckListTransId'=>$transid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//                            $update = $sql->update();
//                            $update->table('Proj_LBInitialFeasibilityCheckListTrans');
//                            $update->set( array( 'CheckListId' => $id, 'AssignedTo' => $assignedTo, 'FeasibilityId' => $iFeasibilityId
//                                          , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
//                            $update->where(array('CheckListTransId'=>$transid));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $id=$this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                }
            } catch (PDOException $e) {
                $connection->rollback();

            }
        } else {
            $aVNo = CommonHelper::getVoucherNo(104, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            // Zone List
            $select = $sql->select();
            $select->from('Proj_ZoneMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->zonetypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Source List
            $select = $sql->select();
            $select->from('Proj_SourceMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->sourcelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Sale Type List
            $select = $sql->select();
            $select->from('Proj_SaleTypeMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->saletypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Area Unit List
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where('TypeId=2');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Unit for Kilometer
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where('TypeId=1');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unittypesKm = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // City List
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId', 'CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->citylists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Joint Ventures Share Types
            $select = $sql->select();
            $select->from('Proj_JVShareTypeMaster')
                ->columns(array('data' => 'ShareTypeId', 'value' => 'ShareTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->jvsharetypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Property Names
            $subQuery = $sql->select();
            $subQuery->from("Proj_LandInitialFeasibility")
                ->columns(array("EnquiryId"));

            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'))
                ->where->expression('EnquiryId Not IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Project Type
            $select = $sql->select();
            $select->from('Proj_ProjectTypeMaster')
                ->columns(array('data' => 'ProjectTypeId', 'value' => 'ProjectTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Soil Type autocomplete
            $select = $sql->select();
            $select->from('Proj_SoilTypeMaster')
                ->columns(array(new Expression("DISTINCT SoilType as value")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->SoilType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            // users
            $select = $sql->select();
            $select->from('WF_Users')
                ->columns(array('data' => 'UserId', 'value' => 'UserName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // jv checklist
            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_CheckListMaster' ))
                ->columns(array('data' => 'CheckListId', 'value' => 'CheckListName'))
                ->where("a.DeleteFlag='0' AND TypeId='2'")
                ->order('a.CheckListName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->jvchecklists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            // initial feas. checklist
            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_CheckListMaster' ))
                ->columns(array('data' => 'CheckListId', 'value' => 'CheckListName'))
                ->where("a.DeleteFlag='0' AND TypeId='1'")
                ->order('a.CheckListName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->checklists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $feasibilityId = $this->params()->fromRoute('feasibilityId');
            $EnqId = $this->params()->fromRoute('enquiryId');
            $page = $this->params()->fromRoute('page');

            if (isset($EnqId) && $EnqId != 0) {
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->where(array("EnquiryId"=>$EnqId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->EnqId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
            }


            if (isset($feasibilityId) && $feasibilityId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandInitialFeasibility'))
                    ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId = b.EnquiryId', array('PropertyName'))
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $landInitialE = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->landInitial = $landInitialE;

                $iEnquiryId = 0;
                if (!empty($landInitialE)) {
                    $iEnquiryId = $landInitialE['EnquiryId'];
                }

                $select = $sql->select();
                $select->from('Proj_InitialOwnerDetail')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialOwnerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subQuery = $sql->select();
                $subQuery->from("Proj_InitialOwnerDetail")
                    ->columns(array("OwnerId"));
                $subQuery->where(array('FeasibilityId' => $feasibilityId));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_InitialCoOwnerDetail'))
                    ->where->expression('OwnerId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialCoOwnerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_InitialPrevOwnerDetail'))
                    ->where->expression('OwnerId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialPrevOwnerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_SoilDocument')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialSoilDocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_JVShareType'))
                    ->join(array('b' => 'Proj_JVShareTypeMaster'), 'a.Type = b.ShareTypeId', array('ShareTypeName'))
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialJVShare = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_JVDocument')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialJvDocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_MarketTrendCompetitorRate'))
                    ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId = b.ProjectTypeId', array('ProjectTypeName'))
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialCompetitor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_MarketTrendAverageRate'))
                    ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId = b.ProjectTypeId', array('ProjectTypeName'))
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landInitialAverage = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // get checklist
                $select = $sql->select();
                $select->from(array('a' => "Proj_LandBankChecklistTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignUserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignUserId','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
                $select->where("a.EnquiryId=$iEnquiryId AND b.TypeId = '1'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select->from(array('a' => "Proj_LBInitialFeasibilityCheckListTrans"))
//                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'CheckListType'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
//                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignedTo','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
//                $select->where("a.FeasibilityId=$feasibilityId AND b.CheckListType = 'I'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->checklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_LandBankChecklistTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignUserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignUserId','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
                $select->where("a.EnquiryId=$iEnquiryId AND b.TypeId = '2'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->jvchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from(array('a' => "Proj_LBInitialFeasibilityCheckListTrans"))
//                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'CheckListType'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
//                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignedTo','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
//                $select->where("a.FeasibilityId=$feasibilityId AND b.CheckListType = 'J'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->jvchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Image and video file types
                $select = $sql->select();
                $select->from('Proj_LandInitialFeasibilityFiles')
                    ->where("FeasibilityId=$feasibilityId AND FileType='image'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $images = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($images) > 0) {
                    $this->_view->images = $images;
                }

                $select = $sql->select();
                $select->from('Proj_LandInitialFeasibilityFiles')
                    ->where("FeasibilityId=$feasibilityId AND FileType='video'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $videos = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($videos != FALSE && count($videos) > 0) {
                    $this->_view->videos = $videos;
                }

            }
            $this->_view->feasibilityId = (isset($feasibilityId) && $feasibilityId != 0) ? $feasibilityId : 0;
            $this->_view->page = (isset($page) && $page != '') ? $page : '';
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getenquirydetailsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                $arr_details = array();
                $count = 0;

                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->where("EnquiryId=$EnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $arr_details['enquiry'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $arr_details['ownerDetails'] = $this->generateOwnerDetailsHTML($EnquiryId, $count);
                $arr_details['ownerDetailsCount'] = $count;

                $response = $this->getResponse();
                $response->setContent(json_encode($arr_details));
                return $response;
            }
        }
    }

    public function findchecklistAction(){
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
                $sql = new Sql($dbAdapter);

                $name = $this->bsf->isNullCheck($this->params()->fromPost('name'), 'string');
                $id = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');
                $type = $this->bsf->isNullCheck($this->params()->fromPost('type'), 'number');

                $whereCond = "CheckListName='$name' AND TypeId='$type' AND DeleteFlag=0";
                if($id != 0) {
                    $whereCond .= ' AND ChecklistId != ' . $id;
                }

                $select = $sql->select();
                $select->from('Proj_CheckListMaster')
                    ->columns( array( 'CheckListId'))
                    ->where($whereCond);
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (sizeof($results) !=0 )
                    return $this->getResponse()->setStatusCode(200)->setContent('Y');

                return $this->getResponse()->setStatusCode(201)->setContent('N');
            }
        }
    }

    public function addchecklistAction(){
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
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $EnquiryId = $this->bsf->isNullCheck($this->params()->fromPost('EnquiryId'), 'number');
                    $name = $this->bsf->isNullCheck($this->params()->fromPost('name'), 'string');
                    $userid = $this->bsf->isNullCheck($this->params()->fromPost('userid'), 'number');
                    $targetdate = $this->bsf->isNullCheck($this->params()->fromPost('targetdate'), 'string');
                    $status = $this->bsf->isNullCheck($this->params()->fromPost('status'), 'string');
                    $chktype = $this->bsf->isNullCheck($this->params()->fromPost('chktype'), 'string');

                    $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                    if($RequestFrom == '') {
                        return $this->getResponse()
                            ->setStatusCode(400)
                            ->setContent('Bad Request');
                    }

                    // create new checklist
                    $insert = $sql->insert();
                    $insert->into('Proj_CheckListMaster')
                        ->Values(array('CheckListName' => $name, 'TypeId' => $chktype));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $CheckListId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $tableName = 'Proj_LandBankChecklistTrans';

//                    switch($RequestFrom) {
//                        case 'initialfeasibility':
//                            $tableName = 'Proj_LBInitialFeasibilityCheckListTrans';
//                            break;
//                        case 'duediligence':
//                            $tableName = 'Proj_LBDueDiligenceCheckListTrans';
//                    }
                    // insert into trans
                    $insert = $sql->insert();
                    $insert->into($tableName)
                        ->Values(array('CheckListId' => $CheckListId, 'EnquiryId' => $EnquiryId, 'AssignUserId' => $userid, 'TargetDate' => date('Y-m-d', strtotime($targetdate)), 'Status' => $status, 'LastUpdateDate' => date('Y-m-d')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $CheckListTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from(array('a' => $tableName))
                        ->join(array('b' => 'Proj_CheckListMaster'), 'b.CheckListId=a.CheckListId',array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                        ->columns( array('CheckListTransId', 'TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'))
                        ->where( "CheckListTransId='$CheckListTransId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    return $this->getResponse()
                        ->setStatusCode(200)
                        ->setContent(json_encode($results));
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }

    public function addchecklistmasterAction(){
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
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $id = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');
                    $name = $this->bsf->isNullCheck($this->params()->fromPost('name'), 'string');
                    $type = $this->bsf->isNullCheck($this->params()->fromPost('type'), 'number');

                    $connection->beginTransaction();
                    if($id == 0) {
                        // create new checklist
                        $insert = $sql->insert();
                        $insert->into( 'Proj_CheckListMaster' )
                            ->Values( array( 'CheckListName' => $name, 'TypeId' => $type ) );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $update = $sql->update();
                        $update->table('Proj_CheckListMaster')
                            ->set(array('CheckListName' => $name, 'TypeId' => $type))
                            ->where(array('CheckListId' => $id));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    /*$select = $sql->select();
                    $select->from( 'Proj_CheckListMaster' )
                        ->columns( array( 'CheckListName', 'TypeId' ))
                        ->where( "CheckListId='$id'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();*/
                    //select a.CheckListName,a.TypeId,b.CheckListTypeName from Proj_CheckListMaster a LEFT JOIN Proj_CheckListTypeMaster b on a.TypeId = b.TypeId where a.CheckListId = 5
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_CheckListMaster'))
                        ->join(array('b' => 'Proj_CheckListTypeMaster'), 'a.TypeId=b.TypeId',array('CheckListTypeName'), $select::JOIN_LEFT)
                        ->columns( array('CheckListName', 'TypeId'))
                        ->where( "a.CheckListId='$id'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    return $this->getResponse()
                        ->setStatusCode(200)
                        ->setContent(json_encode($results));
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }

    public function deletechecklistAction(){
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
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $CheckListTransId = $this->bsf->isNullCheck($this->params()->fromPost('CheckListTransId'), 'number');
                    $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                    if($RequestFrom == '') {
                        return $this->getResponse()
                            ->setStatusCode(400)
                            ->setContent('Bad Request');
                    }

//                    switch($RequestFrom) {
//                        case 'initialfeasibility':
//                            $tableName = 'Proj_LBInitialFeasibilityCheckListTrans';
//                            break;
//                        case 'duediligence':
//                            $tableName = 'Proj_LBDueDiligenceCheckListTrans';
//                    }

                    // delete from trans
                    $tableName='Proj_LandBankChecklistTrans';
                    $delete = $sql->delete();
                    $delete->from($tableName)
                        ->where("CheckListTransId=$CheckListTransId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    return $this->getResponse()
                        ->setStatusCode(200)
                        ->setContent('Deleted');
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }

    public function deletechecklistmasterAction(){
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
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $CheckListId = $this->bsf->isNullCheck($this->params()->fromPost('CheckListId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists in initial feasibility
                            $select1 = $sql->select();
                            $select1->from('Proj_LandBankChecklistTrans')
                                ->columns(array('CheckListId'))
                                ->where(array('CheckListId' => $CheckListId));
                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($result) > 0)
                                return $response->setStatusCode( 201 )->setContent('Exists');


//                            // check for already exists in due diligence
//                            $select1 = $sql->select();
//                            $select1->from('Proj_LandBankChecklistTrans')
//                                ->columns(array('CheckListId'))
//                                ->where(array('CheckListId' => $CheckListId));
//                            $statement = $sql->getSqlStringForSqlObject( $select1 );
//                            $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//                            if(count($result) > 0)
//                                return $response->setStatusCode( 201 )->setContent('Exists');

                            return $response->setStatusCode('200')->setContent('Not used');
                            break;
                        case 'update':
                            $select = $sql->select();
                            $select->from('Proj_CheckListMaster')
                                ->columns(array('CheckListName'))
                                ->where(array('CheckListId' => $CheckListId));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $checklist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table('Proj_CheckListMaster')
                                ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('CheckListId' => $CheckListId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $connection->commit();

                            return $response->setStatusCode('200')->setContent('Deleted');
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    return $response->setStatusCode('400')->setContent('Bad Request');
                };
            }
        }
    }

    public function checkforchecklisttransAction(){
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
                $sql = new Sql($dbAdapter);

                $CheckListTransId = $this->bsf->isNullCheck($this->params()->fromPost('CheckListTransId'), 'number');
                $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                if($RequestFrom == '') {
                    return $this->getResponse()
                        ->setStatusCode(400)
                        ->setContent('Bad Request');
                }

//                switch($RequestFrom) {
//                    case 'initialfeasibility':
//                        $tableName = 'Proj_LBInitialFeasibilityCheckListHistory';
//                        break;
//                    case 'duediligence':
//                        $tableName = 'Proj_LBDueDiligenceCheckListHistory';
//                }

                $tableName = 'Proj_LandBankChecklistHistory';
                $select = $sql->select();
                $select->from($tableName)
                    ->columns( array( 'CheckListTransId'))
                    ->where( "CheckListTransId=$CheckListTransId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (count($results) != 0) {
                    return $this->getResponse()
                        ->setStatusCode(200)
                        ->setContent('Y');
                }

                return $this->getResponse()
                    ->setStatusCode(204)
                    ->setContent('N');
            }
        }
    }

    public function getchecklistAction(){
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
                $sql = new Sql($dbAdapter);

                $EnquiryId = $this->bsf->isNullCheck($this->params()->fromPost('EnquiryId'), 'string');

                $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                if($RequestFrom == '') {
                    return $this->getResponse()
                        ->setStatusCode(400)
                        ->setContent('Bad Request');
                }

//                switch($RequestFrom) {
//                    case 'initialfeasibility':
//                        $tableName = 'Proj_LBInitialFeasibilityCheckListTrans';
//                        break;
//                    case 'duediligence':
//                        $tableName = 'Proj_LBDueDiligenceCheckListTrans';
//                }

                $tableName = 'Proj_LandBankChecklistTrans';
                $select = $sql->select();
                $select->from(array('a' => $tableName))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'b.CheckListId=a.CheckListId',array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId', 'TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'))
                    ->where( "EnquiryId='$EnquiryId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (sizeof($results) ==0 ) {
                    return $this->getResponse()
                        ->setStatusCode(201)
                        ->setContent('No content');
                }

                return $this->getResponse()
                    ->setStatusCode(200)
                    ->setContent(json_encode($results));
            }
        }
    }

    public function addchecklisthistoryAction(){
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
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $transId = $this->bsf->isNullCheck($this->params()->fromPost('transid'), 'number');
                    $userid = $this->bsf->isNullCheck($this->params()->fromPost('userid'), 'number');
                    $status = $this->bsf->isNullCheck($this->params()->fromPost('status'), 'string');
                    $remarks = $this->bsf->isNullCheck($this->params()->fromPost('remarks'), 'string');

                    $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                    if($RequestFrom == '') {
                        return $this->getResponse()
                            ->setStatusCode(400)
                            ->setContent('Bad Request');
                    }

//                    switch($RequestFrom) {
//                        case 'initialfeasibility':
//                            $tableName = 'Proj_LBInitialFeasibilityCheckListHistory';
//                            break;
//                        case 'duediligence':
//                            $tableName = 'Proj_LBDueDiligenceCheckListHistory';
//                    }

                    $tableName ='Proj_LandBankChecklistHistory';
                    // create new checklist history
                    $insert = $sql->insert();
                    $insert->into($tableName)
                        ->Values(array('CheckListTransId' => $transId, 'UserId' => $userid, 'RefDate' => date('Y-m-d'), 'Status' => $status, 'Remarks' => $remarks));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from(array('a' => $tableName))
                        ->join(array('b' => 'WF_Users'), 'b.UserId=a.UserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                        ->columns( array( 'RefDate' => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"), 'Status', 'Remarks'))
                        ->where( "TransId='$TransId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    return $this->getResponse()
                        ->setStatusCode(200)
                        ->setContent(json_encode($result));
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }

    public function getchecklisthistoryAction(){
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
                $sql = new Sql($dbAdapter);

                $CheckListTransId = $this->bsf->isNullCheck($this->params()->fromPost('CheckListTransId'), 'number');

                $RequestFrom = $this->bsf->isNullCheck($this->params()->fromQuery('requesttype'), 'string');

                if($RequestFrom == '') {
                    return $this->getResponse()
                        ->setStatusCode(400)
                        ->setContent('Bad Request');
                }

//                switch($RequestFrom) {
//                    case 'initialfeasibility':
//                        $tableName = 'Proj_LBInitialFeasibilityCheckListTrans';
//                        $historytableName = 'Proj_LBInitialFeasibilityCheckListHistory';
//                        break;
//                    case 'duediligence':
//                        $tableName = 'Proj_LBDueDiligenceCheckListTrans';
//                        $historytableName = 'Proj_LBDueDiligenceCheckListHistory';
//                }

                $tableName ='Proj_LandBankChecklistTrans';
                $historytableName ='Proj_LandBankChecklistHistory';

                $select = $sql->select();
                $select->from(array('a' => $tableName))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'b.CheckListId=a.CheckListId',array('CheckListName'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId'))
                    ->where( "CheckListTransId='$CheckListTransId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $chklist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($chklist == FALSE) {
                    return $this->getResponse()
                        ->setStatusCode(201)
                        ->setContent('No content');
                }

                $select = $sql->select();
                $select->from(array('a' => $historytableName))
                    ->join(array('b' => 'WF_Users'), 'b.UserId=a.UserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array( 'RefDate' => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"), 'Status', 'Remarks'))
                    ->where( "CheckListTransId='$CheckListTransId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $chklist['history'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                return $this->getResponse()
                    ->setStatusCode(200)
                    ->setContent(json_encode($chklist));
            }
        }
    }

    public function businessfeasibilityAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || LandBank Business Feasibility");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();
                $files = $request->getFiles();

                $iFeasibilityId = $this->bsf->isNullCheck($postData['FeasibilityId'], 'number');
                if ($iFeasibilityId==0) {
                    $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == false)
                        $RefNo = $postData['RefNo'];
                    else
                        $RefNo = $aVNo["voucherNo"];

                    $insert = $sql->insert();
                    $insert->into('Proj_LandBusinessFeasibility');
                    $insert->Values(array('RefNo' => $this->bsf->isNullCheck($RefNo, 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                    , 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'string'), 'OptionName' => $this->bsf->isNullCheck($postData['OptionName'], 'string')
                    , 'PresentedBy' => $this->bsf->isNullCheck($postData['PresentedBy'], 'string'), 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'], 'number')
                    , 'NoOfBlocks' => $this->bsf->isNullCheck($postData['NoOfBlocks'], 'number'), 'NoOfFloors' => $this->bsf->isNullCheck($postData['NoOfFloors'], 'number')
                    , 'NoOfFlats' => $this->bsf->isNullCheck($postData['NoOfFlats'], 'number'), 'SaleableArea' => $this->bsf->isNullCheck($postData['SaleableArea'], 'number')
                    , 'SaleableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'LeasableArea' => $this->bsf->isNullCheck($postData['LeasableArea'], 'number')
                    , 'LeasableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'CommonArea' => $this->bsf->isNullCheck($postData['CommonArea'], 'number')
                    , 'CommonAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'TotalArea' => $this->bsf->isNullCheck($postData['TotalArea'], 'number')
                    , 'TotalAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'ProjectDescription' => $this->bsf->isNullCheck($postData['ProjectDescription'], 'string')
                    , 'PropLandDevelopmentCost' => $this->bsf->isNullCheck($postData['PropLandDevelopmentCost'], 'number'), 'PropLegalAndApprovalCost' => $this->bsf->isNullCheck($postData['PropLegalAndApprovalCost'], 'number')
                    , 'PropConstructionCost' => $this->bsf->isNullCheck($postData['PropConstructionCost'], 'number'), 'PropTotalProjectCost' => $this->bsf->isNullCheck($postData['PropTotalProjectCost'], 'number')
                    , 'PropSaleableArea' => $this->bsf->isNullCheck($postData['PropSaleableArea'], 'number'), 'PropSaleableAreaUnitId' => $this->bsf->isNullCheck($postData['PropSaleableAreaUnitId'], 'number')
                    , 'PropLeaseableArea' => $this->bsf->isNullCheck($postData['PropLeaseableArea'], 'number'), 'PropLeaseableAreaUnitId' => $this->bsf->isNullCheck($postData['PropLeaseableAreaUnitId'], 'number')
                    , 'PropLeaseableRatePerYear' => $this->bsf->isNullCheck($postData['PropLeaseableRatePerYear'], 'number'),'PropLeaseableRateUnitId' => $this->bsf->isNullCheck($postData['PropLeaseableRateUnitId'], 'number')
                    , 'PropProjectDuration' => $this->bsf->isNullCheck($postData['PropProjectDuration'], 'number')
                    , 'TypeOfDevelopement' => $this->bsf->isNullCheck($postData['TypeOfDevelopement'], 'string')
                    , 'TypeOfBuilding' => $this->bsf->isNullCheck($postData['TypeOfBuilding'], 'string')
                    , 'DwellingOrUnits' => $this->bsf->isNullCheck($postData['DwellingOrUnits'], 'number')
                    , 'ParkingReq' => $this->bsf->isNullCheck($postData['ParkingReq'], 'string')
                    , 'EWS' => $this->bsf->isNullCheck($postData['EWS'], 'string')
                    , 'OSR' => $this->bsf->isNullCheck($postData['OSR'], 'string')
                    , 'PropProjectDurationUnitId' => $this->bsf->isNullCheck($postData['PropProjectDurationUnitId'], 'number')
                    , 'PropSaleableRate' => $this->bsf->isNullCheck($postData['PropSaleableRate'], 'number'), 'PropSaleableRateUnitId' => $this->bsf->isNullCheck($postData['PropSaleableRateUnitId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $feasibilityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4','wmv');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessFeasibilityFiles');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Detail Specifications
                    $specRowId = $this->bsf->isNullCheck($postData['specrowid'], 'number');
                    for ($i = 1; $i <= $specRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['spectitle_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['specdescription_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessSpecDetail');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Title' => $title, 'Description' => $desc));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Layout Drawing
                    $drawingRowId = $this->bsf->isNullCheck($postData['drawingrowid'], 'number');
                    for ($i = 1; $i <= $drawingRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['drawingname_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['drawingdescription_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        $url = '';
                        if ($files['drawingFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['drawingFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessLayoutDrawing');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Title' => $title, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['documentname_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['documentdescription_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        $url = '';
                        if ($files['docFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/landbank/businessfeasibility/' . $feasibilityId . '/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessDocument');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Type' => $title, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Consultant
                    $consultantRowId = $this->bsf->isNullCheck($postData['consultantrowid'], 'number');
                    for ($i = 1; $i <= $consultantRowId; $i++) {
                        $name = $this->bsf->isNullCheck($postData['consultantname_' . $i], 'string');
                        $type = $this->bsf->isNullCheck($postData['consultanttype_' . $i], 'string');
                        $fee = $this->bsf->isNullCheck($postData['fees_' . $i], 'string');
                        $feeAmount = $this->bsf->isNullCheck($postData['feesamount_' . $i], 'number');

                        if ($name == "" || $type == "" || $fee == "" || $feeAmount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessConsultant');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Name' => $name, 'Type' => $type, 'Fee' => $fee, 'FeeAmount' => $feeAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('BFeasibilityDone' => 1))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $id= $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/businessfeasibility', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                } else {
                    $update = $sql->update();
                    $update->table('Proj_LandBusinessFeasibility');
                    $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate'])), 'OptionName' => $this->bsf->isNullCheck($postData['OptionName'], 'string')
                    , 'PresentedBy' => $this->bsf->isNullCheck($postData['PresentedBy'], 'string'), 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'], 'number')
                    , 'NoOfBlocks' => $this->bsf->isNullCheck($postData['NoOfBlocks'], 'number'), 'NoOfFloors' => $this->bsf->isNullCheck($postData['NoOfFloors'], 'number')
                    , 'NoOfFlats' => $this->bsf->isNullCheck($postData['NoOfFlats'], 'number'), 'SaleableArea' => $this->bsf->isNullCheck($postData['SaleableArea'], 'number')
                    , 'SaleableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'LeasableArea' => $this->bsf->isNullCheck($postData['LeasableArea'], 'number')
                    , 'LeasableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'CommonArea' => $this->bsf->isNullCheck($postData['CommonArea'], 'number')
                    , 'CommonAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'TotalArea' => $this->bsf->isNullCheck($postData['TotalArea'], 'number')
                    , 'TotalAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'], 'number'), 'ProjectDescription' => $this->bsf->isNullCheck($postData['ProjectDescription'], 'string')
                    , 'PropLandDevelopmentCost' => $this->bsf->isNullCheck($postData['PropLandDevelopmentCost'], 'number'), 'PropLegalAndApprovalCost' => $this->bsf->isNullCheck($postData['PropLegalAndApprovalCost'], 'number')
                    , 'PropConstructionCost' => $this->bsf->isNullCheck($postData['PropConstructionCost'], 'number'), 'PropTotalProjectCost' => $this->bsf->isNullCheck($postData['PropTotalProjectCost'], 'number')
                    , 'PropSaleableArea' => $this->bsf->isNullCheck($postData['PropSaleableArea'], 'number'), 'PropSaleableAreaUnitId' => $this->bsf->isNullCheck($postData['PropSaleableAreaUnitId'], 'number')
                    , 'PropLeaseableArea' => $this->bsf->isNullCheck($postData['PropLeaseableArea'], 'number'), 'PropLeaseableAreaUnitId' => $this->bsf->isNullCheck($postData['PropLeaseableAreaUnitId'], 'number')
                    , 'PropLeaseableRatePerYear' => $this->bsf->isNullCheck($postData['PropLeaseableRatePerYear'], 'number'),'PropLeaseableRateUnitId' => $this->bsf->isNullCheck($postData['PropLeaseableRateUnitId'], 'number')
                    , 'PropProjectDuration' => $this->bsf->isNullCheck($postData['PropProjectDuration'], 'number')
                    , 'TypeOfDevelopement' => $this->bsf->isNullCheck($postData['TypeOfDevelopement'], 'string')
                    , 'TypeOfBuilding' => $this->bsf->isNullCheck($postData['TypeOfBuilding'], 'string')
                    , 'DwellingOrUnits' => $this->bsf->isNullCheck($postData['DwellingOrUnits'], 'number')
                    , 'ParkingReq' => $this->bsf->isNullCheck($postData['ParkingReq'], 'string')
                    , 'EWS' => $this->bsf->isNullCheck($postData['EWS'], 'string')
                    , 'OSR' => $this->bsf->isNullCheck($postData['OSR'], 'string')
                    , 'PropProjectDurationUnitId' => $this->bsf->isNullCheck($postData['PropProjectDurationUnitId'], 'number'), 'PropSaleableRate' => $this->bsf->isNullCheck($postData['PropSaleableRate'], 'number')));
                    $update->where(array('FeasibilityId'=>$iFeasibilityId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // save photos & video
                    foreach($files['attachedfiles'] as $file) {
                        if (!$file['name'])
                            continue;

                        $dir = 'public/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/';
                        $filename = $this->bsf->uploadFile($dir, $file);

                        // update valid files only
                        if (!$filename)
                            continue;

                        $url = '/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/' . $filename;

                        $imgExts = array('jpeg', 'jpg', 'png');
                        $videoExts = array('mp4','wmv','mpg');
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if(in_array($ext, $imgExts))
                            $type = 'image';
                        else if(in_array($ext, $videoExts))
                            $type = 'video';

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessFeasibilityFiles');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'URL' => $url, 'FileType' => $type));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandBusinessSpecDetail')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Detail Specifications
                    $specRowId = $this->bsf->isNullCheck($postData['specrowid'], 'number');
                    for ($i = 1; $i <= $specRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['spectitle_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['specdescription_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessSpecDetail');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Title' => $title, 'Description' => $desc));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandBusinessLayoutDrawing')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Layout Drawing
                    $drawingRowId = $this->bsf->isNullCheck($postData['drawingrowid'], 'number');
                    for ($i = 1; $i <= $drawingRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['drawingname_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['drawingdescription_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['drawingFile_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        if($url == '') {
                            if ($files['drawingFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['drawingFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessLayoutDrawing');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Title' => $title, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandBusinessDocument')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Documents
                    $documentRowId = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                    for ($i = 1; $i <= $documentRowId; $i++) {
                        $title = $this->bsf->isNullCheck($postData['documentname_' . $i], 'string');
                        $desc = $this->bsf->isNullCheck($postData['documentdescription_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['docFile_' . $i], 'string');

                        if ($title == "" || $desc == "")
                            continue;

                        if($url == '') {
                            if ($files['docFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/landbank/businessfeasibility/' . $iFeasibilityId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessDocument');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Type' => $title, 'Description' => $desc, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandBusinessConsultant')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Consultant
                    $consultantRowId = $this->bsf->isNullCheck($postData['consultantrowid'], 'number');
                    for ($i = 1; $i <= $consultantRowId; $i++) {
                        $name = $this->bsf->isNullCheck($postData['consultantname_' . $i], 'string');
                        $type = $this->bsf->isNullCheck($postData['consultanttype_' . $i], 'string');
                        $fee = $this->bsf->isNullCheck($postData['fees_' . $i], 'string');
                        $feeAmount = $this->bsf->isNullCheck($postData['feesamount_' . $i], 'number');

                        if ($name == "" || $type == "" || $fee == "" || $feeAmount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBusinessConsultant');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Name' => $name, 'Type' => $type, 'Fee' => $fee, 'FeeAmount' => $feeAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('BFeasibilityDone' => 1))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $id= $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/businessfeasibility', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                }
            } catch (PDOException $e) {
                $connection->rollback();
            }
        } else {
            $aVNo = CommonHelper::getVoucherNo(105, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            // Area Unit List
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where('TypeId=2');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Duration Unit List
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where("TypeId=5 and UnitDescription NOT IN ('Hour','Minute','Second')");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->durationunittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Leaseable Rate List
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where("TypeId=5 and UnitDescription NOT IN ('Day','Hour','Minute','Second')");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Leasetypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Property Names
//            $select = $sql->select();
//            $select->from('Proj_LandEnquiry')
//                ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Project Type
            $select = $sql->select();
            $select->from('Proj_ProjectTypeMaster')
                ->columns(array('data' => 'ProjectTypeId', 'value' => 'ProjectTypeName'))
                ->where(array("ProjectTypeId Not in(8)"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Autocomplete Consultant Type
            $select = $sql->select();
            $select->from('Proj_LandBusinessConsultant')
                ->columns(array(new Expression("DISTINCT Type as value")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ConsultantType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Autocomplete Consultant Name
            $select = $sql->select();
            $select->from('Proj_LandBusinessConsultant')
                ->columns(array(new Expression("DISTINCT Name as value")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ConsultantName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $feasibilityId = $this->bsf->isNullCheck($this->params()->fromRoute('feasibilityId'), 'number');
            $inEquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
            $snEnquiryName="";

            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('EnquiryId','PropertyName'))
                ->where("EnquiryId=$inEquiryId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $nEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($nEnquiry)) {
                $snEnquiryName= $nEnquiry['PropertyName'];
            }

            $this->_view->EnquiryId= $inEquiryId;
            $this->_view->EnquiryName= $snEnquiryName;

            if (isset($feasibilityId) && $feasibilityId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                    ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId = b.EnquiryId', array('PropertyName'))
                    ->join(array('c' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId = c.ProjectTypeId', array('ProjectTypeName'))
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landBusiness = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_LandBusinessSpecDetail')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landBusinessSpec = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandBusinessLayoutDrawing')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landBusinessDrawing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandBusinessDocument')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landBusinessDocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandBusinessConsultant')
                    ->where('FeasibilityId=' . $feasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landBusinessConsultant = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandBusinessFeasibilityFiles')
                    ->where("FeasibilityId=$feasibilityId AND FileType='image'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $images = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($images) > 0) {
                    $this->_view->images = $images;
                }

                $select = $sql->select();
                $select->from('Proj_LandBusinessFeasibilityFiles')
                    ->where("FeasibilityId=$feasibilityId AND FileType='video'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $videos = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($videos != FALSE && count($videos) > 0) {
                    $this->_view->videos = $videos;
                }
            }

            $this->_view->feasibilityId = (isset($feasibilityId) && $feasibilityId != 0) ? $feasibilityId : 0;
            $this->_view->page = (isset($page) && $page != '') ? $page : '';
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }


    public function financialfeasibilityAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Financial Feasibility");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');
                    $pageUrl = $this->bsf->isNullCheck($request->getPost('pageUrl'), 'string');

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                        ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.PropSaleableAreaUnitId=d.UnitId', array('PropSaleableAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                        ->join(array('e'=>'Proj_LandEnquiry'),'a.EnquiryId=e.EnquiryId',array('FFeasibilityId','FFeasibilityDone'),$select::JOIN_LEFT)
                        ->columns(array('OptionName', 'PropTotalProjectCost', 'TotalArea', 'PropSaleableArea', 'FeasibilityId'), array('ProjectTypeName'))
                        ->where("a.EnquiryId=$EnquiryId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $feasibilities = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $response = $this->getResponse();
                    if (!count($feasibilities)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $response->setContent($this->generateFinancialFeasibilityData($feasibilities, $EnquiryId,$pageUrl,$viewRenderer->basePath()));
                    }

                    return $response;
                } catch (PDOException $e) {

                }
            }
        } else {
            if ($request->isPost()) {

            } else {
                $iEnquiryId =  $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
                $this->_view->enquiryId= $iEnquiryId;

                $aVNo = CommonHelper::getVoucherNo(106, date('Y/m/d'), 0, 0, $dbAdapter, "");
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->columns(array('PropertyName'))
                 ->where("EnquiryId=$iEnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->property= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                // Property Names
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->page = (isset($page) && $page != '') ? $page : '';
            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function financialfeasibilitydetailAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast | Financial Feasibility Detail");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();

                $iFeasibilityId = $this->bsf->isNullCheck($postData['FinancialFeasibilityId'], 'number');
                $page =  $this->bsf->isNullCheck($this->params()->fromRoute('page'),'number');
                if ($iFeasibilityId==0) {
                    $aVNo = CommonHelper::getVoucherNo(106, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == false)
                        $RefNo = $postData['RefNo'];
                    else
                        $RefNo = $aVNo["voucherNo"];

                    $insert = $sql->insert();
                    $insert->into('Proj_LandFianancialFeasibility');
                    $insert->Values(array('RefNo' => $this->bsf->isNullCheck($RefNo, 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                    , 'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number'), 'BusinessFeasibilityId' => $this->bsf->isNullCheck($postData['BusinessFeasibilityId'], 'number')
                    , 'ProposalCost' => $this->bsf->isNullCheck($postData['TotalProposalCost'], 'number'), 'ExpectedIncome' => $this->bsf->isNullCheck($postData['TotalExpectedIncome'], 'number')
                    , 'CapitalAmount' => $this->bsf->isNullCheck($postData['CapitalAmount'], 'number'), 'InvestorAmount' => $this->bsf->isNullCheck($postData['InvestorAmount'], 'number')
                    , 'LoanAmount' => $this->bsf->isNullCheck($postData['LoanAmount'], 'number'), 'JVAmount' => $this->bsf->isNullCheck($postData['JVAmount'], 'number')
                    , 'InterestRate' => $this->bsf->isNullCheck($postData['InterestRate'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $feasibilityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // Proposal Project Cost
                    $pcostrowid = $this->bsf->isNullCheck($postData['pcostrowid'], 'number');
                    for ($i = 1; $i <= $pcostrowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['pcostparticular_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['pcostamount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialExpenseTrans');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Particular' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Expected Income
                    $exincomerowid = $this->bsf->isNullCheck($postData['exincomerowid'], 'number');
                    for ($i = 1; $i <= $exincomerowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['exparticular_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['examount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialIncomeTrans');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'Particular' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Project Schedule & Fund Flow
                    $constructionrowid = $this->bsf->isNullCheck($postData['constructionrowid'], 'number');
                    for ($i = 1; $i <= $constructionrowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['constructionyear_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['constructionamount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialScheduleTrans');
                        $insert->Values(array('FeasibilityId' => $feasibilityId, 'ShYear' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('FFeasibilityDone' => 1,'FFeasibilityId'=>$feasibilityId))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $id= $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                } else {
                    $update = $sql->update();
                    $update->table('Proj_LandFianancialFeasibility');
                    $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string'), 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                    , 'ProposalCost' => $this->bsf->isNullCheck($postData['TotalProposalCost'], 'number'), 'ExpectedIncome' => $this->bsf->isNullCheck($postData['TotalExpectedIncome'], 'number')
                    , 'CapitalAmount' => $this->bsf->isNullCheck($postData['CapitalAmount'], 'number'), 'InvestorAmount' => $this->bsf->isNullCheck($postData['InvestorAmount'], 'number')
                    , 'LoanAmount' => $this->bsf->isNullCheck($postData['LoanAmount'], 'number'), 'JVAmount' => $this->bsf->isNullCheck($postData['JVAmount'], 'number')
                    , 'InterestRate' => $this->bsf->isNullCheck($postData['InterestRate'], 'number')));
                    $update->where(array('FeasibilityId'=>$iFeasibilityId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandFinancialExpenseTrans')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Proposal Project Cost
                    $pcostrowid = $this->bsf->isNullCheck($postData['pcostrowid'], 'number');
                    for ($i = 1; $i <= $pcostrowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['pcostparticular_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['pcostamount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialExpenseTrans');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Particular' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandFinancialIncomeTrans')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Expected Income
                    $exincomerowid = $this->bsf->isNullCheck($postData['exincomerowid'], 'number');
                    for ($i = 1; $i <= $exincomerowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['exparticular_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['examount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialIncomeTrans');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'Particular' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandFinancialScheduleTrans')
                        ->where("FeasibilityId=$iFeasibilityId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Project Schedule & Fund Flow
                    $constructionrowid = $this->bsf->isNullCheck($postData['constructionrowid'], 'number');
                    for ($i = 1; $i <= $constructionrowid; $i++) {
                        $particular = $this->bsf->isNullCheck($postData['constructionyear_' . $i], 'string');
                        $amount = $this->bsf->isNullCheck($postData['constructionamount_' . $i], 'number');

                        if ($particular == "" || $amount == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_LandFinancialScheduleTrans');
                        $insert->Values(array('FeasibilityId' => $iFeasibilityId, 'ShYear' => $particular, 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry')
                        ->set(array('FFeasibilityDone' => 1))
                        ->where(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $id= $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                    $connection->commit();
                    if($postData['pageUrl'] == 'F') {
                        $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$id));
                    } else {
                        $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                    }
                }

            } catch (PDOException $e) {
                $connection->rollback();

            }
        } else {
            $EnquiryId = $this->params()->fromRoute('EnquiryId');
            $FeasibilityId = $this->params()->fromRoute('FeasibilityId');
            $FinancialFeasibilityId = $this->params()->fromRoute('FinancialFeasibilityId');
            $aVNo = CommonHelper::getVoucherNo(106, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $select = $sql->select();
            $select->from('Proj_WorkTypeMaster')
                ->columns(array('data' => 'WorkTypeId', 'value' => 'WorkType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->worktype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_ExpectedIncomeMaster')
                ->columns(array('value' => 'Particular'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_expectedincomeParticulars = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_ProposalCostMaster')
                ->columns(array('value' => 'Particular'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_proposalCostParticulars = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if (isset($FeasibilityId) && $FeasibilityId != 0 && isset($EnquiryId) && $EnquiryId != 0) {
                // Property Name
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->columns(array('EnquiryId', 'PropertyName','SaleTypeId'))
                    ->where("EnquiryId=$EnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->enquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                    ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'a.PropSaleableAreaUnitId=d.UnitId', array('PropSaleableAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'a.PropProjectDurationUnitId=e.UnitId', array('PropProjectDurationUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('OptionName', 'PropTotalProjectCost', 'TotalArea', 'PropSaleableArea', 'FeasibilityId', 'PropProjectDuration'))
                    ->where("a.FeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->feasibility = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->enquiryId = (isset($EnquiryId) && $EnquiryId != 0) ? $EnquiryId : 0;
                $this->_view->feasibilityId = (isset($FeasibilityId) && $FeasibilityId != 0) ? $FeasibilityId : 0;
            }

            if (isset($FinancialFeasibilityId) && $FinancialFeasibilityId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandFianancialFeasibility'))
                    ->where('FeasibilityId=' . $FinancialFeasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landFinancial = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_LandFinancialExpenseTrans')
                    ->where('FeasibilityId=' . $FinancialFeasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landFinancialExp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandFinancialIncomeTrans')
                    ->where('FeasibilityId=' . $FinancialFeasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landFinancialInc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_LandFinancialScheduleTrans')
                    ->where('FeasibilityId=' . $FinancialFeasibilityId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->landFinancialSch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->financialId = (isset($FinancialFeasibilityId) && $FinancialFeasibilityId != 0) ? $FinancialFeasibilityId : 0;
            $this->_view->page = (isset($page) && $page != '') ? $page : '';

        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function duediligenceAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Due Diligence");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');
        $dueDiligenceId = $this->bsf->isNullCheck($this->params()->fromRoute('dueDiligenceId'), 'number');
        $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isPost()) {
            try {
                $postData = $request->getPost();
                $RefDate = $postData['RefDate'];
                $EnquiryId = $postData['EnquiryId'];
//                echo '<pre>'; print_r($postData); die;

                $aVNo = CommonHelper::getVoucherNo(107, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                if ($aVNo["genType"] == false)
                    $voucherNo = $postData['RefNo'];
                else
                    $voucherNo = $aVNo["voucherNo"];

                $connection->beginTransaction();
                if($dueDiligenceId == 0) {
                    //LandBank - DueDiligence Register
                    $insert = $sql->insert();
                    $insert->into('Proj_LandDueDiligence');
                    $insert->Values(array('RefNo' => $voucherNo
                    , 'RefDate' => date('Y-m-d', strtotime($RefDate))
                    , 'EnquiryId' => $EnquiryId));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $dueDiligenceId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $ecrowid = $this->bsf->isNullCheck($postData['ecrowid'], 'number');
                    for ($i = 1; $i <= $ecrowid; $i++) {
                        $insert = $sql->insert();
                        $insert->into( 'Proj_LandEncumbranceDetails' );
                        $insert->Values( array( 'DueDiligenceId' => $dueDiligenceId
                        , 'OwnerId' => $this->bsf->isNullCheck( $postData[ 'OwnerId_' . $i ], 'number' )
                        , 'SlNo' => $this->bsf->isNullCheck( $postData[ 'slno_' . $i ], 'string' )
                        , 'DocDescription' => $this->bsf->isNullCheck( $postData[ 'docdesc_' . $i ], 'string' )
                        , 'DocNo' => $this->bsf->isNullCheck( $postData[ 'docno_' . $i ], 'string' )
                        , 'VendorName' => $this->bsf->isNullCheck( $postData[ 'exec_' . $i ], 'string' )
                        , 'ClaimantName' => $this->bsf->isNullCheck( $postData[ 'claimant_' . $i ], 'string' )
                        , 'ExtSlNo' => $this->bsf->isNullCheck( $postData[ 'exnt_' . $i ], 'string' )
                        , 'ParentDocRefNo' => $this->bsf->isNullCheck( $postData[ 'docref_' . $i ], 'string' ) ) );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    }

                    $update = $sql->update();
                    $update->table('Proj_LandEnquiry');
                    $update->set(array('DueDiligenceId' => $dueDiligenceId));
                    $update->where(array('EnquiryId'=>$EnquiryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // legal checklist
                    $newCheckLists = array();
                    $chkRowId = $this->bsf->isNullCheck($postData['legalchk-rowid'], 'number');
                    for ($i = 1; $i <= $chkRowId; $i++) {
                        $id = $this->bsf->isNullCheck($postData['id_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '3'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBankChecklistTrans');
                        $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//                        $insert = $sql->insert();
//                        $insert->into('Proj_LBDueDiligenceCheckListTrans');
//                        $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // financial checklist
                    $finChkRowId = $this->bsf->isNullCheck($postData['financialchk-rowid'], 'number');
                    for ($i = 1; $i <= $finChkRowId; $i++) {
                        $id = $this->bsf->isNullCheck($postData['id_fin_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_fin_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_fin_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_fin_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_fin_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '4'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }


                        $insert = $sql->insert();
                        $insert->into('Proj_LandBankChecklistTrans');
                        $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                        $insert = $sql->insert();
//                        $insert->into('Proj_LBDueDiligenceCheckListTrans');
//                        $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                        , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else {
                    //LandBank - DueDiligence Register
                    $update = $sql->update();
                    $update->table('Proj_LandDueDiligence');
                    $update->set(array('RefNo' => $voucherNo
                    , 'RefDate' => date('Y-m-d', strtotime($RefDate))
                    , 'EnquiryId' => $EnquiryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $ecrowid = $this->bsf->isNullCheck($postData['ecrowid'], 'number');
                    for ($i = 1; $i <= $ecrowid; $i++) {
                        $embId = $this->bsf->isNullCheck( $postData[ 'encumbranceid_' . $i ], 'number' );
                        $update = $sql->update();
                        $update->table( 'Proj_LandEncumbranceDetails' );
                        $update->set( array( 'DueDiligenceId' => $dueDiligenceId
                        , 'OwnerId' => $this->bsf->isNullCheck( $postData[ 'OwnerId_' . $i ], 'number' )
                        , 'SlNo' => $this->bsf->isNullCheck( $postData[ 'slno_' . $i ], 'string' )
                        , 'DocDescription' => $this->bsf->isNullCheck( $postData[ 'docdesc_' . $i ], 'string' )
                        , 'DocNo' => $this->bsf->isNullCheck( $postData[ 'docno_' . $i ], 'string' )
                        , 'VendorName' => $this->bsf->isNullCheck( $postData[ 'exec_' . $i ], 'string' )
                        , 'ClaimantName' => $this->bsf->isNullCheck( $postData[ 'claimant_' . $i ], 'string' )
                        , 'ExtSlNo' => $this->bsf->isNullCheck( $postData[ 'exnt_' . $i ], 'string' )
                        , 'ParentDocRefNo' => $this->bsf->isNullCheck( $postData[ 'docref_' . $i ], 'string' ) ) )
                            ->where("EncumbranceId=$embId");
                        $statement = $sql->getSqlStringForSqlObject( $update );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    }

                    // legal checklist
                    $newCheckLists = array();
                    $chkRowId = $this->bsf->isNullCheck($postData['legalchk-rowid'], 'number');
                    for ($i = 1; $i <= $chkRowId; $i++) {
                        $transid = $this->bsf->isNullCheck($postData['transid_' . $i], 'string');
                        $updaterow = $this->bsf->isNullCheck($postData['updaterow_' . $i], 'string');
                        $id = $this->bsf->isNullCheck($postData['id_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '3'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }

                        if($transid == 0 && $updaterow == 0) {

                            $insert = $sql->insert();
                            $insert->into( 'Proj_LandBankChecklistTrans' );
                            $insert->Values( array( 'CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                            , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );


//                            $insert = $sql->insert();
//                            $insert->into( 'Proj_LBDueDiligenceCheckListTrans' );
//                            $insert->Values( array( 'CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                             , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
//                            $statement = $sql->getSqlStringForSqlObject( $insert );
//                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        } else if($transid != 0 && $updaterow == 1){

                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistTrans');
                            $update->set( array( 'CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                            , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
                            $update->where(array('CheckListTransId'=>$transid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                            $update = $sql->update();
//                            $update->table('Proj_LBDueDiligenceCheckListTrans');
//                            $update->set( array( 'CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                          , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
//                            $update->where(array('CheckListTransId'=>$transid));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    // financial checklist
                    $finChkRowId = $this->bsf->isNullCheck($postData['financialchk-rowid'], 'number');
                    for ($i = 1; $i <= $finChkRowId; $i++) {
                        $transid = $this->bsf->isNullCheck($postData['transid_fin_' . $i], 'string');
                        $updaterow = $this->bsf->isNullCheck($postData['updaterow_fin_' . $i], 'string');
                        $id = $this->bsf->isNullCheck($postData['id_fin_' . $i], 'string');
                        $name = $this->bsf->isNullCheck($postData['name_fin_' . $i], 'string');
                        $assignedTo = $this->bsf->isNullCheck($postData['assignedto_fin_' . $i], 'number');
                        $progress = $this->bsf->isNullCheck($postData['progress_fin_' . $i], 'string');
                        $date = $this->bsf->isNullCheck($postData['date_fin_' . $i], 'string');

                        if ($id =='' || $name == '' || $assignedTo == 0 || $progress == '' || $date == '')
                            continue;

                        if($id == 'new' && !array_key_exists($name, $newCheckLists)) {
                            $insert = $sql->insert();
                            $insert->into('Proj_CheckListMaster');
                            $insert->Values(array('CheckListName' => $name, 'TypeId' => '4'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $id = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $newCheckLists[$name] = $id;
                        } else if(array_key_exists($name, $newCheckLists)){
                            $id = $newCheckLists[$name];
                        }

                        if($transid == 0 && $updaterow == 0) {

                            $insert = $sql->insert();
                            $insert->into('Proj_LandBankChecklistTrans');
                            $insert->Values(array('CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                            , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//                            $insert = $sql->insert();
//                            $insert->into('Proj_LBDueDiligenceCheckListTrans');
//                            $insert->Values(array('CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                            , 'TargetDate' => date('Y-m-d', strtotime($date)), 'Status' => $progress));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if($transid != 0 && $updaterow == 1){

                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistTrans');
                            $update->set( array( 'CheckListId' => $id, 'AssignUserId' => $assignedTo, 'EnquiryId' => $EnquiryId
                            , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
                            $update->where(array('CheckListTransId'=>$transid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                            $update = $sql->update();
//                            $update->table('Proj_LBDueDiligenceCheckListTrans');
//                            $update->set( array( 'CheckListId' => $id, 'AssignedTo' => $assignedTo, 'DueDiligenceId' => $dueDiligenceId
//                                          , 'TargetDate' => date( 'Y-m-d', strtotime( $date ) ), 'Status' => $progress ) );
//                            $update->where(array('CheckListTransId'=>$transid));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }
                $connection->commit();
                if($postData['pageUrl'] == 'F') {
                    $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$EnquiryId));
                } else {
                    $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                }
            } catch (PDOException $e) {
                $connection->rollback();
            }
        } else {
            $aVNo = CommonHelper::getVoucherNo(107, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            // Property Names
            $subQuery = $sql->select();
            $subQuery->from("Proj_LandDueDiligence")
                ->columns(array("EnquiryId"));

            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'))
                ->where->expression('EnquiryId Not IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // users
            $select = $sql->select();
            $select->from('WF_Users')
                ->columns(array('data' => 'UserId', 'value' => 'UserName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            $select = $sql->select();
//            $select->from('proj_landinitialfeasibility')
//                ->columns(array('FeasibilityId'))
//                ->where(array("EnquiryId=$enquiryId"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $Feasibility= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            $FeasibilityId=$Feasibility['FeasibilityId'];

            // legal checklist
            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_CheckListMaster' ))
                ->columns(array('data' => 'CheckListId', 'value' => 'CheckListName'))
                ->where("a.DeleteFlag='0' AND TypeId='3'")
                ->order('a.CheckListName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->legalchecklists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

//            $select = $sql->select();
//            $select->from(array('b' =>'Proj_InitialOwnerDetail'))
//                ->join(array('c' => 'Proj_UOM'), 'b.LandAreaUnitId=c.UnitId', array('LandAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
//                ->where("b.FeasibilityId=$FeasibilityId");
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $encumbrancedetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            $this->_view->encumbrancedetails=$encumbrancedetails;
            // financial checklist
            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_CheckListMaster' ))
                ->columns(array('data' => 'CheckListId', 'value' => 'CheckListName'))
                ->where("a.DeleteFlag='0' AND TypeId='4'")
                ->order('a.CheckListName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->financialchecklists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

//            $dueDiligenceId = $this->bsf->isNullCheck($this->params()->fromRoute('dueDiligenceId'), 'number');
            $iEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
            if($iEnquiryId != 0) {
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->where('EnquiryId=' . $iEnquiryId );
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->EnqName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->Enq =$iEnquiryId;
            }

            $this->_view->duediligenceid = $dueDiligenceId;
            if (isset($dueDiligenceId) && $dueDiligenceId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandDueDiligence'))
                    ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select::JOIN_LEFT)
                    ->where('a.DueDiligenceId=' . $dueDiligenceId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->duediligencedetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'Proj_LandEncumbranceDetails'))
                    ->join(array('b' => 'Proj_InitialOwnerDetail'), 'a.OwnerId=b.OwnerId', array('*'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.LandAreaUnitId=c.UnitId', array('LandAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where("a.DueDiligenceId=$dueDiligenceId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $encumbrancedetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($encumbrancedetails) > 0)
                    $this->_view->encumbrancedetails = $encumbrancedetails;

//                echo '<pre>'; print_r($this->_view->encumbrancedetails); die;

                $select = $sql->select();
                $select->from(array('a' => "Proj_LandBankChecklistTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignUserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignUserId','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
                $select->where("a.EnquiryId=$iEnquiryId AND b.TypeId= '3'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->legalchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from(array('a' => "Proj_LBDueDiligenceCheckListTrans"))
//                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
//                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignedTo','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
//                $select->where("a.DueDiligenceId=$dueDiligenceId AND b.TypeId = '3'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->legalchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_LandBankChecklistTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignUserId',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignUserId','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
                $select->where("a.EnquiryId=$iEnquiryId AND b.TypeId= '4'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->financialchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//echo $page;die;
                $this->_view->page = (isset($page) && $page != '') ? $page : '';

//                $select = $sql->select();
//                $select->from(array('a' => "Proj_LBDueDiligenceCheckListTrans"))
//                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName', 'TypeId'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'WF_Users'), 'c.UserId=a.AssignedTo',array('UserName', 'UserLogo'), $select::JOIN_LEFT)
//                    ->columns( array('CheckListTransId', 'CheckListId', 'AssignedTo','TargetDate' => new Expression("FORMAT(a.TargetDate, 'dd-MM-yyyy')"), 'Status'));
//                $select->where("a.DueDiligenceId=$dueDiligenceId AND b.TypeId = '4'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->financialchecklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function registerAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Land Bank Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {




        } else {
            //$EnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('EnquiryId'), 'number');
//            if (!$EnquiryId) {
//                // enquires
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('b' => 'Proj_SourceMaster'), 'a.SourceId=b.SourceId', array('SourceFrom' => 'SourceName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
                ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns(array('EnquiryId', 'RefNo', 'RefDate'=>new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"),'PropertyName', 'SourceName',
                    'TotalArea', 'LandCost', 'PropertyLocation', 'ContactNo', 'Email','IFeasibilityId','BFeasibilityDone','FFeasibilityDone','DueDiligenceId','FinalizationId','ConceptionDone','KickoffDone','Latitude','Longitude','Radius','PropImageURL'));
            $select->order('a.RefDate DESC');
             $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrEnquires = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('value'=>'CityName'), $select::JOIN_LEFT)
                ->columns(array('data' => new Expression("Distinct a.CityId")))
                ->where("a.CityId<>0");
            $select->order('d.CityName');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrlocation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->columns(array('value' => new Expression("Distinct a.SourceName"),'data'=> new Expression("'0'")));
            $select->order('a.SourceName');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrsource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $minDate = date("Y/m/d");
            $maxDate = date("Y/m/d");

            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->columns(array('minDate' => new Expression("Min(a.RefDate)"),'maxDate'=> new Expression("Max(a.RefDate)")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $minmaxDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($minmaxDate)) {
                $minDate =  $this->bsf->isNullCheck($minmaxDate['minDate'],'date');
                $maxDate =  $this->bsf->isNullCheck($minmaxDate['maxDate'],'date');
            }
            $this->_view->minDate= $minDate;
            $this->_view->maxDate= $maxDate;

//            $this->_view->enquiryId= $EnquiryId;
//            $this->_view->feasibilityId= $FeasibilityId;
//            $this->_view->conceptionId= $iConceptionId;
//            $this->_view->mode = $mode;


//            } else {
            // enquiry details

//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_LandEnquiry'))
//                    ->join(array('b' => 'Proj_SourceMaster'), 'a.SourceId=b.SourceId', array('SourceFrom' => 'SourceName'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
//                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('CityName'), $select::JOIN_LEFT)
//                    ->columns(array('EnquiryId', 'RefNo', 'RefDate', 'PropertyName', 'SourceName', 'TotalArea', 'LandCost', 'PropertyLocation', 'ContactNo', 'Email'))
//                    ->where("a.EnquiryId=$EnquiryId");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->enquiryDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();



//            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getduediligencedetailsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_InitialOwnerDetail'))
                    ->join(array('b' => 'Proj_LandInitialFeasibility'), 'a.FeasibilityId=b.FeasibilityId', array(), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.LandAreaUnitId=c.UnitId', array('LandAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('SurveyNo'=>new Expression("a.SurveyNo"), 'PattaNo'=>new Expression("a.PattaNo")
                    , 'PattaName'=>new Expression("a.PattaName"), 'LandArea'=>new Expression("a.LandArea"),
                        'OwnerName'=>new Expression("a.OwnerName")
                    , 'OwnerId'=>new Expression("a.OwnerId")))
                    ->where("b.EnquiryId=$EnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response = $this->getResponse();
                if (!sizeof($details)) {
                    $response->setContent('No Data');
                    $response->setStatusCode('204');
                } else
                    $response->setContent($this->generateDueDiligenceData($details));

                return $response;
            }
        }
    }

    public function finalizationAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" LandBank Finalization");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isPost()) {
            try {
                $postData = $request->getPost();
//                $finalizationId = $this->bsf->isNullCheck($postData['finalizationId'], 'number');
                $finalizationId =  $this->bsf->isNullCheck($this->params()->fromRoute('finalizationId'),'number');
                $iEnquiryId = $this->bsf->isNullCheck( $postData[ 'EnquiryId' ], 'number' );
//                echo '<pre>'; print_r($postData); die;
//                echo $postData['pageUrl'];die;
                $connection->beginTransaction();
                if($finalizationId == 0) {
                    $aVNo = CommonHelper::getVoucherNo(108, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == false)
                        $RefNo = $postData['RefNo'];
                    else
                        $RefNo = $aVNo["voucherNo"];

                    $insert = $sql->insert();
                    $insert->into( 'Proj_LandBankFinalization' );
                    $insert->Values( array( 'RefNo' => $this->bsf->isNullCheck( $RefNo, 'string' ), 'RefDate' => date( 'Y-m-d', strtotime( $postData[ 'RefDate' ] ) )
                    , 'EnquiryId' => $this->bsf->isNullCheck( $postData[ 'EnquiryId' ], 'number' ), 'SaleTypeId' => $this->bsf->isNullCheck( $postData[ 'SaleTypeId' ], 'number' )
                    , 'FinalAmount' => $this->bsf->isNullCheck( $postData[ 'FinalAmount' ], 'number' ), 'AccountNo' => $this->bsf->isNullCheck( $postData[ 'AccountNo' ], 'string' )
                    , 'BranchName' => $this->bsf->isNullCheck( $postData[ 'BranchName' ], 'string' ), 'BankName' => $this->bsf->isNullCheck( $postData[ 'BankName' ], 'string' )
                    , 'CityId' => $this->bsf->isNullCheck( $postData[ 'CityId' ], 'number' ), 'IFSCCode' => $this->bsf->isNullCheck( $postData[ 'IFSCCode' ], 'string' )
                    , 'AccountName' => $this->bsf->isNullCheck( $postData[ 'AccountName' ], 'string' ) ) );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    $finalizationId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // Schedule Details
                    $scheduleRowId = $this->bsf->isNullCheck( $postData[ 'scheduleRowId' ], 'number' );
                    for ( $i = 1; $i <= $scheduleRowId; $i++ ) {
                        $insert = $sql->insert();
                        $insert->into( 'Proj_LBFinalizationOwnerDetail' );
                        $insert->Values( array( 'FinalizationId' => $finalizationId, 'OwnerId' => $this->bsf->isNullCheck( $postData[ 'ownerId_' . $i ], 'number' ),
                            'ShareTypeId' => $this->bsf->isNullCheck( $postData[ 'shareTypeId_' . $i ], 'number' ), 'Percentage' => $this->bsf->isNullCheck( $postData[ 'sharePercentage_' . $i ], 'number' ),
                            'Amount' => $this->bsf->isNullCheck( $postData[ 'shareAmount_' . $i ], 'number' ) ) );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        $finalizationOwnerId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        // Payment Details
                        $paymentdetailid = $this->bsf->isNullCheck( $postData[ 'paymentdetailid_' . $i ], 'number' );
                        for ( $j = 1; $j <= $paymentdetailid; $j++ ) {
                            $desc = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_desc_' . $j ], 'string' );
                            $date = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_date_' . $j ], 'string' );
                            $amt = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_amt_' . $j ], 'number' );

                            if ( $desc == "" || $date == "" || $amt == "" )
                                continue;

                            $insert = $sql->insert();
                            $insert->into( 'Proj_LBFinalizationPaymentSchedule' );
                            $insert->Values( array( 'FinalizationOwnerId' => $finalizationOwnerId, 'Date' => date( 'Y-m-d', strtotime( $date ) ), 'Description' => $desc, 'Amount' => $amt ) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                    }
                } else {
                    $update = $sql->update();
                    $update->table( 'Proj_LandBankFinalization' );
                    $update->set( array( 'RefNo' => $this->bsf->isNullCheck( $postData[ 'RefNo' ], 'string' ), 'RefDate' => date( 'Y-m-d', strtotime( $postData[ 'RefDate' ] ) )
                    , 'EnquiryId' => $this->bsf->isNullCheck( $postData[ 'EnquiryId' ], 'number' ), 'SaleTypeId' => $this->bsf->isNullCheck( $postData[ 'SaleTypeId' ], 'number' )
                    , 'FinalAmount' => $this->bsf->isNullCheck( $postData[ 'FinalAmount' ], 'number' ), 'AccountNo' => $this->bsf->isNullCheck( $postData[ 'AccountNo' ], 'string' )
                    , 'BranchName' => $this->bsf->isNullCheck( $postData[ 'BranchName' ], 'string' ), 'BankName' => $this->bsf->isNullCheck( $postData[ 'BankName' ], 'string' )
                    , 'CityId' => $this->bsf->isNullCheck( $postData[ 'CityId' ], 'number' ), 'IFSCCode' => $this->bsf->isNullCheck( $postData[ 'IFSCCode' ], 'string' )
                    , 'AccountName' => $this->bsf->isNullCheck( $postData[ 'AccountName' ], 'string' ) ) )
                            ->where(array('FinalizationId'=>$finalizationId));
                    $statement = $sql->getSqlStringForSqlObject( $update );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    // Schedule Details
                    $scheduleRowId = $this->bsf->isNullCheck( $postData[ 'scheduleRowId' ], 'number' );
                    for ( $i = 1; $i <= $scheduleRowId; $i++ ) {
                        $transid = $this->bsf->isNullCheck( $postData[ 'ownertransid_' . $i ], 'number' );
                        $updaterow = $this->bsf->isNullCheck( $postData[ 'ownerupdaterow_' . $i ], 'number' );

                        $finalizationOwnerId = $transid;
                        if($transid == 0) { //&& $updaterow == 0
                            $insert = $sql->insert();
                            $insert->into( 'Proj_LBFinalizationOwnerDetail' );
                            $insert->Values( array( 'FinalizationId' => $finalizationId, 'OwnerId' => $this->bsf->isNullCheck( $postData[ 'ownerId_' . $i ], 'number' ),
                                'ShareTypeId' => $this->bsf->isNullCheck( $postData[ 'shareTypeId_' . $i ], 'number' ), 'Percentage' => $this->bsf->isNullCheck( $postData[ 'sharePercentage_' . $i ], 'number' ),
                                'Amount' => $this->bsf->isNullCheck( $postData[ 'shareAmount_' . $i ], 'number' ) ) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $finalizationOwnerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        } else if ($transid != 0){ //&& $updaterow == 1
                            $update = $sql->update();
                            $update->table( 'Proj_LBFinalizationOwnerDetail' );
                            $update->set( array( 'FinalizationId' => $finalizationId, 'OwnerId' => $this->bsf->isNullCheck( $postData[ 'ownerId_' . $i ], 'number' ),
                                'ShareTypeId' => $this->bsf->isNullCheck( $postData[ 'shareTypeId_' . $i ], 'number' ), 'Percentage' => $this->bsf->isNullCheck( $postData[ 'sharePercentage_' . $i ], 'number' ),
                                'Amount' => $this->bsf->isNullCheck( $postData[ 'shareAmount_' . $i ], 'number' ) ) )
                                ->where(array('FinalizationOwnerId'=>$finalizationOwnerId));
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }

                        // delete payment details
                        $deleteids = trim($postData[ 'paymentdeleteids_' . $i ], ",");
                        if($deleteids !== '' && $deleteids !== '0') {
                            $delete = $sql->delete();
                            $delete->from('Proj_LBFinalizationPaymentSchedule')
                                ->where("PaymentScheduleId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Payment Details
                        $paymentdetailid = $this->bsf->isNullCheck( $postData[ 'paymentdetailid_' . $i ], 'number' );
                        for ( $j = 1; $j <= $paymentdetailid; $j++ ) {
                            $transid = $this->bsf->isNullCheck( $postData[ 'payment_' . $i .'_transid_' . $j ], 'number' );
                            $updaterow = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_updaterow_' . $j ], 'number' );

                            $desc = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_desc_' . $j ], 'string' );
                            $date = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_date_' . $j ], 'string' );
                            $amt = $this->bsf->isNullCheck( $postData[ 'payment_' . $i . '_amt_' . $j ], 'number' );

                            if ( $desc == "" || $date == "" || $amt == "" )
                                continue;

                            if($transid == 0 && $updaterow == 0) {
                                $insert = $sql->insert();
                                $insert->into( 'Proj_LBFinalizationPaymentSchedule' );
                                $insert->Values( array( 'FinalizationOwnerId' => $finalizationOwnerId, 'Date' => date( 'Y-m-d', strtotime( $date ) ), 'Description' => $desc, 'Amount' => $amt ) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            } else if ($transid != 0 && $updaterow == 1){
                                $update = $sql->update();
                                $update->table( 'Proj_LBFinalizationPaymentSchedule' );
                                $update->set( array( 'FinalizationOwnerId' => $finalizationOwnerId, 'Date' => date( 'Y-m-d', strtotime( $date ) ), 'Description' => $desc, 'Amount' => $amt ) )
                                    ->where("PaymentScheduleId=$transid");
                                $statement = $sql->getSqlStringForSqlObject( $update );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }
                        }
                    }
                }

                $update = $sql->update();
                $update->table('Proj_LandEnquiry')
                    ->set(array('FinalizationId' => $finalizationId))
                    ->where(array('EnquiryId' => $iEnquiryId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();
                if($postData['pageUrl'] == 'F') {
                    $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$iEnquiryId));
                } else {
                    $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                }

            } catch (PDOException $e) {
                $connection->rollback();
            }
        } else {
            $iFinalisationId =  $this->bsf->isNullCheck($this->params()->fromRoute('finalizationId'),'number');
            $iEnquiryId =  $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
            if ($iFinalisationId !=0) {
                $select = $sql->select();
                $select->from(array('a' =>'Proj_LandBankFinalization'))
                    ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select:: JOIN_LEFT)
                    ->where("a.FinalizationId=$iFinalisationId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $finalization = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($finalization)) {
                    $this->_view->finalization = $finalization;
                    $iEnquiryId= $this->bsf->isNullCheck($finalization['EnquiryId'],'number');

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_LBFinalizationOwnerDetail'))
                        ->join(array('b' => 'Proj_LandOwnerDetail'), 'a.OwnerId=b.OwnerId', array('OwnerName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_JVShareTypeMaster'), 'a.ShareTypeId=c.ShareTypeId', array('ShareTypeName'), $select:: JOIN_LEFT)
                        ->columns(array('FinalizationOwnerId', 'OwnerId', 'ShareTypeId', 'Percentage', 'Amount'))
                        ->where("a.FinalizationId= $iFinalisationId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ownerdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_LBFinalizationPaymentSchedule'))
                        ->join(array('b' => 'Proj_LBFinalizationOwnerDetail'), 'a.FinalizationOwnerId=b.FinalizationOwnerId', array(), $select:: JOIN_INNER)
                        ->columns(array('PaymentScheduleId','FinalizationOwnerId','Description','Date' => new Expression("FORMAT(a.Date, 'dd-MM-yyyy')"),'Amount'))
                        ->where("b.FinalizationId= $iFinalisationId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ownerpaymentdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            }

            $this->_view->enquiryId= $iEnquiryId;
            $this->_view->finalisationId= $iFinalisationId;

            $aVNo = CommonHelper::getVoucherNo(108, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            // Property Names
            $propertyname="";
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('PropertyName'))
                ->where("EnquiryId=$iEnquiryId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($propertynames)) $propertyname = $propertynames['PropertyName'];

            $this->_view->propertyname = $propertyname;
            // City List
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId', 'CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->citylists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Joint Ventures Share Types
            $select = $sql->select();
            $select->from('Proj_JVShareTypeMaster')
                ->columns(array('data' => 'ShareTypeId', 'value' => 'ShareTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->jvsharetypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Offer Type
            $select = $sql->select();
            $select->from('Proj_SaleTypeMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->offertypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $this->_view->page = (isset($page) && $page != '') ? $page : '';
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getfinalizationownerdetailsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            if ($request->isPost()) {
                try {
                    $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                    $select = $sql->select();
                    $select->from(array('a'=>'Proj_initialownerdetail'))
                        ->columns(array('OwnerName', 'OwnerId'))
                        ->join(array('b' => 'Proj_landinitialfeasibility'), 'a.FeasibilityId=b.FeasibilityId', array(), $select:: JOIN_LEFT)
                        ->where("b.EnquiryId=$EnquiryId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $owners = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $response = $this->getResponse();
                    if (!count($owners)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $response->setContent(json_encode($owners));
                    }
                } catch (PDOException $e) {
                    $response->setContent("Bad Request");
                    $response->setStatusCode("400");
                }
                return $response;
            }
        }
    }

    public function projectconceptionAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project Conception");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);


        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                        ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.PropSaleableAreaUnitId=d.UnitId', array('PropSaleableAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('OptionName', 'NoOfBlocks', 'NoOfFloors', 'NoOfFlats', 'TotalArea', 'PropSaleableArea', 'FeasibilityId'))
                        ->where("a.EnquiryId=$EnquiryId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $feasibilities = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $response = $this->getResponse();
                    if (!count($feasibilities)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $html = '';
                        foreach ($feasibilities as $feasibility) {
                            $html .= ' <div class="col-lg-4 col-lg-offset-0 col-md-4 col-md-offset-0 col-sm-6 col-sm-offset-0 col-xs-12 col-xs-offset-0">
                                <div class="site-img">
                                    <div class="pro-land effects-pro">
                                      <div class="img" style="background-image:url(\'/bsf_v1.0/public/images/flat-img2.jpg\'),url(\'/bsf_v1.0/public/images/no-image.png\');"></div>
                                      <div class="read-pl">
                                        <a href="'. $viewRenderer->basePath() . '/project/landbank/projectconceptiondetail/' . $EnquiryId . '/' . $feasibility['FeasibilityId'] . '" class="info" data-toggle="tooltip" data-placement="top" data-original-title="Read More"></a>
                                      </div>
                                    </div>
                                    <span class="info-det">
                                    <a href="'. $viewRenderer->basePath() . '/project/landbank/projectconceptiondetail/' . $EnquiryId . '/' . $feasibility['FeasibilityId'] . '">' . $feasibility['OptionName'] . '</a>
                                    <ul>
                                      <li>
                                        <label>No. of Blocks</label>
                                        <span>' . $feasibility['NoOfBlocks'] . '</span>
                                      </li>
                                      <li>
                                        <label>No. of Floors</label>
                                        <span>' . $feasibility['NoOfFloors'] . '</span>
                                      </li>
                                      <li>
                                        <label>No. of Flats</label>
                                        <span>' . $feasibility['NoOfFlats'] . '</span>
                                      </li>
                                      <li>
                                        <label>Total Area</label>
                                        <span>' . $feasibility['TotalArea'] . ' ' . $feasibility['TotalAreaUnitName'] . '</span>
                                      </li>
                                      <li>
                                        <label>Saleable Area</label>
                                        <span>' . $feasibility['PropSaleableArea'] . ' ' . $feasibility['PropSaleableAreaUnitName'] . '</span>
                                      </li>
                                    </ul>
                                    </span>
                                </div>
                            </div>';
                        }



                        $response->setContent($html);
                    }

                    return $response;
                } catch (PDOException $e) {

                }
            }
        } else {
            // Property Names
            $iEnquiryId =  $this->bsf->isNullCheck($this->params()->fromRoute('EnquiryId'),'number');
            $this->_view->enquiryId= $iEnquiryId;

            $sPropertyName="";
            if ($iEnquiryId !=0) {
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->columns(array('PropertyName'))
                    ->where("EnquiryId=$iEnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $penquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($penquiry)) {
                    $sPropertyName = $this->bsf->isNullCheck($penquiry['PropertyName'],'string');
                }
            }

            $this->_view->landname= $sPropertyName;
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getprojectconceptiondetailsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            if ($request->isPost()) {
                try {
                    $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');

                    $select = $sql->select();
                    $select->from('Proj_LandOwnerDetail')
                        ->columns(array('OwnerName', 'OwnerId'))
                        ->where("EnquiryId=$EnquiryId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $owners = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $response = $this->getResponse();
                    if (!count($owners)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $html = '';
                        $i = 0;
                        foreach ($owners as $owner) {
                            $i++;
                            $html .= '<tr>
                            <td width="20%">
                                <input class="parent_text" type="text" name="ownername_' . $i . '" id="ownername_' . $i . '" maxlength="155" value="' . $owner['OwnerName'] . '" readonly/>
                                <input type="hidden" name="ownerId_' . $i . '" id="ownerId_' . $i . '" value="' . $owner['OwnerId'] . '"/>
                            </td>
                            <td width="20%">
                                <input class="parent_text" type="text" name="shareType_' . $i . '" id="shareType_' . $i . '" maxlength="155"/>
                                <input type="hidden" name="shareTypeId_' . $i . '" id="shareTypeId_' . $i . '" />
                            </td>
                            <td width="10%">
                                <input class="parent_text" type="text" name="sharePercentage_' . $i . '" id="sharePercentage_' . $i . '" onblur="return FormatNum(this, 2)" onkeypress="return isDecimal(event,this)" maxlength="18"/>
                            </td>
                            <td width="10%">
                                <input class="parent_text" type="text" name="shareAmount_' . $i . '" id="shareAmount_' . $i . '" onblur="return FormatNum(this, 2)" onkeypress="return isDecimal(event,this)" maxlength="18"/>
                            </td>
                            <td width="3%" align="center">
                                <ul class="action_btns">
                                    <li><a href="#" class="mainTr"> <i class="fa fa-chevron-circle-down" data-toggle="tooltip" data-placement="top" data-original-title="Expand"></i></a></li>
                                </ul>
                            </td>
                        </tr>
                        <!--expand table-->
                        <tr style="display:none;" class="subTr">
                            <td colspan="9" style="padding:0px !important; ">
                                <div class="subDiv" style="display:none;">
                                    <div class="col-lg-12">
                                        <div class="table-responsive topsp">
                                            <table class="table tableWithFloatingHeader" style=" margin-bottom:0px;">
                                                <thead>
                                                    <tr>
                                                        <th>Description</th>
                                                        <th>Date</th>
                                                        <th>Amount</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <input type="hidden" name="payment_' . $i . '_transid_1" id="payment_' . $i . '_transid_1" value="0">
                                                        <input type="hidden" name="payment_' . $i . '_updaterow_1" id="payment_' . $i . '_updaterow_1" value="0">
                                                        <td width="15%"><input type="text" class="parent_text" name="payment_' . $i . '_desc_1" id="payment_' . $i . '_desc_1" maxlength="155" onchange="return validatePaymentTr(this)"></td>
                                                        <td width="5%"><input type="text" class="parent_text date_picker" placeholder="DD-MM-YYYY" name="payment_' . $i . '_date_1" id="payment_' . $i . '_date_1" onchange="return validatePaymentTr(this)"></td>
                                                        <td width="5%"><input class="parent_text" type="text" name="payment_' . $i . '_amt_1" id="payment_' . $i . '_amt_1" onblur="return FormatNum(this, 2)" onkeypress="return isDecimal(event,this)" maxlength="18" onchange="return validatePaymentTr(this)"/></td>
                                                        <td width="3%" align="center">
                                                            <ul class="action_btns">
                                                                <li>
                                                                    <a href="#" id="payment_' . $i . '_delete_1" onclick="deleteSubTr(this, event);" class="subTrDelete" style="display: none;"><i class="fa fa-trash" data-toggle="tooltip" data-placement="top" data-original-title="Delete" ></i></a>
                                                                </li>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <input type="hidden" name="paymentdetailid_' . $i . '" id="paymentdetailid_' . $i . '" value="1"/>
                                            <input type="hidden" name="paymentdeleteids_' . $i . '" id="paymentdeleteids_' . $i . '" value="0"/>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>';
                        }
                        $html .= '<input type="hidden" name="scheduleRowId" id="scheduleRowId" value="' . $i . '"/>';
                        $response->setContent($html);
                    }
                    return $response;
                } catch (PDOException $e) {

                }
            }
        }
    }

    public function projectconceptiondetailAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project Conception");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $nEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('EnquiryId'), 'number');
        $page = $this->params()->fromRoute('page');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $files = $request->getFiles();
            //echo '<pre>'; print_r($postData); die;

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $iConceptionId = $this->bsf->isNullCheck($postData['conceptionId'], 'number');
                $sql = new Sql($dbAdapter);

                $delFilesUrl = array();

                $iEnquiryId =$this->bsf->isNullCheck($postData['enquiryId'], 'number');
                $iFeasibilityId =$this->bsf->isNullCheck($postData['feasibilityId'], 'number');
                $sRefNo =$this->bsf->isNullCheck($postData['RefNo'], 'string');
                $sRefDate = $this->bsf->isNullCheck($postData['RefDate'], 'string');
                $OptionName = $this->bsf->isNullCheck($postData['OptionName'], 'string');
                $iProjectTypeId =$this->bsf->isNullCheck($postData['ProjectTypeId'], 'number');
                $iProjectTypeId =$this->bsf->isNullCheck($postData['ProjectTypeId'], 'number');
                $iNoOfBlocks =$this->bsf->isNullCheck($postData['NoOfBlocks'], 'number');
                $iNoOfFloors =$this->bsf->isNullCheck($postData['NoOfFloors'], 'number');
                $iNoOfFlats =$this->bsf->isNullCheck($postData['NoOfFlats'], 'number');
                $dCommonArea =$this->bsf->isNullCheck($postData['CommonArea'], 'number');
                $iCommonAreaUnitId =$this->bsf->isNullCheck($postData['CommonAreaUnitId'], 'number');
                $dPropSaleableArea =$this->bsf->isNullCheck($postData['PropSaleableArea'], 'number');
                $iPropSaleableAreaUnitId =$this->bsf->isNullCheck($postData['PropSaleableAreaUnitId'], 'number');
                if ($iConceptionId==0) {

                    $sVno= $sRefNo;
                    $aVNo = CommonHelper::getVoucherNo(109, date('Y-m-d', strtotime($postData['RefDate'])), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == true) $sVno = $aVNo["voucherNo"];

                    $insert = $sql->insert();
                    $insert->into('Proj_LandConceptionRegister');
                    $insert->Values(array('EnquiryId' => $iEnquiryId, 'FeasibilityId' => $iFeasibilityId
                    , 'RefNo' => $sVno
                    , 'OptionName' => $OptionName,
                        'RefDate' => date('Y-m-d', strtotime($sRefDate))
                    , 'ProjectTypeId' => $iProjectTypeId, 'NoOfBlocks' => $iNoOfBlocks,'NoOfFloors' => $iNoOfFloors,'NoOfFlats' => $iNoOfFlats
                    , 'SaleableArea' => $dPropSaleableArea, 'SaleableAreaUnitId' => $iPropSaleableAreaUnitId,'CommonArea'=>$dCommonArea,'CommonAreaUnitId'=>$iCommonAreaUnitId));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $iConceptionId = $dbAdapter->getDriver()->getLastGeneratedValue();

                } else {

                    $update = $sql->update();
                    $update->table('Proj_LandConceptionRegister');
                    $update->set(array('EnquiryId' => $iEnquiryId, 'FeasibilityId' => $iFeasibilityId
                    , 'RefNo' => $sRefNo, 'RefDate' => date('Y-m-d', strtotime($sRefDate))
                    , 'ProjectTypeId' => $iProjectTypeId, 'NoOfBlocks' => $iNoOfBlocks,'NoOfFloors' => $iNoOfFloors,'NoOfFlats' => $iNoOfFlats
                    , 'SaleableArea' => $dPropSaleableArea
                    , 'OptionName' => $OptionName,
                        'SaleableAreaUnitId' => $iPropSaleableAreaUnitId,'CommonArea'=>$dCommonArea,'CommonAreaUnitId'=>$iCommonAreaUnitId));
                    $update->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionFeatureTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionAmenityTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionConsultantTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from( array('a' => 'Proj_LandConceptionDrawingTrans'))
                        ->columns(array('URL'))
                        ->where("a.ConceptionId=$iConceptionId");
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $drawingdel = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    foreach($drawingdel as $delurl) {
                        $url = $delurl['URL'];
                        if($url != '' || !is_null($url)) {
                            $delFilesUrl[] = 'public' . $url;
                        }
                    }

                    $select = $sql->select();
                    $select->from( array('a' => 'Proj_LandConceptionDocumentTrans'))
                        ->columns(array('URL'))
                        ->where("a.ConceptionId=$iConceptionId");
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $documentdel = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    foreach($documentdel as $delurl) {
                        $url = $delurl['URL'];
                        if($url != '' || !is_null($url)) {
                            $delFilesUrl[] = 'public' . $url;
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionDrawingTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionDocumentTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    $delete = $sql->delete();
//                    $delete->from('Proj_LandConceptionGeneral')
//                        ->where(array('ConceptionId'=>$iConceptionId));
//                    $statement = $sql->getSqlStringForSqlObject($delete);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                    $delete = $sql->delete();
//                    $delete->from('Proj_LandConceptionApprovalTrans')
//                        ->where(array('ConceptionId'=>$iConceptionId));
//                    $statement = $sql->getSqlStringForSqlObject($delete);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionIncomeTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionExpenseTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_LandConceptionScheduleTrans')
                        ->where(array('ConceptionId'=>$iConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $srowid = $this->bsf->isNullCheck($postData['splfeaturerowid'],'number');
                for ($i = 1; $i <= $srowid; $i++) {
                    $sName = $this->bsf->isNullCheck($postData['splfeature_' . $i],'string');
                    if ($sName != "") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionFeatureTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'FeatureName' => $sName));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $arowid = $this->bsf->isNullCheck($postData['amenitiesrowid'],'number');
                for ($i = 1; $i <= $arowid; $i++) {
                    $sName = $this->bsf->isNullCheck($postData['amenities_' . $i],'string');
                    if ($sName !="") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionAmenityTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'AmenityName' => $sName));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $crowid = $this->bsf->isNullCheck($postData['consultantrowid'],'number');
                for ($i = 1; $i <= $crowid; $i++) {

                    $sName = $this->bsf->isNullCheck($postData['consultantname_' . $i],'string');
                    $sType = $this->bsf->isNullCheck($postData['consultanttype_' . $i],'string');
                    $dFee = $this->bsf->isNullCheck($postData['fees_' . $i],'number');
                    $dFeeAmount = $this->bsf->isNullCheck($postData['feesamount_' . $i],'number');
                    if ($sName !="") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionConsultantTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'Name' => $sName,'Type'=>$sType,'Fee'=>$dFee,'FeeAmount'=>$dFeeAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $drrowid = $this->bsf->isNullCheck($postData['drawingrowid'],'number');
                for ($i = 1; $i <= $drrowid; $i++) {

                    $Type = $this->bsf->isNullCheck($postData['drawType_' . $i], 'string');
                    $Description = $this->bsf->isNullCheck($postData['drawDesc_' . $i], 'string');
                    $url = $this->bsf->isNullCheck($postData['drawFile_' . $i], 'string');

                    if ($Type == '' || $Description == '')
                        continue;

                    if($url == '') {
                        if($files['drawFile_' . $i]['name']){

                            $dir = 'public/uploads/project/conception/drawing/'.$iConceptionId.'/';
                            $filename = $this->bsf->uploadFile($dir, $files['drawFile_' . $i]);

                            if($filename) {
                                // update valid files only
                                $url = '/uploads/project/conception/drawing/'.$iConceptionId.'/' . $filename;
                            }
                        }
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_LandConceptionDrawingTrans');
                    $insert->Values(array('ConceptionId' => $iConceptionId, 'Title' => $Type, 'Description' => $Description, 'URL' => $url));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $drrowid = $this->bsf->isNullCheck($postData['documentrowid'],'number');
                for ($i = 1; $i <= $drrowid; $i++) {

                    $Type = $this->bsf->isNullCheck($postData['docType_' . $i], 'string');
                    $Description = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');
                    $url = $this->bsf->isNullCheck($postData['docFile_' . $i], 'string');

                    if ($Type == '' || $Description == '')
                        continue;
                    if($url == '') {
                        if ($files['drawFile_' . $i]['name']) {

                            $dir = 'public/uploads/project/conception/document/' . $iConceptionId . '/';
                            $filename = $this->bsf->uploadFile($dir, $files['drawFile_' . $i]);

                            if ($filename) {
                                // update valid files only
                                $url = '/uploads/project/conception/document/' . $iConceptionId . '/' . $filename;
                            }
                        }
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_LandConceptionDocumentTrans');
                    $insert->Values(array('ConceptionId' => $iConceptionId, 'Type' => $Type, 'Description' => $Description, 'URL' => $url));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

//                $sLandMark = $this->bsf->isNullCheck($postData['LandMark'],'string');
//                $sProjectAddress = $this->bsf->isNullCheck($postData['ProjectAddress'],'string');
//                $sSoilType = $this->bsf->isNullCheck($postData['SoilType'],'string');
//                $dFSI = $this->bsf->isNullCheck($postData['FSI'],'number');
//                $dPremiumFSI = $this->bsf->isNullCheck($postData['PremiumFSI'],'number');
//                $dGuideline = $this->bsf->isNullCheck($postData['Guideline'],'number');
//                $iFloors = $this->bsf->isNullCheck($postData['Floors'],'number');
//                $dExpandableFSI = $this->bsf->isNullCheck($postData['ExpandableFSI'],'number');
//                $sGroundWater= $this->bsf->isNullCheck($postData['GroundWater'],'string');
//                $iGovtWaterSupply = isset($postData['GovtWaterSupply']) ? 1 : 0;
//                $iElectricity = isset($postData['Electricity']) ? 1 : 0;
//
//                $insert = $sql->insert();
//                $insert->into('Proj_LandConceptionGeneral');
//                $insert->Values(array('ConceptionId' => $iConceptionId, 'SoilType' => $sSoilType,'LandMark' => $sLandMark,'ProjectAddress' => $sProjectAddress,
//                        'GroundWaterLevel'=>$sGroundWater,'FSI' => $dFSI, 'PremiumFSI' => $dPremiumFSI,
//                        'Guideline'=>$dGuideline,'Floors'=>$iFloors,'ExpandableArea'=>$dExpandableFSI,
//                        'GovtWaterSupply'=>$iGovtWaterSupply,'Electricity'=>$iElectricity));
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//                $arowid = $this->bsf->isNullCheck($postData['approvalrowid'],'number');
//                for ($i = 1; $i <= $arowid; $i++) {
//                    $sName = $this->bsf->isNullCheck($postData['approvalauthority_' . $i],'string');
//                    $sDate = $this->bsf->isNullCheck($postData['approvaldate_' . $i],'string');
//                    $sNo = $this->bsf->isNullCheck($postData['approvalno_' . $i],'string');
//
//                    if ($sName !="") {
//                        $insert = $sql->insert();
//                        $insert->into('Proj_LandConceptionApprovalTrans');
//                        $insert->Values(array('ConceptionId' => $iConceptionId, 'AuthorityName' => $sName,'ApproveDate'=>date('Y-m-d', strtotime($sDate)),'ApproveNo'=>$sNo));
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    }
//                }

                $rowid = $this->bsf->isNullCheck($postData['incomerowid'],'number');
                for ($i = 1; $i <= $rowid; $i++) {
                    $sName = $this->bsf->isNullCheck($postData['incomeparticular_' . $i],'string');
                    $dAmount = $this->bsf->isNullCheck($postData['incomeamount_' . $i],'number');
                    if ($sName !="") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionIncomeTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'Particular' => $sName,'Amount'=>$dAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $rowid = $this->bsf->isNullCheck($postData['exrowid'],'number');
                for ($i = 1; $i <= $rowid; $i++) {
                    $sName = $this->bsf->isNullCheck($postData['exparticular_' . $i],'string');
                    $dAmount = $this->bsf->isNullCheck($postData['examount_' . $i],'number');
                    if ($sName !="") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionExpenseTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'Particular' => $sName,'Amount'=>$dAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $rowid = $this->bsf->isNullCheck($postData['constructionrowid'],'number');
                for ($i = 1; $i <= $rowid; $i++) {
                    $sName = $this->bsf->isNullCheck($postData['constructionyear_' . $i],'string');
                    $dAmount = $this->bsf->isNullCheck($postData['constructionamount_' . $i],'number');
                    if ($sName !="") {
                        $insert = $sql->insert();
                        $insert->into('Proj_LandConceptionScheduleTrans');
                        $insert->Values(array('ConceptionId' => $iConceptionId, 'ShYear' => $sName,'Amount'=>$dAmount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $update = $sql->update();
                $update->table('Proj_LandEnquiry')
                    ->set(array('ConceptionDone' => 1))
                    ->where(array('EnquiryId' => $iEnquiryId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();
                if($postData['pageUrl'] == 'F') {
                    $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup', 'enquiryId'=>$iEnquiryId));
                } else {
                    $this->redirect()->toRoute('project/register', array('controller' => 'landbank', 'action' => 'register'));
                }
                if(!empty($delFilesUrl)) {
                    foreach($delFilesUrl as $url) {
                        unlink($url);
                    }
                }


            } catch (PDOException $e) {
                $connection->rollback();

            }


        } else {
            $EnquiryId = $this->params()->fromRoute('EnquiryId');
            $FeasibilityId = $this->params()->fromRoute('FeasibilityId');

            $mode = 'Add';
            $iConceptionId = 0;
//            $select = $sql->select();
//            $select->from('Proj_LandConceptionRegister')
//                ->columns(array('ConceptionId'))
//                ->where("FeasibilityId=$EnquiryId");
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $property = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from('Proj_LandConceptionRegister')
                ->columns(array('ConceptionId'))
                ->where("FeasibilityId=$FeasibilityId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $conception = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if ($conception != false) {
                $mode = 'Edit';
                $iConceptionId = $conception['ConceptionId'];
            }
            $aVNo = CommonHelper::getVoucherNo(109, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            if ($mode == 'Add') {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                    ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'))
                    ->join(array('c' => 'Proj_LandEnquiry'), 'a.EnquiryId=c.EnquiryId', array('PropertyName'))
                    ->where("a.FeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->feasibility = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandBusinessConsultant'))
                    ->columns(array('Name','Type','Fee','FeeAmount'))
                    ->where("a.FeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->consultantlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandBusinessLayoutDrawing'))
                    ->columns(array('Title','Description','URL'))
                    ->where("a.FeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->drawinglist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandBusinessDocument'))
                    ->columns(array('Type','Description','URL'))
                    ->where("a.FeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->documentlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandInitialFeasibility'))
                    ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('ProjectTypeName'=>new Expression("PropertyAddress"),'LandMark'))
                    ->columns(array('SoilType','GroundWaterLevel','GovtWaterSupply','Electricity','FSI','PremiumFSI','Guideline','Floors','ExpandableArea'))
                    ->where("a.EnquiryId=$EnquiryId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->generallist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandFinancialIncomeTrans'))
                    ->join(array('b' => 'Proj_LandFianancialFeasibility'), 'a.FeasibilityId=b.FeasibilityId', array(), $select::JOIN_INNER)
                    ->columns(array('Particular','Amount'))
                    ->where("b.BusinessFeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->incomelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandFinancialExpenseTrans'))
                    ->join(array('b' => 'Proj_LandFianancialFeasibility'), 'a.FeasibilityId=b.FeasibilityId', array(), $select::JOIN_INNER)
                    ->columns(array('Particular','Amount'))
                    ->where("b.BusinessFeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->expenselist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandFinancialScheduleTrans'))
                    ->join(array('b' => 'Proj_LandFianancialFeasibility'), 'a.FeasibilityId=b.FeasibilityId', array(), $select::JOIN_INNER)
                    ->columns(array('ShYear','Amount'))
                    ->where("b.BusinessFeasibilityId=$FeasibilityId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->shedulelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            } else {
                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionRegister'))
                    ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_LandBusinessFeasibility'), 'a.FeasibilityId=c.FeasibilityId', array('OptionName','PresentedBy'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_LandEnquiry'), 'a.EnquiryId=d.EnquiryId', array('PropertyName'), $select:: JOIN_LEFT)
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->feasibility = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionConsultantTrans'))
                    ->columns(array('Name','Type','Fee','FeeAmount'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->consultantlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionDrawingTrans'))
                    ->columns(array('Title','Description','URL'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->drawinglist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionDocumentTrans'))
                    ->columns(array('Type','Description','URL'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->documentlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionGeneral'))
                    ->columns(array('SoilType','ProjectAddress','LandMark','GroundWaterLevel','GovtWaterSupply','Electricity','FSI','PremiumFSI','Guideline','Floors','ExpandableArea'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->generallist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionIncomeTrans'))
                    ->columns(array('Particular','Amount'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->incomelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionExpenseTrans'))
                    ->columns(array('Particular','Amount'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->expenselist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( array('a' => 'Proj_LandConceptionScheduleTrans'))
                    ->columns(array('ShYear','Amount'))
                    ->where("a.ConceptionId=$iConceptionId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->shedulelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $select = $sql->select();
            $select->from( array('a' => 'Proj_LandBusinessFeasibilityFiles'))
                ->columns(array('URL'))
                ->where("a.FeasibilityId=$FeasibilityId and FileType='image'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->imagelist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql->select();
            $select->from( array('a' => 'Proj_LandConceptionFeatureTrans'))
                ->columns(array('FeatureName'))
                ->where("a.ConceptionId=$iConceptionId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->featurelist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql->select();
            $select->from( array('a' => 'Proj_LandConceptionAmenityTrans'))
                ->columns(array('AmenityName'))
                ->where("a.ConceptionId=$iConceptionId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->amenitylist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql->select();
            $select->from( array('a' => 'Proj_LandConceptionApprovalTrans'))
                ->columns(array('AuthorityName','ApproveDate','ApproveNo'))
                ->where("a.ConceptionId=$iConceptionId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->approvallist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $this->_view->enquiryId= $EnquiryId;
            $this->_view->feasibilityId= $FeasibilityId;
            $this->_view->conceptionId= $iConceptionId;
            $this->_view->mode = $mode;

            // Project Type
            $select = $sql->select();
            $select->from('Proj_ProjectTypeMaster')
                ->columns(array('data' => 'ProjectTypeId', 'value' => 'ProjectTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Area Unit List
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->where('TypeId=2');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // particulars List
            $select = $sql->select();
            $select->from('Proj_ExpectedIncomeMaster')
                ->columns(array('value' => 'Particular'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_expectedincomeParticulars = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_ProposalCostMaster')
                ->columns(array('value' => 'Particular'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_proposalCostParticulars = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $this->_view->page = (isset($page) && $page != '') ? $page : '';
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function followupAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Follow Up");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $FollowUpId = $this->bsf->isNullCheck($this->params()->fromRoute('followupid'), 'number');
        $nEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
        $request = $this->getRequest();

        $nEnquiryName = "";

        if($FollowUpId != 0 ) {
            //Load for edit page

            $select = $sql->select();
            $select->from(array('a' =>'Proj_LandBankFollow'))
                ->columns(array('NatureOfCall' => new Expression("b.CallNatureName")))
                ->join(array('b' => 'Proj_CallNatureMaster'), 'a.CallNatureId=b.CallNatureId', array(), $select::JOIN_INNER)
                ->where(array('FollowUpId'=>$FollowUpId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Call = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_LandEnquiry'))
                ->join(array('b' => 'Proj_LandBankFollow'), 'a.EnquiryId=b.EnquiryId', array(), $select::JOIN_INNER)
                ->where(array('FollowUpId'=>$FollowUpId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Property = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_LandBankFollow'))
                ->join(array('b' => 'Vendor_Master'), 'a.BrokerId=b.VendorId', array('VendorId','VendorName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_LandEnquiry'), 'a.EnquiryId=c.EnquiryId', array('PropertyName'), $select::JOIN_LEFT)
                ->join(array('d' => 'Proj_CallNatureMaster'), 'a.CallNatureId=d.CallNatureId', array('CallNatureName'), $select::JOIN_LEFT)
                ->where(array('a.FollowUpId'=>$FollowUpId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->LandBankFollow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_LandBankFollow'))
                ->columns(array('CheckListType' => new Expression("c.TypeId"),'CheckListId' => new Expression("c.CheckListId")
                ,'UserId' => new Expression("b.AssignUserId"),'ChecklistDate' => new Expression("b.TargetDate"),'CheckListName' => new Expression("c.CheckListName")))
                ->join(array('b' => 'Proj_LandBankChecklistTrans'), 'a.FollowUpId=b.FollowupId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_CheckListMaster'), 'b.ChecklistId=c.CheckListId', array(), $select::JOIN_INNER)
                ->where(array('a.FollowUpId'=>$FollowUpId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Assignlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_LandBankFollow'))
                ->columns(array('CheckListType' => new Expression("d.TypeId"),'CheckListId' => new Expression("c.CheckListId")
                ,'UserId' => new Expression("b.UserId"),'Status' => new Expression("b.Status"),'CheckListName' => new Expression("d.CheckListName")))
                ->join(array('b' => 'Proj_LandBankChecklistHistory'), 'a.FollowUpId=b.FollowupId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_LandBankChecklistTrans'), 'b.ChecklistTransId=c.ChecklistTransId', array(), $select::JOIN_INNER)
                ->join(array('d' => 'Proj_CheckListMaster'), 'c.ChecklistId=d.ChecklistId', array(), $select::JOIN_INNER)
                ->where(array('a.FollowUpId'=>$FollowUpId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Actionlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }
        if ($request->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $type = $this->bsf->isNullCheck($request->getPost('type'), 'string');
                    if ($type == 'Enquiry') {
                        $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');
                        $sShow = $this->bsf->isNullCheck($request->getPost('show'), 'string');
                        $select = $sql->select();

                        $select->from(array('a' => 'Proj_LandBankFollow'))
                            ->join(array('b' => 'Proj_LandCallTypeMaster'), 'a.CallTypeId=b.CallTypeId', array('CallTypeName'), $select::JOIN_LEFT)
                            ->join(array('c' => 'WF_Users'), 'a.CreateUserId=c.UserId', array('UserName','UserLogo'), $select::JOIN_LEFT)
                            ->columns(array('Remarks','CreatedDate'))
                            ->where("a.EnquiryId=$EnquiryId")
                            ->order('a.CreatedDate DESC');
                        if ($sShow=='limit') $select->limit(6);

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $follows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $iRowcount=0;
                        $response = $this->getResponse();
                        if (!count($follows)) {
                            $response->setContent('No Data');
                            $response->setStatusCode('204');
                        } else {
                            $html = '<ul>';
                            foreach ($follows as $follow) {
                                $iRowcount = $iRowcount+1;
                                $html .= '<li>
                                    <span class="fu-imag"><img src="' . $viewRenderer->basePath() . '/' . $follow['UserLogo'] . '" width="40" height="40"/></span>
                                    <div class="doc-det">
                                        <a href="#" class="hu-name">' . $follow['CallTypeName'] . '<i class="fa fa-external-link"></i></a>
                                        <p>' . $follow['UserName'] . '</p>
                                        <strong><i class="fa fa-calendar"></i>' . date("d/m/Y", strtotime($follow['CreatedDate'])) . ' &nbsp;&nbsp; <i class="fa fa-clock-o"></i> ' . date("h:i A", strtotime($follow['CreatedDate'])) . '</strong>
                                    </div>
                                    <div class="fu-con">
                                        <p class="top-arr-aff">' . $follow['Remarks'] . '</p>
                                    </div>
                                </li>';
                                if ($sShow=='limit' && $iRowcount ==5) break;
                            }
                            $html .= '</ul>';
                            if ($sShow=='limit' && count($follows) >5)  $html .=  '<a href="javascript:showallHistory();" class="view-bt">View more&nbsp;<i class="fa fa-eye"></i></a> </div>';
                            $response->setContent($html);
                        }

                        return $response;
                    }  else if($type == 'ChecklistAssign' ) {
                        $ChecklistType = $this->bsf->isNullCheck($request->getPost('ChecklistAssign'), 'string');

                        //Tender Assign Checklist from Proj_LandBankChecklistTrans
                        $subquery = $sql->select();
                        $subquery -> from("Proj_LandBankChecklistTrans")
                            ->columns(array('ChecklistId'));

                        $select = $sql->select();
                        $select->from('Proj_CheckListMaster')
                            ->where(array('TypeId'=> $ChecklistType));
                        $select->where->expression('CheckListId NOT IN ?', array($subquery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $AssignList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $AssignList = json_encode($AssignList);

                        $response = $this->getResponse();
                        $response->setContent($AssignList);
                        return $response;
                    } else if ($type == 'ChecklistAction') {
                        $ChecklistType = $this->bsf->isNullCheck($request->getPost('ChecklistAction'), 'string');


                        $subquery = $sql->select();
                        $subquery -> from("Proj_LandBankChecklistTrans")
                            ->columns(array('ChecklistId'));

                        $select = $sql->select();
                        $select->from('Proj_CheckListMaster')
                            ->where(array('TypeId'=> $ChecklistType));
                        $select->where->expression('CheckListId IN ?', array($subquery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $ActionList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $ActionList = json_encode($ActionList);

                        $response = $this->getResponse();
                        $response->setContent($ActionList);
                        return $response;
                    }  else if($type == 'Owner' ) {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');

                        $select = $sql->select();
                        $select->from('Proj_LandOwnerDetail')
                            ->columns(array('OwnerId','OwnerName'))
                            ->where(array('EnquiryId'=> $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $ownerList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $ownerList = json_encode($ownerList);

                        $response = $this->getResponse();
                        $response->setContent($ownerList);
                        return $response;
                    } else if($type == 'Broker' ) {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');

                        $select = $sql->select();
                        $select->from('Vendor_Master')
                            ->columns(array('VendorId','VendorName'))
                            ->where(array('ServiceTypeId'=> 1));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $brokerList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $brokerList = json_encode($brokerList);

                        $response = $this->getResponse();
                        $response->setContent($brokerList);
                        return $response;
                    } else if($type == 'Initial-Feasibility' ) {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');
                        $iFId=0;

                        $select = $sql->select();
                        $select->from('Proj_LandInitialFeasibility')
                            ->columns(array('FeasibilityId'))
                            ->where(array('EnquiryId' => $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $enqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($enqList)) { $iFId = $this->bsf->isNullCheck($enqList['FeasibilityId'],'number'); }
                        $response = $this->getResponse();
                        $response->setContent($iFId);
                        return $response;
                    }  else if($type == 'BusinessFeasibility') {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');
                        $iFId=0;

                        $select = $sql->select();
                        $select->from('Proj_LandBusinessFeasibility')
                            ->columns(array('FeasibilityId'))
                            ->where(array('EnquiryId' => $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $enqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($enqList)) { $iFId = $this->bsf->isNullCheck($enqList['FeasibilityId'],'number'); }
                        $response = $this->getResponse();
                        $response->setContent($iFId);
                        return $response;
                    } else if($type == 'Due-Diligence') {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');
                        $iFId=0;

                        $select = $sql->select();
                        $select->from('Proj_LandDueDiligence')
                            ->columns(array('DueDiligenceId'))
                            ->where(array('EnquiryId' => $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $enqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($enqList)) { $iFId = $this->bsf->isNullCheck($enqList['DueDiligenceId'],'number'); }
                        $response = $this->getResponse();
                        $response->setContent($iFId);
                        return $response;
                    } else if($type == 'Finalization') {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');
                        $iFId=0;

                        $select = $sql->select();
                        $select->from('Proj_LandBankFinalization')
                            ->columns(array('FinalizationId'))
                            ->where(array('EnquiryId' => $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $enqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($enqList)) { $iFId = $this->bsf->isNullCheck($enqList['FinalizationId'],'number'); }
                        $response = $this->getResponse();
                        $response->setContent($iFId);
                        return $response;
                    } else if($type == 'Project-KickOff') {
                        $iEnquiryId = $this->bsf->isNullCheck($request->getPost('data'), 'number');
                        $iFId=0;

                        $select = $sql->select();
                        $select->from('KF_KickoffRegister')
                            ->columns(array('KickoffId'))
                            ->where(array('EnquiryId' => $iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $enqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($enqList)) { $iFId = $this->bsf->isNullCheck($enqList['KickoffId'],'number'); }
                        $response = $this->getResponse();
                        $response->setContent($iFId);
                        return $response;
                    }
                } catch (PDOException $e) {
                }
            }
        } else if ($request->isPost()) {

            $postData = $request->getPost();
            $formfrom = $this->bsf->isNullCheck($postData['formfrom'], 'string');
            $nEnquiryId = $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
            $nEnquiryName = $this->bsf->isNullCheck($postData['EnquiryName'], 'string');

            if ($formfrom != "title") {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();


//                echo '<pre>'; print_r($postData); die;

                    $nextCallDate = $this->bsf->isNullCheck($postData['NextCallDate'], 'string');
                    if ($nextCallDate == "")
                        $nextCallDate = NULL;
                    else
                        $nextCallDate = date('Y-m-d', strtotime($nextCallDate));

                    $RefDate = $postData['RefDate'];
                    if ($RefDate == "")
                        $RefDate = NULL;
                    else
                        $RefDate = date('Y-m-d', strtotime($RefDate));

                    if ($FollowUpId != 0) {
                        //Update FollowUp
                        $update = $sql->update();
                        $update->table('Proj_LandBankFollow');
                        $update->set(array(
                            'EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                        , 'CallNatureId' => $this->bsf->isNullCheck($postData['NatureofCallId'], 'number')
//                    , 'EnquiryCallType' => $this->bsf->isNullCheck($postData['EnquiryCallType'], 'string')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        , 'NextCallRequired' => $this->bsf->isNullCheck($postData['NextCallRequired'], 'number')
                        , 'NextCallDate' => $nextCallDate
//                    , 'NextCallTypeId' => $this->bsf->isNullCheck($postData['NextEnquiryCallType'], 'number')
                        , 'BrokerId' => $this->bsf->isNullCheck($postData['brokerList'], 'number')
                        , 'OwnerId' => $this->bsf->isNullCheck($postData['onwerList'], 'number')
                        , 'CreateUserId' => $this->bsf->isNullCheck(0, 'number')
                        , 'CreatedDate' => date('Y-m-d H:i:s')
                        , 'RefDate' => $RefDate));
                        $update->where(array('FollowUpId' => $FollowUpId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_LandBankFollow'))
                            ->where(array('a.FollowUpId' => $FollowUpId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (count($resEnquiry) > 0) {
                            $resEnquiry = $resEnquiry['EnquiryCallType'];
                        }

                        if ($postData['checklistDate']) {
                            $checklistDate = date('Y-m-d', strtotime($postData['checklistDate']));
                        }
                        if ($resEnquiry == 8) {
                            //Update in Proj_LandBankChecklistTrans
                            //Assign - Checklist
                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistTrans');
                            $update->set(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
//                        , 'ChecklistId' => $this->bsf->isNullCheck($postData['TenderAssignChecklist'],'number')
                            , 'AssignUserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                            , 'TargetDate' => $checklistDate
                            , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                            $update->where(array('FollowUpId' => $FollowUpId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                        if ($resEnquiry == 9) {
                            //Update in Proj_LandBankChecklistHistory
                            // Checklist - Action
                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_LandBankFollow'))
                                ->columns(array('ChecklistTransId' => new Expression("b.ChecklistTransId")))
                                ->join(array('b' => 'Proj_LandBankChecklistHistory'), 'a.FollowUpId=b.FollowupId', array(), $select::JOIN_INNER)
                                ->join(array('c' => 'Proj_LandBankChecklistTrans'), 'b.ChecklistTransId=c.ChecklistTransId', array(), $select::JOIN_INNER)
                                ->join(array('d' => 'Proj_CheckListMaster'), 'c.ChecklistId=d.ChecklistId', array(), $select::JOIN_INNER)
                                ->where(array('a.FollowUpId' => $FollowUpId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resTransId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

//                        $subquery = $sql->select();
//                        $subquery -> from("Proj_LandBankChecklistTrans")
//                            ->columns(array('LandBankChecklistTransId'))
//                            ->where(array('ChecklistId' =>$postData['TenderActionChecklist']));
//                        $statement = $sql->getSqlStringForSqlObject( $subquery );
//                        $resTransId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if (count($resTransId) > 0) {
                                $resTransId = $resTransId['ChecklistTransId'];
                            }

                            $update = $sql->update();
                            $update->table('Proj_LandBankChecklistHistory');
                            $update->set(array('ChecklistTransId' => $resTransId
                            , 'UserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                            , 'Status' => $this->bsf->isNullCheck($postData['Progress'], 'string')
                            , 'RefDate' => $checklistDate
                            , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                            $update->where(array('FollowUpId' => $FollowUpId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                    } else {
                        $RefDate = $postData['RefDate'];
                        if ($RefDate == "")
                            $RefDate = NULL;
                        else
                            $RefDate = date('Y-m-d', strtotime($RefDate));

                        $insert = $sql->insert();
                        $insert->into('Proj_LandBankFollow');
                        $insert->Values(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                        , 'CallNatureId' => $this->bsf->isNullCheck($postData['NatureofCallId'], 'number')
                        , 'CallTypeId' => $this->bsf->isNullCheck($postData['EnquiryCallType'], 'number')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        , 'NextCallRequired' => $this->bsf->isNullCheck($postData['NextCallRequired'], 'number')
                        , 'NextCallDate' => $nextCallDate
//                    , 'NextCallTypeId' => $this->bsf->isNullCheck($postData['NextEnquiryCallType'], 'number')
                        , 'BrokerId' => $this->bsf->isNullCheck($postData['brokerList'], 'number')
                        , 'OwnerId' => $this->bsf->isNullCheck($postData['onwerList'], 'number')
                        , 'CreateUserId' => $this->bsf->isNullCheck(0, 'number')
                        , 'CreatedDate' => date('Y-m-d H:i:s')
                        , 'RefDate' => $RefDate
                        , 'Reason' => $this->bsf->isNullCheck($postData['Reason'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $LandFollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();


                        $checklistDate = date('Y/m/d');
                        if ($postData['checklistDate']) {
                            $checklistDate = date('Y-m-d', strtotime($postData['checklistDate']));
                        }

                        if ($postData['EnquiryCallType'] == 8) {
                            //Insert in Proj_LandBankChecklistTrans
                            //Assign - Checklist
                            $insert = $sql->insert();
                            $insert->into('Proj_LandBankChecklistTrans');
                            $insert->Values(array('EnquiryId' => $this->bsf->isNullCheck($postData['EnquiryId'], 'number')
                            , 'FollowupId' => $this->bsf->isNullCheck($LandFollowupId, 'number')
                            , 'ChecklistId' => $this->bsf->isNullCheck($postData['TenderAssignChecklist'], 'number')
                            , 'AssignUserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                            , 'TargetDate' => $checklistDate
                            , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                        if ($postData['EnquiryCallType'] == 9) {
                            //Insert in Proj_LandBankChecklistHistory
                            // Checklist - Action
                            $subquery = $sql->select();
                            $subquery->from("Proj_LandBankChecklistTrans")
                                ->columns(array('ChecklistTransId'))
                                ->where(array('ChecklistId' => $postData['TenderActionChecklist']));
                            $statement = $sql->getSqlStringForSqlObject($subquery);
                            $resList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if (count($resList) > 0) {
                                $LandBankChecklistTransId = $resList[0]['ChecklistTransId'];
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_LandBankChecklistHistory');
                            $insert->Values(array('ChecklistTransId' => $this->bsf->isNullCheck($LandBankChecklistTransId, 'number')
                            , 'FollowupId' => $this->bsf->isNullCheck($LandFollowupId, 'number')
                            , 'UserId' => $this->bsf->isNullCheck($postData['checklistUser'], 'number')
                            , 'Status' => $this->bsf->isNullCheck($postData['Progress'], 'string')
                            , 'RefDate' => $checklistDate
                            , 'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                    $connection->commit();
                    // $this->redirect()->toRoute('project/landbankfollowup', array('controller' => 'landbank', 'action' => 'followup-register'));
                } catch
                (PDOException $e) {
                    $connection->rollback();
                }
            }
        }
        // Landbank follows

        if ($FollowUpId != 0) {
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandBankFollow'))
                ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_CallNatureMaster'), 'a.CallNatureId=c.CallNatureId', array('CallNatureName'), $select::JOIN_LEFT)
                ->where("a.FollowUpId=$FollowUpId");

            $statement = $sql->getSqlStringForSqlObject($select);
            $arr_follow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->arr_follow = $arr_follow;

            if (!empty($arr_follow)) {
                $nEnquiryId = $arr_follow['EnquiryId'];
                $nEnquiryName = $arr_follow['PropertyName'];
            }

        }

        $select = $sql->select();
        $select->from(array('a' => 'Proj_LandEnquiry'))
            ->join(array('b' => 'Proj_SourceMaster'), 'a.SourceId=b.SourceId', array('SourceFrom' => 'SourceName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
            ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('CityName'), $select::JOIN_LEFT)
            ->columns(array('EnquiryId', 'RefNo', 'RefDate'=>new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"),'PropertyName', 'SourceName',
                'TotalArea', 'LandCost', 'PropertyLocation', 'ContactNo', 'Email','IFeasibilityId','BFeasibilityDone','FFeasibilityDone','DueDiligenceId','FinalizationId','ConceptionDone','KickoffDone','Latitude','Longitude','Radius','PropImageURL'))
            ->where("a.EnquiryId=$nEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


        $this->_view->EnquiryId = $nEnquiryId;
        $this->_view->EnquiryName = $nEnquiryName;

        $select = $sql->select();
        $select->from('Proj_LandCallTypeMaster')
            ->where('CallTypeId >7')
            ->order('CallTypeId');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->enquiryCallType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

//            $this->_view->enquiryCallType = $this->bsf->getEnquiryCallType();
        //Checklist Type
//            $select = $sql->select();
//            $select->from('Proj_CheckListMaster')
//                ->where("CheckListType<>'T'");
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->ChecklistType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //Username from WF_Users
        $select = $sql->select();
        $select->from('WF_Users');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->UserName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        // Property Names
        $select = $sql->select();
        $select->from('Proj_LandEnquiry')
            ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->propertynames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Autocomplete Reason
        $select = $sql->select();
        $select->from('Proj_LandBankFollow')
            ->columns(array(new Expression("DISTINCT Reason as value")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->Reason = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        // Nautre of calls
//            $select = $sql->select();
//            $select->from('Proj_CallNatureMaster')
//                ->columns(array('data' => 'CallNatureId', 'value' => 'CallNatureName'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->natureofcalls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from( array( 'a' => 'Proj_CheckListTypeMaster' ))
            ->columns(array('TypeId','CheckListTypeName'))
            ->where('TypeId<=4');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->checklisttype = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


        //Call Nature Master
        $select = $sql->select();
        $select->from('Proj_CallNatureMaster')
            ->columns(array('data' => 'CallNatureId', 'value' => 'CallNatureName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->CallNature = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    private function generateDueDiligenceData($details)
    {
        $html = '';
        $i = 0;
        foreach ($details as $detail) {
            $i++;
            $html .= '<tr>
                <td width="10%"><input class="parent_text" type="text" readonly value="' . $detail['SurveyNo'] . '"/></td>
                <td width="10%"><input class="parent_text" type="text" readonly value="' . $detail['PattaNo'] . '"/></td>
                <td width="12%"><input class="parent_text" type="text" readonly value="' . $detail['PattaName'] . '"/></td>
                <td width="12%"><input class="parent_text" type="text" readonly value="' . $detail['LandArea'] . ' ' . $detail['LandAreaUnitName'] . '"/></td>
                <td width="12%"><input class="parent_text" type="text" readonly value="' . $detail['OwnerName'] . '"/></td>
                <td width="3%" align="center">
                    <ul class="action_btns">
                        <li>
                            <a href="#" class="mainTr"><i class="fa fa-chevron-circle-down" data-toggle="tooltip" data-placement="top" data-original-title="Expand"></i></a>
                        </li>
                    </ul>
                </td>
            </tr>
            <!--expand table-->
            <tr style="display:none;" class="subTr">
                <td colspan="9" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                        <div class="col-lg-12">
                            <div class="table-responsive topsp">
                                <table class="table tableWithFloatingHeader" style=" margin-bottom:0px;">
                                    <thead>
                                        <tr>
                                            <th>S.No.</th>
                                            <th>Description of Documents</th>
                                            <th>Doc No. / Dt.</th>
                                            <th>Exec / Vendor</th>
                                            <th>Claimant / Owner</th>
                                            <th>S.No / Exnt.</th>
                                            <th>Parent Doc. Ref</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <input type="hidden" name="OwnerId_'.$i.'" value="' . $detail['OwnerId'] . '"/>
                                            <td width="10%"><input class="parent_text" type="text" name="slno_'.$i.'" id="slno_'.$i.'"/></td>
                                            <td width="30%"><textarea class="parent_texts" name="docdesc_'.$i.'" id="docdesc_'.$i.'"></textarea></td>
                                            <td width="10%"><input class="parent_text" type="text" name="docno_'.$i.'" id="docno_'.$i.'"/></td>
                                            <td width="10%"><input class="parent_text" type="text" name="exec_'.$i.'" id="exec_'.$i.'"/></td>
                                            <td width="10%"><input class="parent_text" type="text" name="claimant_'.$i.'" id="claimant_'.$i.'"/></td>
                                            <td width="10%"><input class="parent_text" type="text" name="exnt_'.$i.'" id="exnt_'.$i.'"/></td>
                                            <td width="10%"><input class="parent_text" type="text" name="docref_'.$i.'" id="docref_'.$i.'"/></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <!--expand table-->';
        }
        $html .= '<input type="hidden" name="ecrowid" value="' . $i . '"/>';
        return $html;
    }

    private function generateFinancialFeasibilityData($feasibilities, $EnquiryId,$pageUrl,$sbasepath)
    {
        $chartData1 = array();
        $chartData2 = array();
        $html = '';
        $i = 0;

        foreach ($feasibilities as $feasibility) {
            $FFeasibilityId = ($feasibility['FFeasibilityDone'] != 0) ? $feasibility['FFeasibilityId'] : 0;
            $html .= ' <div class="col-lg-4 col-lg-offset-0 col-md-4 col-md-offset-0 col-sm-6 col-sm-offset-0 col-xs-12 col-xs-offset-0">
                    <div class="site-img">
                        <div class="pro-land effects-pro">
                          <div class="img" style="background-image:url(\'/bsf_v1.0/public/images/flat-img2.jpg\'),url(\'/bsf_v1.0/public/images/no-image.png\');"></div>
                          <div class="read-pl">
                            <a href="'. $sbasepath . '/project/landbank/financialfeasibilitydetail/' . $EnquiryId . '/' . $feasibility['FeasibilityId'] . '/' .$FFeasibilityId .'/'.$pageUrl.'" class="info" data-toggle="tooltip" data-placement="top" data-original-title="Read More"></a>
                          </div>
                        </div>
                        <span class="info-det">
                        <a href="'. $sbasepath . '/project/landbank/financialfeasibilitydetail/' . $EnquiryId . '/' . $feasibility['FeasibilityId'] . '/' . $FFeasibilityId. '">' . $feasibility['OptionName'] . '</a>
                        <ul>
                          <li>
                            <label>Project Type</label>
                            <span>' . $feasibility['ProjectTypeName'] . '</span>
                          </li>
                          <li>
                            <label>Proposal Cost</label>
                            <span>' . $feasibility['PropTotalProjectCost'] . '</span>
                          </li>
                          <li>
                            <label>Total Area</label>
                            <span>' . $feasibility['TotalArea'] . ' ' . $feasibility['TotalAreaUnitName'] . '</span>
                          </li>
                          <li>
                            <label>Saleable Area</label>
                            <span>' . $feasibility['PropSaleableArea'] . ' ' . $feasibility['PropSaleableAreaUnitName'] . '</span>
                          </li>
                          <li>
                            <label>Return On Investment</label>
                            <span>0</span>
                          </li>
                        </ul>
                        </span>
                    </div>
                </div>';

            $chartData1[$i]['Name'] = $feasibility['OptionName'];
            $chartData1[$i]['Rate'] = $feasibility['PropTotalProjectCost'];

            $chartData2[$i]['Name'] = $feasibility['OptionName'];
            $chartData2[$i]['ROI'] = 0;
            $i++;
        }

        return json_encode(array('html' => $html,
            'chartData1' => $chartData1,
            'chartData2' => $chartData2));
    }

    private function generateOwnerDetailsHTML($EnquiryId, &$count)
    {
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        // get owner details
        $select = $sql->select();
        $select->from('Proj_LandOwnerDetail')
            ->where("EnquiryId=$EnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ownerDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $count = count($ownerDetails);
        if ($count == 0)
            return null;

        // Sale Type List
        $select = $sql->select();
        $select->from('Proj_SaleTypeMaster');
        $statement = $sql->getSqlStringForSqlObject($select);
        $saletypelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // Area Unit List
        $select = $sql->select();
        $select->from('Proj_UOM')
            ->where('TypeId=2');
        $statement = $sql->getSqlStringForSqlObject($select);
        $unittypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $html = '';
        $i = 0;
        foreach ($ownerDetails as $detail) {
            $i++;
            $html_output='';
            $sChecked="";

            if ($detail['NRI'] == "1") $sChecked = "checked";
            $html .= '<tr>
            <td width="20%"><input class="parent_text" type="text" name="surveyno_' . $i . '" id="surveyno_' . $i . '" onchange="addMainTr(this)" maxlength="50" value="' . $detail['SurveyNo'] . '"/></td>
            <td width="30%"><input class="parent_text" type="text" name="ownername_' . $i . '" id="ownername_' . $i . '" onchange="addMainTr(this)" maxlength="155" value="' . $detail['OwnerName'] . '"/></td>
            <td width="30%"><input class="parent_text" type="text" name="owneraddress_' . $i . '" id="owneraddress_' . $i . '" onchange="addMainTr(this)" maxlength="155" value="' . $detail['OwnerAddress'] . '"/></td>
            <td width="20%">
                <div class="input-grouping">
                    <input class="parent_text" type="text" onblur="return FormatNum(this, 2)" onchange="addMainTr(this)" onkeypress="return isDecimal(event,this)" maxlength="18" name="landarea_' . $i . '" id="landarea_' . $i . '" value="' . $detail['LandArea'] . '"/>
                    <div class="input-group-btn" style="top: 0;">
                        <select class="parent_text" name="landareaunitid_' . $i . '" id="landareaunitid_' . $i . '">';
            foreach ($unittypes as $list) {
                $html .= '<option value="' . $list['UnitId'] . '"' . (($list['UnitId'] == $detail['LandAreaUnitId']) ? 'selected' : '') . '>' . $list['UnitName'] . '</option>';
            }
            $html .= '</select>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </td>
            <td width="3%" align="center"><a href="#" class="mainTrDelete_' . $i . '" style="margin-right: 10px;" onclick="deleteMainTr(this, event);"><i class="fa fa-trash ctlss" data-toggle="tooltip" data-placement="top" data-original-title="Delete" ></i></a> <a href="#" class="mainTr"> <i class="fa fa-chevron-circle-down ctls" data-toggle="tooltip" data-placement="top" data-original-title="Expand" ></i></a></td>
        </tr>
        <tr style="display:none;" class="subTr">
            <td colspan="9" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                    <div class="col-lg-12">
                        <div class="table-responsive topsp">
                            <table class="table tableWithFloatingHeader" style=" margin-bottom:0px;">
                                <thead>
                                <tr>
                                    <th>Father Name</th>
                                    <th>DOB</th>
                                    <th>Contact No</th>
                                    <th>Passport No</th>
                                    <th>PAN No</th>
                                    <th>NRI </th>
                                    <th>Purchase Type</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td width="15%"><input class="parent_text" type="text" name="workinfo_' . $i . '_fathername" id="workinfo_' . $i . '_fathername" maxlength="155" value="' . $detail['FatherName'] . '"/></td>
                                    <td width="12%"><input type="text" class="parent_text date_picker"  placeholder="DD-MM-YYYY" name="workinfo_' . $i . '_dob" id="workinfo_' . $i . '_dob" value="' . date('d-m-Y', strtotime($detail['DOB'])) . '"/></td>
                                    <td width="17%"><input class="parent_text" type="text" name="workinfo_' . $i . '_contactno" id="workinfo_' . $i . '_contactno" maxlength="20" value="' . $detail['ContactNo'] . '"/></td>
                                    <td width="17%"><input class="parent_text" type="text" name="workinfo_' . $i . '_passportno" id="workinfo_' . $i . '_passportno" maxlength="50" value="' . $detail['PassportNo'] . '"/></td>
                                    <td width="16%"><input class="parent_text" type="text" name="workinfo_' . $i . '_panno" id="workinfo_' . $i . '_panno" onblur="return panjsvalidation(this.value);" maxlength="20" value="' . $detail['PanNo'] . '"/></td>
                                    <td width="4"><div class="radio_check clear">
                                            <p>
                                                <input type="checkbox"  value="1" name="workinfo_' . $i . '_NRI" id="workinfo_' . $i . '_NRI" '.$sChecked.' />
                                                <label for="workinfo_' . $i . '_NRI" class="ripple"></label>
                                            </p>
                                        </div>
                                    </td>
                                    <td width="16%">
                                        <select class="parent_text" data-size="6" title="Sale Type" name="workinfo_' . $i . '_purchasetype" id="workinfo_' . $i . '_purchasetype">';
            foreach ($saletypelists as $list) {
                $checked = ($list['SaleTypeId'] == $detail['PurchaseTypeId']) ? 'selected' : '';
                $html .= '<option value="' . $list['SaleTypeId'] . '"' . $checked . '>' . $list['SaleTypeName'] . '</option>';
            }
            $html .= '</select>
                                    </td>
                                    <td width="3%" align="center"><a href="#" class="mainTr"> <i class="fa fa-chevron-circle-down ctls" data-toggle="tooltip" data-placement="top" data-original-title="Expand" ></i></a></td>
                                </tr>
                                <tr style="display:none;" class="subTr">
                                    <td colspan="9" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                            <div class="table-responsive">
                                                <table style=" margin-bottom:0px;background:#E8FAFD!important;" class="table">
                                                    <tbody>
                                                    <tr>
                                                        <td style="padding:0px !important; border:none;" colspan="9"><div>
                                                                <div style=" margin-top:10px;" class="col-lg-12">
                                                                    <ul class="nav nav-tabs navs-tabs">
                                                                        <li class="active coownerli"><a href="#owner_' . $i . '" data-toggle="tab" aria-expanded="true">Co Owner Details</a></li>
                                                                        <li class=""><a href="#landinfo_' . $i . '" data-toggle="tab" aria-expanded="false">Land Information</a></li>
                                                                        <li class=""><a href="#prevowner_' . $i . '" data-toggle="tab" aria-expanded="false">Previous Owner Details</a></li>
                                                                    </ul>
                                                                </div>
                                                                <div class="tab-content">
                                                                    <div class="tab-pane fade active in" id="owner_' . $i . '">
                                                                        <div class="col-lg-12">
                                                                            <div class="table-responsive topsp">
                                                                                <table style=" margin-bottom:0px;" class="table tableWithFloatingHeader">
                                                                                    <thead>
                                                                                    <tr>
                                                                                        <th class="bg_clo">Co Owner Name</th>
                                                                                        <th class="bg_clo">Father Name</th>
                                                                                        <th class="bg_clo">DOB</th>
                                                                                        <th class="bg_clo">Contact No</th>
                                                                                        <th class="bg_clo">Passport No</th>
                                                                                        <th class="bg_clo">PAN No</th>
                                                                                        <th class="bg_clo">Relationship with Owner</th>
                                                                                        <th class="bg_clo">Action</th>
                                                                                    </tr>
                                                                                    </thead>
                                                                                    <tbody>';
                                                                                $select = $sql->select();
                                                                                $select->from('Proj_LandCoOwnerDetail')
                                                                                    ->where('OwnerId=' . $detail['OwnerId']);
                                                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                                                $coOwnerDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                                                $j = 1;
                                                                                foreach ($coOwnerDetails as $coOwner) {
                                                                                    $html .= '<tr>
                                                                                            <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_coownername_' . $j . '" id="workinfo_' . $i . '_co_coownername_' . $j . '" onchange="addSubTr(this)" maxlength="155" value="' . $coOwner['CoOwnerName'] . '"></td>
                                                                                            <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_fathername_' . $j . '" id="workinfo_' . $i . '_co_fathername_' . $j . '" onchange="addSubTr(this)" maxlength="155" value="' . $coOwner['FatherName'] . '"></td>
                                                                                            <td width="5%"><input type="text" class="parent_text date_picker" placeholder="DD-MM-YYYY" name="workinfo_' . $i . '_co_dob_' . $j . '" id="workinfo_' . $i . '_co_dob_' . $j . '" onchange="addSubTr(this)" value="' . date('d-m-Y', strtotime($coOwner['DOB'])) . '"></td>
                                                                                            <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_contactno_' . $j . '" id="workinfo_' . $i . '_co_contactno_' . $j . '" onchange="addSubTr(this)" maxlength="20" value="' . $coOwner['ContactNo'] . '"></td>
                                                                                            <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_passportno_' . $j . '" id="workinfo_' . $i . '_co_passportno_' . $j . '" onchange="addSubTr(this)" maxlength="50" value="' . $coOwner['PassportNo'] . '"></td>
                                                                                            <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_panno_' . $j . '" id="workinfo_' . $i . '_co_panno_' . $j . '" onchange="addSubTr(this)" onblur="return panjsvalidation(this.value);" maxlength="20" value="' . $coOwner['PanNo'] . '"></td>
                                                                                            <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_relationship_' . $j . '" id="workinfo_' . $i . '_co_relationship_' . $j . '" onchange="addSubTr(this)" maxlength="20" value="' . $coOwner['RelationshipWithOwner'] . '"></td>
                                                                                            <td width="3%" align="center"><a href="#"  id="workinfo_' . $i . '_co_delete_' . $j . '" onclick="deleteSubTr(this, event);" class="subTrDelete" style="margin-right: 10px;"><i class="fa fa-trash ctlss" data-toggle="tooltip" data-placement="top" data-original-title="Delete" ></i></a></td>
                                                                                        </tr>';
                                                                                            $j++;
                                                                                        }
                                                                                        $html .= '<tr>
                                                                                        <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_coownername_' . $j . '" id="workinfo_' . $i . '_co_coownername_' . $j . '" onchange="addSubTr(this)" maxlength="155"></td>
                                                                                        <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_fathername_' . $j . '" id="workinfo_' . $i . '_co_fathername_' . $j . '" onchange="addSubTr(this)" maxlength="155"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text date_picker" placeholder="DD-MM-YYYY" name="workinfo_' . $i . '_co_dob_' . $j . '" id="workinfo_' . $i . '_co_dob_' . $j . '"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_contactno_' . $j . '" id="workinfo_' . $i . '_co_contactno_' . $j . '" onchange="addSubTr(this)" maxlength="20"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_passportno_' . $j . '" id="workinfo_' . $i . '_co_passportno_' . $j . '" onchange="addSubTr(this)" maxlength="50"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_panno_' . $j . '" id="workinfo_' . $i . '_co_panno_' . $j . '" onchange="addSubTr(this)" onblur="return panjsvalidation(this.value);" maxlength="20"></td>
                                                                                        <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_co_relationship_' . $j . '" id="workinfo_' . $i . '_co_relationship_' . $j . '" onchange="addSubTr(this)" maxlength="20"></td>
                                                                                        <td width="3%" align="center"><a href="#"  id="workinfo_' . $i . '_co_delete_' . $j . '" onclick="deleteSubTr(this, event);" class="subTrDelete" style="margin-right: 10px;"><i class="fa fa-trash ctlss" data-toggle="tooltip" data-placement="top" data-original-title="Delete" ></i></a></td>
                                                                                    </tr>
                                                                                    </tbody>
                                                                                </table>
                                                                                <input type="hidden" name="coownerid_' . $i . '" id="coownerid_' . $i . '" value="' . $j . '">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="tab-pane fade" id="landinfo_' . $i . '">
                                                                        <div class="col-lg-12">
                                                                            <div class="table-responsive topsp">
                                                                                <table style=" margin-bottom:0px;" class="table tableWithFloatingHeader">
                                                                                    <thead>
                                                                                    <tr>
                                                                                        <th class="bg_clo">Patta No</th>
                                                                                        <th class="bg_clo">Patta Name</th>
                                                                                        <th class="bg_clo">Area</th>
                                                                                    </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                    <tr>
                                                                                        <td width="5%"><input class="parent_text" name="workinfo_' . $i . '_pattano" id="workinfo_' . $i . '_pattano" maxlength="50" value="' . $detail['PattaNo'] . '"></td>
                                                                                        <td width="10%"><input type="text" class="parent_text" name="workinfo_' . $i . '_pattaname" id="workinfo_' . $i . '_pattaname" maxlength="155" value="' . $detail['PattaName'] . '"></td>
                                                                                        <td width="5%">
                                                                                            <div class="input-grouping">
                                                                                                <input class="parent_text" type="text" onblur="return FormatNum(this, 2)" onkeypress="return isDecimal(event,this)" maxlength="18" name="workinfo_' . $i . '_area" id="workinfo_' . $i . '_area" value="' . $detail['Area'] . '"/>
                                                                                                <div class="input-group-btn" style="top: 0;">
                                                                                                    <select class="parent_text" name="workinfo_' . $i . '_areaunitid" id="workinfo_' . $i . '_areaunitid">
                                                                                                        <?php echo $selectUnit; ?>
                                                                                                    </select>
                                                                                                </div>
                                                                                                <div class="clearfix"></div>
                                                                                            </div>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td colspan="4"><div class="col-lg-12">
                                                                                                <div class="col-lg-12 tap_work">
                                                                                                    <ul>
                                                                                                        <li>
                                                                                                            <label>East Side Details</label>
                                                                                                            <input type="text" class="parent_text" name="workinfo_' . $i . '_eastdetail" id="workinfo_' . $i . '_eastdetail" maxlength="255" value="' . $detail['EastSideDetail'] . '">
                                                                                                        </li>
                                                                                                        <li>
                                                                                                            <label>West Side Details</label>
                                                                                                            <input type="text" class="parent_text" name="workinfo_' . $i . '_westdetail" id="workinfo_' . $i . '_westdetail" maxlength="255" value="' . $detail['WestSideDetail'] . '">
                                                                                                        </li>
                                                                                                        <li>
                                                                                                            <label>North Side Details</label>
                                                                                                            <input type="text" class="parent_text" name="workinfo_' . $i . '_northdetail" id="workinfo_' . $i . '_northdetail" maxlength="255" value="' . $detail['NorthSideDetail'] . '">
                                                                                                        </li>
                                                                                                        <li>
                                                                                                            <label>South Side Details</label>
                                                                                                            <input type="text" class="parent_text" name="workinfo_' . $i . '_southdetail" id="workinfo_' . $i . '_southdetail" maxlength="255" value="' . $detail['SouthSideDetail'] . '">
                                                                                                        </li>
                                                                                                    </ul>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="tab-pane fade" id="prevowner_' . $i . '">
                                                                        <div class="col-lg-12">
                                                                            <div class="table-responsive topsp">
                                                                                <table style=" margin-bottom:0px;" class="table tableWithFloatingHeader">
                                                                                    <thead>
                                                                                    <tr>
                                                                                        <th class="bg_clo">Prev. Owner Name</th>
                                                                                        <th class="bg_clo">Father Name</th>
                                                                                        <th class="bg_clo">DOB</th>
                                                                                        <th class="bg_clo">Contact No</th>
                                                                                        <th class="bg_clo">Passport No</th>
                                                                                        <th class="bg_clo">PAN No</th>
                                                                                        <th class="bg_clo">Action</th>
                                                                                    </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                    <tr>
                                                                                        <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_po_prevownername_1" id="workinfo_' . $i . '_po_prevownername_1" onchange="addPrevOwnerSubTr(this)" maxlength="155"></td>
                                                                                        <td width="8%"><input type="text" class="parent_text" name="workinfo_' . $i . '_po_fathername_1" id="workinfo_' . $i . '_po_fathername_1" onchange="addPrevOwnerSubTr(this)" maxlength="155"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text date_picker" placeholder="DD-MM-YYYY" name="workinfo_' . $i . '_po_dob_1" id="workinfo_' . $i . '_po_dob_1"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_po_contactno_1" id="workinfo_' . $i . '_po_contactno_1" onchange="addPrevOwnerSubTr(this)" maxlength="20" ></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_po_passportno_1" id="workinfo_' . $i . '_po_passportno_1" maxlength="50" onchange="addPrevOwnerSubTr(this)"></td>
                                                                                        <td width="5%"><input type="text" class="parent_text" name="workinfo_' . $i . '_po_panno_1" id="workinfo_' . $i . '_po_panno_1" maxlength="20" onblur="return panjsvalidation(this.value);" onchange="addPrevOwnerSubTr(this)"></td>
                                                                                        <td width="3%" align="center"><a href="#" id="workinfo_' . $i . '_po_delete_1" onclick="deletePrevOwnerSubTr(this, event);" class="subTrDelete" style="margin-right: 10px;"><i class="fa fa-trash ctlss" data-toggle="tooltip" data-placement="top" data-original-title="Delete" ></i></a></td>
                                                                                    </tr>
                                                                                    </tbody>
                                                                                </table>
                                                                                <input type="hidden" name="prevownerid_' . $i . '" id="prevownerid_' . $i . '" value="1">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
        }
        return $html;
    }

    public function followupRegisterAction()
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
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'),'string');
                if($Type == 'Load') {
                    //Write your Ajax post code here
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_LandBankFollow'))
                        ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_CallNatureMaster'), 'a.CallNatureId=c.CallNatureId', array('CallNatureName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_LandCallTypeMaster'), 'a.CallTypeId =d.CallTypeId ', array('CallTypeName'), $select::JOIN_LEFT)
                        ->where(array('DeleteFlag' => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $results = json_encode($results);
                    $response = $this->getResponse()->setStatusCode(200)->setContent($results);
                } else {
                    $postParam = $request->getPost();
//                //Delete FollowUp

                    $postParam = $request->getPost();
                    $update = $sql->update();
                    $update->table('Proj_LandBankFollow');
                    $update->set(array('DeleteFlag' => '1'));
                    $update->where(array('FollowUpId'=>$postParam['id']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $results = json_encode($results);
                    $response = $this->getResponse()->setContent($results);
                }
                return $response;
            }
        }
    }

    public function getLandRegisterAction() {

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $postParams = $request->getPost();
                $sOption =  $this->bsf->isNullCheck($postParams['SortOption'],'string');
                $sEnquiryNo= $this->bsf->isNullCheck($postParams['EnquiryNo'],'string');
                $dFromDate= $this->bsf->isNullCheck($postParams['FromDate'],'date');
                $dToDate= $this->bsf->isNullCheck($postParams['ToDate'],'date');
                $sSourceName= $this->bsf->isNullCheck($postParams['SourceName'],'string');
                $sLocation= $this->bsf->isNullCheck($postParams['Location'],'string');
                $dFromPrice= $this->bsf->isNullCheck($postParams['FromPrice'],'number');
                $dToPrice= $this->bsf->isNullCheck($postParams['ToPrice'],'number');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_LandEnquiry'))
                    ->join(array('b' => 'Proj_SourceMaster'), 'a.SourceId=b.SourceId', array('SourceFrom' => 'SourceName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'Proj_LandInitialFeasibility'), 'a.EnquiryId=e.EnquiryId', array(), $select::JOIN_LEFT)
                    ->columns(array('EnquiryId', 'RefNo', 'RefDate'=>new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"), 'PropertyName', 'SourceName',
                        'TotalArea', 'LandCost', 'PropertyLocation', 'ContactNo', 'Email','PropImageURL','IFeasibilityId','BFeasibilityDone','FFeasibilityDone','DueDiligenceId','FinalizationId','ConceptionDone','KickoffDone','Latitude','Longitude','Radius'));

                //$select->where->like('RefNo', $sEnquiryNo);
                $where = "a.RefDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.RefDate <='". date('d-M-Y', strtotime($dToDate)) ."'";
                if ($sEnquiryNo !="") {
                    $sEnquiryNo = '%' . $sEnquiryNo .'%';
                    $where =  $where . " and a.RefNo like('".$sEnquiryNo."')";
                }
                if ($sLocation !="") {
                    $sLocation = '%' . $sLocation .'%';
                    $where =  $where . " and d.CityName like('".$sLocation."')";
                }
                if ($sSourceName !="") {
                    $sSourceName = '%' . $sSourceName .'%';
                    $where =  $where . " and a.SourceName like('".$sSourceName."')";
                }

                if ($dFromPrice !=0 && $dToPrice !=0) {
                    $where = $where . " and a.LandCost  >= " . $dFromPrice . " and a.LandCost  <= " . $dToPrice;
                } else if ($dFromPrice !=0 && $dToPrice==0) {
                    $where =  $where . " and a.LandCost  = " . $dFromPrice;
                }  else if ($dFromPrice ==0 && $dToPrice!=0) {
                    $where =  $where . " and a.LandCost  = " . $dToPrice;
                }

                $select->where($where);

                if ($sOption == "Most Recent") {
                    $select->order('a.RefDate DESC');
                } else if ($sOption == "PropertyName") {
                    $select->order('a.PropertyName');
                } else if ($sOption == "Location") {
                    $select->order('d.CityName');
                } else if ($sOption == "Source") {
                    $select->order('a.SourceName');
                } else if ($sOption == "Price-Low to High") {
                    $select->order('a.LandCost');
                } else if ($sOption == "Price-High to Low") {
                    $select->order('a.LandCost DESC');
                }

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $data = array();
                $data['trans'] = $results;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }


    public function feasibilityoptionAction(){
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project Conception");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $page = $this->params()->fromRoute('page');

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $EnquiryId = $this->bsf->isNullCheck($request->getPost('EnquiryId'), 'number');
                    $sOptionType = $this->bsf->isNullCheck($request->getPost('OptionType'), 'string');
                    $pageUrl = $this->bsf->isNullCheck($request->getPost('pageUrl'), 'string');
                    $iOptionId=0;
                    $select = $sql->select();
                    $soptionpage = "businessfeasibility";
                    if ($sOptionType =="F") {
                        $soptionpage = "financialfeasibility";
                        $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                            ->join(array('b' => 'Proj_LandFianancialFeasibility'), 'a.FeasibilityId=b.BusinessFeasibilityId', array('FeasibilityId'=>new Expression("BusinessFeasibilityId")), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'a.PropSaleableAreaUnitId=d.UnitId', array('PropSaleableAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('OptionName', 'NoOfBlocks', 'NoOfFloors', 'NoOfFlats', 'TotalArea', 'PropSaleableArea'))
                            ->where("a.EnquiryId=$EnquiryId");
                    } else {
                        $select->from(array('a' => 'Proj_LandBusinessFeasibility'))
                            ->join(array('c' => 'Proj_UOM'), 'a.TotalAreaUnitId=c.UnitId', array('TotalAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'a.PropSaleableAreaUnitId=d.UnitId', array('PropSaleableAreaUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('OptionName', 'NoOfBlocks', 'NoOfFloors', 'NoOfFlats', 'TotalArea', 'PropSaleableArea', 'FeasibilityId'))
                            ->where("a.EnquiryId=$EnquiryId");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $feasibilities = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                    $response = $this->getResponse();
                    if (!count($feasibilities)) {
                        $response->setContent('No Data');
                        $response->setStatusCode('204');
                    } else {
                        $html = '';
                        foreach ($feasibilities as $feasibility) {
                            if ($sOptionType =="F") $iOptionId = $EnquiryId;
                            else $iOptionId = $this->bsf->isNullCheck($feasibility['FeasibilityId'],'number');
                            $iFeasiId = $this->bsf->isNullCheck($feasibility['FeasibilityId'],'number');
                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_LandBusinessFeasibilityFiles'))
                                   ->columns(array('URL'))
                                   ->where("a.FeasibilityId=$iFeasiId and FileType ='image'");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $feasimage = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $type='';
                            if ($sOptionType =="F")
                                $type='F';
                            else
                                $type='R';

                            $html .= ' <div class="col-lg-4 col-lg-offset-0 col-md-4 col-md-offset-0 col-sm-6 col-sm-offset-0 col-xs-12 col-xs-offset-0">
                                <div class="site-img">
                                    <div class="pro-land effects-pro">
                                    <div id = "carousel-example" class="carousel slide" data-ride = "carousel" >
                                    <div class="carousel-inner" >';
                                    $imagecount=1;
                                    foreach ($feasimage as $fimage) {
                                      if ($imagecount==1) $html .= '<div class="item active" >';
                                      else $html .= '<div class="item" >';
                                        $html .= '<div class="imgsslider" style = "background-image:url(' . $viewRenderer->basePath() . $fimage["URL"] .'); height:400px;"></div >
                                           </div >';
                                        $imagecount = $imagecount+1;
                                     }
                            $html .= '</div >
                                      <a class="left carousel-control" href = "#carousel-example" data-slide = "prev" > <span class="glyphicon glyphicon-chevron-left" ></span ></a > <a class="right carousel-control" href = "#carousel-example" data-slide = "next" > <span class="glyphicon glyphicon-chevron-right" ></span ></a >
                                      </div >

                                     <div class="read-pl" >
                                       <a href = "'. $viewRenderer->basePath() . '/project/landbank/' . $soptionpage . '/' . $EnquiryId . '/' . $iOptionId .'/'.$pageUrl.'" class="info" data-toggle = "tooltip" data-placement = "top" data-original-title = "Read More" ></a >
                                      </div >
                                      </div>
                                    <span class="info-det">
                                    <a href="'. $viewRenderer->basePath() . '/project/landbank/' . $soptionpage . '/' . $EnquiryId . '/'  . $iOptionId . '">' . $feasibility['OptionName'] . '</a>
                                    <ul>
                                      <li>
                                        <label>No. of Blocks</label>
                                        <span>' . $feasibility['NoOfBlocks'] . '</span>
                                      </li>
                                      <li>
                                        <label>No. of Floors</label>
                                        <span>' . $feasibility['NoOfFloors'] . '</span>
                                      </li>
                                      <li>
                                        <label>No. of Flats</label>
                                        <span>' . $feasibility['NoOfFlats'] . '</span>
                                      </li>
                                      <li>
                                        <label>Total Area</label>
                                        <span>' . $feasibility['TotalArea'] . ' ' . $feasibility['TotalAreaUnitName'] . '</span>
                                      </li>
                                      <li>
                                        <label>Saleable Area</label>
                                        <span>' . $feasibility['PropSaleableArea'] . ' ' . $feasibility['PropSaleableAreaUnitName'] . '</span>
                                      </li>
                                    </ul>
                                    </span>
                                </div>
                            </div>';
                        }
                        $response->setContent($html);
                    }

                    return $response;
                } catch (PDOException $e) {

                }
            }
        } else {
            // Property Names
            $iEnquiryId =  $this->bsf->isNullCheck($this->params()->fromRoute('EnquiryId'),'number');
            $this->_view->enquiryId= $iEnquiryId;

            $sOptionType =  $this->bsf->isNullCheck($this->params()->fromRoute('OptionType'),'string');
            $this->_view->optiontype= $sOptionType;


            $sPropertyName="";
            if ($iEnquiryId !=0) {
                $select = $sql->select();
                $select->from('Proj_LandEnquiry')
                    ->columns(array('PropertyName'))
                    ->where("EnquiryId=$iEnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $penquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($penquiry)) {
                    $sPropertyName = $this->bsf->isNullCheck($penquiry['PropertyName'],'string');
                }
            }
            $this->_view->landname= $sPropertyName;
            $this->_view->page = (isset($page) && $page != '') ? $page : '';
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function checklistAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $select = $sql->select();
        $select->from( array( 'a' => 'Proj_CheckListMaster' ))
            ->join(array('b' => 'Proj_CheckListTypeMaster'), 'a.TypeId=b.TypeId', array('CheckListTypeName','TypeId'), $select:: JOIN_LEFT)
            ->columns(array('CheckListId','CheckListName'))
            ->where("a.DeleteFlag='0'")
            ->order('a.CheckListName');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->checklists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from( array( 'a' => 'Proj_CheckListTypeMaster' ))
            ->columns(array('TypeId','CheckListTypeName'));
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->checklisttype = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function locationmapAction(){
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
    public function businessfeasibilityRegisterAction(){
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
        $EnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
        $page = $this->params()->fromRoute('page');
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
            $select = $sql->select();
            $select->from( array( 'a' => 'proj_landbusinessfeasibility' ))
                ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select:: JOIN_LEFT)
                ->columns(array("EnquiryId"))
                ->where("a.EnquiryId=$EnquiryId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->Enquiry= $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from(array("a" => "proj_landbusinessfeasibility"))
                ->columns(array(
                    'ProjectTypeName' => new Expression("b.ProjectTypeName"),
                    'NoOfFloors' => new Expression("a.NoOfFloors"),
                    'NoOfFlats' => new Expression("a.NoOfFlats"),
                    'NoOfBlocks' => new Expression("a.NoOfBlocks"),
                    'OptionName' => new Expression("a.OptionName"),
                    'SaleableArea' => new Expression("a.SaleableArea"),
                    'LeasableArea' => new Expression("a.LeasableArea"),
                    'CommonArea' => new Expression("a.CommonArea"),
                    'TotalArea' => new Expression("a.TotalArea"),
                    'TypeOfDevelopement' => new Expression("a.TypeOfDevelopement"),
                    'TypeOfBuilding' => new Expression("a.TypeOfBuilding"),
                    'DwellingOrUnits' => new Expression("a.DwellingOrUnits"),
                    'ParkingReq' => new Expression("a.ParkingReq"),
                    'EWS' => new Expression("a.EWS"),
                    'OSR' => new Expression("a.OSR"),
                ))
                ->join(array("b" => "Proj_ProjectTypeMaster"), "a.ProjectTypeId=b.ProjectTypeId", array(), $select::JOIN_LEFT);
            $select->where("a.EnquiryId=$EnquiryId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists = array();
            $desc=array("ProjectTypeName", "NoOfFloors", "NoOfFlats","NoOfBlocks","SaleableArea","LeasableArea","CommonArea",
                "TotalArea","TypeOfDevelopement","TypeOfBuilding","DwellingOrUnits","ParkingReq","EWS","OSR");
            for($i=0;$i<count($desc);$i++) {
                $arrUnitLists[$i]['Desc']=$desc[$i];
                for($j=0;$j<count($unitList);$j++) {
//                    $arrUnitLists[$i]['Plan'.($j+1)]=$unitList[$j][$desc[$i]];
                    $arrUnitLists[$i][$unitList[$j]['OptionName']]=$unitList[$j][$desc[$i]];
                }
            }
//            echo'<pre>'; print_r($arrUnitLists);die;
            $this->_view->arrUnitLists=$arrUnitLists;
            $this->_view->unitList=$unitList;


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
    public function projectconceptionRegisterAction(){
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
        $EnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
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
            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_LandConceptionRegister' ))
                ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName'), $select:: JOIN_LEFT)
                ->columns(array("EnquiryId"))
                ->where("a.EnquiryId=$EnquiryId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->Enquiry= $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from(array("a" => "Proj_LandConceptionRegister"))
                ->columns(array(
                    'ProjectTypeName' => new Expression("b.ProjectTypeName"),
                    'NoOfFloors' => new Expression("a.NoOfFloors"),
                    'NoOfFlats' => new Expression("a.NoOfFlats"),
                    'NoOfBlocks' => new Expression("a.NoOfBlocks"),
                    'OptionName' => new Expression("a.OptionName"),
                    'SaleableArea' => new Expression("a.SaleableArea"),
                    'LeasableArea' => new Expression("a.LeasableArea"),
                    'CommonArea' => new Expression("a.CommonArea"),
                    'TotalArea' => new Expression("a.TotalArea"),
                ))
                ->join(array("b" => "Proj_ProjectTypeMaster"), "a.ProjectTypeId=b.ProjectTypeId", array(), $select::JOIN_LEFT);
            $select->where("a.EnquiryId=$EnquiryId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists = array();
            $desc=array("ProjectTypeName", "NoOfFloors", "NoOfFlats","NoOfBlocks","SaleableArea","LeasableArea","CommonArea",
                "TotalArea");
            for($i=0;$i<count($desc);$i++) {
                $arrUnitLists[$i]['Desc']=$desc[$i];
                for($j=0;$j<count($unitList);$j++) {
//                    $arrUnitLists[$i]['Plan'.($j+1)]=$unitList[$j][$desc[$i]];
                    $arrUnitLists[$i][$unitList[$j]['OptionName']]=$unitList[$j][$desc[$i]];
                }
            }
//            echo'<pre>'; print_r($arrUnitLists);die;
            $this->_view->arrUnitLists=$arrUnitLists;
            $this->_view->unitList=$unitList;


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