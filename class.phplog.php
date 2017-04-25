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
    public function isLog() {
        return (NULL != $this->user);
    }
    
    /* returns True if admin */
    public function isAdmin() {
        if (NULL == $this->user);
        return ($this->user['PRIVILEGE']==self::PRIVILEGE_ADMIN);
    }
    
    /* user log out */
    public function logOut()
    { 
        $this->unSetUser();
    }
    
    /* user log in */
    public function logIn($login, $password=NULL)
    {        
        $sql  = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` ';
        $sql .= 'WHERE '.$this->conf['login_with'].'="'.$login.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->logOut();
            $this->lastError = 'Unknown User';
            return false; 
        } 
        
        if ($this->conf['use_passwords'])
        {
            if ($data['PASSWORD']!=md5($password)) {
                $this->logOut();
                $this->lastError = 'Invalid Password';
                return false; 
            }
        }
        return $this->setUser($data['UUID']);
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
        
        $user['UUID']     = $this->guid();        
        $user['PASSWORD'] = md5($user['PASSWORD']); // Hash it
        $user['CREATION_DATE'] = date('Y-m-d H:i:s',time());
        $user['LAST_CONNECTION'] = date('Y-m-d H:i:s',time());
        
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
    
    public function updateUser($uuid, $password, $user)
    {
        // check uuid/password
        $sql  = "SELECT * FROM `".$GLOBALS['CONFIG']['user_table']."` WHERE UUID='$uuid'";
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->lastError = 'Unknown User';
            return false; 
        } 
        
        if ($this->conf['use_passwords'])
        {
            if ($data['PASSWORD']!=md5($password)) {
                $this->lastError = 'Invalid Password';
                return false; 
            }
        }
        
        // protect some fields
        if (isset($user['UUID'])) unset($user['UUID']);
        if (isset($user['CREATION_DATE'])) unset($user['CREATION_DATE']);
        if (isset($user['LAST_CONNECTION'])) unset($user['LAST_CONNECTION']);

        // update USER!        
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

        return true; 
    }
    // UPDATE `user` SET `FIRSTNAME` = 'Mathieu!' WHERE `UUID` = '9BF0EF02-B63D-40D4-8173-275BA741847C'
    
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
        	
        $sql = 'SELECT * FROM `'.$GLOBALS['CONFIG']['user_table'].'` WHERE UUID="'.$uuid.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);
        $data = $req->fetch_assoc();
        if (!$data) {
            $this->logOut();
            $this->lastError = 'Unknown User';
            return false;
        }
            
        /* update connection date */
        $sql = 'UPDATE `'.$GLOBALS['CONFIG']['user_table'].'` SET `LAST_CONNECTION`=now() WHERE UUID="'.$uuid.'"';
        $req = $this->db->query($sql);
        if (!$req) $this->dbError($sql);

        $this->user = $data;		
        
        if ($this->conf['use_session']) {
            $_SESSION['phplog_uuid'] = $data['UUID'];
        }        
        return true;
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
    function guid(){
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
    
    public function dbg_print() {
        if (!$this->debug) return;
        echo "<hr>\n";        
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
                echo "<li>SESSION: phplog_uuid = ".((isset($_SESSION['phplog_uuid']))?$_SESSION['phplog_authkey']:"not set")."</li>\n";                
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
        $path1 = $this->curPath.DIRECTORY_SEPARATOR;
        $path2 = realpath($path1.'..').DIRECTORY_SEPARATOR;
        $path3 = realpath($path2.'..').DIRECTORY_SEPARATOR;
                
        if (true)                     { $filePath = $path3.$fileName; }
        if (!file_exists ($filePath)) { $filePath = $path2.$fileName; }
        if (!file_exists ($filePath)) { $filePath = $path1.$fileName; }
        if (!file_exists ($filePath)) die("$fileName missing");
        
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
                    if (isset($_SESSION['phplog_uuid']))      unset($_SESSION['phplog_uuid']);
                    if (isset($_SESSION['phplog_clientIP']))     unset($_SESSION['phplog_clientIP']);
                    if (isset($_SESSION['phplog_clientUpdate'])) unset($_SESSION['phplog_clientUpdate']);
                }
            }

            /* update session variable */
            $_SESSION['phplog_clientIP']     = $_SERVER['REMOTE_ADDR'];
            $_SESSION['phplog_clientUpdate'] = time(); 
            
            // automatically log if session is defined
            if (isset($_SESSION['phplog_uuid'])) {
                $this->setUser($_SESSION['phplog_uuid']);
            }
        }
    }
}


