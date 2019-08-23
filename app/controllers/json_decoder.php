<?php
  namespace App\Controllers;

  /**
   * Returns PHP readeble data, from Telegram webhook.
   */
  class Json_Decoder {
    private $json;
    private $pattern = '/[^a-zа-яёй0-9 \(\)\?\,\s\-\–\.\_\!]/iu';
    private $msg_pattern = '/[^a-zа-яёй0-9 \=\:\(\)\?\,\s\+\-\–\.\!]/iu';

    function __construct( array $settings, string $redirect_to ) {
      $json = file_get_contents( 'php://input' );
      $this->json = json_decode( $json, true );
      // DEBUG 
      if ( DEBUG ) {
        $this->saveLog( LOG_FOLDER . 'json_d.log' );
        $this->checkWebhook( $settings, LOG_FOLDER . 'history.log', LOG_FOLDER . 'dublicates.log' );
      }
      // DEBUG
      $this->verifyAccess( $redirect_to );
    }

    /**
     * Verify access and redirect if forbidden.
     * @param  string $url Url page for redirect
     */
    private function verifyAccess( string $redirect_to ): void
    {
      $tg_subs1 = '/^149\.154\.(16[0-9]|17[0-5])\.([1-9]|[1-8][0-9]|9[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-4])$/';
      $tg_subs2 = '/^91\.108\.([4-7])\.([1-9]|[1-8][0-9]|9[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-4])$/';
      $from_tg1 = preg_match( $tg_subs1, $_SERVER['REMOTE_ADDR'] );
      $from_tg2 = preg_match( $tg_subs2, $_SERVER['REMOTE_ADDR'] );
      $from_tg_server = ( $from_tg1 || $from_tg2 );

      switch (true) {
        case !empty( $_FILES ):                                   // Don't allow upload files
        case ( !$from_tg_server ):                                // Post not from Telegram Servers
          header( "Location: $redirect_to" );
          die();
        case !isset( $this->json['message'] ):                    // No message
        case isset( $this->json['message']['forward_from'] ):     // Forward
        case isset( $this->json['edited_message'] ):
        case $this->json['message']['from']['is_bot'] === true:   // Message from bot
        case $this->json['message']['chat']['type'] == 'private': // Not supported type of chat
        case $this->json['message']['chat']['type'] == 'channel': // Same thing
          exit('ok');
        default:
          return;  
      }
    }


    /**
     * Gets user id and chat id only.
     * @return array
     */
    public function getID(): array 
    {
      $chat_id = $this->json['message']['chat']['id'];
      $message_id = $this->json['message']['message_id'];
      return [ (int)$chat_id, (int)$message_id ];
    }


    /**
     * Return chat name for DB.
     * @return string  Table name
     */
    public function chatName(): string
    {
      return preg_replace( $this->pattern, '', $this->json['message']['chat']['username'] );
    }


    /**
     * Complete data for router.
     * @return array  One dimension array
     */
    public function collectData(): array
    {
      $user = $this->pregCheck( $this->json['message']['from'], ['id', 'username', 'is_bot', 'first_name'], 'user_', 30 );
      $user['new_member'] = isset( $this->json['message']['new_chat_member'] ) ? 1 : 0;
      if( empty($user['user_username']) ) 
        $user['user_username'] = $user['user_first_name'];

      $chat = $this->pregCheck( $this->json['message']['chat'], ['id', 'username', 'type', 'title'], 'chat_', 30 );
      $reply = $this->getReply( ['id', 'username', 'is_bot', 'first_name'] );
      $text = $this->getMsg( 1024 );
      return array_merge( $user, $chat, $reply,  $text ); 
    }


    /**
     * Returns some sender data and message text.
     * @param  int|integer  $max_chars  Max length
     * @return array
     */
    private function getMsg( int $max_chars = 1024 ): array
    {
      if ( isset($this->json['message']) && isset($this->json['message']['text']) ) {
        $msg_arr = [];
        $msg_arr['message_id'] = (int)$this->json['message']['message_id'];
        $preg = preg_replace( $this->msg_pattern, '', $this->json['message']['text'] );
        $msg_arr['msg_text'] = mb_substr( $preg, 0, $max_chars, 'UTF-8' );
        return $msg_arr;
      } else
        return [ 'msg_text' => false ];
    }


    /**
     * Returns reply info.
     * @param  array        $allowed    Whitelist
     * @param  int|integer  $max_chars  Max length
     * @return array
     */
    private function getReply( array $allowed, int $max_chars = 30 ): array
    {
      if ( isset($this->json['message']['reply_to_message']) ) {
        $reply = $this->pregCheck( $this->json['message']['reply_to_message']['from'], $allowed, 'reply_from_', $max_chars );
        $reply['has_reply'] = true;
        $reply['reply_text'] = null;
        if ( isset($this->json['message']['reply_to_message']['text']) )
          $reply['reply_text'] = preg_replace( $this->msg_pattern, '', $this->json['message']['reply_to_message']['text'] );
        
        if ( empty($reply['reply_from_username']) ) 
          $reply['reply_from_username'] = $reply['reply_from_first_name'];

        $reply['reply_date'] = (int)$this->json['message']['reply_to_message']['date'];
        return $reply;
      } else
        return [ 'has_reply' => null ];
    }


    /**
     * Make array save for DB.
     * @param  array        $array       Any row array for checking
     * @param  array        $allowed     Whitelist
     * @param  string       $add_to_key  Will add this prefix for keys
     * @param  int|integer  $max_chars   Max length
     * @return array
     */
    private function pregCheck( array $array, array $allowed, string $add_to_key = '', int $max_chars ): array
    {
      $new_arr = [];
      foreach ( $array as $key => $value ) {
        if ( in_array( $key, $allowed ) ){
          if ($key == 'id') 
            $new_arr[ $add_to_key . $key ] = (int)$value;
          else {
            $val = !isset( $value ) ? '' : preg_replace( $this->pattern, '', $value );
            $new_arr[ $add_to_key . $key ] = mb_substr( $val, 0, $max_chars, 'UTF-8' ); 
          }
        }
      }
      return $new_arr;
    }


    private function checkWebhook( array $settings, string $history_log, string $dublicates ): void
    { 
      $result = '';
      $new_file_msg =  date('Y-m-d H:i:s') . PHP_EOL . 'makes new file' . PHP_EOL;

      if ( !isset($this->json['update_id']) )
        return;
      // Make new log files
      if ( !file_exists($history_log) ) {
        file_put_contents( $history_log, $new_file_msg );
        chmod( $history_log, 0664 );
      }
      if ( !file_exists($dublicates) ) {
        file_put_contents( $dublicates, $new_file_msg );
        chmod( $dublicates, 0664 );
      }

      if ( isset($this->json['edited_message']) ){
        $json = $this->json['edited_message'];
      } else
        $json = $this->json['message'];

      $update = (int)$this->json['update_id'];

      // Read history file and make array 
      $history = file_get_contents( $history_log, true );
      $history_array = explode("\n", $history);
      
      if ( in_array($update, $history_array) ) { // Takes dublicates from webhook
        $old_dubl = file_get_contents( $dublicates, true ); 
        $old_dubl .= date( 'Y-m-d H:i:s' ) . PHP_EOL;
        file_put_contents( $dublicates, $old_dubl );
        exit('ok');
      }

      $new_history = $history . $update . PHP_EOL;

      if ( substr_count($new_history, "\n") > 5 ) // Write new history
        $new_history = preg_replace( '/^.+\n/', '', $new_history);

      if ( !empty( $update ) )
        file_put_contents( $history_log, $new_history );
    }


    /**
     * SaveLog for debug, you may no need it.
     * @param string  $file  Path where save logs
     */
    private function saveLog( string $file ): void
    {
      if ( !file_exists($file) ) {
        $new_file_msg =  date('Y-m-d H:i:s') . PHP_EOL . 'makes new file' . PHP_EOL;
        file_put_contents( $file, '' );
        chmod( $file, 0664 );
      }

      $log = file_get_contents( $file, true ) . PHP_EOL . date( 'Y-m-d H:i:s' ) . PHP_EOL;
      $log .= print_r( $this->json, true );
      file_put_contents( $file, $log );
    }
  }
