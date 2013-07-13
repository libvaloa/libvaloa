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
 * 2004,2005 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005
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
 * Time lib for benchmarking.
 *
 * @package       Kernel
 * @subpackage    Common
 * @uses          libvaloa
 */

class Benchmark {

	private $startTime;
	private $startMem;
	
	public function __construct() {
		$this->startTime = microtime(true);
		$this->startMem = memory_get_usage();
	}

	/**
	 * Starts counter by storing current microtime to $startTime variable.
	 *
	 * @access public
	 * @return float Current microtime
	 */
	public function startCounter() {
		$this->startTime = microtime(true);
		$this->startMem = memory_get_usage();
		return $this->startTime;
	}

	/**
	 * Stops counter and returns benchmark in seconds.
	 *
	 * @access public
	 * @param  integer $decimals number of decimals in benchmark time
	 * @return float Benchmark time
	 */
	public function benchmark($decimals = 3) {
		return sprintf("%0.".(int)$decimals."f", (microtime(true)-$this->startTime));
	}
	
	public function memory() {
		return memory_get_usage() - $this->startMem;
	}
	
	/**
	 * Stops counter and returns benchmark in seconds from the time libvaloa environment was started.
	 *
	 * @access public
	 * @param  integer $decimals number of decimals in benchmark time
	 * @return float Benchmark time
	 */
	public static function benchScript($decimals = 3) {
		return sprintf("%0.".(int)$decimals."f", (microtime(true) - libvaloa::$time));
	}
	
}
