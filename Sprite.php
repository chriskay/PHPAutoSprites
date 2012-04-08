<?php
/**
 * @author Christian Kilb 
 */
require_once('Exception.php');
require_once('SpriteCreator.php');

if(!defined('__DIR__'))
{
        define('__DIR__', dirname(__FILE__));
}

/**
 * 
 */
abstract class Sprite
{
        /* Configuration */
        const IMAGE_DIR = '../images'; // path to image dir relative to directory of sprite.php
        const SPRITE_DIR = 'sprites'; // path where sprites should be saved relative to IMAGE_DIR
        const SPRITE_URL = 'images/sprites/'; // URL to sprites , eg. http://mywebsite.com/images/sprites

        const DATA_DIR = 'data'; // path to data dir relative to directory of sprite.php
        const TRACK_FILE = 'tracks.json'; // name of track file in DATA_DIR
        const SPRITE_FILE = 'sprites.json'; // name of sprite info file in DATA_DIR         

        const PNG_COMPRESSION = 9; // how much to compress sprite pngs from 0 (no compression) to 9
        const MAX_SPRITE_PIXEL = 250000; // maximal sum of width*height of all images

        protected static $pseudo_classes = array(
                'active', 'hover'
        );

        /**
         * creates image HTML element with CSS sprite data
         * @param type $src source of image by default (no :hover etc.)
         * @param type $attributes HTML attributes, eg. array('title' => 'img title')
         * @param type $sprite_nr number of sprite image should be placed in or -1 for auto assignment
         * @return string HTML element
         * @throws SpriteException 
         */
        public static function image($src, $attributes = array(), $sprite_id = null)
        {
                return self::htmlElement('span', $src, $attributes, $sprite_id);
        }

        /**
         * creates image input HTML element with CSS sprite data
         * @param type $src source of image by default (no :hover etc.)
         * @param type $attributes HTML attributes, eg. array('alt' => 'img alt text')
         * @param type $sprite_nr number of sprite image should be placed in or -1 for auto assignment
         * @return string HTML element
         * @throws SpriteException 
         */
        public static function input($src, $attributes = array(), $sprite_id = null)
        {
                if(!isset($attributes['type']))
                {
                        $attributes['type'] = 'submit';
                }

                if(!isset($attributes['value']))
                {
                        $attributes['value'] = '';
                }

                if(!isset($attributes['style']))
                {
                        $attributes['style'] = 'border-width: 0px;';
                }

                return self::htmlElement('input', $src, $attributes, $sprite_id);
        }
        
        /**
         * creates image input HTML element with CSS sprite data
         * @param type $src source of image by default (no :hover etc.)
         * @param type $attributes HTML attributes, eg. array('alt' => 'img alt text')
         * @param type $sprite_nr number of sprite image should be placed in or -1 for auto assignment
         * @return string HTML element
         * @throws SpriteException 
         */
        public static function anchor($src, $attributes = array(), $sprite_id = null)
        {
                return self::htmlElement('a', $src, $attributes, $sprite_id);
        }

        protected static $sprite_existing = true;
        
        /**
         * creates HTML element with CSS sprite data
         * @param type $element tag name of element eg. 'div'
         * @param type $src source of image by default (no :hover etc.)
         * @param type $attributes HTML attributes, eg. array('alt' => 'img alt text')
         * @param type $sprite_nr number of sprite image should be placed in or -1 for auto assignment
         * @return string HTML element
         * @throws SpriteException 
         */
        public static function htmlElement($element, $src, $attributes = array(), $sprite_id = null)
        {
                $src = __DIR__.DIRECTORY_SEPARATOR.self::IMAGE_DIR.DIRECTORY_SEPARATOR.$src;
                
                $sprite = self::getSpriteData($src);

                $html = '<'.$element;

                if(!$sprite)
                {
                        self::trackImage($src, $sprite_id);
                        
                        self::createSprites();

                        $sprite = self::getSpriteData($src);
                        
                        if(!$sprite) // image not existing
                        {
                                throw new SpriteException('Image '.$src.' could not be loaded.');
                        }
                        
                        $html .= ' style="'.addslashes(self::getCSS($src));
                        
                        if(isset($attributes['style']))
                        {
                               $html .= $attributes['style'];
                               unset($attributes['style']);
                        }
                        
                        $html .= '"';

                        self::$sprite_existing = false;
                }
                else
                {
                        $html .= ' class="'.self::getClassName($src).'"';

                        if(!self::$sprite_existing)
                        {
                                if(isset($attributes['style']))
                                {
                                        $attributes['style'] .= self::getCSS($src);
                                }
                                else
                                {
                                        $attributes['style'] = self::getCSS($src);
                                }
                        }
                }

                foreach($attributes as $key => $attribute)
                {
                        $html .= ' '.$key.'="'.$attribute.'"';
                }

                $html .= '></'.$element.'>';

                return $html;
        }

        /**
         * save image source and sprite nr in track file
         * @param  $src image source
         * @param int $sprite_nr number of sprite or -1 if a sprite should 
         * be automatically assigned
         */
        protected static function trackImage($src, $sprite_id = null)
        {
                $tracks = self::getFileContent(self::TRACK_FILE);

                $tracks[$src] = $sprite_id;
                self::setFileContent(self::TRACK_FILE, $tracks);
        }

        /**
         * +ß
         * get info about sprite position of an image via it's source
         * @param string $src image source
         * @return mixed sprite info array or false if no info exist
         */
        protected static function getSpriteData($src)
        {
                $sprites = self::getFileContent(self::SPRITE_FILE);

                if(isset($sprites[$src]))
                {
                        return $sprites[$src];
                }

                return false;
        }

        /**
         * creates CSS sprite images
         * @throws SpriteException 
         */
        protected static function createSprites()
        {
                $counts = self::getFileContent(self::TRACK_FILE);
                $sprites = array();

                $pseudo_classes = array_merge(array(''), self::$pseudo_classes);

                $images = array();
                foreach($counts as $source => $sprite_nr)
                {
                        foreach($pseudo_classes as $pseudoClass)
                        {
                                $src = self::getSrcWithPseudoClass($source, $pseudoClass);
                                if(!is_readable($src))
                                {
                                        if(isset($counts[$src]))
                                        {
                                                unset($counts[$src]);
                                        }
                                        continue;
                                }

                                $images[$src] = $sprite_nr;
                        }
                }

                // assign images to sprites
                $sprites = array();
                $index = 0;
                foreach($images as $src => $sprite_nr)
                {
                        $i = $sprite_nr;
                        if($sprite_nr < 0)
                        {
                                $i = 'auto'.$index;
                                
                                if(isset($sprites[$i]) && $sprites[$i]->getPixelSum() > self::MAX_SPRITE_PIXEL)
                                {
                                        $index++;
                                        $i = 'auto'.$index;
                                }
                        }
                        
                        if(!isset($sprites[$i]))
                        {
                                $sprites[$i] = new SpriteCreator();       
                        }
                        
                        $sprites[$i]->addImage($src);
                }

                self::deleteSprites();
                $positions = array();
                foreach($sprites as $sprite)
                {
                        $spriteFileName = md5(time().microtime());

                        $sprite->output(__DIR__.DIRECTORY_SEPARATOR.self::IMAGE_DIR.DIRECTORY_SEPARATOR.self::SPRITE_DIR.DIRECTORY_SEPARATOR.$spriteFileName.'.png', self::PNG_COMPRESSION);
                
                        foreach($sprite->positions() as $position)
                        {
                                $src = $position['src'];
                                unset($position['src']);
                                
                                $position['sprite'] = $spriteFileName;
                                $positions[$src] = $position;
                        }
                }
                
                self::setFileContent(self::SPRITE_FILE, $positions);
        }

        /**
         * @var array remembers file content of files. So getFileContent
         * needs to open file just once.
         */
        protected static $file_memorization = array();
        
        /**
         * get content from file
         * @param string $file name of file in DATA_DIR
         * @return array content
         */
        protected static function getFileContent($file)
        {
                if(!isset(self::$file_memorization[$file]))
                {
                        $arr = array();
                        $file = __DIR__.DIRECTORY_SEPARATOR.self::DATA_DIR.DIRECTORY_SEPARATOR.$file;

                        if(is_readable($file))
                        {
                                $content = file_get_contents($file);
                                $arr = json_decode($content);

                                if($arr === null)
                                {
                                        $arr = array();
                                        unlink($file);
                                }

                                $arr = (array) $arr;

                                foreach($arr as $key => $val)
                                {
                                        if(is_object($val))
                                        {
                                                $arr[$key] = (array) $val;
                                        }
                                }
                        }


                        self::$file_memorization[$file] = $arr;
                }

                return self::$file_memorization[$file];
        }

        /**
         * saves content array json encoded in specified file
         * @param string $file
         * @param array $content 
         */
        protected static function setFileContent($file, array $content)
        {
                $file = __DIR__.DIRECTORY_SEPARATOR.self::DATA_DIR.DIRECTORY_SEPARATOR.$file;
                $handle = fopen($file, 'w+');

                fwrite($handle, json_encode($content));
                fclose($handle);

                self::$file_memorization[$file] = $content;
        }

        /**
         * delete all sprites images created before that are older than 1 minute
         */
        protected static function deleteSprites()
        {
                $sprite_dir = __DIR__.DIRECTORY_SEPARATOR.self::IMAGE_DIR.DIRECTORY_SEPARATOR.self::SPRITE_DIR;
                $handle = opendir($sprite_dir);
                while(($file = readdir($handle)) !== false)
                {
                        if($file{0} != '.')
                        {
                                $modified = filemtime($sprite_dir.DIRECTORY_SEPARATOR.$file);

                                if($modified < (time()-60))
                                {
                                        unlink($sprite_dir.DIRECTORY_SEPARATOR.$file);
                                }
                        }
                }
        }

        /**
         * returns complete <style> tag with all the CSS data for all sprite images
         * @return string <style> tag with CSS data
         */
        public static function style()
        {
                $html = '<style type="text/css">';

                $sprites = self::getFileContent(self::SPRITE_FILE);
                foreach($sprites as $src => $sprite)
                {
                        $html .= '.'.self::getClassName($src);

                        $pseudoClass = self::getPseudoClass($src);
                        if($pseudoClass)
                        {
                                $html .= ':'.$pseudoClass;
                        }

                        $html .= '{'.self::getCSS($src).'}';
                }

                $html .= '</style>';

                return $html;
        }

        /**
         * return CSS class name for image source
         * @param string $src image source
         * @return string CSS class name
         */
        protected static function getClassName($src)
        {
                $hash = md5(self::getSrcWithoutPseudoClass($src));
                $hash = 'sprite_'.substr($hash, 0, 10);
                return $hash;
        }

        /**
         * returns CSS data for image defined by source
         * @param string $src image source
         * @return string minified CSS code 
         */
        protected static function getCSS($className)
        {
                $sprite = self::getSpriteData($className);
                $css = '';
                if($sprite)
                {
                        $css =  'display: inline-block;
                                background-image: url('.self::SPRITE_URL.$sprite['sprite'].'.png);
                                background-position: -'.$sprite['x'].'px -'.$sprite['y'].'px;
                                background-color: transparent;
                                width: '.$sprite['width'].'px;
                                height: '.$sprite['height'].'px;';
                }

                $css = str_replace(array(
                        "\r",
                        "\n",
                        '  '
                ), '', $css);

                return $css;
        }

        /**
         * adds pseudo class to file name
         * @param string $src image source, eg. 'images/img.png'
         * @param string $pseudoClass name of pseudo class, eg. 'hover'
         * @return string file name with pseudo class, eg. 'images/img_hover.png' 
         */
        protected function getSrcWithPseudoClass($src, $pseudoClass)
        {
                if(empty($pseudoClass))
                {
                        return $src;
                }

                // extends _pseudo class to image file name
                $src_exploded = explode('/', $src);
                $file_name_ext = $src_exploded[count($src_exploded)-1];

                $file_exploded = explode('.', $file_name_ext);
                $file_name = $file_exploded[count($file_exploded)-2];
                $file_ext = $file_exploded[count($file_exploded)-1];

                $file_name_ps = null;
                if(empty($pseudoClass))
                {
                        $file_name_ps = $file_name.$pseudoClass.'.'.$file_ext;
                }
                else
                {
                        $file_name_ps = $file_name.'_'.$pseudoClass.'.'.$file_ext;
                }

                $src_exploded[count($src_exploded)-1] = $file_name_ps;
                $src = implode('/', $src_exploded);

                return $src;
        }

        /**
         * get file name without pseudo class in it
         * @param string $src eg. 'images/img_hover.png'
         * @return string eg. 'images/img.png'
         */
        protected function getSrcWithoutPseudoClass($src)
        {                        
                $src_exploded = explode('/', $src);
                $file_name_ext = $src_exploded[count($src_exploded)-1];

                foreach(self::$pseudo_classes as $pseudoClass)
                {
                        $file_name_ext = str_replace('_'.$pseudoClass, '', $file_name_ext);
                }

                $src_exploded[count($src_exploded)-1] = $file_name_ext;
                $src = implode('/', $src_exploded);

                return $src;
        }

        /**
         * get pseudo class from file name
         * @param type $src eg. 'images/image_hover.png'
         * @return mixed pseudo class or false if no pseudo class was found, eg. 'hover'
         */
        protected function getPseudoClass($src)
        {
                $src_exploded = explode('/', $src);
                $file_name_ext = $src_exploded[count($src_exploded)-1];

                foreach(self::$pseudo_classes as $pseudoClass)
                {
                        if(strpos($file_name_ext, '_'.$pseudoClass))
                        {
                                return $pseudoClass;
                        }
                }

                return false;
        }
}