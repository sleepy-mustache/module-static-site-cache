<?php
namespace Module\StaticCache;

/**
 * Recursively deletes files and directories
 * @param  string $dir A directory to delete
 */
function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);

		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
			}
		}

		reset($objects);
		@rmdir($dir);
	}
}

/**
 * Hooks into the preprocess hooks to start buffering and clear old cached files
 *
 * @return void
 * @internal
 */
function preprocess() {
	$dir = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "normal" . DIRECTORY_SEPARATOR;

	if (file_exists($dir)) {
		$created = filemtime($dir);
		$cleanInterval = 300;

		if (time() - $created > $cleanInterval) {
			rrmdir($dir);
		} else {
			header("SM-Cache Expires: " . ($cleanInterval - (time() - $created)) . " seconds.");
		}
	}

	ob_start();
}

/**
 * Caches the Page, unless we at the homepage
 * @return void
 * @internal
 */
function postprocess() {
	$root = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;
	$cachedir = "cache" . DIRECTORY_SEPARATOR . "normal" . DIRECTORY_SEPARATOR;
	$uri = explode('?', $_SERVER['REQUEST_URI']);
	$uri = reset($uri);
	$file = $uri . '_' . $_SERVER['QUERY_STRING'] . '.html';
	$cachefile = $root . $cachedir . $_SERVER['SERVER_NAME'] . $file;

	$full_html = ob_get_flush() . "\n<!-- StaticCached -->";

	if (!is_dir(dirname($cachefile)) && !mkdir(dirname($cachefile), 0777, true)) {
		throw new \Exception('StaticCache: Cannot make directory.');
	}

	if (!$static = fopen($cachefile, "w")) {
		throw new \Exception("StaticCache: Unable to open file!");
	}

	fwrite($static, $full_html);
	fclose($static);
}


if (\Sleepy\SM::isLive()) {
	\Sleepy\Hook::doAction('sleepy_preprocess',  '\Module\StaticCache\preprocess' );
	\Sleepy\Hook::doAction('sleepy_postprocess', '\Module\StaticCache\postprocess');
}