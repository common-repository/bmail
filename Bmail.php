<?php
/*
 
    Copyright 2014 Brandon Ferrara
  
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
    Plugin Name: Bmail
    Description: This plugin will allow mass mailing
    Author: Brandon Ferrara
    Author URI: http://bferrara.ca/
    Version: 1.1

*/

global $Bmail_db_version;
$Bmail_db_version =  "1.0";
//ini_set('sendmail_from', 'sender@domain.com');

class Bmail
{
  var $mimeboundary;
  
  var $sender;
  
  var $recipient;
  
  var $subject;
  
  var $attachments;
  
  var $headers=array();
  
  public $pluginName = "Bmail";
  
  function __construct(){
    $this->mimeboundary = "MIME_BOUNDRY";
    $this->sender = get_option('admin_email');
  }
  
  function Bmail_update_db_check() {
    global $Bmail_db_version;
    if (get_site_option( 'Bmail_db_version' ) != $Bmail_db_version) {
        $this->Bmail_install();
    }
  }
  
  function Bmail_install() {
     global $wpdb;
     global $Bmail_db_version;
  
    $Bmail_installed_ver = get_option( "Bmail_db_version" );

    if( $Bmail_installed_ver != $Bmail_db_version ) {
     $table_name = $wpdb->prefix . $this->pluginName;
        
     $sql = "CREATE TABLE ".$table_name."_hotkeys (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            hotkey_trigger VARCHAR(255) NOT NULL,
            created_date VARCHAR(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY title (title),
            UNIQUE KEY hotkey (hotkey_trigger));";
  
     require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
     dbDelta( $sql );
   
     if(!add_option( "Bmail_db_version", $Bmail_db_version )){
      update_option( "Bmail_db_version", $Bmail_db_version );
     }
    }
  }
  
  function Bmail_install_data(){
    global $wpdb;
    $table_name = $wpdb->prefix . $this->pluginName;
    $wpdb->insert( 
      $table_name.'_hotkeys', 
      array( 
          'title' => "Name", 
          'hotkey_trigger' => "name",
          'created_date' => date('Y-m-d h:i:s')
      ),
      array(
          '%s', 
          '%s', 
          '%s'  
      )
    );
  }
  
  function Bmail_insert_hotkey($hotkey){    
    global $wpdb;
    $table_name = $wpdb->prefix . $this->pluginName;
    $wpdb->insert( 
      $table_name.'_hotkeys', 
      array( 
          'title' => $hotkey['title'], 
          'hotkey_trigger' => preg_replace('/[^A-Za-z]/', '', $hotkey['trigger']),
          'created_date' => date('Y-m-d h:i:s')
      ), 
      array( 
          '%s', 
          '%s', 
          '%s'
      ) 
    );
  }
  
  function Bmail_get_hotkeys(){
    global $wpdb;
    $table_name = $wpdb->prefix . $this->pluginName;
    $hotkeys = $wpdb->get_results( "SELECT * FROM ".$table_name."_hotkeys ORDER BY title" );
    if(empty($hotkeys)){
      $this->Bmail_install_data();
      $hotkeys = $wpdb->get_results( "SELECT * FROM ".$table_name."_hotkeys ORDER BY title" );  
    }
    return $hotkeys;
  }
  
  function Bmail_Bmailer($recipient,$subject,$message,$attachments = null){
      $sender = $this->sender;
    // validate incoming parameters
    if($attachments){
      if(!is_array($attachments)){
          $attachments = array($attachments);
      }
    }
    if(!preg_match("/^.+@.+$/",$sender)){
    
      trigger_error('Invalid value for email sender.');
    
    }
    
    if(!preg_match("/^.+@.+$/",$recipient)){
      
      trigger_error('Invalid value for email recipient.');
    
    }
    
    if(!$subject||strlen($subject)>255){
      
      trigger_error('Invalid length for email subject.');
    
    }
    
    if(!$message){
      
      trigger_error('Invalid value for email message.');
    
    }
      
    $this->attachments=$attachments;
      
    $this->sender=$sender;
    
    $this->recipient=$recipient;
    
    $this->subject=$subject;
    
    $this->message=$message;
  
  // define some default MIME headers
    
    $this->headers['MIME-Version']='1.0';
    
    $this->headers['Content-Type']='text/html; charset=iso-8859-1';//'multipart/mixed; boundary='.$this->mimeboundary;
     
    $this->headers['From']=$this->sender;
    
    $this->headers['Return-Path']=$this->sender;
    
    $this->headers['Reply-To']=$this->sender;
    
    $this->headers['X-Mailer']='PHP/'.phpversion();
    
    $this->headers['X-Sender']=$this->sender;
    
    $this->headers['X-Priority']='3';
  
  }
  // create text part of the message
  
  function Bmail_buildBody(){
    
    //$body = "--".$this->mimeboundary."\nContent-Type: text/html; charset=iso-8859-1\n".
    $body = $this->message;
    if($this->attachments){
      for($i=0;$i<count($this->attachments);$i++){
        if(is_file($this->attachments[$i])){
          $body .= "--".$this->mimeboundary."\n";
          $fp =    @fopen($this->attachments[$i],"rb");
          $data =    @fread($fp,filesize($this->attachments[$i]));
          @fclose($fp);
          $data = chunk_split(base64_encode($data));
          $body .= "Content-Type: application/octet-stream; name=\"".basename($this->attachments[$i])."\"\n" .
          "Content-Description: ".basename($this->attachments[$i])."\r\n" .
          "Content-Disposition: attachment;\n" . " filename=\"".basename($this->attachments[$i])."\"; size=".filesize($this->attachments[$i]).";\n" .
          "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
        }
      }
    }
    //$body .= "--".$this->mimeboundary."--\n";
    return $body;
  }
  
  // create message MIME headers
  function Bmail_buildHeaders(){
    foreach($this->headers as $name=>$value){
      $headers[]=$name.': '.$value;
    }
    return implode("\n",$headers);//."\nThis is a multi-part message in MIME format.\n";
  }
  
  // add new MIME header
  function Bmail_addHeader($name,$value){
    $this->headers[$name]=$value;
  }
  
  // send email
  function Bmail_send(){
    $to=$this->recipient;
    $subject=$this->subject;
    $headers=str_ireplace('\r', '', $this->Bmail_buildHeaders());
    $message=$this->Bmail_buildBody();
    if(!mail($to,$subject,$message,$headers,'-f <'.$this->sender.'>')){
      trigger_error('Error sending email.',E_USER_ERROR);
    }
    return true;
  }


  public function Bmail_testBmail()
  {
    // create a new instance of the 'Mailer' class
   $file = 'http://*.gif';

    copy($file, basename($file));

   $attachments = array(basename($file));
   $this->Bmail_Bmailer('brandon@drbdiet.com','Testing mailer class','Hello buddy. How are you?', $attachments);

    if($this->Bmail_send()){
      echo 'Message was sent successfully.';
    }
    unlink(basename($file));
    //$this->forward('default', 'module');
  }

  function Bmail_menu() {
      add_options_page( 'Bmail', 'Bmail', 'manage_options', 'Bmail', array($this, $this->pluginName.'_mailer' ));
  }
  
  function Bmail_mailer(){
    echo "<h3>Bmail Mass Mailer</h2>
          <form method='post' style='display:inline-block;'><input ".(!isset($_POST['bmail_mailinglist']) ? "style='font-weight:bold;color:red;'" : '')." type='submit' class='button' value='Bmail' /></form>
          <form method='post' style='display:inline-block;'><input ".(isset($_POST['bmail_mailinglist']) ? "style='font-weight:bold;color:red;'" : '')." type='submit' class='button' name='bmail_mailinglist' value='Hotkeys' /></form>
          <hr />";
    if(isset($_POST['BmailSendingIdentifier']) && $_POST['BmailSendingIdentifier']=="send"){
      $this->headers['Bcc'] = $_POST['bcc'];
      $this->headers['Cc'] = $_POST['cc'];
      $message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> <html><head></head><body>'.stripcslashes(nl2br(str_ireplace('\r', '',$_POST['content']), true))."</body></html>";
      if(strpos($_POST['bmail_recipients'], ',')){
        $bmail_recipients = explode(',', $_POST['bmail_recipients']);   
        foreach($bmail_recipients as $recipient){
          $args = explode(';', $recipient);
          $recipient = trim(array_shift($args));
          $thisMessage=$message;
          $subject = $_POST['subject'];
          $outputMessage=$_POST['content'];
          foreach($args as $arg){
            $arg = explode(':', $arg);
            $thisMessage = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $thisMessage);
            $outputMessage = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $outputMessage);
            $subject = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $subject);
          }
          $this->Bmail_Bmailer($recipient, $subject, $thisMessage, $attachments);
          if($this->Bmail_send()){
            echo '<b>To: </b>'.$recipient."<br /><b>Subject: </b>".$subject."<br /><div style='border:solid;border-width:2px;width:80%;'>".nl2br(stripcslashes($outputMessage), true)."</div><hr />";
          }
          else{
            echo 'Failed to send the message to: ('.$recipient.')  :(';
          }
        }
      }
      else{
        $recipient = $_POST['bmail_recipients'];
        $args = explode(';', $recipient);
        $recipient = trim(array_shift($args));
        $thisMessage=$message;
        $subject = $_POST['subject'];
        $outputMessage=$_POST['content'];
        foreach($args as $arg){
          $arg = explode(':', $arg);
          $thisMessage = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $thisMessage);
          $outputMessage = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $outputMessage);
          $subject = str_replace('[Bmail_Hotkey:'.trim($arg[0]).']', trim($arg[1]), $subject);
        }
        $this->Bmail_Bmailer($recipient,$subject,$thisMessage, $attachments);
        if($this->Bmail_send()){
          echo '<b>To: </b>'.$recipient."<br /><b>Subject: </b>".$subject."<br /><div style='border:solid;border-width:2px;width:80%;'>".nl2br(stripcslashes($outputMessage), true)."</div>";
        }
        else{
          echo 'Failed to send the message :(';
        }
      }
      echo "<hr /><form method='post'><input type='submit' value='Do More Bmailing' /></form>";
    }
    elseif(isset($_POST['bmail_mailinglist'])){
      if($_POST['bmail_mailinglist'] == 'submit'){
        foreach($_POST['bmail_hotkeys'] as $key => $hotkey){
          $this->Bmail_insert_hotkey($hotkey);
        }
      }elseif($_POST['bmail_mailinglist'] == 'delete'){
        global $wpdb;
        $table_name = $wpdb->prefix . $this->pluginName;
        $wpdb->delete( $table_name, array( 'id' => $_POST['bmail_hotkey_id'] ) );
      }
      $currentHotkeys = $this->Bmail_get_hotkeys();
      echo "<p>You can set hotkeys to help you with mass mailing, if you want to hotkey the recipient's name you simply add the hotkey's title
            and the hotkey trigger and click save, you can use the hotkey when Bmailing by adding the trigger inside the hotkey box:
            [Bmail_Hotkey:<b><i>trigger</i></b>]. The default Bmail hotkey is <b><i>Name</i></b>.</p>
            <h3>New Hotkeys</h3>
            <form method='post'><input type='hidden' name='bmail_mailinglist' value='submit' />
            <table id='Bmail_hotkey_table'>
              <thead><tr><th>Title</th><th>Trigger</th></th><th></th></tr></thead>
              <tbody><tr><td><input type='text' name='bmail_hotkeys[0][title]' /></td><td><input type='text' name='bmail_hotkeys[0][trigger]' /></td><td></td></tr></tbody>
            </table><input type='submit' value='Save' /></form> <hr />
            <h3>Added Hotkeys</h3><table style='min-width:400px;'><thead><tr><th style='border:solid;border-width:2px;'>Title</th><th style='border:solid;border-width:2px;'>Trigger</th></th><th></th></tr></thead>
            <tbody>";
      foreach($currentHotkeys as $currentHotkey){
        echo "<tr><td style='border:solid;border-width:2px;'>".$currentHotkey->title."</td><td style='border:solid;border-width:2px;'>".$currentHotkey->hotkey_trigger."</td>
              <td><form method='post'><input type='hidden' name='bmail_hotkey_id' value='".$currentHotkey->id."' />
                <input type='hidden' name='bmail_mailinglist' value='delete' /><input type='submit' value='Delete' /></form>
              </td></tr>";
      }
      echo "</tbody></table>";
    }
    else{
      //Bmail Send Form
      echo "<form method='post' style='font-weight:bold;'><input type='hidden' name='BmailSendingIdentifier' value='send' />
      From: (Your site's email address)<br /> ".$this->sender."<hr />
      To: (Separate by commas for email, semi-colons for optional hotkeys and colons for corresponding values.) ex: email;hotkey:replacement<br />
        <input type='text' name='bmail_recipients' style='width:90%;' placeholder='".$this->sender.";name:".get_bloginfo('name').",test@otherMail.net;name:John Smith' /><hr />
      Cc: (Separate by commas)<br />
        <input type='text' name='cc' style='width:90%;'/><hr />
      Bcc: (Separate by commas)<br />
        <input type='text' name='bcc' style='width:90%;'/><hr />
      Subject: (You may place hotkeys here.)<br />
        <input type='text' name='subject' style='width:90%;' placeholder='RE: [Bmail_Hotkey:name]'/><hr />
      Message: (You may place hotkeys here.)";
      echo wp_editor('Dear [Bmail_Hotkey:name],', 'content');
      echo "<hr />
        <input type='submit' value='Send Bmail' /></form>";
    }
  }

}

    $bmail = new Bmail();
    add_action( 'admin_menu',  array($bmail, 'Bmail_menu') );
    add_action('admin_print_scripts', 'do_jslibs' );
    add_action('admin_print_styles', 'do_css' );
    add_action( 'plugins_loaded', array($bmail, 'Bmail_update_db_check') );
    register_activation_hook( __FILE__, 'Bmail_install' );
    register_activation_hook( __FILE__, 'Bmail_install_data' );
    
    function do_css()
    {
        wp_enqueue_style('thickbox');
    }
    
    function do_jslibs()
    {
        wp_enqueue_script('editor');
        wp_enqueue_script('thickbox');
        add_action( 'admin_head', 'wp_tiny_mce' );
    }

?>