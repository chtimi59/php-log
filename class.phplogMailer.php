<?php
require 'libs/PHPMailer/PHPMailerAutoload.php';

class PHPLogMailer extends PHPLog
{   
    // --- Email Candidates --- 
      
    /* create a candidate */
    public function addCandidate($email, $lang='en')
    {
        // Sanity check
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

        // Clean too old candidates
        $sql = 'DELETE FROM `'.($GLOBALS['CONFIG']['user_table'].'.tmp').'` WHERE (DATE + INTERVAL 30 MINUTE < NOW())';
        $req = $this->db->query($sql);  
        if (!$req) $this->dbError($sql);
        
        // Add it in db
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
        $arr = array('%AUTH_KEY%' => $uuid);                
        return $this->sendEmail('candidate',$lang,$arr,$email);
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
    
    
    // --- User override methods --- 

    public function addUser($user, $lang='en')
    {
        if (parent::addUser($user))
        {
             /* replacement %VAR% */
            $arr = array();  
            foreach ($user as $key => $value) {
                $key = "%$key%";
                if (!isset($arr[$key])) $arr[$key]=$value;
            }
            
            /* send mail */
            return $this->sendEmail('add',$lang,$arr,$user['EMAIL']);        
            
        } else {
            return false;
        }
    }
    
    public function deleteUser($lang='en', $idenfication=NULL, $password=NULL)
    {
        /* values before */
        $uuid = $this->checkCredentials($idenfication, $password);
        $uuid = $this->UUIDnotNull($uuid);
        $before = $this->getUser($uuid);
        if (!$before) {
            $this->lastError = 'Unknown User';
            return false; 
        }
        
        /* delete it */
        if (parent::deleteUser($idenfication, $password))
        {
            /* replacement %VAR% */
            $arr = array();  
            foreach ($before as $key => $value) {
                $key = "%$key%";
                if (!isset($arr[$key])) $arr[$key]=$value;
            }
            
            /* send mail */
            return $this->sendEmail('delete',$lang,$arr,$before['EMAIL']);        
            
        } else {
            return false;
        }
    }
    
    public function updateUser($user, $lang='en', $idenfication=NULL, $password=NULL)
    {
        /* values before */
        $uuid = $this->checkCredentials($idenfication, $password);
        $uuid = $this->UUIDnotNull($uuid);
        $before = $this->getUser($uuid);
        if (!$before) {
            $this->lastError = 'Unknown User';
            return false; 
        }
        
        /* update it */
        if (parent::updateUser($user, $idenfication, $password))
        {            
            /* get new values */
            $after = $this->getUser($uuid);
            if (!$after) {
                $this->lastError = 'Unknown User';
                return false; 
            }
            
            /* replacement %VAR% */            
            $arr = array();  
            foreach ($after as $key => $value) {
                $key = "%$key%";
                if (!isset($arr[$key])) $arr[$key]=$value;
            }            
            
            /* send mail */
            if ($after['EMAIL']!=$before['EMAIL']) $this->sendEmail('update',$lang,$arr,$before['EMAIL']);        
            return $this->sendEmail('update',$lang,$arr,$after['EMAIL']); 
            
        } else {
            return false;
        }
    }
    
    public function forgetPassword($email, $lang='en')
    {
        // Sanity check
        if (!$this->check_email_address($email)) {
            $this->lastError = 'invalid email';
            return false;
        }
        
        // Do we now this email ?
        $sql = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` WHERE EMAIL="'.$email.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = 'email unkown';
            return false;
        }
        $uuid = $data['UUID'];
        
        // Change it for a new temporay password !
        $u = strtoupper(substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 4));
        $l = strtoupper(substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 4));
        $new_password = $u.'-'.$l;
        $sql = "UPDATE `".$GLOBALS['CONFIG']['user_table']."` SET ";
        $sql .= "`PASSWORD`='".md5($new_password)."'";
        $sql .= " WHERE `UUID` =  '$uuid'";        
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);        
        
        /* update current */
        if ($this->isLog($uuid)) $this->setUser($uuid);
        
        //send mail 
        $arr = array('%NEW_PASSWORD%' => $new_password);        
        return $this->sendEmail('forget',$lang,$arr,$email);        
    }
    
    
    /* Send an email
       templateName, html template name such as 'account'
       lang, language such as 'en' or 'fr' for french
       replace_arr, array of strings replacement in template %VAR%
       email, by default use user email
    */
    public function sendEmail($templateName, $lang='en', $replace_arr=NULL, $email=NULL)
    {
        /* find out html template file */
        $htmlFilePath="";
        if ($this->conf['email_template_folder']!=null) {
            $htmlFilePath .= $GLOBALS['CONFIG']['root_path'];
            $htmlFilePath .= DIRECTORY_SEPARATOR.$this->conf['email_template_folder'];
        } else {
            $htmlFilePath .= $this->curPath.DIRECTORY_SEPARATOR;
            $htmlFilePath .= "mails.template".DIRECTORY_SEPARATOR;
        }
        $htmlFilePath .= DIRECTORY_SEPARATOR."$lang.$templateName.html";
        if ($this->debug) echo("send mail with this file $htmlFilePath <br>\n");
        
        /* add %VAR% for template */
        if (NULL == $replace_arr) $replace_arr = array();
        if (!isset($replace_arr['%BASE_URL%'])) $replace_arr['%BASE_URL%'] = $GLOBALS['CONFIG']['base_url'];
        if (NULL!=$this->user) {
            foreach ($this->user as $key => $value) {
                $key = "%$key%";
                if (!isset($replace_arr[$key])) $replace_arr[$key]=$value;
            }
        }
        $message  = $this->getFileContent($htmlFilePath, $replace_arr);
        
        /* find 'title' tag */
        $regexp='/\<TITLE\>(.*?)\<\/TITLE\>/i';
        if (!preg_match($regexp, $message, $matches)) {
            die("&lt;TITLE&gt; tag missing in '$htmlFilePath'");
        }
        $title = $matches[1];
        
        /* email To ? */
        if (NULL==$email)
        {
            if (NULL == $this->user) {
                $this->lastError = "No email";
                return false;
            }
            $email = $this->user['EMAIL'];
        }
        if ($this->debug) echo("send to $email<br>\n");
        
        /* send email */
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
        $mail->AddAddress($email);
        $mail->Subject = $title;            
        $mail->Body = $message;
        
        if (!$mail->Send()) {
            $this->lastError = "couln't sent email";
            return false;
        } else {
            return true;
        }
    }

    // ---
    protected function check_email_address($email) {
        $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/'; 
        return (preg_match($regex, $email));
    }

    function __construct($debug=false)
    {
        parent::__construct($debug);

    }
        
}
?>