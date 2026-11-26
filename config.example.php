<?php
// Copy this file to config.php and fill in your own values. Never put real passwords in this example file.
// ---------------------------------------------------------------
//  VidyaSetu – configuration (edit this file, no code change needed)
// ---------------------------------------------------------------
return [
    'app_name'   => 'VidyaSetu',
    'tagline'    => 'One portal for attendance, homework & parent communication',
    'base_url'   => 'http://localhost:8000',
    'timezone'   => 'Asia/Kolkata',
    'admin_email'=> 'support@vidyasetu.test',   // receives "Contact us" messages
    'demo_mode'  => true,                         // shows demo logins on the login page
    'demo_password' => '',                        // password of the sample accounts; empty = a random one (data/demo_password.txt)

    // Database: 'sqlite' (default, zero setup, file in data/) or 'mysql' (MySQL 8 / MariaDB 10.5+).
    // For mysql: import database/vidyasetu_mysql.sql (+ optional vidyasetu_demo_data.sql) once, then fill these in.
    'db' => [
        'driver' => 'sqlite',
        'host' => '127.0.0.1', 'port' => 3306, 'name' => 'vidyasetu', 'user' => 'root', 'pass' => '',
    ],

    // Minimum attendance % (Indian schools commonly use 75%)
    'min_attendance' => 75,

    // 'period' = one mail per absent period, 'daily' = one mail per student per day
    'absence_alert_mode' => 'period',

    // Period timings (Mon–Sat, up to 8 periods)
    'periods' => [
        1 => ['08:00', '08:45'], 2 => ['08:45', '09:30'], 3 => ['09:30', '10:15'],
        4 => ['10:30', '11:15'], 5 => ['11:15', '12:00'], 6 => ['12:00', '12:45'],
        7 => ['13:15', '14:00'], 8 => ['14:00', '14:45'],
    ],

    // Daily database backup into data/backups (download from Super Admin > Backup).
    'auto_backup' => true,

    // SMS / WhatsApp alerts (optional, usually paid). Fill in with the HTTP API details your provider gives you.
    // {phone} (91XXXXXXXXXX) and {message} are replaced. In India SMS needs DLT-registered sender and templates,
    // and WhatsApp needs approved message templates. While 'enabled' is false, messages are only logged.
    'sms' => [
        'enabled' => false, 'method' => 'POST', 'url' => '',
        'headers' => [],                       // e.g. ['Authorization: Bearer YOUR-KEY', 'Content-Type: application/json']
        'body' => 'mobile={phone}&message={message}',
    ],
    'whatsapp' => [
        'enabled' => false, 'method' => 'POST', 'url' => '',
        'headers' => [],
        'body' => 'mobile={phone}&message={message}',
    ],

    // SMTP – leave host empty for demo mode: every e-mail is stored in the
    // "Email Log" (visible in dashboards) and NOTHING IS DELIVERED.
    // Fill this in to really send welcome mails, passwords and absence alerts:
    //   Hostinger : 'host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl',
    //               'user' => and 'from' => the same mailbox you created in hPanel, 'pass' => its password
    //   Gmail     : 'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls',
    //               'user' => and 'from' => your Gmail, 'pass' => a Google "App password" (not your normal password)
    // Then test it from Super Admin > Email log > "Send test".
    // NOTE: needs the PHP openssl extension (run.bat already enables it).
    'smtp' => [
        'host' => '', 'port' => 587, 'secure' => 'tls',
        'user' => '', 'pass' => '',
        'from' => 'noreply@vidyasetu.test', 'from_name' => 'VidyaSetu',
    ],
];
