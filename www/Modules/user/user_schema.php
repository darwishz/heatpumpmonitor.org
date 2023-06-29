<?php

$schema['users'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'username' => array('type' => 'varchar(30)'),
    'email' => array('type' => 'varchar(64)'),
    // 'password' => array('type' => 'varchar(64)'),
    // 'salt' => array('type' => 'varchar(32)'),
    // 'lastlogin' => array('type' => 'datetime'),
    // 'admin' => array('type' => 'int(11)', 'Null'=>false),

    // User profile fields
    // 'gravatar' => array('type' => 'varchar(30)', 'default'=>''),
    // 'name'=>array('type'=>'varchar(30)', 'default'=>''),
    // 'location'=>array('type'=>'varchar(30)', 'default'=>''),
    // 'timezone' => array('type'=>'varchar(64)', 'default'=>'UTC'),
    // 'language' => array('type' => 'varchar(5)', 'default'=>'en_EN'),
    // 'bio' => array('type' => 'text'),

    // 'tags' => array('type' => 'text'),
    // 'startingpage' => array('type'=>'varchar(64)', 'default'=>'feed/list'),
    // 'email_verified' => array('type' => 'int(11)', 'default'=>0),
    // 'verification_key' => array('type' => 'varchar(64)', 'default'=>'')
);

$schema['emoncmsorg_link'] = array(
    'userid' => array('type' => 'int(11)'),
    'emoncmsorg_userid' => array('type' => 'int(11)'),
    'emoncmsorg_apikey_write' => array('type' => 'varchar(64)'),
    'emoncmsorg_apikey_read' => array('type' => 'varchar(64)')
);