<?php

namespace Complement\Curl;

class Response
{

	protected $_app;
	protected $_results = array();

	public function __construct( $app, $content, $info, $errno, $error )
	{
		$this->_app = $app;

		if ( strpos( $content, "\r\n\r\n" ) !== false )
		{
			list( $header, $content ) = explode( "\r\n\r\n", $content, 2 );

			$this->_results[ 'header' ] = $this->_parseHeader( $header );

			if ( isset( $this->_results[ 'header' ][ 'Set-Cookie' ] ) )
			{
				$this->_results[ 'cookie' ] = $this->_parseCookie( $this->_results[ 'header' ][ 'Set-Cookie' ] );
			}
		}

		$this->_results[ 'content' ] = $content;
		$this->_results[ 'info' ] = $info;
		$this->_results[ 'errno' ] = $errno;
		$this->_results[ 'error' ] = $error;
	}

	public function info( $name = null )
	{
		return $this->_results( __FUNCTION__, $name );
	}

	public function header( $name = null )
	{
		return $this->_results( __FUNCTION__, $name );
	}

	public function cookie( $name = null )
	{
		return $this->_results( __FUNCTION__, $name );
	}

	public function content()
	{
		return $this->_result( __FUNCTION__ );
	}

	public function json( $name = null )
	{
		return $this->_app->arr->get( $this->_app->arr->jsonDecode( $this->content() ), $name );
	}

	public function errno()
	{
		return $this->_result( __FUNCTION__ );
	}

	public function error()
	{
		return $this->_result( __FUNCTION__ );
	}

	protected function _result( $method )
	{
		return $this->_app->arr->get( $this->_results, $method );
	}

	protected function _results( $method, $name = null )
	{
		if ( $name === null )
		{
			return $this->_app->arr->get( $this->_results, $method );
		}
		else
		{
			return $this->_app->arr->get( $this->_results, $method . '.' . $name );
		}
	}

	protected function _parseHeader( $raw, $values = array() )
	{
		$values = array();

		if ( is_array( $raw ) )
		{
			foreach ( $raw AS $_raw )
			{
				$values = array_merge( $values, $this->_parseCookie( $_raw ) );
			}
		}
		else
		{
			$raw = explode( "\n", $raw );

			foreach ( $raw AS $_raw )
			{
				if ( strpos( $_raw, ':' ) !== false )
				{
					list( $name, $value ) = array_map( 'trim', explode( ':', $_raw, 2 ) );

					if ( isset( $values[ $name ] ) )
					{
						if ( !is_array( $values[ $name ] ) )
						{
							$values[ $name ] = array( $values[ $name ] );
						}

						$values[ $name ][] = $value;
					}
					else
					{
						$values[ $name ] = $value;
					}
				}
			}
		}

		return $values;
	}

	protected function _parseCookie( $raw )
	{
		$values = array();

		if ( is_array( $raw ) )
		{
			foreach ( $raw AS $_raw )
			{
				$values = array_merge( $values, $this->_parseCookie( $_raw ) );
			}
		}
		else
		{
			$raw = explode( ';', $raw );

			foreach ( $raw AS $_raw )
			{
				if ( strpos( $_raw, '=' ) !== false )
				{
					list( $name, $value ) = array_map( 'trim', explode( '=', $_raw, 2 ) );

					if ( !in_array( strtolower( $name ), array( 'expires', 'path', 'domain', 'secure', 'httponly' ) ) )
					{
						$values[ $name ] = $value;
					}
				}
			}
		}

		return $values;
	}

}
