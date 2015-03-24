<?php
/**
 * $shimansky.biz
 *
 * Static web site core scripts
 * @category PHP
 * @access public
 * @copyright (c) 2012 Shimansky.biz
 * @author Serguei Shimansky <serguei@shimansky.biz>
 * @license http://opensource.org/licenses/bsd-license.php
 * @package shimansky.biz
 * @link https://bitbucket.org/englishextra/shimansky.biz
 * @link https://github.com/englishextra/shimansky.biz.git
 */

$relpa = ($relpa0 = preg_replace("/[\/]+/", "/", $_SERVER['DOCUMENT_ROOT'] . '/')) ? $relpa0 : '';

if (!isset($pt_regional) || empty($pt_regional)) {include $relpa . 'inc/regional.inc';}

if (!defined('MYSQL_DB_HOST')) {define('MYSQL_DB_HOST', $pt_regional['mysql_db_host']);}
if (!defined('MYSQL_DB_USER')) {define('MYSQL_DB_USER', $pt_regional['mysql_db_user']);}
if (!defined('MYSQL_DB_PASS')) {define('MYSQL_DB_PASS', $pt_regional['mysql_db_pass']);}
if (!defined('MYSQL_DB_NAME')) {define('MYSQL_DB_NAME', $pt_regional['mysql_db_name']);}

br()
   ->config()
     ->set( 'db'
          , array( 'engine'   => 'mysql'
                 , 'hostname' => MYSQL_DB_HOST
                 , 'name'     => MYSQL_DB_NAME
                 , 'username' => MYSQL_DB_USER
                 , 'password' => MYSQL_DB_PASS
				 , 'charset' => 'utf8'
                 ));
