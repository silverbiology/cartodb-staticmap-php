<?php

# Some functions derived from http://code.google.com/p/gmap-tile-generator/source/browse/trunk/gmaps-tile-creator/src/gov/ca/maps/tile/geom/GlobalMercator.java
Class CartoDBStaticmap {

	public $TILE_SIZE = 256;
	private $tileSize;
	private $initialResolution;
	private $originShift;
	private $singlePoint = false;
	
	public function __construct($user = '', $table = '', $coordinates = array(), $filter = array(), $zoom = 6, $style = '', $cachePath = "cache/") {
		$this->maxTiles = 100;
		$this->tileSize = $this->TILE_SIZE;
		$this->initialResolution = 2 * pi() * 6378137 / $this->tileSize;
		$this->originShift = 2 * pi() * 6378137 / 2.0;
		$this->user = $user;
		$this->table = $table;
		$this->zoom = ($zoom == '') ? 6 : $zoom;
		$this->coordinates = $coordinates;
		$this->mode = (@strtolower($filter['mode']) == 'tile') ? 'tile' : 'bounded';
		$this->sql = ($filter['query'] != '') ? urlencode($filter['query']) : '';
		$this->uID = uniqid();
		$this->output = ($filter['output'] == '') ?  $this->uID . '.png' : $filter['output'];
		$this->filter = $filter['filter'];
		
		if ($style != '') {
			$this->cartocss = $style;
		}

		$this->width = $filter['width'];
		$this->height = $filter['height'];

		$this->resizeMaxWidth = $filter['resizeMaxWidth'];
		$this->resizeMaxHeight = $filter['resizeMaxHeight'];

		$this->cartodbUrl = 'http://' . $this->user . '.cartodb.com/tiles/' . $this->table . '/';

		$this->cachePath = $cachePath;
		
		$this->mapFile = $this->cachePath . $this->output;
		$this->mapUrl = $this->cachePath . $this->output;

		$this->setFactorValues();
		if ($this->sql == '') {
			$this->generateSQL();
		}

	}

	# Helper Methods
	public function LatLonToMeters($lat, $lon) {
		$mx = $lon * $this->originShift / 180.0;
		$my = log(tan((90 + $lat) * pi() / 360.0)) / (pi() / 180.0);
		$my = $my * $this->originShift / 180.0;
		return array($mx, $my);
	}

	public function MetersToLatLon($mx, $my) {
		$lon = ($mx / $this->originShift) * 180.0;
		$lat = ($my / $this->originShift) * 180.0;
		$lat = 180 / pi() * (2 * atan(exp($lat * pi() / 180.0)) - pi() / 2.0);
		return array($lat, $lon);
	}
	
	public function PixelsToMeters($px, $py, $zoom) {
		$res = $this->Resolution($zoom);
		$mx = $px * $res - $this->originShift;
		$my = $py * $res - $this->originShift;
		return array($mx, $my);
	}
	
	public function MetersToPixels($mx, $my, $zoom) {
		$res = $this->Resolution($zoom);
		$px = round(($mx + $this->originShift) / $res);
		$py = round(($my + $this->originShift) / $res);
		return array($px, $py);
	}
	
	public function PixelsToTile($px, $py) {
		$tx = ceil($px / ($this->tileSize) - 1);
		$ty = ceil($py / ($this->tileSize) - 1);
		return array($tx, $ty);
	}
	
	public function PixelsToRaster($px, $py, $zoom) {
		$mapSize = $this->tileSize << $zoom;
		return array( $px, ($mapSize - $py));
	}
	
	public function MetersToTile($mx, $my, $zoom) {
		$p = $this->MetersToPixels($mx, $my, $zoom);
		return $this->PixelsToTile($p[0], $p[1]);
	}
	
	public function TileBounds($tx, $ty, $zoom) {
		$min = $this->PixelsToMeters($tx * $this->tileSize, $ty * $this->tileSize, $zoom);
		$minx = $min[0];
		$miny = $min[1];
		$max = $this->PixelsToMeters(($tx + 1) * $this->tileSize, ($ty + 1) * $this->tileSize, $zoom);
		$maxx = $max[0];
		$maxy = $max[1];
		return array($minx, $miny, $maxx, $maxy);
	}

	public function TileLatLonBounds($tx, $ty, $zoom) {
		$bounds = $this->TileBounds($tx, $ty, $zoom);
		$mins = $this->MetersToLatLon($bounds[0], $bounds[1]);
		$maxs = $this->MetersToLatLon($bounds[2], $bounds[3]);
		return array($mins[0], $mins[1], $maxs[0], $maxs[1]);
	}
	
	public function Resolution($zoom) {
		return ($this->initialResolution / pow(2, $zoom));
	}
	
	public function ZoomForPixelSize($pixelSize) {
		for ($i = 0; $i < 30; $i++) {
			if ($pixelSize > $this->Resolution($i)) {
				if ($i != 0) {
					return $i - 1;
				} else {
					return 0; // We don't want to scale up
				}
			}
		}
		return 0;
	}
	
	public function TMSTileFromGoogleTile($tx, $ty, $zoom) {
		return array($tx, ((pow(2, $zoom) - 1) - $ty) );
	}
	
	public function GoogleTile($lat, $lon, $zoom) {
		$meters = $this->LatLonToMeters($lat, $lon);
		$tile = $this->MetersToTile($meters[0], $meters[1], $zoom);
		return $this->GTile($tile[0], $tile[1], $zoom);
	}
	
	public function GTile($tx, $ty, $zoom) {
		return array($tx, ((pow(2, $zoom) - 1) - $ty) );
	}

	public function PixelToGoogleTile($px, $py, $zoom) {
		$tile = $this->PixelsToTile($px, $py);
		return $this->GTile($tile[0], $tile[1], $zoom);
	}

	# Core Methods
	public function setFactorValues() {
		$this->point1 = $this->getLocationPoint($this->coordinates['lat1'], $this->coordinates['lon1'], $this->zoom);
		$ar = $this->GoogleTile($this->coordinates['lat1'], $this->coordinates['lon1'], $this->zoom);

		$this->tileOffset["x"] = 0;
		$this->tileOffset["y"] = 0;
		$this->x[] = $ar[0];
		$this->y[] = $ar[1];

		if( $this->coordinates['lat2'] == '' && $this->coordinates['lon2'] == '' ) {
			# Single Point
			$this->originalCell["x"] = $ar[0];
			$this->originalCell["y"] = $ar[1];
			$this->singlePoint = true;
			if($this->width == '' && $this->height == '') {
				$this->x[] = $this->x[0];
				$this->y[] = $this->y[0];
			} else {
				$x = floor($this->width/2/$this->tileSize);
				$y = floor($this->height/2/$this->tileSize);
				$this->tileCount["x"] = $x;
				$this->tileCount["y"] = $y;

				if ($this->point1["x"] > ($this->tileSize / 2)) {
					$this->tileOffset["x"] = 1;
					$this->x[] = $this->x[0] + $x + 1;
				}
				if ($this->point1["x"] < ($this->tileSize / 2)) {
					$this->tileOffset["x"] = -1;
					$this->x[] = $this->x[0] + $x;
					$this->x[0]--;
				}

				if ($this->point1["y"] < ($this->tileSize / 2)) {
					$this->tileOffset["y"] = 1;
					$this->y[] = $this->y[0] + $y + 1;					
				}
				if ($this->point1["y"] > ($this->tileSize / 2)) {
					$this->tileOffset["y"] = -1;
					$this->y[] = $this->y[0] + $y;
					$this->y[0]--;
				}
			}
		} else {
			# Viewport Box
			$this->point2 = $this->getLocationPoint($this->coordinates['lat2'], $this->coordinates['lon2'], $this->zoom);
			$ar = $this->GoogleTile($this->coordinates['lat2'], $this->coordinates['lon2'], $this->zoom);
			$this->x[] = $ar[0];
			$this->y[] = $ar[1];
		}
		sort($this->x);
		sort($this->y);
	}

	public function generateSQL() {
		$sql = sprintf("SELECT * FROM %s ", mysql_escape_string($this->table));
		if(is_array($this->filter) && count($this->filter)) {
			$sql .= ' WHERE 1=1 ';
			foreach($this->filter as $key => $value) {
				$sql .= sprintf(" AND %s = '%s'", $key, mysql_escape_string($value));
			}
		}
		$this->sql = urlencode($sql);
	}

	public function clearCache() {
#		$path = ($this->cachePath == '') ? 'cache/' : $this->cachePath;
		$cmd = 'rm -rf ' . $this->cachePath . $this->uID . '/';
// echo '<br>';echo $cmd;exit;
		system($cmd);
	}

	# getting tile values
	public function setTileValues() {
		$cnt = 0;
		$files = array();
		for($j=$this->y[0]; $j <= $this->y[1]; $j++) {
			$row = array();
			for($i=$this->x[0]; $i <= $this->x[1]; $i++) {
				$row[] = $i . '_' . $j;
				$cnt++;
			}
			$files[] = $row;
		}
		$this->files = $files;
		$this->tileCount = $cnt;
	}

	# creating tiles
	public function createTiles() {
		if(count($this->files)) {
			mkdir($this->cachePath . $this->uID, 0755);
			foreach($this->files as $row) {
				foreach($row as $tle) {
					list($i,$j) = explode('_', $tle);
					$query = $this->cartodbUrl . $this->zoom . '/' . $i . '/' . $j . '.png?sql=' . $this->sql . '&style=' . urlencode($this->cartocss);
					$tFile = @file_get_contents($query);
					if (!$tFile) {
						# Failed to get the tile from the server so we show a place holder in case it was only that tile.
						file_put_contents($this->cachePath . $this->uID . '/' . $i . '_' . $j . '.png', @file_get_contents('bad-256.png') );
					} else {
						if (!@file_put_contents($this->cachePath . $this->uID . '/' . $i . '_' . $j . '.png', $tFile)) {
							exit("Permission denied to save to " . $this->cachePath . $this->uID . '/');
						}
					}
				}
			}
		}
	}

	# Merge tiles
	public function mergeTiles() {
		if(count($this->files) == 1 && count($this->files[0]) == 1) {
			rename($this->cachePath . $this->uID . '/' . $this->files[0][0] . '.png', $this->mapFile);
		} else {
			$rows = array();
			foreach($this->files as $row) {
				array_walk($row, 'setPath', $this->cachePath . $this->uID . '/');
				$rows[] = implode(' ',$row) . ' +append ';
			}
			if(count($rows) > 1) {
				$cmd = 'convert \( ' . implode(' \) \( ',$rows) . ' \) -append ' . $this->mapFile;
			} else {
				$cmd = 'convert ' . implode(' ',$rows) . $this->mapFile;
			}
			system($cmd);
		}
	}

	public function cleanUp() {
		# deleting the tiles
		for($j=$this->y[0]; $j <= $this->y[1]; $j++) {
			for($i=$this->x[0]; $i <= $this->x[1]; $i++) {
				@unlink($this->cachePath . $this->uID . '/' . $i . '_' . $j . '.png');
			}
		}
	}

	public function checkSize() {
		if($this->resizeMaxWidth != '' && $this->resizeMaxHeight != '') {
			list($width,$height) = getimagesize( $this->mapFile);
			if($width > $this->resizeMaxWidth || $height > $this->resizeMaxHeight) {
				$cmd = sprintf("convert %s -resize %dx%d %s", $this->mapFile, $this->resizeMaxWidth, $this->resizeMaxHeight, $this->mapFile);
				system($cmd);
			}
		}
	}

	public function getLocationPoint($lat1,$lon1,$zoom) {
		$metres = $this->LatLonToMeters($lat1,$lon1);
		$t = $this->MetersToTile($metres[0],$metres[1],$zoom);
		$bounds = $this->TileBounds($t[0], $t[1], $zoom);
		$cornerOfTile = $this->MetersToPixels($bounds[0], $bounds[1], $zoom);
		$positionOfPoint = $this->MetersToPixels($metres[0], $metres[1], $zoom);
		return array("x" =>($positionOfPoint[0] - $cornerOfTile[0]), "y" => $this->tileSize - ($positionOfPoint[1] - $cornerOfTile[1]));
	}


	public function trimToPoint() {
		list($width, $height) = getImageSize($this->mapFile);

		if($this->singlePoint) {
			$center["x"] = ((($this->x[1] - $this->x[0]) + 1) * $this->tileSize)/2;
			$center["y"] = ((($this->y[1] - $this->y[0]) + 1) * $this->tileSize)/2;
			$x = $this->width;
			$y = $this->height;

			$calcX = (($this->originalCell["x"] - $this->x[0]) * $this->tileSize) + $this->point1["x"];
			$xOffset = (($center["x"] - $calcX) / 2) * -1;
			$calcY = (($this->originalCell["y"] - $this->y[0]) * $this->tileSize) + $this->point1["y"];
			$yOffset = ($center["y"] - $calcY) * -1;
			$gravity = '-gravity center';
		} else {
			$xFactor = $yFactor = 0;
			$xFactor = ($this->tileSize - $this->point2["x"]);
			$x = $width - $this->point1["x"] - $xFactor;
			$yFactor = ($this->tileSize - $this->point2["y"]);
			$y = $height - $this->point1["y"] - $yFactor;
			$xOffset = $this->point1["x"];
			$yOffset = $this->point1["y"];
			$gravity = '';
		}

		if ($xOffset >= 0) $xOffset = "+" . strval($xOffset);
		if ($yOffset >= 0) $yOffset = "+" . strval($yOffset);

		$cmd = sprintf("convert %s %s -crop %dx%d%s%s %s"
			, $this->mapFile
			, $gravity
			, $x
			, $y
			, $xOffset
			, $yOffset
			, $this->mapFile
		);
		
#print "<pre>";	echo '<br>';print_r($this->point1); echo '<br>' . $cmd; print_r($this);
#exit();

		system($cmd);
	}

	public function renderTiles() {
		$this->setTileValues();
		if($this->tileCount > $this->maxTiles) {
			die('Too many tiles. The max tiles is set at: ' . $this->maxTiles . ' and your tile count is: ' . $this->tileCount);
		}
		$this->createTiles();
		$this->mergeTiles();
		if($this->mode != 'tile') {
			$this->trimToPoint();
		}
		$this->checkSize();
		$this->clearCache();
#		$this->cleanUp();
	}

}

# Used for php array_walk callback function
function setPath(&$value, $key, $path) {
	$value = $path . $value . '.png';
}

?>