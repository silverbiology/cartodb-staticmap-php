<?php

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);

include_once 'class.misc.php';
include_once 'class.cartodb-staticmap.php';

# Type of request
if (PHP_SAPI === 'cli') {
	# From Command Line
	$args = arguments($argv);
	$args = @array_merge($args['commands'], $args['options'], $args['flags'], $args['arguments']);

	$zoom = ($args['zoom'] == '') ? 12 : $args['zoom'];
	$table = ($args['table'] == '') ? 'testtable' : $args['table'];
	$filter['width'] = $args['width'];
	$filter['height'] = $args['height'];
	$filter['output'] = $args['output'];
	$filter['resizeMaxWidth'] = $args['resizeMaxWidth'];
	$filter['resizeMaxHeight'] = $args['resizeMaxHeight'];
	$style = $args['style'];
	$user = $args['user'];
	$coordinates['lat1'] = $args['lat1'];
	$coordinates['lon1'] = $args['lon1'];
	$coordinates['lat2'] = $args['lat2'];
	$coordinates['lon2'] = $args['lon2'];
	$filter['mode'] = (@strtolower($args['mode']) == 'tile') ? 'tile' : 'bounded';
	$filter['query'] = $args['q'];
	if($filter['query'] == '') {
		$fltr = $args['filter'];
		if($fltr != '') {
			$filter['filter'] = json_decode($fltr, true);
		}
	}
} else {
	# FROM POST or GET Request
	$zoom = ($_REQUEST['zoom'] == '') ? 12 : $_REQUEST['zoom'];
	$table = ($_REQUEST['table'] == '') ? 'testtable' : $_REQUEST['table'];
	$style = $_REQUEST['style'];
	$user = $_REQUEST['user'];

	$filter['width'] = $_REQUEST['width'];
	$filter['height'] = $_REQUEST['height'];
	$filter['output'] = $_REQUEST['output'];
	
	$filter['resizeMaxWidth'] = $_REQUEST['resizeMaxWidth'];
	$filter['resizeMaxHeight'] = $_REQUEST['resizeMaxHeight'];

	$filter['mode'] = (@strtolower($_REQUEST['mode']) == 'tile') ? 'tile' : 'bounded';
	
	$coordinates['lat1'] = $_REQUEST['lat1'];
	$coordinates['lon1'] = $_REQUEST['lon1'];
	$coordinates['lat2'] = $_REQUEST['lat2'];
	$coordinates['lon2'] = $_REQUEST['lon2'];

	$filter['query'] = trim($_REQUEST['q']);
	if($filter['query'] == '') {
		$fltr = $_REQUEST['filter'];
		if($fltr != '') {
			$filter['filter'] = json_decode($fltr, true);
		}
	}
}

if (($coordinates['lat1'] == '' && $coordinates['lat2'] == '') || ($coordinates['lon1'] == '' && $coordinates['lon2'] == '')) {
	die('The lat1/lon1 value(s) have to be provided.');
}

$tile = new CartoDBStaticmap($user, $table, $coordinates, $filter, $zoom, $style);
$tile->renderTiles();

if (PHP_SAPI === 'cli') {
	echo($tile->mapUrl);
} else {
	$fsize = filesize($tile->mapFile);
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false); // required for certain browsers
	header("Content-Type: image/png");
	header("Content-Disposition: attachment; filename=\"" . basename($tile->mapUrl) . "\";" );
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: " . $fsize);
	ob_clean();
	flush();
	readfile($tile->mapFile);
	unlink($tile->mapFile);
}

?>