<?php

if (!defined('MEDIAWIKI')) {
    die('Not an entry point.');
}

$wgUloginProviders = 'vkontakte,odnoklassniki,mailru,facebook,twitter,google,yandex,livejournal,openid,lastfm,linkedin,soundcloud';
$wgUloginHidden = 'other';
$wgUloginDisplay = 'small';
$wgUloginFields = 'first_name,last_name,nickname,email';

define('Ulogin_VERSION', '1.1');

$dir = dirname(__FILE__) . '/';

$wgExtensionCredits['validextensionclass'][] = array(
    'path' => __FILE__,
    'name' => 'uLogin',
    'author' => 'Cramen',
    'url' => 'https://github.com/ulogin/ulogin-MediaWiki',
    'descriptionmsg' => 'ulogin-desc-text',
    'version' => Ulogin_VERSION,
);

$wgExtensionMessagesFiles['ulogin'] = dirname(__FILE__) . '/Ulogin.i18n.php';

$wgHooks['UserLoadFromSession'][] = 'fnUloginAuthenticateHook';
$wgHooks['UserLoginForm'][] = 'onUserLoginForm';
$wgHooks['UserCreateForm'][] = 'onUserLoginForm';

function onUserLoginForm( &$tpl ) {
   global $wgUloginProviders;
   global $wgUloginHidden;
   global $wgUloginDisplay;
   global $wgUloginFields;
   $header = $tpl->get( 'header' );
   $titleObj = SpecialPage::getTitleFor( 'Userlogin' );
   $resultUrl = urldecode($titleObj->getLocalURL());

   $header .= '<strong>' . wfMsg( 'ulogin-login-via-social-text' ) . ':</strong><br /><script src="//ulogin.ru/js/ulogin.js"></script>
               <div id="uLogin" data-ulogin="display=' . $wgUloginDisplay . ';fields=' . $wgUloginFields . ';providers=' . $wgUloginProviders . ';hidden=' . $wgUloginHidden . ';redirect_uri=' . urlencode('http://'.$_SERVER['HTTP_HOST'].$resultUrl) . '"><img style="cursor:pointer;" src="/linkedin.png" data-uloginbutton="linkedin" /></div>
               <br />';
   $tpl->set( 'header', $header );
}

function fnUloginAuthenticateHook($user, &$result)
{
    global $IP, $wgLanguageCode, $wgRequest, $wgOut;

    if (isset($_REQUEST["title"])) {
        $lg = Language::factory($wgLanguageCode);

        if ($_REQUEST["title"] == $lg->specialPage("Userlogin")) {

            if ($_POST && isset($_POST['token'])) {
                $s = file_get_contents('http://ulogin.ru/token.php?token=' . $_POST['token'] . '&host=' . $_SERVER['HTTP_HOST']);
                $user = json_decode($s, true);

                $username = $user['nickname'] . '-' . $user['uid'];

                //die( var_dump($user['profile']) );

                $isNew = true;

                $u = User::newFromName($username);
                if( $u->getId() != 0 ) {
                    $isNew = false;
                }
                if( LinkedInData::userFromLinkedinId( $u ) ) {
                    $u = User::newFromId( LinkedInData::userFromLinkedinId( $u ) );
                    $isNew = false;
                }

                require_once("$IP/includes/WebStart.php");

                //Create new user if not exists
                if ($isNew) {

                    $u->addToDatabase();
                    $u->setRealName($user['first_name'] . ' ' . $user['last_name']);
                    $u->setEmail($user['email']);
                    $u->setPassword(md5($username.time())); // do something random
                    $u->setToken();
                    $u->saveSettings();

                    $u->sendConfirmationMail();

                    $ssUpdate = new SiteStatsUpdate(0, 0, 0, 0, 1);
                    $ssUpdate->doUpdate();

                }

                $u->setOption("rememberpassword", 1);
                $u->setCookies();
                $user = $u;

                //Redirect to access token request
                LinkedInData::requestUserToken();
                //$wgOut->redirect(Title::newMainPage()->getFullUrl());

            }
        }
        else if ($_REQUEST["title"] == $lg->specialPage("Userlogout")) {
            $user->logout();
        }
    }

    return true;
}
