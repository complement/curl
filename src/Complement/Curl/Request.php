<?php

namespace Complement\Curl;

use Closure;

class Request
{

	public static $settings = array(
		'header'	 => array(
			'Accept'			 => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language'	 => 'en-us,en;q=0.5',
			'Accept-Charset'	 => 'UTF-8,*',
			'Expect'			 => '',
		),
		'userAgent'	 => 'Mozilla/5.0 (Windows NT 5.1; rv:9.0) Gecko/20100101 Firefox/9.0',
		'encoding'	 => 'gzip,deflate',
	);
	protected $_app;
	protected $_settings = array();
	protected $_handlers = array();
	protected $_response = false;

	public function __construct( $app )
	{
		$this->_app = $app;
	}

	public function make( $url = null )
	{
		$curl = new static( $this->_app );

		if ( $url !== null )
		{
			$curl->url( $url );
		}

		return $curl;
	}

	public function GET( $url = null )
	{
		return $this->make( $url )->method( 'GET' );
	}

	public function POST( $url = null )
	{
		return $this->make( $url )->method( 'POST' );
	}

	public function PUT( $url = null )
	{
		return $this->make( $url )->method( 'PUT' );
	}

	public function DELETE( $url = null )
	{
		return $this->make( $url )->method( 'DELETE' );
	}

	public function method( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function url( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function referer( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function userAgent( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function encoding( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function proxy( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function timeout( $value = null )
	{
		return $this->_setting( __FUNCTION__, $value );
	}

	public function param( $name = null, $value = null )
	{
		return $this->_settings( __FUNCTION__, $name, $value );
	}

	public function cookie( $name = null, $value = null )
	{
		return $this->_settings( __FUNCTION__, $name, $value );
	}

	public function header( $name = null, $value = null )
	{
		return $this->_settings( __FUNCTION__, $name, $value );
	}

	public function option( $name = null, $value = null )
	{
		return $this->_settings( __FUNCTION__, $name, $value );
	}

	public function complete( $value = null )
	{
		if ( $value === null )
		{
			$this->_app->arr->get( $this->_settings, __FUNCTION__ );
		}

		if ( $value instanceof Closure )
		{
			$this->_settings[ __FUNCTION__ ] = $value;
		}
		else
		{
			throw new \InvalidArgumentException();
		}

		return $this;
	}

	protected function _setting( $method, $value = null )
	{
		if ( $value === null )
		{
			return $this->_app->arr->get( $this->_settings, $method );
		}

		$this->_settings[ $method ] = $value;

		return $this;
	}

	protected function _settings( $method, $name = null, $value = null )
	{
		if ( is_array( $name ) )
		{
			foreach ( $name AS $_name => $_value )
			{
				$this->_settings[ $method ][ $_name ] = $_value;
			}

			return $this;
		}

		if ( $name === null )
		{
			return $this->_app->arr->get( $this->_settings, $method );
		}
		elseif ( $value === null )
		{
			return $this->_app->arr->get( $this->_settings, $method . '.' . $name );
		}

		$this->_settings[ $method ][ $name ] = $value;

		return $this;
	}

	public function handler( $name = null, $value = null )
	{
		if ( is_array( $name ) )
		{
			foreach ( $name AS $_name => $_value )
			{
				if ( $_value instanceof static )
				{
					$this->_handlers[ $_name ] = $_value;
				}
				else
				{
					throw new \InvalidArgumentException();
				}
			}

			return $this;
		}

		if ( $name === null )
		{
			return $this->_handlers;
		}
		elseif ( $value === null )
		{
			return $this->_app->arr->get( $this->_handlers, $name );
		}

		if ( $value instanceof static )
		{
			$this->_handlers[ $name ] = $value;
		}
		else
		{
			throw new \InvalidArgumentException();
		}

		return $this;
	}

	public function response( $value = null )
	{
		if ( $value === null )
		{
			return $this->_response;
		}

		if ( $value instanceof Response )
		{
			$this->_response = $value;
		}
		else
		{
			throw new \InvalidArgumentException();
		}

		return $this;
	}

	public function request()
	{
		$chs = array();
		$handlers = array();
		$results = array();

		// Prepare the CURL Settings

		if ( $this->_handlers )
		{
			foreach ( $this->_handlers AS $_name => $_handler )
			{
				if ( $_handler->_handlers )
				{
					throw new \InvalidArgumentException();
				}

				if ( $this->_settings )
				{
					$_handler->_settings = $this->_app->arr->merge( $this->_settings, $_handler->_settings );
				}

				$chs[ $_name ] = $_handler->_ch();
				$handlers[ $_name ] = $_handler;
			}
		}
		else
		{
			$chs[] = $this->_ch();
			$handlers[] = $this;
		}

		if ( count( $chs ) > 1 )
		{
			// Request multiple handlers

			$mh = curl_multi_init();

			foreach ( $chs AS $_ch )
			{
				curl_multi_add_handle( $mh, $_ch );
			}

			do
			{
				$status = curl_multi_exec( $mh, $active );
			}
			while ( $status == CURLM_CALL_MULTI_PERFORM );

			while ( $active && $status == CURLM_OK )
			{
				if ( curl_multi_select( $mh ) != -1 )
				{
					do
					{
						$status = curl_multi_exec( $mh, $active );
					}
					while ( $status == CURLM_CALL_MULTI_PERFORM );
				}
			}

			foreach ( $chs AS $_name => $_ch )
			{
				$results[ $_name ] = array( curl_multi_getcontent( $_ch ), curl_getinfo( $_ch ), 0, '' );
			}

			while ( ( $info = curl_multi_info_read( $mh ) ) !== false )
			{
				if ( isset( $info[ 'handle' ], $info[ 'result' ] ) )
				{
					$index = array_search( $info[ 'handle' ], $chs );

					if ( $index !== false )
					{
						$results[ $_name ][ 2 ] = $info[ 'result' ];
					}
				}
			}

			foreach ( $chs AS $_name => $_ch )
			{
				$results[ $_name ][ 3 ] = curl_error( $_ch );

				curl_multi_remove_handle( $mh, $_ch );

				curl_close( $_ch );
			}

			curl_multi_close( $mh );
		}
		else
		{
			// Request only one handler

			foreach ( $chs AS $_name => $_ch )
			{
				$results[ $_name ] = array( curl_exec( $_ch ), curl_getinfo( $_ch ), curl_errno( $_ch ), curl_error( $_ch ) );

				curl_close( $_ch );
			}
		}

		// Put back each result to each handler
		// Run the complate callback of each handler

		foreach ( $handlers AS $_name => $_handler )
		{
			if ( $results[ $_name ] )
			{
				$_handler->response( new Response( $this->_app, $results[ $_name ][ 0 ], $results[ $_name ][ 1 ], $results[ $_name ][ 2 ], $results[ $_name ][ 3 ] ) );

				if ( isset( $_handler->_settings[ 'complete' ] ) && $_handler->_settings[ 'complete' ] instanceof Closure )
				{
					call_user_func( $_handler->_settings[ 'complete' ], $_handler );
				}
			}
		}

		return $this;
	}

	protected function _ch()
	{
		$ch = curl_init();

		if ( static::$settings )
		{
			$this->_settings = $this->_app->arr->merge( static::$settings, $this->_settings );
		}

		// Init the options
		$options = isset( $this->_settings[ 'option' ] ) ? $this->_settings[ 'option' ] : array();

		if ( isset( $this->_settings[ 'method' ] ) )
		{
			$options[ CURLOPT_CUSTOMREQUEST ] = strtoupper( $this->_settings[ 'method' ] );
		}

		if ( isset( $this->_settings[ 'url' ] ) )
		{
			$url = $this->_settings[ 'url' ];

			if ( stripos( $url, 'https' ) === 0 )
			{
				$options[ CURLOPT_SSL_VERIFYPEER ] = isset( $options[ CURLOPT_SSL_VERIFYPEER ] ) ? $options[ CURLOPT_SSL_VERIFYPEER ] : 0;
				$options[ CURLOPT_SSL_VERIFYHOST ] = isset( $options[ CURLOPT_SSL_VERIFYHOST ] ) ? $options[ CURLOPT_SSL_VERIFYHOST ] : 0;
			}

			if ( isset( $this->_settings[ 'param' ] ) )
			{
				$method = isset( $this->_settings[ 'method' ] ) ? strtoupper( $this->_settings[ 'method' ] ) : 'GET';
				$param = http_build_query( $this->_settings[ 'param' ] );

				switch ( $method )
				{
					case 'POST':
					case 'PUT':
						$options[ CURLOPT_POST ] = 1;
						$options[ CURLOPT_POSTFIELDS ] = $param;
						break;
					default:
						$url .= ( strpos( $url, '?' ) === false ) ? '?' : '&';
						$url .= $param;
						break;
				}
			}

			$options[ CURLOPT_URL ] = $url;
		}

		if ( isset( $this->_settings[ 'cookie' ] ) )
		{
			$options[ CURLOPT_COOKIE ] = str_replace( '&', '; ', http_build_query( $this->_settings[ 'cookie' ] ) );
		}

		if ( isset( $this->_settings[ 'referer' ] ) )
		{
			$options[ CURLOPT_REFERER ] = $this->_settings[ 'referer' ];
		}

		if ( isset( $this->_settings[ 'userAgent' ] ) )
		{
			$options[ CURLOPT_USERAGENT ] = $this->_settings[ 'userAgent' ];
		}

		if ( isset( $this->_settings[ 'encoding' ] ) )
		{
			$options[ CURLOPT_ENCODING ] = $this->_settings[ 'encoding' ];
		}

		if ( isset( $this->_settings[ 'header' ] ) )
		{
			$header = $this->_settings[ 'header' ];

			if ( $this->_app->arr->isAssoc( $header ) )
			{
				array_walk( $header, function( &$value, $key )
				{
					$value = $key . ':' . $value;
				} );

				$header = array_values( $header );
			}

			$options[ CURLOPT_HTTPHEADER ] = $header;
		}

		if ( isset( $this->_settings[ 'proxy' ] ) && count( $proxy = explode( ':', $this->_settings[ 'proxy' ], 2 ) ) > 1 )
		{
			$options[ CURLOPT_PROXY ] = $proxy[ 0 ];
			$options[ CURLOPT_PROXYPORT ] = $proxy[ 1 ];
		}

		if ( isset( $this->_settings[ 'timeout' ] ) )
		{
			$options[ CURLOPT_TIMEOUT ] = $this->_settings[ 'timeout' ];
		}

		$options[ CURLOPT_HEADER ] = 1;
		$options[ CURLOPT_RETURNTRANSFER ] = 1;

		curl_setopt_array( $ch, $options );

		return $ch;
	}

}
