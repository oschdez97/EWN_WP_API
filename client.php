<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

// Load environment variables from .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class WPClient
{
    private $wpHost;
    private $wpApiCredentials;
    
    public function __construct() {
        $this->wpHost = $_ENV['WP_HOST'];
        $this->wpApiCredentials = [
            "username" => $_ENV['WP_USER'], 
            "password" => $_ENV['WP_APP_PASSWORD']
        ];
    }
    
    private function create_media_file($media_file_path) 
    {
        if(file_exists($media_file_path)) 
        {
            // Get image dimensions and MIME type
            list($width, $height) = getimagesize($media_file_path);
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $media_file_path);
            finfo_close($file_info);
    
            $client = new Client();
    
            $headers = [
                'Content-Disposition' => 'attachment; filename="'.basename($media_file_path).'"',
                'Content-Type' => $mime_type,
            ];
            $body = file_get_contents($media_file_path);
    
            $options = [
                'auth' => array($this->wpApiCredentials["username"], $this->wpApiCredentials["password"]),
                'headers' => $headers,
                'body' => $body
            ];
            $response = $client->request('POST', $this->wpHost."/media", $options);
            
            if($response->getStatusCode() === 201) {
                $responseBody = $response->getBody()->getContents();
                $responseData = json_decode($responseBody, true);
                return $responseData;
            }

        }
        return null;
    }

    private function _get($endpoint) 
    {
        $client = new Client();

        $options = [
            'auth' => array($this->wpApiCredentials["username"], $this->wpApiCredentials["password"]),
        ];
        
        try {
            $response = $client->request('GET', $this->wpHost.$endpoint, $options);
            
            if($response->getStatusCode() === 200) {
                $responseBody = $response->getBody()->getContents();
                $responseData = json_decode($responseBody, true);
                return $responseData;
            }
        } catch (Exception $e) {
            $now = date('Y-m-d H:i:s');
            file_put_contents(__DIR__ . "/output.log", "[$now]: " . $e->getMessage() . "\n\n");
        }
        return null;
    }

    private function _getById($endpoint, $id) 
    {
        return $this->_get($endpoint."/$id");
    }

    private function _create($endpoint, $dataBody)
    {
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        $options = [
            'auth' => array($this->wpApiCredentials["username"], $this->wpApiCredentials["password"]),
            'headers' => $headers,
            'body' => json_encode($dataBody)
        ];
        
        try {
            $response = $client->request('POST', $this->wpHost.$endpoint, $options);
            
            if(in_array($response->getStatusCode(), [200, 201])) {
                $responseBody = $response->getBody()->getContents();
                $responseData = json_decode($responseBody, true);
                return $responseData;
            }
        } catch (Exception $e) {
            $now = date('Y-m-d H:i:s');
            file_put_contents(__DIR__ . "/output.log", "[$now]: " . $e->getMessage() . "\n\n");
        }
        return null;
    }

    private function _update($endpoint, $id, $dataBody) 
    {
        $elem = $this->_get("/$endpoint/$id");
        if($elem == null) return;
        
        return $this->_create("/$endpoint/$id", $dataBody);
    }

    private function _get_postListOf($postData, $target) 
    {
        $res = [];
        
        if(in_array($target, ["categories", "tags"]) && isset($postData[$target]) && !empty($postData[$target])) 
        {
            $existing_ones = $this->_get("/$target");

            // Try to map with an existing one
            foreach($postData[$target] as $elem1) 
            {
                $match_found = 0;
                foreach($existing_ones as $elem2) 
                {
                    if(mb_strtolower($elem1) == mb_strtolower($elem2["name"])) 
                    {
                        $match_found = 1;
                        if(!in_array($elem2["id"], $res)) 
                        {
                            $res[] = $elem2["id"];
                        }
                        break;
                    }
                }
                
                if(!$match_found) 
                {
                    // Create new
                    $name = ucfirst(mb_strtolower($elem1));

                    $newObj = $this->_create("/$target", [
                        "name" => $name,
                        "description" => "",
                        "slug" => make_url_friendly($name)
                    ]);
                    
                    if($newObj != null) 
                    {
                        $res[] = $newObj["id"];
                    }
                }
            }
        }
        return $res;
    }
    
    public function createPost($postData)  
    {
        // handle featured image
        $featured_img_id = 0;
        if(isset($postData["featured_img"]) && !empty($postData["featured_img"])) 
        {
            $img = $postData["featured_img"];
            $caption = strlen($img["caption"]) ? $img["caption"] : "";
            $description = strlen($img["description"]) ? $img["description"] : "";
            $alt_text = strlen($img["alt_text"]) ? $img["alt_text"] : "";
            $media_file_path = $img["filename"];
            
            $resp = $this->create_media_file(__DIR__."/media/$media_file_path");

            if($resp != null) 
            {
                $featured_img_id = $resp["id"];
                $resp = $this->_create("/media/$featured_img_id", [
                    "caption" => $caption,
                    "description" => $description,
                    "alt_text" => $alt_text,
                ]);
            }
        }

        // handle categories and tags
        $categories_list = $this->_get_postListOf($postData, "categories");
        $tags_list = $this->_get_postListOf($postData, "tags");

        // create post
        $postBody = [
            "status" => "publish",
            "author" => 1,
            "title" => $postData["title"],
            "content" => $postData["content"],
            "categories" => $categories_list,
            "tags" => $tags_list,
            "featured_media" => $featured_img_id,
        ];
        
        $response = $this->_create("/posts", $postBody);
        return $response;
    }
}

?>