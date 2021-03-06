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
 * 2006 Joni Halme <jontsa@angelinecms.info>
 *
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>
 *
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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
 * Main exception class. All other libvaloa exceptions should extend this class.
 *
 * @package       Kernel
 * @subpackage    Common
 */

class Common_Exception extends Exception {

	private $previous = NULL; // simulates PHP 5.3 functionality

	public function __construct($message = NULL, $code = 0, Exception $previous = null) {
		if(version_compare(PHP_VERSION,"5.3.0") >= 0) {
			parent::__construct($message, $code, $previous);
		} else {
			parent::__construct($message, $code);
		}
	}

	public function __toString() {
		return $this->message;
	}
	
	/**
	* Method overload. Adds getPrevious() method to exceptions
	* when using PHP < 5.3.
	*/
	public function __call($m, $a) {
		if(strtolower($m) === "getprevious") {
			return $this->previous;
		}
		foreach(debug_backtrace() as $tv) {
			if(isset($tv["function"]) && $tv["function"] === $m) {
				break;
			}
		}
		trigger_error("Call to an undefined method ".get_class($this)."::{$m}() in {$tv['file']} line {$tv['line']}", E_USER_ERROR);
		exit;
	}

}
