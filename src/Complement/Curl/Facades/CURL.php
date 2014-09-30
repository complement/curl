<?php

namespace Complement\Curl\Facades;

use Illuminate\Support\Facades\Facade;

class CURL extends Facade
{

	protected static function getFacadeAccessor()
	{
		return 'curl';
	}

}
