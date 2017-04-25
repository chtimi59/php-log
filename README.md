# php-log
A set of functions to easily manage user/log in your PHP projects

# Motivation
It's quite common to have to deal with user accounts (password, session, emails).
So here we try to do something re-usable

in short, you can use it like that

```php
<?php
date_default_timezone_set('America/Montreal');
session_start();
require 'libs/PHPLog/PHPLogAutoload.php';
$log = new PHPLogMailer();

[...]

```
# API provided

Basics functions:
```php
$log->logIn('admin@somewhere.com','1234'); // log in with id**, password
$log->logOut();  // log out
$log->isLog();   // am I logged ?
$log->isAdmin(); // am I Admin ?
$log->user();    // get current User array

$log->addUser($user); // create a new user
/* note:
$user['PASSWORD'] and $user['EMAIL'] are mandatory to create a new user
other fields depends on your 'users.sql' file */

$log->updateUser($uuid, $password, $user); // change account setting

```
note log id depend on your phplog.conf

Email oriended functions:
```php
$log->addCandidate($email, $lang='en'); // create an email candidate, send the email
$log->getCandidate($uuid);              // get email candidate created
$log->deleteCandidate($uuid);           // delete an email candiate

$log->forgetPassword($email, $lang='en'); // reset password, and send an email with it
$log->sendEmail($htmlFile, $replace=NULL); // send an email to user
```

# Setup

This git repo, should actually be used as a git's submodule in your project.

Dependency:
- https://github.com/chtimi59/php-setup-pack
- https://github.com/PHPMailer/PHPMailer

The following arborescence tree is expected:

```
project_dir\
   libs\PHPLog    - this submodule
   libs\PHPMailer - PHPMailer submodule   
   setup\         - php-setup-pack submodule
   
   phplog.conf    - phplog configuration file
   
   setup.conf     - php-setup-pack configuration file
   setup.sql      - php-setup-pack database script
   users.sql      - php-setup-pack database script

```

Hence, to add this submodule and its dependency, write:
```
git submodule add git@github.com:chtimi59/php-log.git libs/PHPMailer
git submodule add git@github.com:PHPMailer/PHPMailer.git libs/PHPMailer
git submodule add git@github.com:chtimi59/php-setup-pack.git setup
```

Once, it's done, create a project_dir/*phplog.conf* according your needs:

```json
{
    "use_session": true,
    "session_timeout": 180,
    "use_passwords": true,
    "login_with": "EMAIL",
    "email_template_folder": './templates/'    
}   
```
Note: if not present, project_dir/setup/*phplog.conf* default will be used instead

in setup.conf you actually needs to following items:
```json
"db"    : true,
"user"  : true,
"admin" : true,
"mail"  : true
```

see https://github.com/chtimi59/php-setup-pack for more details.

That's means users.sql can be updated with your needs. Hence you can add extra fields to define a user, such as
- FIRSTNAME
- LASTNAME
- BIRTHDAY
- etc. ...

by default user are defined by the following mandatory fields:
- UUID
- EMAIL
- PASSWORD (md5 hased)
- CREATION_DATE
- PRIVILEGE (1 for admin, other values meaning are up to you)
- LAST_CONNECTION (last time a connection has been made)






