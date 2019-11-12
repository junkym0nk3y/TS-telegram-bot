<?php
  namespace App\Controllers;
  use \App\Controllers\DB as DB;

  /**
   * Send data to DB.
   */
  class DB_post extends DB { 
    private $set, $values;

    function __construct( string $table ) {
      parent::__construct( $table );
    }


    /**
     * Helper for PDO, filter post and converts it to SET.
     * @param  array  $post     Post data
     * @param  array  $allowed  Whitelist
     * @return array            Multidimension array 
     */
    private function postToQuery( array $post, array $allowed ): array
    {
      $set = '';
      $values = [];

      foreach ( $allowed as $field ) {
        if ( isset($post[$field]) ) {
          $set .= '`'. str_replace('`', '``', $field) . "`=:$field, ";
          $values[$field] = $post[$field];
        }
      }

      return [ substr($set, 0, -2), $values ];
    }


    /**
     * Add new user.
     * @param  array  $post     Post data
     * @param  array  $allowed  Whitelist
     * @return array            DB operation result
     */
    public function userAdd( array $post, array $allowed ) {
      list( $set, $values ) = $this->postToQuery( $post, $allowed );
      $query = "INSERT INTO $this->table SET $set";
      $result = parent::request( $query, $values );
      return $result;
    }


    /**
     * Removes by id, if user leave chat.
     * @param  int    $user_id  User id
     * @return array            DB operation result
     */
    public function userLeave( int $user_id ) {
      $query = "DELETE FROM $this->table WHERE user_id = ?";
      $result = parent::request( $query, [ $user_id ] );
      return $result;
    }


    public function kickUsers( array $user_array ) {
      $query = '';
      $values = [];
      $i = 0;

      $length = count( $user_array );
      if ( $length == 0 )
        return 'no_users';
      
      foreach ( $user_array as $user_id ) {
        $i++;
        $query .= $i !== $length ? "DELETE FROM $this->table WHERE user_id = ?; " : "DELETE FROM $this->table WHERE user_id = ?";
        $values[] = $user_id;
      }

      $result = parent::request( $query, $values );
      return $result;
    }


    /**
     * Updates info, when user send message.
     * @param  array  $post     Post data
     * @param  array  $allowed  Filter list
     * @return array            DB operation result
     */
    public function userUpdate( array $post, array $allowed ) {
      list( $set, $values ) = $this->postToQuery( $post, $allowed );
      $query = "UPDATE $this->table SET $set, msgs = msgs + 1 WHERE user_id = :user_id";
      $result = parent::request( $query, $values );
      return $result;
    }


    /**
     * Updates karma.
     * @param  int    $user_id  User id
     * @param  bool   action    Add or remove karma
     * @return array            DB operation result
     */
    public function karmaUpdate( int $user_id, int $from_id, bool $action = true ) {
      if ( $action ) 
        $query = "UPDATE $this->table SET karma = karma + 1 WHERE user_id = ?; ";
      else 
        $query = "UPDATE $this->table SET karma = karma - 1 WHERE user_id = ?; ";

      $query.= "UPDATE $this->table SET last_karma_send = ? WHERE user_id = ?";
      $result = parent::request( $query, [ $user_id, date('Y-m-d H:i:s'), $from_id ] );
      return $result;
    }


    /**
     * Updates lie.
     * @param  int    $user_id  User id
     * @return array            DB operation result
     */
    public function lieUpdate( int $user_id, bool $action = true ) {
      if ( $action ){
        $query = "UPDATE $this->table SET lie_times = lie_times - 1, last_lie = before_lie WHERE user_id = ?";
        $result = parent::request( $query, [ $user_id ] );
      } else {
        $query = "UPDATE $this->table SET lie_times = lie_times + 1, before_lie = last_lie, last_lie = ? WHERE user_id = ?";
        $result = parent::request( $query, [ date('Y-m-d H:i:s'), $user_id ] );
      }

      return $result;
    }


    /**
     * [addStickerSet description]
     * @param array $post    [description]
     * @param array $allowed [description]
     */
    public function addStickerSet( array $post, array $allowed ) {
      list( $set, $values ) = $this->postToQuery( $post, $allowed );
      $query = "INSERT IGNORE INTO $this->sticker_sets SET $set";
      $result = parent::request( $query, $values );
      return $result;
    }


    /**
     * [addStickerSet description]
     * @param array $post    [description]
     * @param array $allowed [description]
     */
    public function addNewSticker( string $set_url, int $stickers ) {
      $query = "UPDATE $this->sticker_sets SET stickers = ? WHERE set_url = ?";
      $result = parent::request( $query, [ $stickers, $set_url ] );
      return $result;
    }


    /**
     * Updates biography of user.
     * @param  string  $user_bio  Bio text
     * @param  int     $user_id   User id
     * @return array              DB operation result
     */
    public function updateBio( string $user_bio, int $user_id ) {
      $query = "UPDATE $this->table SET user_bio = ? WHERE user_id = ?";
      $result = parent::request( $query, [$user_bio, $user_id] );
      return $result;
    }
    
  }
  