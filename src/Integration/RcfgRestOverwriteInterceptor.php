<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Integration;

use Asf\RcfgShowcase\Repository\ShowcaseCopyRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class RcfgRestOverwriteInterceptor
{
    public function __construct(
        private readonly ShowcaseCopyRepository $copyRepository = new ShowcaseCopyRepository()
    ) {
    }

    public function register(): void
    {
        add_filter('rest_pre_dispatch', [$this, 'forceOverwriteForShowcaseWorkingCopies'], 10, 3);
    }

    /**
     * Interceptet den REST-RPC des 3D-Trauringkonfigurators.
     *
     * Der normale Frontend-Speichern-Button ruft savePreset mit overwrite=false auf.
     * Für von uns erzeugte Arbeitskopien erzwingen wir serverseitig overwrite=true,
     * damit keine IDs wie ABCD-EFGH-1, ABCD-EFGH-2 usw. entstehen.
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function forceOverwriteForShowcaseWorkingCopies($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        if ($result !== null) {
            return $result;
        }

        if (!$this->isRcfgApiRequest($request)) {
            return $result;
        }

        $rpc = sanitize_text_field((string) $request->get_param('rpc'));

        if ($rpc !== 'savePreset') {
            return $result;
        }

        $arguments = $this->decodeRppArguments($request->get_param('rpp'));

        if (!is_array($arguments) || !isset($arguments[0])) {
            return $result;
        }

        $copyId = strtoupper(trim((string) $arguments[0]));

        if ($copyId === '') {
            return $result;
        }

        if (!$this->copyRepository->existsByCopyId($copyId)) {
            return $result;
        }

        // savePreset($id, $preset_0, $preset_1, $imgData, $overwrite = false)
        $arguments[4] = true;

        $encodedArguments = wp_json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($encodedArguments)) {
            return $result;
        }

        $request->set_param('rpp', $encodedArguments);
        $this->copyRepository->touchUpdatedAt($copyId);

        return $result;
    }

    private function isRcfgApiRequest(\WP_REST_Request $request): bool
    {
        $namespace = defined('ONE_RCFG_REST_NAMESPACE')
            ? trim((string) ONE_RCFG_REST_NAMESPACE, '/')
            : 'oneApi/rcfg/v3';

        return $request->get_route() === '/' . $namespace . '/api';
    }

    /**
     * @param mixed $rawArguments
     * @return array<int, mixed>|null
     */
    private function decodeRppArguments($rawArguments): ?array
    {
        if (is_array($rawArguments)) {
            return $rawArguments;
        }

        if (!is_string($rawArguments) || trim($rawArguments) === '') {
            return null;
        }

        $decoded = json_decode($rawArguments, true);

        return is_array($decoded) ? $decoded : null;
    }
}