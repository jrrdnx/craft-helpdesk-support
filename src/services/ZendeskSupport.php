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
 * ZendeskSupport Service
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
class ZendeskSupport extends Component
{
    // Public Methods
    // =========================================================================

    /**
	 * Get the url to set as the CURLOPT_URL option
	 *
	 * @return string
	 */
	public function getUrl(string $endpoint)
	{
		return "https://" . HelpdeskSupport::$plugin->getSettings()->getApiDomain() . ".zendesk.com/api/v2/" . $endpoint;
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
		return HelpdeskSupport::$plugin->getSettings()->getApiUsername() . "/token:" . HelpdeskSupport::$plugin->getSettings()->getApiToken();
	}

	/**
     * GET the user object for the currently logged in user
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->getCurrentUser()
     *
     * @return mixed
     */
    public function getCurrentUser()
    {
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("users/search.json?query=" . Craft::$app->getUser()->getIdentity()->email), $this->getAuthOption(), $this->getAuthString());
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->users[0];
	}

	/**
	 * GET all tickets for the given user
	 *
	 * 		HelpdeskSupport::$plugin->zendeskSupport->getTicketsForUser($userId, $includeClosed)
	 */
	public function getTicketsForUser(int $userId, $includeClosed = true)
	{
		$tickets = array();

		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("users/" . $userId . "/tickets/requested.json"), $this->getAuthOption(), $this->getAuthString());
		$requested = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($requested["http_code"] == 200)
		{
			foreach(json_decode($requested["data"])->tickets as $ticket)
			{
				if($includeClosed || (!$includeClosed && $ticket->status !== "solved" && $ticket->status !== "closed"))
				{
					$tickets[$ticket->id] = $ticket;
				}
			}
		}

		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("users/" . $userId . "/tickets/ccd.json"), $this->getAuthOption(), $this->getAuthString());
		$ccd = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($ccd["http_code"] == 200)
		{
			foreach(json_decode($ccd["data"])->tickets as $ticket)
			{
				if($includeClosed || (!$includeClosed && $ticket->status !== "solved" && $ticket->status !== "closed"))
				{
					$tickets[$ticket->id] = $ticket;
				}
			}
		}

		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("users/" . $userId . "/tickets/assigned.json"), $this->getAuthOption(), $this->getAuthString());
		$assigned = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($assigned["http_code"] == 200)
		{
			foreach(json_decode($assigned["data"])->tickets as $ticket)
			{
				if($includeClosed || (!$includeClosed && $ticket->status !== "solved" && $ticket->status !== "closed"))
				{
					$tickets[$ticket->id] = $ticket;
				}
			}
		}

		// Normalize ticket properties for list view
		foreach($tickets as &$ticket)
		{
			$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
			$ticket->hsCreatedAt = $ticket->created_at;
			$ticket->hsUpdatedAt = $ticket->updated_at;
		}

		usort($tickets, function($a, $b){ return strcmp($b->updated_at, $a->updated_at); });

		return $tickets;
	}

	/**
     * GET the ticket object for the requested ticket ID
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->getTicket($ticketId)
     *
     * @return mixed
     */
	public function getTicket(int $ticketId, int $userId)
	{
		// Get ticket info; sideload user info
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . ".json?include=users"), $this->getAuthOption(), $this->getAuthString());
		$ticket = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($ticket["http_code"] !== 200)
		{
			return null;
		}
		$ticket = json_decode($ticket["data"]);

		// Don't display ticket if user is not the requester, assignee, or listed as a collaborator, follower, or email CC
		if($userId != $ticket->ticket->requester_id && $userId != $ticket->ticket->assignee_id && !in_array($userId, $ticket->ticket->collaborator_ids) && !in_array($userId, $ticket->ticket->follower_ids) && !in_array($userId, $ticket->ticket->email_cc_ids))
		{
			return null;
		}

		// Get ticket comments (why can't this be part of the ticket request, Zendesk?)
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . "/comments.json?include=users"), $this->getAuthOption(), $this->getAuthString());
		$comments = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($comments["http_code"] !== 200)
		{
			return null;
		}
		$comments = json_decode($comments["data"]);

		// Add full names for requester and assignee
		foreach($ticket->users as $user)
		{
			if($user->id == $ticket->ticket->requester_id) $ticket->ticket->hsRequester = $user->name;
			if($user->id == $ticket->ticket->assignee_id) $ticket->ticket->hsAssignee = $user->name;
		}

		// Add author info to each comment, normalize
		foreach($comments->comments as &$comment)
		{
			foreach($comments->users as $user)
			{
				if($user->id == $comment->author_id)
				{
					$comment->hsAuthor = $user->name;
					$comment->hsAuthorImg = @$user->photo->content_url;
				}
			}
		}

		$ticket = $ticket->ticket;
		$ticket->comments = $comments->comments;

		// Normalize ticket properties for list view
		$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
		$ticket->hsCreatedAt = $ticket->created_at;
		$ticket->hsUpdatedAt = $ticket->updated_at;

		// Normalize comments, exclude non-public or non-message
		$ticket->comments = array();
		foreach($comments->comments as &$comment)
		{
			if($comment->public && $comment->type == "Comment")
			{
				$comment->hsCreatedAt = $comment->created_at;
				$comment->hsBody = $comment->html_body;
				if($comment->attachments)
				{
					foreach($comment->attachments as &$attachment)
					{
						$attachment->hsFilename = $attachment->file_name;
						$attachment->hsUrl = $attachment->content_url;
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
     *     HelpdeskSupport::$plugin->zendeskSupport->uploadAttachment($assetId, $userId)
     *
     * @return mixed
     */
	public function uploadAttachment(int $assetId, int $userId)
	{
		$asset = Craft::$app->assets->getAssetById((int) $assetId);
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("uploads.json"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			'filename' => $asset->getFilename(),
			'file' => $asset->getTransformSource(),
			'mimeType' => $asset->getMimeType()
		));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 201)
		{
			return null;
		}

		return json_decode($response["data"])->upload->token;
	}

	/**
     * POST a new ticket
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->createTicket()
     *
     * @return mixed
     */
	public function createTicket(int $userId, string $description, string $priority, string $subject = '', array $attachmentTokens = array())
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets.json"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			'subject' => $subject,
			'priority' => $priority,
			'status' => 'new',
			'requester_id' => $userId,
			'comment' => [
				'body' => $description,
				'uploads' => $attachmentTokens
			],
		));
		$response = HelpdeskSupport::$plugin->core->curlExec($curl);
		if($response["http_code"] !== 201)
		{
			return null;
		}

		return json_decode($response["data"])->ticket;
	}

	/**
     * PUT a ticket update
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->updateTicket($ticketId, $reply, $userId, $attachmentTokens)
     *
     * @return mixed
     */
	public function updateTicket(int $ticketId, string $reply, int $userId, array $attachmentTokens = array())
	{
		$curl = HelpdeskSupport::$plugin->core->curlInit($this->getUrl("tickets/" . $ticketId . ".json"), $this->getAuthOption(), $this->getAuthString());
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			'comment' => [
				'body' => $reply,
				'uploads' => $attachmentTokens,
				'author_id' => $userId
			],
			'status' => 'open'
		));
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
				'label' => 'Urgent',
				'value' => 'urgent'
			),
			array(
				'label' => 'High',
				'value' => 'high'
			),
			array(
				'label' => 'Normal',
				'value' => 'normal'
			),
			array(
				'label' => 'Low',
				'value' => 'low'
			)
		);
	}
}
