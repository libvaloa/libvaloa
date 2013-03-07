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
 * 2009 Joni Halme <jontsa@angelinecms.info>
 *
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>
 *
 * Portions created by the Initial Developer are Copyright (C) 2009,2010
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2009,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
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
 * Controller Request object.
 *
 * $uri must always contain host[/path][/index.php]/module[/method][/params][?getparams] without http[s]:// prefix.
 * If method is not found, it is appended to parameters and no method is called automatically.
 * If module is not found, it is appended to parameters and default module is opened
 * Parameters can be used as variable1/value1/variable2/value2 or value1/value2/value3 etc
 *
 * @package       Kernel
 * @subpackage    Controller
 */

class Controller_Request {

	private static $instance = false;

	private $baseuri = array();       // host (with http[s]:// prefix) and path
	private $module = false;          // requested module to load
	private $method = "index";        // requested method to call from module
	private $parameters = array();    // parameters for module

	private $ajax = false;
	private $json = false;

	function __construct() {
		if(substr(php_sapi_name(), 0, 3) === "cgi" && isset($_SERVER["ORIG_PATH_INFO"])) {
			$uri = $_SERVER["HTTP_HOST"].$_SERVER["ORIG_PATH_INFO"];
		} else {
			$uri = $_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"];
		}
		
		// strip / from the end of url
		if(substr($uri, -1) === "/" && $uri !== "/") {
			$uri = substr($uri, 0, -1);
		}

		// http/https autodetect
		if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") {
			$prefix = "https://";
		} else {
			$prefix = "http://";
		}
		
		// url should be without http[s]:// prefix and contain host[/path][/index.php]/module[/method][/params][?getparams]
		$this->baseuri["host"] = "{$prefix}{$_SERVER['HTTP_HOST']}";

		// Route when rewrite..
		if(isset($_SERVER['REWRITE']) && !strstr($uri,"index.php")) {
			$uri = str_replace($_SERVER["HTTP_HOST"], "{$_SERVER['HTTP_HOST']}/index.php", $uri);
		}
		list($host,$route) = explode("index.php", $uri, 2);				
		$route = str_replace("index.php", "", $route);
		$this->baseuri["path"] = str_ireplace($_SERVER["HTTP_HOST"], "", $host);

		// strip GET parameters, we will add them later
		list($route) = explode("?", $route, 2);
		if(substr($route,0,1) === "/") {
			$route = substr($route, 1);
		}
		$route = explode("/", $route);
		
		// get module from route
		if(isset($route[0])) {
			$this->module = array_shift($route);
		}

		// get method from route
		if(isset($route[0])) {
			$this->method = array_shift($route);
		}
		
		// rest are parameters
		$this->parameters = array_map(array($this, "decodeRouteParam"), $route);
		self::$instance = $this;
		
		// ajax autodetect
		if(isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
			$this->ajax = true;
			if(isset($_SERVER["HTTP_ACCEPT"]) && in_array("application/json", explode(",", $_SERVER["HTTP_ACCEPT"]), true)) {
				$this->json = true;
			}
		}
	}

	/**
	* Returns Controller_Request instance. Use this instead of new Controller_Request.
	* @return Controller_Request
	*/
	public static function getInstance() {
		if(self::$instance) {
			return self::$instance;
		}
		return new Controller_Request;
	}

	/**
	* This method is called from controller if selected method does not exist.
	* We assume that second parameter is not meant as method but as a parameter.
	* @note This method should never ever be called after shiftModule().
	*/
	public function shiftMethod() {
		if($this->method && $this->method != "index") {
			array_unshift($this->parameters, $this->method);
		}
		$this->method = false;
	}

	/**
	* This method is called from controller if selected module does not exist.
	* We assume that first parameter is not meant as module name but as a parameter.
	*/
	public function shiftModule() {
		if($this->module) {
			array_unshift($this->parameters, $this->module);
		}
		$this->module = false;
	}

	/**
	* Sets module to load.
	*/
	public function setModule($module) {
		$this->module = $module;
	}

	/**
	* Sets method to call from module
	*/
	public function setMethod($method) {
		$this->method = $method;
	}

	/**
	* Sets parameters for module.
	*/
	public function setParams($params) {
		if(is_array($params)) {
			$this->params = $params;
		} else {
			$this->params = explode("/", $params);
		}
	}

	/**
	* Returns the parameters and their values from current request.
	* @param bool $string If true, return value is request string, otherwise its an array
	* @return mixed
	*/
	public function getParams($string = false) {
		if(!$string) {
			return $this->parameters;
		}
		return "/".implode("/", $this->parameters);
	}

	/**
	* Returns name of requested module.
	*/
	public function getModule($full = true) {
		if(!$full) {
			$tmp = explode("_", $this->module);
			return $tmp[0];
		}
		return $this->module;
	}

	public function getMainModule() {
		return $this->getModule(false);
	}

	public function getChildModule() {
		$tmp = explode("_", $this->module);
		if(isset($tmp[1])) {
			return $tmp[1];
		}
		return false;
	}

	/**
	* Returns name of requested method.
	*/
	public function getMethod() {
		return $this->method;
	}

	/**
	* Returns a single parameter by its position in parameters or by its key.
	*/
	public function getParam($k) {
		if(is_int($k)) {
			return isset($this->parameters[$k])?$this->parameters[$k]:false;
		} else {
			$k = array_search($k, $this->parameters);
			if($k !== false && isset($this->parameters[$k+1])) {
				return $this->parameters[$k+1];
			}
			return false;
		}
	}

	/**
	* Returns the host-part of the current request IF available.
	* @return string
	*/
	public function getHost() {
		return $this->baseuri["host"];
	}

	/**
	* Returns the path to your angeline installation. The path does not contain index.php.
	* @return string
	*/
	public function getPath() {
		return $this->baseuri["path"];
	}
	
	/**
	* Returns the full route to the current request without the leading /. For example "my_module/method/param1/value1".
	* Parameters in route are encoded
	* @return string
	*/
	public function getCurrentRoute() {
		$params = array_map("encodeRouteParam", $this->parameters);
		return "{$this->module}/".($this->method !== false && $this->method != "index"?"{$this->method}/":"").implode("/", $params);
	}

	/**
	* Returns host and path to the website with http[s]:// prefix.
	* @param bool $noautoindex If true, index.php will not be automatically appended to url.
	* @return string
	*/
	public function getBaseUri($noautoindex = false) {
		return $this->baseuri["host"].$this->baseuri["path"].($this->detectRewrite()||$noautoindex?"":"index.php/");
	}

	/**
	* Returns full URI of the current website with module, method and module parameters.
	*/
	public function getUri() {
		return $this->getBaseUri().$this->getCurrentRoute();
	}

	/**
	* Checks wether or not the requested module exists and that it extends Main-class.
	* @return bool
	*/
	public function moduleExists() {
		return (class_exists("Module_{$this->module}") && is_subclass_of("Module_{$this->module}", "Main"));
	}

	/**
	 * Autodetect if we use rewrite.
	 */
	public function detectRewrite() {
		return isset($_SERVER["REWRITE"]);
	}

	public function isAjax($val = NULL) {
		if($val !== NULL) {
			$this->ajax = (bool)$val;
		}
		return $this->ajax;
	}

	public function isJson($val = NULL) {
		if($val !== NULL) {
			$this->json = (bool)$val;
		}
		return $this->json;
	}
	
	private function decodeRouteParam($val) {
		if(substr($val, 0, 5) === '$enc$') {
			return base64_decode(str_replace(".", "/", urldecode(substr($val, 5))));
		} else {
			return urldecode($val);
		}
	}

}
