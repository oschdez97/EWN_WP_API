<?php

require_once __DIR__ . "/client.php";

$WPClient = new WPClient();

$posts = json_decode(file_get_contents(__DIR__ . "/tests/posts.json"), true);
foreach($posts as $post) 
{
    $response = $WPClient->createPost($post);
}


?>