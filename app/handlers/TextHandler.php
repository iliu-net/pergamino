<?php
require_once('submodules/html2text/src/Html2Text.php');

class TextHandler implements MimeHandler {
  const MIME_TYPE = 'text/*';
  const CLASS_NAME = __CLASS__;
  static public function static_preview() {
    return '/ui/imgs/ft-text-128.png';
  }
  static public function gen_preview($digest,$ext) {
    $fstore = Sc::f3()->get('FILESTORE');
    self::txt2png($fstore.Artifact::digest_fname($digest,$ext),
		  $fstore.Artifact::digest_pname($digest));
  }
  static public function txt2png($input,$output) {
    # 1st convert to PS using enscript...
    list($fp,$fname) = DefaultHandler::mkstemp(Sc::f3()->get('FILESTORE').'cnv-XXXXXX.ps');
    if (!$fp) return false;
    fclose($fp);

    exec('enscript -1 --pages=1 --no-header --truncate-lines --margins=0:0:0:0 --output='.
	      escapeshellarg($fname).' '.escapeshellarg($input),
	      $out,$rc);
    if ($rc != 0) {
      unlink($fname);
      return false;
    }
    # 2nd convert PS to PNG...
    exec('convert '.escapeshellarg($fname).
	  ' -background white -alpha remove -alpha off -resize 600x800 -gravity NorthWest -crop 128x128+0+0 '.
	  escapeshellarg($output),
	  $out,$rc);
    unlink($fname);
    if ($rc == 0) return true;
    unlink($output);
    return false;
  }

}

class TextHtmlHandler implements MimeHandler {
  const MIME_TYPE = 'text/html';
  const CLASS_NAME = __CLASS__;
  static public function static_preview() {
    return '/ui/imgs/ft-html-128.png';
  }

  static public function html2txt($input) {
    list($fp,$output) = DefaultHandler::mkstemp(Sc::f3()->get('FILESTORE').'cnv-XXXXXX.txt');
    if (!$fp) return NULL;
    $html = file_get_contents($input);
    $cnv = new \Html2Text\Html2Text($html);
    fwrite($fp,$cnv->getText());
    fclose($fp);
    return $output;
  }

  static public function gen_preview($digest,$ext) {
    $fstore = Sc::f3()->get('FILESTORE');
    $txtfile = self::html2txt($fstore.Artifact::digest_fname($digest,$ext));
    if (is_null($txtfile)) return;
    TextHandler::txt2png($txtfile,
		  $fstore.Artifact::digest_pname($digest));
    unlink($txtfile);
  }
}

Artifact::add_handler(TextHandler::CLASS_NAME, TextHandler::MIME_TYPE,'text/plain,.md');
Artifact::add_handler(TextHtmlHandler::CLASS_NAME, TextHtmlHandler::MIME_TYPE);
