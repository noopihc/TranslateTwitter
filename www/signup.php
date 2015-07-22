<?php

/**
 * @file
 * Check if consumer token is set and if so send user to get a request token.
 */

/**
 * Exit with an error message if the CONSUMER_KEY or CONSUMER_SECRET is not defined.
 */
require_once('../../translatetweet/config.php');
if (CONSUMER_KEY === '' || CONSUMER_SECRET === '') {
  //echo 'You need a consumer key and secret to test the sample code. Get one from <a href="https://dev.twitter.com/apps">dev.twitter.com/apps</a>';
  exit;
}

/* Build an image link to start the redirect process. */
$content = '<a class="btn btn-lg btn-info" href="authorize"><img src="images/sign-in-with-twitter-gray.png" alt="sign in with twitter"></a>';
$additional = '';
 
/* Include HTML to display on the page. */
include('../../translatetweet/inc.html');
