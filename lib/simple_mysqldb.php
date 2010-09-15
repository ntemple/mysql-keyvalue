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

/* Stripped down version of larger class. Still overkill for the project. */

class simple_mysqldb {


  var $db_rw;
  var $prefix   = ''; // Table prefix, use #__ in queries (#__ removed if no prefix)
  var $halt     = false;
  var $rs       = NULL;
  var $queries  = 0;  // number of queries

  /**
  * Connect to a database
  *
  */
  function connect($host, $database, $user, $password) {

    $handle = @mysql_connect($host, $user, $password);

    if (!$handle) return $this->halt("connection to $database failed.", -1);
    if (!@mysql_select_db($database, $handle)) return $this->halt("cannot select database $database", -1);

    $this->db_rw = $handle;

    return $handle;
  }

  /**
  *  Prepare a select statement using ?
  */
  function prepare() {
    $args  = func_get_args();

    // Allow _just_ an array to be passed, for easier integration
    if (count($args) == 1 && is_array($args[0]) ) {
      $args = $args[0];
    }

    return  call_user_func_array('_db_prepare', $args);
  }

  /*
  * (pretend to) discard the query result
  *
  * There are many cases when the db class is no
  * longer needed, however the returned result set is
  * still in use.  To REALLY free the result set, send
  * call free(true);
  *
  */

  function free($force = false) {
    if ($this->rs && $force) {
      @mysql_free_result($this->rs);
    }
    $this->rs = 0;
  }

  /**
  *
  * resolve_query assumes that the $_qs has already been setup
  */
  function _resolve_query($link) {

    // PHP4 chokes on empty queries
    if (trim($this->_qs) == "") return 0;

    if ($this->rs) $this->free();

    $this->queries++;
    $this->rs = mysql_query($this->_qs, $link);

    $this->row   = 0;
    if (!$this->rs) return $this->halt("ERR:" . $this->_qs);
    return $this->rs;
  }

  /**
  *
  * Query
  * Extended to allow prepare:
  *
  * $db->query('query string') or ...
  * $db->query->('query string ? ?', $r1, r2); or ...
  * $db->query->('query_string ? ?', $r[]);
  *
  */

  function query() {
    $args  = func_get_args();
    if (count($args) == 1 && is_array($args[0]) ) $args = $args[0];
    $this->_qs = call_user_func_array('_db_prepare', $args);

    return $this->_resolve_query($this->db_rw);
  }


  /**
  * return one value from the query
  */

  function get_value() {
    $rs = $this->query(func_get_args());
    if (!$rs) return false;
    $row = mysql_fetch_row($rs);
    $this->free();
    return $row[0];
  }
  /**
  * return data formatted for Flexy select()
  *
  * example: get_select('select id, name from options');
  */
  function get_select() {
    $rs = $this->query(func_get_args());

    $data = array();
    while($row = @mysql_fetch_row($rs) ) {
      $id   = $row[0];
      $name = $row[1];
      $data[$id] = $name;
    }
    $this->free();
    return $data;
  }

  /**
  * Slurp everything into an associative array
  */
  function get_results() {
    $rs = $this->query(func_get_args());
    $data = array();
    if (!$rs) return NULL;

    while($row = mysql_fetch_assoc($rs) )
      $data[] = $row;
    $this->free();
    return $data;
  }

  /**
  * return result as a single associative array
  */
  function get_row(){
    $rs = $this->query(func_get_args());
    $row = mysql_fetch_assoc($rs);
    $this->free();
    return $row;
  }

  function affected_rows() {
    return mysql_affected_rows($this->db_rw);
  }

  function insert_id() {
    return mysql_insert_id($this->db_rw);
  }

  /**
  * return result of a column
  */
  function get_column() {
    $rs = $this->query(func_get_args());

    $data = array();

    if (!$rs) return $data;

    while($row = mysql_fetch_row($rs) ) {
      $data[] = $row[0];
    }
    $this->free();
    return $data;
  }


  /**
  * Error handling meant to be overridden
  * in child class
  */

  function halt($msg, $errno = 0 ) {
    $this->_errno  = mysql_errno();
    $this->_error  = mysql_error();
    $backtrace = debug_backtrace();
    echo "Error: $msg\n";
  }
}



/**
* Prepare a query
*
*/
function _db_prepare() {
  $args = func_get_args();

  if (count($args) == 1) {
    // only a query, no substition expected nor required
    return $args[0];
  }

  // We need to substitute
  if (is_array($args[1])) {
    // The last argument is an array of replacement values
    $template = array_shift($args);  // get the template
    $args = array_shift($args);      // get the actual replacement values
    array_unshift($args, $template); // put the template on top
  }
  $query = call_user_func_array('_db_make_qw', $args);
  return $query;
}

/**
* string _db_make_qw($query, $arg1, $arg2, ...)
*
* @access private
*/
function _db_make_qw() {
  $args = func_get_args();
  $tmpl =& $args[0];
  $tmpl = str_replace("%", "%%", $tmpl);
  $tmpl = str_replace("?", "%s", $tmpl);
  foreach ($args as $i=>$v) {
    if (!$i) continue;
    if (is_int($v)) continue;
    $args[$i] = "'".mysql_real_escape_string($v)."'";
  }
  for ($i=$c=count($args)-1; $i<$c+20; $i++)
    $args[$i+1] = "UNKNOWN_PLACEHOLDER_$i";
  return call_user_func_array("sprintf", $args);
}



