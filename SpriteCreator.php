<?php
/**
 * @author Christian Kilb 
 */
class SpriteCreator
{
        protected $images = array();
        
        /**
         * add image to sprite
         * @param string $image_src source of image
         * @throws SpriteException 
         */
        public function addImage($image_src)
        {
                $info = getimagesize($image_src);
                if(!$info)
                {
                        throw new SpriteException('Could not read '.$image_src.'.');
                        return;
                }
                
                $image = null;
                switch($info['mime'])
                {
                        case image_type_to_mime_type(IMAGETYPE_JPEG):
                                $image = imagecreatefromjpeg($image_src);
                                break;
                        case image_type_to_mime_type(IMAGETYPE_PNG):
                                $image = imagecreatefrompng($image_src);
                                break;
                        case image_type_to_mime_type(IMAGETYPE_GIF):
                                $image = imagecreatefromgif($image_src);
                                break;
                }
                
                if(!$image)
                {
                        throw new SpriteException('Unsupported mime type of '.$image_src.'.');
                        return;
                }
                
                // this key is necessary for sorting images by width
                $key = sprintf('%09d',$info[0]).sprintf('%04d',rand(0,9999));
                $this->images[$key] = array(
                    'resource' => $image,
                    'src' => $image_src);
        }
        
        /**
         * calculates and returns information about this sprite
         * @return array containing width, height, images and their positions
         * @throws SpriteException 
         */
        public function getSpriteData()
        {
                if(count($this->images) < 1)
                {
                        throw new SpriteException('No images added.');
                        return;
                }
                
                // sort images by width, biggest first
                krsort($this->images);
                
                // sprite width = biggest width of all images
                $biggest = array_shift($images = $this->images);
                $spriteWidth = imagesx($biggest['resource']);
                $spriteHeight = 0;
                
                // $freeBlocks will save top left x and y coordinates, width and height, where image still can be placed
                $freeBlocks = array(
                    0 => array(
                        'x' => 0,
                        'y' => 0,
                        'height' => 0, // height = 0 means, block is at the bottom of the sprite image
                        'width' => $spriteWidth
                    )
                );
                
                // $imagePositions will save where each image will be placed in div (x,y)
                $imagePositions = array();
                
                // get image positions
                foreach($this->images as $arr)
                {
                        $image = $arr['resource'];
                        
                        $chosenBlockKey = null;
                        $chosenBlock = null;
                        foreach($freeBlocks as $key => $block)
                        {
                                // check if image fits in block
                                if($block['width'] >= imagesx($image) &&
                                        ($block['height'] >= imagesy($image) || $block['height'] == 0))
                                {
                                        // check if this block is better than chosen block before
                                        if($chosenBlock == null ||
                                                ($chosenBlock['height'] == 0 || $chosenBlock['width'] > $block['width']))
                                        {
                                                $chosenBlockKey = $key;
                                                $chosenBlock = $block;
                                        }
                                }
                        }
                        
                        // remove block from freeBlocks array
                        unset($freeBlocks[$chosenBlockKey]);
                        
                        // if this block has no height (= is at the bottom of sprite image), increase sprite height
                        if($chosenBlock['height'] == 0)
                        {
                                $spriteHeight += imagesy($image);
                                
                                // add new block under this image (sprite bottom)
                                $freeBlocks[] = array(
                                    'x' => 0,
                                    'y' => $chosenBlock['y']+imagesy($image),
                                    'height' => 0,
                                    'width' => $spriteWidth
                                );
                        }
                        
                        // add block under this image
                        elseif((imagesy($image)+$chosenBlock['y']) < $spriteHeight)
                        {
                                $freeBlocks[] = array(
                                    'x' => $chosenBlock['x'],
                                    'y' => $chosenBlock['y']+imagesy($image),
                                    'height' => $chosenBlock['height']-imagesy($image),
                                    'width' => $spriteWidth
                                );
                        }
                       
                        // add block right behind this image
                        if(($chosenBlock['x']+imagesx($image)) < $spriteWidth)
                        {
                                $freeBlocks[] = array(
                                    'x' => $chosenBlock['x']+imagesx($image),
                                    'y' => $chosenBlock['y'],
                                    'height' => imagesy($image)-1,
                                    'width' => $spriteWidth-($chosenBlock['x']+imagesx($image))
                                );
                        }
                        
                        $imagePositions[] = array(
                            'image' => $image,
                            'src' => $arr['src'],
                            'x' => $chosenBlock['x'],
                            'y' => $chosenBlock['y'],
                            'width' => imagesx($image),
                            'height' => imagesy($image)
                        );
                }
                
                return array(
                    'height' => $spriteHeight,
                    'width' => $spriteWidth,
                    'positions' => $imagePositions
                );
        }
        
        /**
         * returns sum of all image areas
         * @return int sum of all image areas
         */
        public function getPixelSum()
        {
                $sum = 0;
                foreach($this->images as $image)
                {
                        $area = imagesx($image['resource'])*imagesy($image['resource']);
                        $sum += $area;
                }
                
                return $sum;
        }
        
        /**
         * returns image positions 
         * @return array image positions
         */
        public function positions()
        {
                $spriteData = $this->getSpriteData();
                foreach($spriteData['positions'] as $key => $data)
                {
                        unset($data['image']);
                        $spriteData['positions'][$key] = $data;
                }
                return $spriteData['positions'];
        }
        
        /**
         * saves sprite as png file
         * @param string $fileName location of sprite png
         */
        public function output($fileName)
        {
                $spriteData = $this->getSpriteData();
                
                $sprite = imagecreatetruecolor($spriteData['width'],$spriteData['height']);
                
                $transparent = imagecolorallocatealpha($sprite, 0, 0, 0, 127); 
                imagefill($sprite, 0, 0, $transparent); 
                
                foreach($spriteData['positions'] as $position)
                {
                        $x = $position['x'];
                        $y = $position['y'];
                        $image = $position['image'];
                        $width = imagesx($image);
                        $height = imagesy($image);
                        imagecopyresampled($sprite, $image, $x, $y, 0, 0, $width, $height, $width, $height);
                }
                
                imagealphablending($sprite, false);
                imagesavealpha($sprite, true);
                
                imagepng($sprite, $fileName);          
                imagedestroy($sprite);
        }
}
?>
