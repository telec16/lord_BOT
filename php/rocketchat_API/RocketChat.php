<?php

namespace RocketChat;

include_once('httpful.phar');

class Client{

	private $api;
	private $username;
	private $password;

	function __construct($username, $password){
		$this->api = ROCKET_CHAT_INSTANCE . REST_API_ROOT;
		$this->username = $username;
		$this->password = $password;
		
		// set template request to send and expect JSON
		$tmp = \Httpful\Request::init()
			->sendsJson()
			->expectsJson();
		\Httpful\Request::ini( $tmp );
	}
	
	private function JSONtoArray($j){
		return json_decode(json_encode($j),true);
	}

	/**
	* Get version information. This simple method requires no authentication.
	*/
	public function version() {
		$response = \Httpful\Request::get( $this->api . 'info' )->send();
		return $response->body->info->version;
	}

	/**
	* Quick information about the authenticated user.
	*/
	public function me() {
		$response = \Httpful\Request::get( $this->api . 'me' )->send();

		if( $response->body->status != 'error' ) {
			if( isset($response->body->success) && $response->body->success == true ) {
				return $this->JSONtoArray($response->body);
			}
		} else {
			echo( $response->body->message . "\n" );
			return false;
		}
	}
	
	/**
	* Authenticate with the REST API.
	*/
	public function login($save_auth = true) {
		$response = \Httpful\Request::post( $this->api . 'login' )
			->body(array( 'user' => $this->username, 'password' => $this->password ))
			->send();

		if( $response->code == 200 && isset($response->body->status) && $response->body->status == 'success' ) {
			if( $save_auth) {
				// save auth token for future \Httpful\Requests
				$tmp = \Httpful\Request::init()
					->addHeader('X-Auth-Token', $response->body->data->authToken)
					->addHeader('X-User-Id', $response->body->data->userId);
				\Httpful\Request::ini( $tmp );
			}
			return true;
		} else {
			echo( $response->body->message . "\n" );
			return false;
		}
	}

	
	/**
	* List the private groups the caller is part of.
	*/
	public function groups_list() {
		$response = \Httpful\Request::get( $this->api . 'groups.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body->groups);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}

	
	
	/**
	* List all of the users and their information.
	*
	* Gets all of the users in the system and their information, the result is
	* only limited to what the callee has access to view.
	*/
	public function users_list(){
		$response = \Httpful\Request::get( $this->api . 'users.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body->users);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}

	/**
	* Gets a user’s information, limited to the caller’s permissions.
	*/
	public function user_info() {
		$response = \Httpful\Request::get( $this->api . 'users.info?userId=' . $this->id )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}
	
	/**
	* List the channels the caller has access to.
	*/
	public function channels_list() {
		$response = \Httpful\Request::get( $this->api . 'channels.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body->channels);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}
	
	
	/**
	* Retrieves the information about the channel.
	*/
	public function channels_info() {
		$response = \Httpful\Request::get( $this->api . 'channels.info?roomId=' . $this->id )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}
	
	/**
	* Retrieves channel's history.
	*/
	public function channels_history($id, $count=100, $oldest="n/a") {
		$response = \Httpful\Request::get( $this->api . "channels.history?roomId={$id}&count={$count}&oldest={$oldest}")->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $this->JSONtoArray($response->body);
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}

	/**
	* Post a message in this channel, as the logged-in user
	*/
	public function chat_post_message($roomId, $text, $alias="", $avatar="") {
		
		$response = \Httpful\Request::post( $this->api . 'chat.postMessage' )
			->body( array(	'roomId' => $roomId,
							'text' => $text,
							'alias' => $alias,
							'avatar' => $avatar) )
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			if( isset($response->body->error) )	echo( $response->body->error . "\n" );
			else if( isset($response->body->message) )	echo( $response->body->message . "\n" );
			return false;
		}
	}

}
