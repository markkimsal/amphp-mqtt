<?php
namespace MarkKimsal\Mqtt {

function dumphex($data) {
	$count = 0;
	if (is_int($data)) {
		print str_pad(dechex($data), 2, '0', STR_PAD_LEFT). ' ';
		$data = '';
	}
	while (strlen($data)) {
		$d = substr($data, 0, 1);
		$data = substr($data, 1);
		$arr = unpack('C', $d);
		print str_pad(dechex($arr[1]), 2, '0', STR_PAD_LEFT). ' ';
		$count++;
		if ($count == 8) { print ' '; }

		if ($count == 16) { print "\n"; $count = 0; }
	}
	print "\n";
}


}
