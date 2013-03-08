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
 * 2004,2005,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006,2008,2010 Joni Halme <jontsa@angelinecms.info>
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
 * Adds XSLT features to XML.
 *
 * Allows creating any kind of (text) output using XML and XSL styles.
 *
 * @package    Kernel
 * @subpackage Xml
 */

if(!defined('LIBVALOA_XML_ENABLE_PHP_FUNCTIONS')) define('LIBVALOA_XML_ENABLE_PHP_FUNCTIONS', 1);

class Xml_Xsl {

	/**
	 * Array of XSL filenames to include.
	 * @var string
	 */
	private $xslfiles = array();

	/**
	 * Adds XSL file to list of files to include.
	 *
	 * @access public
	 * @param  mixed $files Filename with path or array of files
	 * @param  bool $prepend If true, file(s) are put to the top of xsl file stack
	 */
	public function includeXSL($files, $prepend = false) {
		$files = (array) $files;
		foreach($files as &$file) {
			if(!in_array($file, $this->xslfiles, true)) {
				if($prepend) {
					array_unshift($this->xslfiles, $file);
				} else {
					$this->xslfiles[] = $file;
				}
			}
		}
	}

	/**
	 * Creates XSL stylesheet and parses XML+XSL using XsltProcessor.
	 *
	 * @todo   Allow changing of encoding
	 * @access public
	 * @param  DomDocument $xmldom XML-data as DomDocument
	 * @return string Parsed data as string
	 * @uses   DomDocument
	 * @uses   XsltProcessor
	 */
	public function parse($xmldom) {
		foreach($this->xslfiles as $primary => &$v) {
			$dom = new DomDocument;
			$dom->load($v);			
			if($dom->firstChild->nodeName === "xsl:stylesheet") {
				break;
			}
			unset($primary);
		}
		if(!isset($primary)) {
			throw new Exception("No valid XML stylesheets were found for XSLT parser.");
		}
		foreach($this->xslfiles as $k => &$v) {
			if($k === $primary) {
				continue;
			}
			$include = $dom->createElementNS("http://www.w3.org/1999/XSL/Transform","xsl:include");
			$include->setAttributeNode(new DomAttr("href", $v));
			$dom->firstChild->appendChild($include);
		}
		$proc = new XsltProcessor;			
		$proc->importStylesheet($dom);	

		// Allow PHP functions from XSL templates
		if(LIBVALOA_XML_ENABLE_PHP_FUNCTIONS == 1) {
			$proc->registerPhpFunctions();
		}
		return (string) $proc->transformToXML($xmldom);
	}

	/**
	 * self to string conversion.
	 *
	 * @access public
	 * @return string Parsed data as string
	 */
	public function __toString() {
		try {
			return $this->parse();
		} catch(Exception $e) {
			return "";
		}
	}

}
