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
	public $allowAttachmentsOnCreate = true;

	public $statuses = array(
		2 => 'open',
		3 => 'pending',
		4 => 'resolved',
		5 => 'closed'
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
		// Per Freshdesk API: "If you use the API key, there is no need for a password. You can use any set of characters as a dummy password."
		return HelpdeskSupport::$plugin->getSettings()->getApiToken() . ":CRAFTHELPDESKSUPPORT";
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
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("contacts?email=" . Craft::$app->getUser()->getIdentity()->email), $this->getAuthOption(), $this->getAuthString());
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
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "?include=requester"), $this->getAuthOption(), $this->getAuthString());
		$ticket = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($ticket["http_code"] !== 200)
		{
			return null;
		}
		$ticket = json_decode($ticket["data"]);

		// Don't display ticket if user is not the requester
		if($userId != $ticket->requester_id) {
			return null;
		}

		// Add full names for requester and assignee
		$ticket->hsRequester = $ticket->requester->name;

		// Normalize ticket properties for list view
		$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description_text;
		$ticket->status = $this->statuses[$ticket->status];
		$ticket->priority = $this->priorities[$ticket->priority];
		$ticket->hsCreatedAt = $ticket->created_at;
		$ticket->hsUpdatedAt = $ticket->updated_at;

		// Get assigned to user (why can't this be part of the ticket request, Freshdesk?)
		$ticket->hsAssignee = 'N/A';
		if($ticket->responder_id)
		{
			$curl2 = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("agents/" . $ticket->responder_id), $this->getAuthOption(), $this->getAuthString());
			$assignee = HelpdeskSupport::$plugin->core->curlExec($curl2);
			if($assignee["http_code"] === 200)
			{
				$ticket->hsAssignee = json_decode($assignee["data"])->contact->name;
			}
		}

		// Get ticket comments (why can't this be part of the ticket request, Freshdesk?)
		$curl3 = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/conversations"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl3, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		$comments = HelpdeskSupport::$plugin->core->curlExec($curl3);
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
		// Normalize comments, exclude private
		$commentUserIds = array();
		foreach($comments as &$comment)
		{
			if(!$comment->private)
			{
				$comment->hsAuthor = '';
				$comment->hsAuthorImg = '';
				if($comment->user_id == $userId)
				{
					$comment->hsAuthor = $ticket->hsRequester;
					$comment->hsAuthorImg = '';//$comment->createdBy->avatarURL;
				}
				else
				{
					$commentUserIds[$comment->user_id] = $comment->user_id;
				}

				$comment->hsCreatedAt = $comment->created_at;
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
			}
		}
		unset($comment);

		foreach($commentUserIds as $commentUserId)
		{
			$curl4 = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("agents/" . $commentUserId), $this->getAuthOption(), $this->getAuthString());
			$agent = HelpdeskSupport::$plugin->core->curlExec($curl4);
			if($agent["http_code"] === 200)
			{
				foreach($comments as &$comment)
				{
					if(!$comment->private)
					{
						if($comment->user_id == $commentUserId)
						{
							$comment->hsAuthor = json_decode($agent["data"])->contact->name;
							$comment->hsAuthorImg = '';//$comment->createdBy->avatarURL;
						}
					}
				}
				unset($comment);
			}
		}

		foreach($comments as $comment)
		{
			if(!$comment->private)
			{
				$ticket->comments[] = $comment;
			}
		}

		return $ticket;
	}

	/**
     * Builds an array for use in CURLOPT_POSTFIELDS setting when including attachments
     *
     *     HelpdeskSupport::$plugin->freshdesk->buildPostFieldsForAttachments()
     *
     * @return array
     */
	public function buildPostFieldsForAttachments(array $fields, array $assets)
	{
		$data = "";
		$eol = "\r\n";
		$mime_boundary = md5(time());
		foreach($fields as $fieldName => $fieldValue)
		{
			$data .= '--' . $mime_boundary . $eol;
			$data .= 'Content-Disposition: form-data; name="' . $fieldName . '"' . $eol . $eol;
			$data .= $fieldValue . $eol;
		}
		foreach($assets as $asset)
		{
			$data .= '--' . $mime_boundary . $eol;
			$data .= 'Content-Disposition: form-data; name="attachments[]"; filename="' . $asset->getFilename() . '"' . $eol;
			$data .= "Content-Type: " . $asset->getMimeType() . $eol . $eol;
			$data .= file_get_contents($asset->getTransformSource()) . $eol;
		}
		$data .= "--" . $mime_boundary . "--" . $eol . $eol;
		$header = "Content-type: multipart/form-data; boundary=" . $mime_boundary;

		return array(
			$data,
			$header
		);
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
			$postFieldsHeader = $this->buildPostFieldsForAttachments(array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description
			), $attachmentAssets);
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postFieldsHeader[0]);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array($postFieldsHeader[1]));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		}
		else
		if(count($attachmentAssets) == 1)
		{
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description,
				'attachments[]' => curl_file_create($attachmentAssets[0]->getTransformSource(), $attachmentAssets[0]->getMimeType(), $attachmentAssets[0]->getFilename())
			));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		}
		else
		{
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
				'requester_id' => $userId,
				'subject' => $subject,
				'status' => 2,
				'priority' => $priority,
				'source' => 1,
				'description' => $description
			)));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		}
		if($response["http_code"] !== 201)
		{
			return null;
		}

		return json_decode($response["data"]);
	}

	/**
     * POST a ticket update
     *
     *     HelpdeskSupport::$plugin->freshdesk->updateTicket()
     *
     * @return mixed
     */
	public function updateTicket(int $ticketId, string $reply, int $userId, array $attachmentAssets = array())
	{
		if(count($attachmentAssets) > 1)
		{
			$postFieldsHeader = $this->buildPostFieldsForAttachments(array(
				'body' => $reply,
				'user_id' => $userId
			), $attachmentAssets);
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/reply"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postFieldsHeader[0]);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array($postFieldsHeader[1]));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
			if($response["http_code"] !== 201)
			{
				return null;
			}
		}
		else
		if(count($attachmentAssets) == 1)
		{
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/reply"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, array(
				'body' => $reply,
				'user_id' => $userId,
				'attachments[]' => curl_file_create($attachmentAssets[0]->getTransformSource(), $attachmentAssets[0]->getMimeType(), $attachmentAssets[0]->getFilename())
			));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
			if($response["http_code"] !== 201)
			{
				return null;
			}
		}
		else
		{
			$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/reply"), $this->getAuthOption(), $this->getAuthString());
			curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
				'body' => $reply,
				'user_id' => $userId,
			)));
			$response = HelpdeskSupport::$plugin->core->curlExec($curl);
			if($response["http_code"] !== 201)
			{
				return null;
			}
		}

		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
			'status' => 2,
			'source' => 1
		)));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);

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
