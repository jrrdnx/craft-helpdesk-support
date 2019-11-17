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
     * Establishes a new cURL session to a specified API endpoint using a GET request
     *
     * From any other plugin file, call it like this:
     *
     *     HelpdeskSupport::$plugin->core->curlInit()
     *
     * @return mixed
     */
    public function curlInit(string $apiService, string $endpoint, string $method = "get", array $options = array())
    {
		if(!$endpoint)
		{
			return null;
		}

		$headers	= array();
		$curl		= curl_init();

		// determine URL and authenticate
		if($apiService === "freshdesk")
		{
			$requestUrl = "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".freshdesk.com/api/v2/" . $endpoint;
			curl_setopt($curl, CURLOPT_USERPWD, HelpdeskSupport::$plugin->getSettings()->getApiToken() . ":ABCXYZ"); // Per Freshdesk API: "If you use the API key, there is no need for a password. You can use any set of characters as a dummy password."
		}
		else
		if($apiService === "teamworkDesk")
		{
			$requestUrl = "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".teamwork.com/desk/v1/" . $endpoint . ($endpoint == "upload/attachment" ? "" : ".json");
			curl_setopt($curl, CURLOPT_USERNAME, HelpdeskSupport::$plugin->getSettings()->getApiToken());
		}
		else
		if($apiService === "zendeskSupport")
		{
			$requestUrl = "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".zendesk.com/api/v2/" . $endpoint . ".json";
			curl_setopt($curl, CURLOPT_USERPWD, HelpdeskSupport::$plugin->getSettings()->getApiUsername() . "/token:" . HelpdeskSupport::$plugin->getSettings()->getApiToken());
		}

		if($method == "get")
		{
			$requestUrl .= "?";
			foreach($options as $option => $value)
			{
				$requestUrl .= $option . "=" . $value;
			}
		}

		curl_setopt($curl, CURLOPT_URL, $requestUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		if($apiService == "zendeskSupport" && $endpoint == "tickets" && $method == "post")
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			$headers[] = "Content-Type: application/json";
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array("ticket" => $options)));
		}
		else
		if($method == "post")
		{
			curl_setopt($curl, CURLOPT_POST, true);
			if($options)
			{
				if($apiService === "teamworkDesk" && isset($options["file"]) && isset($options["uploadType"]) && isset($options["fileName"]))
				{
					$curlFile = curl_file_create($options["file"], $options["uploadType"], $options["fileName"]);
					$options["file"] = $curlFile;
					curl_setopt($curl, CURLOPT_POSTFIELDS, $options);
				}
				else
				if($apiService === "zendeskSupport" && isset($options["file"]) && isset($options["mimeType"]) && isset($options["filename"]))
				{
					$curlFile = curl_file_create($options["file"], $options["mimeType"], $options["filename"]);
					$options["file"] = $curlFile;
					curl_setopt($curl, CURLOPT_POSTFIELDS, $options);
				}
				else
				{
					curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($options)));
				}
			}
		}
		else
		if($method == "put")
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
			if($apiService === "zendeskSupport" && $options)
			{
				$headers[] = "Content-Type: application/json";
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array("ticket" => $options)));
			}
			else
			{
				curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($options)));
			}
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($curl);
		$responseInfo = curl_getinfo($curl);

		return array(
			"http_code" => $responseInfo["http_code"],
			"data" => $response
		);
	}
}
