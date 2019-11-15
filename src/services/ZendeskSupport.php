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
     * Get the user object for the currently logged in user
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->getCurrentUser()
     *
     * @return mixed
     */
    public function getCurrentUser()
    {
		$response = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "users/search", "get", array("query" => "jnix@reusserdesign.com"));//Craft::$app->getUser()->getIdentity()->email));
		if($response["http_code"] !== 200)
		{
			return null;
		}

		return json_decode($response["data"])->users[0];
	}

	/**
	 * Get all tickets for the given user
	 *
	 * 		HelpdeskSupport::$plugin->zendeskSupport->getTicketsForUser($userId)
	 */
	public function getTicketsForUser(int $userId)
	{
		$tickets = array();

		$requested = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "users/" . $userId . "/tickets/requested", "get");
		if($requested["http_code"] == 200)
		{
			foreach(json_decode($requested["data"])->tickets as $ticket)
			{
				$tickets[$ticket->id] = $ticket;
			}
		}

		$ccd = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "users/" . $userId . "/tickets/ccd", "get");
		if($ccd["http_code"] == 200)
		{
			foreach(json_decode($ccd["data"])->tickets as $ticket)
			{
				$tickets[$ticket->id] = $ticket;
			}
		}

		$assigned = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "users/" . $userId . "/tickets/assigned", "get");
		if($assigned["http_code"] == 200)
		{
			foreach(json_decode($assigned["data"])->tickets as $ticket)
			{
				$tickets[$ticket->id] = $ticket;
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
     * Get the ticket object for the requested ticket ID
     *
     *     HelpdeskSupport::$plugin->zendeskSupport->getTicket($ticketId)
     *
     * @return mixed
     */
	public function getTicket(int $ticketId, int $userId)
	{
		// Get ticket info; sideload user info
		$ticket = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "tickets/" . $ticketId, "get", array("include" => "users"));
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
		$comments = HelpdeskSupport::$plugin->core->curlInit("zendeskSupport", "tickets/" . $ticketId . "/comments", "get", array("include" => "users"));
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

		// Add author info to each comment
		foreach($comments->comments as &$comment)
		{
			foreach($comments->users as $user)
			{
				if($user->id == $comment->author_id)
				{
					$comment->author = $user->name;
					$comment->authorImg = @$user->photo->content_url;
				}
			}
		}

		$ticket = $ticket->ticket;
		$ticket->comments = $comments->comments;

		// Normalize ticket properties for list view
		$ticket->hsSubject = $ticket->subject ? $ticket->subject : $ticket->description;
		$ticket->hsCreatedAt = $ticket->created_at;
		$ticket->hsUpdatedAt = $ticket->updated_at;

		return $ticket;
	}
}
