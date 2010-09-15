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

require_once('kvstore.php');
require_once('simple_mysqldb.php');

$db = new simple_mysqldb();

// YOU MUST MODIFY CONNECTION, and create at least one table
$db->connect('localhost', 'kvstore', 'user', 'pass');


$ds = new kvstore($db);

$ds->set('key', 'value');

$ds->value = $ds->get('key');

$mkv = array(
'usr:0001' => 'First user',
'usr:0002' => 'Second user', 
'usr:0003' => 'Third user' 
);

$ds->mset($mkv);

$x = $ds->mget_assoc(array_keys($mkv));
print_r($x);

$x = $ds->mget(array_keys($mkv));
print_r($x);

$x = $ds->incr('counter1');
$x = $ds->incr('counter2');
$x = $ds->incr('counter2');
$x = $ds->incr('counter2');
print_r($x);

$x = $ds->mget(array('counter1', 'counter2'));
print_r($x);


$ds->rpush('list', 'a');
$ds->rpush('list', 'b');
$ds->rpush('list', 'c');
$ds->rpush('list', 'd');
$ds->rpush('list', 'e');






