<?php
class PdfHandler implements MimeHandler {
  const MIME_TYPE = 'application/pdf';
  const CLASS_NAME = __CLASS__;
  static public function static_preview() {
    return '/ui/imgs/ft-pdf-128.png';
  }
  static public function gen_preview($digest,$ext) {
    $fstore = Sc::f3()->get('FILESTORE');
    $src = $fstore.Artifact::digest_fname($digest,$ext);
    $dst = $fstore.Artifact::digest_pname($digest);
    exec('convert -density 75 '.escapeshellarg($src).'[0] '.
	  ' -background white -alpha remove -alpha off '.
	  ' -resize 150x200 -crop 128x128+0+0 '.
	  escapeshellarg($dst));
  }
}

Artifact::add_handler(PdfHandler::CLASS_NAME, PdfHandler::MIME_TYPE);
