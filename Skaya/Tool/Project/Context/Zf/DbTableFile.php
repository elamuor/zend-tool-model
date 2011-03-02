<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Tool
 * @subpackage Framework
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @see Zend_Tool_Project_Context_Filesystem_File
 */

/**
 * This class is the front most class for utilizing Zend_Tool_Project
 *
 * A profile is a hierarchical set of resources that keep track of
 * items within a specific project.
 * 
 * @category   Zend
 * @package    Zend_Tool
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Skaya_Tool_Project_Context_Zf_DbTableFile extends Zend_Tool_Project_Context_Zf_DbTableFile
{
	
	protected $_dbTableName;
	protected $_isAbstract = false;
	protected static $_abstractTableName;
	
	protected $_defaultNameFilter = null;
	
	public function init()
	{
		$this->_defaultNameFilter = new Zend_Filter_Word_UnderscoreToCamelCase();
		$this->setDbTableName($this->_resource->getAttribute('dbTableName'));
		$this->_isAbstract = $this->_resource->getAttribute('abstract') === 'true';
		if ($this->_isAbstract) {
			self::$_abstractTableName = $this->getDefaultNamespace().$this->_defaultNameFilter->filter($this->getDbTableName());
		}
		return parent::init();
	}
	
	/**
	 * getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'DbTableFile';
	}
	
	public function getDefaultNamespace() {
		return 'Model_DbTable_';
	}
	
	public function getPersistentAttributes()
	{
		$persistent = array('dbTableName' => $this->getDbTableName());
		if ($this->_isAbstract) {
			$persistent['abstract'] = 'true';
		}
		return $persistent;
	}

	public function setDbTableName($dbTableName)
	{
		$this->_dbTableName = $dbTableName;
		$this->_filesystemName = $this->_defaultNameFilter->filter($dbTableName) . '.php';
	}

	public function getDbTableName()
	{
		return $this->_dbTableName;
	}
	
	public function getContents() {
		if ($this->_isAbstract) {
			return $this->_getAbstractClassDefinition();
		}
		$ns = $this->getDefaultNamespace();
		$className = $ns.$this->_defaultNameFilter->filter($this->getDbTableName());
		
		$foreignKeyReference = Zend_Tool_Project_Provider_Model::getForeignKeysReference($this->getDbTableName());
		foreach ($foreignKeyReference['dependent'] as &$_d) {
			$_d = $ns.$this->_defaultNameFilter->filter($_d);
		}
		foreach ($foreignKeyReference['references'] as &$_d) {
			$_d['refTableClass'] = $ns.$this->_defaultNameFilter->filter($_d['refTableClass']);
		}
		
		$properties = array(
			array(
				'name'         => '_name',
				'visibility'   => 'protected',
				'defaultValue' => new Zend_CodeGenerator_Php_Property_DefaultValue(array(
					'value' => $this->getDbTableName(),
					'type' => Zend_CodeGenerator_Php_Property_DefaultValue::TYPE_STRING
				))
			),
			array(
				'name'         => '_primary',
				'visibility'   => 'protected',
				'defaultValue' => new Zend_CodeGenerator_Php_Property_DefaultValue(array(
					'value' => $this->_getTablePrimaryKey(),
					'type' => Zend_CodeGenerator_Php_Property_DefaultValue::TYPE_AUTO
				))
			)
		);
		
		if (!empty($foreignKeyReference['dependent'])) {
			$properties[] = array(
						'name' => '_dependentTables',
						'visibility'   => 'protected',
						'defaultValue' => new Zend_CodeGenerator_Php_Property_DefaultValue(array(
							'value' => $foreignKeyReference['dependent'],
							'type' => Zend_CodeGenerator_Php_Property_DefaultValue::TYPE_AUTO
						))
					);
		}
		
		if (!empty($foreignKeyReference['references'])) {
			$properties[] = array(
						'name' => '_referenceMap',
						'visibility'   => 'protected',
						'defaultValue' => new Zend_CodeGenerator_Php_Property_DefaultValue(array(
							'value' => $foreignKeyReference['references'],
							'type' => Zend_CodeGenerator_Php_Property_DefaultValue::TYPE_AUTO
						))
					);
		}
		
		$codeGenFile = new Zend_CodeGenerator_Php_File(array(
			'fileName' => $this->getPath(),
			'classes' => array(
				new Zend_CodeGenerator_Php_Class(array(
					'name' => $className,
					'extendedClass' => 'Model_DbTable_Abstract',
					'properties' => $properties
				))
			)
		));
		Zend_CodeGenerator_Php_File::registerFileCodeGenerator($codeGenFile); 
		return $codeGenFile->generate();
	}
	
	protected function _getTablePrimaryKey() {
		$primaryKeys = array();
		/**
		* DB adapter used for constructing model
		* 
		* @var Zend_Db_Adapter_Abstract
		*/
		$adapter = Zend_Tool_Project_Provider_Model::getDbAdapter();
		$keysInfo = $adapter->fetchAll('SHOW INDEX FROM '.$adapter->quoteTableAs($this->getDbTableName()));
		$keysInfo = array_filter($keysInfo, create_function('$key', 'return ($key["Key_name"] == "PRIMARY");'));
		if (count($keysInfo) == 1) {
			$primaryKeys = array_shift($keysInfo);
			$primaryKeys = $primaryKeys['Column_name'];
		}
		else {
			foreach ($keysInfo as $_k) {
				$primaryKeys[] = $_k['Column_name'];
			}
		}
		return $primaryKeys;
	}
	
	protected function _getAbstractClassDefinition() {
		$className = self::$_abstractTableName;
		$codeGenFile = new Zend_CodeGenerator_Php_File(array(
			'fileName' => $this->getPath(),
			'classes' => array(
				new Zend_CodeGenerator_Php_Class(array(
					'abstract' => true,
					'name' => $className,
					'extendedClass' => 'Zend_Db_Table',
					'methods' => array(
						array(
							'name' => 'fetchAllBy',
							'parameters' => array(
								array('name' => 'key'),
								array('name' => 'value'),
								array('name' => 'order', 'defaultValue' => null),
								array('name' => 'count', 'defaultValue' => null),
								array('name' => 'offset', 'defaultValue' => null)
							),
							'body' => <<<EOS
		if (is_array(\$value)) {
			\$where = \$this->getAdapter()->quoteInto("\$key IN (?)", \$value);
		}
		else {
			\$where = \$this->getAdapter()->quoteInto("\$key = ?", \$value);
		}
		return \$this->fetchAll(\$where, \$order, \$count, \$offset);
EOS
						),
						array(
							'name' => 'fetchRowBy',
							'parameters' => array(
								array('name' => 'key'),
								array('name' => 'value'),
								array('name' => 'order', 'defaultValue' => null),
								array('name' => 'count', 'defaultValue' => null),
								array('name' => 'offset', 'defaultValue' => null)
							),
							'body' => <<<EOS
		\$where = \$this->getAdapter()->quoteInto("\$key = ?", \$value);
		return \$this->fetchRow(\$where, \$order, \$count, \$offset);
EOS
						),
						array(
							'name' => '__call',
							'parameters' => array(
								array('name' => 'name'),
								array('name' => 'arguments')
							),
							'body' => <<<EOS
		\$filter = new Zend_Filter_Word_CamelCaseToUnderscore();
		\$actionName = '';
		foreach (array('fetchRowBy', 'fetchAllBy', 'deleteBy') as \$_a) {
			if (strpos(\$name, \$_a) === 0) {
				\$actionName = \$_a;
				break;
			}
		}
		if (empty(\$actionName)) throw new Exception("Undefined method \$name");
		\$fetchField = substr(\$name, strlen(\$actionName));
		array_unshift(\$arguments, strtolower(\$filter->filter(\$fetchField)));
		return call_user_func_array(array(\$this, \$actionName), \$arguments);
EOS
						),
						array(
							'name' => 'deleteBy',
							'parameters' => array(
								array('name' => 'key'),
								array('name' => 'value')
							),
							'body' => <<<EOS
		return \$this->delete(\$this->getAdapter()->quoteInto(\$key.' = ?', \$value));
EOS
						),
						array(
							'name' => 'filterDataByRowsNames',
							'parameters' => array(
								array('name' => 'data')
							),
							'body' => <<<EOS
		\$_cols = \$this->info(self::COLS);
		return (!empty(\$_cols))?array_intersect_key(\$data, array_flip(\$_cols)):array();
EOS
						)
					)
				))
			)
		));
		Zend_CodeGenerator_Php_File::registerFileCodeGenerator($codeGenFile); 
		return $codeGenFile->generate();
	}
}
