<?php

$__dir = realpath(__DIR__);
chdir($__dir);

$tmpdir = "___temp";
@mkdir($tmpdir);

$thumbdir = "___thumbs";
@mkdir($thumbdir);

$show_unsupported = true;

function getFiles($path, $recursive = true, $withDirs = false){
    global $tmpdir, $thumbdir;
    $skip["./black.jpg"] = true;
    $skip["./cooliris.swf"] = true;
    $skip["./demo.html"] = true;
    $skip["./mymedia.php"] = true;
    $skip["./mymedia.xml"] = true;
    $skip["./$tmpdir"] = true;
    $skip["./$thumbdir"] = true;

    $aFiles = array();
    if($dh = opendir($path)){
        while(false !== ($file = readdir($dh))){
            if($file == '.' || $file == '..'){
                continue;
            }
            $file = $path . '/' . $file;
            if (isset($skip[$file])) continue;

            if(is_dir($file)){
                if($withDirs){
                    $aFiles[] = $file;
                }
                if($recursive){
                    $aFiles = array_merge($aFiles, getFiles($file, true, $withDirs));
                }
            }else{
                $aFiles[] = $file;
            }
        }
        closedir($dh);
    }
    sort($aFiles);
    return $aFiles;
}

function resize($src, $dst, $w, $h, $fit = true){
    if(file_exists($dst)) return true;
    $res = false;
    $image = new Imagick();
    try {
	$res = $image->readImage($src);
    } catch (Exception $e) {
	//var_dump($e);
	if ($e->getCode() != 420) {
	    fputs(STDERR, 'Caught exception: ' . $e->getMessage() . "\n");
	    //die();
	    return false;
	}
    }

    if($res) {
	fputs(STDERR, "IMAGICKED: $src to $dst\n");

	$image->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1, $fit);
	$image->setImageFormat('png');
	//$image->setImageCompression(Imagick::COMPRESSION_JPEG);
	//$image->setImageCompressionQuality(100);
	$image->stripImage();
        $image = $image->flattenImages();
        $image->writeImage($dst);
	$image->clear();
    }
    return true;
}

$q = '?';
echo "<{$q}xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\"{$q}>\n";
echo "<rss version=\"2.0\" xmlns:media=\"http://search.yahoo.com/mrss/\"
      xmlns:atom=\"http://www.w3.org/2005/Atom\">
      <channel>
          <title></title>
          <description></description>
          <link></link>";
/*
text/html .html
image/ .jpg .jpeg .png .gif
video/ .flv .mp4 .f4v
/x-shockwave-flash .swf
               <media:thumbnail url=\"$file\" time=\"00:00:00\"/>
*/


$files = getFiles(".");
//var_dump($files);
//die();

foreach($files as $file) {
    $link = $file;
    $info = pathinfo($file);
    $unlink = false;
    switch(strtolower($info["extension"])) {
	case "jpg":
	case "jpeg":
	case "gif":
	case "png":
	case "bmp":
	    $thumb = $file . "[0]";
	    $newthumb = "$thumbdir/".md5($link).".png";
	    $content = $file;
	    if(!resize($thumb, $newthumb, 150, 100)) continue 2;
	    break;
	case "xls":
	case "xlsx":
	case "csv":
	case "rtf":
	case "doc":
	case "docx":
	    //$cmd = "unoconv -f pdf -o temp.pdf '$file'";
	    $cmd = "soffice --headless --convert-to pdf --outdir $tmpdir '$file'";
	    fputs(STDERR, `$cmd`);
	    $file = "$tmpdir/{$info['filename']}.pdf";
	    $unlink = true;
	case "pdf":
	    $thumb = $file . "[0]";
	    $newthumb = "$thumbdir/".md5($link).".png";
	    if(!resize($thumb, $newthumb, 150, 100)) continue 2;
	    $content = "$thumbdir/".md5($link)."_big.png";
	    if(!resize($thumb, $content, 1000, 1000)) continue 2;
	    if ($unlink) {
		unlink($file);
		$unlink = false;
	    }
	    break;
	default:
	    if(!$show_unsupported) continue 2;
	    $thumb = $newthumb = "black.jpg";
	    $content = $file;
    }

//               <link>$link</link>
//               <media:description>.</media:description>

    echo "
          <item>
               <title>$link</title>
               <media:thumbnail url=\"$newthumb\"/>
               <media:content url=\"$content\"/>
          </item>
    ";
}

echo "      </channel>
</rss>";

@rmdir($tmpdir);
