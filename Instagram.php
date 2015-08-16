<?php

/**
 * Class para subir imagen a instagram
 * @author Valentin Mari <valentinmari@hotmail.com>
 * 
 */
class Instagram
{  
  protected $username;
  protected $password;

  private $agent;
  private $guid;
  private $device_id;

  const API_URL = 'https://instagram.com/api/v1/';
  const TMP_DIR = '.';

  function __construct($username, $password)
  {
    $this->username  = $username;
    $this->password  = $password;
    $this->agent     = $this->generateUserAgent();
    $this->guid      = $this->generateGuid();
    $this->device_id = $this->getDeviceID();
  }

  private function sendRequest( $url, $post_data = null, $cookies = false )
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, self::API_URL.$url);
    curl_setopt($ch, CURLOPT_USERAGENT, $this->agent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, self::TMP_DIR.'/cookies.txt');

    if($post_data) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if(strpos($response, "Sorry, an error occurred while processing this request.")){
      throw new Exception("Request failed, there's a chance that this proxy/ip is blocked", 1);
    }

    if(empty($response)){
      throw new Exception("Empty response received from the server while trying to login", 1);
    }

    if(strpos($response, "login_required")){
      throw new Exception("You are not logged in. There's a chance that the account is banned", 1);
    }
      
    return $response;
  }

  private function generateGuid()
  {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
      mt_rand(0, 65535), 
      mt_rand(0, 65535), 
      mt_rand(0, 65535), 
      mt_rand(16384, 20479), 
      mt_rand(32768, 49151), 
      mt_rand(0, 65535), 
      mt_rand(0, 65535), 
      mt_rand(0, 65535));
  }

  private function generateUserAgent()
  {
    $resolutions = array('720x1280', '320x480', '480x800', '1024x768',
     '1280x720', '768x1024', '480x320');
    $versions = array('GT-N7000', 'SM-N9000', 'GT-I9220', 'GT-I9100');
    $dpis = array('120', '160', '320', '240');

    $ver = $versions[array_rand($versions)];
    $dpi = $dpis[array_rand($dpis)];
    $res = $resolutions[array_rand($resolutions)];
    
    return 'Instagram 4.'.mt_rand(1,2).'.'.mt_rand(0,2).' Android ('.
      mt_rand(10,11).'/'.mt_rand(1,3).'.'.mt_rand(3,5).'.'.mt_rand(0,5).
      '; '.$dpi.'; '.$res.'; samsung; '.$ver.'; '.$ver.'; smdkc210; en_US)';
  }

  private function generateSignature( $data )
  {
    return hash_hmac('sha256', $data, 'b4a23f5e39b5929e0666ac5de94c89d1618a2916');
  }

  private function getPostData( $filename )
  {
    if(!$filename) {
      throw new Exception('The image doesn\'t exist',1);
    } else {
      $post_data = array('device_timestamp' => time(), 
                'photo' => '@'.$filename);
      return $post_data;
    }
  }

  private function getDeviceID()
  {
    return 'android-'.$this->generateGuid();
  }

  private function generateDataLogin()
  {
    return '{"device_id":"'.$this->device_id.'","guid":"'.$this->guid.'","username":"'.$this->username.'","password":"'.$this->password.'","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
  }

  private function generateDataMediaConfig( $media_id , $caption )
  {
    return '{"device_id":"'.$this->device_id.'","guid":"'.$this->guid.'","media_id":"'.$media_id.'","caption":"'.$caption.'","device_timestamp":"'.time().'","source_type":"5","filter_type":"0","extra":"{}","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
  }

  private function getSignedBody( $data )
  {
    $sig = $this->generateSignature($data);
    return 'signed_body='.$sig.'.'.urlencode($data).'&ig_sig_key_version=4';
  }

  private function decodeResponse( $resp )
  {
    $data = @json_decode($resp,1);
    if(empty($data)){
      throw new Exception('Error decoding response', 1);
    }
  }

  private function cleanCaption( $caption ){
    return trim(preg_replace("/\r|\n/", "", $caption));
  }

  private function doLogin()
  {
    $data = $this->generateDataLogin();
    $data = $this->getSignedBody($data);
    $response = $this->sendRequest('accounts/login/', $data);
    return $this->decodeResponse($response);
  }

  private function doUpload( $file )
  {
    $data = $this->getPostData($file);
    $response = $this->sendRequest('media/upload/', $data, true);
    return $this->decodeResponse($response);
  }

  private function doMediaConfiguration( $media_id , $caption )
  {
    $caption = $this->cleanCaption($caption);
    $data = $this->generateDataMediaConfig($media_id,$caption);
    $data = $this->getSignedBody($data);
    $response = $this->sendRequest('media/configure/', $data, true);
    return $this->decodeResponse($response);
  }

  /**
   * Upload image, must be a perfect square and JPEG formatted.
   * @param  String $path    Path of the image
   * @param  String $caption Message of the image
   * @return [type]          [description]
   */
  public function uploadPhoto( $path, $caption = '' )
  {
    $this->doLogin();
    $resp = $this->doUpload($path);
    if( $resp['status'] == 'ok' ){
      $conf = $this->doMediaConfiguration($resp['media_id'],$caption);
      if( $conf['status'] == 'fail' ){
        throw new Exception("Can't config image", 1);
      }
    }else{
      throw new Exception("Can't upload image", 1);
    }
  }
}