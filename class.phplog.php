<?php
class PHPLog
{   
    /* user privilege */
    const PRIVILEGE_NONE  = 0;
    const PRIVILEGE_ADMIN = 1;

    /* last error string */
    public $lastError = '';
    
    /* current user info */
    protected $user = NULL;
    
    /* user accessor */
    public function user() {
        return $this->user;
    }
    
    /* returns True if logged */
    public function isLog($uuid=NULL) {
        if (NULL == $this->user) return false;        
        if ($uuid==NULL) {
            return true;
        } else {
            return ($this->user['UUID']==$uuid);
        }
    }
    
    /* returns True if admin */
    public function isAdmin($uuid=NULL) {
        if (!$this->isLog($uuid)) return false;
        return ($this->user['PRIVILEGE']==self::PRIVILEGE_ADMIN);
    }
    
    /* user log out */
    public function logOut($uuid=NULL) { 
        if ((NULL!=$uuid) && (!$this->isLog($uuid))) return;
        $this->unSetUser();
    }
    
    /* user log in */
    public function logIn($idenfication, $password=NULL)
    {   
        /* check Credentials */
        $uuid = $this->checkCredentials($idenfication, $password);
        if (!$uuid) {
            $this->logOut();
            return false; 
        }
            
        /* set current user */
        return $this->setUser($uuid);
    }
    
    // --- User Operation ----
    
    public function addUser($user)
    {
        if(!isset($user['PASSWORD'])) {
            $this->lastError = 'PASSWORD missing';
            return false;
        }
        
        if(!isset($user['EMAIL'])) {
            $this->lastError = 'EMAIL missing';
            return false;
        }
        
        $user['UUID'] = $this->guid();        
        $user['PASSWORD'] = md5($user['PASSWORD']); // Hash it
        $user['CREATION_DATE'] = date('Y-m-d H:i:s',time());
        $user['LAST_CONNECTION'] = date('Y-m-d H:i:s',time());
        $user['LAST_IP'] = $this->getClientIp();
        
        // Add a new USER!        
        $sql = "INSERT INTO `".$GLOBALS['CONFIG']['user_table']."` ";
		$sql .="(";
        $first = true;
        foreach ($user as $key => $value) {
            $sql .= ($first?' ':' ,')."`".$key."`";
            if ($first) $first = false;
        }
        $sql .=") VALUES (";
        $first = true;
        foreach ($user as $key => $value) {
            if (is_string($value)) $value="'$value'";
            $sql .= ($first?' ':' ,').$value;
            if ($first) $first = false;
        }
		$sql .= ");";            
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        return true; 
    }
    
    public function deleteUser($idenfication=NULL, $password=NULL)
    {
        /* check Credentials */
        $uuid = $this->checkCredentials($idenfication, $password);
        $uuid = $this->UUIDnotNull($uuid);
        if (!$uuid) return false;
        
        /* delete user */
        $sql  = "DELETE FROM `".$GLOBALS['CONFIG']['user_table']."` WHERE UUID='$uuid'";
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        
        // logOut
        $this->logOut($uuid);        
        return true; 
    }
    
    public function updateUser($user, $idenfication=NULL, $password=NULL)
    {
        // protect some fields
        if (isset($user['UUID'])) unset($user['UUID']);
        if (isset($user['CREATION_DATE'])) unset($user['CREATION_DATE']);
        if (isset($user['LAST_CONNECTION'])) unset($user['LAST_CONNECTION']);
        if (isset($user['LAST_IP'])) unset($user['LAST_IP']);
        
        /* check Credentials */
        $uuid = $this->checkCredentials($idenfication, $password);
        $uuid = $this->UUIDnotNull($uuid);
        if (!$uuid) return false;
                
        /* update user */
        $sql = "UPDATE `".$GLOBALS['CONFIG']['user_table']."` SET ";
        $first = true;
        foreach ($user as $key => $value) {
            if (is_string($value)) $value="'$value'";
            $sql .= ($first?' ':' ,')."`$key`=$value";
            if ($first) $first= false;
        }
        $sql .= " WHERE `UUID` =  '".$uuid."'";        
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        
        /* update current */
        if ($this->isLog($uuid)) $this->setUser($uuid);
        
        return true; 
    }
    
    // --- User set/unset ----
    
    /* unset a User (i.e. logOut) */
    protected function unSetUser() {
        $this->user = NULL;
        if ($this->conf['use_session']) {
            if (isset($_SESSION['phplog_uuid'])) unset($_SESSION['phplog_uuid']);  
        }
        return true;
    }    
    
    /* set a User only with the uuid 
       return true if succeed
       
       Warning: This method shouldn't be use directly from UI as there no
       login/password checks here.       
    */
    protected function setUser($uuid)
    {
        if (NULL == $uuid) {
            $this->logOut();
            $this->lastError = 'Invalid uuid';
            return false;
        }
        	
        /* update connection date */
        $sql  = "UPDATE `".$GLOBALS['CONFIG']['user_table']."` SET ";
        $sql .= "`LAST_CONNECTION`=now(), ";
        $sql .= "`LAST_IP`='".$this->getClientIp()."' ";
        $sql .= "WHERE UUID='$uuid'";
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);

        /* get user */
        $data = $this->getUser($uuid);
        if (!$data) {
            $this->logOut();
            return false;
        }
        
        $this->user = $data;		
        
        if ($this->conf['use_session']) {
            $_SESSION['phplog_uuid'] = $data['UUID'];
        }        
        return true;
    }
    
    /* read data of a specific UUID */
    protected function getUser($uuid)
    {        
        if (NULL==$uuid) {
            $this->lastError = 'Invalid uuid';
            return false; 
        }         
        $sql  = "SELECT * FROM `".$GLOBALS['CONFIG']['user_table']."` WHERE UUID='$uuid'";
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = 'Unknown User';
            return false; 
        } 
        return $data; 
    }    
    
    /* if $uuid is NULL return current logged user's uuid */
    protected function UUIDnotNull($uuid)
    {        
        if (NULL==$uuid) {
            if ($this->user) {
                $uuid = $this->user['UUID'];
            } else {     
                $this->lastError = 'Invalid uuid';
                return false; 
            }
        }         
        return $uuid;
    }
    
    // --- Credentials ----
    
    /* credentials check return uuid if succeed */
    protected function checkCredentials($idenfication, $password=NULL)
    {
        // check with $idenfication
        if (NULL==$idenfication) {
            $this->lastError = 'Invalid idenfication';
            return false; 
        } 
        $sql  = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` ';
        $sql .= 'WHERE '.$this->conf['login_with'].'="'.$idenfication.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = 'Unknown User';
            return false; 
        }
        
        // optional password check
        if ($this->conf['use_passwords']) {
            if ($data['PASSWORD']!=md5($password)) {
                $this->lastError = 'Invalid Password';
                return false; 
            }
        }
        
        return $data['UUID'];
    }
    
    // --- BASICS ----
    
    /* local current path */
    protected $curPath = NULL;
    
    /* user-configuration from phplog.conf */
    protected $conf = NULL;

    /* output log */
    protected $debug = false;
    
    /* database mysqli object */
    protected $db = NULL;
    
    /* create guid RFC 4122 */
    public function guid(){
        if (function_exists('com_create_guid')){
            return trim(com_create_guid(), '{}');
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
             $uuid = substr($charid, 0, 8).$hyphen
                    .substr($charid, 8, 4).$hyphen
                    .substr($charid,12, 4).$hyphen
                    .substr($charid,16, 4).$hyphen
                    .substr($charid,20,12);
            return $uuid;
        }
    }
    
    /* get Client Ip Address */
    public function getClientIp()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
           $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        if ($ipaddress=="::1") $ipaddress="127.0.0.1";    
        return $ipaddress;
    }

    /* database error */
    protected function dbError($msg) {
        if ($this->debug)
        {
            $str="<br>\n";
            $str .= "MYSQLI ERROR:<br>\n";
            if ($msg) $str .= "$msg<br>\n";
            if ($this->db) {
                $str .= $this->db->error . "<br>\n";
                $str .= "Errno: " . $this->db->errno . "<br>\n";
            }
            echo("<br>$str");
        }
        throw new Exception();
    }
    
    public function dbg_print($title) {
        if (!$this->debug) return;
        if ($title) {
            echo "$title";
            echo "<hr>\n";        
        }
        echo "<ul>\n";
            echo "<li>isLog(): ".($this->isLog()?'yes':'no')."</li>\n";
            echo "<li>isAdmin(): ".($this->isAdmin()?'yes':'no')."</li>\n";
            echo "<li>user:</li>";
            if ($this->user()) {
                echo "<ul>";            
                foreach ($this->user() as $key => $value) echo "<li>$key: $value</li>\n";
                echo "</ul>";
            }            
        echo "</ul>\n"; 
        
        if ($this->conf['use_session']) {        
            echo "<ul>\n";        
                echo "<li>SESSION: phplog_uuid = ".((isset($_SESSION['phplog_uuid']))?$_SESSION['phplog_uuid']:"not set")."</li>\n";                
                echo "<li>SESSION: phplog_clientIP = ".((isset($_SESSION['phplog_clientIP']))?$_SESSION['phplog_clientIP']:"not set")."</li>\n";                
                echo "<li>SESSION: phplog_clientUpdate = ".((isset($_SESSION['phplog_clientUpdate']))?$_SESSION['phplog_clientUpdate']:"not set")."</li>\n";                
            echo "</ul>\n";                
        }
        
        echo "current path: '".$this->curPath."'<br>\n";
        echo "phplog.conf config:";
        echo "<ul>";
            foreach ($this->conf as $key => $value) echo "<li>$key: $value</li>\n";
        echo "</ul>";
    }
    
    /* Read a conf file by checking several folders... */
    protected function getFileContent($fileName, $replace_arr = NULL)
    {
        $filePath = NULL;
        do {
            /* full path described ? */
            $filePath = $fileName;
            //echo("$filePath<br>\n");
            if (file_exists ($filePath)) break;            
            /* file from root path ? */
            $filePath = $GLOBALS['CONFIG']['root_path'].DIRECTORY_SEPARATOR.$fileName;
            //echo("$filePath<br>\n");
            if (file_exists ($filePath)) break;
            /* local file ? */
            $filePath = $this->curPath.DIRECTORY_SEPARATOR.$fileName;
            //echo("$filePath<br>\n");
            if (file_exists ($filePath)) break;
            /* stop there */
            die("$fileName missing");
        } while(0);

        // read file
        $string = file_get_contents($filePath);
        
        // %VAR% token replacement
        if ($replace_arr!=NULL)
            $string = str_replace(array_keys($replace_arr), array_values($replace_arr), $string);
        
        return $string;
    }
    
    /* PHPLog constructor */
    function __construct($debug=false)
    {
        $this->debug = $debug;        
        $this->user = NULL;        
        $this->curPath = realpath(dirname(__FILE__));
           
        /* user-configuration phplog.conf (json file) */
        $this->conf = json_decode($this->getFileContent('phplog.conf'), true);
        if (!$this->conf) die("phplog.conf JSON error");
        
        /* check setup configuration */
        if (!isset($GLOBALS['CONFIG']['sql_host']))   die("bad setup configuration, sql_host missing");
        if (!isset($GLOBALS['CONFIG']['sql_login']))  die("bad setup configuration, sql_login missing");
        if (!isset($GLOBALS['CONFIG']['sql_pw']))     die("bad setup configuration, sql_pw missing");
        if (!isset($GLOBALS['CONFIG']['sql_db']))     die("bad setup configuration, sql_db missing");
        if (!isset($GLOBALS['CONFIG']['user_table'])) die("bad setup configuration, user_table missing");
        
        /* db connection */
        if ($GLOBALS['CONFIG']['sql_isPW']) {
            $this->db = @mysqli_connect($GLOBALS['CONFIG']['sql_host'],
                                  $GLOBALS['CONFIG']['sql_login'],
                                  $GLOBALS['CONFIG']['sql_pw'],
                                  $GLOBALS['CONFIG']['sql_db']);
        } else {
            $this->db = @mysqli_connect($GLOBALS['CONFIG']['sql_host'],
                                  $GLOBALS['CONFIG']['sql_login'],
                                  NULL,
                                  $GLOBALS['CONFIG']['sql_db']); 
        }
        if (!$this->db) $this->dbError('Connection to \''.$GLOBALS['CONFIG']['sql_host'].'\' failed');
        
        /* session */
        if ($this->conf['use_session'])
        {
            /* check if a session is available */
            if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
                if(session_status() == PHP_SESSION_NONE) { die("You need to start a session before: 'session_start()'"); }
            } else {
                if(session_id() == '') { die("You need to start a session before: 'session_start()'"); }
            }
            
            /* need to clear session variables ? */
            if ((NULL!=$this->conf['session_timeout']) && (isset($_SESSION['phplog_clientUpdate'])))
            {
                $notSeenSince = (time()-$_SESSION['phplog_clientUpdate']);
                $timeOut = $this->conf['session_timeout'];
                
                if ($notSeenSince > $timeOut)
                {
                    $this->lastError = 'Session timeout';
                    if (isset($_SESSION['phplog_uuid']))         unset($_SESSION['phplog_uuid']);
                    if (isset($_SESSION['phplog_clientIP']))     unset($_SESSION['phplog_clientIP']);
                    if (isset($_SESSION['phplog_clientUpdate'])) unset($_SESSION['phplog_clientUpdate']);
                }
            }

            /* update session variable */
            $_SESSION['phplog_clientIP']     = $this->getClientIp();
            $_SESSION['phplog_clientUpdate'] = time(); 
            
            // automatically log if session is defined
            if (isset($_SESSION['phplog_uuid'])) {
                $this->setUser($_SESSION['phplog_uuid']);
            }
        }
    }
}


