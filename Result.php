<?php
/**
 * This file is for connecting to a database server
 * with the odbc driver installed 
 * 
 * @package	CodeIgniter
 * @author	Ronny García Zambrano <rsgarcia0203>
 * @copyright	Copyright (c) 2022, Ronny García
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://github.com/rsgarcia0203/ODBCDriver
 * @since	Version 4.0.1
 */

namespace CodeIgniter\Database\ODBC;

use CodeIgniter\Database\BaseResult;
use CodeIgniter\Entity\Entity;
use stdClass;

/**
 * ODBC Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Ronny García <rsgarcia0203>
 * @link		https://github.com/rsgarcia0203/ODBCDriver
 */
class Result extends BaseResult
{

    /**
     * Gets the number of fields in the result set.
	  * 
	  * @return   int
     */
    public function getFieldCount(): int
    {
		  return odbc_num_fields($this->resultID);
    }

    /**
     * Generates an array of column names in the result set.
	  * 
	  * @return array
     */
    public function getFieldNames(): array
    {
        return array_map(fn ($fieldIndex) => odbc_field_name($this->resultID, $fieldIndex), range(1, $this->getFieldCount()));
    }

    /**
     * Generates an array of objects representing field meta-data.
	  * 
	  * @return array
     */
    public function getFieldData(): array
    {
        return array_map(fn ($fieldIndex) => (object) [
            'name'          => odbc_field_name($this->resultID, $fieldIndex),
            'type'          => odbc_field_type($this->resultID, $fieldIndex),
            'max_length'    => odbc_field_len($this->resultID, $fieldIndex),
        ], range(1, $this->getFieldCount()));
    }

    /**
     * Frees the current result
     *
     * @return void
     */
    public function freeResult()
    {
        if (is_resource($this->resultID))
        {
            odbc_free_result($this->resultID);
            $this->resultID = FALSE;
        }
    }

    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return false
     */
    public function dataSeek(int $n = 0)
    {
        // Don´t support Data Seek in ODBC
        return false;
    }

    /**
     * Returns the result set as an array.
     *
     * Overridden by driver classes.
     *
     * @return mixed
     */
    protected function fetchAssoc()
    {
        return odbc_fetch_array($this->resultID);
    }

    /**
     * Returns the result set as an object.
     *
     * Overridden by child classes.
     *
     * @return bool|Entity|object
     */
    protected function fetchObject(string $className = 'stdClass')
    {
        $row = odbc_fetch_object($this->resultID);

        if ($className === 'stdClass' OR ! $row)
        {
            return $row;
        }

        if (is_subclass_of($className, Entity::class)) {
            return (new $className())->setAttributes((array) $row);
        }

        $instance = new $className();
        foreach (get_object_vars($row) as $key => $value)
        {
            $instance->{$key} = $value;
        }

        return $instance;
    }
}

// --------------------------------------------------------------------

if ( ! function_exists('odbc_fetch_array'))
{
	/**
	 * ODBC Fetch array
	 *
	 * Emulates the native odbc_fetch_array() function when
	 * it is not available (odbc_fetch_array() requires unixODBC)
	 *
	 * @param	resource	&$result
	 * @param	int		$rownumber
	 * @return	array|bool
	 */
	function odbc_fetch_array(&$result, $rownumber = 1)
	{
		$rs = array();
		if ( ! odbc_fetch_into($result, $rs, $rownumber))
		{
			return FALSE;
		}

		$rs_assoc = array();
		foreach ($rs as $k => $v)
		{
			$field_name = odbc_field_name($result, $k+1);
			$rs_assoc[$field_name] = $v;
		}

		return $rs_assoc;
	}
}

// --------------------------------------------------------------------

if ( ! function_exists('odbc_fetch_object'))
{
	/**
	 * ODBC Fetch object
	 *
	 * Emulates the native odbc_fetch_object() function when
	 * it is not available.
	 *
	 * @param	resource	&$result
	 * @param	int		$rownumber
	 * @return	object|bool
	 */
	function odbc_fetch_object(&$result, $rownumber = 1)
	{
		$rs = array();
		if ( ! odbc_fetch_into($result, $rs, $rownumber))
		{
			return FALSE;
		}

		$rs_object = new stdClass();
		foreach ($rs as $k => $v)
		{
			$field_name = odbc_field_name($result, $k+1);
			$rs_object->$field_name = $v;
		}

		return $rs_object;
	}
}
