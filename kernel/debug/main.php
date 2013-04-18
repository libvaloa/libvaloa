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
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2006,2007,2008,2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 
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
 * Debug handler.
 *
 * @package       Kernel
 * @subpackage    Debug
 * @uses          Common_Time
 * @uses          Controller_Request
 * @uses          DB
 */

if(!defined('LIBVALOA_DEBUG')) define('LIBVALOA_DEBUG', 0);

class Debug {

	private static $data = array();
	private static $shutdown = false;

	public static function append() {
		$value = func_get_args();
		if(count($value) < 2) {
			$value = reset($value);
		}
		$debugobj = new stdClass;
		$debugobj->time = Common_Time::benchScript(5);
		$debugobj->mu = memory_get_usage();
		$debugobj->type = gettype($value);
		if(is_array($value) || is_object($value)) {
			$debugobj->data = print_r($value, true);
		} else {
			$debugobj->data = $value;
		}
		$backtrace = debug_backtrace();
		$debugobj->backtrace = "{$backtrace[2]["file"]} line {$backtrace[2]["line"]} (Called from {$backtrace[3]["function"]}())";
		if(self::$shutdown === false) {
			register_shutdown_function(array("Debug", "dump"));
			self::$shutdown = true;
		}
		self::$data[] = $debugobj;
	}
	
	public static function dump() {
		if(class_exists('DB')) {
			self::d("Executed ".DB::$querycount." sql queries");			
		}
		print '<div class="debug">';
		foreach(self::$data as $v) {
			echo sprintf('<code><strong>%s</strong> Memory usage %s bytes<br/>%s&#160;[%s]%s</code><br/>', $v->backtrace, $v->mu, $v->time, $v->type, (in_array($v->type, array("array", "object"), true)?"<pre>".$v->data."</pre>":$v->data));
		}
		print '</div>';
		self::$data = array();
	}

	public static function d() {
		if(!libvaloa::$loaded || (!defined('LIBVALOA_DEBUG') || LIBVALOA_DEBUG == 0) || Controller_Request::getInstance()->isJson()) {
			return;
		}

		$a = func_get_args();
		call_user_func_array(array("Debug", "append"), $a);
	}	

	public static function __print() {
		return self::d(func_get_args());
	}	

}

