<?php
namespace App\Views;
use \App\Controllers\Telegram as Tg;

/**
 * Send something to Telegram.
 */
class Telegram_Post extends Tg {
	private $chat_id, $message_id, $msg;

	function __construct( array $tg_config ) {
    parent::__construct( $tg_config );
	}


  /**
   * Send new message by method.
   * @param array  $action  Method and message
   * @param array  $id      Chat id and message id
   */
  public function sendMsg( array $action, array $id ): void 
  {
    list( $action_type, $this->msg ) = $action;
    list( $this->chat_id, $this->message_id ) = $id;

    if ( $action_type == 'sendMsg' )
      $this->asMsg();
    elseif ( $action_type == 'sendReply' )
      $this->asReply();
    else
      exit('ok');
  }


  /**
   * Send as new message.
   */
	private function asMsg(): void
  {
		$data = [
			'text' => $this->msg,
			'chat_id' => $this->chat_id,
      'parse_mode' => 'html',
		];

		parent::request( 'sendMessage', $data );
	}


  /**
   * Send as reply to message.
   */
	protected function asReply(): void
  {
		$data = [
			'text' => $this->msg,
			'chat_id' => $this->chat_id,
      'reply_to_message_id' => $this->message_id,
      'parse_mode' => 'html',
		];

		parent::request( 'sendMessage', $data );
	}

}
