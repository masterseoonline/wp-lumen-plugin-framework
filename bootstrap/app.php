<?php
/*
|--------------------------------------------------------------------------
| Require The Composer AutoLoader
|--------------------------------------------------------------------------
| Loaded in mu-plugins or, include here.
*/
require_once __DIR__.'/../vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Bootstrap WP & Autoloader for Artisan
|--------------------------------------------------------------------------
*/
try {
	if(!class_exists('Laravel\Lumen\Application')){
		require_once(realpath(__DIR__."/../../../../wp-load.php"));
	}
} catch (Exception $e) {
	exit('Wp-Lumen: Laravel\Lumen\Application Class not found.  Check wp-load.php path in bootstrap/app.php (17)');
}


/*
|--------------------------------------------------------------------------
| Load Env with Overload Enabled (last plugin loaded will overwrite)
|--------------------------------------------------------------------------
*/
try {
	(new Dotenv\Dotenv(__DIR__.'/../'))->overload();
} catch (Dotenv\Exception\InvalidPathException $e) {
    exit('Wp-Lumen: No Environment Settings (.env) Found.');
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
*/

$app = new Laravel\Lumen\Application(
    realpath(__DIR__.'/../')
);

$app->withEloquent();
//$app->withFacades(true);


/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);


/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

$app->middleware([
	\Illuminate\Session\Middleware\StartSession::class,
]);


$app->routeMiddleware([
	'auth' => App\Http\Middleware\Authenticate::class,
	'start_session' => \Illuminate\Session\Middleware\StartSession::class,
	'share_errors' => \Illuminate\View\Middleware\ShareErrorsFromSession::class,
	'no404s' => App\Http\Middleware\SilenceWp404s::class
]);
/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
*/

$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);


// Add Session ServiceProvider
$app->configure('session');
$app->bind(\Illuminate\Session\SessionManager::class, function ($app) {
	return new \Illuminate\Session\SessionManager($app);
});
$app->register(\Illuminate\Session\SessionServiceProvider::class);

// Add DebugBar ServiceProvider
//$app->register(App\Providers\DebugbarServiceProvider::class);

// Add Wordpress ServiceProvider
$app->register(App\Providers\WordpressServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Include WP Mods
|--------------------------------------------------------------------------
| Here we will register all of the application's WP modifications.
*/
//$files = $app->make('files');
//$files->requireOnce(realpath(__DIR__.'/../cleanup/head.php'));
//$files->requireOnce(realpath(__DIR__.'/../cleanup/rest-api.php'));
//$files->requireOnce(realpath(__DIR__.'/../cleanup/emojis.php'));


/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
*/
$request = Illuminate\Http\Request::capture();

if(!is_admin()){

	$app->router->group([
		'namespace' => 'App\Http\Controllers',
	], function ($router) {
		require __DIR__.'/../routes/web.php';
	});

	if($app->make('config')->get('router.loading') == 'eager'){  //Load before WP
		$response = $app->handle($request);

		if($response->content()){
			$response->send();
			exit($response->status());
		}

	}elseif(is_404()){ //Load after WP

		//Start Router During Template Redirect
		add_action('template_redirect',function() use ($app, $request){
			$response = $app->handle($request);

			if($response->content()){
				$response->send();
				exit($response->status());
			}
		}, 1);
	}
}else{
	$app->handle($request);

}

return $app;