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
 * Luminous isn't a sloth but it's an unnecessary strain on the server to keep
 * regenerating output from an input that hasn't changed since last year. This 
 * class provides a caching mechanism tailored specifically to Luminous.
 *
*/
class LuminousCache
{

  public $use_gz_compression = true; /**< Enables gzip compression on cached 
    files. This slows everything down by a measurable factor, but cached files
    can reach 500% of the original plain file size. Whereas with gz, they'll
    be more like 10-20%. */
  
  public $path_to_source_file = null; /**< Unique filepath to the source file.
  This is where it will be stored in the cache. */
  
  public $version; ///< Current Luminous version
  
  public $purge_time = -1; /**< Interval between cache purges (i.e. deletion of
  everything) or -1 to disable */
  
  public $cache_max_age = -1; /**< Maximum age of a cached file before it
  becomes invalid or -1 to disable */
  
  private $source_md5 = null; /**< MD5 of the plain source */
  
  private $cache_dir = null; /**< Cache directory relative to the CWD. Defaults 
    to "./cache" (set in constructor). Dir must have rwx permissions */
    
  private $cache_hit = false; /**< Determines whether the lookup for this file
  was successful or not */

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
  
  public function __construct($path_to_source, $md5, $cache_dir=null)
  {
    $this->path_to_source_file = $path_to_source;
    $this->source_md5 = $md5;
    
    if ($cache_dir === null)
    {
      $f = explode('/', __FILE__);
      array_pop($f);
      $cache_dir = implode('/', $f);
      $cache_dir .= '/../';
      $cache_dir = realpath($cache_dir) . '/cache/';
    }
    $this->SetCacheDir($cache_dir);
  }

  /**
    * Sets the cache directory
    * \param new_dir the new cache directory, which may be absolute or relative
    *   to the cwd.
    */
  public function SetCacheDir($new_dir)
  {
    try
    {
        $s = $this->MakeDir($new_dir);
    }
    catch (Exception $e)
    {
        trigger_error("The directory $new_dir could not be created, caching will fail. Either create the 
            directory yourself (and make it writable to the webserver) or disable caching to remove this warning");
        $this->use_cache = false;
        return false;
    }
    $this->cache_dir = $new_dir;
  }

  /**
    * Reads the file from the cache. If it exists and is within time 
    * limitations (if set), it is returned. If not (doesn't exist, too old), 
    * the function returns false.
    * \return the formatted string if it's present in the cache, or logical 
    * false.
    */
  public function ReadCache()
  {
    if (!$this->use_cache)
        return false;
    $path = $this->cache_dir . '/' . $this->path_to_source_file;

    if (file_exists($path))
    {

      $f = file_get_contents($path);
      if ($this->use_gz_compression)
        $f = @gzuncompress($f);
      
      $lines = explode("\n", $f, 3);
      if (count($lines) < 3)
        return false;
      $cached_md5 = trim($lines[0]);
      $cached_time = (int)trim($lines[1]);

      $time_constraint = ($this->cache_max_age == -1) || (time() 
        - $cached_time < $this->cache_max_age);

    
      $this->cache_hit = ($cached_md5 == $this->source_md5) && $time_constraint;

      if ($this->cache_hit)
        return $lines[2];
    }
    return $this->cache_hit;
  }

  /**
   * Creates a given directory
   * \param path the path to create
   * \return true or false, depending on whether it was successful
   * \throw Exception if the path does not exist and cannot be created.
   */ 
  private function MakeDir($path)
  {
    @mkdir($path, 0777, true);
    if (!is_dir($path))
    {
      throw new Exception ("LuminousCache: Could not create '$path'");
      return false;
    }
    return true;
    
    $dirs = explode("/", $path);
    $d = "";
    foreach ($dirs as $dir)
    {
      if (!is_dir($d .= "$dir/"))
      {
        if (!mkdir($d))
        {
          throw new Exception ("LuminousCache: Directory '$d' is not writable");
          return false;
        }
      }
         
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
  public function WriteCache($formatted_source_string)
  {
    if ($this->cache_hit) // file already cached
        return false;
    if (!$this->use_cache)
        return false;
    $t = time();
    $str = $this->source_md5;
    $str .= "\n$t";
    $str .= "\n$formatted_source_string";
    $path = $this->cache_dir . '/' . $this->path_to_source_file;
    $path = preg_replace('/\/+/', '/', $path);
    
    $dirs = explode('/', $path);
    array_pop($dirs); // the last element is the filename.
    $dir_path = join('/', $dirs);
    $s = $this->MakeDir($dir_path);
    
    if (!$s)
      return false;

    if ($this->use_gz_compression)
      $str = gzcompress($str);

    $r = file_put_contents($path, $str);
    return $r !== false;
  }
  
  /**
   * Recursive function to delete all contents of a directory in the cache.
   */ 
  private function DeleteCache($dir)
  {
    $files = scandir($dir);
    foreach ($files as $f)
    {
      if ($f == "." || $f == "..")
        continue;
      $path = "$dir/$f";
      if (is_file($path))
        unlink($path);
      else
      {
        $this->DeleteCache($path);
      }
    }
    rmdir($dir);
  }

  /**
   * Invokes the deletion process. If $this->purge_time is set (and is not -1)
   * the cache directory is cleaned out if the time elapsed since the last purge
   * is greater than $this->purge_time.
   * 
   * Calling this on every pageload is the easiest way.
   */ 
  public function Purge()
  {
    if ($this->purge_time == -1)
      return;
    
    $last_purge_time_f = $this->cache_dir . '/.purgedata';
    $purge = true;
    if (file_exists($last_purge_time_f))
    {
      $t = (int)file_get_contents($last_purge_time_f);
      $time = time();
      $purge = ($time - $t) > $this->purge_time;
    }

    if (!$purge)
      return;
    
    $files = scandir($this->cache_dir);
    
    foreach($files as $f)
    {
      if ($f == '.' || $f == '..')
        continue;
      
      $path = $this->cache_dir . "/$f";
      if (is_dir($path))
        $this->DeleteCache($path);
      elseif (is_file($path))
        unlink($path);
    }
    file_put_contents($last_purge_time_f, time());
  }
}
