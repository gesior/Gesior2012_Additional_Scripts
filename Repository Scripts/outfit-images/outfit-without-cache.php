<?php
// This script requires PHP 5.0 or higher with 'GD' ( http://www.php.net/manual/en/book.image.php )
if(isset($SERVER['HTTP_IF_MODIFIED_SINCE']))
{
	header('HTTP/1.0 304 Not Modified');
	/* PHP/webserver by default can return 'no-cache', so we must modify it */
	header('Cache-Control: public');
	header('Pragma: cache');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', 1337) . ' GMT');
	exit;
}

$outfit_colors = array(
	0xFFFFFF, 0xFFD4BF, 0xFFE9BF, 0xFFFFBF, 0xE9FFBF, 0xD4FFBF,
	0xBFFFBF, 0xBFFFD4, 0xBFFFE9, 0xBFFFFF, 0xBFE9FF, 0xBFD4FF,
	0xBFBFFF, 0xD4BFFF, 0xE9BFFF, 0xFFBFFF, 0xFFBFE9, 0xFFBFD4,
	0xFFBFBF, 0xDADADA, 0xBF9F8F, 0xBFAF8F, 0xBFBF8F, 0xAFBF8F,
	0x9FBF8F, 0x8FBF8F, 0x8FBF9F, 0x8FBFAF, 0x8FBFBF, 0x8FAFBF,
	0x8F9FBF, 0x8F8FBF, 0x9F8FBF, 0xAF8FBF, 0xBF8FBF, 0xBF8FAF,
	0xBF8F9F, 0xBF8F8F, 0xB6B6B6, 0xBF7F5F, 0xBFAF8F, 0xBFBF5F,
	0x9FBF5F, 0x7FBF5F, 0x5FBF5F, 0x5FBF7F, 0x5FBF9F, 0x5FBFBF,
	0x5F9FBF, 0x5F7FBF, 0x5F5FBF, 0x7F5FBF, 0x9F5FBF, 0xBF5FBF,
	0xBF5F9F, 0xBF5F7F, 0xBF5F5F, 0x919191, 0xBF6A3F, 0xBF943F,
	0xBFBF3F, 0x94BF3F, 0x6ABF3F, 0x3FBF3F, 0x3FBF6A, 0x3FBF94,
	0x3FBFBF, 0x3F94BF, 0x3F6ABF, 0x3F3FBF, 0x6A3FBF, 0x943FBF,
	0xBF3FBF, 0xBF3F94, 0xBF3F6A, 0xBF3F3F, 0x6D6D6D, 0xFF5500,
	0xFFAA00, 0xFFFF00, 0xAAFF00, 0x54FF00, 0x00FF00, 0x00FF54,
	0x00FFAA, 0x00FFFF, 0x00A9FF, 0x0055FF, 0x0000FF, 0x5500FF,
	0xA900FF, 0xFE00FF, 0xFF00AA, 0xFF0055, 0xFF0000, 0x484848,
	0xBF3F00, 0xBF7F00, 0xBFBF00, 0x7FBF00, 0x3FBF00, 0x00BF00,
	0x00BF3F, 0x00BF7F, 0x00BFBF, 0x007FBF, 0x003FBF, 0x0000BF,
	0x3F00BF, 0x7F00BF, 0xBF00BF, 0xBF007F, 0xBF003F, 0xBF0000,
	0x242424, 0x7F2A00, 0x7F5500, 0x7F7F00, 0x557F00, 0x2A7F00,
	0x007F00, 0x007F2A, 0x007F55, 0x007F7F, 0x00547F, 0x002A7F,
	0x00007F, 0x2A007F, 0x54007F, 0x7F007F, 0x7F0055, 0x7F002A,
	0x7F0000
);

$colorableOutfits = array(128, 129, 12, 130, 131, 132, 133, 134, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 194, 226, 251, 252, 253, 254, 255, 264, 268, 269, 270, 273, 278, 279, 288, 289, 324, 325, 328, 329, 335, 336, 366, 367);
// blocks possibility to 'cache' images with color numbers like 343456 (random) to milion files
$outfit_id = (int) $_GET['id'];
$addons  = ((int) $_GET['addons']) % 4; // 0, 1, 2, 3 = 4 possibilities
$head = ((int) $_GET['head']) % count($outfit_colors);
$body = ((int) $_GET['body']) % count($outfit_colors);
$legs = ((int) $_GET['legs']) % count($outfit_colors);
$feet = ((int) $_GET['feet']) % count($outfit_colors);

function colorizePixel($color, &$r, &$g, &$b)
{
	global $outfit_colors;

	if ($color < count($outfit_colors))
		$value = $outfit_colors[$color];
	else
		$value = 0;

	$ro = ($value & 0xFF0000) >> 16;
	$go = ($value & 0xFF00) >> 8;
	$bo = ($value & 0xFF);
	$r = (int) ($r * ($ro / 255));
	$g = (int) ($g * ($go / 255));
	$b = (int) ($b * ($bo / 255));
}

if(in_array($outfit_id, $colorableOutfits))
{
	if(file_exists('outfit_colors/' . $outfit_id . '_' . $addons . '.png'))
	{
		// let's paint that outfit!
		$image_color = imagecreatefrompng('outfit_colors/' . $outfit_id . '_' . $addons . '.png');
		$image_outfit = imagecreatefrompng('outfit_images/' . $outfit_id . '_' . $addons . '.png');
		imagealphablending($image_outfit, false);
		imagesavealpha($image_outfit, true);
		for($i = 0; $i < imagesy($image_color); $i++)
		{
			for($j = 0; $j < imagesx($image_color); $j++)
			{
				$templatepixel = imagecolorat($image_color, $j, $i);
				$outfit = imagecolorat($image_outfit, $j, $i);
				if($templatepixel == $outfit)
					continue;

				$rt = ($templatepixel >> 16) & 0xFF;
				$gt = ($templatepixel >> 8) & 0xFF;
				$bt = $templatepixel & 0xFF;
				$ro = ($outfit >> 16) & 0xFF;
				$go = ($outfit >> 8) & 0xFF;
				$bo = $outfit & 0xFF;
					if($rt && $gt && !$bt)
					colorizePixel($head, $ro, $go, $bo);
				elseif($rt && !$gt && !$bt)
					colorizePixel($body, $ro, $go, $bo);
				elseif(!$rt && $gt && !$bt)
					colorizePixel($legs, $ro, $go, $bo);
				elseif(!$rt && !$gt && $bt)
					colorizePixel($feet, $ro, $go, $bo);
				else
					continue;

				imagesetpixel($image_outfit, $j, $i, imagecolorallocate($image_outfit, $ro, $go, $bo));
			}
		}
		imagedestroy($image_color);
		header('Content-Type: image/png');
		/* PHP/webserver by default can return 'no-cache', so we must modify it */
		header('Cache-Control: public');
		header('Pragma: cache');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', 1337) . ' GMT');
		imagepng($image_outfit);
		imagedestroy($image_outfit);
	}
	else
	{
		exit('Cannot generate painted outfit: ' . $outfit_id . '_' . $addons . '.png');
	}
}
else
{
	if(file_exists('outfit_images/' . $outfit_id . '_0.png'))
	{
		header('Content-Type: image/png');
		/* PHP/webserver by default can return 'no-cache', so we must modify it */
		header('Cache-Control: public');
		header('Pragma: cache');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', 1337) . ' GMT');
		readfile('outfit_images/' . $outfit_id . '_0.png');
	}
	else
	{
		exit('Cannot generate not painted outfit: ' . $outfit_id);
	}
}