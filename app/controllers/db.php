<?php
  namespace App\Controllers;
  use \PDO as PDO;
  use \App\PDO_start as PDO_start;

  /**
   * Get data from DB.
   */
  class DB {
    protected $table;
    protected $sticker_sets;

    function __construct( string $table ) {
      $this->table = $table;
      $this->sticker_sets = $table . '_stickers';
      $this->tableExists();
    }


    /**
     * Checks if table exist and create if does not.
     */
    protected function tableExists(): void
    {
      $result = $this->request( "SELECT 1 FROM $this->table LIMIT 1" );

      if (!$result){ 
        $query = "CREATE TABLE $this->table (
          user_id          BIGINT NOT NULL,
          user_first_name  VARCHAR(65) NOT NULL,
          user_bio         VARCHAR(512),
          hex_color        VARCHAR(7) NOT NULL,
          status           VARCHAR(15) NOT NULL,
          last_seen        TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
          first_seen       TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
          msgs             BIGINT unsigned DEFAULT 1 NOT NULL,
          karma            INT DEFAULT 0,
          last_karma_send  TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
          lie_times        INT unsigned DEFAULT 0,
          last_lie         TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
          before_lie       TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
          PRIMARY KEY (user_id)
        ); ";

        $query .= "CREATE TABLE $this->sticker_sets (
          id                 INT NOT NULL AUTO_INCREMENT,
          set_url            VARCHAR(60) NOT NULL,
          set_title          VARCHAR(60) NOT NULL,
          owner              VARCHAR(60) NOT NULL,
          owner_id           BIGINT NOT NULL,
          stickers           INT(3) unsigned DEFAULT 1 NOT NULL,
          PRIMARY KEY (id)
        )";

        $this->request( $query );
      } 
    }


    public function botAddDate()
    {
      $query = "SELECT first_seen FROM $this->table ORDER BY first_seen ASC LIMIT 1";
      $stmt = $this->request( $query );
      return $stmt->fetchColumn();
    }


    /**
     * Returns one dimension array with user id as key and nickname as value.
     * Or, string 'no users' if didn't find anything.
     * @return array|string
     */
    public function getUsers( $for_kick = false, $due = '' )
    {
      if( !$for_kick )
        $query = "SELECT user_id, user_first_name FROM $this->table ORDER BY user_id ASC";
      else
        $query = "SELECT user_id, user_first_name FROM $this->table WHERE status = 'member' AND UNIX_TIMESTAMP(last_seen) < $due ORDER BY user_first_name ASC";

      $stmt = $this->request( $query );
      $result = $stmt->fetchAll( PDO::FETCH_KEY_PAIR );
      return !$result ? 'no users' : $result;
    }


    /**
     * Returns one dimension array with user id as key and nickname as value.
     * Or, string 'no users' if didn't find anything.
     * @return array|string
     */
    public function getStickerSets( $owner_id )
    {
      if ( $owner_id ){
        $query = "SELECT * FROM $this->sticker_sets WHERE owner_id = ? ORDER BY set_title ASC";
      } else {
        $query = "SELECT * FROM $this->sticker_sets ORDER BY set_title ASC";
      }
      
      $stmt = $this->request( $query, [ $owner_id ] );
      $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
      return !$result ? 'no stickers' : $result;
    }


    /**
     * Returns all user data
     * @param  int    $user_id
     * @return array
     */
    public function getUserData( int $user_id ): array
    {
      $query = "SELECT * FROM $this->table WHERE user_id = ? LIMIT 1";
      $stmt = $this->request( $query, [ $user_id ] );

      return $stmt->fetch( PDO::FETCH_ASSOC );
    }


    /**
     * Hex color assigned to the user.
     * @param  int    $user_id 
     * @return array|null
     */
    public function getHex( int $user_id ): string
    {
      $query = "SELECT hex_color FROM $this->table WHERE user_id = ? LIMIT 1";
      $stmt = $this->request( $query, [ $user_id ] );

      return $stmt->fetchColumn();
    }


    /**
     * Send query to database and get or set data.
     * @param  string  $query   MySQL query
     * @param  array   $values  Values for placeholders
     * @return object           PDO Statement 
     */
    protected function request( string $query, array $values = [] ) {
      $stmt = PDO_start::getConnect()->dbh->prepare( $query );

      try {
          $result = $values ? $stmt->execute( $values ) : $stmt->execute();
      } catch (Exception $e) {
          $result = FALSE;
      }

      return !$result ? FALSE : $stmt;
    }
    
  }
  