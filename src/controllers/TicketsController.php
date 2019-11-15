<?php
/**
 * Helpdesk Support plugin for Craft CMS 3.x
 *
 * Helpdesk support integrations for Craft CMS
 *
 * @link      https://jarrodnix.me
 * @copyright Copyright (c) 2019 Jarrod D Nix
 */

namespace jrrdnx\helpdesksupport\controllers;

use jrrdnx\helpdesksupport\HelpdeskSupport;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;

/**
 * Tickets Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Jarrod D Nix
 * @package   HelpdeskSupport
 * @since     0.1.0
 */
class TicketsController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'create-new-ticket', 'view-tickets'];

    // Public Methods
	// =========================================================================

	/**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/helpdesk-support/view-tickets
     *
     * @return mixed
     */
    public function actionIndex()
    {
		// Ensure settings are valid and get the chosen provider
		if(!($apiService = HelpdeskSupport::$plugin->core->getApiService()))
		{
			return $this->renderTemplate(
				'helpdesk-support/invalid-settings'
			);
		}

		// Get current user
		$user = HelpdeskSupport::$plugin->{$apiService}->getCurrentUser();
		if(!$user)
		{
			return $this->renderTemplate(
				'helpdesk-support/view-tickets',
				[
					'userFound' => false
				]
			);
		}

		// Get tickets for user
		$tickets = HelpdeskSupport::$plugin->{$apiService}->getTicketsForUser($user->id);

        return $this->renderTemplate(
			'helpdesk-support/view-tickets',
			[
				'userFound' => true,
				'tickets' => $tickets
			]
		);
    }

    /**
     * Handle a request going to our plugin's createNewTicket URL,
     * e.g.: actions/helpdesk-support/create-new-ticket
     *
     * @return mixed
     */
    public function actionCreateNewTicket()
    {
        // Ensure settings are valid and get the chosen provider
		if(!($apiService = HelpdeskSupport::$plugin->core->getApiService()))
		{
			return $this->renderTemplate(
				'helpdesk-support/invalid-settings'
			);
		}

        return $this->renderTemplate(
			'helpdesk-support/create-new-ticket'
		);
    }

    /**
     * Handle a request going to our plugin's viewTicket URL,
     * e.g.: actions/helpdesk-support/view-ticket/XXXXXX
     *
     * @return mixed
     */
    public function actionViewTicket(int $ticketId = null)
    {
		// Return to ticket list if ID not provided
		if(!$ticketId)
		{
			$this->redirect('/admin/helpdesk-support/view-tickets');
		}

        // Ensure settings are valid and get the chosen provider
		if(!($apiService = HelpdeskSupport::$plugin->core->getApiService()))
		{
			return $this->renderTemplate(
				'helpdesk-support/invalid-settings'
			);
		}

		// Get current user
		$user = HelpdeskSupport::$plugin->{$apiService}->getCurrentUser();
		if(!$user)
		{
			// Return to ticket list if user not found
			$this->redirect('/admin/helpdesk-support/view-tickets');
		}

		// Get ticket info
		$ticket = HelpdeskSupport::$plugin->{$apiService}->getTicket($ticketId, $user->id);
		if(!$ticket)
		{
			// Return to ticket list if ticket not found
			return $this->redirect('/admin/helpdesk-support/view-tickets');
		}

        return $this->renderTemplate(
			'helpdesk-support/view-ticket',
			[
				'ticket' => $ticket
			]
		);
    }
}
