<?php
require 'libs/PHPMailer/PHPMailerAutoload.php';

class PHPLogMailer extends PHPLog
{   
    /* create a candidate */
    public function addCandidate($email, $lang='en')
    {
        $this->cleanUpCandidates();
        
        if (!$this->check_email_address($email)) {
            $this->lastError = 'invalid email';
            return false;
        }
        
        // Already exist ?
        $sql = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` WHERE EMAIL="'.$email.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if ($data) {
            $this->lastError = 'email already register';
            return false;
        }

        // Add to temporary user db
        $uuid = "";
        $sql = 'SELECT * FROM `'.($GLOBALS['CONFIG']['user_table'].'.tmp').'` WHERE EMAIL="'.$email.'"';
        $req = $this->db->query($sql);  
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            // save request
            $uuid = $this->guid();
            $sql = "INSERT INTO `".($GLOBALS['CONFIG']['user_table'].'.tmp')."` (`UUID`, `EMAIL`) VALUES ('".$uuid."', '".$email."');";
            $req = $this->db->query($sql);  
            if (!$req) $this->dbError($sql);
        } else {
            // already existing request
            $uuid = $data['UUID'];
        }
        
        //send mail 
        $htmlFile = ".mail.template.newrequest.$lang.html";
        $arr = array('%base_url%' => $GLOBALS['CONFIG']['base_url'], '%auth_key%' => $uuid);        
        $message  = $this->getFileContent($htmlFile, $arr);        
        $regexp='/\<TITLE\>(.*?)\<\/TITLE\>/i';
        if (!preg_match($regexp, $message, $matches)) {
            die("&lt;TITLE&gt; tag missing in '$htmlFile'");
        }
        if(!$this->sendMail($email, $matches[1], $message)) {
            $this->lastError = "couln't sent email";
            return false;
        }
        return true;
    }
    
    /* return a candidate */
    public function getCandidate($uuid)
    {
        $sql = 'SELECT * FROM `'.($GLOBALS['CONFIG']['user_table'].'.tmp').'` WHERE UUID="'.$uuid.'"';
        $req = $this->db->query($sql);  
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = "invalid uuid";
            return false;
        }
        return $data;
    }
    
    /* delete a candidate */
    public function deleteCandidate($uuid)
    {
        $sql = 'DELETE FROM `'.($GLOBALS['CONFIG']['user_table'].'.tmp').'` WHERE UUID="'.$uuid.'"';
        $req = $this->db->query($sql);  
        if (!$req) $this->dbError($sql);        
        return true;
    }
    
    
    // ---

    public function forgetPassword($email, $lang='en')
    {
        if (!$this->check_email_address($email)) {
            $this->lastError = 'invalid email';
            return false;
        }
        
        $sql = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` WHERE EMAIL="'.$email.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = 'email unkown';
            return false;
        }
        
        // change for a new temporay password
        $u = strtoupper(substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 4));
        $l = strtoupper(substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 4));
        $new_password = $u.'-'.$l;
        $sql = "UPDATE `".$GLOBALS['CONFIG']['user_table']."` SET ";
        $sql .= "`PASSWORD`='".md5($new_password)."'";
        $sql .= " WHERE `UUID` =  '".$data['UUID']."'";        
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        
        //send mail 
        $htmlFile = ".mail.template.forget.$lang.html";
        $arr = array('%base_url%' => $GLOBALS['CONFIG']['base_url'], '%new_password%' => $new_password);        
        $message  = $this->getFileContent($htmlFile, $arr);        
        $regexp='/\<TITLE\>(.*?)\<\/TITLE\>/i';
        if (!preg_match($regexp, $message, $matches)) {
            die("&lt;TITLE&gt; tag missing in '$htmlFile'");
        }
        if(!$this->sendMail($email, $matches[1], $message)) {
            $this->lastError = "couln't sent email";
            return false;
        }
        
        return true;
    }
    
    /* send an email to user */
    public function sendEmail($htmlFile, $replace) {
        
    }

    // ---
    protected function check_email_address($email) {
        $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/'; 
        return (preg_match($regex, $email));
    }

    /* send email */
    protected function sendMail($mailTo, $title, $messge) {
        $mail = new PHPMailer();
        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->SMTPDebug  = 0;                     // enables SMTP debug information (for testing)
                                                   // 1 = errors and messages
                                                   // 2 = messages only		
                                                   
        $mail->Host       = $GLOBALS['CONFIG']['smtp_host'];	
        $mail->Port       = $GLOBALS['CONFIG']['smtp_port'];
        $mail->Username   = $GLOBALS['CONFIG']['smtp_login'];	
        $mail->Password   = $GLOBALS['CONFIG']['smtp_pw'];											   
        $mail->SMTPAuth   = $GLOBALS['CONFIG']['smtp_auth'];
        $mail->SMTPSecure = $GLOBALS['CONFIG']['smtp_secure'];
        
        $mail->IsHTML(true);
        $mail->SetFrom($GLOBALS['CONFIG']['smtp_email']);
        $mail->AddReplyTo($GLOBALS['CONFIG']['smtp_email']);	
        $mail->AddAddress($mailTo);
        $mail->Subject  = $title;            
        $mail->Body = $messge;
        return $mail->Send();
    }

    protected function cleanUpCandidates() {
        
    }
    
    function __construct($debug=false)
    {
        parent::__construct($debug);

    }
        
}
?>