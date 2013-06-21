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
 * 2013 Tarmo Alexander Sundström <ta@sundstrom.im>
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
 * Frontcontroller.
 * @package       Kernel
 * @subpackage    Controller
 * @uses          Controller_Request
 */

// Default route
if(!defined('LIBVALOA_DEFAULT_ROUTE'))        { define('LIBVALOA_DEFAULT_ROUTE', 'index'); }

// Default route when user is authed
if(!defined('LIBVALOA_DEFAULT_ROUTE_AUTHED')) { define('LIBVALOA_DEFAULT_ROUTE_AUTHED', LIBVALOA_DEFAULT_ROUTE); }

class Controller {

	/**
	 * Loads & runs specified module.
	 *
	 * @access      static
	 * @uses        Controller_Request
	 */
	public function __construct() {
		Controller::defaults();

		$request = Controller_Request::getInstance();
		$application = "Module_".$request->getModule();
		if(!in_array($request->getMethod(), get_class_methods($application), true) || substr($request->getMethod(), 0 ,2) === "__" || in_array($request->getMethod(), array("init"), true)) {
			$request->shiftMethod();
			if(in_array("index", get_class_methods($application))) {
				$request->setMethod("index");
			}
		}
		
		$application = new $application;
		$method = $request->getMethod();
		
		if($method) {
			// Get expected parameters
			$reflection = new ReflectionClass($application);
			$expectedParams = $reflection->getMethod($method)->getNumberOfParameters();
			if($expectedParams > 0) {
				for(; $expectedParams != 0; $expectedParams--) {
					$params[] = $request->getParam($expectedParams - 1); // Params start from 0 in Controller_Request
				}
				$params = array_reverse($params);
			}
		
			// Execute the module
			if(isset($params)) {
				call_user_func_array(array($application, $method), $params);
			} else {
				$application->{$method}();
			}
		}
		
		echo $application;
	}

	/**
	* Sets default routes
	*/
	public static function defaults() {
		$request = Controller_Request::getInstance();
		
		if(!$request->getModule() || !$request->moduleExists()) {
			$request->shiftMethod();
			$request->shiftModule();
			
			// Get default module
			if(isset($_SESSION["UserID"]) && !empty($_SESSION["UserID"])) {
				$params = explode("/", LIBVALOA_DEFAULT_ROUTE_AUTHED);
				$request->setModule($params[0]);
			} else {
				$params = explode("/", LIBVALOA_DEFAULT_ROUTE);
				$request->setModule($params[0]);
			}
			
			if(!$request->moduleExists()) {
				throw new Exception("Application not found.");
			} else {
				unset($params[0]);
				if(isset($params[1])) {
					$request->setMethod(array_shift($params));
				}
				$request->setParams($params);
			}
			unset($params);
		}
	}	

}
