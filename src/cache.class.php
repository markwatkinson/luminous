<?php

/*
  Copyright 2010 Mark Watkinson

  This file is part of Luminous.

  Luminous is free software: you can redistribute it and/or
  modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Luminous is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Luminous.  If not, see <http://www.gnu.org/licenses/>.

*/

/**
 * \file luminous_cache.class.php
 * \brief The Luminous caching system
 */ 
 
/**
 * \class LuminousCache
 * \author Mark Watkinson 
 * 
 * \brief A caching system for Luminous.
 *
*/
class LuminousCache
{
  public $use_gz_compression = true; /**< Enables gzip compression on cached 
    files. This slows everything down by a measurable factor, but cached files
    can reach 500% of the original plain file size. Whereas with gz, they'll
    be more like 10-20%. */
  
  private $id;

  public $purge_older_than = 3600; /**< purges files which haven't been
    modified (mtime) for the given number of seconds
    NOTE: cache hits trigger a touch
    */

  public $purge_interval = -1; /**< Interval between cache purges (i.e. deletion of
  everything) or -1 to disable */
  
  private $cache_dir = null; /**< Cache directory relative to the CWD. Defaults 
    to "./cache" (set in constructor). Dir must have rwx permissions */

  private $cache_hit = false;

  private $use_cache = true;

  /**
   * Constructor
   * \param path_to_source the path to the source file. This should be unique, 
   * and this is where the cached version will be stored inside the cache 
   * directory. You could give this as a checksum if your source DB is not file
   * path based.
   * \param md5 the MD5 string of the plain file. This is used to validate 
   * whether or not the cache still represents the same file
   * \param cache_dir the directory for the cache. This defaults to "./cache/".
   *    relative to the luminous_cache.class.php file.
   *    Failing to specify a writable directory will result in an Exception 
   *    being thrown.
   * \throw Exception if the cache_dir is not specified to a writable path.
   */
  
  public function __construct($id, $cache_dir=null)  {

    $this->id = $id;
    
    if ($cache_dir === null) {
      $f = explode('/', __FILE__);
      array_pop($f);
      $cache_dir = implode('/', $f);
      $cache_dir .= '/../';
      $cache_dir = realpath($cache_dir) . '/cache/';
    }
    $this->set_dir($cache_dir);

  }


  /**
    * Sets the cache directory
    * \param new_dir the new cache directory, which may be absolute or relative
    *   to the cwd.
    */
  public function set_dir($new_dir) {
    try {
        $s = $this->mkcachedir($new_dir);
    }
    catch (Exception $e)  {
        trigger_error("Luminous warning: The directory $new_dir could not be
            created, caching will fail. Either create the directory yourself
            (and make it writable to the webserver) or disable caching to
            remove this warning");
        $this->use_cache = false;
        return false;
    }
    $this->cache_dir = $new_dir;
  }



  

  /**
    * Reads the file from the cache. If it exists and is within time 
    * limitations (if set), it is returned. If not (doesn't exist, too old), 
    * the function returns false.
    * \return the formatted string if it's present in the cache, or null
    */
  public function read()
  {
    $path = $this->cache_dir . '/' . $this->id;

    if (file_exists($path)) {
      $this->cache_hit = true;      
      $f = file_get_contents($path);
      touch($path);
      if ($this->use_gz_compression)
        $f = @gzuncompress($f);
      return $f;
    }
    return null;
  }

  /**
   * Creates a given directory
   * \param path the path to create
   * \return true or false, depending on whether it was successful
   * \throw Exception if the path does not exist and cannot be created.
   */ 
  private function mkcachedir($path)
  {
    @mkdir($path, 0777, true);
    if (!is_dir($path))  {
      throw new Exception ("LuminousCache: Could not create '$path'");
      return false;
    }
    return true;
  }

  

  /**
   * Writes to the cache.
   * \param formatted_source_string the formatted source string, as returned by
   * an instance of Luminous.
   * \return True on success, false on some kind of failure
   * \throw Exception if the cache structure cannot be built.
   */ 
  public function write($str)
  {
    if (!$this->use_cache || $this->cache_hit) // file already cached
        return false;

    $path = $this->cache_dir . '/' . $this->id;

    if ($this->use_gz_compression)
      $str = gzcompress($str);

    $r = file_put_contents($path, $str);
    return $r !== false;
  }  


  public function purge() {
    if (!$this->use_cache || $this->purge_older_than <= 0) return;
    $purge_file = $this->cache_dir . '/.purgedata';
    if (!file_exists($purge_file)) touch($purge_file);
    $last = 0;
    $fh = fopen($purge_file, 'r+');
    $time = time();
    $t__ = microtime(true);
    if (flock($fh, LOCK_EX)) {
      if (filesize($purge_file))
        $last = (int)fread($fh, filesize($purge_file));
      else $last = 0;
      if ($time - $last > 60*60*24) {
        rewind($fh);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $time);
        foreach(scandir($this->cache_dir) as $file) {
          if ($file[0] === '.') continue;
          $mtime = filemtime($this->cache_dir . '/' . $file);
          if ($time - $mtime > $this->purge_older_than)
            unlink($this->cache_dir . '/' . $file);
        }
      }
      flock($fh, LOCK_UN);
      fclose($fh);
    }
    $t1_ = microtime(true);
  }


}
