<?php
/**
 * Helpdesk Support plugin for Craft CMS 3.x
 *
 * Helpdesk support integrations for Craft CMS
 *
 * @link      https://jarrodnix.me
 * @copyright Copyright (c) 2019 Jarrod D Nix
 */

namespace jrrdnx\helpdesksupport\models;

use jrrdnx\helpdesksupport\HelpdeskSupport;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * HelpdeskSupport Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it's passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Jarrod D Nix
 * @package   HelpdeskSupport
 * @since     0.1.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
	public $apiProvider = '';
	public $apiDomain = '';
	public $apiUsername = '';
	public $apiToken = '';

    // Public Methods
	// =========================================================================

	public function getApiProvider(): string
	{
		return strtolower(preg_replace("/[^A-Za-z0-9]/", '', Craft::parseEnv($this->apiProvider)));
	}

	public function getApiDomain(): string
    {
        return Craft::parseEnv($this->apiDomain);
	}

	public function getApiUsername(): string
    {
        return Craft::parseEnv($this->apiUsername);
	}

	public function getApiToken(): string
	{
		return Craft::parseEnv($this->apiToken);
	}

	public function behaviors()
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['apiProvider', 'apiDomain', 'apiUsername', 'apiToken'],
            ],
        ];
    }

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
			['apiProvider', 'string'],
			['apiProvider', 'default', 'value' => ''],
            ['apiDomain', 'string'],
			['apiDomain', 'default', 'value' => ''],
			['apiUsername', 'string'],
			['apiUsername', 'default', 'value' => ''],
			['apiToken', 'string'],
			['apiToken', 'default', 'value' => ''],
        ];
    }
}
