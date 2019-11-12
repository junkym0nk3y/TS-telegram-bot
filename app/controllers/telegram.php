<?php
  namespace App\Controllers;
  
  /**
   * Class for communication with Telegram's API.
   */
  class Telegram {
    protected $tg_config; // Telegram token from Bot
    protected $proxy = null;

    function __construct( array $tg_config ) {
      $this->tg_config = $tg_config;
    }


    /**
     * Set up proxy settings, if you need it.
     * @param string  $proxy  Proxy adress and port
     */
    public function setProxy(): void
    {
      $this->proxy = $this->tg_config['proxy'];
    }


    public function resetWebhook(): array
    {
      $multidata = [
        [ 'url' => '' ],
        [ 'url' => 'https://' . $_SERVER['SERVER_NAME'] ],
      ];

      return $this->multiRequest( 'setWebhook', $multidata );
    }
    

    /**
     * Check if group is allowed by config.
     * @param string  $chatName       Chat name
     * @param array   $allowed_chats  Allowed list
     */
    public function checkGroup( string $chat_name, array $allowed, int $chat_id ): void
    {
      if ( !in_array($chat_name, $allowed) ){
        $this->request( 'leaveChat', ['chat_id' => $chat_id] );
        exit('ok');
      }
    }


    /**
     * Gets user's avatar data and save it.
     * @param  int     $id  User id
     * @return string|null  Jpeg image or null
     */
    public function getAvatar( int $id ) {
      $raw_photos = $this->request( 'getUserProfilePhotos', [ 'user_id' => $id ] );
      $array_photos = json_decode( $raw_photos, true );
      
      if ( $array_photos['ok'] && $array_photos['result']['total_count'] != 0 ) {
        $photo_id = $array_photos['result']['photos'][0][0]['file_id'];
        $raw_fileurl = $this->request( 'getFile', ['file_id' => $photo_id] );
        $array_fileurl = json_decode( $raw_fileurl, true );
        if ( !$array_fileurl['ok'] || is_null($array_fileurl['result']['file_size']) )
          return null;

        return $this->getFile( $array_fileurl, $id );
      } else
        return null;
    }


    /**
     * Returns status, aka - user is admin on this chat or simple member?
     * @param  int     $chat_id  Chat id
     * @param  int     $user_id  User id
     * @return string            User info
     */
    public function getStatus( int $chat_id, int $user_id ): string
    {
      $raw = $this->request( 'getChatMember', ['chat_id' => $chat_id, 'user_id' => $user_id] );
      $array = json_decode( $raw, true );
      if ( empty($array['ok']) )
        return $array['description'];

      return preg_replace( '/[^a-z]/iu', '', $array['result']['status'] );
    }


    /**
     * Returns chat admins
     * @param  int     $chat_id  Chat id
     * @return array             Admins list
     */
    public function getAdmins( int $chat_id ): array
    {
      $raw = $this->request( 'getChatAdministrators', ['chat_id' => $chat_id] );
      $array = json_decode( $raw, true );
      foreach ($array['result'] as $res) { 
        $result[] = $res['user']['id'];
      }
      return $result;
    }


    /**
     * Returns this bot's id, username and firstname.
     * @param  string $pattern [description]
     * @return array             Bot info
     */
    public function getBotInfo( string $pattern = '/[^a-zа-яёй0-9 \(\)\?\,\s\-\–\.\_\!]/iu' ): array
    {
      $raw = $this->request( 'getMe' );
      $array = json_decode( $raw, true );

      if ( !$array['result']['id'] )
        exit('ok');
      
      $result = [
        'id' => (int)$array['result']['id'],
        'username' => preg_replace( $pattern, '', $array['result']['username'] ),
        'first_name' => preg_replace( $pattern, '', $array['result']['first_name'] ),
      ];

      return $result;
    }


    /**
     * Send file as document to chat.
     * @param int     $chat_id
     * @param string  $file     File path
     */
    public function sendDocument(int $chat_id, string $file): void
    {
      $data = [
        'chat_id' => $chat_id,
        'document' => new \CURLFile( $file ),
      ];

      $this->request( 'sendDocument', $data, true );
    }


    /**
     * [getStickerSet description]
     * @param  string $name [description]
     * @return [type]       [description]
     */
    public function getStickerSet( string $name ): array
    {
      $raw = $this->request( 'getStickerSet', [ 'name' => $name ] );
      $array = json_decode( $raw, true );

      switch (true) {
        case !$raw:
          return [ 'error', '' ];
        case !empty( $array['ok'] ):
          return [ 'ok', count($array['result']['stickers']) ];
        default:
          return [ 'not exist', '' ];
      }
    }


    /**
     * Create new sticker set. The bot will be able to edit the created sticker set.
     * @param  array  $data [description]
     * @return array        [description]
     */
    public function createNewStickerSet( array $data ): array
    {
      $raw = $this->request( 'createNewStickerSet', $data, true );
      $array = json_decode( $raw, true );

      switch (true) {
        case !$raw:
          return [ 'error', '' ];
        case !empty( $array['ok'] ):
          return [ 'ok', '' ];
        default:
          return [ $array['error_code'], $array['description'] ];
      }
    }


    /**
     * [newSticker description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function addStickerToSet( array $data ): array
    {
      $raw = $this->request( 'addStickerToSet', $data, true );
      $array = json_decode( $raw, true );

      switch (true) {
        case !$raw:
          return [ 'error', '' ];
        case !empty( $array['ok'] ):
          return [ 'ok', '' ];
        default:
          return [ $array['error_code'], $array['description'] ];
      }
    }


    /**
     * Save file from Telegram.
     * @param  array   $array  Info about file
     * @param  int     $id     Id of file
     * @param  string  $path   Path to save this file
     * @return bool            Status of operation
     */
    private function getFile( array $array, int $id ) {
      $url_path = $array['result']['file_path'];
      $size = $array['result']['file_size'];
      $curl = curl_init();

      $options = [
        CURLOPT_URL => 'https://api.telegram.org/file/bot' . $this->tg_config['token'] .  '/' . $url_path,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_BINARYTRANSFER => true,
      ];
      curl_setopt_array( $curl, $options );

      if ( $this->proxy ){
        curl_setopt( $curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 );
        curl_setopt( $curl, CURLOPT_PROXY, $this->proxy );
      }

      $content = curl_exec( $curl );
      switch (true) {
        case curl_getinfo( $curl, CURLINFO_HTTP_CODE ) != 200:
        case curl_getinfo( $curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD ) != $size:
          curl_close( $curl );
          return null;
      }

      curl_close( $curl );
      return $content;
    }


    public function kickMembers( array $user_array, int $chat_id, int $until = null ): array
    {
      $until_date = !$until ? time() + 60 : $until;
        
      $multidata = [];
      foreach ( $user_array as $key => $id ) {
        $multidata[] = [ 
          'user_id' => $id,
          'chat_id' => $chat_id,
          'until_date' => $until_date, 
        ];
      }

      return $this->multiRequest( 'kickChatMember', $multidata );
    }


    protected function multiRequest( string $method, array $multidata = null ): array
    {
      $curls = [];
      $m_init = curl_multi_init();
      $result = [];
      $running = null; // running processes

      $options = [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $this->tg_config['token'] .  '/' . $method,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
      ];

      foreach ( $multidata as $id => $data ) {
        $curls[$id] = curl_init();
        curl_setopt_array( $curls[$id], $options );
        if ( $this->proxy ){
          curl_setopt( $curls[$id], CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 );
          curl_setopt( $curls[$id], CURLOPT_PROXY, $this->proxy );
        }
        if ( $data ) {
          curl_setopt( $curls[$id], CURLOPT_POST, 1 );
          curl_setopt( $curls[$id], CURLOPT_POSTFIELDS, $data );
        }
       curl_multi_add_handle( $m_init, $curls[$id] );
      }
      do { // Wait when curl end procedure
        curl_multi_exec( $m_init, $running ); 
      } while( $running > 0);
      foreach ( $curls as $id => $resonse ) { // Get all response data
        $result[$id] = curl_multi_getcontent( $resonse );
        curl_multi_remove_handle($m_init, $resonse);
      }
      curl_multi_close( $m_init ); // Free ram
      return $result;
    }



    /**
     * Formed request to Telegram API.
     * @param   string      $method  Mehtod name
     * @param   array|null  $data    
     * @return  bool
     */
    protected function request( string $method, array $data = null, bool $is_file = false ) {
      $curl = curl_init();

      $options = [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $this->tg_config['token'] .  '/' . $method,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
      ];

      curl_setopt_array( $curl, $options );
      if ( $this->proxy ){
        curl_setopt( $curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 );
        curl_setopt( $curl, CURLOPT_PROXY, $this->proxy );
      }
      if ( $data ) {
        curl_setopt( $curl, CURLOPT_POST, 1 );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
      }
      if ( $is_file )
        curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Content-Type:multipart/form-data' ] );
      $out = curl_exec( $curl );
      // DEBUG
      if ( DEBUG ) {
        $file = LOG_FOLDER . 'curl.log';
        $new_file_msg =  date('Y-m-d H:i:s') . PHP_EOL . 'makes new file' . PHP_EOL;
        if ( !file_exists($file) ) {
          file_put_contents( $file, $new_file_msg );
          chmod( $file, 0664 );
        }

        $array = json_decode( $out, true );
        $log = file_get_contents( $file, true ) . PHP_EOL . date( 'Y-m-d H:i:s' ) . PHP_EOL;
        $log .= print_r( $array, true );

        file_put_contents( $file, $log );
      }
      // DEBUG
      curl_close( $curl );
      return $out;
    }

  }
