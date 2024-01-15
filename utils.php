<?php

date_default_timezone_set('Europe/Madrid');
setlocale(LC_TIME, 'es_ES.utf8', 'es_ES', 'es');

function make_url_friendly($url) 
{
    $url = strtolower($url);
    $find = array(' ',
                '&',
                '\r\n',
                '\n',
                '+');
    $url = str_replace ($find, '-', $url);
    $find = array('/[^a-z0-9\-<>]/',
                '/[\-]+/',
                '/<[^>]*>/');
    $repl = array('',
                '-',
                '');
    $url =  preg_replace ($find, $repl, $url);
    return $url;
}

?>