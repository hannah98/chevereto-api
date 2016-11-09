# Chevereto API

This is an override to the API to extract media links from sites like reddit and upload the media into Chevereto.

## Installation

Just copy the route.api.php file into the ./app/routes/overrides directory.

### Using a different endpoint

You can choose to not override the default Chevereto API endpoint (yourserver.com/api) and use your own endpoint.  Just rename the "api" portion of the filename to a name of your choice.

For example, if you want your upload endpoint to be yourserver.com/myapi, rename the route.api.php file to route.myapi.php.

#### Caution

If you decide to use a custom endpoint name, by default Chevereto will require you to login to use the endpoint (Currently the /api/ endpoint does not require a login since it uses the API key).  If you want to enable a custom endpoint, like /myapi/ then you must enable it manually in the ./app/loader.php file by following these steps:

* Open the ```./app/loader.php``` file
* Look for the comment: ```// Website privacy mode```
* You should see a line that looks like this, which allows endpoints to not require login:
```
$allowed_requests = ['api', 'login', 'logout', 'image', 'album', 'page', 'account', 'connect', 'json']; // json allows endless scrolling for privacy link
```
* Simply add your new endpoint into the list.  If your new endpoint is ```myapi```` then the change would look like this:
```
$allowed_requests = ['myapi','api', 'login', 'logout', 'image', 'album', 'page', 'account', 'connect', 'json']; // json allows endless scrolling for privacy link
```

## Usage

This endpoint uses the Imgur API to download Imgur media.  As such, you will need to provide an Imgur API key by setting the environment variable $IMGUR_API_KEY.  (Note, this is not your Chevereto API key).

Simply call the REST API endpoint with your API KEY and the URL of the post that has media you want to save.  For example:

```
http://yourserver.com/myapi/1/upload/?key=YOURAPIKEY&format=json&source=https://www.reddit.com/r/ChildrenFallingOver/comments/551w0u
```

This will parse the Reddit post, find the media link, and upload that media to your Chevereto server.
