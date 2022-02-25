<?php

class Tag extends CBaseModel {
  public function table_name() { return 'daTag'; }

  public function list_tags() {
    return $this->define_dict('tagName');
  }

  public function count_tags($id=NULL) {
    if (is_null($id)) {
      $rows = $this->db->exec('SELECT tagId,COUNT(*) AS count FROM daArtifactTags GROUP BY tagId');
      if (count($rows) == 0) return false;
      $res = [];
      foreach ($rows as $row) {
	$res[$row['tagId']] = $row['count'];
      }
      return $res;
    } else {
      $rows = $this->db->exec('SELECT COUNT(*) AS count FROM daArtifactTags WHERE tagId=?',$id);
      if (count($rows) == 0) return false;
      return $rows[0]['count'];
    }
  }

  public function delete($id) {
    parent::delete($id);
    $this->db->exec('DELETE FROM daArtifactTags WHERE tagId = ?',$id);
  }

}
