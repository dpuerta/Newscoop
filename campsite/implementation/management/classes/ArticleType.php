<?php
/**
 * @package Campsite
 */

/**
 * Includes
 */
// We indirectly reference the DOCUMENT_ROOT so we can enable
// scripts to use this file from the command line, $_SERVER['DOCUMENT_ROOT']
// is not defined in these cases.
if (!isset($g_documentRoot)) {
    $g_documentRoot = $_SERVER['DOCUMENT_ROOT'];
}
/**
 * Includes
 */
require_once($g_documentRoot.'/classes/DatabaseObject.php');
require_once($g_documentRoot.'/classes/Log.php');
require_once($g_documentRoot.'/classes/ArticleTypeField.php');
require_once($g_documentRoot.'/classes/ParserCom.php');

/**
 * @package Campsite
 */
class ArticleType {
	var $m_columnNames = array();
	var $m_dbTableName;
	var $m_name;
	var $m_metadata;


	/**
	 * An article type is a dynamic table that is created for an article
	 * to allow different publications to display their content in different
	 * ways.
	 *
	 * @param string $p_articleType
	 */
	function ArticleType($p_articleType)
	{
		$this->m_name = $p_articleType;
		$this->m_dbTableName = 'X'.$p_articleType;
		// Get user-defined values.
		$dbColumns = $this->getUserDefinedColumns();
		foreach ($dbColumns as $columnMetaData) {
			$this->m_columnNames[] = $columnMetaData->getName();
		}
		$this->m_metadata = $this->getMetadata();
	} // constructor


	/**
	 * Create a new Article Type.  Creates a new table in the database.
	 * @return boolean
	 */
	function create()
	{
		global $Campsite;
		$queryStr = "CREATE TABLE `".$this->m_dbTableName."`"
					."(NrArticle INT UNSIGNED NOT NULL, "
					." IdLanguage INT UNSIGNED NOT NULL, "
					." PRIMARY KEY(NrArticle, IdLanguage))";
		$success = $Campsite['db']->Execute($queryStr);

		if ($success) {
			$queryStr = "INSERT INTO ArticleTypeMetadata"
						."(type_name, is_hidden) "
						."VALUES ('".$this->m_dbTableName."', 0)";
			print $queryStr;
			$success2 = $Campsite['db']->Execute($queryStr);			
		} else {
			return $success;
		}

		if ($success2) {
			if (function_exists("camp_load_language")) { camp_load_language("api");	}
		    $logtext = getGS('The article type $1 has been added.', $this->m_dbTableName);
	    	Log::Message($logtext, null, 61);
			//ParserCom::SendMessage('article_types', 'create', array("article_type"=>$this->m_dbTableName));
		} else {
			$queryStr = "DROP TABLE ".$this->m_dbTableName;
			$result = $Campsite['db']->Execute($queryStr);
			// RFC: Maybe a check on this result as well?  We drop the table since creation is two-tier: create the table,
			// then add the entry into ArticleTypeMetadata; so if the second part failed, but hte first part worked (when would 
			// that ever really happen??) we drop the table and return 0.  But if the table drop breaks too, should I
			// give a more verbose error.  I'm voting not, due to rarity--and if things get that bad they have other issues.
		}
		
		return $success2;
	} // fn create


	/**
	 * Return TRUE if the Article Type exists.
	 * @return boolean
	 */
	function exists()
	{
		global $Campsite;
		$queryStr = "SHOW TABLES LIKE '".$this->m_dbTableName."'"; // the old code had an X, but m_dbTableName in ArticleType::articleType() is already with an X pjh
		$result = $Campsite['db']->GetOne($queryStr);
		if ($result) {
			return true;
		} else {
			return false;
		}
	} // fn exists


	/**
	 * Delete the article type.  This will delete the entire table
	 * in the database.  Not recommended unless there is no article
	 * data in the table.
	 */
	function delete()
	{
		global $Campsite;
		$queryStr = "DROP TABLE ".$this->m_dbTableName;
		$success = $Campsite['db']->Execute($queryStr);
		if ($success) {
			$queryStr = "DELETE FROM ArticleTypeMetadata WHERE type_name='".$this->m_dbTableName."'";
			$success2 = $Campsite['db']->Execute($queryStr);
		} 
		
		if ($success2) {
			if (function_exists("camp_load_language")) { camp_load_language("api");	}
			$logtext = getGS('The article type $1 has been deleted.', $this->m_dbTableName);
			Log::Message($logtext, null, 62);
			ParserCom::SendMessage('article_types', 'delete', array("article_type" => $this->m_name));
		}
	} // fn delete

	/**
	 * Rename the article type.  This will move the entire table in the database and update ArticleTypeMetadata.
	 * Usually, one wants to just rename the Display Name, which is done via SetDisplayName
	 *
	 */
	function rename($p_newName)
	{
		global $Campsite;
		if (!ArticleType::isValidFieldName($p_newName)) return 0;
		$queryStr = "RENAME TABLE ".$this->m_dbTableName ." TO X".$p_newName;
		$success = $Campsite['db']->Execute($queryStr);
		if ($success) {
			$queryStr = "UPDATE ArticleTypeMetadata SET type_name='X". $p_newName ."' WHERE type_name='". $this->m_dbTableName ."'";
			$success2 = $Campsite['db']->Execute($queryStr);		
		}


		if ($success2) {
			$this->m_dbTableName = 'X'. $p_newName;
			if (function_exists("camp_load_language")) { camp_load_language("api"); }
			$logText = getGS('The article type $1 has been renamed to $2.', $this->m_dbTableName, $p_newName);
			Log::Message($logText, null, 62);
			ParserCom::SendMessage('article_types', 'rename', array('article_type' => $this->m_name));
		}
	
			
	}

	
	/**
	 * @return string
	 */
	function getName($p_languageId) 
	{
		if (is_numeric($p_languageId) && isset($this->m_names[$p_languageId])) {
			return $this->m_names[$p_languageId];;
		} else {
			return "";
		}
	} // fn getName
	
	
	/**
	 * Set the type name for the given language.  A new entry in 
	 * the database will be created if the language does not exist.
	 * 
	 * @param int $p_languageId
	 * @param string $p_value
	 * 
	 * @return boolean
	 */
	function setName($p_languageId, $p_value) 
	{
		global $Campsite;
		if (!is_numeric($p_languageId)) {
			return false;
		}
		
		
		// if the string is empty, nuke it		
		if (!is_string($p_value)) {
			$phase_id = $this->m_metadata['fk_phrase_id'];
			$trans =& new Translation($p_languageId, $phrase_id);
			$trans->delete();
			$sql = "DELETE FROM ArticleTypeMetadata WHERE type_name=". $this->m_dbTableName ." AND fk_phrase_id=". $phrase_id;
			$changed = $Campsite['db']->Execute($sql);
		}
		
		if (isset($this->m_names[$p_languageId])) {
			$description =& new Translation($p_languageId, $this->m_metadata['fk_phrase_id']);
			$description->setText($p_value);
			
			// Update the name.
			$oldValue = $this->m_names[$p_languageId];
			//$sql = "UPDATE ArticleTypeMetadata SET type_name='".$this->m_dbTableName."' "
			//		." WHERE type_name=".$this->m_dbTableName
			//		." AND fk_phrase_id=".$phrase_id;
			//$changed = $Campsite['db']->Execute($sql);
			$changed = true;
		} else {
			// Insert the new translation.
			$description =& new Translation($p_languageId);
			$description->create($p_value);
			$phrase_id = $description->getPhraseId();

			$oldValue = "";
			$sql = "INSERT INTO ArticleTypeMetadata SET type_name='".$this->m_dbTableName ."', fk_phrase_id=".$phrase_id;
			$changed = $Campsite['db']->Execute($sql);			
		}
		if ($changed) {
			$this->m_names[$p_languageId] = $p_value;
			if (function_exists("camp_load_language")) { camp_load_language("api");	}
			$logtext = getGS('Type $1 updated', $this->m_dbTableName.": (".$oldValue. " -> ".$this->m_names[$p_languageId].")");
			Log::Message($logtext, null, 143);		
			//ParserCom::SendMessage('article_types', 'modify', array('article_type' => $this->m_name));
		}
		return $changed;
	} // fn setName
	
	/**
	 * Parses m_metadata for phrase_ids and returns an array of language_id => translation_text
	 *
	 * @return array
	 *
	 */
	function getTranslations() {
		$return = array();
		foreach ($this->m_metadata as $m) {
			if (is_numeric($m['fk_phrase_id'])) {
				$tmp = Translation::getTranslations($m['fk_phrase_id']);
				foreach ($tmp as $k => $v) 
					$return[$k] = $v;
				unset($tmp);
			}
		}	
		return $return;
	}
	
	/**
	 * @return string
	 */
	function getTableName()
	{
		return $this->m_dbTableName;
	} // fn getTableName


	/** 
	* Return an associative array of the metadata in ArticleFieldMetadata.
	*
	**/
	function getMetadata() {
		global $Campsite;
		$queryStr = "SELECT * FROM ArticleTypeMetadata WHERE type_name='". $this->m_dbTableName ."' and field_name IS NULL";
		$queryArray = $Campsite['db']->GetAll($queryStr);
		return $queryArray;
	}
	
	/**
	 * Return an array of ArticleTypeField objects.
	 *
	 * @return array
	 */
	function getUserDefinedColumns()
	{
		global $Campsite;
		#$queryStr = 'SHOW COLUMNS FROM '.$this->m_dbTableName
		#			." LIKE 'F%'";
		$queryStr = "SELECT * FROM ArticleTypeMetadata WHERE type_name='". $this->m_dbTableName ."' AND field_name IS NOT NULL ORDER BY field_weight DESC";
		$queryArray = $Campsite['db']->GetAll($queryStr);
		$metadata = array();
		if (is_array($queryArray)) {
			foreach ($queryArray as $row) {
				$queryStr = "SHOW COLUMNS FROM ". $this->m_dbTableName ." LIKE '". $row['field_name'] ."'";
				$rowdata = $Campsite['db']->GetAll($queryStr);
				$columnMetadata =& new ArticleTypeField($this->m_name);
				$columnMetadata->fetch($rowdata[0]);
				$columnMetadata->m_metadata = $columnMetadata->getMetadata();
				$metadata[] =& $columnMetadata;
			}
		}
		
		return $metadata;
	} // fn getUserDefinedColumns


	/**
	 * Static function.
	 * @param string $p_name
	 * @return boolean
	 */
	function IsValidFieldName($p_name)
	{
		if (empty($p_name)) {
			return false;
		}
		for ($i = 0; $i < strlen($p_name); $i++) {
			$c = $p_name[$i];
			$valid = ($c >= 'A' && $c <= 'Z') || ($c >= 'a' && $c <= 'z') || $c == '_';
			if (!$valid) {
			  return false;
			}
		}
		return true;
	} // fn IsValidFieldName


	/**
	 * Get all article types that currently exist.
	 * Returns an array of strings.
	 *
	 * @return array
	 */
	function GetArticleTypes()
	{
		global $Campsite;
		$queryStr = "SHOW TABLES LIKE 'X%'";
		$tableNames = $Campsite['db']->GetCol($queryStr);
		if (!is_array($tableNames)) {
			$tableNames = array();
		}
		$finalNames = array();
		foreach ($tableNames as $tmpName) {
			$finalNames[] = substr($tmpName, 1);
		}
		return $finalNames;
	} // fn GetArticleTypes

	/**
	 * sets the is_hidden variable
	 */
	function setStatus($p_status) {
		global $Campsite;
		if ($p_status == 'hide') 
			$set = "is_hidden=1";
		if ($p_status == 'show')
			$set = "is_hidden=0";
		$queryStr = "UPDATE ArticleTypeMetadata SET $set WHERE type_name='". $this->getTableName() ."'";
		print $queryStr;
		$ret = $Campsite['db']->Execute($queryStr);
	}

	/**
	*
	* gets the display name of a type; this is based on the native language -- and if no native language translation is available
	* we use dbTableName
	*
	**/
	function getDisplayName() {
		global $_REQUEST;
		$loginLanguageId = 0;
		$loginLanguage = Language::GetLanguages(null, $_REQUEST['TOL_Language']);
		if (is_array($loginLanguage)) {
			$loginLanguage = array_pop($loginLanguage);
			$loginLanguageId = $loginLanguage->getLanguageId();
		}
		$translations = $this->getTranslations();
		if (!isset($translations[$loginLanguageId])) return substr($this->getTableName(), 1);
		else return $translations[$loginLanguageId] .' ('. $loginLanguage->getCode() .')';		
	}
	
} // class ArticleType

?>