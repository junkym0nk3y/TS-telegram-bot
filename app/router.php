<?php
  namespace App;
  use \App\Controllers\Telegram as Telegram;
  use \App\Controllers\DB as DB;
  use \App\Controllers\DB_post as DB_post;
  use \App\Image_Gen as Image_Gen;

  class Router
  {
    private $info;                   
    private $tg;                      
    private $db;                      
    private $db_post;                 
    private $bot;                     // Bot's info, array
    private $lang;                    // Parsed language file
    private $is_admin;                // This user is admin of chat or not?
    private $bot_master;              


    /**
     * [__construct description]
     * @param array    $decoder  Post data
     * @param Telegram $tg       Get data request from Telegram's API
     * @param DB       $db       Get data from DB
     * @param DB_post  $db_post  Send data to DB
     */
    function __construct( array $decoder, Telegram $tg, DB $db, DB_post $db_post ) {
      $this->info = $decoder;
      $this->tg = $tg;
      $this->db = $db;
      $this->db_post = $db_post;
      $this->info['status'] = $tg->getStatus( $this->info['chat_id'], $this->info['user_id'] );
      $this->is_admin = in_array( $this->info['status'], ['administrator', 'creator', 'owner'] ) ? 1 : 0;
      $this->dbUpdate();
    }


    /**
     * Override member status of this user.
     * It makes him - bot's overload, buagagagaga.
     * @param int  $owner_id  ID
     */
    public function setMaster(int $bot_master, bool $override_admins = false ): void
    {
    $this->bot_master = $bot_master;

    if ( $override_admins && $this->info['user_id'] == $this->bot_master )
      $this->is_admin = 1;
    }


    /**
     *
     * 
     * Update info in DB of users.
     *
     * 
     */
    private function dbUpdate(): void
    {
      $db_users = $this->db->getUsers();
      $user_not_exist = $db_users == 'no users' ? true : !array_key_exists( $this->info['user_id'], $db_users );
      $leave = [ 'left', 'kicked' ];
      $avatars_path = '/data/avatars/';

      if ( in_array($this->info['status'], $leave) ){ // Remove from DB, if leave
        $this->db_post->userLeave( $this->info['user_id'] );
        exit('ok');
      } 

      if ( $user_not_exist ) { // Username not found in DB
        $user_id = $this->info['user_id'];
        $username = $this->info['user_first_name'];
        $img = new Image_Gen();
        $hex = $this->info['hex_color'] = $img->randomColor();
        $this->avatarCheck( $avatars_path, $user_id, $username, $hex ); // Make avatar if not exist
        $allowed_post = [ 'user_id', 'user_first_name', 'hex_color', 'status' ];
        $this->db_post->userAdd( $this->info, $allowed_post );
      } else { // Update user in DB
        $allowed_post = [ 'user_id', 'user_first_name', 'status' ];
        $this->db_post->userUpdate( $this->info, $allowed_post );
      }

      if ( $this->info['has_reply'] && !$this->info['reply_from_is_bot'] ) { // Find reply, not from bot
        $reply_id = $this->info['reply_from_id'];
        $reply_status = $this->tg->getStatus( $this->info['chat_id'], $reply_id );
        $reply_user_not_exist = $db_users == 'no users' ? true : !array_key_exists( $reply_id, $db_users );

        if ( !in_array($reply_status, $leave) && $reply_user_not_exist ) { // Add user from reply to db
          $reply_username = $this->info['reply_from_first_name'];
          $img = new Image_Gen();
          $reply_hex = $img->randomColor();
          $post = [
            'user_id' => $reply_id, 'user_first_name' => $reply_username,
            'hex_color' => $reply_hex, 'status' => $reply_status,
          ];
          $reply_allowed_post = [ 'user_id', 'user_first_name', 'hex_color', 'status' ];
          $this->avatarCheck( $avatars_path, $reply_id, $reply_username, $reply_hex ); // Make avatar if not exist
          $this->db_post->userAdd( $post, $reply_allowed_post );
        }
      }

    }


    /**
     * [getAction description]
     * @param  array  $lang [description]
     * @return [type]       [description]
     */
    public function getAction( array $lang ): array
    { 
      $this->lang = $lang;
      $this->bot = $this->tg->getBotInfo();
      $bot_name = preg_match( '/\b(' . $this->bot['first_name'] . ')\b/iu', $this->info['msg_text'] );
      $alter_name = preg_match( '/\b(' . $this->lang['command']['alter_name'] . ')\b/iu', $this->info['msg_text'] );
      $bot_call = ( $bot_name || $alter_name );

      if ( $this->info['new_member'] ) { // Welcome message
        /*
          $username = !empty( $username ) ? $username : $this->info['user_first_name'];
          $find = ['{{ username }}', '{{ user_id }}'];
          $replace = [ 'username' => $username, 'user_id' => $this->info['user_id'] ];
          $msg = str_replace( $find ,$replace , $this->lang['respond']['welcome'] );
          return [ 'sendMsg', $msg ];
         */
        exit('ok');
      }

      switch (true) {
      // 1. If user calls bot by name
        case $bot_call && !$this->info['has_reply']:;
          return [ 'sendReply', $this->callBot() ];
      // 2. When user reply another user and calls bot by name
        case $bot_call && $this->info['has_reply']:
          return [ 'sendReply', $this->replyUserAndCallBot() ];
      // 3. When user reply this bot
        case $this->info['has_reply'] && $this->info['reply_from_id'] == $this->bot['id']:
          return [ 'sendReply', $this->replyBot() ];
      // 4. When user reply another user
        case $this->info['has_reply']:
          return [ 'sendMsg', $this->replyUser() ];
        default:
          exit('ok');
      }
    }


    /**
     *
     * 
     * 1. If user calls bot by name.
     * @return string  Message
     *
     * 
     */
    private function callBot(): string
    {
      $owner_id = null;
      $user_id = $this->info['user_id'];
      $username = $this->info['user_username'];
      $first_name = $this->info['user_first_name'];

      switch ( true ) {
      // Help file
        case preg_match( '/\b(' . $this->lang['command']['help'] . ')\b/iu', $this->info['msg_text'] ):
          $path = $_SERVER['DOCUMENT_ROOT'] . $this->lang['respond']['help_file'];
          $tmpl = file_get_contents( $path, true );
          $karma_plus = $this->lang['command']['karma_plus'][0];
          $lies = $this->lang['command']['lies'][0];
          $find = [ 
            '{{ url }}', '{{ bot_name }}', '{{ chat_name }}',
            '{{ ask_bio }}', '{{ save_bio }}', '{{ karma }}',
            '{{ add_sticker }}', '{{ add_set }}', '{{ my_stickers }}',
            '{{ all_stickers }}','{{ karma_plus }}','{{ karma_minus }}', 
            '{{ lies }}', '{{ not_liar }}',
          ];
          $replace = [ 
            'url' => 'github.com/junkym0nk3y/TS-telegram-bot/',
            'bot_name'     => $this->bot['first_name'], 
            'chat_name'    => $this->info['chat_title'],
            'ask_bio'      => $this->lang['command']['ask_bio'],
            'save_bio'     => $this->lang['command']['save_bio'], 
            'karma'        => $this->lang['command']['karma'],
            'add_sticker'  => $this->lang['command']['add_sticker'],
            'add_set'      => $this->lang['command']['add_set'],
            'my_stickers'  => $this->lang['command']['my_stickers'],
            'all_stickers' => $this->lang['command']['all_stickers'],
            'karma_plus'   => $karma_plus,
            'karma_minus'  => $this->lang['command']['karma_minus'][0],
            'lies'         => $lies,
            'not_liar'     => $this->lang['command']['not_liar'],
          ];
          return str_replace( $find, $replace, $tmpl );
        case preg_match( '/\b(' . $this->lang['command']['karma'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->userKarma( $user_id, $username ); // Karma
        case preg_match( '/\b(' . $this->lang['command']['ask_bio'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->userBio( $user_id, $username ); // Bio 
        case preg_match( '/\b(' . $this->lang['command']['add_set'] . ')\b/iu', $this->info['msg_text'] ):
          $file = $_SERVER['DOCUMENT_ROOT'] . '/style/sticker_logo.png';
          $slugify = $this->slugifySet( $this->lang['command']['add_set'] );
          if ( mb_strlen($slugify[0]) < 4 ) {
            return $this->lang['respond']['sticker']['change_setname'];
          } else
            return $this->newStickerSet( $file, $slugify ); // Add new stickerset
        case preg_match( '/\b(' . $this->lang['command']['add_sticker'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->lang['respond']['sticker']['noreply']; // Add new sticker  
        case preg_match( '/\b(' . $this->lang['command']['my_stickers'] . ')\b/iu', $this->info['msg_text'] ):
          $owner_id = $user_id;
        case preg_match( '/\b(' . $this->lang['command']['all_stickers'] . ')\b/iu', $this->info['msg_text'] ):
          $sticker_list = $this->db->getStickerSets( $owner_id );
          if ( $sticker_list != 'no stickers' ){
            $list = '';
            foreach ( $sticker_list as $set ) {
              $find = [ '{{ url }}', '{{ title }}', '{{ total }}', '{{ owner }}', '{{ owner_id }}' ];
              $replace = [
                'url' => 't.me/addstickers/' . $set['set_url'] . '_by_' . $this->bot['username'],
                'title' => $set['set_title'], 
                'total' => $set['stickers'],
                'owner' => $set['owner'],
                'owner_id' => $set['owner_id'],
              ];
              $list .= str_replace( $find, $replace, $this->lang['respond']['sticker']['row'] );
            }
            return $this->lang['respond']['sticker']['find_sets'] . $list; // List of stickersets
          } else
            return $this->lang['respond']['sticker']['not_found'];    
        case preg_match('/(\w+) (==|===|!==|!=) (\w+)/u', $this->info['msg_text'], $output_array);
          $random = mt_rand( 0, count($this->lang['dialog']['true_or_false']) -1 );
          return $this->lang['dialog']['true_or_false'][$random]; // True or false game
        case preg_match( '/\b(' . $this->lang['command']['tanos'] . ')\b/iu', $this->info['msg_text'] ):
          if ( !$this->is_admin )
            return $this->lang['respond']['not_admin'];

          $wait_for_collect = 60;
          $passed = $this->daysPassed( $this->db->botAddDate() );
          $days = $passed[0] . ' ' . $passed[1];
          
          if ( $passed[0] <= $wait_for_collect ) {
            $find = [ '{{ days }}', '{{ wait_for_collect }}' ];
            $replace = compact( 'days', 'wait_for_collect' );
            return str_replace( $find, $replace, $this->lang['respond']['tanos']['wait_for_it'] );
          } else {
            $list = $this->db->getUsers( true, strtotime('-2 week') ); //('-1 day') );//
            if( !$list == 'no users' )
              return $this->lang['respond']['tanos']['no_users'];

            $length = count( $list );
            $i = 0;
            $tmpl = $this->lang['respond']['tanos']['list'];
            foreach ( $list as $user_id => $username ) {
              $i++;
              $find = [ '{{ user_id }}', '{{ username }}' ];
              $replace = ['user_id' => $user_id, 'username' =>  $username ];
              $tmpl .= str_replace( $find, $replace, $this->lang['respond']['tanos']['row'] );
              $tmpl .= $i !== $length ? ', ' : $this->lang['respond']['tanos']['ask'];
            }
            return $tmpl; // Makes list of non-active users
          }
        // Bot dialogs
        case preg_match( '/\b(' . $this->lang['dialog']['ask_1'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->lang['dialog']['answer_1'];
        case preg_match( '/\b(' . $this->lang['dialog']['ask_2'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->lang['dialog']['answer_2'];
        case preg_match( '/\b(' . $this->lang['dialog']['ask_3'] . ')\b/iu', $this->info['msg_text'] ):
        case preg_match( '/\b(' . $this->lang['dialog']['ask_4'] . ')\b/iu', $this->info['msg_text'] ):
          return $this->lang['dialog']['answer_3_4'];
        case preg_match( '/\b(' . $this->lang['command']['ping'] . ')\b/iu', $this->info['msg_text'] ):
          $passed = $this->daysPassed( $this->db->botAddDate() );
          $days = $passed[0] . ' ' . $passed[1];
          $bot_name = $this->bot['first_name'];
          $help = $this->lang['command']['help'];
          $find = [ '{{ username }}', '{{ days }}', '{{ bot_name }}', '{{ help }}' ];
          $replace = compact( 'username', 'days', 'bot_name', 'help' );
          $find = [ '{{ user_id }}', '{{ username }}', '{{ days }}', '{{ bot_name }}', '{{ help }}' ];
          $replace = compact( 'user_id', 'username', 'days', 'bot_name', 'help' );
          return str_replace( $find, $replace, $this->lang['respond']['hello'] );
        default:
          exit('ok');
      }
    }


    /**
     *
     * 
     * 2. When user reply another user and calls bot by name.
     * @return string  Message
     *
     * 
     */
    private function replyUserAndCallBot(): string
    {
      $user_id = $this->info['reply_from_id'];
      $username = $this->info['reply_from_username'];
      $first_name = $this->info['reply_from_first_name'];

      switch ( true ) { 
        case preg_match( '/\b(' . $this->lang['command']['save_bio'] . ')\b/iu', $this->info['msg_text'] ):
          if ( $this->info['reply_from_is_bot'] )
            exit('ok');

          $bio_cmd = $this->lang['command']['save_bio'];
          $bio = substr( strstr($this->info['msg_text'], $bio_cmd), strlen($bio_cmd) );
          $bio = preg_replace( '/\s+/', ' ', trim($bio) );
          if ( mb_strlen($bio) < 4 )
            return $this->lang['respond']['bio']['too_short'];

          if ( $this->is_admin ) {
            $this->db_post->updateBio( $bio, $user_id );
            return $this->lang['respond']['bio']['approved']; // Add more info to bio
          } else {
            $find = [ '{{ user_id }}', '{{ bio }}' ];
            $replace = compact( 'user_id', 'bio' );
            return str_replace( $find, $replace, $this->lang['respond']['bio']['wait_approve'] ) ;
          }
        case preg_match( '/\b(' . $this->lang['command']['karma'] . ')\b/iu', $this->info['msg_text'] ):
          if ( $this->info['reply_from_is_bot'] )
            exit('ok');

          return $this->userKarma( $user_id, $username ); // Karma stats    
        case preg_match( '/\b(' . $this->lang['command']['ask_bio'] . ')\b/iu', $this->info['msg_text'] ):
          if ( $this->info['reply_from_is_bot'] )
            return $this->lang['respond']['its_bot']; // Bio from bot

          return $this->userBio( $user_id, $username ); // User's bio
        case preg_match( '/\b(' . $this->lang['command']['add_sticker'] . ')\b/iu', $this->info['msg_text'] ):
          if ( $this->info['reply_from_is_bot'] )
            exit('ok');

          return $this->makeQuote( '/data/quotes/' ); // Make new sticker
        default:
          exit('ok');
      }
    }


    /**
     *
     * 
     * 3. When user reply this bot.
     * @return string  Message
     *
     * 
     */
    private function replyBot(): string
    { 
      $asc_cmd = preg_replace('/\({.+/s', '', $this->lang['respond']['bio']['wait_approve'] );
      $asc_cmd = trim($asc_cmd);

      switch ( true ) {
        case preg_match( '/\b(' . $asc_cmd . ')\b/iu', $this->info['reply_text'] ):
          if ( !$this->is_admin )
            return $this->lang['respond']['not_admin'];

          if ( !in_array($this->info['msg_text'], $this->lang['command']['confirm']) ) {
            return $this->lang['respond']['bio']['not_approved']; // Bio not approved
          } else {
            preg_match( "/\((.*?)\)/",  $this->info['reply_text'], $user_id ); // Return id inside brackets
            $bio = preg_replace( "/^.+\:\n/", '', $this->info['reply_text'] ); // Removes all text before colon
            $this->db_post->updateBio( $bio, $user_id[1] );
            return $this->lang['respond']['bio']['approved']; // Bio approved
          }
        case preg_match( '/\b(' . $this->lang['respond']['tanos']['list'] . ')\b/iu', $this->info['reply_text'] ):
          if ( !$this->is_admin )
            return $this->lang['respond']['not_admin'];

          $status = $this->tg->getStatus( $this->info['chat_id'], $this->bot['id'] );
          if ( $this->info['reply_date'] + 120 < time() ){
            return $this->lang['respond']['tanos']['overtime'];
          } elseif ( !in_array($this->info['msg_text'], $this->lang['command']['confirm']) ) {
            return $this->lang['respond']['bio']['not_approved'];
          } elseif ( $status !== 'administrator' ) {
            return $this->lang['respond']['tanos']['bot_not_adm'];
          } else {
            preg_match_all( "/\((.*?)\)/",  $this->info['reply_text'], $user_array );
            $results = $this->tg->kickMembers( $user_array[1], $this->info['chat_id'] );
            $this->db_post->kickUsers( $user_array[1] );
            return $this->lang['respond']['tanos']['goodbye'];
          }
        default:
          exit('ok');
      }
    }


    /**
     *
     * 
     * 4. When user reply another user.
     * @return string  Message
     *
     * 
     */
    private function replyUser(): string
    {
      if ( $this->info['reply_from_is_bot'] )
        exit('ok');

      $karma_plus = $not_liar = false;
      $username = $this->info['reply_from_username'];
      $user_id = $this->info['reply_from_id'];
      $replied_user_db = $this->db->getUserData( $this->info['reply_from_id'] );
      $user_db = $this->db->getUserData( $this->info['user_id'] );
      $last_time = strtotime( $user_db['last_karma_send'] );

      switch ( true ) {
        case in_array( $this->info['msg_text'], $this->lang['command']['karma_plus'] ):
          $karma_plus = true; // Add karma
        case in_array( $this->info['msg_text'], $this->lang['command']['karma_minus'] ):
          if ( $this->info['reply_from_id'] == $this->info['user_id'] || $last_time + 20 >= time() ) 
            exit('ok'); // Change own karma is not allowed
          $karma = $karma_plus ? $replied_user_db['karma'] + 1 : $replied_user_db['karma'] - 1;
          $msg_tmpl = $karma_plus ? $this->lang['respond']['karma']['add'] : $this->lang['respond']['karma']['lose'];
          $find = [ '{{ user_id }}', '{{ username }}', '{{ karma }}' ];
          $replace = compact( 'user_id', 'username' , 'karma' );
          $this->db_post->karmaUpdate( $this->info['reply_from_id'], $this->info['user_id'], $karma_plus );
          return str_replace( $find, $replace, $msg_tmpl ); // Karma changed
        case preg_match( '/\b(' . $this->lang['command']['not_liar'] . ')\b/iu', $this->info['msg_text'] ):
          if ( !$this->is_admin )
            return $this->lang['respond']['not_admin'];

          $not_liar = true; 
        case in_array( $this->info['msg_text'], $this->lang['command']['lies'] ):
          if ( $not_liar ) { // Admin says - this user is not a liar
            $lies = $replied_user_db['lie_times'] == 0 ? 0 : $replied_user_db['lie_times'] - 1;
            $passed = $this->daysPassed( $replied_user_db['before_lie']  );
            $honest_days = $passed[0] . ' ' . $passed[1];
            $tmpl = $this->lang['respond']['liar']['lose'];
          } else {
            if ( $this->info['reply_from_id'] == $this->info['user_id'] || $last_time + 20 >= time() )
              exit('ok'); // Can't change own

            $lies = $replied_user_db['lie_times'] + 1;
            $passed = $this->daysPassed( $replied_user_db['last_lie'] );
            $honest_days = $passed[0] . ' ' . $passed[1];
            $tmpl = $this->lang['respond']['liar']['add'];
          }
          $tmpl .= $this->liesCounter( $lies ); // Add to template
          $this->db_post->lieUpdate( $this->info['reply_from_id'], $not_liar ); 
          $find = [ '{{ user_id }}', '{{ username }}', '{{ honest_days }}', '{{ lies }}' ];
          $replace = compact( 'user_id', 'username', 'honest_days', 'lies' );
          return str_replace( $find, $replace, $tmpl );
        default:
          exit('ok');
      }
    }


    /**
     * [userKarma description]
     * @param  int    $user_id         [description]
     * @param  string $username        [description]
     * @param  string $user_first_name [description]
     * @return [type]                  [description]
     */
    private function userKarma( int $user_id, string $username  ): string
    {
      $user_db = $this->db->getUserData( $user_id );
      $karma = $user_db['karma'];
      $find = [ '{{ user_id }}', '{{ username }}', '{{ karma }}' ];
      $replace = compact( 'user_id', 'username', 'karma' );
      return str_replace( $find, $replace, $this->lang['respond']['karma']['stats'] );
    }


    /**
     * [userBio description]
     * @param  int    $user_id         [description]
     * @param  string $username        [description]
     * @param  string $user_first_name [description]
     * @return [type]                  [description]
     */
    private function userBio( int $user_id, string $username ): string
    {
      $user_db = $this->db->getUserData( $user_id );
      $bio = $user_db['user_bio'];
      $passed = $this->daysPassed( $user_db['first_seen'] ); // Reg date with plural local format 
      $reg_days = $passed[0] . ' ' . $passed[1];
      $msgs = $user_db['msgs'];
      $num = strlen( $msgs ) == 1 ? $msgs : substr( $msgs, -1 );
      switch ( $num ) { // Plural local format of message 
        case 1:
          $local = $this->lang['local']['message'];
          break;
        case 2: case 3: case 4:
          $local = $this->lang['local']['messages'];
          break;
        default:
          $local = $this->lang['local']['messages_many'];
          break;
      }
      $karma = $user_db['karma'];
      $lies = $user_db['lie_times'];
      // Build template
      $msg_tmpl = isset( $bio ) ? $this->lang['respond']['bio']['find'] : $this->lang['respond']['bio']['empty'];
      $msg_tmpl .= $this->lang['respond']['bio']['second_line'];
      $msg_tmpl .= PHP_EOL . PHP_EOL . $this->liesCounter( $lies );
      $passed = $this->daysPassed( $user_db['last_lie'] ); // Days without lies, with plural local format 
      $honest_days = $passed[0] . ' ' . $passed[1];
      $find = [ '{{ user_id }}', '{{ username }}', '{{ bio }}', '{{ reg_days }}', '{{ msgs }}', 
                '{{ local }}', '{{ karma }}', '{{ lies }}', '{{ honest_days }}' ];
      $replace = compact( 'user_id', 'username', 'bio', 'reg_days', 'msgs', 'local', 'karma', 'lies', 'honest_days' );
      return str_replace( $find, $replace, $msg_tmpl );
    }


    /**
     * [makeQuote description]
     * @param  string $tmp_path [description]
     * @return [type]           [description]
     */
    private function makeQuote( string $tmp_path ): string
    {
      $folder = $_SERVER['DOCUMENT_ROOT'] . $tmp_path;
      $this->mkdirIfNotExist($folder); // Make temp folder if not exist
      $slugify = $this->slugifySet( $this->lang['command']['add_sticker'] ); // Translate stickerset name
      if ( mb_strlen($slugify[0]) < 4 )
        return $this->lang['respond']['bio']['too_short'];

      list( $title, $set_url ) = $slugify;
      // Create new .png file
      $date = new \DateTime();
      $file = $folder . $date->getTimestamp() . '.png';
      // Save temp file
      $img = new Image_Gen();
      $avatar = $_SERVER['DOCUMENT_ROOT'] . '/data/avatars/' . $this->info['reply_from_id'] . '.jpg';
      $nick_color = $this->db->getHex( $this->info['reply_from_id'] );
      $png = $img->quoteToSticker( $this->info['reply_text'], $this->info['reply_from_first_name'], $nick_color, $avatar );
      file_put_contents( $file, $png );
      $set_exist = $this->tg->getStickerSet( $set_url . '_by_' . $this->bot['username'] ); // Checks is set already exist
     
     if ( $set_exist[0] == 'not exist' ) {
        $msg = $this->newStickerSet( $file, $slugify ); // Make new one
      } elseif ( $set_exist[0] == 'ok' ) {
        $msg = $this->uploadNewSticker( $file, $set_url, $title );
      } else
        $msg = $this->lang['respond']['error'];

      unlink($file);
      return $msg;
    }
    

    /**
     * [newStickerSet description]
     * @param  string $file    [description]
     * @param  array  $slugify [description]
     * @return [type]          [description]
     */
    private function newStickerSet( string $file, array $slugify ): string 
    {
      list( $title, $set_url ) = $slugify;
      $set_name = $set_url . '_by_' . $this->bot['username'];

      $tg_data = [ // Array for api
        'user_id' => $this->bot_master,
        'name' => $set_name,
        'title' => $title,
        'png_sticker' => new \CURLFile( $file ),
        'emojis' => '📦',
      ];

      $post = [ // Array for DB
        'set_url' => $set_url,
        'set_title' => $title,
        'owner' => $this->info['user_first_name'],
        'owner_id' => $this->info['user_id'],
      ];

      list( $code, $desc ) = $this->tg->createNewStickerSet( $tg_data ); // Status of operation
      switch ( $code ) {
        case 'error':
          return $this->lang['respond']['error'];
        case 'ok':
          $this->db_post->addStickerSet( $post, ['set_url', 'set_title', 'owner', 'owner_id'] );
          return str_replace( ['{{ set_name }}'], compact('set_name'), $this->lang['respond']['sticker']['new_set'] );
        case 400:
          if ( $desc == 'Bad Request: sticker set name is already occupied' )
            return str_replace( ['{{ set_name }}'], compact('set_name'), $this->lang['respond']['sticker']['exist_set'] );
          elseif ( $desc == 'Bad Request: sticker set name invalid' )
            return $this->lang['respond']['sticker']['change_setname'];
          else
            return $desc;
        case 500:
          return $this->lang['respond']['sticker']['error'];
        default:
          return 'code: ' . $code . ': ' . $desc;
      }
    }


    /**
     * [uploadNewSticker description]
     * @param  [type] $file    [description]
     * @param  [type] $set_url [description]
     * @return [type]          [description]
     */
    private function uploadNewSticker( string $file, string $set_url, string $title ): string
    {
      $set_name = $set_url . '_by_' . $this->bot['username'];
      $tg_data = [ // Array for api
        'user_id' => $this->bot_master,
        'name' => $set_name,
        'png_sticker' => new \CURLFile( $file ),
        'emojis' => '📜',
      ];

      list( $code, $decription ) = $this->tg->addStickerToSet( $tg_data );
      switch ( $code ) {
        case 'error':
          return $this->lang['respond']['error'];
        case 'ok':
          $set_exist = $this->tg->getStickerSet( $set_name ); // Checks is set already exist
          $this->db_post->addNewSticker( $set_name, $set_exist[1]+1 );
          return str_replace( ['{{ set_name }}', '{{ title }}'], compact('set_name', 'title'), $this->lang['respond']['sticker']['added'] );
        case 400:
          if ( $decription = 'Bad Request: STICKER_PNG_DIMENSIONS' )
            return $this->lang['respond']['sticker']['too_big'];
          else 
            return 'code: ' . $code . ': ' . $decription;
        case 500:
          return $this->lang['respond']['sticker']['error'];
        default:
          return 'code: ' . $code . ': ' . $decription;
      }
    }


    /**
     * Download or make user's avatar image.
     * @param  string $path  File save directory
     */
    private function avatarCheck( string $path, int $user_id, string $username, string $hex ): void
    {
      $folder = $_SERVER['DOCUMENT_ROOT'] . $path;
      $file = $folder . $user_id . '.jpg';
      if ( file_exists($file) )
        return; // File is found, nothing to do
         
      $avatar = $this->tg->getAvatar( $user_id );
      if ( !$avatar ) { // No avatar, make new one
        $img = new Image_Gen();
        $avatar = $img->makeAvatar( $username, $hex );
      }

      $this->mkdirIfNotExist($folder);
      file_put_contents( $file, $avatar );
      chmod( $file, 0664 );
    }


    /**
     * [liesCounter description]
     * @param  int    $lies [description]
     * @return [type]       [description]
     */
    private function liesCounter( int $lies ): string
    {
      if ( $lies == 0 )
        $tmpl = $this->lang['respond']['liar']['not_liar'];
      else {
        $tmpl = $this->lang['respond']['liar']['honest_days'];
        $num = strlen($lies) == 1 ? $lies : substr($lies, -1); 
        switch ($num) {
          case 1:
            $tmpl .= ' <b>' . $this->lang['local']['once'] . '</b>.';
            break;
          case 2: case 3: case 4:
            $tmpl .= ' <b>' . $this->lang['local']['times'] . '</b>.';
            break;
          default:
            $tmpl .= ' <b>' . $this->lang['local']['times_many'] . '</b>.';
            break;
        }
      }
      return $tmpl;
    }


    /**
     * [daysPassed description]
     * @param  int    $from [description]
     * @param  int    $to   [description]
     * @return [type]       [description]
     */
    private function daysPassed( string $from ): array
    {
      $from = strtotime( $from );
      $datediff = time() - $from;
      $days = round ($datediff / (60 * 60 * 24) );
      $num = strlen($days) == 1 ? $days : substr($days, -1);
      switch ($num) {
        case 0:
          $days = 1;
        case 1:
          $local = $this->lang['local']['day'];
          break;
        case 2: case 3: case 4:
          $local = $this->lang['local']['days'];
          break;
        default:
          $local = $this->lang['local']['days_many'];
          break;
      }
      return [ $days, $local ];
    }


    /**
     * [mkdirIfNotExist description]
     * @param  string $path [description]
     * @return [type]       [description]
     */
    private function mkdirIfNotExist( string $path ): bool
    {
      return !file_exists( $path ) ? mkdir( $path, 0755, true ) : true;
    }


    /**
     * Remove string from message and return array with filtered and translated strings.
     * @param  string  $cmd  This text will remove from message
     * @return array         Filtered and translated message
     */
    private function slugifySet( string $cmd ): array
      {
      $title = substr( strstr($this->info['msg_text'], $cmd), strlen($cmd) );
      $title = preg_replace( '/\s+/', ' ', trim($title) );
      if ( mb_strlen($title) === 0 ) {
        $words = explode( ' ', $this->info['chat_title'] );
        $title = $words[0];
        if ( isset($words[1]) )
          $title .= ' ' . $words[1];
      }

      $rules = 'Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();';
      $translate = transliterator_transliterate( $rules, $title );
      $translate = preg_replace( '/[-\s]+/', '_', $translate );
      return [ $title, $translate ];
    } 

  }
