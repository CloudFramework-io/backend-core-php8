<?php
/**
 * Router to include tests scripts of CLOUDFRAMEWORK
 */
$test_file = ($script[1]??null)?:'default';
if(!is_file(__DIR__."/_backup/{$test_file}.php")) {
    die("Test [{$test_file}] does not exists");
}
include_once(__DIR__."/_backup/{$test_file}.php");
