# Craft Helpdesk Support

Helpdesk support integrations for Craft CMS. Current integrations provided are [Freshdesk](https://freshdesk.com/), [Teamwork Desk](https://www.teamwork.com/desk/), and [Zendesk Support](https://www.zendesk.com/support/).

[Open an Issue](https://github.com/jrrdnx/craft-helpdesk-support/issues) to request an API integration for your platform!

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require jrrdnx/helpdesk-support

3. In the Control Panel, go to Settings -> Plugins and click the "Install" button for Helpdesk Support.

## Helpdesk Support Overview

This plugin assumes that the email address of the currently logged in Craft user is the email address of your helpdesk platform's user under which to create new tickets and/or reply to existing tickets.

If no matching email address is found, they will be presented with a message to contact their Project Manager/Developer to set up a helpdesk account.

## Configuring Helpdesk Support

In the Control Panel, go to Settings and click on the Helpdesk Support icon to configure this plugin's settings.

Enter your provider, and follow the provider-specific directions to enter your Domain, Username, and/or API Token/Key (depending on your provider, not all of these will be required. If any required settings are not given, users will be presented with a message that one or more settings are missing and a link to update those settings.

## Using Helpdesk Support

Add the Dashboard Widget for a quick look at all active tickets (not Solved, Resolved, Closed, etc) and quick links to Create New Ticket or View All Tickets.

Helpdesk sidebar nav icon will auto-update to the Provider selected in the plugin settings, and clicking this main navigation link will show a list of all of that customer's tickets (including Solved, Resolved, Closed, etc).

Click a ticket's subject to view the details of that ticket, including a sidebar with all details (ID, Requester Name, Date Created and Last Updated, Assignee, etc) and a list of all of the conversations/replies to that ticket, as well as a form to add a reply w/ any attachments.

Or use the main menu's Create New Ticket option to submit a new ticket directly from Craft.

## Helpdesk Support Roadmap

[Open an Issue](https://github.com/jrrdnx/craft-helpdesk-support/issues) to report any bugs or request a new feature or API integration.

Brought to you by [Jarrod D Nix](https://jarrodnix.me)

Icon made by [Smashicons](https://www.flaticon.com/authors/smashicons) from [www.flaticon.com](https://www.flaticon.com)