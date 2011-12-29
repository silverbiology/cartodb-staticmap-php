<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors',1);

#include_once 'config.php';
include_once '../staticmap/class.cartodb-staticmap.php';

# FROM POST or GET Request
$zoom = ($_REQUEST['zoom'] == '') ? 12 : $_REQUEST['zoom'];
$table = ($_REQUEST['table'] == '') ? 'testtable' : $_REQUEST['table'];

$filter['width'] = $_REQUEST['width'];
$filter['height'] = $_REQUEST['height'];
$filter['output'] = $_REQUEST['output'];

$filter['resizeMaxWidth'] = $_REQUEST['resizeMaxWidth'];
$filter['resizeMaxHeight'] = $_REQUEST['resizeMaxHeight'];

$filter['mode'] = (@strtolower($_REQUEST['mode']) == 'tile') ? 'tile' : 'bounded';

$coordinates['lat1'] = $_REQUEST['lat1'];
$coordinates['lon1'] = $_REQUEST['lon1'];
// $coordinates['lat2'] = $_REQUEST['lat2'];
// $coordinates['lon2'] = $_REQUEST['lon2'];

$filter['query'] = trim($_REQUEST['q']);
if($filter['query'] == '') {
	$fltr = $_REQUEST['filter'];
	if($fltr != '') {
		$filter['filter'] = json_decode($fltr, true);
	}
}

if(($coordinates['lat1'] == '' && $coordinates['lat2'] == '') || ($coordinates['lon1'] == '' && $coordinates['lon2'] == '')) {
	die('lat1/lon1 value has to be provided');
}

$tile = new CartoDBTiler('silverbiology', $table, $coordinates, $filter, $zoom);

// $tile->renderTiles();
echo '<pre>';
echo '<br>';

	$metres = $tile->LatLonToMeters($coordinates['lat1'],$coordinates['lon1']);
	echo '<br>';print_r($metres);
	$t = $tile->MetersToTile($metres[0],$metres[1],$zoom);
	print_r($t);
	$bounds = $tile->TileBounds($t[0], $t[1], $zoom);
	print_r($bounds);
 
$cornerOfTile = $tile->MetersToPixels($bounds[0], $bounds[1], $zoom);
$positionOfPoint = $tile->MetersToPixels($metres[0], $metres[1], $zoom);
echo '<br> Corner Of the Tile <br>';
print_r( $cornerOfTile );
echo '<br> Position Of the Point <br>';
print_r( $positionOfPoint );

echo '<br>';
print "X: " . ($positionOfPoint[0] - $cornerOfTile[0]);
print "<br>Y: " . ($positionOfPoint[1] - $cornerOfTile[1]);

?>