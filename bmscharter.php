<?php

// DtBMS
// By: the DtTvB (http://blog.dt.in.th/)

if ($_FILES['bmsf']['error']) {
	die ('File uploading failed.... Maybe file size too large?');
}

$filename = $_FILES['bmsf']['name'];
if (substr(strtolower($filename), -4) != '.bms' && substr(strtolower($filename), -4) != '.ojn') {
	die ('Only .BMS and .OJN files are allowed!');
}

define (NOTEPAD, 80);

$map  = array(
	'16' => 0,
	'11' => 1,
	'12' => 2,
	'13' => 3,
	'14' => 4,
	'15' => 5,
	'18' => 6,
	'56' => 10,
	'51' => 11,
	'52' => 12,
	'53' => 13,
	'54' => 14,
	'55' => 15,
	'58' => 16,
	'03' => 'BPM',
	'08' => 'BZM'
);

if ($_POST['mapmode'] == 'new') {
	$map  = array(
		'11' => 0,
		'12' => 1,
		'13' => 2,
		'14' => 3,
		'15' => 4,
		'18' => 5,
		'19' => 6,
		'51' => 10,
		'52' => 11,
		'53' => 12,
		'54' => 13,
		'55' => 14,
		'58' => 15,
		'59' => 16,
		'03' => 'BPM',
		'08' => 'BZM'
	);
}

$data = file_get_contents($_FILES['bmsf']['tmp_name'], FILE_BINARY);
$ojnmode = false;

if (substr($data, 4, 3) == 'ojn') {
	$ojnmode = true;
}

if ($ojnmode) {

	$bmap = array();
	$note = array();
	$maxb = 0;
	$bmn = 0;

	$l = unpack(implode('/', array(
		'lsongid',
		'c8signature',
		'lgenre',
		'fbpm',
		's3level',
		'sunk',
		'l12unk',
		'a24genres',
		'l2unk',
		'a32title',
		'a32subtitle',
		'a32artist',
		'a32noter',
		'a32musicfile',
		'ljpg',
		'l3unk',
		'l4pos'
	)), substr($data, 0, 300));
	
	$notepos = array(
		$l['pos1'], $l['pos2'], $l['pos3'], $l['pos4']
	);
	$lvl = intval($_POST['notelevel']);
	$startpos = $notepos[$lvl];
	$endpos = $notepos[$lvl + 1];
	$lvll = array('[Ex] ', '[Nx] ', '[Hx] ');
	
	$map  = array(
		1 => 'BZM',
		2 => 0,
		3 => 1,
		4 => 2,
		5 => 3,
		6 => 4,
		7 => 5,
		8 => 6
	);
	
	$fp = fopen($_FILES['bmsf']['tmp_name'], 'rb');
	fseek ($fp, $startpos);
	while (!feof($fp) && ftell($fp) < $endpos) {
		$measure = reset(unpack('l', fread($fp, 4)));
		$channel = reset(unpack('s', fread($fp, 2)));
		$length  = reset(unpack('s', fread($fp, 2)));
		if (isset($map[$channel])) {
			for ($i = 0; $i < $length; $i ++) {
				if ($channel == 1) {
					$value = unpack('fvalue', fread($fp, 4));
				} else {
					$value = unpack('svalue/Czero/Cflag', fread($fp, 4));
				}
				if ($value['value'] == 0) continue;
				$beat = (4 * ($measure + ($i / $length)));
				if ($beat > $maxb)
					$maxb = $beat;
				
				if ($channel == 1) {
					$bmap[++$bmn] = $value['value'];
					$value['value'] = $bmn;
					$mychan = 'BZM';
				} else {
					$mychan = $map[$channel] + ($value['flag'] & 2 ? 10 : 0);
				}
				$note[] = array(
					'channel' => $mychan,
					'beat'    => $beat,
					'value'   => $value['value'],
					'rawchan' => $channel
				);
			}
		} else {
			fread ($fp, 4 * $length);
		}
	}
	fclose ($fp);
	
	$data = '
#title ' . ($lvll[$lvl]) . $l['artist'] . ' - ' . $l['title'] . '
#artist ' . $l['noter'] . '';
	$mybpm = $l['bpm'];
	//print_r ($note);
	//die ();

} else {

	$bmap = array();
	if (preg_match_all('~^#bpm(..) (.*)$~im', $data, $bpms, PREG_SET_ORDER)) {
		foreach ($bpms as $v) {
			$bmap[strtolower($v[1])] = floatval($v[2]);
		}
	}

	$lnes = explode("\n", $data);
	$note = array();
	$long = array();
	$maxb = 0;
	foreach ($lnes as $v) {
		$line = strtolower(trim($v));
		if (preg_match('~^#(\\d{3})(\\d\\d):~', $line, $match)) {
			$measure = intval($match[1]);
			$channel = $match[2];
			if (isset($map[$channel])) {
				if (strlen($line) > 7) {
					$broken = str_split(substr($line, 7), 2);
					$length = count($broken);
					for ($i = 0; $i < $length; $i ++) {
						if ($broken[$i] == '00') continue;
						$beat = (4 * ($measure + ($i / $length)));
						if ($beat > $maxb)
							$maxb = $beat;
						$note[] = array(
							'channel' => $map[$channel],
							'beat'    => $beat,
							'value'   => $broken[$i],
							'rawchan' => $channel
						);
					}
				}
			}
		}
	}

	$mybpm = 0;
	if (preg_match('~^#bpm (.*)$~im', $data, $m)) {
		$mybpm = floatval($m[1]);
	}

}

$notesize  = 7;
$notewidth = 20;
$beatsize  = 48;

$im = imagecreate(($notewidth * 7) + 2 * NOTEPAD, ($maxb * $beatsize) + 350);
$bg = imagecolorallocate($im, 0, 0, 0);

$bottom = ($maxb * $beatsize) + 300;

$color    = array();
$color[0] = imagecolorallocate($im, 255, 255, 255);
$color[4] = imagecolorallocate($im, 50, 50, 50);
$color[5] = imagecolorallocate($im, 120, 120, 120);
$color[6] = imagecolorallocate($im, 255, 150, 150);
$color[7] = imagecolorallocate($im, 180, 180, 180);

define ('MTP', 0.8);
$color[1] = imagecolorallocate($im, 240, 240, 240);
$color[2] = imagecolorallocate($im, 150, 200, 250);
$color[3] = imagecolorallocate($im, 250, 200, 150);
$color[11] = imagecolorallocate($im, 240 * MTP, 240 * MTP, 240 * MTP);
$color[12] = imagecolorallocate($im, 150 * MTP, 200 * MTP, 250 * MTP);
$color[13] = imagecolorallocate($im, 250 * MTP, 200 * MTP, 150 * MTP);
$color[21] = imagecolorallocate($im, 240, 240, 240);
$color[22] = imagecolorallocate($im, 150, 200, 250);
$color[23] = imagecolorallocate($im, 250, 200, 150);
$color[31] = imagecolorallocate($im, 240 * MTP, 240 * MTP, 240 * MTP);
$color[32] = imagecolorallocate($im, 150 * MTP, 200 * MTP, 250 * MTP);
$color[33] = imagecolorallocate($im, 250 * MTP, 200 * MTP, 150 * MTP);
$lnstyle = 0;

if ($_POST['theme'] == 'new') {
	$lnstyle = 1;
	$color[1] = imagecolorallocate($im, 241, 241, 243);
	$color[2] = imagecolorallocate($im, 68,  212, 246);
	$color[3] = imagecolorallocate($im, 254, 204, 83);
	$color[11] = imagecolorallocate($im, 241 * MTP, 241 * MTP, 243 * MTP);
	$color[12] = imagecolorallocate($im, 68  * MTP, 212 * MTP, 246 * MTP);
	$color[13] = imagecolorallocate($im, 254 * MTP, 204 * MTP, 83  * MTP);
	$color[21] = imagecolorallocate($im, 248, 243, 247);
	$color[22] = imagecolorallocate($im, 130, 231, 241);
	$color[23] = imagecolorallocate($im, 255, 246, 157);
	$color[31] = imagecolorallocate($im, 248 * MTP, 243 * MTP, 247 * MTP);
	$color[32] = imagecolorallocate($im, 130 * MTP, 231 * MTP, 241 * MTP);
	$color[33] = imagecolorallocate($im, 255 * MTP, 246 * MTP, 157 * MTP);
}

$ih = imagesy($im);
for ($i = 0; $i <= 7; $i ++) {
	$x = NOTEPAD + ($i * $notewidth);
	imageline ($im, $x, 0, $x, $ih, $color[4]);
}

$x  = NOTEPAD;
$x2 = NOTEPAD + (7 * $notewidth);
for ($i = 0; $i <= $maxb; $i ++) {
	$y  = $bottom - ($beatsize * $i) + $notesize;
	imageline ($im, $x, $y, $x2, $y, $color[4]);
	if ($i % 4 == 0) {
		imageline ($im, $x, $y - 1, $x2, $y - 1, $color[5]);
		imagestring ($im, 5, NOTEPAD + 4 + (7 * $notewidth), $y - 10, '#' . str_pad($i / 4, 3, '0', STR_PAD_LEFT), $color[5]);
	}
}

imageline ($im, NOTEPAD, 0, NOTEPAD, $ih, $color[5]);
imageline ($im, NOTEPAD + (7 * $notewidth), 0, NOTEPAD + (7 * $notewidth), $ih, $color[5]);

$x = imagesx($im);
for ($i = 1; $i < 260; $i += 2) {
	imageline ($im, 0, $i, $x, $i, $bg);
}

$lch  = array();
$stat = array(
	'tap'  => 0,
	'long' => 0,
	'min'  => $mybpm,
	'max'  => $mybpm
);

foreach ($note as $v) {
	$channel = $v['channel'];
	if ($channel === 'BPM' || $channel === 'BZM') {
		$newbpm = ($channel === 'BZM' ? $bmap[$v['value']] : hexdec($v['value']));
		$y = $bottom - ($beatsize * $v['beat']) + $notesize;
		$text = number_format($newbpm, 2);
		imagestring ($im, 5, NOTEPAD - 4 - (strlen($text) * 9), $y - 10, $text, $color[6]);
		if ($newbpm < $stat['min']) $stat['min'] = $newbpm;
		if ($newbpm > $stat['max']) $stat['max'] = $newbpm;
	} else {
		$c = 1;
		if ($channel % 2 == 1)         $c = 2;
		if (($channel % 10 ) == 3)     $c = 3;
		if ($channel >= 0 & $channel <= 6) {
			$stat['tap'] ++;
			$x = NOTEPAD + ($channel * $notewidth);
			$y = $bottom - ($beatsize * $v['beat']);
			imagefilledrectangle ($im, $x, $y, $x + $notewidth - 1, $y + $notesize - 1, $color[$c]);
			imageline ($im, $x, $y + $notesize - 1, $x + $notewidth - 1, $y + $notesize - 1, $color[10 + $c]);
			imageline ($im, $x, $y, $x + $notewidth - 1, $y, $color[10 + $c]);
		} else if ($channel >= 10 & $channel <= 16) {
			$stat['long'] ++;
			if (!isset($lch[$channel])) {
				$lch[$channel] = $v['beat'];
			} else {
				$x = NOTEPAD + (($channel - 10) * $notewidth);
				$y = $bottom - ($beatsize * $v['beat']);
				$z = $bottom - ($beatsize * $lch[$channel]);
				unset ($lch[$channel]);
				imagefilledrectangle ($im, $x, $y, $x + $notewidth - 1, $z + $notesize - 1, $color[20 + $c]);
				imagefilledrectangle ($im, $x + 1, $y, $x + ($notewidth * 0.15), $z + $notesize - 1, $color[0]);
				imagefilledrectangle ($im, $x + ($notewidth * 0.85) - 1, $y, $x + $notewidth - 3, $z + $notesize - 1, $color[30 + $c]);
				imageline ($im, $x, $z + $notesize - 1, $x + $notewidth - 1, $z + $notesize - 1, $color[30 + $c]);
				imageline ($im, $x, $y, $x + $notewidth - 1, $y, $color[30 + $c]);
			}
		}
	}
}

$y = 10;
if (preg_match('~^#title (.*)$~im', $data, $m)) {
	$title = explode("\n", wordwrap(trim($m[1]), floor(imagesx($im) / 9) - 5, "\n", true));
	foreach ($title as $v) {
		imagestring ($im, 5, 24, $y, $v, $color[0]);
		$y += 20;
	}
}
if (preg_match('~^#artist (.*)$~im', $data, $m)) {
	$title = explode("\n", wordwrap(trim($m[1]), floor(imagesx($im) / 7) - 5, "\n", true));
	foreach ($title as $v) {
		imagestring ($im, 3, 24, $y, $v, $color[1]);
		$y += 15;
	}
}
$st = ' [' . round($stat['min'], 3) . '-' . round($stat['max'], 3) . ']';
$y += 2;  imagestring ($im, 2, 24,  $y, " - Tap Notes:  " . $stat['tap'],  $color[7]);
$y += 15;  imagestring ($im, 2, 24, $y, " - Long Notes: " . $stat['long'], $color[7]);
$y += 15; imagestring ($im, 2, 24,  $y, " - BPM:        " . round($mybpm, 3) . $st,  $color[7]);
$y += 15; imagestring ($im, 2, 24, $y, "Powered by: the DtTvB's " . ($ojnmode ? 'OJN' : 'BMS') . " Parser", $color[7]);
$y += 15; imagestring ($im, 2, 24, $y, "v0.1.5 - http://blog.dt.in.th/dtbms.html", $color[7]);

$file = substr($filename, 0, -4);
$file = str_replace("'", '', $file);
$file = preg_replace('~[^a-z0-9]~i', ' ', $file);
$file = ucwords($file);
$file = str_replace(' ', '', $file);
$file = 'Preview of ' . $file . '.png';

header ('Content-Type: image/png');
header ('Content-Disposition: attachment; filename="' . $file . '"');
imagepng ($im);

?>
