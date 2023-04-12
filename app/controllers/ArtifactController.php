<?php
class ArtifactController extends CBaseController {
  public static $statuses = [
    0 => 'active',
    1 => 'retired',
    2 => 'archived',
  ];

  static public function scan_url($desc) {
    if (!Sc::f3()->exists('SCAN_URL')) return '';

    $txt = ': <a href="'.Sc::f3()->get('SCAN_URL').'?posturl=';
    $txt .= Sc::enc(Sc::url('/artifact/create'));
    $txt .= Sc::enc('?tags='.Sc::f3()->get('GET.tags'));
    $txt .= '">'.$desc.'</a>';

    return $txt;
  }


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
    if (empty($f3->get('QUERY'))) {
      if (!empty($_COOKIE['ARTIFACT_QUERY'])) {
	parse_str($_COOKIE['ARTIFACT_QUERY'],$get);
	$f3->set('GET',$get);
      }
    } else {
      setcookie('ARTIFACT_QUERY',$f3->get('QUERY'),
		[
		  'expires'=>time()+60*60*24*30 ,
		  'path'=>$f3->get('BASE'),
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
    $statuses = [-1=>'All statuses'];
    foreach (self::$statuses as $k=>$v) {
      $statuses[$k] = $v;
    }
    $f3->set('statuses',$statuses);
    $f3->set('expire_opts',[
      0 => ' ',
      -1 => 'Before',
      1 => 'After',
    ]);
    if (!$f3->exists('GET.expire_opts')) $f3->set('GET.expire_opts','0');
    if (!$f3->exists('GET.expdate') || $f3->get('GET.expire_opts') == 0 ) $f3->set('GET.expdate','');

    if (!$f3->exists('GET.status')) $f3->set('GET.status',0);

    $tag_filter = [];
    if ($f3->exists('GET.tags')) {
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
    if ($f3->get('GET.status') == -1) {
      $filter = NULL;
    } else {
      $filter = ['status = ?', $f3->get('GET.status')];
    }
    if ($f3->get('GET.expire_opts') != 0 && $f3->get('GET.expdate') != '') {
      if (is_null($filter)) {
	$filter = ['' ];
      } else {
	$filter[0] .= ' AND ';
      }
      if ($f3->get('GET.expire_opts') == -1) {
	$filter[0] .= 'expires < ?';
      } else {
	$filter[0] .= 'expires > ?';
      }
      $filter[] = $f3->get('GET.expdate');
    }
    //~ $f3->set('msg',print_r($filter,true));

    foreach ($arts->find($filter) as $row) {
      $id = $row['id'];
      if (count($tag_filter) > 0) {
	if (!isset($tagdata[$id])) {
	  if (!isset($tag_filter[0])) continue;
	} else {
	    //~ # Row without tags...
	    //~ if (!isset($tag_filter[0])) continue;
	  //~ }
	  $found = true;
	  foreach ($tag_filter as $tf) {
	    if (!isset($tagdata[$id][$tf])) {
	      $found = false;
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

  static public function receive_file($f3) {
    $fattr = [];
    $msg = NULL;
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
	  $fattr['duplicate'] = true;
	} else {
	  if (move_uploaded_file($file['tmp_name'],$fstore.Artifact::digest_fname($digest,$ext)) == false) {
	    $msg = 'error uploading file';
	    return false;
	  }
	  $fattr['duplicate'] = false;
	}
	$fattr['digest'] = $digest;
	$fattr['mime-type'] = $file['type'];
	$fattr['size'] = $file['size'];
	$fattr['filename'] = $pi['filename'];
	$fattr['extension'] = $ext;

	return false;
      }, true, false);
    return [$fattr,$msg];

  }


  public function create($f3,$params) {
    $tags = new Tag($this->db);

    if ($f3->exists('POST.create')) {
      list($fattr,$msg) = self::receive_file($f3);
      if (count($fattr) != 0) {
	$arts = new Artifact($this->db);
	$q = $arts->find(['digest = ?', $fattr['digest']]);
	if (count($q) != 0) {
	  // Existing record...
	  $msg = 'Artifact already exists';
	} else {
	  $yt = date('Y');
	  $tags->new_tag($yt);
	  $tag_list = $tags->list_tags();

	  if ($f3->exists('POST.tags')) {
	    $intags = $f3->get('POST.tags');
	  } elseif ($f3->exists('GET.tags')) {
	    $intags = $f3->get('GET.tags');
	  }
	  if ($intags != '') $intags .= ',';
	  $intags .= $yt;

	  $f3->set('POST.digest',$fattr['digest']);
	  $f3->set('POST.size',$fattr['size']);
	  $f3->set('POST.fileName',$fattr['filename']);
	  $f3->set('POST.fileExtension',$fattr['extension']);
	  $f3->set('POST.mimeType',$fattr['mime-type']);
	  $f3->set('POST.tags',self::entag($intags,$tag_list));
	  if ($f3->get('POST.docName') == '') $f3->set('POST.docName',$fattr['filename']);
	  $f3->set('POST.created', date('Y-m-d'));

	  if ($f3->get('POST.expiration_type') == 0) {
	    $f3->set('POST.expires',NULL);
	  } elseif ($f3->get('POST.expiration_type') == 1) {
	    $expdate = trim($f3->get('POST.expdate'));
	    if (preg_match('/^\d\d\d\d-\d\d-\d\d$/',$expdate)) {
	      $f3->set('POST.expires',$expdate);
	    } else {
	      $f3->set('POST.expires',NULL);
	    }
	  } elseif ($f3->get('POST.expiration_type') == 2) {
	    $months = intval(trim($f3->get('POST.months')));
	    if ($months == 0) {
	      $f3->set('POST.expires',NULL);
	    } else {
	      $year = intval(substr($f3->get('POST.created'),0,4));
	      $month = intval(substr($f3->get('POST.created'),5,2));
	      $day = intval(substr($f3->get('POST.created'),8,2));
	      $month += $months;
	      if ($month > 12) {
		# Figure out how many years need to be incremented
		$year += floor(($month-1)/12);
		$month = (($month-1)%12)+1;
	      }
	      $tm = mktime(0,0,0,$month,$day,$year);
	      $f3->set('POST.expires',date('Y-m-t',$tm));
	    }
	  }


	  $arts->reset();
	  $arts->add();
	  $id = $arts->get('_id');
	  if ($f3->get('POST.create') == 'scanned') {
	    echo "===============\n";
	    echo 'NEXT-URL:'.Sc::url('/artifact/update/'.$id).PHP_EOL;
	    echo "===============\n";
	    return;
	  }


	  //~ echo Sc::go('/artifact/msg/New artifact created','listing');
	  //~ echo '<pre>';
	  //~ echo 'POST'.PHP_EOL;
	  //~ var_dump($f3->get('POST'));
	  //~ echo 'TAGS'.PHP_EOL;
	  //~ var_dump($tag_list);
	  //~ echo '</pre>';
	  //~ return;

	  //~ $f3->reroute('/artifact/msg/New artifact created');
	  $f3->reroute('/artifact/gthumb/'.$id.'/New artifact '.$id.'  created');

	  return;
	}
      }
      $f3->set('msg',$msg);
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


    $f3->set('tags',$tags->list_tags());
    $f3->set('statuses', self::$statuses);

    echo View::instance()->render('artifact-detail.html');
  }

  public function update($f3,$params) {
    $tags = new Tag($this->db);
    $tag_list = $tags->list_tags();

    $art = new Artifact($this->db);
    if ($f3->exists('POST.update')) {

      $f3->set('POST.tags',self::entag($f3->get('POST.tags'),$tag_list));
      if ($f3->get('POST.expiration_type') == 0) {
	$f3->set('POST.expires',NULL);
      } elseif ($f3->get('POST.expiration_type') == 1) {
	$expdate = trim($f3->get('POST.expdate'));
	if (preg_match('/^\d\d\d\d-\d\d-\d\d$/',$expdate)) {
	  $f3->set('POST.expires',$expdate);
	} else {
	  $f3->set('POST.expires',NULL);
	}
      } elseif ($f3->get('POST.expiration_type') == 2) {
	$months = intval(trim($f3->get('POST.months')));
	if ($months == 0) {
	  $f3->set('POST.expires',NULL);
	} else {
	  $year = intval(substr($f3->get('POST.created'),0,4));
	  $month = intval(substr($f3->get('POST.created'),5,2));
	  $day = intval(substr($f3->get('POST.created'),8,2));
	  $month += $months;
	  if ($month > 12) {
	    # Figure out how many years need to be incremented
	    $year += floor(($month-1)/12);
	    $month = (($month-1)%12)+1;
	  }
	  $tm = mktime(0,0,0,$month,$day,$year);
	  $f3->set('POST.expires',date('Y-m-t',$tm));
	}
      }

      $art->reset();
      $art->edit($params['id']);

      //~ echo Sc::go('/artifact/msg/Entry '.$params['id'].'  updated','listing');
      //~ echo '<pre>';
      //~ echo 'POST'.PHP_EOL;
      //~ var_dump($f3->get('POST'));
      //~ echo 'TAGS'.PHP_EOL;
      //~ var_dump($tag_list);
      //~ echo '</pre>';
      //~ return;
      //~ $f3->reroute('/artifact/msg/Entry '.$params['id'].'  updated');
      $f3->reroute('/artifact/gthumb/'.$params['id'].'/Entry '.$params['id'].'  updated');
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
    $f3->set('statuses', self::$statuses);

    $exdate = $f3->get('POST.expires');
    if (is_null($exdate)) {
      $f3->set('POST.expiration_type', 0);
    } else {
      $md = mktime(0,0,0,
		intval(substr($exdate,5,2)),
		intval(substr($exdate,8,2)),
		intval(substr($exdate,0,4)));

      $f3->set('POST.expdate', $exdate);

      $d1 = new DateTime($f3->get('POST.created'));
      $d2 = new DateTime($exdate);
      $interval = $d1->diff($d2);
      $f3->set('POST.months',$interval->y*12+$interval->m);
	//~ echo '<pre>interval: ';
	//~ var_dump([$d1,$d2,$interval]);
	//~ echo '</pre>';
      if (intval(date('t',$md)) == intval(substr($exdate,8,2))) {
	$f3->set('POST.expiration_type', 2);
      } else {
	$f3->set('POST.expiration_type', 1);
      }
    }

    echo View::instance()->render('artifact-detail.html');
  }
  public function genthumb($f3,$params) {
    if (isset($params['id'])) {
      $art = new Artifact($this->db);
      $art->get_by_id($params['id']);
      if ($f3->get('POST.id')) {
	Artifact::gen_preview($f3->get('POST.digest'),
			  $f3->get('POST.mimeType'),
			  $f3->get('POST.fileExtension'));
      }
    }
    $msg = isset($params['msg']) ? $params['msg'] : '';
    $f3->reroute('/artifact/msg/'.$msg);
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
