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

/**
 * base functionality available to all objects extending from a generated base class
 *
 */
abstract class BaseTable {

  protected static
    /**
     * table name
     */
    $table,
    /**
     * primary key
     */
    $pk,
    /**
     * table relations array
     */
    $relations,
    /**
     * metadata class name
     */
    $meta_class,
    /**
     * metadata field
     */
    $meta_field;

  protected
    /**
     * record data array
     */
    $data,
    /**
     * metadata array
     */
    $meta,
    /**
     * relation data array
     */
    $relation_data,
    /**
     * record primary key value
     */
    $id,
    /**
     * array of data fields that have changed since hydration
     */
    $changed;

  /**
   * search for single record in self::$table
   *
   * @param Array $constraints
   * @return BaseTable
   */
  final public static function one(Array $constraints) {
    return self::select('`'.implode('` = ? and `',array_keys($constraints)).'` = ? limit 1',array_values($constraints))->current();
  }

  /**
   * search for any number of records in self::$table
   *
   * @param Array $constraints
   * @return ResultSet
   */
  final public static function find(Array $constraints) {
    return self::select('`'.implode('` = ? and `',array_keys($constraints)).'` = ?',array_values($constraints));
  }

  /**
   * execute a query in self::$table
   *
   * @param string $qs
   * @param mixed $params
   * @return ResultSet
   */
  final public static function select($qs,$params=null) {
    return Db::hydrate(new static,'select * from `'.static::$table.'` where '.$qs,$params);
  }

  /**
   * construct object and load supplied data or fetch data by supplied id
   *
   * @param mixed $val
   */
  public function __construct($val=null) {
    if (is_array($val)) {
      $this->data = $val;
      $this->changed = array_flip(array_keys($this->data));
      $this->_loadMeta();
    } else if (is_numeric($val)) {
      if (!$obj = self::one(array(static::$pk => $val))) {
        throw new RecordNotFoundException("Nothing to be found with id $val");
      }
      $this->hydrate($obj->toArray());
    }
  }

  /**
   * most of the magic in here makes it all work
   * - handles all getters and setters on columns and relations
   *
   * @param string $method
   * @param Array $params
   * @return mixed
   */
  final public function __call($method,$params=array()) {
    $name = Builder::unCamelCase(substr($method,3,strlen($method)));
    if (strpos($method,'get')===0) {
      if (array_key_exists($name,$this->data)) {
        return $this->data[$name];
      }
      if (isset(static::$relations[$name])) {
        $class = substr($method,3,strlen($method));
        if (count($params)) {
          if ($params[0] === true) {
            return @$this->relation_data[$name.'_all'] ?: $this->relation_data[$name.'_all'] = $class::find(array(static::$relations[$name]['fk'] => $this->getId()));
          }
          $qparams = array_merge(array($this->getId()),(array)@$params[1]);
          $qk = md5(serialize(array($name,$params[0],$qparams)));
          return @$this->relation_data[$qk] ?: $this->relation_data[$qk] = $class::select('`'.static::$relations[$name]['fk'].'` = ? and '.$params[0],$qparams);
        }
        return @$this->relation_data[$name] ?: $this->relation_data[$name] = $class::one(array(static::$relations[$name]['fk'] => $this->getId()));
      }
    }
    else if (strpos($method,'set')===0) {
      $this->changed[$name] = true;
      $this->data[$name] = array_shift($params);
      return $this;
    }
    throw new BadMethodCallException("No amount of magic can make $method work..");
  }

  /**
   * simple output object data as array
   *
   * @return Array
   */
  final public function toArray() {
    return $this->data;
  }

  /**
   * simple output object pk id
   *
   * @return integer
   */
  final public function getId() {
    return $this->id;
  }

  /**
   * store supplied data and bring object state to current
   *
   * @param Array $data
   * @return $this
   */
  final public function hydrate(Array $data) {
    $this->id = $data[static::$pk];
    $this->data = $data;
    $this->_loadMeta();
    $this->changed = array();
    return $this;
  }

  /**
   * create an object with a defined relation to this one.
   *
   * @param BaseTable $obj
   * @return BaseTable
   */
  final public function create(BaseTable $obj) {
    return $obj->{'set'.Builder::camelCase(static::$relations[Builder::unCamelCase(get_class($obj))]['fk'])}($this->id);
  }

  /**
   * insert or update modified object data into self::$table and any associated metadata
   *
   * @return void
   */
  public function save() {
    if (empty($this->changed)) return;
    $data = array_intersect_key($this->data,$this->changed);

    // use proper sql NULL for values set to php null
    foreach ($data as $key => $value) {
      if ($value === null) {
        $data[$key] = new PlainSql('NULL');
      }
    }

    if ($this->id) {
      $query = 'update `'.static::$table.'` set `'.implode('` = ?, `',array_keys($data)).'` = ? where `'.static::$pk.'` = '.$this->id.' limit 1';
    }
    else {
      $query = 'insert into `'.static::$table.'` (`'.implode('`,`',array_keys($data))."`) values (".rtrim(str_repeat('?,',count($data)),',').")";
    }
    Db::execute($query,array_values($data));
    if ($this->id === null) {
      $this->id = Db::pdo()->lastInsertId();
    }
    $this->meta->{'set'.Builder::camelCase(static::$meta_field)}($this->id)->save();
    $this->hydrate(self::one(array(static::$pk => $this->id))->toArray());
  }

  /**
   * delete this object's record from self::$table and any associated meta data
   *
   * @return void
   */
  public function delete() {
    Db::execute('delete from `'.static::$table.'` where `'.static::$pk.'` = ? limit 1',$this->getId());
    $this->meta->delete();
  }

  /**
   * add an array of key/val to the metadata
   *
   * @param Array $data
   * @return $this
   */
  public function addMeta(Array $data) {
    foreach ($data as $field => $val) {
      $this->setMeta($field,$val);
    }
    return $this;
  }

  /**
   * set a field of metadata
   *
   * @param string $field
   * @param string $val
   * @return $this
   */
  public function setMeta($field,$val) {
    if (empty($this->meta[$field])) {
      $meta_class = static::$meta_class;
      $this->meta[$field] = new $meta_class(array('key' => $field,'val' => $val));
    }
    else {
      $this->meta[$field]->setVal($val);
    }
    return $this;
  }

  /**
   * get a field of metadata
   *
   * @param string $field
   * @return mixed
   */
  public function getMeta($field) {
    return isset($this->meta[$field]) ? $this->meta[$field]->getVal() : null;
  }

  /**
   * internally fetch and load any associated metadata
   *
   * @return void
   */
  private function _loadMeta() {
    if (!$meta_class = static::$meta_class) {
      return $this->meta = new ResultSet;
    }
    foreach ($meta_class::find(array(static::$meta_field => $this->getId())) as $obj) {
      $meta[$obj->getKey()] = $obj;
    }
    $this->meta = new ResultSet((array)@$meta);
  }

}
