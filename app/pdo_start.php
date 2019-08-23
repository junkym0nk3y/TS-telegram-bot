<?php
  namespace App;
  use \PDO as PDO;
  use \PDOException as PDOException;

  /**
   * DB Connector.
   */
  class PDO_start 
  {
    public $dbh;
    public $isConnected;
    public static $instance = null;
    private $settings;

    private function __construct( array $settings ) {
      $this->isConnected = true;
      $this->settings = $settings;

      $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, //PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
      ];

      try { 
        $this->dbh = new PDO('mysql:host=localhost;charset=utf8;dbname='.$this->settings['dbname'], $this->settings['username'], $this->settings['password'], $opt);
      }catch(PDOException $e) { 
        $this->isConnected = false;
        throw new Exception( $e->getMessage() );
      }
    }


    /**
     * Connect to database.
     * @param  array  $settings Database credentials
     * @return [type]           Instance or make new one
     */
    public static function getConnect( array $settings = [] ) {
      if ( self::$instance == null )
        self::$instance = new static( $settings );

      return self::$instance;
    }
    
  }
  