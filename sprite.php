<?php
namespace PHPAutoSprites
{
        require_once('exception.php');
        
        abstract class Sprite
        {
                const DATA_DIR = 'data';
                const IMAGE_DIR = '../images';
                const TRACK_FILE = 'tracks.json';
                const SPRITE_FILE = 'sprites.json'; // DATA_DIR > SPRITE_FILE               
                const SPRITE_DIR = 'sprites'; // IMAGE_DIR > SPRITE_DIR
                const SPRITE_URL = 'images/sprites/';
                const PNG_COMPRESSION = 9;
                const MAX_SPRITE_HEIGHT = 200;
                
                protected static $pseudo_classes = array(
                    'active', 'hover'
                );
                
                public static function image($src, $attributes = array(), $sprite_nr = -1)
                {
                        return self::htmlElement('div', $src, $attributes, $sprite_nr);
                }
                
                public static function input($src, $attributes = array(), $sprite_nr = -1)
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
                        
                        return self::htmlElement('input', $src, $attributes, $sprite_nr);
                }
                               
                protected static $sprite_existing = true;
                public static function htmlElement($element, $src, $attributes = array(), $sprite_nr = -1)
                {
                        self::trackImage($src, $sprite_nr);                        
                        
                        $sprite = self::getSpriteData($src);
                        
                        $html = '<'.$element;
                        
                        if(!$sprite)
                        {
                                self::createSprites();
                                
                                $sprite = self::getSpriteData($src);        
                                
                                if(!$sprite) // image not existing
                                {
                                        throw new AutoSpritesException('Image '.$src.' could not be loaded.');
                                }
                                
                                $html .= ' style="'.addslashes(self::getCSS($src)).'"';
                                
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
                
                protected static function trackImage($src, $sprite_nr)
                {
                        $tracks = self::getFileContent(self::TRACK_FILE);
                        
                        $tracks[$src] = $sprite_nr;
                        self::setFileContent(self::TRACK_FILE, $tracks);
                }
                
                protected static function getSpriteData($src)
                {
                        $sprites = self::getFileContent(self::SPRITE_FILE);
                        
                        if(isset($sprites[$src]))
                        {
                                return $sprites[$src];
                        }
                        
                        return false;
                }
                
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

                                        $info = getimagesize($src);

                                        $image = null;
                                        if($info['mime'] == 'image/png')
                                        {
                                                $image = imagecreatefrompng($src);
                                        }
                                        elseif($info['mime'] == 'image/jpeg')
                                        {
                                                $image = imagecreatefromjpeg($src);
                                        }

                                        if(!$image)
                                        {
                                                throw new AutoSpritesException('Image '.$src.' has unknown format.');
                                        }

                                        $images[$src] = array(
                                            'sprite_nr' => $sprite_nr,
                                            'image' => $image
                                            );
                                }
                        }
                        
                        // assign images to sprites
                        $sprites = array();
                        $sprite_height = 0;
                        $sprite_width = 0;
                        $index = 0;
                        foreach($images as $src => $data)
                        {
                                $image = $data['image'];
                                $sprite_nr = $data['sprite_nr'];
                                
                                $i = $sprite_nr;
                                if($sprite_nr < 0)
                                {
                                        $i = 'auto'.$index;
                                        
                                        if(!isset($sprites[$i]) || $sprites[$i]['height'] >= self::MAX_SPRITE_HEIGHT)
                                        {
                                                $sprite_height = 0;
                                                $sprite_width = 0;
                                                $index++;
                                                $i = 'auto'.$index;
                                        }
                                }
                                
                                $width = imagesx($image);
                                if($sprite_width < $width)
                                {
                                        $sprite_width = $width;
                                }
                                
                                $height = imagesy($image);
                                $sprite_height += $height;
                                
                                if(!isset($sprites[$i]))
                                {
                                        $sprites[$i] = array(
                                                'images' => array(),
                                                'width' => 0,
                                                'height' => 0
                                        );
                                }
                                $sprites[$i]['images'][$src] = $image;
                                $sprites[$i]['width'] = $sprite_width;
                                $sprites[$i]['height'] = $sprite_height;
                        }
                        
                        foreach($sprites as $sprite)
                        {
                                $sprite_file_name = md5(time().microtime());

                                $last_height = 0;
                                $sprite_position_x = 0; // current x position where to place next image in sprite
                                $sprite_position_y = 0; // current y position where to place next image in sprite
                                $sprite_res = imagecreatetruecolor($sprite['width'], $sprite['height']);
                                
                                foreach($sprite['images'] as $src => $image)
                                {
                                        $width = imagesx($image);
                                        $height = imagesy($image);

                                        if(($sprite_position_x+$width) > $sprite_width)
                                        {
                                                $sprite_position_x = 0;
                                                $sprite_position_y += $last_height;
                                                $last_height = 0;
                                        }

                                        $copied = imagecopyresampled($sprite_res, $image, $sprite_position_x, $sprite_position_y, 0, 0, $width, $height, $width, $height);

                                        if(!$copied)
                                        {
                                                throw new AutoSpritesException('Could not copy image to sprite.');
                                        }

                                        $sprite_content[$src] = array(
                                                'sprite' => $sprite_file_name,
                                                'x' => $sprite_position_x,
                                                'y' => $sprite_position_y,
                                                'width' => $width,
                                                'height' => $height);

                                        if($last_height < $height)
                                        {
                                                $last_height = $height;
                                        }

                                        $sprite_position_x = $sprite_position_x+$width;
                                }

                                self::deleteSprites();
                                self::setFileContent(self::SPRITE_FILE, $sprite_content);

                                imagepng($sprite_res, __DIR__.DIRECTORY_SEPARATOR.self::IMAGE_DIR.DIRECTORY_SEPARATOR.self::SPRITE_DIR.DIRECTORY_SEPARATOR.$sprite_file_name.'.png', self::PNG_COMPRESSION);                        
                        }
                }
                
                protected static $file_memorization = array();
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
                
                protected static function setFileContent($file, array $content)
                {
                        $file = __DIR__.DIRECTORY_SEPARATOR.self::DATA_DIR.DIRECTORY_SEPARATOR.$file;
                        $handle = fopen($file, 'w+');
                        
                        fwrite($handle, json_encode($content));
                        fclose($handle);
                        
                        self::$file_memorization[$file] = $content;
                }
                
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
                
                protected static function getClassName($str)
                {
                        $hash = md5(self::getSrcWithoutPseudoClass($str));
                        $hash = 'sprite_'.substr($hash, 0, 10);
                        return $hash;
                }
                
                protected static function getCSS($src)
                {
                        $sprite = self::getSpriteData($src);

                        if($sprite)
                        {
                                $css =  'display: inline-block;
                                        background-image: url('.self::SPRITE_URL.$sprite['sprite'].'.png);
                                        background-position: -'.$sprite['x'].'px -'.$sprite['y'].'px;
                                        background-color: transparent;
                                        width: '.$sprite['width'].'px;
                                        height: '.$sprite['height'].'px';
                        }
                        
                        $css = str_replace(array(
                            "\r",
                            "\n",
                            '  '
                        ), '', $css);
                        
                        return $css;
                }
                
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
}
?>