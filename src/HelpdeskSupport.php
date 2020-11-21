<?php
/**
 * Helpdesk Support plugin for Craft CMS 3.x
 *
 * Helpdesk support integrations for Craft CMS
 *
 * @link      https://jarrodnix.me
 * @copyright Copyright (c) 2019 Jarrod D Nix
 */

namespace jrrdnx\helpdesksupport;

use jrrdnx\helpdesksupport\services\ZendeskSupport as ZendeskSupportService;
use jrrdnx\helpdesksupport\services\TeamworkDesk as TeamworkDeskService;
use jrrdnx\helpdesksupport\services\Freshdesk as FreshdeskService;
use jrrdnx\helpdesksupport\services\Core as CoreService;
use jrrdnx\helpdesksupport\models\Settings;
use jrrdnx\helpdesksupport\widgets\Tickets as TicketsWidget;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Dashboard;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We've made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we're going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Jarrod D Nix
 * @package   HelpdeskSupport
 * @since     0.1.0
 *
 * @property  ZendeskSupportService $zendeskSupport
 * @property  TeamworkDeskService $teamworkDesk
 * @property  FreshdeskService $freshdesk
 * @property  CoreService $core
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class HelpdeskSupport extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * HelpdeskSupport::$plugin
     *
     * @var HelpdeskSupport
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin's migrations, you'll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '0.1.0';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * HelpdeskSupport::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['helpdesk-support/create-new-ticket'] = 'helpdesk-support/tickets/create-new-ticket';
                $event->rules['helpdesk-support/view-tickets'] = 'helpdesk-support/tickets/index';
                $event->rules['helpdesk-support/view-ticket/<ticketId:\d+>'] = 'helpdesk-support/tickets/view-ticket';
            }
        );

        // Register our widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = TicketsWidget::class;
            }
        );

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );

/**
 * Logging in Craft involves using one of the following methods:
 *
 * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
 * Craft::info(): record a message that conveys some useful information.
 * Craft::warning(): record a warning message that indicates something unexpected has happened.
 * Craft::error(): record a fatal error that should be investigated as soon as possible.
 *
 * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
 *
 * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
 * the category to the method (prefixed with the fully qualified class name) where the constant appears.
 *
 * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
 * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
 *
 * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
 */
        Craft::info(
            Craft::t(
                'helpdesk-support',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
	}

	/**
     * Builds the sidebar nav
     *
     * @return \craft\base\Plugin|null
     */
	public function getCpNavItem()
	{
		$subNavs = [];
		$navItem = parent::getCpNavItem();

		$navItem['label'] = \Craft::t("helpdesk-support", "helpdesk");

		// Use actual provider icon
		$navItem['icon'] = "@jrrdnx/helpdesksupport/icon-mask.svg";
		if($this->getSettings()->getApiProvider() == "freshdesk")
		{
			$navItem['icon'] = "@jrrdnx/helpdesksupport/icon-mask-freshdesk.svg";
		}
		else
		if($this->getSettings()->getApiProvider() == "teamworkdesk")
		{
			$navItem['icon'] = "@jrrdnx/helpdesksupport/icon-mask-teamworkdesk.svg";
		}
		else
		if($this->getSettings()->getApiProvider() == "zendesksupport")
		{
			$navItem['icon'] = "@jrrdnx/helpdesksupport/icon-mask-zendesksupport.svg";
		}

		$subNavs['create-new-ticket'] = [
			'label' => \Craft::t("helpdesk-support", "create-new-ticket"),
			'url' => 'helpdesk-support/create-new-ticket',
		];
		$subNavs['view-tickets'] = [
			'label' => \Craft::t("helpdesk-support", "view-tickets"),
			'url' => 'helpdesk-support/view-tickets',
		];

		$navItem = array_merge($navItem, [
			'subnav' => $subNavs,
		]);

		return $navItem;
	}

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin's settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'helpdesk-support/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
