<?php
  require './vendor/autoload.php';
  require './app/configs/config.php';

  use \App\Controllers\DB as DB;             // MySQL DB connector
  use \App\Controllers\DB_post as DB_post;   // Extends DB class for write
  use \App\Controllers\Json_Decoder as Json; // Gets json data from webhook and decode it to array
  use \App\Controllers\Telegram as Telegram; // Class for communication with api
  use \App\Views\Telegram_Post as TG_post;   // Extended class for sending data
  use \App\PDO_start as PDO_start;           // PDO init
  use \App\Router as Router;                 // Big and ugly controller, for bot's logic


  /**
   * 
   * Controllers
   * 
   */
  $dec = new Json( $redirect );
  $id = $dec->getID();                             // Chat and User ID
  $chat_name = $dec->chatName();                   // Chat name where request come from
  $tg = new Telegram( $settings['telegram'] );     // Class for communication with api
  //$tg->setProxy();                                 // Comment this, unless proxy needed
  $tg->checkGroup( $chat_name, $allowed, $id[0] ); // Check out if this chat in allowed list and leave it if not

  PDO_start::getConnect( $settings['database'] );
  $router = new Router( $dec->collectData(), $tg, new DB($chat_name), new DB_post($chat_name) ); 
  $router->setMaster( $settings['telegram']['master'], true );  // Remove true, unless you need override admin access to bot
  $action = $router->getAction( $lang );

  /**
   * 
   * Send message trough API
   * 
   */
  $send = new TG_post( $settings['telegram'] );
  //$send->setProxy(); // Comment this, unless proxy needed
  $send->sendMsg( $action, $id );
?>
