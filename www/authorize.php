<?php

/* Start session and load library. */
session_start();
require_once('../../translatetweet/twitteroauth/twitteroauth.php');
require_once('../../translatetweet/config.php');

/* Build TwitterOAuth object with client credentials. */
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
 
/* Get temporary credentials. */
$request_token = $connection->getRequestToken(OAUTH_CALLBACK);

/* Save temporary credentials to session. */
$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
 
/* If last connection failed don't display authorization link. */
switch ($connection->http_code) {
  case 200:
    /* Build authorize URL and redirect user to Twitter. */
    $url = $connection->getAuthorizeURL($token);
    header('Location: ' . $url); 
    break;
  default:
    /* Show notification if something went wrong. */
    $err = date('Y-m-d H:i:s')." redirect: ".$connection->http_code." token: ".$_SESSION['oauth_token']." secret: ".$_SESSION['oauth_token_secret']." IP: ".$_SERVER['REMOTE_ADDR']."\n";
    file_put_contents("../../translatetweet/logs/errors.log",$err, FILE_APPEND);
    header('Location: ./error.html');
}
