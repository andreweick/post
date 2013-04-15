<?php

/*****************************************************************
 Troubleshooting note: when working on new scripts, append

  ?debug=on or ?debug=verbose

 to the form action address. The "verbose" setting will cause PHP
 to output notices as well as warnings and errors.

 Also, use the setDebugParams function (below) to alter any $params
 elements for debugging purposes. This function will only be run
 when $_GET['debug'] is set.
*****************************************************************/


function setDebugParams() {

  /* override any parameters just before sending the e-mail */
  /* only runs if $_GET['debug'] is set. */

  global $params;
  $params['to'] = 'jonathan@brightbit.com';
}


if (array_key_exists('debug', $_COOKIE))
  $debug_mode = $_COOKIE['debug'];
elseif (array_key_exists('debug', $_GET))
  $debug_mode = $_GET['debug'];
else
  $debug_mode = false;

if ($debug_mode) {
  error_reporting(
    $debug_mode == 'verbose' ? E_ALL : E_WARNING
  );
}

$script = preg_replace( //generic version of the url (strip meaningless subdomains)
  '/^https?:\/\/(www\.|local\.|dev\.)?|\/$|\/?\?.+$/',
  '', $_SERVER['HTTP_REFERER']
);

$script_query_str = preg_replace('/^[^\?]+\?/', '', $_SERVER['HTTP_REFERER']);
if ($script_query_str) {
  $script_params_pairs = split('&', $script_query_str);
  $script_params = array();
  foreach($script_params_pairs as $pair) {
    list($key, $val) = split('=', $pair);
    if ($key && $val) {
      $script_params[$key] = $val;
    }
  }
}

$full_domain = preg_replace('%^https?://|/.*$%', '', $_SERVER['HTTP_REFERER']);

$domain = preg_replace('/^(local\.|new\.|www\.|dev\.)/', '', $full_domain);
$domain = preg_replace('{\.dev/}', '.com', $domain);

$all_params = array (
  "missionfocus.com/contact" => array(
    'from' => $_POST['email'],
    'to' => 'andrew.eick@missionfocus.com',
    'subject' => 'Posted at missionfocus.com',
    'goto' => "http://$full_domain/contact/success",
    'dojsspamcheck' => true
  ),
  'google_translate' => array(
    'from' => "website@missionfocus.com",
    'to' => 'webmaster@missionfocus.com',
    'subject' => "Form post via Google translate (or cache)" .
      (array_key_exists('Subject', $_POST) ? ': '.$_POST['Subject'] : ''),
    'goto' => "http://www.missionfocus.com/",
    'dojsspamcheck' => false
  ),
  'spam' => array(
    'from' => "website@$domain",
    'to' => "andrew.eick@missionfocus.com",
    'subject' => "apparently spam from $full_domain",
    'goto' => "http://$full_domain/"
  ),
  "default" => array(
    'from' => "website@$domain",
    'to' => "andrew.eick@missionfocus.com",
    'subject' => "contact sent from ".$_SERVER['HTTP_REFERER'],
    'goto' => "http://$full_domain/contact/success/",
    'dojsspamcheck' => false
  )
);

if (array_key_exists($script, $all_params))
  $params = $all_params[$script];
elseif (preg_match('/64.233.[0-9]+.[0-9]+|209.85.[0-9]+.[0-9]+/', $domain))
  $params = $all_params['google_translate'];
else
  $params = $all_params['default'];

if (
  strpos($params['from'], "\r") > 0 ||           // Check for extra mail headers snuck into from field
  $domain == '' ||                               // Check for empty referring host
  ($params['dojsspamcheck'] && !$_POST['valid']) // Check that the user agent supported Javascript
) {
  $params = $all_params['spam'];
}

if (isset($params['message'])) {
  $message = $params['message'];
} else {
  function makeReadable($name) { return preg_replace('/_|-/',' ',$name); }

  $line_sep="\r\n";
  $message = '';
  $dashes = '----------------------------------------------';
  foreach($_POST as $key => $val) {
    if ($key == 'Subject') {
      $params['subject'] = $val . ' [' . $params['subject'] . ']';
    } elseif (strtolower($val) == 'sectionhead') {
      $message .= $line_sep . $dashes . $line_sep . $key . $line_sep . $dashes . $line_sep;
    } elseif ($key != 'Submit' && $key != 'PHPSESSID' && $key != 'Message' && $key != 'valid' && $val > " ") {
      $message .=  makeReadable($key) . ": $val $line_sep";
    }
  }
  if ($_POST['Message']) {
    $message .= $line_sep.str_replace('\\','',$_POST['Message']).$line_sep;
  }
}

$headers = '';
foreach($params as $key => $val) {
  if (preg_match('/^(from|cc|bcc|reply-to)$/', $key)) {
    $headers .= "\r\n$key: $val";
  }
}
$headers .= "\r\nX-Submitted-From: {$_SERVER['HTTP_REFERER']}";
$headers .= "\r\nX-User-Agent: {$_SERVER['HTTP_USER_AGENT']}";

if ($debug_mode) setDebugParams();

if ($message > ' ') {
  mail ($params['to'], $params['subject'], $message, $headers);
}

header("Location: {$params['goto']}");
