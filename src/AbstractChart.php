<?php

namespace Argo22\AnalyticChart;

abstract class ComponentAbstract
{
	protected $_identifier;
	protected $_defaultProperties = array();
	static protected $_instanceHolder = array();
	protected $_sessionProcessor = false;

	/**
	 * Class construct
	 * - Set component identifier
	 * - Load assets (javascript and stylesheet files)
	 * - Set session handler
	 */
	public function __construct($id, $assetsLoad, $assetsList,
		$sessionLoad = array()
	) {
		$this->_identifier = $id;
		self::_loadAssets($assetsLoad, $assetsList);
		$this->_setSessionHandler($sessionLoad);

		self::$_instanceHolder[$id] = $this;
	}


	/**
	 * Get available LOD mapping
	 *
	 * @return array
	 */
	static public function getLodMapping()
	{
		return array(
			'hour' => 'hourly',
			'day' => 'daily',
			'month' => 'monthly',
			'week' => 'weekly'
		);
	}


	/**
	 * Get general assets useful for all inheriting components
	 *
	 * @return array
	 */
	static public function getGeneralAssets()
	{
		return array(
			'js' => array(
				'manager.ajax.js',
				'jquery-browser.js',
			),
			'css' => array(),
		);
	}


	/**
	 * Load assets via assetsLoad definitions:
	 * [
	 *	'instance' = <headerAssetsControlInstance>
	 *	'methodCss' = <methodNameToAddCssFile>
	 *	'methodJs' = <methodNameToAddJsFile>
	 * ]
	 *
	 * @param  string					$identifier
	 * @param  array					$assetsLoad
	 */
	static protected function _loadAssets($assetsLoad, $data = array())
	{
		static $generalsLoaded;

		$mandatory = array('instance', 'methodCss', 'methodJs');
		foreach ($mandatory as $key) {
			if (!isset($assetsLoad[$key]) || empty($assetsLoad[$key])) {
				throw new \Exception('Incorrect assets loading params');
			}
		}
		// append js and css files
		$general = self::getGeneralAssets();
		foreach (array('css', 'js') as $assetType) {
			if (is_null($generalsLoaded)) {
				$data[$assetType] = array_merge(
					$data[$assetType],
					$general[$assetType]
				);
			}
			if (!isset($data[$assetType])) {
				continue;
			}
			foreach ($data[$assetType] as $asset) {
				$methodName = "method" . ucfirst($assetType);
				$assetsLoad['instance']->$assetsLoad[$methodName](
					__DIR__ . '/../' . $assetType . '/' . $asset
				);
			}
		}
		$generalsLoaded = true;
	}


	/**
	 * Sets external session processor per instance
	 *
	 * @param  array	$sessionLoad
	 * @return void
	 */
	protected function _setSessionHandler($sessionLoad)
	{
		// no session support
		if (empty($sessionLoad)) {
			return;
		}
		$mandatory = array('instance', 'methodSet', 'methodGet');
		foreach ($mandatory as $key) {
			if (!isset($sessionLoad[$key]) || empty($sessionLoad[$key])) {
				throw new \Exception('Failed to set session handler');
			}
			$this->_sessionProcessor[$key] = $sessionLoad[$key];
		}
	}


	/**
	 * Get component property
	 * - fetch from session if present
	 * - return default otherwise
	 *
	 * @param  string	$name
	 * @return mixed
	 */
	protected function _getProperty($name) {

		// check name param
		if ($name == '') {
			throw new \Exception('Empty property name');
		}

		// fetch from a session if handler and value set
		if ($this->_sessionProcessor) {
			$methodName = $this->_sessionProcessor['methodGet'];
			return $this->_sessionProcessor['instance']->$methodName($name);
		}

		// use default values
		return $this->_getDefaultProperty($name);
	}


	/**
	 * Set component property to a session
	 *
	 * @param  string	$name
	 * @param  mixed	$value
	 * @return self
	 */
	protected function _setProperty($name, $value) {

		// check name param
		if ($name == '') {
			throw new \Exception('Empty property name');
		}

		// store to a session if handler and value set
		if ($this->_sessionProcessor) {
			$methodName = $this->_sessionProcessor['methodSet'];
			$this->_sessionProcessor['instance']->$methodName($name, $value);
		}

		// fluent
		return $this;
	}


	/**
	 * Return default property value or null if not set
	 *
	 * @param  string	$name
	 * @return mixed
	 */
	protected function _getDefaultProperty($name) {

		// check name param
		if ($name == '') {
			throw new \Exception('Empty property name');
		}

		if (array_key_exists($name, $this->_defaultProperties)) {
			return $this->_defaultProperties[$name];
		} else {
			return null;
		}
	}


	/**
	 * Sets a default value for a property. This value is used in case
	 * the property isn't stored in a session.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function _setDefaultProperty($name, $value)
	{
		// check name param
		if ($name == '') {
			throw new \Exception('Empty property name');
		}

		$this->_defaultProperties[$name] = $value;
	}


	/**
	 * Retrieve chart component instance by its identifier
	 *
	 * @param  string	$id
	 * @return null|Object
	 */
	static public function getInstanceById($id)
	{
		if (array_key_exists($id, self::$_instanceHolder)) {
			return self::$_instanceHolder[$id];
		}

		return null;
	}
}
