<?php
/**
 * @package Campsite
 */

/**
 * Includes
 */
require_once($GLOBALS['g_campsiteDir'].'/db_connect.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/DatabaseObject.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/DbObjectArray.php');
/**
 * @package Campsite
 */

class ContextBoxArticle extends DatabaseObject
{
    var $m_dbTableName = 'context_articles';

    var $m_keyColumnNames = array('fk_context_id', 'fk_article_no');

    var $m_columnNames = array('fk_context_id', 'fk_article_no');

    public function __construct($p_context_id = null, $fk_article_no = null) {
        parent::__construct($this->m_columnNames);
    }

    public static function saveList($p_context_id, $p_article_no_array) {
        self::removeList($p_context_id);
        self::insertList($p_context_id, array_unique($p_article_no_array));
    }

    public static function removeList($p_context_id) {
    	Global $g_ado_db;
        $queryStr = 'DELETE FROM context_articles'
                    .' WHERE fk_context_id=' . $p_context_id.'';
        $g_ado_db->Execute($queryStr);
        $wasDeleted = ($g_ado_db->Affected_Rows());
        return $wasDeleted;
    }

    public static function insertList($p_context_id, $p_article_no_array) {
    	Global $g_ado_db;
    	foreach($p_article_no_array as $p_article_no) {
    		$queryStr = 'INSERT INTO context_articles'
    		          . ' VALUES ('.$p_context_id.','.$p_article_no.')';
    		$g_ado_db->Execute($queryStr);
    	}
    }


    /**
     * Gets an issues list based on the given parameters.
     *
     * @param integer $p_context_id
     *    The Context Box Identifier
     * @param string $p_order
     *    An array of columns and directions to order by
     * @param integer $p_start
     *    The record number to start the list
     * @param integer $p_limit
     *    The offset. How many records from $p_start will be retrieved.
     * @param integer $p_count
     *    The total count of the elements; this count is computed without
     *    applying the start ($p_start) and limit parameters ($p_limit)
     *
     * @return array $issuesList
     *    An array of Issue objects
     */
    public static function GetList($p_context_id, $p_order = null,
    $p_start = 0, $p_limit = 0, &$p_count, $p_skipCache = false)
    {
        global $g_ado_db;

        if (!$p_skipCache && CampCache::IsEnabled()) {
            $paramsArray['parameters'] = serialize($p_parameters);
            $paramsArray['order'] = (is_null($p_order)) ? 'null' : $p_order;
            $paramsArray['start'] = $p_start;
            $paramsArray['limit'] = $p_limit;
            $cacheListObj = new CampCacheList($paramsArray, __METHOD__);
            $issuesList = $cacheListObj->fetchFromCache();
            if ($issuesList !== false && is_array($issuesList)) {
                return $issuesList;
            }
        }

        $returnArray = array();
        $queryStr = '
           SELECT fk_article_no FROM context_articles'.
           ' WHERE fk_context_id='.$p_context_id
        ;
        $rows = $g_ado_db->GetAll($queryStr);
        if(is_array($rows)) {
            foreach($rows as $row) {
                $returnArray[] = $row['fk_article_no'];
            }
        }

        $p_count = count($returnArray);
        return array_reverse($returnArray);
    }


	/**
	 * Remove the article from any related articles list.
	 * @param int $articleNumber
	 * @return void
	 */
    public static function OnArticleDelete($articleNumber)
    {
		global $g_ado_db;

		$articleNumber = (int)$articleNumber;
		if ($articleNumber < 1) {
		    return;
		}

		$queryStr = 'DELETE FROM context_articles'
					." WHERE fk_article_no = '$articleNumber'";
		$g_ado_db->Execute($queryStr);
    }


	/**
	 * Remove the given context box articles.
	 * @param int $contextBoxId
	 * @return void
	 */
    public static function OnContextBoxDelete($contextBoxId)
    {
		global $g_ado_db;

		$contextBoxId = (int)$contextBoxId;
		if ($contextBoxId < 1) {
		    return;
		}

		$queryStr = 'DELETE FROM context_articles'
					." WHERE fk_context_id = '$contextBoxId'";
		$g_ado_db->Execute($queryStr);
    }
}