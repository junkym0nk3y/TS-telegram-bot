<?php
  setlocale( LC_TIME, 'ru_RU.utf8' );
  date_default_timezone_set( 'Europe/Moscow' );
  header( 'Content-Type: application/json; charset=utf-8', true, 200 );
  define( 'DEBUG', true );
  define( 'LOG_FOLDER', $_SERVER['DOCUMENT_ROOT'] . '/logs/' );

  $lang     = parse_ini_file( './app/configs/bot.ini', true );                  // List of bot's commands
  $settings = parse_ini_file( '/var/www/telegram.ini', true );                  // Tg Token, Proxy, DB credentials etc
  $allowed  = [ 'stoner_music', 'sysodmins_chat', 'progeri_chat', 'dubl_dva' ]; // Only for this chats - bot will respond
  $redirect = 'https://github.com/junkym0nk3y/TS-telegram-bot';                 // Redirect url

  // DEBUG
  if ( DEBUG ) {
    if ( !file_exists(LOG_FOLDER) )
      mkdir( LOG_FOLDER, 0755, true );
    ini_set( 'display_errors', 1 );
    error_reporting( E_ALL );
    error_reporting( -1 );
  } else {
    function deleteAll( $str ) {
      if ( is_file($str) ) {
          return unlink( $str );
      }
      elseif ( is_dir($str) ) {
          $scan = glob( rtrim($str,'/').'/*' );
          foreach( $scan as $index=>$path ) {
              deleteAll( $path );
          }
          return @rmdir( $str );
      }
    }

    deleteAll( LOG_FOLDER );
    error_reporting( 0 );
  }
  // DEBUG
