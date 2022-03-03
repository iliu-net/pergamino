<?php
interface MimeHandler {
  static public function gen_preview($digest,$ext);
  static public function static_preview();
}

class DefaultHandler implements MimeHandler {
  const MIME_TYPE = '*';
  const DEFAULT_PREVIEW = '/ui/imgs/ft-blob-128.png';
  const CLASS_NAME = __CLASS__;

  static public function static_preview() {
    return self::DEFAULT_PREVIEW;
  }
  static public function gen_preview($digest,$ext) {
    return; //This is a no-op
  }

  static public function mkstemp( $template ) {
    $attempts = 238328; // 62 x 62 x 62
    $letters  = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $length   = strlen($letters) - 1;

    if( strlen($template) < 6 || !strstr($template, 'XXXXXX') )
      return [NULL,NULL];

    for( $count = 0; $count < $attempts; ++$count) {
      $random = "";

      for($p = 0; $p < 6; $p++) {
	$random .= $letters[mt_rand(0, $length)];
      }

      $randomFile = str_replace("XXXXXX", $random, $template);

      if( !($fd = @fopen($randomFile, "x+")) )
	continue;

      return [$fd,$randomFile];
    }
    return [NULL,NULL];
  }
}

class Artifact extends CBaseModel {
  static public $mime_handlers = [];

  static public function init_handlers() {
    if (count(self::$mime_handlers) != 0) return;
    Artifact::add_handler(DefaultHandler::CLASS_NAME, DefaultHandler::MIME_TYPE, false);
    $dirs = Sc::f3()->get('handlers');
    if (!is_array($dirs)) {
      $dirs = explode('|',$dirs);
    }
    foreach ($dirs as $dir) {
      $dir = trim($dir);
      foreach (glob($dir.'*.php') as $f) {
	if (!is_readable($f)) continue;
	include_once($f);
      }
    }

    //~ echo '<pre>'.print_r(Sc::f3()->get('accept_media'),true).'</pre>';
  }

  static public function add_handler($class_name, $mime_match,$ext=NULL) {
    $mime_match = strtolower($mime_match);
    self::$mime_handlers[$mime_match] = $class_name;

    if ($ext !== false) {
      if (is_null($ext)) $ext = $mime_match;
      $media = Sc::f3()->exists('accept_media') ? Sc::f3()->get('accept_media').',' : '';
      Sc::f3()->set('accept_media',$media . $ext);
    }
    uksort(self::$mime_handlers,function ($a,$b) {
      $la=strlen($a);$lb=strlen($b);
      if ($la==$lb) return 0;
      return $la < $lb ? 1 : -1;
    });
  }
  static public function get_handler($type) {
    self::init_handlers();
    $type = strtolower($type);
    # First the simple case ...
    if (isset(self::$mime_handlers[$type])) return self::$mime_handlers[$type];
    foreach (self::$mime_handlers as $mtype=>$mclass) {
      if (fnmatch($mtype,$type)) return $mclass;
    }
    return BinaryBlobHandler::CLASS_NAME;
  }
  static public function gen_preview($digest,$mtype,$ext) {
    $fstore = Sc::f3()->get('FILESTORE');
    if (is_file($fstore.Artifact::digest_pname($digest))) return;

    $handler = self::get_handler($mtype);
    call_user_func([$handler,'gen_preview'],$digest,$ext);
  }
  static public function get_preview($digest,$mtype) {
    $fstore = Sc::f3()->get('FILESTORE');
    if (!is_file($fstore.self::digest_pname($digest))) {
      $handler = self::get_handler($mtype);
      return Sc::url(call_user_func([$handler,'static_preview']));
    }
    return Sc::url('/'.$fstore.self::digest_pname($digest));
  }
  public function write_tags($id, $tags) {
    $this->db->exec('DELETE FROM daArtifactTags WHERE artifactId = ?',$id);
    if (!$tags) return;
    if (!is_array($tags)) $tags = [ $tags ];
    foreach ($tags as $ti) {
      //~ echo "ID: $id, TI=$ti\n";
      $this->db->exec('INSERT INTO daArtifactTags (artifactId,tagId) VALUES (?,?)',
		      [ $id, $ti ]);

    }
  }
  public function read_tags($id=NULL) {
    if (is_null($id)) {
      $all_tags = [];
      $rows = $this->db->exec('SELECT artifactId,tagId from daArtifactTags');
      foreach ($rows as $row) {
	if (!isset($all_tags[$row['artifactId']])) $all_tags[$row['artifactId']] = [];
	$all_tags[$row['artifactId']][$row['tagId']] = $row['tagId'];
      }
      return $all_tags;
    }
    $tags = [];
    $rows = $this->db->exec('SELECT tagId from daArtifactTags WHERE artifactId = ?', $id);
    foreach ($rows as $row) {
      $tags[$row['tagId']] = $row['tagId'];
    }
    return $tags;
  }

  public function table_name() { return 'daArtifact'; }

  public function add() {
    parent::add();
    $last_id =  $this->get('_id');
    $tags = Sc::f3()->get('POST.tags');
    $this->write_tags($last_id,$tags);
  }
  public function edit($id) {
    parent::edit($id);
    $tags = Sc::f3()->get('POST.tags');
    $this->write_tags($id,$tags);
  }
  public function get_by_id($id,$copy=true) {
    $res = parent::get_by_id($id,$copy);
    if ($copy) {
      if (count($this->query) != 1) return;
      Sc::f3()->set('POST.tags',$this->read_tags($id));
    }
    if ($res === false) return $res;
    $res['tags'] = $this->read_tags($id);
    return $res;
  }
  public function delete($id) {
    $row = $this->get_by_id($id,false);

    $fstore = Sc::f3()->get('FILESTORE');
    @unlink($fstore.self::digest_fname($row['digest'],$row['fileExtension']));
    @unlink($fstore.self::digest_pname($row['digest']));

    parent::delete($id);
    $this->write_tags($id,NULL);
  }


  static public function digest_dir($digest) {
    return substr($digest,0,2);
  }
  static public function digest_fname($digest,$ext,$thm='') {
    if ($ext != '') $ext = '.'.$ext;
    return substr($digest,0,2).'/'.substr($digest,2).$thm.$ext;
  }
  static public function digest_pname($digest) {
    return self::digest_fname($digest,'png','-preview');
  }
  static public function download_url($digest,$ext) {
    $fstore = Sc::f3()->get('FILESTORE');
    return '/'.$fstore.self::digest_fname($digest,$ext);
  }
}


