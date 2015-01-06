<?php

namespace Abraham\TwitterOAuth;
use Abraham\TwitterOAuth\OAuth;
/*
 * Abraham Williams (abraham@abrah.am) https://abrah.am
 *
 * The first PHP Library to support OAuth 1.0A for Twitter's REST API.
 */

/* Load OAuth lib. You can find it at http://oauth.net */
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'OAuth.php');

/**
 * Twitter OAuth class
 */
class TwitterOAuth {
  /* Contains the last HTTP status code returned. */
  public $http_code;
  /* Contains the last API call. */
  public $url;
  /* Set up the API root URL. */
  public $api_host = "https://api.twitter.com";
  /* Set up the API root URL. */
  public $api_version = "1.1";
  /* Set timeout default. */
  public $timeout = 5;
  /* Set connect timeout. */
  public $connecttimeout = 5; 
  /* Decode returned json data to an array. See http://php.net/manual/en/function.json-decode.php */
  public $decode_json_assoc = FALSE;
  /* Contains the last HTTP headers returned. */
  public $http_info;
  /* Set the useragnet. */
  public $useragent = 'TwitterOAuth v0.3.0-dev';
  /* Immediately retry the API call if the response was not successful. */
  //public $retry = TRUE;

  /**
   * Debug helpers
   */
  function lastStatusCode() { return $this->http_code; }
  function lastAPICall() { return $this->last_api_call; }

  /**
   * construct TwitterOAuth object
   */
  function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL) {
    $this->sha1_method = new OAuth\OAuthSignatureMethod_HMAC_SHA1();
    $this->consumer = new OAuth\OAuthConsumer($consumer_key, $consumer_secret);
    if (!empty($oauth_token) && !empty($oauth_token_secret)) {
      $this->token = new OAuth\OAuthConsumer($oauth_token, $oauth_token_secret);
    } else {
      $this->token = NULL;
    }
  }

  /**
   * Make URLs for user browser navigation.
   */
  function url($url, $parameters) {
    $query = http_build_query($parameters);
    return "{$this->api_host}/{$url}?{$query}";
  }

  /**
   * Make /oauth/* requests to the API.
   */
  function oauth($url, $parameters = array()) {
    $url = "{$this->api_host}/{$url}";
    $request = $this->oAuthRequest($url, 'POST', $parameters);
    return OAuth\OAuthUtil::parse_parameters($request);
  }

  /**
   * Make GET requests to the API.
   */
  function get($url, $parameters = array()) {
    $url = "{$this->api_host}/{$this->api_version}/{$url}.json";
    $response = $this->oAuthRequest($url, 'GET', $parameters);
    return json_decode($response, $this->decode_json_assoc);
  }
  
  /**
   * Make POST requests to the API.
   */
  function post($url, $parameters = array()) {
    $url = "{$this->api_host}/{$this->api_version}/{$url}.json";
    $response = $this->oAuthRequest($url, 'POST', $parameters);
    return $response;
    return json_decode($response, $this->decode_json_assoc);
  }

  /**
   * Format and sign an OAuth / API request
   */
  function oAuthRequest($url, $method, $parameters) {
    $request = OAuth\OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $url, $parameters);
    $request->sign_request($this->sha1_method, $this->consumer, $this->token);
    return $this->http($request->get_normalized_http_url(), $method, $request->to_header(), $parameters);
  }

  /**
   * Make an HTTP request
   *
   * @return API results
   */
  function http($url, $method, $header, $postfields = NULL) {

    /* Curl settings */
    $options = array(
      // CURLOPT_VERBOSE => TRUE,
      // CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
      // CURLOPT_CAPATH => __DIR__,
      CURLOPT_CONNECTTIMEOUT => $this->connecttimeout,
      CURLOPT_HEADER => FALSE,
      CURLOPT_HEADERFUNCTION => array($this, 'getHeader'),
      CURLOPT_HTTPHEADER => array($header, 'Expect:'),
      CURLOPT_RETURNTRANSFER => TRUE,
      // CURLOPT_SSL_VERIFYHOST => 2,
      // CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_URL => $url,
      CURLOPT_USERAGENT => $this->useragent,
    );

    switch ($method) {
      case 'GET':
        if (!empty($postfields)) {
          $options[CURLOPT_URL] = $options[CURLOPT_URL] . '?' . OAuth\OAuthUtil::build_http_query($postfields);
        }
        break;
      case 'POST':
        $options[CURLOPT_POST] = TRUE;
        if (!empty($postfields)) {
          $options[CURLOPT_POSTFIELDS] = OAuth\OAuthUtil::build_http_query($postfields);
        }
        break;
    }

    $ci = curl_init();
    curl_setopt_array($ci, $options);
    $response = curl_exec($ci);
    $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    $this->http_info = curl_getinfo($ci);
    $this->url = $url;
    curl_close($ci);

    return $response;
  }

  /**
   * Get the header info to store.
   */
  function getHeader($ch, $header) {
    $i = strpos($header, ':');
    if (!empty($i)) {
      $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
      $value = trim(substr($header, $i + 2));
      $this->http_header[$key] = $value;
    }
    return strlen($header);
  }
}
