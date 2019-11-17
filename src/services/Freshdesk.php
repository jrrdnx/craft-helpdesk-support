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
 * Freshdesk Service
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
class Freshdesk extends Component
{
	public $statuses = array(
		2 => 'Open',
		3 => 'Pending',
		4 => 'Resolved',
		5 => 'Closed'
	);

	public $priorities = array(
		1 => 'Low',
		2 => 'Medium',
		3 => 'High',
		4 => 'Urgent'
	);

    // Public Methods
	// =========================================================================

	/**
	 * Get the url to set as the CURLOPT_URL option
	 *
	 * @return string
	 */
	public function getUrl(string $endpoint)
	{
		return "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".freshdesk.com/api/v2/" . $endpoint;
	}

	/**
	 * Get the authentication method (CURLOPT_USERNAME or CURLOPT_USERPWD)
	 *
	 * @return integer
	 */
	public function getAuthOption()
	{
		return CURLOPT_USERPWD;
	}

	/**
	 * Get the authentication string
	 *
	 * @return string
	 */
	public function getAuthString()
	{
		return HelpdeskSupport::$plugin->getSettings()->getApiToken() . ":ABCXYZ"; // Per Freshdesk API: "If you use the API key, there is no need for a password. You can use any set of characters as a dummy password."
	}

    /**
     * GET the user object for the currently logged in user
     *
     *     HelpdeskSupport::$plugin->freshdesk->getCurrentUser()
     *
     * @return mixed
     */
    public function getCurrentUser()
    {
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("contacts?email=jnix@reusserdesign.com"), $this->getAuthOption(), $this->getAuthString());//Craft::$app->getUser()->getIdentity()->email
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])[0];
	}

	/**
	 * GET all tickets for the given user
	 *
	 * 		HelpdeskSupport::$plugin->freshdesk->getTicketsForUser()
	 */
	public function getTicketsForUser(int $userId, $includeClosed = true)
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets?requester_id=" . $userId), $this->getAuthOption(), $this->getAuthString());
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		$tickets = json_decode($response["data"]);
		$ticketsArray = array();

		// Normalize ticket properties for list view
		foreach($tickets as &$ticket)
		{
			$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
			$ticket->hsCreatedAt = $ticket->created_at;
			$ticket->hsUpdatedAt = $ticket->updated_at;
			$ticket->status = $this->statuses[$ticket->status];

			if($includeClosed || (!$includeClosed && $ticket->status !== "resolved" && $ticket->status !== "closed"))
			{
				$ticketsArray[$ticket->id] = $ticket;
			}
		}

		return $ticketsArray;
	}

	/**
     * GET the ticket object for the requested ticket ID
     *
     *     HelpdeskSupport::$plugin->freshdesk->getTicket()
     *
     * @return mixed
     */
	public function getTicket(int $ticketId, int $userId)
	{
		// Get ticket info
		$ticket = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId), $this->getAuthOption(), $this->getAuthString(), "get", array("include" => "requester"));
		if($ticket["http_code"] !== 200)
		{
			return null;
		}
		$ticket = json_decode($ticket["data"]);
		// var_dump($ticket);
		// exit;

		// Don't display ticket if user is not the requester
		if($userId != $ticket->requester_id) {
			return null;
		}

		// Add full names for requester and assignee
		$ticket->hsRequester = $ticket->requester->name;
		// $ticket->hsAssignee = ($ticket->assignedTo !== null) ? $ticket->assignedTo->firstName . " " . $ticket->assignedTo->lastName : 'N/A';

		// Normalize ticket properties for list view
		$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description_text;
		$ticket->status = $this->statuses[$ticket->status];
		$ticket->priority = $this->priorities[$ticket->priority];
		$ticket->hsCreatedAt = $ticket->created_at;
		$ticket->hsUpdatedAt = $ticket->updated_at;

		// Get ticket comments (why can't this be part of the ticket request, Freshdesk?)
		$comments = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/conversations"), $this->getAuthOption(), $this->getAuthString(), "get");
		if($comments["http_code"] !== 200)
		{
			return null;
		}
		$comments = json_decode($comments["data"]);

		// Add first comment from ticket description, requester, created at, and attachments
		foreach($ticket->attachments as &$attachment)
		{
			$attachment->hsFilename = $attachment->name;
			$attachment->hsUrl = $attachment->attachment_url;
			$attachment->hsSize = $attachment->size;
		}
		$ticket->comments = array(

			array(
				"hsCreatedAt" => $ticket->created_at,
				"hsAuthor" => $ticket->requester->name,
				"hsAuthorImg" => "",
				"hsBody" => $ticket->description,
				"attachments" => $ticket->attachments
			)
		);
		// Normalize comments, exclude non-public or non-message
		foreach($comments as $comment)
		{
			var_dump($comment);
			if(!$comment->private)
			{
				$comment->hsCreatedAt = $comment->created_at;
				$comment->hsAuthor = '';//$comment->createdBy->firstName . " " . $comment->createdBy->lastName;
				$comment->hsAuthorImg = '';//$comment->createdBy->avatarURL;
				$comment->hsBody = $comment->body;
				if($comment->attachments)
				{
					foreach($comment->attachments as &$attachment)
					{
						$attachment->hsFilename = $attachment->name;
						$attachment->hsUrl = $attachment->attachment_url;
						$attachment->hsSize = $attachment->size;
					}
				}
				$ticket->comments[] = $comment;
			}
		}

		return $ticket;
	}

	/**
     * POST an attachment to upload
     *
     *     HelpdeskSupport::$plugin->freshdesk->uploadAttachment()
     *
     * @return mixed
     */
	public function uploadAttachment(int $assetId, int $userId)
	{
		$asset = Craft::$app->assets->getAssetById((int) $assetId);
		$response = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("upload/attachment"), $this->getAuthOption(), $this->getAuthString(), "post", array(
			'file' => $asset->getTransformSource(),
			'fileName' => $asset->getFilename(),
			'userId' => $userId,
			'uploadType' => $asset->getMimeType()
		));
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->attachment->id;
	}

	/**
     * POST a new ticket
     *
     *     HelpdeskSupport::$plugin->freshdesk->createTicket()
     *
     * @return mixed
     */
	public function createTicket(int $userId, string $description, int $priority, string $subject = '', array $attachmentAssets = array())
	{
		if(count($attachmentAssets) > 1)
		{
			$attachments = array();
			foreach($attachmentAssets as $asset)
			{
				$attachments[] = curl_file_create($asset->getTransformSource(), $asset->getMimeType(), $asset->getFilename());
			}
			$response = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString(), "post", array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description,
				'attachments[]' => $attachments
			));
		}
		else
		if(count($attachmentAssets) == 1)
		{
			$response = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString(), "post", array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description,
				'attachments[]' => curl_file_create($attachmentAssets[0]->getTransformSource(), $attachmentAssets[0]->getMimeType(), $attachmentAssets[0]->getFilename())
			));
		}
		else
		{
			$response = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString(), "post", array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description
			));
		}
		var_dump($response);
		exit;
		if($response["http_code"] !== 201)
		{
			return null;
		}

		var_dump(json_decode($response["data"]));
		return json_decode($response["data"]);
	}

	/**
	 * Return a list of priorty options for a selectField form element
	 *
	 * @return array
	 */
	public function getPriorityOptions()
	{
		$return = array(
			array(
				'label' => 'Select priority...',
				'value' => null,
			)
		);

		foreach($this->priorities as $value => $label)
		{
			$return[] = array(
				'label' => $label,
				'value' => (int) $value
			);
		}

		return $return;
	}
}
