<?php
//引入TadTools的函式庫
if(!file_exists(XOOPS_ROOT_PATH."/modules/tadtools/tad_function.php")){
 redirect_header("http://www.tad0616.net/modules/tad_uploader/index.php?of_cat_sn=50",3, _TAD_NEED_TADTOOLS);
}
include_once XOOPS_ROOT_PATH."/modules/tadtools/tad_function.php";


/********************* 自訂函數 *********************/


//FB登入
if(!function_exists('facebook_login')){
  function facebook_login($mode=""){
    global $xoopsConfig ,$xoopsDB , $xoopsTpl,$xoopsUser;
    require_once 'class/facebook.php';

    if($xoopsUser){
      header("location:".XOOPS_URL . "/user.php");
      exit;
    }

    if (isset($_POST)) {
        foreach ( $_POST as $k => $v ) {
            ${$k} = $v;
        }
    }
    if (isset($_GET['op'])) {
        $op = trim($_GET['op']);
        if (isset($_GET['uid'])) {
            $uid = intval($_GET['uid']);
        }
    }

    $modhandler = &xoops_gethandler('module');
    $tad_loginModule = &$modhandler->getByDirname("tad_login");
    $config_handler =& xoops_gethandler('config');
    $tad_loginConfig= & $config_handler->getConfigsByCat(0, $tad_loginModule->getVar('mid'));


    $facebook = new Facebook(array(
    'appId'  => $tad_loginConfig['appId'],
    'secret' => $tad_loginConfig['secret'],
    ));

    $user = $facebook->getUser();

    if ($user) {
      try {
        // Proceed knowing you have a logged in user who's authenticated.
        $user_profile = $facebook->api('/me');
      } catch (FacebookApiException $e) {
        error_log($e);
        $user = null;
      }
    }


    // Login or logout url will be needed depending on current user state.
    if ($user) {
      $myts =& MyTextsanitizer::getInstance();
      $uid = $user_profile['id'];
      $uname = $user_profile['username']."_fb";
      $name = $myts->addSlashes($user_profile['name']);
      $email =  $user_profile['email'];
      $bio = $myts->addSlashes($user_profile['bio']);
      $url = formatURL($user_profile['link']);
      $form= $myts->addSlashes($user_profile['hometown']['name']);
      $sig= $myts->addSlashes($user_profile['quotes']);
      $occ= $myts->addSlashes($user_profile['work'][0]['employer']['name']);

      login_xoops($uname,$name,$email,"",$url,$form,$sig,$occ,$bio);
    } else {
      //$args = array('scope' => 'email');
      //$loginUrl = $facebook->getLoginUrl($args);
      $loginUrl = $facebook->getLoginUrl(array(
      'scope' => 'email'
      //,'redirect_uri' => XOOPS_URL
      ));
    }
    if($mode=="return"){
      return $loginUrl;
    }else{
      $xoopsTpl->assign('facebook',$loginUrl);
    }
  }
}


//搜尋有無相同username資料
if(!function_exists('login_xoops')){
  function login_xoops($uname="",$name="",$email="",$SchoolCode="",$url="",$form="",$sig="",$occ="",$bio=""){
    global $xoopsModuleConfig , $xoopsConfig ,$xoopsDB ,$xoopsUser;

    $member_handler =& xoops_gethandler('member');

    if ($member_handler->getUserCount(new Criteria('uname', $uname)) > 0) {
      //若有！
      $uname = trim($uname);
      $pass = getPass($uname);

      if ($uname == '' || $pass == '') {
        redirect_header(XOOPS_URL.'/user.php', 1, _MD_TNOPENID_INCORRECTLOGIN);
        exit();
      }

      $member_handler =& xoops_gethandler('member');
      xoops_loadLanguage('auth');

      include_once $GLOBALS['xoops']->path('class/auth/authfactory.php');

      $xoopsAuth =& XoopsAuthFactory::getAuthConnection($uname);

      $user = $xoopsAuth->authenticate($uname, $pass);

      if (false != $user) {
        if (0 == $user->getVar('level')) {
          redirect_header(XOOPS_URL.'/index.php', 5, _MD_TNOPENID_NOACTTPADM);
          exit();
        }

        if ($xoopsConfig['closesite'] == 1) {
          $allowed = false;
          foreach ($user->getGroups() as $group) {
            if (in_array($group, $xoopsConfig['closesite_okgrp']) || XOOPS_GROUP_ADMIN == $group) {
              $allowed = true;
              break;
            }
          }
          if (!$allowed) {
            redirect_header(XOOPS_URL.'/index.php', 1, _MD_TNOPENID_NOPERM);
            exit();
          }
        }

        $user->setVar('last_login', time());
        if (!$member_handler->insertUser($user)) {
        }
        // Regenrate a new session id and destroy old session
        $GLOBALS["sess_handler"]->regenerate_id(true);
        $_SESSION = array();
        $_SESSION['xoopsUserId'] = $user->getVar('uid');
        $_SESSION['xoopsUserGroups'] = $user->getGroups();
        $user_theme = $user->getVar('theme');
        if (in_array($user_theme, $xoopsConfig['theme_set_allowed'])) {
          $_SESSION['xoopsUserTheme'] = $user_theme;
        }

        // Set cookie for rememberme
        if (!empty($xoopsConfig['usercookie'])) {
          if (!empty($_POST["rememberme"])) {
            setcookie($xoopsConfig['usercookie'], $_SESSION['xoopsUserId'] . '-' . md5($user->getVar('pass') . XOOPS_DB_NAME . XOOPS_DB_PASS . XOOPS_DB_PREFIX), time() + 31536000, '/', XOOPS_COOKIE_DOMAIN, 0);
          } else {
            setcookie($xoopsConfig['usercookie'], 0, -1, '/', XOOPS_COOKIE_DOMAIN, 0);
          }
        }

        if (!empty($_POST['xoops_redirect']) && !strpos($_POST['xoops_redirect'], 'register')) {
          $_POST['xoops_redirect'] = trim($_POST['xoops_redirect']);
          $parsed = parse_url(XOOPS_URL);
          $url = isset($parsed['scheme']) ? $parsed['scheme'].'://' : 'http://';
          if (isset( $parsed['host'])) {
            $url .= $parsed['host'];
            if (isset( $parsed['port'])) {
              $url .= ':' . $parsed['port'];
            }
          } else {
            $url .= $_SERVER['HTTP_HOST'];
          }
          if (@$parsed['path']) {
            if (strncmp($parsed['path'], $_POST['xoops_redirect'], strlen( $parsed['path']))) {
              $url .= $parsed['path'];
            }
          }
          $url .= $_POST['xoops_redirect'];
        } else {
          $url = XOOPS_URL . '/index.php';
        }

        // RMV-NOTIFY
        // Perform some maintenance of notification records
        $notification_handler =& xoops_gethandler('notification');
        $notification_handler->doLoginMaintenance($user->getVar('uid'));

        redirect_header($url, 1, sprintf("", $user->getVar('uname')), false);
      } else if (empty($_POST['xoops_redirect'])) {
        redirect_header(XOOPS_URL . '/user.php', 5, $xoopsAuth->getHtmlErrors());
      } else {
        redirect_header(XOOPS_URL . '/user.php?xoops_redirect=' . urlencode(trim($_POST['xoops_redirect'])), 5, $xoopsAuth->getHtmlErrors(), false);
      }
    }else {

      $pass = randStr(128);
      $newuser =& $member_handler->createUser();
      $newuser->setVar("user_viewemail",1);
      $newuser->setVar("attachsig",0);
      $newuser->setVar("name", $name);
      $newuser->setVar("uname", $uname);
      $newuser->setVar("email", $email);
      $newuser->setVar("url", formatURL($url));
      $newuser->setVar("user_avatar",'avatars/blank.gif');
      $newuser->setVar('user_regdate', time());
      $newuser->setVar("user_icq", "");
      $newuser->setVar("user_from", $from);
      $newuser->setVar("user_sig", $sig);
      $newuser->setVar("theme", $xoopsConfig['theme_set']);
      $newuser->setVar("user_yim", "");
      $newuser->setVar("user_msnm", "");
      $newuser->setVar("pass", md5($pass));
      $newuser->setVar("timezone_offset", $xoopsConfig['default_TZ']);
      $newuser->setVar("uorder", $xoopsConfig['com_order']);
      $newuser->setVar("umode",$xoopsConfig['com_mode']);
      // RMV-NOTIFY
      $newuser->setVar("notify_method", 1);
      $newuser->setVar("notify_mode", 1);
      $newuser->setVar("bio", $bio);
      $newuser->setVar("rank", 1);
      $newuser->setVar("level", 1);
      //$newuser->setVar("user_occ", $myts->addSlashes($user_profile['work'][0]['employer']['name']));
      $newuser->setVar("user_intrest", $SchoolCode);
      $newuser->setVar('user_mailok', true);
      if (!$member_handler->insertUser($newuser, 1)) {
          $main= _MD_TADLOGIN_CNRNU;
      }

      $sql = "INSERT INTO `" . $xoopsDB->prefix('groups_users_link') . "`  (groupid, uid) VALUES  (2, " . $newuser->getVar('uid') . ")";
      $xoopsDB->queryF($sql) or die(mysql_error());

      $sql = "replace into `".$xoopsDB->prefix('tad_login_random_pass')."` (`uname` , `random_pass`) values  ('{$uname}','{$pass}')";
      $xoopsDB->queryF($sql) or die(mysql_error());

      login_xoops($uname,$name,$email,$SchoolCode,$url,$form,$sig,$occ,$bio);
    }
  }
}

if(!function_exists("getPass")){
  function getPass($uname=""){
    global $xoopsDB;
    if(empty($uname))return;

    $sql = "select `random_pass` from `".$xoopsDB->prefix('tad_login_random_pass')."` where `uname`='{$uname}'";
    $result = $xoopsDB->queryF($sql) or die(mysql_error());
    list($random_pass)=$xoopsDB->fetchRow($result);

    //舊OpenID使用者
    if(empty($random_pass)){
      $random_pass=randStr(128);

      $sql = "replace into `".$xoopsDB->prefix('tad_login_random_pass')."` (`uname` , `random_pass`) values  ('{$uname}','{$random_pass}')";
      $xoopsDB->queryF($sql) or die(mysql_error());

      $sql="update `".$xoopsDB->prefix('users')."` set `pass`=md5('{$random_pass}') where `uname`='{$uname}'";
      $xoopsDB->queryF($sql) or die(mysql_error());
    }


    $sql = "select `pass` from `".$xoopsDB->prefix('users')."` where `uname`='{$uname}'";
    $result = $xoopsDB->queryF($sql) or die(mysql_error());
    list($pass)=$xoopsDB->fetchRow($result);
    if($pass !== md5($random_pass)){
      $sql="update `".$xoopsDB->prefix('users')."` set `pass`=md5('{$random_pass}') where `uname`='{$uname}'";
      $xoopsDB->queryF($sql) or die(mysql_error());
    }

    return $random_pass;
  }
}


if(!function_exists("randStr")){
  function randStr($len=6,$format='ALL') {
    switch($format) {
      case 'ALL':
        $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'; break;
      case 'CHAR':
        $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; break;
      case 'NUMBER':
      $chars='0123456789'; break;
      default :
        $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      break;
    }

    mt_srand((double)microtime()*1000000*getmypid());
    $password="";
    while(strlen($password)<$len)
      $password.=substr($chars,(mt_rand()%strlen($chars)),1);
    return $password;
  }
}

?>