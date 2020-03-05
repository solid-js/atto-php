<?php

class ArrayUtils
{
    /**
     * Traverse an array from a path as a string.
     * Ex : $pPath = 'my.nested.array' will traverse your array and get the value of 'array' inside 'nested' inside 'my' inside $pObject
     * @param $path : The path
     * @param $object : The associative array to traverse
     * @return mixed|null : value if found, else null
     */
	static function traverse ( $path, $object )
	{
		// Check if our object is null
		if (is_null($object)) return null;

		// Split the first part of the path
		$explodedPath = explode('.', $path, 2);

		// One element in path selector
		if (!isset($explodedPath[1]))
		{
			// Check if this element exists and return it if found
			return isset($object[$explodedPath[0]]) ? $object[$explodedPath[0]] : null;
		}

		// Nesting detected in path
		// Check if first part of the path is in object
		else if (isset($explodedPath[0]) && isset($object[$explodedPath[0]]))
		{
			// Target child from first part of path and traverse recursively
			return ArrayUtils::traverse($explodedPath[1], $object[$explodedPath[0]]);
		}

		// Not found
		else return null;
	}
}
