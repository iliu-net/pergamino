<?php
class ArtifactController extends CBaseController {

  static public function entag($txt,$taglist) {
    $tagtxt = array_flip($taglist);
    $tags = [];
    foreach (preg_split('/\s*,\s*/',$txt) as $tt) {
      if (isset($tagtxt[$tt])) {
	$i = $tagtxt[$tt];
	$tags[$i] = $i;
      }
    }
    return $tags;
  }

  public function index($f3,$params) {
    if (empty(Sc::f3()->get('QUERY'))) {
      if (!empty($_COOKIE['ARTIFACT_QUERY'])) {
	parse_str($_COOKIE['ARTIFACT_QUERY'],$get);
	Sc::f3()->set('GET',$get);
      }
    } else {
      setcookie('ARTIFACT_QUERY',Sc::f3()->get('QUERY'),
		[
		  'expires'=>time()+60*60*24*30 ,
		  'path'=>Sc::f3()->get('BASE'),
		]);
    }

    if (isset($params['msg'])) {
      $f3->set('msg',$params['msg']);
    } else {
      $f3->set('msg','');
    }
    $arts = new Artifact($this->db);
    $tags = new Tag($this->db);

    $tagdata = $arts->read_tags();
    $tag_list = $tags->list_tags();

    $tag_filter = [];
    if (Sc::f3()->exists('GET.tags')) {
      $tagnames = array_flip($tag_list);
      foreach (preg_split('/\s*,\s*/',Sc::f3()->get('GET.tags')) as $tx) {
	if ($tx == '') continue;
	if (!isset($tagnames[$tx])) {
	   $tag_filter[0] = 0;
	} else {
	  $tag_filter[$tagnames[$tx]] = $tagnames[$tx];
	}
      }
    }

    $results = [];
    $columns = $arts->schema();
    foreach ($arts->find() as $row) {
      $id = $row['id'];
      if (count($tag_filter) > 0) {
	if (!isset($tagdata[$id])) {
	  if (!isset($tag_filter[0])) continue;
	} else {
	    //~ # Row without tags...
	    //~ if (!isset($tag_filter[0])) continue;
	  //~ }
	  $found = false;
	  foreach ($tag_filter as $tf) {
	    if (isset($tagdata[$id][$tf])) {
	      $found = true;
	      break;
	    }
	  }
	  if (!$found) continue;
	}
      }
      $res = [];
      foreach (array_keys($columns) as $col) {
	$res[$col] = $row[$col];
      }
      $res['tags'] = isset($tagdata[$id]) ? $tagdata[$id] : [];
      $results[$id] = $res;
    }

    $f3->set('artifacts',$results);
    $f3->set('tags',$tag_list);
    $f3->set('tagfilter',implode(',',$tag_filter));

    echo View::instance()->render('artifact-list.html');
  }

  public function create($f3,$params) {
    $tags = new Tag($this->db);
    $tag_list = $tags->list_tags();

    if ($f3->exists('POST.create')) {
      $fattr = [];
      $files = Web::instance()->receive(function($file,$formFieldName) use (&$fattr,$f3,&$msg) {
	  if ($file['size'] == 0 || $file['error'] != 0) return false;

	  exec('md5sum '.$file['tmp_name'],$rout,$rc);
	  if ($rc != 0 || count($rout) != 1) {
	    $msg = 'md5sum error';
	    return false;
	  }
	  if (!preg_match('/^([0-9a-f]+)\s/',$rout[0],$mv)) {
	    $msg = 'invalid checksum';
	    return false;
	  }
	  if (strlen($mv[1]) != 32) {
	    $msg = 'invalid checksum';
	    return false;
	  }
	  $digest = $mv[1];

	  $pi = pathinfo($file['name']);
	  $ext = isset($pi['extension']) ? $pi['extension'] : '';

	  $fstore = $f3->get('FILESTORE');
	  if (!is_dir($fstore.Artifact::digest_dir($digest))) {
	    if (mkdir($fstore.Artifact::digest_dir($digest)) === false) {
	      $msg = 'mkdir('.Artifact::digest_dir($digest).') error';
	      return false;
	    }
	  }
	  // Check if file already exists!
	  if (is_file($fstore.Artifact::digest_fname($digest,$ext))) {
	    $msg = 'File already exists';
	    return false;
	  }
	  if (move_uploaded_file($file['tmp_name'],$fstore.Artifact::digest_fname($digest,$ext)) == false) {
	    $msg = 'error updating file';
	    return false;
	  }

	  $fattr['digest'] = $digest;
	  $fattr['mime-type'] = $file['type'];
	  $fattr['size'] = $file['size'];
	  $fattr['filename'] = $pi['filename'];
	  $fattr['extension'] = $ext;

	  return false;
	}, true, false);
      if (count($fattr) != 0) {

	$f3->set('POST.digest',$fattr['digest']);
	$f3->set('POST.size',$fattr['size']);
	$f3->set('POST.fileName',$fattr['filename']);
	$f3->set('POST.fileExtension',$fattr['extension']);
	$f3->set('POST.mimeType',$fattr['mime-type']);
	$f3->set('POST.tags',self::entag($f3->get('POST.tags'),$tag_list));
	if ($f3->get('POST.docName') == '') $f3->set('POST.docName',$fattr['filename']);

	$arts = new Artifact($this->db);
	$arts->reset();
	$arts->add();

	echo Sc::go('/artifact/msg/New artifact created','listing');
	echo '<pre>';
	echo 'POST'.PHP_EOL;
	var_dump($f3->get('POST'));
	echo 'TAGS'.PHP_EOL;
	var_dump($tag_list);
	echo '</pre>';
	return;

	$f3->reroute('/artifact/msg/New artifact created');
	return;
      } else {
	$f3->set('msg',$msg);
      }
    }
    if (isset($params['tags'])) {
      $res = [];
      foreach (preg_split('/\s*,\s*/',$params['tags']) as $tn) {
	$i = intval($tn);
	if (isset($tag_list[$i])) $res[$i] = $i;
      }
      $f3->set('POST.tags',$res);
    }

    Artifact::init_handlers();
    $f3->set('form_action',Sc::url('/artifact/create'));
    $f3->set('page_head','Create Artifact');
    $f3->set('form_command','create');
    $f3->set('form_label','New Artifact');

    $f3->set('tags',$tag_list);

    echo View::instance()->render('artifact-detail.html');
  }

  public function update($f3,$params) {
    $tags = new Tag($this->db);
    $tag_list = $tags->list_tags();

    $art = new Artifact($this->db);
    if ($f3->exists('POST.update')) {

      $f3->set('POST.tags',self::entag($f3->get('POST.tags'),$tag_list));
      $art->reset();
      $art->edit($params['id']);

      //~ echo '<pre>';
      //~ echo 'POST'.PHP_EOL;
      //~ var_dump($f3->get('POST'));
      //~ echo 'TAGS'.PHP_EOL;
      //~ var_dump($tag_list);
      //~ echo '</pre>';
      //~ echo Sc::go('/artifact/msg/Entry '.$params['id'].'  updated','listing');
      //~ return;
      $f3->reroute('/artifact/msg/Entry '.$params['id'].'  updated');
      return;
    }
    $art->get_by_id($params['id']);
    if (!$f3->get('POST.id')) {
      $f3->reroute('/artifact/msg/Lookup Error ('.$params['id'].')');
      return;
    }

    Artifact::gen_preview($f3->get('POST.digest'),
			  $f3->get('POST.mimeType'),
			  $f3->get('POST.fileExtension'));

    $f3->set('form_action',Sc::url('/artifact/update/'.$params['id']));
    $f3->set('page_head','Edit Artifact');
    $f3->set('form_command','update');
    $f3->set('form_label','Update');

    $f3->set('tags',$tag_list);

    echo View::instance()->render('artifact-detail.html');
  }

  public function delete($f3,$params) {
    if (isset($params['id'])) {
      $id = $params['id'];
      $art = new Artifact($this->db);
      $art->delete($id);
      $f3->reroute('/artifact/msg/Artifact '.$id.' deleted!');
    } else {
      $f3->reroute('/artifact/msg/No record deleted');
    }
  }
}
