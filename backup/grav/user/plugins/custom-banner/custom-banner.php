<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

/**
 * Class CustomBannerPlugin
 * @package Grav\Plugin
 */
class CustomBannerPlugin extends Plugin
{
    private const CUSTOM_BANNER_DISMISS = 'custom-banner-dismiss';

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                // Uncomment following line when plugin requires Grav < 1.7
                // ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Composer autoload.
     *is
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Do not show banner in admin
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminSave' => ['onAdminSave', 0],
            ]);
            return;
        }

        // Do not continue if banner has been dismissed
        if (isset($_COOKIE[self::CUSTOM_BANNER_DISMISS])) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ]);
    }

    public function onAdminSave(): void
    {
        // When updating plugin settings delete "dismiss" cookie
        if ($this->grav['uri']->basename() == 'custom-banner') {
            if (isset($_COOKIE[self::CUSTOM_BANNER_DISMISS])) {
                setcookie(self::CUSTOM_BANNER_DISMISS, 'false', time()-1, $this->grav['uri']->rootUrl());
            }
        }
    }

    public function onAssetsInitialized(): void
    {
        $this->grav['assets']->addDirCss('plugins://custom-banner/css');
        $this->grav['assets']->addDirJs('plugins://custom-banner/js');
    }

    public function onOutputGenerated(): void
    {
        // Get plugin config or fill with default if undefined
        $config = $this->config();
        $config['exclude-pages'] = (array)$config['exclude-pages'];
        $defaults = $this->config->getDefaults()['plugins']['custom-banner'];
        $config = array_merge($defaults, array_filter($config, function ($v) {
            return !(is_null($v));
        }));

        // Validate that all is as expected
        $this->getBlueprint()->validate($config);

        // Generate banner HTML
        // Content
        $content = $config['content'];
        $button_text = $config['button-text'];
        $button_url = $config['button-url'];
        $dismiss_text = $config['dismiss-text'];
        $dismiss_button = ($config['dismiss-button'] ? 'inline-block' : 'none');

        // Style
        $position = $config['position'];
        $bg_colour = $config['bg-colour'];
        $fg_colour = $config['fg-colour'];
        $box_shadow = ($config['box-shadow'] ? '5px 5px 0.75rem gray' : 'none');

        $banner = <<<EOD
        <div class="custom-banner-container" style="$position: 1rem;">
            <div class="custom-banner-body" style="box-shadow: $box_shadow; background-color: $bg_colour;">
                <p class="custom-banner-content" style="color: $fg_colour;">$content</p>
                <span style="flex-grow: 1; min-width: 1rem;"></span>
                <div class="custom-banner-actions">
                    <a class="button custom-banner-dismiss" href="javascript:void(0)" onclick="custom_button_dismiss();" style="display: $dismiss_button;">$dismiss_text</a>
                    <a class="button custom-banner-button" href="$button_url">$button_text</a>
                </div>
            </div>
        </div>
        EOD;

        // Add banner to grav output
        if (!in_array($this->grav['uri']->url(), $config['exclude-pages'])) {
            $output = $this->grav->output;
            $output = preg_replace('/(\<body).*?(\>)/i', '${0}'.$banner, $output, 1);
            $this->grav->output = $output;
        }
    }
}
