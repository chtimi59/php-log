<?php
/*
NOTE about this file:

The goal here is to get something to test PHPLog.
This implies a standard project with it's proper setup (see: https://github.com/chtimi59/php-setup-pack)
setup.conf needs the following definition:
{
    "title": "test-phpLog",
    "features": {
        "db"    : true,
        "user"  : true,
        "admin" : true,
        "mail"  : true     
    }
}

and Root directory should contains the folowing index.php:

  <?php
   if(!@include("conf.php")) { echo "Setup missing"; die(); }
   include 'libs/PHPLog/utest.php';
  ?>

*/

date_default_timezone_set('America/Montreal');
session_start();
require 'libs/PHPLog/PHPLogAutoload.php';
$log = new PHPLogMailer(true);

define('ACTION_NONE',       0);
define('ACTION_LOGIN',      1);
define('ACTION_LOGOUT',     2);
define('ACTION_BADLOGIN',   3);
define('ACTION_ADDUSER',    4);
define('ACTION_UPDATEUSER', 5);

define('ACTION_ADDCANDIDATE',   6);
define('ACTION_VERIFCANDIDATE', 7);
define('ACTION_DELETECANDIDATE',8);
define('ACTION_FORGETPW'       ,9);
?>

<html>
<body>

<?php
$log->dbg_print();
echo "<hr>\n";

if (!isset($_GET['action'])) $_GET['action'] = ACTION_NONE;
if ( isset($_GET['verif'] )) $_GET['action'] = ACTION_VERIFCANDIDATE;
switch($_GET['action'])
{
    case ACTION_NONE:
        break;
        
    case ACTION_LOGIN:
        echo '<h1>Login</h1>';
        if (!$log->logIn('admin@somewhere.com','1234'))
            echo $log->lastError;
        break;
        
    case ACTION_LOGOUT: 
        echo '<h1>Logout</h1>';
        if (!$log->logOut())
            echo $log->lastError;
        break;
        
    case ACTION_BADLOGIN:
        echo '<h1>Bad Login</h1>';
        $user = 'sd9BF0EF02-B63D-40D4-8173-275BA741847C';
        $user = NULL;
        if (!$log->logIn($user))
            echo $log->lastError;
        break;
        
    case ACTION_ADDUSER:
        echo '<h1>Add User</h1>';
        $user = array(
            'EMAIL' => 'jdorgeville@spiria.com',
            'PASSWORD' => 'toto',
            'PRIVILEGE' => 4,
            'FIRSTNAME' => 'Alfred',
        );
        if (!$log->addUser($user))
            echo $log->lastError;
        break;
        
    case ACTION_UPDATEUSER:
        echo '<h1>Update User</h1>';
        $user = array(
            'UUID' => 'sadsadsa',
            'EMAIL' => 'admin@somewhere.com',
            'PRIVILEGE' => 4,
            'FIRSTNAME' => 'Mathieu',
        );
        if (!$log->updateUser($GLOBALS['CONFIG']['admin_uuid'],'1234',$user))
            echo $log->lastError;
        break;
        
    case ACTION_ADDCANDIDATE:
        echo '<h1>Add Candidate</h1>';
        if (!$log->addCandidate('jdorgeville@spiria.com','fr'))
            echo $log->lastError;
        break;
        
    case ACTION_VERIFCANDIDATE:
        echo '<h1>Verif Candidate</h1>';
        $data = $log->getCandidate($_GET['verif']);
        if (!$data) {
            echo $log->lastError;
        } else {
            echo($data['EMAIL']." OK!");
        }
        if (!$log->deleteCandidate($_GET['verif']))
            echo $log->lastError;
        break;
        
    case ACTION_DELETECANDIDATE:
        echo '<h1>Delete Candidate</h1>';
        $data = $log->deleteCandidate('EE3E9ECE-6E8B-4C94-946C-D13187BBCE0F');
        if (!$data) {
            echo $log->lastError;
        }
        break;
        
    case ACTION_FORGETPW:
        echo '<h1>Forget Password</h1>';
        $data = $log->forgetPassword('jdorgeville@spiria.com');
        if (!$data) {
            echo $log->lastError;
        }
        break;        
}
$log->dbg_print();
?>

<br>
<div style='margin:20px;'>
<a href="index.php?action=<?php echo ACTION_NONE; ?>">ACTION_NONE</a><br>
<a href="index.php?action=<?php echo ACTION_LOGIN; ?>">ACTION_LOGIN</a><br>
<a href="index.php?action=<?php echo ACTION_BADLOGIN; ?>">ACTION_BADLOGIN</a><br>
<a href="index.php?action=<?php echo ACTION_LOGOUT; ?>">ACTION_LOGOUT</a><br>
<a href="index.php?action=<?php echo ACTION_ADDUSER; ?>">ACTION_ADDUSER</a><br>
<a href="index.php?action=<?php echo ACTION_UPDATEUSER; ?>">ACTION_UPDATEUSER</a><br>
<br>
<a href="index.php?action=<?php echo ACTION_ADDCANDIDATE; ?>">ACTION_ADDCANDIDATE</a><br>
<a href="index.php?action=<?php echo ACTION_DELETECANDIDATE; ?>">ACTION_DELETECANDIDATE</a><br>
<br>
<a href="index.php?action=<?php echo ACTION_FORGETPW; ?>">ACTION_FORGETPW</a><br>
<br>

</div>
</body>
</html>