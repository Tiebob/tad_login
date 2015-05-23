<?php
function xoops_module_update_tad_login(&$module, $old_version) {
    GLOBAL $xoopsDB;

    if(!chk_chk1()) go_update1();
    if(!chk_chk2()) go_update2();
    if(!chk_chk3()) go_update3();

    return true;
}

//�ˬd���L�H���K�X��ƪ�
function chk_chk1(){
  global $xoopsDB;
  $sql="select count(*) from ".$xoopsDB->prefix("tad_login_random_pass");
  $result=$xoopsDB->query($sql);
  if(empty($result)) return false;
  return true;
}


//�����s
function go_update1(){
  global $xoopsDB;
  $sql="CREATE TABLE ".$xoopsDB->prefix("tad_login_random_pass")." (
    `uname` VARCHAR( 100 ) NOT NULL ,
    `random_pass` VARCHAR( 255 ) NOT NULL ,
    PRIMARY KEY ( `uname` )
  );";
  $xoopsDB->queryF($sql) or redirect_header(XOOPS_URL,3,  mysql_error());

  return true;
}


//�ˬd���L�s�չw�]����
function chk_chk2(){
  global $xoopsDB;
  $sql="select count(*) from ".$xoopsDB->prefix("tad_login_config");
  $result=$xoopsDB->query($sql);
  if(empty($result)) return false;
  return true;
}


//�����s
function go_update2(){
  global $xoopsDB;
  $sql="CREATE TABLE ".$xoopsDB->prefix("tad_login_config")." (
    `config_id` smallint(5) unsigned NOT NULL auto_increment,
    `item` text NOT NULL,
    `group_id` smallint(5) unsigned NOT NULL default 0,
    PRIMARY KEY (`config_id`)
  ) ENGINE=MyISAM ;";
  $xoopsDB->queryF($sql) or redirect_header(XOOPS_URL,3,  mysql_error());

  return true;
}


//�ˬd���L���O���
function chk_chk3(){
  global $xoopsDB;
  $sql="select count(`kind`) from ".$xoopsDB->prefix("tad_login_config");
  $result=$xoopsDB->query($sql);
  if(empty($result)) return false;
  return true;
}


//�����s
function go_update3(){
  global $xoopsDB;
  $sql="ALTER TABLE ".$xoopsDB->prefix("tad_login_config")." ADD `kind` varchar(255) NOT NULL default '' after `item`";
  $xoopsDB->queryF($sql) or redirect_header(XOOPS_URL,3,  mysql_error());

  $sql="select config_id,item from ".$xoopsDB->prefix("tad_login_config")." ";
  $result=$xoopsDB->queryF($sql) or redirect_header(XOOPS_URL,3,  mysql_error());
  while(list($config_id,$item)=$xoopsDB->fetchRow($result)){
    $kind=(strpos($item, "@")!==false)?"email":"teacher";
    $sql="update ".$xoopsDB->prefix("tad_login_config")." set kind='$kind' where config_id='{$config_id}'";
    $xoopsDB->queryF($sql) or redirect_header(XOOPS_URL,3,  mysql_error());
  }
  return true;
}


//�إߥؿ�
function mk_dir($dir=""){
    //�Y�L�ؿ��W�٨q�Xĵ�i�T��
    if(empty($dir))return;
    //�Y�ؿ����s�b���ܫإߥؿ�
    if (!is_dir($dir)) {
        umask(000);
        //�Y�إߥ��Ѩq�Xĵ�i�T��
        mkdir($dir, 0777);
    }
}

//�����ؿ�
function full_copy( $source="", $target=""){
  if ( is_dir( $source ) ){
    @mkdir( $target );
    $d = dir( $source );
    while ( FALSE !== ( $entry = $d->read() ) ){
      if ( $entry == '.' || $entry == '..' ){
        continue;
      }

      $Entry = $source . '/' . $entry;
      if ( is_dir( $Entry ) ) {
        full_copy( $Entry, $target . '/' . $entry );
        continue;
      }
      copy( $Entry, $target . '/' . $entry );
    }
    $d->close();
  }else{
    copy( $source, $target );
  }
}


function rename_win($oldfile,$newfile) {
   if (!rename($oldfile,$newfile)) {
      if (copy ($oldfile,$newfile)) {
         unlink($oldfile);
         return TRUE;
      }
      return FALSE;
   }
   return TRUE;
}


function delete_directory($dirname) {
    if (is_dir($dirname))
        $dir_handle = opendir($dirname);
    if (!$dir_handle)
        return false;
    while($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname."/".$file))
                unlink($dirname."/".$file);
            else
                delete_directory($dirname.'/'.$file);
        }
    }
    closedir($dir_handle);
    rmdir($dirname);
    return true;
}

?>
