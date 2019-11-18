<?php
/**
 * Helpdesk Support plugin for Craft CMS 3.x
 *
 * Helpdesk support integrations for Craft CMS
 *
 * @link      https://jarrodnix.me
 * @copyright Copyright (c) 2019 Jarrod D Nix
 */

namespace jrrdnx\helpdesksupport\services;

use jrrdnx\helpdesksupport\HelpdeskSupport;

use Craft;
use craft\base\Component;

/**
 * Core Service
 *
 * All of your plugin's business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Jarrod D Nix
 * @package   HelpdeskSupport
 * @since     0.1.0
 */
class Core extends Component
{
	// Public Methods
    // =========================================================================

    /**
     * Verify to make sure we have the settings defined that we need to function
     *
     * From any other plugin file, call it like this:
     *
     *     HelpdeskSupport::$plugin->core->getApiService()
     *
     * @return mixed
     */
    public function getApiService()
    {
		$apiService		= "";
		$apiProvider	= HelpdeskSupport::$plugin->getSettings()->getApiProvider();
		$apiDomain		= HelpdeskSupport::$plugin->getSettings()->getApiDomain();
		$apiUsername	= HelpdeskSupport::$plugin->getSettings()->getApiUsername();
		$apiToken		= HelpdeskSupport::$plugin->getSettings()->getApiToken();
		if($apiProvider == "freshdesk")
		{
			// Freshdesk requires a domain and a token
			if(!$apiDomain || !$apiToken)
			{
				return null;
			}
			return "freshdesk";
		}
		else
		if($apiProvider == "teamworkdesk")
		{
			// Teamwork Desk requires a domain and a token
			if(!$apiDomain || !$apiToken)
			{
				return null;
			}
			return "teamworkDesk";
		}
		else
		if($apiProvider == "zendesksupport")
		{
			// Zendesk Support requires a domain, a username, and a token
			if(!$apiDomain || !$apiUsername || !$apiToken)
			{
				return null;
			}
			return "zendeskSupport";
		}
		else
		{
			return null;
		}
	}

	/**
     * Establishes a new cURL session to a specified url w/ authentication options
     *
     * From any other plugin file, call it like this:
     *
     *     HelpdeskSupport::$plugin->core->curlInit()
     *
     * @return mixed
     */
    public function curlInit(string $url, int $authOption, string $authString)
    {
		if(!$url || !$authOption || !$authString)
		{
			return null;
		}

		$headers	= array();
		$curl		= curl_init();

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, $authOption, $authString);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		return $curl;
	}

	/**
	 * Executes a given cURL session
	 *
	 * From any other plugin file, call it like this:
     *
     *     HelpdeskSupport::$plugin->core->curlExec()
	 *
	 * @return mixed
	 */
	public function curlExec($curl)
	{
		$response = curl_exec($curl);
		$responseInfo = curl_getinfo($curl);

		return array(
			"http_code" => $responseInfo["http_code"],
			"data" => $response
		);
	}
}
