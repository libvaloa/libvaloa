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
 * 2004,2005,2006,2007,2008,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2006,2007,2008,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006,2010 Joni Halme <jontsa@angelinecms.info>
 * 2005 J-P Vieresjoki <jp@angelinecms.info>
 * 2007 Markus Sällinen <mack@angelinecms.info>
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
 * Creates XHTML user interface using Xml_Xsl.
 *
 * @package    Kernel
 * @subpackage Xml
 * @uses       Xml_Xsl
 */

class UI_XML extends Xml Implements UI {

	/**
	 * @access private
	 * @var DOMElement 'pageroot' which can be anything. Default is 'index'
	 */
	private $page;

	public $issetpageroot = false;
	private $requisites;
	private $mainxsl;
	private $xsl;
	private $asxml = false;
	private $paths = array();

	private $properties = array(
		"binding" => "",
		"bindingmain" => "",
		"currentroute" => "",
		"basehref" => "",
		"basepath" => "",
		"locale" => "",
		"title" => "",
		"layout" => "",
		"userid" => "",
		"user" => "",
		"contenttype" => "text/html"
	);

	public function __set($k, $v) {
		if(isset($this->properties[$k])) {
			$this->properties[$k] = $v;
		}
	}

	public function __get($k) {
		if(isset($this->properties[$k])) {
			return $this->properties[$k];
		}
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  mixed $root XML page root. Default is 'page' and you shouldn't change this
	 */
	public function __construct($root = "page") {
		parent::__construct($root);
		$this->xsl = new Xml_Xsl;
		$this->page = $this->dom->createElement("index");
		$this->requisites = new stdClass;
	}

	public function includePath($paths) {
		$paths = (array) $paths;
		foreach($paths as &$path) {
			if(!in_array($path, $this->paths, true)) {
				$this->paths[] = $path;
			}
		}
	}

	public function getIncludePaths() {
		return $this->paths;
	}
	
	/**
	 * Swap between xhtml and xml output.
	 * @param bool $val
	 */
	public function asXML($val) {
		$this->asxml = (bool) $val;		
	}

	/**
	 * Sets page root node name under /page/module.
	 *
	 * Tip: You can gain a minimal speed increase if you set your page root before adding XML.
	 *
	 * @access public
	 * @param  string $root Pageroot node name
	 * @return bool
	 */
	public function setPageRoot($root = false) {
		$this->issetpageroot = true;
		if($this->validateNodeName($root) && $root != $this->page->nodeName) {
			$nodelist = $this->page->childNodes;
			$this->page = $this->dom->createElement($root);
			foreach($nodelist as $item) {
				$this->page->appendChild($item->cloneNode(true));
			}
		}
	}

	/**
	* Returns if page root isset
	* @return mixed
	*/
	public function issetPageRoot() {
		return $this->issetpageroot;
	}

	/**
	 * Adds an object to XML tree.
	 *
	 * $object parameter can also be an array but array keys must be strings.
	 *
	 * @todo    Validate $object
	 * @access  public
	 * @param   object $object XMLVars object
	 * @param   boolean $cdata Defines if we create CDATA section to XMLtree
	 * @param   mixed $key Optional nodename to put XML data under. If false, data is put to page root, otherwise to XML-root.
	 */
	public function addObject($object, $cdata = false, $key = false) {
		if(!is_object($object) && !is_array($object)) {
			return;
		}
		if($key && $this->validateNodeName($key)) {
			$key = $this->dom->createElement($key);
			$this->objectToNode($object, $key, $cdata);
			$this->dom->firstChild->appendChild($key);
		} else {
			$this->objectToNode($object, $this->page, $cdata);
		}
	}

	/**
	 * Set Main XSL file to load.
	 * 
	 * $param string $file
	 */
	public function setMainXSL($file) {
		$this->mainxsl = $file;
	}

	// Interface alias
	public function setMainTemplate($file) {
		$this->setMainXSL($file);
	}
	
	/**
	 * Adds common XML data and returns XSL parser output.
	 *
	 * @param  bool $asxml If true, return value will be XML
	 * @access public
	 * @uses   Xml_Read
	 */
	public function parse($asxml = false) {
		$xml = new stdClass;
		$this->detectMainXSL();

		// Error messages to XML
		if(isset($_SESSION["messages"]) && !empty($_SESSION["messages"])) {
			$xml->messages = $_SESSION["messages"];
			unset($_SESSION["messages"]);
		}
		
		// Page requisites
		foreach($this->requisites as $k=>$v) {
			$xml->{$k} = array_reverse($v);
		}
		
		// Properties
		foreach($this->properties as $k => $v) {
			if(!empty($v)) {
				$xml->$k = $v;				
			}
		}
		$this->addObject($xml, false, "common");
		
		// Return parsed XHTML
		$module = $this->dom->createElement("module");
		$module->appendChild($this->page);
		$this->dom->firstChild->appendChild($module);
 		return $this->asxml?parent::toString():$this->xsl->parse($this->dom);
	}
	
	/**
	 * Detects path and file of 'main' XSL file.
	 *
	 * 'Main' XSL means the root XSL template. If/when found, it is added to list of
	 * XSL files to include.
	 *
	 * @access public
	 * @return bool
	 */
	public function detectMainXSL() {
		$name = (isset($this->mainxsl) && !empty($this->mainxsl))?$this->mainxsl:"index";
		foreach($this->getIncludePaths() as $path) {
			if(is_readable("{$path}".DIRECTORY_SEPARATOR."{$name}.xsl")) {
				$this->xsl->includeXSL("{$path}".DIRECTORY_SEPARATOR."{$name}.xsl", true);
				return;
			}
		}
	}

	/**
	 * Add javascript to be included in XHTML.
	 *
	 * @access public
	 * @param  string $file Javascript file
	 * @param  mixed $module False to search JS-file from current module and theme directories, true to search just module directory or name of the module
	 */
	public function addJS($name = false) {
		$this->addFile($name.".js");
	}
	
	/**
	 * Add CSS file to be included in XHTML.
	 *
	 * File is first looked from theme directory and then from default css directory.
	 *
	 * @access public
	 * @param  string $name CSS filename
	 * @param  mixed $module False to search CSS-file from current module and theme directories, true to search just module directory or name of the module
	 */
	public function addCSS($name = false) {
		$this->addFile($name.".css");
	}

	private function addFile($name = false) {
		$tmp = $name;
		$name = str_replace("/", DIRECTORY_SEPARATOR, $name);
		$ext = substr(strrchr($name, "."), 1);
		foreach($this->paths as &$v) {
			if(is_readable($v.DIRECTORY_SEPARATOR."{$name}") && !empty($ext)) {
				$this->requisites->{$ext}[] = "resource/{$tmp}";
				return;
			}
		}		
	}

	/**
	 * Add XSL-file to parser.
	 *
	 * File is first looked from extensionspath/modules directory, then module directory. If module name was not specified or $module was set to true,
	 * method also looks up the file in theme directories.
	 *
	 * @access public
	 * @param  string $name XSL-filename without .xsl suffix
	 * @param  mixed $module False to search XSL-file from current module and theme directories, true to search just module directory or name of the module
	 * @return bool True if XSL file was included, otherwise false
	 */
	public function addXSL($name = false) {
		$name.= ".xsl";
		foreach($this->paths as $path) {
			if(is_readable("{$path}".DIRECTORY_SEPARATOR."{$name}")) {
				$this->xsl->includeXSL("{$path}".DIRECTORY_SEPARATOR."{$name}");
				return;
			}
		}
	}

	// Interface alias
	public function addTemplate($name = false) {
		$this->addXSL($name);
	}

	/**
	 * Add a error to session.
	 *
	 * @access public
	 * @param  mixed $message Error message or array of errors
	 */
	public function addError($message) {
		$this->addMessage($message, "error");
	}

	/**
	 * Add a message to session.
	 *
	 * @access public
	 * @param  mixed $message Message or array of message strings
	 * @param  string $class Tells XSL/CSS which type of message this is
	 */
	public function addMessage($message, $class = "message") {
		if(!isset($_SESSION["messages"])) {
			$_SESSION["messages"] = array();
		}
		if(is_object($message)) {
			$message = (string) $message;
		}
		$message = (array)$message;
		foreach($message as &$v) {
			$msgObj = new stdClass;
			$msgObj->item = (string) $v;
			$msgObj->type = $class;
			$_SESSION["messages"][] = $msgObj;
		}
	}

	/**
	 * self to string conversion.
	 *
	 * @access public
	 * @return string XHTML output
	 */
	public function __toString() {
		try {
			return (string) $this->parse();
		} catch(Exception $e) {
			return "";
		}
	}

}
