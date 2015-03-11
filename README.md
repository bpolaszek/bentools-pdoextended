PDO Extended
===========

A PDO extension that will help your developer's life.

Features :
- Several methods like sqlArray(), sqlrow(), sqlColumn(), sqlValue() that will help you to quickly retrieve a resultset
- You can disconnect, reconnect, pause the connection.
- Bind your values easily, example : 

```php
$ids = $cnx->sqlColumn("SELECT Id FROM table WHERE CreationDate BETWEEN ? AND ?", ['2013-01-01', '2013-06-01']);
$id = $cnx->sqlValue("SELECT Id FROM table WHERE Id = ?", [35]);
$id = $cnx->sqlValue("SELECT Id FROM table WHERE Id = ?", 35); // You don't need an array if you just have 1 value to bind
$row = $cnx->sqlRow("SELECT * FROM table WHERE Name LIKE :Name AND Id >= :Id", ['Name' => 'foo%', 'Id' => 22]);
``` 

- Bind, Run and Debug your statements very easily :

```php

$stmt = $cnx->prepare("SELECT Id FROM table WHERE CreationDate BETWEEN ? AND ?");
$ids = $stmt->sqlColumn(['2013-01-01', '2013-06-01']); // Fetches Ids in an indexed array
var_dump($stmt->debug()->preview()); // SELECT Id FROM table WHERE CreationDate BETWEEN '2013-01-01' AND '2013-06-01'
var_dump($stmt->debug()->duration); // 0.004

``` 

- Create placeholders on the fly :

```php 
$ids = [2, 5, 8, 24, 26];
try {
    $rows = $cnx->sqlArray("SELECT * FROM table WHERE Id IN (".PDOStatementExtended::PlaceHolders($ids).")", $ids);
}
catch (StmtException $e) {
    var_dump($e->getStmt()->queryString); // SELECT * FROM table WHERE Id IN (?, ?, ?, ?, ?)
    var_dump($e->getStmt()->preview()); // SELECT * FROM table WHERE Id IN (2, 5, 8, 24, 26)
}

// Also works with strings with automatic quote wrapping
$dates = ['2013-01-01', '2013-06-01'];
try {
    $rows = $cnx->sqlArray("SELECT * FROM table WHERE CreationDate IN (".PDOStatementExtended::PlaceHolders($dates).")", $dates);
}
catch (StmtException $e) {
    var_dump($e->getStmt()->queryString); // SELECT * FROM table WHERE CreationDate IN (?, ?)
    var_dump($e->getStmt()->preview()); // SELECT * FROM table WHERE CreationDate IN ('2013-01-01', '2013-06-01')
}
``` 

- You no longer need to deal with PDO::PARAM_INT, PDO::PARAM_STRING etc. since the PDO type is automatically set depending on the PHP type.

- Each query you submit to the prepare() and sql() methods are hashed and stored for later re-use. Example :

```php
$cnx->sqlArray("SELECT * WHERE foo = ?", 'bar'); // "SELECT * WHERE foo = ?" string is prepared and transformed to a PDOStatementExtended object
$cnx->sqlArray("SELECT * WHERE foo = ?", 'baz'); // "SELECT * WHERE foo = ?" now matches an existing PDOStatementExtended object previously stored and doesn't have to be prepared again
```

- This behaviour may be disabled or enabled at any time  (enabled by default) :

```php
$cnx->storeStmts(false); // or true
```

- You can also import an existing PDO instance, avoiding you to have a 2nd connection :

```php
$pdo    =   new \PDO('mysql:host=localhost', 'user', 'password');
$cnx    =   PDOExtended\PDOExtended::NewInstanceFromPdo($pdo);
```

Full Example usage :

```php

define('PDO_DSN', 'mysql:host=localhost;dbname=test');
define('PDO_USERNAME', 'root');
define('PDO_PASSWORD', null);

// Normal call
$cnx    =   new PDOExtended(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

// Let's create our working table...
$cnx->sql("CREATE TABLE IF NOT EXISTS
                        TVSeries (
                            Id TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
                            Name VARCHAR(255),
                            Channel VARCHAR(40),
                            PRIMARY KEY (Id),
                            UNIQUE (Name)
                        ) ENGINE=InnoDb");

// Example of insert with a prepared statement
$insertStmt =   $cnx->Prepare("INSERT IGNORE INTO TVSeries SET Name = ?");
$tvSeries   =   Array('Games Of Thrones', 'The Big Bang Theory', 'Dexter');

// For each Series, we play the insert statement with a different value
foreach ($tvSeries AS $series)
    $insertStmt->sql($series);
    // $series is mapped to the "Name" bound value - since it's a string, it'll be bound as PDO::PARAM_STR
    // If $series was an integer, it'd be bound as PDO::PARAM_INT automatically

// The expected parameter may also be an array, in case you have more values to bind
$insertStmt =   $cnx->Prepare("INSERT IGNORE INTO TVSeries SET Name = :Name");
foreach ($tvSeries AS $series)
    $insertStmt->sql(Array('Name' => $series));

// Now, let's update the previous rows with the Channel info.
$channels    =   Array('HBO', 'CBS', 'ShowTime');

// You're not obliged to prepare your statement before executing them. It's the sql() method's job.
$cnx->sql("UPDATE TVSeries SET Channel = ? WHERE Name = ?", Array($channels[0], $tvSeries[0]));
$cnx->sql("UPDATE TVSeries SET Channel = :Channel WHERE Name LIKE :Name", Array('Channel' => $channels[1], 'Name' => $tvSeries[1]));
$cnx->sql("UPDATE TVSeries SET Channel = :Channel WHERE Id = :Id", Array('Channel' => $channels[2], 'Id' => 3)); // Id will be cast as PDO::PARAM_INT because 2 is a PHP integer

// Now, let's easily retrieve some infos.

// All the table into a multidimensionnal array.
var_dump($cnx->sqlArray("SELECT * FROM TVSeries"));

// Juste the Big bang theory row
var_dump($cnx->sqlRow("SELECT * FROM TVSeries WHERE Id = ?", 2));

// A list of series
var_dump($cnx->sqlColumn("SELECT Name FROM TVSeries WHERE Channel IN (". PDOStatementExtended::PlaceHolders($channels) .")", $channels)); // PlaceHolders function will output "IN (?, ?, ?)";

// What's the channel of Dexter ?
var_dump($cnx->sqlValue("SELECT Channel FROM TVSeries WHERE Name LIKE :Name", Array('Name' => 'Dexter')));

// Another way to request it
var_dump($cnx->sqlValue("SELECT Channel FROM TVSeries WHERE Name LIKE ?", Array('Dexter')));

// Another way to request it
var_dump($cnx->sqlValue("SELECT Channel FROM TVSeries WHERE Name LIKE ?", 'Dexter'));

// Association key => value
var_dump($cnx->sqlAssoc("SELECT Channel, Name FROM TVSeries WHERE Id = ?", 3));

// Association key => associative array
var_dump($cnx->sqlAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id = ?", 3, PDOExtended::TO_ARRAY_ASSOC));

// Association key => indexed array
var_dump($cnx->sqlAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id = ?", 3, PDOExtended::TO_ARRAY_INDEX));

// Association key => stdClass
var_dump($cnx->sqlAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id = ?", 3, PDOExtended::TO_STDCLASS));

// Association key => value, multiline version (array of keys => values)
var_dump($cnx->sqlMultiAssoc("SELECT Channel, Name FROM TVSeries WHERE Id IN (?, ?)", Array(1, 2)));

// Association key => associative array, multiline version (array of keys => associative arrays)
var_dump($cnx->sqlMultiAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id IN (?, ?)", Array(1, 2), PDOExtended::TO_ARRAY_ASSOC));

// Association key => indexed array, multiline version (array of keys => indexed arrays)
var_dump($cnx->sqlMultiAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id IN (?, ?)", Array(1, 2), PDOExtended::TO_ARRAY_INDEX));

// Association key => stdClass, multiline version (array of keys => stdClasses)
var_dump($cnx->sqlMultiAssoc("SELECT Channel, Id, Name FROM TVSeries WHERE Id IN (?, ?)", Array(1, 2), PDOExtended::TO_STDCLASS));

// You can also invoke all theses methods from a PDOStatementExtended object
var_dump($cnx->prepare("SELECT Channel, Id, Name FROM TVSeries WHERE Id IN (?, ?)")->sqlMultiAssoc(Array(2, 3), PDOExtended::TO_STDCLASS));

// How long the query has taken ?
$stmt   =   $cnx->prepare("SELECT * FROM TVSeries WHERE Id = :Id OR Name LIKE :Name");
$res    =   $stmt->sqlArray(Array('Id' => 1, 'Name' => 'Dexter'));
var_dump($stmt->getDuration());

// What was the real query played ?
var_dump($stmt->debug()->preview());

// You can disconnect : every call afterwards will result in a PDO Exception until you invoke the Reconnect() method
$cnx->disconnect();

// You can also Pause the connection. It actually disconnects from MySQl, but on the next call (sql, query, sqlArray etc) the connect() method will automatically be called so you never have a "not connected" exception thrown
$cnx->pause();

// Why doing this ? When you have a big treatment to do (parsing a big xml for instance), this prevents from getting "sleep connections" issues


// Every other PDO method is available... $cnx->query() ou $cnx->setAttribute(), etc.

Installation
------------
Add the following line into your composer.json :

    {
        "require": {
            "bentools/pdoextended": "dev-master"
        }
    }  
    
Enjoy.