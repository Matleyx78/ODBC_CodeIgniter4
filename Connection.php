<?php

namespace CodeIgniter\Database\ODBC;

use \CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;
use stdClass;

class Connection extends BaseConnection
{

    /**
     * Database driver
     *
     * @var	string
     */
    public $dbdriver = 'odbc';

    /**
     * Database name
     *
     * @var string
     */
    public $database;

    /**
     * Identifier escape character
     *
     * Must be empty for ODBC.
     *
     * @var	string
     */
    public $escapeChar = '';

    /**
     * Database schema
     *
     * @var	string
     */
    public string $schema = 'dbo';

    /**
     * ODBC result ID resource returned from odbc_prepare()
     *
     * @var	resource
     */
    private $odbc_result;

    /**
     * Values to use with odbc_execute() for prepared statements
     *
     * @var	array
     */
    private array $binds = [];

    /**
     * ESCAPE statement string
     *
     * @var	string
     */
    protected string $_like_escape_str = " {escape '%s'} ";

    /**
     * Class constructor
     *
     * @param array $params
     * @return	void
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Legacy support for DSN in the hostname field
        if (empty($this->DSN))
        {
            $this->DSN = $this->hostname;
        }
    }

    /**
     * Connect to the database.
     *
     * @param bool $persistent
     *
     * @return bool|resource
     * @throws DatabaseException
     *
     */
    public function connect(bool $persistent = false)
    {
        return ($persistent === TRUE)
            ? odbc_pconnect("Driver={SQL Server}; Server=". $this->DSN ."; Database=". $this->database .";", $this->username, $this->password)
            : odbc_connect("Driver={SQL Server}; Server=". $this->DSN ."; Database=". $this->database .";", $this->username, $this->password);
    }

    /**
     * Close DB Connection
     *
     * @return void
     */
    protected function _close(): void
    {
        odbc_close($this->connID);
    }

    /**
     * Keep or establish the connection if no queries have been sent for
     * a length of time exceeding the server's idle timeout.
     *
     * @return void
     */
    public function reconnect():void
    {
        $this->close();
        $this->initialize();
    }

    /**
     * Select a specific database table to use.
     *
     * @param string|null $databaseName
     *
     * @return bool
     */
    public function setDatabase(?string $databaseName = null)
    {
        if (empty($databaseName)) {
            $databaseName = $this->database;
        }

        if (empty($this->connID)) {
            $this->initialize();
        }

        if ($this->execute('USE ' . $this->_escapeString($databaseName))) {
            $this->database  = $databaseName;
            $this->dataCache = [];

            return true;
        }

        return false;
    }

    /**
     * Returns a string containing the version of the database being used.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return odbc_data_source($this->connID, SQL_FETCH_FIRST);
    }

    /**
     * Execute the query
     *
     * @param string $sql an SQL query
     * @return resource|bool
     */
    protected function execute(string $sql)
    {
        if ( ! isset($this->odbc_result))
        {
            return odbc_exec($this->connID, $sql);
        }
        elseif (!$this->odbc_result)
        {
            return FALSE;
        }

        if (TRUE === ($success = odbc_execute($this->odbc_result, $this->binds)))
        {
            // For queries that return result sets, return the result_id resource on success
            $this->isWriteType($sql) OR $success = $this->odbc_result;
        }

        $this->odbc_result = NULL;
        $this->binds       = array();

        return $success;
    }

    /**
     * Begin Transaction
     *
     * @return bool
     */
    protected function _transBegin(): bool
    {
        return odbc_autocommit($this->connID, FALSE);
    }

    /**
     * Commit Transaction
     *
     * @return	bool
     */
    protected function _transCommit(): bool
    {
        if (odbc_commit($this->connID))
        {
            odbc_autocommit($this->connID, TRUE);
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Rollback Transaction
     *
     * @return	bool
     */
    protected function _transRollback(): bool
    {
        if (odbc_rollback($this->connID))
        {
            odbc_autocommit($this->connID, TRUE);
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Affected Rows
     *
     * @return	int
     */
    public function affectedRows(): int
    {
        return odbc_num_rows($this->resultID);
    }

    /**
     * Returns an array containing code and message of the last
     * database error that has occurred.
     *
     * @return array
     */
    public function error(): array
    {
        return ['code' => odbc_error($this->connID), 'message' => odbc_errormsg($this->connID)];
    }

    /**
     * Insert ID
     *
     * @return	bool
     */
    public function insertID(): bool
    {
        /*Unsupported feature in ODBC*/
        return false;
    }

    /**
     * Show table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @param bool $constrainByPrefix
     * @return	string
     */
    protected function _listTables(bool $constrainByPrefix = false, ?string $tableName = null): string
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '".$this->schema."'";

        if ($constrainByPrefix !== FALSE && $this->dbprefix !== '')
        {
            return $sql." AND table_name LIKE '".$this->escape_like_str($this->dbprefix)."%' "
                .sprintf($this->_like_escape_str, $this->_like_escape_chr);
        }

        return $sql;
    }

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @param string $table
     * @return	string
     */
    protected function _listColumns(string $table = ''): string
    {
        return 'SHOW COLUMNS FROM '.$table;
    }

    /**
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @param	string	$table
     * @return	string | array
     */
    protected function _fieldData(string $table): array
    {
        return "SELECT TOP 1 from $table";
    }

    /**
     * Returns an array of objects with index data
     *
     * @return stdClass[]
     * @throws DatabaseException|Exception
     *
     */
    protected function _indexData(string $table): array
    {
        $sql = 'EXEC sp_helpindex ' . $this->escape($this->schema . '.' . $table);

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException(lang('Database.failGetIndexData'));
        }
        $query = $query->getResultObject();

        $retVal = [];

        foreach ($query as $row) {
            $obj       = new stdClass();
            $obj->name = $row->index_name;

            $_fields     = explode(',', trim($row->index_keys));
            $obj->fields = array_map(static fn ($v) => trim($v), $_fields);

            if (strpos($row->index_description, 'primary key located on') !== false) {
                $obj->type = 'PRIMARY';
            } else {
                $obj->type = (strpos($row->index_description, 'nonclustered, unique') !== false) ? 'UNIQUE' : 'INDEX';
            }

            $retVal[$obj->name] = $obj;
        }

        return $retVal;
    }

    /**
     * Returns an array of objects with Foreign key data
     * referenced_object_id  parent_object_id
     *
     * @param string $table
     *
     * @return stdClass[]
     * @throws DatabaseException|Exception
     *
     */
    protected function _foreignKeyData(string $table): array
    {
        $sql = 'SELECT '
            . 'f.name as constraint_name, '
            . 'OBJECT_NAME (f.parent_object_id) as table_name, '
            . 'COL_NAME(fc.parent_object_id,fc.parent_column_id) column_name, '
            . 'OBJECT_NAME(f.referenced_object_id) foreign_table_name, '
            . 'COL_NAME(fc.referenced_object_id,fc.referenced_column_id) foreign_column_name '
            . 'FROM  '
            . 'sys.foreign_keys AS f '
            . 'INNER JOIN  '
            . 'sys.foreign_key_columns AS fc  '
            . 'ON f.OBJECT_ID = fc.constraint_object_id '
            . 'INNER JOIN  '
            . 'sys.tables t  '
            . 'ON t.OBJECT_ID = fc.referenced_object_id '
            . 'WHERE  '
            . 'OBJECT_NAME (f.parent_object_id) = ' . $this->escape($table);

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException(lang('Database.failGetForeignKeyData'));
        }

        $query  = $query->getResultObject();
        $retVal = [];

        foreach ($query as $row) {
            $obj = new stdClass();

            $obj->constraint_name     = $row->constraint_name;
            $obj->table_name          = $row->table_name;
            $obj->column_name         = $row->column_name;
            $obj->foreign_table_name  = $row->foreign_table_name;
            $obj->foreign_column_name = $row->foreign_column_name;

            $retVal[] = $obj;
        }

        return $retVal;
    }

    /**
     * Determines if a query is a "write" type.
     *
     * Overrides BaseConnection::isWriteType, adding additional read query types.
     *
     * @param mixed $sql
     *
     * @return bool
     */
    public function isWriteType($sql): bool
    {
        if (preg_match('#^(INSERT|UPDATE).*RETURNING\s.+(\,\s?.+)*$#is', $sql))
        {
            return FALSE;
        }

        return parent::isWriteType($sql);
    }
}