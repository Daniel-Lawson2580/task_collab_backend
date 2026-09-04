<?php
$raw = file_get_contents('php://input');
var_dump($raw);
$input = json_decode($raw, true) ?? [];
var_dump($input);
