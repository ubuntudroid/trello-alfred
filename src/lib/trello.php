<?php

use Trello\Client;
#use GuzzleHttp\Client;

Class Trello {

	// protected static $app_key           = 'e84053a367c64476aec7547f533736ca';
	// protected static $api_endpoint_base = 'https://api.trello.com/1';
	
	protected static $user_token_del = null;
	protected static $list_id        = null;
	protected static $url            = null;
	
	public $app_key                  = 'e84053a367c64476aec7547f533736ca';

	protected $api_endpoint_base     = 'https://api.trello.com/1';
	
	protected $app_api_key           = 'e84053a367c64476aec7547f533736ca';
	protected $auth_return_url       = 'https://trello.com/1/token/approve/';
	
	protected $get_boards_endpoint   = '/boards/4eea4ffc91e31d1746000046?lists=open&list_fields=name&fields=name,desc&key=::app_key&token=::user_token';

	protected $user_token 				= null;

	public function __construct( $user_token=null )
	{
		$this->user_token = $user_token;
	}

	public function buildURL( $board_id, $user_token )
	{
		return $this->api_endpoint_base . '/boards/' . $board_id . '?lists=open&list_fields=name&fields=name,desc&key=' . $this->app_key . '&token=' . $user_token;
	}

	public function addCard( $title, $description, $labels, $board_id, $user_token )
	{
		$card_data = array(

			'name'      => $title,
			'desc'      => $description,
			'labels'    => $labels,
			'due'       => null,
			'list_name' => 'Incoming',
			'pos'       => 'bottom',

		);

		// echo '###<pre>'; print_r($card_data); echo '</pre>###'; die;

		$endpoint_url = $this->buildURL( $board_id, $user_token );
		
		$result = $this->sendRequest( $endpoint_url, $card_data, $user_token );

		return $result;
	}

	public function sendRequest( $endpoint_url, $data, $user_token )
	{
		$ch = curl_init();

		// Set query data here with the URL
		curl_setopt( $ch, CURLOPT_URL, $endpoint_url ); 
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 ); 
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, '25' );
		
		$content = trim(curl_exec( $ch ));
		
		curl_close( $ch );
		
		$board          = json_decode( $content );
		$lists          = $board->lists;
		$trello_list_id = $lists[0]->id;

		foreach ($lists as $list) 
		{
			if ($list->name == $data['list_name']) 
			{
				$trello_list_id = $list->id;
			}
		}

		if ( $trello_list_id ) 
		{
			
			$ch = curl_init( $this->api_endpoint_base . "/cards" );

			// 
			// Add validation key and token to the post
			// 
			$data['key']    = $this->app_key;
			$data['token']  = $user_token;
			$data['idList'] = $trello_list_id;
			
			curl_setopt_array( $ch, array(

			    CURLOPT_SSL_VERIFYPEER => false,
			    CURLOPT_RETURNTRANSFER => true, 	// So we can get the URL of the newly-created card
			    CURLOPT_POST           => true,
			    
			    // 
			    //	If you use an array without being wrapped in http_build_query, the Trello API server won't recognize your POST variables
			    // 
			    
			    CURLOPT_POSTFIELDS => http_build_query( $data ),
			
			));

			$result      = curl_exec( $ch );

			$TrelloCard = json_decode( $result );
			
			if (empty( $TrelloCard ))
			{
				return "Error adding card: " . $result;
			}

			return ( ($TrelloCard) ? $TrelloCard : false );			
		} 
		else 
		{
			return 'List not found';
		}
	}

	public function getBoards( $trello_user_id, $token=null )
	{
		$_endpoint_url = 'member/' . $trello_user_id . '/boards/';
		
		if (empty( $token ))
		{
			$token = $this->user_token;
		}

		$TrelloClient = new Client( $this->app_api_key );

		$Boards = $TrelloClient->get( $_endpoint_url, array( 'token' => $token ) );

		return $Boards;
	}

	public function getLists( $trello_board_id, $token=null )
	{
		$_endpoint_url = 'boards/' . $trello_board_id . '/lists';
		
		if (empty( $token ))
		{
			$token = $this->user_token;
		}

		$TrelloClient = new Client( $this->app_api_key );

		$Lists = $TrelloClient->get( $_endpoint_url, array( 'token' => $token ) );

		return $Lists;
	}

	public function getLabels( $trello_board_id, $token=null )
	{
		$_endpoint_url = 'boards/' . $trello_board_id . '/labels';

		if (empty( $token ))
		{
			$token = $this->user_token;
		}

		$TrelloClient = new Client( $this->app_api_key );

		$Labels = $TrelloClient->get( $_endpoint_url, array( 'token' => $token ) );

		return $Labels;
	}

	public function getMember( $trello_username, $fields=array('username', 'fullName', 'displayName', 'initials') )
	{
		try
		{
			if (!empty( $fields ) && is_array( $fields ))
			{
				foreach ($fields as $value)
				{
					if (empty( $_fields ))
					{
						$_fields = $value;
						continue;
					}

					$_fields .= ',' . $value;
				}

				$fields = $_fields;
			}
			
			$_endpoint_url = 'member/' . $trello_username;
			
			$_payload      = array(
				'fields'	=> $fields,
				'boards'	=> 'all'
			);
			
			$TrelloClient = new Client( $this->app_api_key );

			$Member = $TrelloClient->get( $_endpoint_url, $_payload );
		}
		catch (\Exception $e)
		{
			return false;
		}

		return $Member;
	}

	public function getAuthUrl( $app_name )
	{
		$endpoint_url = 'member/dberube';
		
		$TrelloClient = new Client( $this->app_api_key );
		
		return $TrelloClient->getAuthorizationUrl( $app_name, $this->auth_return_url, array('read', 'write', 'account'), 'never', 'fragment' );
	}
}