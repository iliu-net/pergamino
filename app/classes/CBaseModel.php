<?php

#****c* classes/CBaseModel
# NAME
#   CBaseModel -- Base Model class
# SYNOPSYS
#   $obj = new CBaseModel($db)
# INPUTS
#   $db -- Database connection
# FUNCTION
#   This class used to model Database objects.
#******
abstract class CBaseModel extends DB\SQL\Mapper {
  public function __construct(DB\SQL $db) {
    parent::__construct($db,$this->table_name());
  }
  #****c* CBaseModel/table_name
  # NAME
  #   table_name -- returns the table used by this object
  # FUNCTION
  #   This declares the entry point used by sub-classes to
  #   return the database table to use.
  #******
  abstract public function table_name();

  #****m* CBaseModel/get_by_id
  # NAME
  #   get_by_id -- retrieves a record by id
  # SYNOPSIS
  #   $this->get_by_id($id,$copy)
  # FUNCTION
  #   Load the F3 HIVE POST array with the results of the query.
  # INPUTS
  #   $id -- ID to query
  #   $copy -- if true (the default) copy values to POST), otherwise
  #            returns the looked value.
  # RESULTS
  #   if $copy is true, $f3->get('POST') is updated.
  #   otherwise, returns the record by $id or false if not found.
  #******
  public function get_by_id($id,$copy=true) {
    $this->load(['id=?',$id]);
    if (!$copy) {
      if (count($this->query) == 1) return $this->query[0];
      return false;
    }
    $this->copyTo('POST');
  }
  #****m* CBaseModel/add
  # NAME
  #   add -- Add a new record
  # SYNOPSIS
  #   $this->add()
  # FUNCTION
  #   Creates a new record from the contents of POST
  # RESULTS
  #   database table is updated
  #******
  public function add() {
    $this->copyFrom('POST');
    $this->save();
  }
  #****m* CBaseModel/edit
  # NAME
  #   edit -- Edit an existing record
  # SYNOPSIS
  #   $this->edit($id)
  # FUNCTION
  #   Updates the record identied by $id with the contents of POST
  # INPUTS
  #   $id -- record id to update
  # RESULTS
  #   database table is updated
  #******
  public function edit($id) {
    $this->load(['id = ?',$id]);
    $this->copyFrom('POST');
    $this->update();
  }
  #****m* CBaseModel/delete
  # NAME
  #   delete -- Delete an existing record
  # SYNOPSIS
  #   $this->delete($id)
  # FUNCTION
  #   Deletes the record identied by $id
  # INPUTS
  #   $id -- record id to delete
  # RESULTS
  #   database table record is deleted.
  #******
  public function delete($id) {
    $this->load(['id = ?',$id]);
    $this->erase();
  }
  #****m* CBaseModel/define_dict
  # NAME
  #   define_dict -- Create a lookup dictionary
  # SYNOPSIS
  #   $this->define_dict($col,$filter,$orderby,$idcol)
  # FUNCTION
  #   Creates a PHP array that can be used in Fm::select calls
  # INPUTS
  #   $col -- column to look-up
  #   $filter -- filter selection (defaults to NULL or none)
  #   $orderby -- column to order by (e.g. "column" or "column DESC").
  #   $idcol -- defaults to 'id'.
  # RESULTS
  #   PHP array
  #******
  public function define_dict($col,$filter=NULL,$orderby='',$idcol='id') {
    $res = [];
    $rows = $this->find($filter);
    foreach ($rows as $row) {
      $res[$row[$idcol]] = $row[$col];
    }
    return $res;
  }
}
