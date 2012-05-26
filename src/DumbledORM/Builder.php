<?php
namespace DumbledORM;

use PDO;
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
 * Builder class for required for generating base classes
 *
 */
abstract class Builder {

  /**
   * simple cameCasing method
   *
   * @param string $string
   * @return string
   */
  public static function camelCase($string)	{
    return ucfirst(preg_replace("/_(\w)/e","strtoupper('\\1')",strtolower($string)));
  }

  /**
   * simple un_camel_casing method
   *
   * @param string $string
   * @return string
   */
  public static function unCamelCase($string)	{
    return strtolower(preg_replace("/(\w)([A-Z])/","\\1_\\2",$string));
  }

  /**
   * re/generates base classes for db schema
   *
   * @param string $prefix
   * @param string $dir
   * @return void
   */
  public static function generateBase($prefix=null,$dir='model') {
    $tables = array();
    foreach (Db::query('show tables',null,PDO::FETCH_NUM) as $row) {
      foreach (Db::query('show columns from `'.$row[0].'`') as $col) {
        if ($col['Key'] === 'PRI') {
          $tables[$row[0]]['pk'] = $col['Field']; break;
        }
      }
    }
    foreach (array_keys($tables) as $table) {
      foreach (Db::query('show columns from `'.$table.'`') as $col) {
        if (substr($col['Field'],-3,3) === '_id') {
          $rel = substr($col['Field'],0,-3);
          if (array_key_exists($rel,$tables)) {
            if ($table === "{$rel}_meta") {
              $tables[$rel]['meta']['class'] = self::camelCase($table);
              $tables[$rel]['meta']['field'] = $col['Field'];
            }
            $tables[$table]['relations'][$rel] = array('fk' => 'id', 'lk' => $col['Field']);
            $tables[$rel]['relations'][$table] = array('fk' => $col['Field'], 'lk' => 'id');
          }
        }
      }
    }
    $basetables = "<?php\nuse DumbledORM\BaseTable;\nspl_autoload_register(function(\$class) { @include(__DIR__.\"/\$class.class.php\"); });\n";
    foreach ($tables as $table => $conf) {
      $relations = preg_replace('/[\n\t\s]+/','',var_export((array)@$conf['relations'],true));
      $meta = isset($conf['meta']) ? "\$meta_class = '{$conf['meta']['class']}', \$meta_field = '{$conf['meta']['field']}'," : '';
      $basetables .= "class ".$prefix.self::camelCase($table)."Base extends BaseTable { protected static \$table = '$table', \$pk = '{$conf['pk']}', $meta \$relations = $relations; }\n";
    }
    @mkdir("./$dir",0777,true);
    file_put_contents("./$dir/base.php",$basetables);
    foreach (array_keys($tables) as $table) {
      $file = "./$dir/$prefix".self::camelCase($table).'.class.php';
      if (!file_exists($file)) {
        file_put_contents($file,"<?php\nclass ".$prefix.self::camelCase($table).' extends '.$prefix.self::camelCase($table).'Base {}');
      }
    }
  }

}
