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

use \ArrayIterator;

/**
 * class to manage result array more effectively
 *
 */
final class ResultSet extends ArrayIterator {

  /**
   * overwritten getArrayCopy to also include all entities as arrays
   *
   * @return an array copy of the ResultSet with entity arrays
   */
  public function getArrayCopy() {
    $array = parent::getArrayCopy();
    array_walk($array, function (&$a) {
                          $a = $a->toArray();
                       });
    return $array;
  }

  /**
   * magic method for applying called methods to all members of result set
   *
   * @param string $method
   * @param Array $params
   * @return $this
   */
  public function __call($method,$params=array()) {
    foreach ($this as $obj) {
      call_user_func_array(array($obj,$method),$params);
    }
    return $this;
  }

  public function __toString() {
      return json_encode($this->getArrayCopy());
  }
}
