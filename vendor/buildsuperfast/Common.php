<?php
class BuildsuperfastClass
{
    const PERPAGE = 10;

	private $sourceType = array('1' => 'News Paper', '2' => 'Web', '3' => 'Clients', '4' => 'Consultants', '5' => 'Well Wishers');
	private $tenderProcessType = array('1' => 'Open Tender', '2' => 'Limited Tender', '3' => 'Single Tender');
	private $consultantType = array('1' => 'Personal Consultant', '2' => 'Technical Consultant', '3' => 'Business Consultant', '4' => 'Executive Consultant');
	private $enquiryCallType = array('1' => 'Site Visit', '2' => 'Document Purchase', '3' => 'Quotation Prepare', '4' => 'Quotation Submit', '5' => 'Revised Quotation', '6' => 'Bid Win', '7' => 'Recive LOI/Order', '8' => 'Assign Check List', '9' => 'Check List - Action', '10' => 'Document Available/Required', '11' => 'Technical Specification', '12' => 'RFI - Request For Information', '13' => 'Close');
	private $businessType = array('1' => 'Small Business', '2' => 'Franchise', '3' => 'Online Business', '4' => 'Family Business', '5' => 'Home-Based Business', '6' => 'Independent Contractor', '7' => 'Importer', '8' => 'Exporter');
	//private $scheduleType = array('1' => 'Stage', '2' => 'EMI', '3' => 'Custom');
	private $emiType = array('1' => 'Monthly', '2' => 'Quarterly', '3' => 'Half-Yearly', '4' => 'Yearly');
	private $rMergeTags = array('*|REF_NO|*','*|REF_DATE|*','*|OWNER|*','*|PROJECT|*','*|UNIT|*','*|OWNER_PERMANANT_ADDRESS|*','*|OWNER_PANNO|*','*|POA_NAME|*','*|ADDRESS_OF_POA|*','*|TENANT_NAME|*','*|TENANT_ADDRESS|*','*|TENANT_PANNO|*','*|TYPE_OF_PROPERTY|*','*|NO_OF_BED_ROOM|*','*|NO_OF_BATH_ROOM|*','*|AREA_OF_PROPERTY|*','*|PROPERTY_ADDRESS|*','*|DATE_OF_AGREEMENT_SIGNED|*','*|DURATION_OF_AGREEMENT|*','*|AGREEMENT_START_DATE|*','*|NOTICE_PERIOD|*','*|MONTHLY_RENT|*','*|DUEDAY_OF_EACH_MONTH|*','*|SECURITY_AMOUNT|*');
	private $cMergeTags = array('*|REF_NO|*','*|REF_DATE|*','*|OWNER|*','*|PROJECT|*','*|UNIT|*','*|OWNER_PERMANANT_ADDRESS|*','*|OWNER_PANNO|*','*|POA_NAME|*','*|ADDRESS_OF_POA|*','*|BUSSINESS_NAME|*','*|BUSSINESS_TYPE|*','*|TYPE_OF_FIRM|*','*|YEAR_ESTABLISH|*','*|REG_ADDRESS|*','*|COMPANY_PAN|*','*|RENTAL_AREA|*','*|RENTAL_FLOOR_OFFER|*','*|PROPERTY_ADDRESS|*','*|DATE_OF_AGREEMENT_SIGNED|*','*|TERMS_OF_LEASE_AGREEMENT|*','*|LOCK_IN_PERIOD|*','*|RENTAL_FREE_PERIOD|*','*|AGREEMENT_START|*','*|NOTICE_PERIOD|*','*|SECURITY_AMOUNT|*','*|RENT_AMOUNT|*','*|RENT_DUE_DATE|*','*|RENT_PAY_GRACE_PERIOD|*','*|LATE_FEES|*','*|MAX_LEVEL_OF_LATE_FESS|*');
	private $renewMergeTags = array('*|REF_NO|*','*|REF_DATE|*','*|AGREEMENT_NO|*','*|RENEWAL_DURATION|*','*|RENEWAL_VALID_DATE|*','*|MONTHLY_RENT|*','*|DUE_DATE_EACH_MONTH|*','*|SECURITY_DEPOSIT_AMOUNT|*');
	private $canMergeTags = array('*|REF_NO|*','*|REF_DATE|*','*|TYPE_OF_CANCELLATION|*','*|AGREEMENT_NO|*','*|CANCEL_REASON|*','*|PROPERTY_VACATE_DATE|*','*|TOTAL_PAYABLE|*');
    private $constructionMergeTags = array('*|DOCUMENT_DATE|*','*|NAME|*','*|FATHER_NAME|*','*|AGE|*','*|ADDRESS1|*','*|ADDRESS2|*','*|CITY|*','*|STATE|*','*|COUNTRY|*','*|PINCODE|*','*|LOCALITY|*','*|PAN_NO|*','*|UNIT_NO|*','*|BLOCK|*','*|LEVEL|*','*|AREA|*','*|FINALISATION_DATE|*','*|COSTCENTRE_NAME|*','*|COSTCENTRE_ADDRESS|*','*|COSTCENTRE_CITY|*','*|COSTCENTRE_PINCODE|*','*|MOBILE_NO|*','*|EMAIL|*','*|REGISTRATION_VALUE|*','*|Rate|*','*|RATE_IN_WORDS|*','*|CAR_PARK_COST|*','*|CAR_PARK_COST_IN_WORDS|*','*|BASIC_COST|*','*|BASIC_COST_IN_WORDS|*','*|UNIT_COST|*','*|UNIT_COST_IN_WORDS|*','*|LAND_COST|*','*|LAND_COST_IN_WORDS|*','*|CONSTRUCTION_COST|*','*|CONSTRUCTION_COST_IN_WORDS|*','*|ADVANCE|*','*|ADVANCE_IN_WORDS|*','*|DOCUMENT_DAY|*','*|DOCUMENT_MONTH|*');
    private $landAgreementMergeTags = array('*|DOCUMENT_DATE|*','*|NAME|*','*|FATHER_NAME|*','*|AGE|*','*|ADDRESS1|*','*|ADDRESS2|*','*|CITY|*','*|STATE|*','*|COUNTRY|*','*|PINCODE|*','*|LOCALITY|*','*|PAN_NO|*','*|UNIT_NO|*','*|BLOCK|*','*|LEVEL|*','*|AREA|*','*|FINALISATION_DATE|*','*|COSTCENTRE_NAME|*','*|COSTCENTRE_ADDRESS|*','*|COSTCENTRE_CITY|*','*|COSTCENTRE_PINCODE|*','*|MOBILE_NO|*','*|EMAIL|*','*|REGISTRATION_VALUE|*','*|Rate|*','*|RATE_IN_WORDS|*','*|CAR_PARK_COST|*','*|CAR_PARK_COST_IN_WORDS|*','*|BASIC_COST|*','*|BASIC_COST_IN_WORDS|*','*|UNIT_COST|*','*|UNIT_COST_IN_WORDS|*','*|LAND_COST|*','*|LAND_COST_IN_WORDS|*','*|CONSTRUCTION_COST|*','*|CONSTRUCTION_COST_IN_WORDS|*','*|ADVANCE|*','*|ADVANCE_IN_WORDS|*','*|DOCUMENT_DAY|*','*|DOCUMENT_MONTH|*');
    private $saleDeedMergeTags = array('*|DOCUMENT_DATE|*','*|NAME|*','*|FATHER_NAME|*','*|AGE|*','*|ADDRESS1|*','*|ADDRESS2|*','*|CITY|*','*|STATE|*','*|COUNTRY|*','*|PINCODE|*','*|LOCALITY|*','*|PAN_NO|*','*|UNIT_NO|*','*|BLOCK|*','*|LEVEL|*','*|UDS|*','*|AREA|*','*|FINALISATION_DATE|*','*|COSTCENTRE_NAME|*','*|COSTCENTRE_ADDRESS|*','*|COSTCENTRE_CITY|*','*|COSTCENTRE_PINCODE|*','*|CO_APPLICANT_NAME|*','*|CO_APPLICANT_ADDRESS|*','*|CO_APPLICANT_AGE|*','*|CO_APPLICANT_RELATIONSHIP_WITH_BUYER|*','*|CO_APPLICANT_PAN_NO|*','*|MOBILE_NO|*','*|EMAIL|*','*|REGISTRATION_VALUE|*','*|Rate|*','*|RATE_IN_WORDS|*','*|CAR_PARK_COST|*','*|CAR_PARK_COST_IN_WORDS|*','*|BASIC_COST|*','*|BASIC_COST_IN_WORDS|*','*|UNIT_COST|*','*|UNIT_COST_IN_WORDS|*','*|LAND_COST|*','*|LAND_COST_IN_WORDS|*','*|CONSTRUCTION_COST|*','*|CONSTRUCTION_COST_IN_WORDS|*','*|ADVANCE|*','*|ADVANCE_IN_WORDS|*','*|DOCUMENT_DAY|*','*|DOCUMENT_MONTH|*');

	private $demandTags = array('*|BUYERNAME|*','*|PROGRESSBILLDATE|*','*|BUYERADDRESS1|*','*|BUYERADDRESS2|*','*|BUYERMAIL|*','*|PROGRESSBILLNO|*','*|NETAMOUNT|*' ,'*|STAGENAME|*','*|OTHERCOSTNAME|*','*|TAXAMOUNT|*','*|GROSSAMOUNT|*','*|PAYMENTSCHEDULE|*','*|BUYERMOBILENUMBER|*','*|BUYERMAILID|*','*|BLOCKNAME|*','*|INTERESTAMOUNT|*','*|CREDITDAYS|*','*|DUEDATE|*','*|PAIDAMOUNT|*','*|BALANCEAMOUNT|*','*|DAYSBALANCE|*');
	private $leadWelcomeTags = array('*|USERNAME|*');
	private $reminderTags = array('*|PBNo|*','*|BillDate|*','*|DaysBal|*','*|BuyerName|*','*|DueDate|*','*|NetAmount|*','*|PaidAmount|*','*|BalanceAmount|*');
	private $receiptConfirmationTags = array('*|LEADNAME|*','*|UNITNO|*','*|FLOORNAME|*','*|PAIDAMOUNT|*','*|BLOCKNAME|*','*|BUYERMAILID|*','*|BUYERADDRESS|*','*|BUYERMOBILENO|*');
    private $unitBlockTags = array('*|LEADNAME|*','*|UNITNO|*','*|PROJECTNAME|*','*|VALIDDATE|*');
    private $bookingFormTags = array('*|LEADNAME|*','*|UNITNO|*','*|PROJECTNAME|*','*|UNITTYPE|*','*|BLOCKNAME|*','*|FLOORNAME|*','*|DISCOUNTTYPE|*','*|DISCOUNT|*','*|NETAMOUNT|*','*|SQRFEET|*','*|ADVANCEAMOUNT|*','*|RATE|*','*|LEADMOBILENUMBER|*','*|MAILID|*','*|ADDRESS|*','*|DATEOFBOOKING|*');
    private $unitUnBlockTags = array('*|LEADNAME|*','*|UNITNO|*','*|PROJECTNAME|*','*|VALIDDATE|*');
    private $allotmentTags = array('*|LEADNAME|*','*|UNITNO|*','*|PROJECTNAME|*','*|UNITTYPE|*','*|BLOCKNAME|*','*|FLOORNAME|*','*|DISCOUNTTYPE|*','*|DISCOUNT|*','*|NETAMOUNT|*','*|SQRFEET|*','*|ADVANCEAMOUNT|*','*|RATE|*','*|LEADMOBILENUMBER|*','*|MAILID|*','*|ADDRESS|*','*|DATEOFBOOKING|*');
    private $stageCompletionTags = array('*|STAGENAME|*','*|UNITNAME|*','*|FLOORNAME|*','*|BLOCKNAME|*','*|COMPLETIONDATE|*','*|PROJECTNAME|*');
    private $buyerLoginTags = array('*|LEADNAME|*','*|USERNAME|*','*|PASSWORD|*');
    private $proposalTags = array('*|LEADNAME|*','*|PROPOSALDATE|*','*|PROJECTNAME|*');



    // instamojo
    public $api_key = '2d7a3960fd309ed1d97ba80045fb44dc';
    public $auth_token = 'd5136c40ac0bcf4a152b1968529fa682';

    public function getSourceType()
	{
        return $this->sourceType;
    }
	
	public function getTenderProcessType()
	{
        return $this->tenderProcessType;
    }
	
	public function getConsultantType()
	{
        return $this->consultantType;
    }
	
	public function getEnquiryCallType()
	{
        return $this->enquiryCallType;
    }
	
	public function getBusinessType()
	{
        return $this->businessType;
    }
	
	/*public function getScheduleType()
	{
        return $this->scheduleType;
    }*/
	
	public function getEmiType()
	{
        return $this->emiType;
    }
	
	public function getMergeTags($type) {
        if($type==1) {
            return $this->rMergeTags;
        } else if($type==2) {
            return $this->cMergeTags;
        } else if($type==3) {
            return $this->renewMergeTags;
        } else if($type==4) {
            return $this->canMergeTags;
        } else if($type==5) {
            return $this->constructionMergeTags;
        } else if($type==6) {
            return $this->landAgreementMergeTags;
        } else if($type==7) {
            return $this->saleDeedMergeTags;
        }
    }
    public function getEmailMergeTags($type) {
            if($type==1) {
                return $this->demandTags;
            } else if($type==2) {
                return $this->leadWelcomeTags;
            } else if($type==3) {
                return $this->reminderTags;
            } else if($type==4) {
                return $this->receiptConfirmationTags;
            } else if($type==5) {
                return $this->unitBlockTags;
            } else if($type==6) {
                return $this->bookingFormTags;
            } else if($type==7) {
                return $this->unitUnBlockTags;
            } else if($type==8) {
                return $this->allotmentTags;
            } else if($type==9) {
                return $this->stageCompletionTags;
            } else if($type==10) {
                return $this->buyerLoginTags;
            } else if($type==11) {
                return $this->proposalTags;
            }

    }
	
	/*public static function currentDateTime() 
	{
		return date('Y-m-d H:i:s');
	}*/

    public function isNullCheck($obj,$datatype)
    {
        if ($datatype =='number') {
            $obj = str_replace(',', '', $obj); // remove commas from value
            if (!isset($obj) || is_null($obj) || empty($obj) || is_numeric($obj)==false) {
                $value =0;
            } else {
                $value =$obj;
            }
        } else if ($datatype=='string') {
            if (!isset($obj) || is_null($obj) || empty($obj)){
                $value ='';
            } else {
                $value = trim($obj);
            }
        } else if ($datatype=='boolean') {
            if (!isset($obj) || is_null($obj) || empty($obj) || is_bool($obj)==false) {
                $value =false;
            } else {
                $value =$obj;
            }
        } else if ($datatype=='date') {
            if (!isset($obj) || is_null($obj) || empty($obj)) {
                $value =date("d-m-Y");
            } else {
                $value =$obj;
            }
        }
        return $value;
    }

    // Sanitize Number
    public function sanitizeNumber($obj, $digit=2) {
        $obj = number_format($obj, $digit,'.', '');
        if($obj == 0)
            $obj = '';

        return $obj;
    }

    public function sanitizeString($obj) {
        $obj = preg_replace("/[^a-zA-Z0-9]+/", "", html_entity_decode($obj, ENT_QUOTES));
        return $obj;
    }

	//Generating activation code
	public function activationCode()
	{
		$salt = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$rand = '';
		$i = 0;
		while ($i < 6) {
			$num = rand() % strlen($salt);
			$tmp = substr($salt, $num, 1);
			$rand = $rand . $tmp;
			$i++;
		}
		return $rand;
	}
	
	//adding http
	public function addHttp($url)
	{
		if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
			$url = "http://" . $url;
		}
		return $url;
	}

    // file Upload
    public function uploadFile($dir, $file) {
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        $mime_types = array('application/pdf','application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel', 'image/jpeg', 'image/png', 'video/mp4','video/x-ms-wmv','video/mpeg','application/msword');
        $validExts = array('pdf','csv', 'xls', 'xlsx', 'jpeg', 'jpg', 'png', 'mp4','wmv','mpg','doc','txt','apk');

        if (!in_array($file['type'], $mime_types) || !in_array($ext, $validExts)) {
            return false;
        }

        $filename = $this->generateRandomString() . "." . $ext;
        //echo $filename;die;
        $filter = new \Zend\Filter\File\Rename(array(
                                                   "target"    => $dir.$filename
                                               ));
        $filter->filter($file['tmp_name']);

        return $filename;
    }

    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
	
	public static function encode($id)
	{
		//return strtotime('2011-06-17 19:14:12') + ($id+1001);
		return base64_encode(base64_encode($id));
	}
	
	public static function decode($id)
	{
		//return ($id - (strtotime('2011-06-17 19:14:12')+1001));
		return base64_decode(base64_decode($id));
	}

    public function timeAgo($time_ago) {
        $time_ago = strtotime($time_ago);
        $cur_time   = time();
        $time_elapsed   = $cur_time - $time_ago;
        $seconds    = $time_elapsed ;
        $minutes    = round($time_elapsed / 60 );
        $hours      = round($time_elapsed / 3600);
        $balMinutes = round($time_elapsed % 3600);
        $balMinutes = round($balMinutes/60);
        $days       = round($time_elapsed / 86400 );
        $weeks      = round($time_elapsed / 604800);
        $months     = round($time_elapsed / 2600640 );
        $years      = round($time_elapsed / 31207680 );
        // Seconds
        if($seconds <= 60){
            return "just now";
        }
        //Minutes
        else if($minutes <=60){
            if($minutes==1){
                return "One min ago";
            }
            else{
                return "$minutes mins ago";
            }
        }
        //Hours
        else if($hours <=24){
            if($hours==1){
                return "A hour ago";
            }else{
                return "$hours hrs $balMinutes mins ago";
            }
        }
        //Days
        else if($days <= 7){
            if($days==1){
                return "Yesterday ".date('h:i A', $time_ago);
            }else{
                return date('F d', $time_ago).','.date('h:i A', $time_ago);
            }
        } else if($years<1) {
            return date('F d', $time_ago).','.date('h:i A', $time_ago);
        } else {
            return date('F d,Y', $time_ago).' '.date('h:i A', $time_ago);
        }
    }
}