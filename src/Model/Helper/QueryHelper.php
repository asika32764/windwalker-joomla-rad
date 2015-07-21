<?php
/**
 * Part of Windwalker project.
 *
 * @copyright  Copyright (C) 2011 - 2014 SMS Taiwan, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Windwalker\Model\Helper;

use JDatabaseDriver as DatabaseDriver;
use JDatabaseQuery as Query;
use JDatabaseQueryElement as QueryElement;
use Windwalker\Compare\Compare;
use Windwalker\DI\Container;
use Windwalker\Facade\AbstractFacade;
use Windwalker\Helper\DateHelper;
use Windwalker\Joomla\Database\DatabaseFactory;

/**
 * The Query Helper
 *
 * @since 2.0
 */
class QueryHelper extends AbstractFacade
{
	/**
	 * Property db.
	 *
	 * @var  DatabaseDriver
	 */
	protected $db = null;

	/**
	 * Property tables.
	 *
	 * @var  array
	 */
	protected $tables = array();

	/**
	 * The DI key to get data from container.
	 *
	 * @return  string
	 */
	public static function getDIKey()
	{
		return 'db';
	}

	/**
	 * Constructor.
	 *
	 * @param DatabaseDriver $db
	 */
	public function __construct(DatabaseDriver $db = null)
	{
		$this->db = $db ? : $this->getDb();
	}

	/**
	 * addTable
	 *
	 * @param string  $alias
	 * @param string  $table
	 * @param mixed   $condition
	 * @param string  $joinType
	 * @param boolean $prefix
	 *
	 * @return  QueryHelper
	 */
	public function addTable($alias, $table, $condition = null, $joinType = 'LEFT', $prefix = null)
	{
		$tableStorage = array();

		$tableStorage['name'] = $table;
		$tableStorage['join']  = strtoupper($joinType);

		if (is_array($condition))
		{
			$condition = array($condition);
		}

		if ($condition)
		{
			$condition = (string) new QueryElement('ON', $condition, ' AND ');
		}
		else
		{
			$tableStorage['join'] = 'FROM';
		}

		// Remove too many spaces
		$condition = preg_replace('/\s(?=\s)/', '', $condition);

		$tableStorage['condition'] = trim($condition);
		$tableStorage['prefix'] = $prefix;

		$this->tables[$alias] = $tableStorage;

		return $this;
	}

	/**
	 * removeTable
	 *
	 * @param string $alias
	 *
	 * @return  $this
	 */
	public function removeTable($alias)
	{
		if (!empty($this->tables[$alias]))
		{
			unset($this->tables[$alias]);
		}

		return $this;
	}

	/**
	 * getFilterFields
	 *
	 * @return  array
	 */
	public function getSelectFields()
	{
		$fields = array();

		$i = 0;

		foreach ($this->tables as $alias => $table)
		{
			$columns = DatabaseFactory::getCommand()->getColumns($table['name']);

			foreach ($columns as $column)
			{
				$prefix = $table['prefix'];

				if ($i === 0)
				{
					$prefix = $prefix === null ? false : true;
				}
				else
				{
					$prefix = $prefix === null ? true : false;
				}

				if ($prefix === true)
				{
					$fields[] = $this->db->quoteName("{$alias}.{$column} AS {$alias}_{$column}");
				}
				else
				{
					$fields[] = $this->db->quoteName("{$alias}.{$column} AS {$column}");
				}
			}

			$i++;
		}

		return $fields;
	}

	/**
	 * registerQueryTables
	 *
	 * @param Query $query
	 *
	 * @return  Query
	 */
	public function registerQueryTables(Query $query)
	{
		foreach ($this->tables as $alias => $table)
		{
			if ($table['join'] == 'FROM')
			{
				$query->from($query->quoteName($table['name']) . ' AS ' . $query->quoteName($alias));
			}
			else
			{
				$query->join(
					$table['join'],
					$query->quoteName($table['name']) . ' AS ' . $query->quoteName($alias) . ' ' . $table['condition']
				);
			}
		}

		return $query;
	}

	/**
	 * buildConditions
	 *
	 * @param Query $query
	 * @param array         $conditions
	 *
	 * @return  Query
	 */
	public static function buildWheres(Query $query, array $conditions)
	{
		foreach ($conditions as $key => $value)
		{
			if (empty($value))
			{
				continue;
			}

			// If using Compare class, we convert it to string.
			if ($value instanceof Compare)
			{
				$query->where((string) static::buildCompare($key, $value, $query));
			}

			// If key is numeric, just send value to query where.
			elseif (is_numeric($key))
			{
				$query->where((string) $value);
			}

			// If is array or object, we use "IN" condition.
			elseif (is_array($value) || is_object($value))
			{
				$value = array_map(array($query, 'quote'), (array) $value);

				$query->where($query->quoteName($key) . new QueryElement('IN ()', $value, ','));
			}

			// Otherwise, we use equal condition.
			else
			{
				$query->where($query->format('%n = %q', $key, $value));
			}
		}

		return $query;
	}

	/**
	 * buildCompare
	 *
	 * @param string|int  $key
	 * @param Compare     $value
	 * @param Query       $query
	 *
	 * @return  string
	 */
	public static function buildCompare($key, Compare $value, $query = null)
	{
		$query = $query ? : DatabaseFactory::getDbo()->getQuery(true);

		if (!is_numeric($key))
		{
			$value->setCompare1($key);
		}

		$value->setHandler(
			function($compare1, $compare2, $operator) use ($query)
			{
				return $query->format('%n ' . $operator . ' %q', $compare1, $compare2);
			}
		);

		return (string) $value;
	}

	/**
	 * getDb
	 *
	 * @return  DatabaseDriver
	 */
	public function getDb()
	{
		if (!$this->db)
		{
			$this->db = DatabaseFactory::getDbo();
		}

		return $this->db;
	}

	/**
	 * setDb
	 *
	 * @param   DatabaseDriver $db
	 *
	 * @return  QueryHelper  Return self to support chaining.
	 */
	public function setDb($db)
	{
		$this->db = $db;

		return $this;
	}

	/**
	 * Filter fields.
	 *
	 * @return  array Filter fields.
	 */
	public function getFilterFields()
	{
		$fields = array();

		foreach ($this->tables as $alias => $table)
		{
			$columns = DatabaseFactory::getCommand()->getColumns($table['name']);

			foreach ($columns as $key => $var)
			{
				$fields[] = "{$alias}.{$key}";
			}
		}

		return $fields;
	}

	/**
	 * Get a query string to filter the publishing items now.
	 *
	 * Will return: '( publish_up < 'xxxx-xx-xx' OR publish_up = '0000-00-00' )
	 *   AND ( publish_down > 'xxxx-xx-xx' OR publish_down = '0000-00-00' )'
	 *
	 * @param   string $prefix Prefix to columns name, eg: 'a.' will use `a`.`publish_up`.
	 *
	 * @return  string Query string.
	 */
	public static function publishingPeriod($prefix = '')
	{
		$db       = Container::getInstance()->get('db');
		$nowDate  = $date = DateHelper::getDate()->toSQL();
		$nullDate = $db->getNullDate();

		$date_where = " ( {$prefix}publish_up < '{$nowDate}' OR  {$prefix}publish_up = '{$nullDate}') AND " .
			" ( {$prefix}publish_down > '{$nowDate}' OR  {$prefix}publish_down = '{$nullDate}') ";

		return $date_where;
	}

	/**
	 * Get a query string to filter the publishing items now, and the published > 0.
	 *
	 * Will return: `( publish_up < 'xxxx-xx-xx' OR publish_up = '0000-00-00' )
	 *    AND ( publish_down > 'xxxx-xx-xx' OR publish_down = '0000-00-00' )
	 *    AND published >= '1' `
	 *
	 * @param   string $prefix        Prefix to columns name, eg: 'a.' will use `a.publish_up`.
	 * @param   string $published_col The published column name. Usually 'published' or 'state' for com_content.
	 *
	 * @return  string  Query string.
	 */
	public static function publishingItems($prefix = '', $published_col = 'published')
	{
		return self::publishingPeriod($prefix) . " AND {$prefix}{$published_col} >= '1' ";
	}

	/**
	 * Simple highlight for SQL queries.
	 *
	 * @param   string  $query  The query to highlight.
	 *
	 * @return  string  Highlighted query string.
	 */
	public static function highlightQuery($query)
	{
		$newlineKeywords = '#\b(FROM|LEFT|INNER|OUTER|WHERE|SET|VALUES|ORDER|GROUP|HAVING|LIMIT|ON|AND|CASE)\b#i';

		$query = htmlspecialchars($query, ENT_QUOTES);

		$query = preg_replace($newlineKeywords, '<br />&#160;&#160;\\0', $query);

		$regex = array(

			// Tables are identified by the prefix.
			'/(=)/'
			=> '<strong class="text-error">$1</strong>',

			// All uppercase words have a special meaning.
			'/(?<!\w|>)([A-Z_]{2,})(?!\w)/x'
			=> '<span class="text-info">$1</span>',

			// Tables are identified by the prefix.
			'/(' . \JFactory::getDbo()->getPrefix() . '[a-z_0-9]+)/'
			=> '<span class="text-success">$1</span>'

		);

		$query = preg_replace(array_keys($regex), array_values($regex), $query);

		$query = str_replace('*', '<strong style="color: red;">*</strong>', $query);

		return $query;
	}
}
