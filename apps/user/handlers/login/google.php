<?php

/**
 * Google social login handler.
 */

if (! in_array ('google', $appconf['User']['login_methods'])) {
	echo $this->error (404, __ ('Not found'), __ ('The page you requested could not be found.'));
	return;
}

$openid = new LightOpenID ($_SERVER['HTTP_HOST']);

// handle the openid request
if (! $openid->mode) {
	$openid->identity = 'https://www.google.com/accounts/o8/id';
	$openid->required = array ('namePerson/first', 'namePerson/last', 'contact/email');
	$this->redirect ($openid->authUrl ());
} elseif ($openid->mode == 'cancel') {
	$this->redirect ($_GET['redirect']);
} elseif (! $openid->validate ()) {
	$this->redirect ($_GET['redirect']);
}

// get the openid token and data
$token = 'g:' . str_replace ('https://www.google.com/accounts/o8/id?id=', '', $openid->identity);
$data = $openid->getAttributes ();

if (isset ($data['contact/email'])) {
	// fetch by email
	$u = User::query ()->where ('email', $data['contact/email'])->single ();
} else {
	// no email, fetch by token
	$uid = User_OpenID::get_user_id ($token);
	if ($uid) {
		$u = new User ($uid);
	}
}

@session_start ();
$_SESSION['session_openid'] = $token;

if ($u) {
	// already have an account, log them in
	$u->session_id = md5 (uniqid (mt_rand (), 1));
	$u->expires = gmdate ('Y-m-d H:i:s', time () + 2592000);
	$try = 0;
	while (! $u->put ()) {
		$u->session_id = md5 (uniqid (mt_rand (), 1));
		$try++;
		if ($try == 5) {
			$this->redirect ($_GET['redirect']);
		}
	}
	$_SESSION['session_id'] = $u->session_id;

	// save openid token
	$oid = new User_OpenID (array (
		'token' => $token,
		'user_id' => $u->id
	));
	$oid->put ();

	$this->redirect ($_GET['redirect']);
} elseif (isset ($data['contact/email'])) {
	// signup form to create a linked account, prefill name and email
	$_POST['name'] = $data['namePerson/first'] . ' ' . $data['namePerson/last'];
	$_POST['email'] = $data['contact/email'];
	$_POST['redirect'] = $_GET['redirect'];
	$_POST['token'] = $token;
	echo $this->run ('user/login/newuser');
} else {
	// signup form to create a linked account, prefill name
	$_POST['name'] = $data['namePerson/first'] . ' ' . $data['namePerson/last'];
	$_POST['redirect'] = $_GET['redirect'];
	$_POST['token'] = $token;
	echo $this->run ('user/login/newuser');
}

?>