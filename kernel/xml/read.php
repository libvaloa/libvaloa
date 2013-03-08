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
 * 2004,2005,2007,2008,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2007,2008,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006 Joni Halme <jontsa@angelinecms.info>
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
 * Reads locale strings from XML using XPath.
 *
 * @package    Kernel
 * @subpackage Xml
 * @uses       Xml
 * @uses       Controller_Request
 */

class Xml_Read extends XML {

	public static $strings = array(); /* Strings for i18n() */

	/**
	 * Returns a list of available locales
	 * @access public
	 * return object $locales
	 */
	public static function availableLocales() {
		foreach($this->paths as $path) {
			if(is_readable($path."/conf/locales.xml")) {
				return XML::fromFile($path."/conf/locales.xml","/locales");
			}			
		}
		return array();
	}

	/**
	 * Returns locale for user.
	 *
	 * Search order is user profile, global profile and then default 'en'.
	 * Locale is stored to self::locale variable for quick retrieval.
	 *
	 * @access public
	 * @return string Locale fe. 'en' or 'fi'
	 */
	public static function detectLocale() {
		if(isset($_SESSION["locale"])) {
			return $_SESSION["locale"];
		}
		return "en";
	}
	
	/**
	 * Loads module strings for translation.
	 * @access public
	 * @param  string $module Module-name
	 */
	public function loadStrings($module = false, $type = false) {
		if(!$module || isset(self::$strings[$module])) {
			return;
		}
		$request = Controller_Request::getInstance();
		$main = $request->getMainModule();
		if($request->getChildModule()) {
			$sub = $request->getMainModule();
		} else {
			$sub = $request->getChildModule();			
		}
		self::$strings[$module] = array();
		if(isset($this->paths) && is_array($this->paths)) {
			foreach($this->paths as $path) {
				if(is_readable($path."/{$main}/{$sub}.xml")) {
					return self::$strings[$module] = $this->parseXML($path."/{$main}/{$sub}.xml", $type);
				}
			}
		}
	}
	
	/**
	 * Loads theme strings for translation.
	 * @access public
	 */
	public function loadThemeStrings() {
		if(!isset(self::$strings["__theme"]) && (isset($this->paths) && is_array($this->paths))) {
			foreach($this->paths as $path) {
				if(is_readable($path."/locale.xml")) {
					return self::$strings["__theme"] = $this->parseXML($path."/locale.xml");		
				}
			}
		}
	}
	
	/**
	 * Loads and parses strings from XML file.
	 *
	 * @access private
	 * @param  string $file Path and filename of XML
	 * @param  string $key Optional target for translations (navi, setting) or false for generic module strings
	 * @return array
	 * @uses   DOMXPath
	 */
	private function parseXML($file, $key = false) {
		$dom = new DomDocument;
		$dom->load($file);
		$xp = new DOMXPath($dom);
		$items = $xp->query("/strings/translation".($key?"[@target='{$key}']":"[@target=false()]")."/value[lang('".self::detectLocale()."')]");
		if($items->length > 0) {
			foreach($items as $k=>$v) {
				$retval[$xp->query("key",$v->parentNode)->item(0)->nodeValue] = $v->nodeValue;
			}
			return $retval;
		}
		return array();
	}

}