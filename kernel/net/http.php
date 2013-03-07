<?php
/**
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is Copyright (C)
 * 2005,2009,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2005,2009,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005 Joni Halme <jontsa@angelinecms.info>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 */
/**
 * Net HTTP class.
 *
 * @package       Kernel
 * @subpackage    Net
 * @todo          transfer-encodings
 */

class Net_HTTP extends Net_Socket {

	private $method;
	private $sliced;
	private $contenttype;
	private $content;
	private $basicauth;
	private $post = array();
	private $get = array();
	private $extraheaders;

	public function __construct() {
		$this->method = "GET";
		$this->contenttype = "text/ascii";
		$this->extraHeaders = "";
	}

	/**
	 * HTTP Request method
	 *
	 * @access public
	 * @param  string $method Either GET, PURGE or POST
	 * @return bool
	 */
	public function setMethod($method) {
		$method = strtoupper($method);
		if(in_array($method, array("GET", "PURGE", "POST")), true)) {
			$this->method = $method;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set URL where to connect.
	 *
	 * Uses sliceAndDiceURL() method to parse parameter string.
	 *
	 * @todo   More validations?
	 * @access public
	 * @param  string $url URL
	 */
	public function setURL($url) {
		$this->sliced = $this->sliceAndDiceURL($url);
	}

	/**
	 * Set request content-type.
	 *
	 * Remember! If you wish to send POST parameters like HTML forms, use
	 * content-type 'application/x-www-form-urlencoded'.
	 *
	 * @access public
	 * @param  string $type Request content-type fe text/xml
	 */
	public function setContentType($type) {
		$this->contenttype = $type;
	}

	/**
	 * Set basic authentication
	 *
	 * @access public
	 * @param  string $user Username
	 * @param  string $pass Password
	 */
	public function setBasicAuth($user, $pass) {
		$this->basicauth["user"] = $user;
		$this->basicauth["pass"] = $pass;
	}

	/**
	 * Set HTTP message payload.
	 *
	 * Remember! If you wish to send POST data like HTML forms, you
	 * should set content-type to 'application/x-www-form-urlencoded' and
	 * use addToPost() method which urlencodes variables.
	 *
	 * @access public
	 * @param  string $data Request contents
	 */
	public function setContent($data) {
		$this->content = $data;
	}

	/**
	 * Add data to POST parameters.
	 *
	 * If you wish to send similar content as HTML forms, remember to set
	 * content-type to 'application/x-www-form-urlencoded'. Otherwise these
	 * variables are just added to the content.
	 *
	 * @access public
	 * @param  string $key Post key
	 * @param  string $value Value for key
	 */
	public function addToPost($key, $value) {
		$this->post[] = urlencode($key)."=".urlencode($value);
	}

	/**
	 * Clears POST variables.
	 *
	 * @access public
	 */
	public function clearPost() {
		$this->post = array();
	}

	/**
	 * Add data to GET parameters.
	 *
	 * @access public
	 * @param  string $key GET key
	 * @param  string $value Key value
	 */
	public function addToGet($key, $value = false) {
		if($value) {
			$this->get[] = urlencode($key)."=".urlencode($value);
		} else {
			$this->get[] = urlencode($key);
		}
	}

	/**
	 * Clears GET variables.
	 *
	 * @access public
	 */
	public function clearGet() {
		$this->get = array();
	}
	
	public function addHeader($header) {
		$this->extraHeaders.= $header."\r\n";
	}

	/**
	 * Send HTTP request.
	 *
	 * @access public
	 * @return mixed Either array with parsed server reply or false on error.
	 */
	public function sendRequest() {
		if(count($this->get) > 0) {
			$getdata = "?".implode("&", $this->get);
		} else {
			$getdata = "";
		}
		if($this->method === "GET" || $this->method === "PURGE") {
			$request = "{$this->method} {$this->sliced['request']}{$getdata} HTTP/1.0\r\n";
			$request.= "Host: {$this->sliced['host']}\r\n";
			if(isset($this->basicauth["user"]) && isset($this->basicauth["pass"])) {
				$str = base64_encode("{$this->basicauth["user"]}:{$this->basicauth["pass"]}");
				$request.= "Authorization: Basic {$str}\r\n";
			}
			$request.= "Connection: Close\r\n";
			$request.= $this->extraHeaders;
			$request.= "Content-type: {$this->contenttype}\r\n";
			$request.= "Content-length: ".strlen($this->content)."\r\n";
			$request.= "\r\n";
			$request.= $this->content;
			$request.= "\r\n\r\n";
		} else {
			$postdata = implode("&", $this->post);
			$this->content = "{$postdata}\r\n{$this->content}";
			$request = "POST {$this->sliced['request']}{$getdata} HTTP/1.0\r\n";
			$request.= "Host: {$this->sliced['host']}\r\n";
			$request.= $this->extraHeaders;
			if(isset($this->basicauth["user"]) && isset($this->basicauth["pass"])) {
				$str = base64_encode("{$this->basicauth["user"]}:{$this->basicauth["pass"]}");
				$request.= "Authorization: Basic {$str}\r\n";
			}
			$request.= "Connection: Close\r\n";
			$request.= "Content-type: {$this->contenttype}\r\n";
			$request.= "Content-length: " . strlen($this->content) . "\r\n";
			$request.= "\r\n";
			$request.= $this->content;
			$request.= "\r\n";
		}
		if($this->connect($this->sliced["host"], $this->sliced["port"], $this->sliced["protocol"])) {
			parent::sendData($request);
			$response = $this->getResponse();
			return $this->parsePostData($response);
		} else {
			return false;
		}
	}

	/**
	 * Parses raw HTTP POST data.
	 *
	 * @todo   More in-depth parsing
	 * @access public
	 * @param  string $data Raw data
	 * @return array Data parsed to an array
	 */
	public function parsePostData($data) {
		$headers = substr($data, 0, strpos($data, "\r\n\r\n"));
		$content = trim(substr($data, strlen($headers)));
		$headers = explode("\r\n",$headers);
		$data = array("headers"=>$headers, "content"=>$content);
		return $data;
	}

	/**
	 * Parse user specified URL.
	 *
	 * @access private
	 * @param  string $url URL to parse
	 * @return array Parsed URL
	 */
	private function sliceAndDiceURL($url){
		// Slice protocol
		if(strstr($url, "://")) {
			list($protocol, $url) = explode('://', $url);
		} else {
			$protocol="http";
		}

		// Slice host
		if(strstr($url, "/")) {
			$host = substr($url, 0, strpos($url, "/"));
			$request = str_replace("{$host}", "", $url);
		} else {
			$host = $url;
			$request = "/";
		}

		// Slice GET parameters
		if(strstr($request, "?")) {
			list($request, $get) = explode("?", $request);
			$get = explode("&", $get);
			foreach($get as $v) {
				$v = explode("=", $v);
				if(!isset($v[1])) {
					$v[1] = false;
				}
				$this->addToGet($v[0], $v[1]);
			}
		}

		// HTTP Auth
		// @todo do something with this
		if(strstr($host, "@")) {
			$user = explode("@", $host);
			$host = $user[1];
			$user = explode(":", $user[0]);
			$__user = $user[0];
			if(isset($user[1])) {
				$__pass = $user[1];
			}
		}

		// Slice host port number
		if(strstr($host, ":")) {
			list($host, $port) = explode(":", $host);
		} elseif($protocol === "https") {
			$port = "443";
		} else {
			$port = "80";
		}
		
		// Patch it up
		$url = array();
		$url["host"] = $host;
		$url["port"] = $port;
		$url["request"] = $request;
		if($protocol === "https") {
			$url["protocol"] = "ssl://";
		} else {
			$url["protocol"] = "";
		}
		return $url;
	}

}

