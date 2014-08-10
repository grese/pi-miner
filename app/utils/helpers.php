<?php
function serveStaticFile($file){
	$html = file_get_contents($file);
	return $html;
}