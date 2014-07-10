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

trait PDOExtendedSingleton {
    /**
     * Current Instance
     */
    private static  $instance;

    /**
     * PDO object Instance
     */
    private         $PDOInstance 	=	null;

    /**
     * Constructor
     *
     * @param string $dsn : Data source name
     * @param string $username : User Name
     * @param string $password : User Password
     * @param string $driver_options : PDO Specific options
     * @param bool $connect
     */
    private function __construct($dsn, $username = null, $password = null, $driver_options = null, $connect = true) {
        $this->PDOInstance  =   new PDOExtended($dsn, $username, $password, $driver_options, $connect);
    }

    /**
     * Singleton call
     *
     * @param string $dsn : Data source name
     * @param string $username : User Name
     * @param string $password : User Password
     * @param string $driver_options : PDO Specific options
     * @param bool $connect
     * @return PDOExtended PDOInstance
     */
    public static function Cnx($dsn = null, $username = null, $password = null, $driver_options = null, $connect = true) {
        if (is_null(self::$instance))
            self::$instance =   new self($dsn, $username, $password, $driver_options, $connect);

        return self::$instance;
    }

    /**
     * Magic call - PDOExtended and PDO methods
     */
    public function __call($method, $args = null) {
        return call_user_func_array(Array($this->PDOInstance, $method), $args);
    }
}