<?php
namespace DumbledORM;

/**
 *
 *  DumbledORM
 *
 *  @version 0.1.1
 *  @author Jason Mooberry <jasonmoo@me.com>
 *  @link http://github.com/jasonmoo/DumbledORM
 *  @package DumbledORM
 *
 *  DumbledORM is a novelty PHP ORM
 *
 *  Copyright (c) 2010 Jason Mooberry
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */

use PDO;

/**
 * thin wrapper for PDO access
 *
 */
abstract class Db {

  /**
   * singleton variable for PDO connection
   *
   */
  private static $_pdo;

  /**
   * singleton getter for PDO connection
   *
   * @return PDO
   */
  public static function pdo() {
    if (!self::$_pdo) {
      self::$_pdo = new PDO('mysql:host='.DbConfig::HOST.';port='.DbConfig::PORT.';dbname='.DbConfig::$DBNAME, DbConfig::USER, DbConfig::PASSWORD);
      self::$_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return self::$_pdo;
  }

  /**
   * execute sql as a prepared statement
   *
   * @param string $sql
   * @param mixed $params
   * @return PDOStatement
   */
  public static function execute($sql,$params=null) {

    $params = is_array($params) ? $params : array($params);

    if ($params) {
      // using preg_replace_callback ensures that any inserted PlainSql
      // with ?'s in it will not be confused for replacement markers
      $sql = preg_replace_callback('/\?/',function($a) use (&$params) {
        $a = array_shift($params);
        if ($a instanceof PlainSql) {
          return $a;
        }
        $params[] = $a;
        return '?';
      },$sql);
    }
    $stmt = self::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }

  /**
   * execute sql as a prepared statement and return all records
   *
   * @param string $query
   * @param mixed $params
   * @param PDO constant $fetch_style
   * @return Array
   */
  public static function query($query,$params=null,$fetch_style=PDO::FETCH_ASSOC) {
    return self::execute($query,$params)->fetchAll($fetch_style);
  }

  /**
   * run a query and return the results as a ResultSet of BaseTable objects
   *
   * @param BaseTable $obj
   * @param string $query
   * @param mixed $params
   * @return ResultSet
   */
  public static function hydrate(BaseTable $obj,$query,$params=null) {
    $set = array();
    foreach (self::query($query,$params) as $record) {
      $clone = clone $obj;
      $clone->hydrate($record);
      $set[$clone->getId()] = $clone;
    }
    return new ResultSet($set);
  }

}
