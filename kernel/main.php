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
 * 2004,2005,2006,2007,2008,2009,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2006,2007,2008,2009,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006,2008 Joni Halme <jontsa@angelinecms.info>
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
 * Kernel core.
 *
 * Sets up libvaloa environment, handles opening database
 * connection, calling authentication and formating get/post data.
 *
 * @package       Kernel
 * @uses          Common_Debug
 * @uses          Controller_Request
 * @uses          DB
 * @uses          Xml_UI
 * @uses          xml_Read
 */

class libvaloa {

	public static $db = false;
	public static $time;
	public static $loaded = false;

	/**
	 * Sets up libvaloa environment and registers base classes/functions.
	 */
	public function __construct() {
		// Execution time counter.
		self::$time = microtime(true);
		
		// Start session.
		// ini_set is quite often disabled on shared hosts, so just
		// ignore cg_maxlifetime if ini_set is not available
		if(function_exists('ini_set')) {
			ini_set("session.gc_maxlifetime", "43200");
		}
		session_set_cookie_params(43200);
		session_start();
		
		// Register class autoloader
		spl_autoload_register(array("libvaloa", "autoload"));
		
		// Uncaught exception handler.
		set_exception_handler(array("libvaloa", "exceptionHandler"));
		
		// Enable debug.
		if(defined('LIBVALOA_DEBUG') && LIBVALOA_DEBUG == 1) {
			error_reporting(E_ALL | E_STRICT);
		} else {
			error_reporting(0);
		}
		self::$loaded = true;
	}

	/**
	 * Class autoloader.
	 *
	 * @access public
	 * @param  string $name Class name
	 */
	public static function autoload($name) {		
		// Load module
		if(strstr(strtolower($name), "module_")) {
			// Module
			$tmp = explode("_", strtolower($name));
			if(isset($tmp[2]) && !empty($tmp[2])) {
				$search[] = LIBVALOA_EXTENSIONSPATH."/modules/{$tmp[1]}/{$tmp[2]}.php";
			}
			if(isset($tmp[1]) && !empty($tmp[1])) {
				$search[] = LIBVALOA_EXTENSIONSPATH."/modules/{$tmp[1]}/{$tmp[1]}.php";
			}
		} else {
			// Load kernel classes			
			// Search for Zend Framework classes
			if(strstr($name, "Zend_")) {
				$filename = str_replace("_", DIRECTORY_SEPARATOR, $name);
			} else {
				$filename = strtolower(str_replace("_", DIRECTORY_SEPARATOR, $name));
			}
			foreach(explode(PATH_SEPARATOR, get_include_path()) as $path) {
				$search[] = $path.$filename.".php";
			}
			foreach(explode(PATH_SEPARATOR, get_include_path()) as $path) {
				$search[] = $path.$filename."/main.php";
			}
		}
		
		// Include classes if found
		if(isset($search)) {
			foreach($search as &$v) {
				if(is_readable($v)) {
					include_once($v);
					return;
				}
			}
		}
	}

	/**
	 * Opens database connection.
	 *
	 * DB connection is accessed via global db() function.
	 *
	 * @access      static
	 * @return      DB database connection
	 * @uses        DB
	 */
	static function openDBConnection() {
		if(!self::$db instanceof DB) {
			try {
				if(LIBVALOA_DB != "sqlite") {
					$initquery = "SET NAMES 'UTF8'";
				} else {
					$initquery = "";
				}
				self::$db = new DB(
					LIBVALOA_DB_SERVER, 
					LIBVALOA_DB_USERNAME, 
					LIBVALOA_DB_PASSWORD, 
					LIBVALOA_DB_DATABASE, 
					LIBVALOA_DB, 
					false, 
					$initquery);
			} catch(Exception $e) {
				die($e->getMessage());
			}
		}
		return self::$db;
	}

	/**
	 * Catches uncaught exceptions and displays error message.
	 */
	static function exceptionHandler($e) {
		print "<h3>An error occured which could not be fixed.</h3>";
		printf("<p>%s</p>", $e->getMessage());
		if($e->getCode()) {
			print " (".$e->getCode().")";
		}
		if(LIBVALOA_DEBUG == 1) {
			printf("<p><b>Location:</b> %s line %s.</p>", $e->getFile(), $e->getLine());
			print "<h4>Exception backtrace:</h4>";
			print "<pre>";
			print_r($e->getTrace());
			print "</pre>";
		}
	}

}

/**
 * Base class which modules extend.
 * @package       Kernel
 */
class Main {

	protected $params = false;

	public function __get($k) {
		if($k === "request") {
			$this->request = Controller_Request::getInstance();
			return $this->request;
		} elseif($k === "ui") {
			// Locale
			$locale = $this->request->getParam("locale");
			if($locale) {
				$_SESSION["locale"] = $locale;
			}
			if(!isset($_SESSION["locale"]) && defined("LIBVALOA_LOCALE")) {
				$_SESSION["locale"] = LIBVALOA_LOCALE;
			}
			if(!isset($_SESSION["locale"])) {
				$_SESSION["locale"] = "en";
			}

			// UI			
			$this->ui = new XML_UI;

			// File paths for the UI
			$this->ui->includePath(LIBVALOA_EXTENSIONSPATH."/themes");
			$this->ui->includePath(LIBVALOA_EXTENSIONSPATH."/modules");
			$this->ui->includePath(LIBVALOA_EXTENSIONSPATH."/themes/".LIBVALOA_LAYOUT);
			$this->ui->includePath(LIBVALOA_EXTENSIONSPATH."/modules/".$this->request->getMainModule());
			if($this->request->isAjax()) {
				$this->ui->setMainXSL("empty");
			}

			// UI properties
			$this->ui->binding = $this->request->getModule();
			$this->ui->bindingMain = $this->request->getMainModule();
			$this->ui->currentroute = $this->request->getCurrentRoute();
			$this->ui->basehref = $this->request->getBaseUri();
			$this->ui->basepath = $this->request->getPath();
			$this->ui->lang = Xml_Read::detectLocale();
			if(isset($_SESSION["UserID"])) {
				$this->ui->userid = $_SESSION["UserID"];
			}
			if(isset($_SESSION["User"])) {
				$this->ui->user = $_SESSION["User"];
			}

			return $this->ui;
		} elseif($k === "view") {
			$this->view = new stdClass;
			return $this->view;
		} elseif(!empty($this->params)) {
			$this->parseParameters();
		}
		if(isset($this->{$k})) {
			return $this->{$k};
		}
		trigger_error("Call to an undefined property ".get_class($this)."::\${$k}", E_USER_WARNING);
		return NULL;
	}

	public function __isset($k) {
		if(!empty($this->params)) {
			$this->parseParameters();
		}
		return isset($this->{$k});
	}

	public function __toString() {
		if(!$this->ui->issetPageRoot() && $this->request->getMethod()) {
			$this->ui->setPageRoot($this->request->getMethod());
		}
		try {
			$this->ui->addObject($this->view);
			if($this->request->getChildModule()) {
				// Load resources for child module, /application_subapplication
				$this->ui->addXSL($this->request->getChildModule());
				$this->ui->addCSS($this->request->getMainModule()."/".$this->request->getChildModule());
				$this->ui->addJS($this->request->getMainModule()."/".$this->request->getChildModule());
			} else {
				// Load resources for main application, /application
				$this->ui->addXSL($this->request->getMainModule());
				$this->ui->addCSS($this->request->getMainModule()."/".$this->request->getMainModule());
				$this->ui->addJS($this->request->getMainModule()."/".$this->request->getMainModule());
			}
			header("Content-type: ".$this->ui->contenttype."; charset=utf-8");
			header("Vary: Accept");
			return (string) $this->ui;
		} catch(Exception $e) {
			return $e->getMessage();
		}
	}

	private function parseParameters() {
		if(is_array($this->params)) {
			foreach($this->params as $k=>$v) {
				if($v) {
					$this->{$v} = $this->request->getParam($k);
				}
			}
		}
		$this->params = false;
	}

}

/**
* Encodes string so that its safe to add as parameter to your route. This allows you to use parameters with slashes
* in your requests. The parameters automatically decoded when they are parsed by Controller_Request.
* @param string $val
* @return string
*/
function encodeRouteParam($val) {
	if(strpos($val, "/") !== false) {
		return "\$enc\$".urlencode(str_replace("/", ".", base64_encode($val)));
	} else {
		return urlencode($val);
	}
}

/**
 * i18n support function. This function translates given text if possible and it supports
 * parameters similar to sprintf(). This function can be called from xsl or php.
 * @package  Kernel
 * @return   string
 */
function i18n() {
	$args = func_get_args();
	if(empty($args)) {
		return "";
	}
	foreach($args as $k=>$v) {
		if(isset($v[0]) && is_object($v[0]) && isset($v[0]->nodeValue)) {
			$args[$k] = $v[0]->nodeValue;
		}
	}
	$string = reset($args);
	$read = new Xml_Read;
	if(libvaloa::$loaded) {
		$module = Controller_Request::getInstance()->getModule();
		$read->loadStrings($module);
		if(isset(Xml_Read::$strings[$module][$string])) {
			$args[0] = Xml_Read::$strings[$module][$string];
			return call_user_func_array("sprintf", $args);
		}
		$module = Controller_Request::getInstance()->getMainModule();
		$read->loadStrings($module);
		if(isset(Xml_Read::$strings[$module][$string])) {
			$args[0] = Xml_Read::$strings[$module][$string];
			return call_user_func_array("sprintf", $args);
		}
		$read->loadThemeStrings();
	}
	foreach(Xml_Read::$strings as &$v) {
		if(isset($v[$string])) {
			$args[0] = $v[$string];
			break;
		}
	}
	return call_user_func_array("sprintf",$args);
}

/**
 * Database access.
 *
 * @access public
 * @return DB
 */
function db() {
	return libvaloa::openDBConnection();
}

/**
 * DEBUG function.
 *
 * @package Kernel
 * @access  public
 */
function DEBUG() {
	if(!libvaloa::$loaded || (!defined('LIBVALOA_DEBUG') || LIBVALOA_DEBUG == 0) || !class_exists("Common_Debug") || Controller_Request::getInstance()->isJson())
		return;

	$a = func_get_args();
	call_user_func_array(array("Common_Debug", "append"), $a);
}
