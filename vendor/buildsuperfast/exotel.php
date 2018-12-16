<?php
class Exotel
{
    private $api_sid = 'micromen';
    private $api_token = 'feb5f1c9ed617ed67ccdef3bf60d070b6c59781d';
	private $CallerId = '09243422233';
    private $callBackUrl = 'https://bjwp.co.uk/bjwp/test.php';
	
	public function connectCallToAgent($from,$to)
    {
		$url = "https://". $this->api_sid.":". $this->api_token."@twilix.exotel.in/v1/Accounts/".$this->api_sid."/Calls/connect";
        $post_data = array(
			'From' => $from,
			'To' => $to,
			'CallerId' => $this->CallerId,
			'CallType' => "trans",
            'StatusCallback' =>$this->callBackUrl
		);
			 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FAILONERROR, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
		 
		$http_result = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch ,CURLINFO_HTTP_CODE);
		 
		curl_close($ch);
		return $http_result;
    }
	
	public function getCallDetails($sid)
    {
		$url = "https://". $this->api_sid.":". $this->api_token."@twilix.exotel.in/v1/Accounts/".$this->api_sid."/Calls/".$sid;
        $post_data = array(
			
		);
			 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FAILONERROR, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
		 
		$http_result = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch ,CURLINFO_HTTP_CODE);
		 
		curl_close($ch);
		return $http_result;
    }
}