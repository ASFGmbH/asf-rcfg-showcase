<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfiguratorAssets
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        if (!$this->isConfiguratorPage()) {
            return;
        }

        wp_enqueue_script(
            'asf-rcfg-showcase-preview-refresh',
            ASF_RCFG_SHOWCASE_URL . 'assets/js/configurator-preview-refresh.js',
            [],
            ASF_RCFG_SHOWCASE_VERSION,
            true
        );

        wp_localize_script(
            'asf-rcfg-showcase-preview-refresh',
            'AsfRcfgShowcasePreview',
            [
                'restRoutePart' => $this->getRestRoutePart(),
                'currentId' => isset($_GET['id'])
                    ? strtoupper(sanitize_text_field(wp_unslash((string) $_GET['id'])))
                    : '',
                'imageBaseUrl' => $this->getRingImageBaseUrl(),
            ]
        );
    }

    private function isConfiguratorPage(): bool
    {
        if (is_page('ringkonfiguration')) {
            return true;
        }

        global $post;

        if ($post instanceof \WP_Post && has_shortcode((string) $post->post_content, '3D-Trauringkonfigurator')) {
            return true;
        }

        return isset($_GET['id']) && is_singular();
    }

    private function getRestRoutePart(): string
    {
        $namespace = defined('ONE_RCFG_REST_NAMESPACE')
            ? trim((string) ONE_RCFG_REST_NAMESPACE, '/')
            : 'oneApi/rcfg/v3';

        return '/' . $namespace . '/api';
    }

    private function getRingImageBaseUrl(): string
    {
        $uploadDir = wp_upload_dir();

        if (!empty($uploadDir['error']) || empty($uploadDir['baseurl'])) {
            return '';
        }

        return trailingslashit((string) $uploadDir['baseurl']) . 'ringkonfiguration/';
    }
}