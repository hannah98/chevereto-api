<?php

/* --------------------------------------------------------------------

  Chevereto
  http://chevereto.com/

  @author       Rodolfo Berrios A. <http://rodolfoberrios.com/>
                        <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  http://chevereto.com/license

  --------------------------------------------------------------------- */

  /* API v1 : PLEASE NOTE

         This API v1 is currently just a bridge to port to Chevereto 3 the API from Chevereto 2.
         From now on Chevereto 2 API will be named API v1

         In future releases there will be an API v2 which will add methods like create user, create albums, etc.

  */

  /* An override of the Chevereto API by hannah98 <https://github.com/hannah98/chevereto-api>.

     It can take a source URL of a reddit post or imgur URL and determine the URL of the
     photo to be downloaded.

     It can also take an optional user= parameter in the API request to set the user who will
     own the photo.

     If this file should be placed in the app/routes/overrides folder of your Chevereto install.

     If this file is named route.api.php it will override the default api route.

     You can also choose to name this route differently (route.myapi.php for example) but then you
     will need to allow your new route access without authentication.  This is done in the
     app/loader.php file, under the website_privacy_mode setting.  You will need to add your new
     route name into the $allowed_requests array.
  */

$route = function($handler) {
        try {
                $version = $handler->request[0];
                $action = $handler->request[1];
        $user = $_REQUEST['user'];
        if (is_null($user)) {
            $user = 'nouser';
        }

        SendLog("Starting API upload");

                if(is_null(CHV\getSetting('api_v1_key')) or CHV\getSetting('api_v1_key') == '') {
                        throw new Exception("API v1 key can't be null. Go to your dashboard and set the API v1 key.", 0);
                }

                // Change CHV\getSetting('api_v1_key') to 'something' if you want to use 'something' as key
                if(!G\timing_safe_compare(CHV\getSetting('api_v1_key'), $_REQUEST['key'])) {
                        throw new Exception("Invalid API v1 key.", 100);
                }

                if(!in_array($version, [1])) {
                        throw new Exception('Invalid API version.', 110);
                }

                $version_to_actions = [
                        1 => ['upload']
                ];

                if(!in_array($action, $version_to_actions[$version])) {
                        throw new Exception('Invalid API action.', 120);
                }

                // API V1 upload
                $source = isset($_FILES['source']) ? $_FILES['source'] : $_REQUEST['source'];

                if(is_null($source)) {
                        throw new Exception('Empty upload source.', 130);
                }

                if($_FILES['source']['tmp_name']) { // File?
                        $source = $_FILES['source'];
                        $type = 'file';
                } else {
                        if(!G\is_image_url($source) && !G\is_url($source)) {

                                // Base64 comes from POST?
                                if($_SERVER['REQUEST_METHOD'] !== 'POST') {
                                        throw new Exception('Upload using base64 source must be done using POST method.', 130);
                                }

                                // Fix the $source base64 string
                                $source = trim(preg_replace('/\s+/', '', $source));

                                // From _GET source should be urlencoded base64
                                if(!G\timing_safe_compare(base64_encode(base64_decode($source)), $source)){
                                        throw new Exception('Invalid base64 string.', 120);
                                }

                                // Set the API temp file
                                $api_temp_file = @tempnam(sys_get_temp_dir(), 'chvtemp');

                                if(!$api_temp_file or !@is_writable($api_temp_file)) {
                                        throw new UploadException("Can't get a tempnam.", 200);
                                }

                                $fh = fopen($api_temp_file, 'w');
                                stream_filter_append($fh, 'convert.base64-decode', STREAM_FILTER_WRITE);
                                if(!@fwrite($fh, $source)) {
                                        throw new Exception('Invalid base64 string.', 130);
                                } else {
                                        // Since all the validations works with $_FILES, we're going to emulate it.
                                        $source = array(
                                                'name'          => G\random_string(12).'.jpg',
                                                'type'          => 'image/jpeg',
                                                'tmp_name'      => $api_temp_file,
                                                'error'         => 'UPLOAD_ERR_OK',
                                                'size'          => '1'
                                        );
                                }
                                fclose($fh);
                        }
            else {
                // If it is an image URL, parse out media URL from reddit posts, if applicable
                SendLog("Old URL: $source");
                $source = ProcessImageURL($source);
                SendLog("New URL: $source");
            }
                }

                // CHV\Image::uploadToWebsite($source, 'username', [params]) to inject API uploads to a given username
        $uploaded_id = CHV\Image::uploadToWebsite($source, $user);
                $json_array['status_code'] = 200;
                $json_array['success'] = array('message' => 'image uploaded', 'code' => 200);
                $json_array['image'] = CHV\Image::formatArray(CHV\Image::getSingle($uploaded_id, false, false), true);

                if($version == 1) {
                        switch($_REQUEST['format']) {
                                default:
                                case 'json':
                                        G\Render\json_output($json_array);
                                break;
                                case 'txt':
                                        echo $json_array['image']['url'];
                                break;
                                case 'redirect':
                                        if($json_array['status_code'] == 200) {
                                                $redirect_url = $json_array['image']['url_viewer'];
                                                header("Location: $redirect_url");
                                        } else {
                                                die($json_array['status_code']);
                                        }
                                break;
                        }
                        die();
                } else {
                        G\Render\json_output($json_array);
                }

        } catch(Exception $e) {
                $json_array = G\json_error($e);
                if($version == 1) {
                        switch($_REQUEST['format']) {
                                default:
                                case 'json':
                                        G\Render\json_output($json_array);
                                        break;
                                case 'txt':
                                case 'redirect':
                                        die($json_array['error']['message']);
                                        break;
                        }
                } else {
                        G\Render\json_output($json_array);
                }

        }
};

function ProcessImageURL($url) {
    if (preg_match("/reddit\.com/i", $url)) {
        $url = FindRedditURL($url);
    }
    if (preg_match("/imgur\.com/i", $url)) {
        $url = FindImgurURL($url);
    }
    if (preg_match("/gfycat\.com/i", $url)) {
        $url = FindGfycatURL($url);
    }
    // Chevereto has a problem with the gifv extension, so as a workaround
    // converting gifv in URL to gif
    $url = preg_replace('/\.gifv$/', '.gif', $url);
    return $url;
}

function FindRedditURL($url) {
    $url = preg_replace('@/[^/]*$@','/', $url);
    $url = preg_replace('/m\.reddit\.com/', 'reddit.com', $url);
    $jsonURL = "$url.json?limit=1";
    $json_array = json_decode(G\fetch_url($jsonURL), TRUE);
    $media_obj = SeekRedditMedia($json_array);
    return $media_obj['media_url'];
}

function SeekRedditMedia($data, $return_data = NULL) {
    if (!is_null($return_data) and array_key_exists('media_url', $return_data)) {
        return $return_data;
    }
    foreach($data as $item) {
        if (array_key_exists('kind', $item) and $item['kind'] == 't3' and array_key_exists('data', $item)) {
            $return_data = GetBestRedditMediaLink($item['data']);
            break;
        }
        elseif (array_key_exists('data', $item) and array_key_exists('children', $item['data'])) {
            return SeekRedditMedia($item['data']['children'], $return_data);
        }
    }
    return $return_data;
}

function GetBestRedditMediaLink($data) {
    if (array_key_exists('url', $data)) {
        $data['media_url'] = $data['url'];
    }
    elseif (array_key_exists('gifv', $data)) {
        $data['media_url'] = $data['gifv'];
    }
    elseif (array_key_exists('gif', $data)) {
        $data['media_url'] = $data['gif'];
    }
    elseif (array_key_exists('mp4', $data)) {
        $data['media_url'] = $data['mp4'];
    }
    else {
        $data['media_url'] = 'https://placehold.it/350x150?text=Could+not+find+reddit+media';
    }
    # The URL reddit uses for their own upload server is not downloadable.  Switch to the source if possible.
    if (preg_match("/\/i.reddituploads.com\//i", $data['media_url'])) {
        if (array_key_exists('preview', $data) && array_key_exists('images', $data['preview'])) {
            $source_url = FindRedditMediaSource($data['preview']['images']);
            if (! is_null($source_url)) {
                $data['media_url'] = $source_url;
            }
        }
    }
    return $data;
}

function FindRedditMediaSource($imageObj) {
    $returnVar = NULL;
    foreach ($imageObj as $data) {
        if(array_key_exists('source', $data) && array_key_exists('url', $data['source'])) {
            $returnVar = $data['source']['url'];
            break;
        }
    }
    return $returnVar;
}

function GetImgurAPIKey() {
    $key = getenv('IMGUR_API_KEY');
    SendLog("Getting key");
    if(is_null($key) || strlen($key)<1) {
        throw new Exception("No IMGUR_API_KEY supplied", 101);
    }
    SendLog("Returning key: $key");
    return $key;
}

function FindGfycatURL($url) {
    SendLog("Getting Gfycat Information");
    $GfycatID = GetGfycatID($url);
    $api_url = 'http://gfycat.com/cajax/get/' . $GfycatID;
    SendLog("API URL: $api_url");
    $json_data = json_decode(G\fetch_url($api_url), TRUE);
    if(!is_null($json_data['gfyItem'])) {
        $info = $json_data['gfyItem'];
        if(!is_null($info['gifUrl'])) {
            $url = $info['gifUrl'];
        }
        elseif(!is_null($info['webmUrl'])) {
            $url = $info['webmUrl'];
        }
    }
    return $url;
}

function FindImgurURL($url) {
    SendLog("Getting Imgur Information");
    if (preg_match("@/a/@i", $url)) {
        return; // leave albums alone
    }
    elseif (preg_match("@/gallery/@i", $url)) {
        return; // leave galleries alone
    }
    $ImgurID = GetImgurID($url);
    $api_url = "https://api.imgur.com/3/image/${ImgurID}";
    SendLog("API URL: $api_url");
    $imgur_api_key = GetImgurAPIKey();
    $imgur_opts = array(
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPHEADER => array("Authorization: Client-ID $imgur_api_key")
    );
    $json_data = G\getUrlHeaders($api_url, $imgur_opts);
    $imgurJSON = $json_data['raw'];
    $imgurData = json_decode($imgurJSON,true);
    if(!is_null($imgurData['data'])) {
        $info = $imgurData['data'];
        if(!is_null($info['gifv'])) {
            $url = $info['gifv'];
        }
        elseif(!is_null($info['gifv'])) {
            $url = $info['gifv'];
        }
        elseif(!is_null($info['link'])) {
            $url = $info['link'];
        }
        elseif(!is_null($info['mp4'])) {
            $url = $info['mp4'];
        }
    }
    SendLog("Returning URL: $url");
    return $url;
}

function GetGfycatID($url) {
    preg_match('/gfycat\.com\/([^\/]+)/i', $url, $matches);
    $gfycat_id = $matches[1];
    # get rid of "#" on the end
    $gfycat_id = explode('#', $gfycat_id)[0];
    SendLog("Gfycat ID found: $gfycat_id");
    return $gfycat_id;
}

function GetImgurID($url) {
    preg_match('/imgur\.com\/([a-zA-Z0-9]{5,8})/i', $url, $matches);
    $imgur_id = $matches[1];
    SendLog("Imgur ID found: $imgur_id");
    return $imgur_id;
}

function SendLog($msg = "Error") {
    error_log("log: $msg", 0);
}
