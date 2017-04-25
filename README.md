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

$log->addUser($user, $lang='en'); // create a new user
/* note: $user['PASSWORD'] and $user['EMAIL'] are mandatory to create a new user
other fields depends on your 'users.sql' file */

$log->deleteUser($lang='en', $idenfication=NULL, $password=NULL); // delete a user
/* note: if no idenfication is provided, then delete current logged user */

$log->updateUser($user, $lang='en', $idenfication=NULL, $password=NULL); // change account setting
/* note1: if no idenfication is provided, then update current logged user */

$log->forgetPassword($email, $lang='en'); // reset password, and send an email with it

$log->sendEmail($templateName, $lang='en', $replace_arr=NULL, $email=NULL) // send an email, by using a template
/* note1: by default the mail is sent to the current logged user. */
/* note2: sendEmail use template file, which are HTMLs files which may have %var% replace variables in it */
```
note log id** depend on your *phplog.conf*

Extra email-oriended functions:
```php
$log->addCandidate($email, $lang='en'); // create an email-candidate, send the email
$log->getCandidate($uuid);              // get email-candidate created
$log->deleteCandidate($uuid);           // delete an email-candiate
```

# Setup

This git repo, should actually be used as a git's submodule in your project.

Dependency:
- https://github.com/chtimi59/php-setup-pack
- https://github.com/PHPMailer/PHPMailer

The following arborescence tree is expected in your project:

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

Hence, to add this submodule (and its dependency), write:
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
    "email_template_folder": "./templates/"
}   
```
Note: if not present, project_dir/libs/PHPLog/*phplog.conf* default will be used instead

in *setup.conf* you actually needs the following items:
```json
"db"    : true,
"user"  : true,
"admin" : true,
"mail"  : true
```

see https://github.com/chtimi59/php-setup-pack for more details.

That's means users.sql can be updated according your needs. Hence you can add extra fields to define a user, such as
- FIRSTNAME
- LASTNAME
- BIRTHDAY
- etc. ...

For your information, by default user are defined with the following mandatory fields:
- UUID (Unique User IDentifier)
- EMAIL (mandatory and should be valid, that how we assume that the user exist)
- PASSWORD (md5 hased)
- CREATION_DATE (used for information)
- PRIVILEGE (0=Default, 1=Admin, other values meaning are up to you)
- LAST_CONNECTION (last time a connection has been made)
- LAST_IP (last IP address)

# Note about email template
By default the following template are provided:
```
en.add.html
en.candidate.html
en.delete.html
en.forget.html
en.sample.html
en.update.html
fr.add.html
fr.candidate.html
fr.delete.html
fr.forget.html
fr.sample.html
fr.update.html
```

by default the following %VAR% can be used

**Specifics %VAR%**
- %BASE_URL% : base url ex:http://localhost/test-phplog 
- %AUTH_KEY% : used in $log->addCandidate() for mail validation
- %NEW_PASSWORD% : used for password reset

**Users Mandatories %VAR%**
- %UUID% : user uuid
- %PASSWORD% : MD5 hashed password
- %CREATION_DATE% : like 2017-04-25 18:01:51
- %PRIVILEGE% : 0 by default, 1 for admin
- %LAST_CONNECTION% : like 2017-04-25 18:14:23
- %LAST_IP% : ip address

**Users Custom %VAR%**
- %FIRST_NAME% : first name
- ...







