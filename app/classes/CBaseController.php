<?php

#****c* classes/CBaseController
# NAME
#   CBaseController -- Base Controller class
# SYNOPSYS
#   $obj = new CBaseController($f3)
# INPUTS
#   $f3 -- FatFreeFramework module instance
# FUNCTION
#   This class used to manage DB access.
#
#   It will either configure the database connection from
#   config.ini or from Environment variables.
# TODO
#   - TODO: make the DB connection a shared|class static variable
#******
class CBaseController {
  protected $db;
  function __construct($f3) {
    $db_dns = self::env_expand($f3->get('db_dns'));
    $db_user = self::env_expand($f3->get('db_user'));
    $db_pass = self::env_expand($f3->get('db_pass'));
    $db=new DB\SQL(
       $db_dns,
       $db_user,
       $db_pass
    );
    $this->db = $db;
  }
  #****m* CBaseController/env_expand
  # NAME
  #   env_expand -- Expand $var references
  # SYNOPSIS
  #   $out = BaseController::env_expand($inp)
  # FUNCTION
  #   Will expand any '$' environment variable references.
  # INPUTS
  #   $inp -- text to expand
  # RESULTS
  #   Expanded text.
  #******
  static public function env_expand($inp) {
    if (strpos($inp,'$') === false) return $inp;
    $cnv = array();
    foreach (getenv() as $k=>$v) {
      $cnv['${'.$k.'}'] = $v;
    }
    return strtr($inp,$cnv);
  }

}

