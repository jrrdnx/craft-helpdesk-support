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
    // Public Methods
    // =========================================================================

    /**
     * GET the user object for the currently logged in user
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->getCurrentUser()
     *
     * @return mixed
     */
    public function getCurrentUser()
    {
		$response = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "customers/email", "get", array("email" => "peter.coppinger@teamwork.com"));//Craft::$app->getUser()->getIdentity()->email
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->customer;
	}

	/**
	 * GET all tickets for the given user
	 *
	 * 		HelpdeskSupport::$plugin->teamworkDesk->getTicketsForUser($userId)
	 */
	public function getTicketsForUser(int $userId)
	{
		$response = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "customers/" . $userId . "/previoustickets", "get");
		if($response["http_code"] !== 200)
		{
			return null;
		}

		$tickets = json_decode($response["data"])->tickets;

		// Normalize ticket properties for list view
		foreach($tickets as &$ticket)
		{
			$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
			$ticket->hsCreatedAt = $ticket->createdAt;
			$ticket->hsUpdatedAt = $ticket->updatedAt;
		}

		return $tickets;
	}

	/**
     * GET the ticket object for the requested ticket ID
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->getTicket($ticketId)
     *
     * @return mixed
     */
	public function getTicket(int $ticketId, int $userId)
	{
		// Get ticket info
		$ticket = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "tickets/" . $ticketId, "get");
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
     *     HelpdeskSupport::$plugin->teamworkDesk->uploadAttachment($assetId, $userId)
     *
     * @return mixed
     */
	public function uploadAttachment(int $assetId, int $userId)
	{
		$asset = Craft::$app->assets->getAssetById((int) $assetId);
		$response = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "upload/attachment", "post", array(
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
     *     HelpdeskSupport::$plugin->teamworkDesk->createTicket()
     *
     * @return mixed
     */
	public function createTicket(int $userId, string $description, string $priority, string $subject = '', array $attachmentTokens = array())
	{
		$response = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "tickets", "post", array(
			// 'assignedTo' => '',
			// 'customerEmail' => $user->email,
			// 'customerFirstName' => $user->firstName,
			// 'customerLastName' => $user->lastName,
			'customerId' => $userId,
			// 'inboxId' => '',
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
		var_dump($response);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->ticket;
	}

	/**
     * POST a ticket update
     *
     *     HelpdeskSupport::$plugin->teamworkDesk->updateTicket($ticketId, $reply, $userId, $attachmentTokens)
     *
     * @return mixed
     */
	public function updateTicket(int $ticketId, string $reply, int $userId, array $attachmentTokens = array())
	{
		$response = HelpdeskSupport::$plugin->core->curlInit("teamworkDesk", "tickets/" . $ticketId, "post", array(
			'body' => $reply,
			'customerId' => $userId,
			'status' => 'active',
			'attachmentIds' => $attachmentTokens
		));
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
