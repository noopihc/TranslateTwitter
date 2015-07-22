<?php
/**
 * @file
 * Take the user when they return from Twitter. Get access tokens.
 * Verify credentials and redirect to based on response from Twitter.
 */

/* Start session and load lib */
session_start();
require_once('../../translatetweet/twitteroauth/twitteroauth.php');
require_once('../../translatetweet/config.php');

/* If the oauth_token is old redirect to the connect page. */
if (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
  $_SESSION['oauth_status'] = 'oldtoken';
  header('Location: ./clearsessions');
}

/* Request access tokens from twitter */
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

/* If access tokens are not available redirect to error page. */
if (empty($connection->http_code) || empty($_SESSION['oauth_token']) || empty($_SESSION['oauth_token_secret'])) {
    header('Location: ./error.html');
}

/* Request access tokens from twitter */
$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);

/* Save the access tokens. Normally these would be saved in a database for future use. */
$_SESSION['access_token'] = $access_token;

/* Remove no longer needed request tokens */
unset($_SESSION['oauth_token']);
unset($_SESSION['oauth_token_secret']);

/* If HTTP response is 200 continue otherwise send to connect page to retry */
if (200 == $connection->http_code) {
  /* The user has been verified and the access tokens can be saved for future use */
  $_SESSION['status'] = 'verified';
  setcookie("nouploadtrn", "verified", time() + 2592000);

  $host = MYSQL_HOST;
  $port = MYSQL_PORT;
  $name = MYSQL_NAME;

  try {
    $dbh = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8",MYSQL_USER,MYSQL_PASS);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    try {
      /* Insert new user */
      $valsi = array($access_token['user_id'],$access_token['screen_name'],$access_token['oauth_token'],$access_token['oauth_token_secret'],$_SERVER['REMOTE_ADDR']);
      $stmti = $dbh->prepare("insert into USERS (TWITTERID,TWITTERNAME,TWITTERTOKEN,TWITTERSECRET,IPADDRESS,CREATEDATE) values (?,?,?,?,?,NOW())");
      $stmti->execute($valsi);
    }
    catch(PDOException $e) {
      $rows = $stmti->rowCount();
      /* User exists, update user instead */
      if ($rows <= 0) {
        $valsu = array($access_token['screen_name'],$access_token['oauth_token'],$access_token['oauth_token_secret'],$_SERVER['REMOTE_ADDR'],$access_token['user_id']);
        $stmtu = $dbh->prepare("update USERS set TWITTERNAME = ?, TWITTERTOKEN = ?, TWITTERSECRET = ?, IPADDRESS = ?, ACTIVE = 1 where TWITTERID = ?");
        $stmtu->execute($valsu);
      }
    }

    /* Automatically follow user and user follows NoUpload */
    $followuser = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET,'*','*');
    $followuser->post('friendships/create', array('user_id' => $access_token['user_id']));
    $connection->post('friendships/create', array('user_id' => '252039189'));

    $dbh = null;
    header('Location: ./index#start');

  }
  catch (Exception $e) {
    $err = date('Y-m-d H:i:s')." callback: user: ".$access_token['user_id']." name: ".$access_token['screen_name']." token: ".$_SESSION['oauth_token']." secret: ".$_SESSION['oauth_token_secret']." IP: ".$_SERVER['REMOTE_ADDR']." error: ".$e->getMessage()."\n";
    echo file_put_contents("../../translatetweet/logs/errors.log",$err, FILE_APPEND);
    header('Location: ./error.html');
  }

} else {
  /* Save HTTP status for error dialog on connnect page.*/
  $err = date('Y-m-d H:i:s')." callback: ".$connection->http_code." user: ".$access_token['user_id']." name: ".$access_token['screen_name']." token: ".$_SESSION['oauth_token']." secret: ".$_SESSION['oauth_token_secret']." IP: ".$_SERVER['REMOTE_ADDR']." error: ".$e->getMessage()."\n";
  file_put_contents("../../translatetweet/logs/errors.log",$err, FILE_APPEND);
  header('Location: ./error.html');
}

