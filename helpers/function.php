<?php

if (!function_exists('redirect')) {
	function redirect(string $path): never
	{
		$path = trim($path);
		if ($path !== '' && !preg_match('/^(?:[a-z]+:)?\/\//i', $path) && $path[0] !== '/') {
			$base = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
			$base = ($base === '/' || $base === '\\') ? '' : $base;
			$path = $base . '/' . ltrim($path, '/');
		}
		header('Location: ' . $path);
		exit;
	}
}
