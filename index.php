<?php

include 'classes.php';
use options\Connection;
use options\telega;


$data = file_get_contents("php://input");
$data = json_decode($data,true);
$obj = new telega($data);
$checkStatus = $obj->readMessage();

