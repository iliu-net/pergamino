<?php
class TagController extends CBaseController {
  public function index($f3,$params) {
    if (isset($params['msg'])) {
      $f3->set('msg',$params['msg']);
    } else {
      $f3->set('msg','');
    }
    $tags = new Tag($this->db);
    $f3->set('tagCounts',$tags->count_tags());
    $f3->set('tags',$tags->find());
    echo View::instance()->render('tag-list.html');
  }

  public function create($f3,$params) {
    if ($f3->exists('POST.create')) {
      $tags = new Tag($this->db);
      $tags->reset();
      $tags->add();
      $f3->reroute('/tag/msg/New tag created');
      return;
    }
    $f3->set('form_action',Sc::url('/tag/create'));
    $f3->set('page_head','Create Tag');
    $f3->set('form_command','create');
    $f3->set('form_label','New Tag');
    echo View::instance()->render('tag-detail.html');
  }


  public function update($f3,$params) {
    $tags = new Tag($this->db);
    if ($f3->exists('POST.update')) {
      //~ echo '<pre>';
      //~ echo '$params'.PHP_EOL;
      //~ var_dump($params);
      //~ echo 'POST'.PHP_EOL;
      //~ var_dump($f3->get('POST'));
      //~ echo '</pre>';
      $tags->reset();
      $tags->edit($params['id']);
      $f3->reroute('/tag/msg/Entry '.$params['id'].'  updated');
      return;
    }
    $tags->get_by_id($params['id']);
    //~ echo '<pre>';
    //~ echo '$params'.PHP_EOL;
    //~ var_dump($params);
    //~ echo 'POST'.PHP_EOL;
    //~ var_dump($f3->get('POST'));
    //~ echo '</pre>';
    //~ return;
    if (!$f3->get('POST.id')) {
      $f3->reroute('/tag/msg/Lookup Error ('.$params['id'].')');
      return;
    }
    $f3->set('form_action',Sc::url('/tag/update/'.$params['id']));
    $f3->set('page_head','Edit Tag');
    $f3->set('form_command','update');
    $f3->set('form_label','Update');
    $f3->set('POST.count', $tags->count_tags($params['id']));
    echo View::instance()->render('tag-detail.html');
  }

  public function delete($f3,$params) {
    if (isset($params['id'])) {
      $id = $params['id'];
      $tags = new Tag($this->db);

      $tags->delete($id);
      $f3->reroute('/tag/msg/Entry '.$id.' deleted!');
    } else {
      $f3->reroute('/tag/msg/No record deleted');
    }
  }
}
