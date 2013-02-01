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
 * 2010 Joni Halme <jontsa@angelinecms.info>
 *
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>
 *
 * Portions created by the Initial Developer are Copyright (C) 2010
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
 * Xml_Conversion, as the name suggests, converts XML-data between following formats:
 * - DOM
 * - SimpleXML
 * - String
 * - PHP object
 * - File
 *
 * It can also apply stylesheets to any of the previous inputs and output the
 * parsed data in any of these formats.
 *
 * This class is not for manipulating XML data. You have PHP DOM and SimpleXML for that.
 * This is only to easily convert between between previous formats and apply stylesheets.
 *
 * The most common usage ofcourse is to convert PHP objects to DomDocuments and vice versa.
 * This conversion works pretty much like the one in Xml-class but this class also supports
 * - attributes
 * - cdata (properly unlike Xml-class)
 * - ArrayAccess classes
 *
 * Example. This loads XML data from file, edits it using SimpleXML, applies stylesheets
 * and echoes the results:
 *
 * $convert=new Xml_Conversion("/path/to/my.xml");
 * $sxml=$convert->toSimpleXML();
 * $sxml->users[0]->name="John Doe";
 * $styles[0]="/path/to/my.xsl";
 * $styles[1]="/path/to/another.xsl";
 * $convert=new Xml_Conversion($sxml);
 * $convert->addStylesheet($styles);
 * echo $convert->toString();
 *
 * @package    Kernel
 * @subpackage Xml
 */

class Xml_Conversion {

	private $source; // source data
	private $is;     // type of the source data as int
	private $styles = array(); // paths to xsl stylesheets

	/**
	 * Constructor takes the source XML as parameter.
	 * The source can be any of the supported formats:
	 * DomDocument, SimpleXMLElement, PHP object, path to file or XML string
	 * @param mixed $source Pointer to source
	 * @todo allow DomNodes alongside of DomDocument as parameter
	 */
	public function __construct(&$source) {
		if($source instanceof DomDocument) {
			$this->is = 0;
		} elseif($source instanceof SimpleXMLElement) {
			if(!class_exists("SimpleXMLElement")) {
				throw new Exception("Can not parse XML from SimpleXMLElement. SimpleXML extension is missing.");
			}
			$this->is = 1;
		} elseif(is_object($source) || is_array($source)) {
			$this->is = 2;
			// only the first element of array/object is used
			// because xml can have only one root element.
			// @todo remove other elements but first
		} elseif(is_file($source)) {
			$this->is = 3;
		} elseif(is_string($source) && !empty($source)) {
			// @todo validation, charset selection
			$this->is = 4;
		} else {
			throw new Exception("XML source is invalid.");
		}
		$this->source = $source;
	}
	
	/**
	* @todo should we catch exceptions and return error string?
	*/
	public function __toString() {
		return $this->toString();
	}
	
	/**
	 * Converts source to XML string.
	 * @param bool $applystyles Apply stylesheets yes/no. Default is true.
	 * @return string
	 * @todo support for passing XsltProcessor as parameter
	 * @todo support for DomNode as source when passing data to XsltProcessor
	 */
	public function toString($applystyles = true) {
		// create XsltProcessor if needed
		if($applystyles && !empty($this->styles)) {
			$proc = self::stylesToProc($this->styles);
			$proc->registerPhpFunctions();
		}
		
		// source parsing if needed
		switch($this->is) {
			case 0:
				$dom = $this->source;
				break;
			case 1:
				if(!isset($proc)) {
					return $this->source->asXML();
				}
			default:
				$dom = $this->toDOM(false);
		}
		
		// if no stylesheets were selected, just return XML string
		if(!isset($proc)) {
			if(!$dom instanceof DomDocument) {
				// when parameter for constructor was DomNode and not domdocument
				return $dom->ownerDocument->saveXML($dom);
			}
			return $dom->saveXML();
		}
		
		// apply stylesheets and return parsed data as string.
		return (string) $proc->transformToXML($dom);
	}
	
	/**
	 * Converts source to DomDocument.
	 * @param bool $applystyles Apply stylesheets yes/no. Default is true.
	 * @return DomDocument
	 * @todo support for passing XsltProcessor as parameter
	 */
	public function toDOM($applystyles = true) {
		if($applystyles && !empty($this->styles)) {
			$proc = self::stylesToProc($this->styles);
			$proc->registerPhpFunctions();
		}
		switch($this->is) {
			case 0:
				if($this->source instanceof DomDocument) {
					return $this->source;
				}
				
				// when parameter for constructor was DomNode
				$dom = new DomDocument("1.0", "utf-8");
				$dome = $dom->importNode($this->source, true);
				$dom->appendChild($dome);
				return $dom;
			case 1:
				// @todo detect charset from simplexml?
				$dom = new DomDocument("1.0", "utf-8");
				$dome = dom_import_simplexml($this->source);
				$dome = $dom->importNode($dome,true);
				$dom->appendChild($dome);
				return $dom;
			case 2:
				$dom = $this->objectToDom($this->source);
				break;
			case 3:
				$dom = new DomDocument;
				$dom->load($this->source);
				break;
			case 4:
				$dom = new DomDocument;
				$dom->loadXML($this->source);
		}
		return isset($proc)?$proc->transformToDoc($dom):$dom;
	}
	
	/**
	 * Converts source to SimpleXMLElement.
	 * @param bool $applystyles Apply stylesheets yes/no. Default is true.
	 * @return SimpleXMLElement
	 * @todo support for passing XsltProcessor as parameter
	 * @todo check simplexml_load_file() return value for false
	 * @todo simplexml_check load_string() return value for false
	 */
	public function toSimpleXML($applystyles = true) {
		if($applystyles && !empty($this->styles)) {
			$dom = $this->toDOM($applystyles);
			return simplexml_import_dom($dom);
		}
		switch($this->is) {
			case 0:
				return simplexml_import_dom($this->source);
			case 1:
				return $this->source;
			case 2:
				$dom = $this->objectToDom($this->source);
				return simplexml_import_dom($dom);
			case 3:
				return simplexml_load_file($this->source);
			case 4:
				return simplexml_load_string($this->source);
		}
	}
	
	/**
	 * Converts source to PHP object.
	 * @param bool $applystyles Apply stylesheets yes/no. Default is true.
	 * @return object
	 * @todo support for passing XsltProcessor as parameter
	 * @todo alternate method for converting simplexml to object?
	 */
	public function toObject($applystyles = true) {
		if($this->is === 2 && (!$applystyles || empty($this->styles))) {
			return $this->source;
		}
		$dom = $this->toDOM($applystyles);
		return $this->domToObject($dom);
	}
	
	/**
	 * Converts source to XML string and write it to file.
	 * @param mixed $filename Optional filename to write to, default is to create temporary file
	 * @param bool $applystyles Apply stylesheets yes/no. Default is true.
	 * @return string Filename
	 * @todo support for passing XsltProcessor as parameter
	 * @todo Filewriting, creating temporary files
	 */
	public function toFile($filename = false, $applystyles = true) {
		return $filename;
	}
	
	/**
	 * Add stylesheet(s) to converter.
	 * @param mixed $files Either single file path as string or array of files
	 * @todo support for stylesheets in string
	 */
	public function addStylesheet($files) {
		$files = array_filter((array)$files);
		foreach($files as $v) {
			if($v instanceof DomDocument) {
				$this->styles[] = $v;
			} elseif(!is_string($v) || in_array($v, $this->styles, true)) {
				return;
			} elseif(is_string($v)) {
				$this->styles[] = $v;
			}
		}
	}
	
	/**
	 * Clears stylesheets from converter.
	 * Note that you can just pass $applystyles=false parameter to converter to
	 * disable stylesheets from output.
	 */
	public function clearStylesheets() {
		$this->styles = array();
	}
	
	/**
	 * Converts XSL files to XsltProcessor instance.
	 * @param mixed $files Either single file path as string or array of files
	 * @return XsltProcessor
	 */
	public static function stylesToProc($files = array()) {
		if(!class_exists("XsltProcessor")) {
			throw new Exception("XSL extension is missing. Can not create XsltProcessor.");
		}
		$dom = self::stylesToDOM($files);
		$proc = new XsltProcessor;
		$proc->importStylesheet($dom);
		return $proc;
	}

	/**
	 * Converts XSL files to DomDocument.
	 * @param mixed $files Either single file path as string or array of files, files can also be DomDocuments
	 * @return DomDocument
	 */
	public static function stylesToDOM($files = array()) {
		foreach($files as $primary=>&$v) {
			if(!$v instanceof DomDocument) {
				$dom = new DomDocument;
				$dom->load($v);
			}
			if($dom->firstChild->nodeName === "xsl:stylesheet") {
				break;
			}
			unset($primary);
		}
		if(!isset($primary)) {
			throw new Common_Exception(i18n("No valid XML stylesheets were found for XSLT parser."));
		}
		
		foreach($files as $k=>&$v) {
			if($k === $primary) {
				continue;
			}
			if($v instanceof DomDocument) {
				if($v->firstChild->nodeName !== "xsl:stylesheet") {
					continue;
				}
				foreach($v->firstChild->childNodes as $include) {
					$dom->appendChild($dom->importNode($include, true));
				}
			} else {
				$include = $dom->createElementNS("http://www.w3.org/1999/XSL/Transform","xsl:include");
				$include->setAttributeNode(new DomAttr("href", $v));
				$dom->firstChild->appendChild($include);
			}
		}
		return $dom;
	}
	
	/**
	 * Converts PHP object to DomDocument.
	 * Note that only the first element in object is converted as XML
	 * can only have one root element.
	 * @param mixed $obj;
	 * @return DomDocument
	 */
	private function objectToDom($obj) {
		$doc = new DomDocument("1.0", "utf-8");
		foreach($obj as $k => &$v) {
			$node = $this->handleObject($v, $doc->createElement($k), $doc);
			break;
		}
		$doc->appendChild($node);
		return $doc;
	}
	
	/**
	 * Recursive object to DomElement converter.
	 * @param stdClass $obj
	 * @param DomNode $node
	 * @param DomDocument $doc Root document
	 */
	private function handleObject($obj, DomNode $node, DomDocument $doc) {
		if(isset($obj->__cdata)) {
			$node->appendChild($doc->createCDATASection($obj->__cdata));
		}
		foreach($obj as $key => &$val) {
			if(in_array($key, array("__attr", "__cdata"), true)) {
				continue;
			} elseif(is_array($val) || $val instanceof ArrayAccess) {
				foreach($val as $k => &$v) {
					$e = $doc->createElement($key);
					if(is_object($v)) {
						$this->handleObject($v, $e, $doc);
					} elseif($v !== NULL) {
						$e->appendChild($doc->createTextNode((string) $v));
					}
					if(isset($obj->__attr) && isset($obj->__attr[$key][$k])) {
						foreach((array)$obj->__attr[$key][$k] as $k2=>$v2) {
							$e->setAttribute($k2, (string)$v2);
						}
					}
					$node->appendChild($e);
				}
				continue;
			} elseif(is_object($val)) {
				$e = $this->handleObject($val, $doc->createElement($key), $doc);
			} elseif($val !== NULL) {
				$e = $doc->createElement($key);
				$e->appendChild($doc->createTextNode($val));
			} else {
				continue;
			}
			if(isset($obj->__attr) && isset($obj->__attr[$key])) {
				foreach((array)$obj->__attr[$key] as $k=>$v) {
					$e->setAttribute($k, (string) $v);
				}
			}
			$node->appendChild($e);
		}
		return $node;
	}

	private function domToObject($dom) {
		return $this->handleNode("/*", new DomXPath($dom));
	}
	
	/**
	 * @todo support for cdata and attributes
	 * @todo more testing and optimize?
	 * @todo support for simplexml
	 */
	private function handleNode($path, $xpath = false) {
		$items = $xpath->query("{$path}");
		if(!is_object($items)) {
			return false;
		}
		if($items->length > 1) {
			$retval = array();
			foreach($items as $k=>$item) {
				array_push($retval, $this->handleNode("{$path}[".($k+1)."]", $xpath));
			}
		} else {
			$retval = new stdClass;
			$nodelist = $xpath->query("{$path}/*");
			foreach($nodelist as $item) {
				if(isset($retval->{$item->nodeName}) && is_object($retval->{$item->nodeName})) {
					$retval->{$item->nodeName} = array(clone $retval->{$item->nodeName});
				}
				$tmp = $xpath->query("{$path}/{$item->nodeName}/*");
				if($tmp->length > 0) {
					if(isset($retval->{$item->nodeName})) {
						$count = count($retval->{$item->nodeName}) + 1;
						array_push($retval->{$item->nodeName}, $this->handleNode("{$path}/{$item->nodeName}[{$count}]", $xpath));
					} else {
						$retval->{$item->nodeName} = $this->handleNode("{$path}/{$item->nodeName}[1]", $xpath);
					}
				} else {
					if(isset($retval->{$item->nodeName})) {
						if(is_array($retval->{$item->nodeName})) {
							array_push($retval->{$item->nodeName}, $item->nodeValue);
						} else {
							if(isset($tmpval)) {
								$retval->{$item->nodeName} = array($tmpval);
								unset($tmpval);
							}
							array_push($retval->{$item->nodeName}, $item->nodeValue);
						}
					} else {
						$retval->{$item->nodeName} = $item->nodeValue;
						$tmpval = $item->nodeValue;
					}
				}
			}
		}
		return $retval;
	}
	
}
