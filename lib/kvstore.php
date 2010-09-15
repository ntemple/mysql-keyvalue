<?php
/*
Copyright (c) 2010, Nick Temple All rights reserved.

Redistribution and use in source and binary forms, with or
without modification, are permitted provided that the following
conditions are met:

* Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following
disclaimer in the documentation and/or other materials provided
with the distribution.

* Neither the name of the origanization nor the names of its
contributors may be used to endorse or promote products derived
from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND
CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED.  IN NO EVENT SHALL THE COPYRIGHT HOLDER OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class kvstore {

  /** @var mysqldb */
  var $db;
  var $table = 'table-0';
  var $locking = false;
  var $locked = false;

  function __construct($db, $table = 'table-0') {
    $this->db = $db;
    $this->table = $table;
  }

  function _create($table) {
    $this->db->query("CREATE TABLE IF NOT EXISTS `$table` ( `k` varchar(255) NOT NULL, `v` longblob NOT NULL, PRIMARY KEY (`k`) ) ENGINE=MyISAM DEFAULT CHARSET=latin1");  
  }

  function _lock() {
    if ($this->locked) return;
    $this->locked = true;

    if ($this->locking) $this->db->query("LOCK TABLES `{$this->table}` WRITE");    
  }

  function _unlock() {   
    if (! $this->locked) return;
    $this->locked = false;

    if ($this->locking) $this->db->query("UNLOCK TABLES");        
  }

  function set($k, $v) {
    $this->db->query("REPLACE INTO `{$this->table}` (k,v) values(?,?)", $k, $v);    
  }

  function get($k) {
    return $this->db->get_value("SELECT v from `{$this->table}` where k=?", $k);
  }

  function mset($mkv) {
    $this->_lock();
    foreach ($mkv as $k => $v) {
      $this->set($k, $v);
    }
    $this->_unlock();    
  }

  function mget($mk) {
    $results = array();
    foreach ($mk as $k) {
      $results[] = $this->get($k);
    }
    return $results;
  }

  // Optimization, not sure if redis will support it  
  function mget_assoc($mk) {
    $in =  implode("','", $mk);
    $sql = "select k,v from `{$this->table}` where k in ('$in')";
    return $this->db->get_select($sql);
  }

  function incrby($k, $x) {
    $this->_lock();
    $v = $this->get($k);
    $v += $x;
    $this->set($k, $v);
    $this->_unlock();
    return $v;
  }

  function keys($pattern) {
    return $this->db->get_row("SELECT k from `{$this->table}` where k like '$pattern'");    
  }

  function incr($k) {
    return $this->incrby($k, 1);    
  }

  function decr($k) {
    return $this->incrby($k, -1);    
  }

  function decrby($k, $x) {
    $this->incrby($k, $x * -1);
  }

  function exists($k) {
    return $this->db->get_value("SELECT count(*) from `{$this->table}` where k=?", $k); // should return 1 or 0    
  }

  function del($k) {
    return $this->db->query("DELETE from `{$this->table}` where k=?", $k); // should return 1 or 0  
  }

  // Set a key to a string returning the old value of the key 
  function getset($k, $v) {
    $this->_lock();
    $old_v = $this->get($k);
    $this->set($k, $v);
    $this->_unlock();
    return $v;
  }

  function setnx($k, $v) {
    $success = false;
    $this->_lock();
    if ($this->exists($k)) {
      $success = false;
    } else {
      $this->set($k, $v);
      $success = true;
    }
    $this->_unlock();
    return $success;
  }

  function append($k, $v)  {
    $this->_lock();
    $cv = $this->get($v);
    $v = $cv . $v;
    $this->set($v);
    $this->unlock();
  }

  /* Lists are implemented as json_encoded blobs. Your mileage my vary */

  function _lget($k) {
    $cv = $this->get($k);    
    if ($cv) {
      $a = json_decode($cv);
    } else {
      $a = array();
    }
#    print_r($a);
    return $a;
  }

  function _lset($k, $a) {
    $this->set($k, json_encode($a));
  }

  function rpush($k, $v) {
    $this->_lock();
    $a = $this->_lget($k);
    array_push($a, $v);
    $this->_lset($k, $a);
    $this->_unlock();    
  }

  function lpush($k, $v) {
    $this->_lock();
    $a = $this->_lget($k);
    array_unshift($a, $v);
    $this->_lset($k, $a);
    $this->_unlock();    
  }

  function rpop($k) {
    $this->_lock();
    $a = $this->_lget($k);
    if (count(a) > 0) {
      $v = array_pop($a);
    } else {
      $v = null;
    }
    $this->_lset($k, $a);
    $this->_unlock();    
    return $v;    
  }

  function lpop($k) {
    $this->_lock();
    $a = $this->_lget($k);
    if (count(a) > 0) {
      $v = array_unshift($a);
    } else {
      $v = null;
    }
    $this->_lset($k, $a);
    $this->_unlock();    

    return $v;        
  }
}


