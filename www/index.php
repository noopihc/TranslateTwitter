<?php
/**
 * @file
 * User has successfully authenticated with Twitter. Access tokens saved to session and DB.
 */

/* Load required lib files. */
session_start();
require_once('../../translatetweet/twitteroauth/twitteroauth.php');
require_once('../../translatetweet/config.php');

/* If access tokens are not available redirect to connect page. */
//if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
if (empty($_COOKIE["nouploadtrn"])) {
    header('Location: ./clearsessions');
}
/* Get user access tokens out of the session. */
//$access_token = $_SESSION['access_token'];

$content = '<a class="btn btn-lg btn-info" href="#start">Congratulations. Lets get started!</a>';
$additional = '<ul><li>Type your tweet and mention @NoUpload in your tweet.</li><li>Include a language hashtag (see list of supported language hashtags below) or multiple language hashtags in your tweet.</li><li>Send the tweet and wait a few minutes.</li><li>Your translated tweets will show up automatically in your Twitter stream.</li></ul>';

/* Include HTML to display on the page */
include('../../translatetweet/inc.html');
