<?php

/**
 * MIT License (MIT)
 *
 * Copyright (c) 2013 Beno!t POLASZEK
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * PDOExtended class
 * @author Beno!t POLASZEK - 2013
 */

namespace PDOExtended;
use PDO;

/**
 * PDOExtended class
 * @author Beno!t POLASZEK - 2013
 */
Class PDOExtended {

    protected       $PDO;
    protected       $dsn;
    protected       $username;
    protected       $password;
    protected       $driver_options;
    protected       $isConnected        =   false;
    protected       $isPaused           =   false;
    protected       $storeStmts         =   true;
    protected       $storedStmts        =   array();
    protected       $latestStmt;

    const           TO_ARRAY_ASSOC      =    1;
    const           TO_ARRAY_INDEX      =    2;
    const           TO_STRING           =    3;
    const           TO_STDCLASS         =    4;

    /**
     * Constructor
     * @link http://php.net/manual/en/pdo.construct.php
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function __construct($dsn = null, $username = null, $password = null, $driver_options = null, $connect = true) {

        # Setting properties
        $this    ->    dsn              =    $dsn;
        $this    ->    username         =    $username;
        $this    ->    password         =    $password;
        $this    ->    driver_options   =    $driver_options;

        # Autoconnect
        if ((bool) $connect)
            $this->connect();

    }

    /**
     * Constructor alias - useful for chaining
     * Example : $Status = PDOExtended::NewInstance('mysql:host=localhost', 'user', 'password')->sqlMultiAssoc("SHOW GLOBAL STATUS");
     */
    public static function NewInstance() {
        $CurrentClass    =    new \ReflectionClass(get_called_class());
        return $CurrentClass->NewInstanceArgs(func_get_args());
    }

    /**
     * Returns a new instance with an existing PDO object
     */
    public static function NewInstanceFromPdo(\PDO $PDO, $driver_options = null) {
        return static::NewInstance(null, null, null, $driver_options, false)->connect($PDO);
    }

    /**
     * Destructor : disconnection
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Magic Shortcut to PDO object methods
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function __call($name, array $args) {

        # If the connection was paused, we have to reconnect
        !$this->isPaused OR $this->reconnect();

        if (!($this->PDO instanceof \PDO))
            throw new \PDOException("PDO Connection isn't active.");

        return call_user_func_array(array($this->PDO, $name), $args);
    }

    /**
     * Checks if connection is active
     *
     * @return bool true / false
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function ping() {

        if ($this->isPaused)
            return false;

        try {
            return (bool) $this->query("SELECT 1+1");
        }
        catch (Exception $e) {
            return false;
        }
    }

    /**
     * Closes the current connection
     * Next calls (query, prepare, etc) will throw an exception unless you use reconnect() method
     *
     * @return PDOExtended instance
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function disconnect() {
        $this    ->    PDO                =    null;
        $this    ->    isConnected        =    false;
        return $this;
    }

    /**
     * Connects to the database
     * Optionnally, you can specify an existing PDO object instance to use, so as to avoid multiple connections
     * Example usage :
     *      $Pdo =   new \PDO('mysql:host=localhost', 'user', 'password');
     *      $PdoExtended =   new PDOExtended\PdoExtended(null, null, null, null, FALSE); // Disables autoconnect
     *      $PdoExtended->connect($Pdo);
     *      $PdoExtended->sqlColumn('SHOW TABLES');
     *
     * @param PDO $PDO
     * @return current instance
     */
    public function connect(\PDO $PDO = null) {

        if ($PDO instanceof \PDO) {
            $this->PDO      =   $PDO;
        }

        else {
            # Creating PDO instance into $this->PDO
            $class          =   new \ReflectionClass('\PDO');
            $this->PDO      =   $class->NewInstanceArgs([$this->dsn, $this->username, $this->password, $this->driver_options]);
        }

        $this->PDO          ->  setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->PDO          ->  setAttribute(PDO::ATTR_STATEMENT_CLASS, Array(__NAMESPACE__ . '\PDOStatementExtended'));

        # Checking PDO connection
        $this->isConnected  =   $this->ping();

        return $this;
    }

    /*
     * Returns the PDO instance for use in other applications
     *
     * @return \PDO instance
     * @access public
     */
    public function getPdo() {
        return $this->PDO;
    }

    /**
     * Pauses the current connection (disconnects temporarily)
     * The connexion will be closed but reopened at the next call (query, prepare, sql etc)
     *
     * @return PDOExtended instance
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function pause() {
        $this    ->    disconnect();
        $this    ->    isPaused    =    true;
        return $this;
    }


    /**
     * Re-opens the connection with the same dsn / username / passwd etc
     *
     * @return PDOExtended instance
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function reconnect() {
        self::__construct($this->dsn, $this->username, $this->password, $this->driver_options);
        return $this;
    }

    /**
     * Stores the latest statement
     *
     * @param \PDOStatement $Stmt
     * @return $this - current instance
     * @access protected
     * @author Beno!t POLASZEK -  2013
     */
    protected function setLatestStmt(\PDOStatement $Stmt) {
        $this->latestStmt    =    $Stmt;
        return $this;
    }

    /**
     * Retrieves the latest statement
     *
     * @return \PDOStatement
     * @access public
     * @author Beno!t POLASZEK -  2013
     */
    public function getLatestStmt() {
        return $this->latestStmt;
    }

    /**
     * Enables / Disables Statement storage
     *
     * @param bool $bool
     * @return $this - current instance
     */
    public function storeStmts($bool = true) {

        $this->storeStmts              =   (bool) $bool;

        # Clear stored statements
        if (!$bool)
            $this->storedStmts         =   array();

        return $this;
    }

    /**
     * Prepares a SQL Statement
     *
     * @param string $sqlString : SQL query
     * @param array $sqlValues : Optional PDO Values to bind
     * @param array $driver_options
     * @return PDOStatementExtended stmt
     * @access public
     * @author Beno!t POLASZEK - 2013
     */
    public function prepare($sqlString, $sqlValues = array(), $driver_options = array()) {

        # If the connection was paused, we have to reconnect
        !$this->isPaused OR $this->reconnect();

        if (!($this->PDO instanceof \PDO))
            throw new \PDOException("PDO Connection isn't active.");

        # If stored statements are enabled
        if ($this->storeStmts && !is_object($sqlString)) {
            $SqlFootPrint   =   md5($sqlString);
            if (!array_key_exists($SqlFootPrint, $this->storedStmts))
                $this->storedStmts[$SqlFootPrint]   =   $this->PDO->prepare($sqlString, $driver_options);

            $Stmt           =   $this->storedStmts[$SqlFootPrint];
        }

        elseif (!$this->storeStmts && $sqlString instanceof \PDOStatement)
            $Stmt           =   $sqlString;

        # The SQL Query becomes a SQL Statement
        else
            $Stmt    =    $this->PDO->prepare($sqlString, $driver_options);

        if ($Stmt instanceof \PDOStatement)
            $this->setLatestStmt($Stmt);

        if (empty($sqlValues))
            return $Stmt;

        # If values have been provided, let's bind them
        else
            $Stmt->bindValues($sqlValues);

        return $Stmt;
    }

    /**
     * Prepares a SQL Statement and executes it
     * @param $sqlString
     * @param array $sqlValues
     * @param array $driver_options
     * @return PDOStatementExtended|\PDOStatement
     * @throws StmtException
     * @throws \PDOException
     */
    public function sql($sqlString, $sqlValues = array(), $driver_options = array()) {

        # If the connection was paused, we have to reconnect
        !$this->isPaused OR $this->reconnect();

        if (!($this->PDO instanceof \PDO))
            throw new \PDOException("PDO Connection isn't active.");

        # If sqlString isn't a PDOStatement yet
        $stmt    =    ($sqlString instanceof \PDOStatement) ? $sqlString : $this->prepare($sqlString, $sqlValues, $driver_options);

        if ($stmt instanceof \PDOStatement)
            $this->setLatestStmt($stmt);

        # If values have been provided, let's bind them
        if (!empty($sqlValues))
            $stmt->bindValues($sqlValues);

        # Execution
        try {
            $stmt->execute();
        }

            # Custom PDO Exception, allowing query preview
        catch (\PDOException $PDOException) {
            throw new StmtException((string) $PDOException->getMessage(), $PDOException->getCode(), $PDOException, $stmt->debug());
        }

        # The statement is executed. You can now use Fetch() and FetchAll() methods.
        return $stmt;
    }

    /**
     * sqlArray executes Query : returns the whole result set
     *
     * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
     * @param array $sqlValues : Optional PDO Values to bind
     * @return Array
     */
    public function sqlArray($sqlString, $sqlValues = array()) {
        return $this->sql($sqlString, $sqlValues)->FetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * sqlRow executes Query : returns the 1st row of your result set
     *
     * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
     * @param array $sqlValues : Optional PDO Values to bind
     * @return Array
     */
    public function sqlRow($sqlString, $sqlValues = array()) {
        return $this->sql($sqlString, $sqlValues)->Fetch(PDO::FETCH_ASSOC);
    }

    /**
     * sqlValues executes Query : returns the 1st column of your result set
     *
     * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
     * @param array $sqlValues : Optional PDO Values to bind
     * @return Array
     */
    public function sqlColumn($sqlString, $sqlValues = array()) {
        return $this->sql($sqlString, $sqlValues)->FetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * sqlValue executes Query : returns the 1st cell of your result set
     *
     * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
     * @param array $sqlValues : Optional PDO Values to bind
     * @return String
     */
    public function sqlValue($sqlString, $sqlValues = array()) {
        return $this->sql($sqlString, $sqlValues)->Fetch(PDO::FETCH_COLUMN);
    }


    /**
     * sqlAssoc executes Query :
     * If $dataType == self::TO_STRING : returns an associative array where the 1st column is the key and the 2nd is the value
     * If $dataType == self::TO_STDCLASS : returns an associative array where the 1st column is the key the others are properties of an anonymous object
     * If $dataType == self::TO_ARRAY_ASSOC : returns an associative array where the 1st column is the key the others are an associative array
     * If $dataType == self::TO_ARRAY_INDEX : returns an associative array where the 1st column is the key the others are an indexed array
     *
     * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
     * @param array $sqlValues : PDO Values to bind
     * @param int $dataType : type of data wanted
     * @return Array
     */
    public function sqlAssoc($sqlString, $sqlValues = array(), $dataType = self::TO_STRING) {
        $Data    =    $this->sql($sqlString, $sqlValues)->Fetch(PDO::FETCH_ASSOC);

        if ($Data) :

            $Keys       =    array_keys($Data);

            if ($dataType == self::TO_STDCLASS)
                $Result    =    Array($Data[$Keys[0]] => (object) array_slice($Data, 1));

            elseif ($dataType == self::TO_ARRAY_ASSOC)
                $Result    =    Array($Data[$Keys[0]] => array_slice($Data, 1));

            elseif ($dataType == self::TO_ARRAY_INDEX)
                $Result    =    Array($Data[$Keys[0]] => array_values(array_slice($Data, 1)));

            else // $dataType == self::TO_STRING by default
                $Result    =    Array($Data[$Keys[0]] => $Data[$Keys[1]]);

            return $Result;

        else :
            return $Data;

        endif;

    }


    /**
     * sqlMultiAssoc executes Query :
     * If $dataType == self::TO_STRING : returns an associative array where the 1st column is the key and the 2nd is the value
     * If $dataType == self::TO_STDCLASS : returns an associative array where the 1st column is the key the others are properties of an anonymous object
     * If $dataType == self::TO_ARRAY_ASSOC : returns an associative array where the 1st column is the key the others are an associative array
     * If $dataType == self::TO_ARRAY_INDEX : returns an associative array where the 1st column is the key the others are an indexed array
     *
     * @param mixed $sqlString : SQL Query as a string or a PDOStatementExtended
     * @param array $sqlValues : PDO Values to bind
     * @param int $dataType : type of data wanted
     * @return Array
     */
    public function sqlMultiAssoc($sqlString, $sqlValues = array(), $dataType = self::TO_STRING) {
        $data    =    $this->sql($sqlString, $sqlValues)->FetchAll(PDO::FETCH_ASSOC);

        if (array_key_exists(0, $data)) :

            $keys    =    array_keys($data[0]);
            $result    =    Array();

            foreach ($data AS $item)

                if ($dataType == self::TO_STDCLASS)
                    $result[]    =    Array($item[$keys[0]] => (object) array_slice($item, 1));

                elseif ($dataType == self::TO_ARRAY_ASSOC)
                    $result[]    =    Array($item[$keys[0]] => array_slice($item, 1));

                elseif ($dataType == self::TO_ARRAY_INDEX)
                    $result[]    =    Array($item[$keys[0]] => array_values(array_slice($item, 1)));

                else // $dataType == self::TO_STRING by default
                    $result[]    =    Array($item[$keys[0]] => $item[$keys[1]]);

            return $result;

        else :
            return $data;

        endif;

    }

    /**
     * Prevents from XSS injection
     * @param $input
     * @param bool $scriptTags
     * @param bool $styleTags
     * @param bool $multiLineComments
     * @return string
     */
    public static function CleanInput($input, $scriptTags = true, $styleTags = true, $multiLineComments = true) {

        $RemovePatterns         =       Array();

        if ((bool) $scriptTags)
            $RemovePatterns[]   =    '@<script[^>]*?>.*?</script>@si'; // Strip out javascript

        if ((bool) $styleTags)
            $RemovePatterns[]   =    '@<style[^>]*?>.*?</style>@siU';  // Strip style tags properly

        if ((bool) $multiLineComments)
            $RemovePatterns[]   =    '@<![\s\S]*?--[ \t\n\r]*>@';      // Strip multi-line comments

        return preg_replace($RemovePatterns, null, $input);
    }

}