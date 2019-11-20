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
 * TeamworkDesk Service
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
class TeamworkDesk extends Component
{
	public $allowAttachmentsOnCreate = false;

    // Public Methods
    // =========================================================================

    /**
	 * Get the url to set as the CURLOPT_URL option
	 *
	 * @return string
	 */
	public function getUrl(string $endpoint)
	{
		return "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".teamwork.com/desk/v1/" . $endpoint;
	}

	/**
	 * Get the authentication method (CURLOPT_USERNAME or CURLOPT_USERPWD)
	 *
	 * @return integer
	 */
	public function getAuthOption()
	{
		return CURLOPT_USERNAME;
	}

	/**
	 * Get the authentication string
	 *
	 * @return string
	 */
	public function getAuthString()
	{
		return HelpdeskSupport::$plugin->getSettings()->getApiToken();
	}

	/**
	 * Get a list of current Inboxes
	 *
	 * @return string
	 */
	public function getInboxOptions()
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("inboxes.json"), $this->getAuthOption(), $this->getAuthString());
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		$return = array(
			array(
				'label' => 'Select an Inbox...',
				'value' => null,
			)
		);

		foreach(json_decode($response["data"])->inboxes as $inbox)
		{
			// Exclude the "My Tickets" collection
			if($inbox->id !== 9999999999)
			{
				$return[] = array(
					'label' => $inbox->name,
					'value' => $inbox->id
				);
			}
		}

		return $return;
	}

	/**
     * GET the user object for the currently logged in user
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->getCurrentUser()
     *
     * @return mixed
     */
    public function getCurrentUser()
    {
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("customers/email.json?email=" . Craft::$app->getUser()->getIdentity()->email), $this->getAuthOption(), $this->getAuthString());
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->customer;
	}

	/**
	 * GET all tickets for the given user
	 *
	 * 		HelpdeskSupport::$plugin->teamworkDesk->getTicketsForUser()
	 */
	public function getTicketsForUser(int $userId, $includeClosed = true)
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("customers/" . $userId . "/previoustickets.json"), $this->getAuthOption(), $this->getAuthString());
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		$tickets = json_decode($response["data"])->tickets;
		$ticketsArray = array();

		// Normalize ticket properties for list view
		foreach($tickets as &$ticket)
		{
			$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
			$ticket->hsCreatedAt = $ticket->createdAt;
			$ticket->hsUpdatedAt = $ticket->updatedAt;

			if($includeClosed || (!$includeClosed && $ticket->status !== "solved" && $ticket->status !== "closed"))
			{
				$ticketsArray[$ticket->id] = $ticket;
			}
		}

		return $ticketsArray;
	}

	/**
     * GET the ticket object for the requested ticket ID
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->getTicket()
     *
     * @return mixed
     */
	public function getTicket(int $ticketId, int $userId)
	{
		// Get ticket info
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . ".json"), $this->getAuthOption(), $this->getAuthString());
		$ticket = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($ticket["http_code"] !== 200)
		{
			return null;
		}
		$ticket = json_decode($ticket["data"])->ticket;

		// Don't display ticket if user is not the customer
		if($userId != $ticket->customer->id) {
			return null;
		}

		// Add full names for requester and assignee
		$ticket->hsRequester = $ticket->customer->firstName . " " . $ticket->customer->lastName;
		$ticket->hsAssignee = ($ticket->assignedTo !== null) ? $ticket->assignedTo->firstName . " " . $ticket->assignedTo->lastName : 'N/A';

		// Normalize ticket properties for list view
		$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
		$ticket->hsCreatedAt = $ticket->createdAt;
		$ticket->hsUpdatedAt = $ticket->updatedAt;

		// Normalize comments, exclude non-public or non-message
		$ticket->comments = array();
		foreach($ticket->threads as $comment)
		{
			if($comment->type == "message")
			{
				$comment->hsCreatedAt = $comment->createdAt;
				$comment->hsAuthor = $comment->createdBy->firstName . " " . $comment->createdBy->lastName;
				$comment->hsAuthorImg = $comment->createdBy->avatarURL;
				$comment->hsBody = $comment->body;
				if($comment->attachments)
				{
					foreach($comment->attachments as &$attachment)
					{
						$attachment->hsFilename = $attachment->filename;
						$attachment->hsUrl = $attachment->downloadURL;
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
     *     HelpdeskSupport::$plugin->teamworkDesk->uploadAttachment()
     *
     * @return mixed
     */
	public function uploadAttachment(int $assetId, int $userId)
	{
		$asset = Craft::$app->assets->getAssetById((int) $assetId);
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("upload/attachment"), $this->getAuthOption(), $this->getAuthString());
		$curlFile = curl_file_create($asset->getTransformSource(), $asset->getMimeType(), $asset->getFilename());
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			'file' => $curlFile,
			'fileName' => $asset->getFilename(),
			'userId' => $userId,
			'uploadType' => $asset->getMimeType()
		));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->attachment->id;
	}

	/**
     * POST a new ticket
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->createTicket()
     *
     * @return mixed
     */
	public function createTicket(int $userId, string $description, string $priority, int $inboxId, string $subject = '', array $attachmentTokens = array())
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets.json"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			// 'assignedTo' => '',
			// 'customerEmail' => $user->email,
			// 'customerFirstName' => $user->firstName,
			// 'customerLastName' => $user->lastName,
			'customerId' => $userId,
			'inboxId' => $inboxId,
			'message' => $description,
			// 'previewTest' => '',
			'priority' => $priority,
			// 'source' => '',
			'status' => 'active',
			'subject' => $subject,
			// 'tags' => '',
			// 'notifyCustomer' => '',
			// 'oldThreadId' => '',
			// 'taskId' => ''
		));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"]);
	}

	/**
     * POST a ticket update
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->updateTicket()
     *
     * @return mixed
     */
	public function updateTicket(int $ticketId, string $reply, int $userId, array $attachmentTokens = array())
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . ".json"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query(array(
			'body' => $reply,
			'customerId' => $userId,
			'status' => 'active',
			'attachmentIds' => $attachmentTokens
		))));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"]);
	}

	/**
	 * Return a list of priorty options for a selectField form element
	 *
	 * @return array
	 */
	public function getPriorityOptions()
	{
		return array(
			array(
				'label' => 'Select priority...',
				'value' => null,
			),
			array(
				'label' => 'High',
				'value' => 'high'
			),
			array(
				'label' => 'Medium',
				'value' => 'medium'
			),
			array(
				'label' => 'Low',
				'value' => 'low'
			),
			array(
				'label' => 'None',
				'value' => 'none'
			),
		);
	}
}
