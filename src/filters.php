<?php

Route::filter('apikey', function()
{
	$username = Input::get('user');

	if($user = User::where('username','=',$username)->first())
	{
		if($user->apikey == Input::get('apikey'))
			Auth::onceUsingId($user->id);
	}
});

Route::filter('protect', function($route, $request, $value = null)
{
	$forbidden = array('create','store','edit','update','destroy');

	$action = $route->getAction();
	if(preg_match('/(.+)@(.+)$/', $action, $matches))
	{
		$method = $matches[2];
		if(in_array($method, $forbidden)) return Redirect::home();
	}
});