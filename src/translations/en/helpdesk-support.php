<?php
/**
 * Helpdesk Support plugin for Craft CMS 3.x
 *
 * Helpdesk support integrations for Craft CMS
 *
 * @link      https://jarrodnix.me
 * @copyright Copyright (c) 2019 Jarrod D Nix
 */

use Craft;

/**
 * Helpdesk Support en Translation
 *
 * Returns an array with the string to be translated (as passed to `Craft::t('helpdesk-support', '...')`) as
 * the key, and the translation as the value.
 *
 * http://www.yiiframework.com/doc-2.0/guide-tutorial-i18n.html
 *
 * @author    Jarrod D Nix
 * @package   HelpdeskSupport
 * @since     0.1.0
 */
return [
	// Common / Widget
	'helpdesk-support'						=> 'Helpdesk Support',
	'helpdesk'								=> 'Helpdesk',
	'tickets'								=> 'Tickets',
	'no-user-found'							=> 'Please contact your Project Manager/Developer to set up a helpdesk account.',

	// Settings
	'invalid-settings'						=> 'Invalid Settings',
	'missing-settings'						=> 'is missing one or more settings. Please',
	'update-settings'						=> 'update your settings',
	'no-settings'							=> 'No settings here, just click Save!',
	'provider'								=> 'Provider',
	'provider-instructions'					=> 'Valid options are \'freshdesk\', \'teamworkdesk\', and \'zendesksupport\'.',
	'domain'								=> 'Domain',
	'domain-instructions'					=> 'Follow the instructions for your helpdesk platform below...',
	'username'								=> 'Username',
	'username-instructions'					=> 'Follow the instructions for your helpdesk platform below...',
	'apitoken'								=> 'API Token/Key',
	'apitoken-instructions'					=> 'Follow the instructions for your helpdesk platform below...',
	'required'								=> 'Required',
	'not-required'							=> 'Not Required',
	'freshdesk'								=> 'Freshdesk',
	'freshdesk-domain-instructions'			=> 'This is the freshdesk.com subdomain associated with your account.',
	'freshdesk-apitoken-instructions'		=> 'Click your avatar and select Profile Settings. Your pre-determined API key will be listed on the right side.',
	'teamwork-desk'							=> 'Teamwork Desk',
	'teamwork-desk-domain-instructions'		=> 'This is the teamwork.com subdomain associated with your account.',
	'teamwork-desk-apitoken-instructions'	=> 'From your Teamwork Desk account, click your avatar and choose "View Profile". On your profile page, choose the API Keys menu (or go to https://YOUR-DOMAIN.teamwork.com/desk/myprofile/apikeys) and create a new <strong><em>v1</em></strong> API key or copy your existing API Key here.',
	'zendesk-support'						=> 'Zendesk Support',
	'zendesk-support-domain-instructions'	=> 'This is the zendesk.com subdomain associated with your account.',
	'zendesk-support-username-instructions'	=> 'The email address or the owner or administrator for your account. Certain API endpoints require agent-level access, and this ensures that those endspoints can be accessed. Comments will appear as "[Administrator] submitted on behalf of [End User]".',
	'zendesk-support-apitoken-instructions'	=> 'From your Zendesk account, choose the Admin navigation in the sidebar. Then under the Channel heading, choose API (or go to https://YOUR-DOMAIN.zendesk.com/agent/admin/api). Enable Token Access if necessary, then add a new API Token.',

	// Create New Ticket
	'create-new-ticket'						=> 'Create New Ticket',
	'subject'								=> 'Subject',
	'subject-instructions'					=> 'Provide a brief description of your request.',
	'inbox'									=> 'Inbox',
	'inbox-instructions'					=> ' ', // If no instructions are desired, then use a single space; an empty string will result in "inbox-instructions" being displayed to users
	'priority'								=> 'Priority',
	'priority-instructions'					=> 'The urgency with which the ticket should be addressed.',
	'select-priority'						=> 'Select priority...',
	'priority-level-urgent'					=> 'Urgent',
	'priority-level-high'					=> 'High',
	'priority-level-medium'					=> 'Medium',
	'priority-level-normal'					=> 'Normal',
	'priority-level-low'					=> 'Low',
	'priority-level-none'					=> 'None',
	'description'							=> 'Description',
	'description-instructions'				=> 'Please enter the details of your request. Make sure to include specific website urls (e.g. ' . Craft::$app->request->getHostInfo() . '/broken-page) if applicable. A member of our support staff will respond as soon as possible.', // Keep the call to Craft::$app->request->getHostInfo() to show the user an example on the current domain
	'attachments'							=> 'Attachments',
	'attachments-instructions'				=> ' ', // If no instructions are desired, then use a single space; an empty string will result in "attachments-instructions" being displayed to users
	'create-ticket'							=> 'Create Ticket',

	// View Tickets
	'view-ticket'							=> 'View Ticket',
	'view-tickets'							=> 'View Tickets',
	'view-all-tickets'						=> 'View All Tickets',
	'id'									=> 'ID',
	'created'								=> 'Created',
	'last-activity'							=> 'Last Activity',
	'no-tickets-found'						=> 'No tickets found',

	// View Ticket
	'requester'								=> 'Requester',
	'assigned-to'							=> 'Assigned To',
	'status'								=> 'Status',
	'type'									=> 'Type',
	'na'									=> 'N/A', // "Not Applicable"
	'add-to-conversation'					=> 'Add to Conversation',
	'add-reply'								=> 'Add Reply',
];
