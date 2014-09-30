<?php

namespace Complement\Curl;

use Illuminate\Support\ServiceProvider;

class CurlServiceProvider extends ServiceProvider
{

	protected $defer = true;

	public function boot()
	{
		$this->package( 'complement/curl' );
	}

	public function register()
	{
		$this->app->bindShared( 'curl', function( $app )
		{
			return new Request( $app );
		} );
	}

	public function provides()
	{
		return array( 'curl' );
	}

}
