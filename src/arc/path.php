<?php

	/*
	 * This file is part of the Ariadne Component Library.
	 *
	 * (c) Muze <info@muze.nl>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace arc;

	/**
	 *	Utility methods to handle common path related tasks, cleaning, changing relative to absolute, etc.
	 */
	class path extends Pluggable {
		
		protected static $collapseCache = array();

		/**
		 *	This method returns all the parent paths for a given path, starting at the root and including the
		 *	given path itself.
		 *	
		 *	Usage:
		 *		\arc\path::parents( '/foo/bar/doh/', '/foo/' ); // => [ '/foo/', '/foo/bar/', '/foo/bar/doh/' ]
		 *
		 *	@param string $path The path to derive all parent paths from.
		 *	@param string $root The root or topmost parent to return. Defaults to '/'.
		 *	@return array Array of all parent paths, starting at the root and ending with the given path. 
		 *		Note: It includes the given path!
		 */
		public static function parents( $path, $root = '/' ) {
			// returns all parents starting at the root, up to and including the path itself
			$prevpath = '/';
			$parents = self::reduce( $path, function( $result, $entry ) use ( $root, &$prevpath ) {
				$prevpath .= $entry . '/';
				if ( strpos( $prevpath , $root ) === 0 && $prevpath !== $root ) { // Add only parents below the root
					$result[] = $prevpath;
				}
				return $result;
			}, array( $root ) );
			return $parents;
		}

		/**
		 *	This method parses a path which may contain '..' or '.' or '//' entries and returns the resulting
		 *	absolute path.
		 *
		 *	Usage:
		 *		\arc\path::collapse( '../', '/foo/bar/' ); // => '/foo/'
		 *		\arc\path::collapse( '\\foo\\.\\bar/doh/../' ); // => '/foo/bar/'
		 *
		 *	@param string $path The input path, which may be relative. If this path starts with a '/' it is
		 *		considered to start in the root.
		 *	@param string $cwd The current working directory. For relative paths this is the starting point.
		 *	@return string The absolute path, without '..', '.' or '//' entries.
		 */
		public static function collapse( $path, $cwd = '/' ) {
			// removes '.', changes '//' to '/', changes '\\' to '/', calculates '..' up to '/'
			if ( !isset($path[0]) ) {
				return $cwd;
			}
			if ( $path[0] !== '/' && $path[0] !== '\\' ) {
				$path = $cwd . '/' . $path;
			}
			if ( isset( self::$collapseCache[$path] ) ) { // cache hit - so return that
				return self::$collapseCache[$path];
			}
			$tempPath = str_replace('\\', '/', (string) $path);
			$collapsedPath = self::reduce( 
				$tempPath, 
				function( $result, $entry ) {
					switch ( $entry ) {
						case '..' :
							$result = dirname( $result );
							if ( isset($result[1]) ) { // fast check to see if there is a dirname
								$result .= '/';
							}
							$result[0] = '/'; // php has a bug in dirname('/') -> returns a '\\' in windows
						break;
						case '.':
						break;
						default:
							$result .= $entry .'/';
						break;
					}
					return $result;
				},
				'/' // initial value, always start paths with a '/'
			);
			// store the collapsed path in the cache, improves performance by factor > 10.
			self::$collapseCache[$path] = $collapsedPath;
			return $collapsedPath;
		}

		/**
		 *	This method cleans the input path with the given filter method. You can specify any of the 
		 *	sanitize methods valid for filter_var or you can use your own callback function. By default 
		 *	it url encodes each filename in the path.
		 *
		 *	Usage:
		 *		\arc\path::clean( '/a path/to somewhere/' ); // => '/a%20path/to%20somewhere/'
		 *
		 *	@param string $path The path to clean. 
		 *	@param mixed $filter Either one of the sanitize filters for filter_var or a callback method as 
		 *		in \arc\path::map
		 *	@param mixed $flags Optional list of flags for the sanitize filter.
		 *	@return string The cleaned path.
		 */
		public static function clean( $path, $filter = null, $flags = null ) {
			if ( is_callable( $filter ) ) {
				$callback = $filter;
			} else {
				if ( !isset( $filter ) ) {
					 $filter = FILTER_SANITIZE_ENCODED;
				}
				if ( !isset($flags) ) {
					$flags = FILTER_FLAG_ENCODE_LOW|FILTER_FLAG_ENCODE_HIGH;
				}
				$callback = function( $entry ) use ( $filter, $flags ) {
					return filter_var( $entry, $filter, $flags);
				};
			}
			return self::map( $path, $callback );
		}

		/**
		 *	Returns either the immediate parent path for the given path, or null if it is outside the 
		 *	root path. Differs with dirname() in that it will not return '/' as a parent of '/', but 
		 *	null instead.
 		 *
		 *	Usage:
		 *		\arc\path::parent( '/foo/bar/' ); // => '/foo/'
		 *
		 *	@param string $path The path from which to get the parent path.
		 *	@param string $root Optional root path, defaults to '/'
		 *	@return string|null The parent of the given path or null if the parent is outside the root path.
		 */
		public static function parent( $path, $root = '/' ) {
			if ( $path == $root ) {
				return null;
			}
			$parent = dirname( $path );
			if ( isset($parent[1]) ) { // fast check to see if there is a dirname
				$parent .= '/';
			}
			$parent[0] = '/'; // dirname('/something/') returns '\' in windows.
			if ( strpos( $parent, $root ) !== 0 ) { // parent is outside of the root
				return null;
			}
			return $parent;
		}

		public static function getRelativePath( $targetPath, $sourcePath ) {
			$relativePath = '';
			$commonParent = \arc\path::walk( $sourcePath, function( $path) use ( $targetPath, &$relativePath ) {
				if ( !\arc\path::isChild( $targetPath, $path ) ) {
					$relativePath .= '../';
				} else {
					return $path;
				}
			}, false);
			$relativePath .= substr( $targetPath, strlen( $commonParent ) );
			return $relativePath;
		}

		public static function isChild( $path, $parent ) {
			return ( strpos( $path, $parent ) === 0 );
		}

		public static function isAbsolute( $path ) {
			return $path[0]==='/';
		}

		protected static function getSplitPath( $path ) {
			return array_filter( explode( '/', $path ), function( $entry ) {
				return ( isset( $entry ) && $entry !== '' );
			});
		}

		/**
		 *	Applies a callback function to each filename in a path. The result will be the new filename.
		 *
		 *	Usage:
		 *		/arc/path::map( '/foo>bar/', function( $filename ) { 
		 *			return htmlentities( $filename, ENT_QUOTES );
		 *		} ); // => '/foo&gt;bar/'
		 *
		 *	@param string $path The path to alter.
		 *	@param Callable $callback 
		 *	@return string A path with all filenames changed as by the callback method.
		 */
		public static function map( $path, $callback ) {
			$splitPath = self::getSplitPath( $path );
			if ( count($splitPath) ) {
				$result = array_map( $callback, $splitPath );
				return '/' . join( $result, '/' ) .'/';
			} else {
				return '/';
			}
		}

		/**
		 *	Applies a callback function to each filename in a path, but the result of the callback is fed back 
		 *	to the next call to the callback method as the first argument.
		 *	
		 *	Usage:
		 *		/arc/path::reduce( '/foo/bar/', function( $previousResult, $filename ) {
		 *			return $previousResult . $filename . '\\';
		 *		}, '\\' ); // => '\\foo\\bar\\'
		 *
		 *	@param string $path The path to reduce.
		 *	@param Callable $callback The method to apply to each filename of the path
		 *	@param mixed $initial Optional. The initial reduced value to start the callback with.
		 *	@return mixed The final result of the callback method
		 */
		public static function reduce( $path, $callback, $initial = null ) {
			return array_reduce( self::getSplitPath( $path ), $callback, $initial );
		}

		/**
		 *	Applies a callback function to each parent of a path, in order. Starting at the root by default, 
		 *	but optionally in reverse order. Will continue while the callback method returns null, otherwise 
		 *	returns the result of the callback method.
		 *
		 *	Usage:
		 *		$result = \arc\path::walk( '/foo/bar/', function( $parent ) {
		 *			if ( $parent == '/foo/' ) { // silly test
		 *				return true;
		 *			}
		 *		});
		 *
		 *	@param string $path Each parent of this path will be passed to the callback method.
		 *	@param Callable $callback The method to apply to each parent
		 *	@param bool $startAtRoot Optional. If set to false, root will be called last, otherwise first. 
		 *		Defaults to true.
		 *	@param string $root Optional. Specify another root, no parents above the root will be called. 
		 *		Defaults to '/'.
		 *	@return mixed The first non-null result of the callback method
		 */
		public static function walk( $path, $callback, $startAtRoot = true, $root = '/' ) {
			$parents = self::parents( $path, $root );
			if ( !$startAtRoot ) {
				$parents = array_reverse( $parents );
			}
			foreach ( $parents as $parent ) {
				$result = call_user_func( $callback, $parent );
				if ( isset( $result ) ) {
					return $result;
				}
			}
		}

	}

