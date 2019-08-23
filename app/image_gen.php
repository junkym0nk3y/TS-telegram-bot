<?php
  namespace App;
  use \Imagick as Imagick;
  use \ImagickDraw as ImagickDraw;

  class Image_Gen {
    private $bold_font = '/style/fonts/Roboto-Bold.ttf';
    private $regular_font = '/style/fonts/Roboto-Regular.ttf';

    function __construct() {
    }

    /**
     * Make new user's avatar.
     * @param  string      $nickname    Username
     * @param  string      $background  Background hex color
     * @param  int|integer $size        Avatar size
     * @return string                   Png image
     */
    public function makeAvatar( string $nickname, string $background, int $size = 100 ): string
    {
      $words = explode( ' ', $nickname );

      if ( isset($words[1]) ) {
        $letter = mb_substr( $words[0], 0, 1,'UTF8' );
        $letter .= mb_substr( $words[1], 0, 1,'UTF8' );
      } else  
        $letter = mb_substr( $nickname, 0, 1,'UTF8' );
      
      $letter = strtoupper( $letter );
      $draw = new ImagickDraw();
      $draw->setFont( $_SERVER['DOCUMENT_ROOT'] . $this->bold_font );
      $draw->setFontSize( 34 );
      $draw->setFillColor( '#fff' );
      $draw->setStrokeColor( '#fff' );
      $draw->setStrokeWidth( 0.2 );
      $draw->setGravity( Imagick::GRAVITY_CENTER );
      $imagick = new Imagick();
      $imagick->newImage( $size, $size, $background );
      $imagick->annotateImage( $draw, 0, 0, 0, $letter );
      $imagick->setImageFormat( 'jpg' );
      
      $imagick->drawImage($draw);
      return $imagick;
    }


    /**
     * Make png image with user quote.
     * @param  string  $text        Quote text
     * @param  string  $nickname    User's nickname
     * @param  string  $nick_color  Hex color code
     * @param  string  $avatar      Path to user's avatar
     * @return string               Png image
     */
    public function quoteToSticker( string $text, string $nickname, string $nick_color, string $avatar ): string
    {
      $new_text = preg_replace( '/(.{1,28})(?:\s|$)|(.{28})/uS', '$1$2'."\n", $text ); // wrap text
      $lines = substr_count( $new_text, "\n" );
      $width = 408;
      $avatar_size = 90;
      $height = ( $lines -1 ) * 28.75 + $avatar_size;
      $offset = 14;
      $background_color = '#222e3a';

      // Init
      $draw = new ImagickDraw();
      $draw->setTextAntialias( true );
      
      // Core
      $im_core = new Imagick(); 
      $im_core->newImage( $width + $avatar_size + $offset, $height, 'Transparent' ); 
      $im_core->setImageFormat( 'png' ); 
      $im_core->drawImage( $draw );
      
      // Arrow
      $arrow = new imagick();
      $draw->setFillColor( $background_color );
      $points = [
        [ 'x' => 0, 'y' => $offset ],
        [ 'x' => $offset, 'y' => 0 ],
        [ 'x' => $offset * 2, 'y' => $offset ],
      ];
      $draw->polygon( $points ) ;
      $arrow->newImage( 28, $offset, 'Transparent' );
      $arrow->drawImage($draw);
      
      // Background
      $rectangle = new Imagick();
      $draw->setFillColor( $background_color ); 
      $draw->roundRectangle( 0, 0, $width, $height, 10, 10 );
      $rectangle->newImage( $width, $height, 'Transparent' ); 
      $rectangle->drawImage( $draw ); 
      
      // Nickname
      $draw->setFont( $_SERVER['DOCUMENT_ROOT'] . $this->bold_font );
      $nick = new Imagick();
      $nick->newImage( $width, 42, 'Transparent' );
      $draw->setFontSize( 28 );
      $draw->setTextAntialias( true );
      $draw->setFillColor( $nick_color );
      $nick->annotateImage( $draw, 0, 20, 0, $nickname );
      
      // Quote
      $draw->setFont( $_SERVER['DOCUMENT_ROOT'] . $this->regular_font );
      $quote = new Imagick();
      $quote->newImage( $width, $height, 'Transparent' );
      $draw->setFontSize( 26 );
      $draw->setTextInterlineSpacing( -2 );
      $draw->setFillColor( '#fff' );
      $quote->annotateImage( $draw, 0, 20, 0, $new_text );
      
      // Avatar
      $image = new Imagick( $avatar );
      $image->scaleImage( $avatar_size, $avatar_size );
      $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_RESET);
      // create mask image
      $mask = new Imagick( );
      $mask->newImage($avatar_size, $avatar_size, 'transparent');
      // create the rounded rectangle
      $shape = new ImagickDraw();
      $shape->setFillColor( $background_color );
      $shape->roundRectangle(0, 0, $avatar_size, $avatar_size, $avatar_size, $avatar_size);
      // draw the rectangle
      $mask->drawImage($shape);
      // apply mask
      $image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
      
      // Composite
      $im_core->compositeImage( $arrow,     Imagick::COMPOSITE_OVER, $avatar_size, $height -$offset );
      $im_core->compositeImage( $rectangle, Imagick::COMPOSITE_OVER, $avatar_size + $offset, 0 );
      $im_core->compositeImage( $nick,      Imagick::COMPOSITE_OVER, $avatar_size + $offset * 2, $offset +2 );
      $im_core->compositeImage( $quote,     Imagick::COMPOSITE_OVER, $avatar_size + $offset * 2, 50 );
      $im_core->compositeImage( $image,     Imagick::COMPOSITE_OVER, 0, $height - $avatar_size );
    
      return $im_core;
    }

    /**
     * Pick random color hex.
     * @return string
     */
    public function randomColor(): string
    {
      $colors = [
        '#e35824',
        '#2196f3',
        '#0ebfa5',
        '#adc81a',
        '#ff8c7e',
        '#ffc107',
        '#37eda6',
        '#e87a0b',
        '#30ccd8',
        '#4db3f9',
      ];
      $random_color = mt_rand( 0, count($colors) -1 );
      return $colors[$random_color];
    }

  }
